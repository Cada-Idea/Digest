<?php
namespace Boletines;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap principal del plugin.
 * Registra todos los componentes en el momento adecuado.
 */
final class Plugin {

	/** @var Plugin */
	private static $instance;

	private function __construct() {}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		// Verificar versión de BD y migrar si hace falta.
		if ( get_option( 'boletines_db_version' ) !== BOLETINES_DB_VERSION ) {
			Installer::install();
		}

		// v1.10.1 — Defensive: asegurar que las tablas críticas del módulo
		// Recursos existen aunque la migración haya fallado silenciosa.
		// Sólo comprueba; si faltan, dispara install() de nuevo.
		Installer::ensure_critical_tables();

		// v2.1.1 — Rewrite rules (URLs path-based para preferencias/unsubscribe/confirm).
		// Los CDNs y WAFs suelen pelar query strings; las URLs en path no se pueden
		// filtrar tan fácilmente. Registramos en init para tener query_vars listos.
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );

		// Núcleo - siempre.
		( new Mailer\Cron() )->register();
		( new Mailer\Tracker() )->register();
		( new Forms\Shortcode() )->register();
		( new Forms\Handler() )->register();
		( new Forms\Block() )->register();
		( new Forms\Ajax() )->register();
		( new Forms\Display() )->register();
		( new Forms\Preferences() )->register();
		( new Rest\Api() )->register();
		( new Webhook\Bounce() )->register();
		( new Shortcodes\CampaignBlocks() )->register();

		// v2.0 — módulos nuevos con try/catch defensivo.
		try { ( new Housekeeping\CleanerBootstrap() )->register(); }
		catch ( \Throwable $e ) { error_log( '[Boletines 2.0] Housekeeping fail: ' . $e->getMessage() ); }

		try { ( new Smtp\Mailer() )->register(); }
		catch ( \Throwable $e ) { error_log( '[Boletines 2.0] SMTP fail: ' . $e->getMessage() ); }

		// Módulos Pro (sistema modular para futuras features).
		Modules\Manager::instance()->boot();

		// Integraciones (opcionales).
		if ( class_exists( 'WooCommerce' ) ) {
			( new Integrations\WooCommerce() )->register();
		}
		( new Integrations\WpUsers() )->register();

		// Admin - sólo en wp-admin.
		if ( is_admin() ) {
			( new Admin\Menu() )->register();
			( new Admin\Assets() )->register();
			( new Licensing\Wizard() )->register();
			( new Admin\Pages\Addons() )->register();
			( new Admin\Pages\Health() )->register();

			// v1.9.1 — Banner persistente si hay inconsistencias críticas.
			add_action( 'admin_notices', array( $this, 'maybe_render_critical_banner' ) );
		}
	}

	/**
	 * Banner visible si el detector de inconsistencias encuentra algo crítico.
	 * Sólo lo mostramos fuera de la propia página de Salud (para no duplicar).
	 */
	public function maybe_render_critical_banner(): void {
		$screen = get_current_screen();
		if ( $screen && strpos( (string) $screen->id, 'boletines-health' ) !== false ) {
			return;
		}
		if ( ! Diagnostics\Inconsistency::has_critical() ) {
			return;
		}
		$url = admin_url( 'admin.php?page=boletines-health' );
		echo '<div class="notice notice-error" style="border-left-color:#dc2626;">';
		echo '<p style="display:flex;align-items:center;gap:12px;">';
		echo '<strong style="color:#7f1d1d;">⚠ ' . esc_html__( 'Digest: detectada una inconsistencia crítica en la base de datos.', 'boletines' ) . '</strong>';
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Ir a diagnóstico', 'boletines' ) . '</a>';
		echo '</p>';
		echo '</div>';
	}

	/** Helper centralizado para obtener opciones del plugin. */
	public static function get_setting( string $key, $default = null ) {
		$settings = get_option( 'boletines_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public static function update_setting( string $key, $value ): void {
		$settings           = get_option( 'boletines_settings', array() );
		$settings[ $key ]   = $value;
		update_option( 'boletines_settings', $settings );
	}

	/** Devuelve el nombre completo de una tabla del plugin. */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . BOLETINES_TABLE_PREFIX . $name;
	}

	/**
	 * Detecta el color principal del tema actual.
	 */
	public static function detect_theme_primary_color(): string {
		$preferred = array( 'primary', 'main', 'brand', 'accent', 'foreground', 'contrast' );

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette' ) );
			$theme_pal = isset( $palette['theme'] ) ? $palette['theme'] : ( is_array( $palette ) ? $palette : array() );

			if ( is_array( $theme_pal ) && ! empty( $theme_pal ) ) {
				foreach ( $preferred as $slug ) {
					foreach ( $theme_pal as $color ) {
						if ( isset( $color['slug'], $color['color'] ) && $color['slug'] === $slug ) {
							return self::resolve_color( $color['color'] );
						}
					}
				}
				if ( isset( $theme_pal[0]['color'] ) ) {
					return self::resolve_color( $theme_pal[0]['color'] );
				}
			}
		}

		$cp = get_theme_support( 'editor-color-palette' );
		if ( is_array( $cp ) && ! empty( $cp[0] ) && is_array( $cp[0] ) ) {
			$palette = $cp[0];
			foreach ( $preferred as $slug ) {
				foreach ( $palette as $color ) {
					if ( isset( $color['slug'], $color['color'] ) && $color['slug'] === $slug ) {
						return self::resolve_color( $color['color'] );
					}
				}
			}
			if ( isset( $palette[0]['color'] ) ) {
				return self::resolve_color( $palette[0]['color'] );
			}
		}

		return '';
	}

	private static function resolve_color( string $value ): string {
		$value = trim( $value );
		if ( strpos( $value, 'var(' ) === 0 ) return '';
		return $value;
	}

	public static function form_button_color(): string {
		$setting = (string) self::get_setting( 'form_button_color', 'auto' );
		if ( $setting !== 'auto' && $setting !== '' ) {
			return $setting;
		}
		$detected = self::detect_theme_primary_color();
		return $detected ?: '#2563eb';
	}

	/**
	 * v2.1.1 — Registra las rewrite rules path-based para los endpoints públicos.
	 * Formato: /boletines-action/{preferences|unsubscribe|confirm}/{sid}/{token}/
	 *
	 * Estas URLs son inmunes a reglas de CDN/proxy que pelan query strings
	 * (Cloudflare Strip Query Strings, Sucuri, mod_security, etc.). El query string
	 * original (?boletines_action=...) sigue funcionando como fallback para emails
	 * viejos en circulación.
	 */
	public function register_rewrite_rules(): void {
		add_rewrite_rule(
			'^boletines-action/preferences/([^/]+)/([^/]+)/?$',
			'index.php?boletines_action_path=preferences&bol_sid=$matches[1]&bol_token=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^boletines-action/unsubscribe/([^/]+)/([^/]+)/?$',
			'index.php?boletines_action_path=unsubscribe&bol_sid=$matches[1]&bol_token=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^boletines-action/confirm/([^/]+)/([^/]+)/?$',
			'index.php?boletines_action_path=confirm&bol_sid=$matches[1]&bol_token=$matches[2]',
			'top'
		);
		// v2.1.3 — Clic path-based: {token}/{base64url(destino)}.
		add_rewrite_rule(
			'^boletines-action/click/([^/]+)/([^/]+)/?$',
			'index.php?boletines_action_path=click&bol_token=$matches[1]&bol_click_data=$matches[2]',
			'top'
		);
	}

	/**
	 * v2.1.1 — Query vars expuestas para las rewrite rules path-based.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'boletines_action_path';
		$vars[] = 'bol_sid';
		$vars[] = 'bol_token';
		$vars[] = 'bol_click_data';
		return $vars;
	}
}
