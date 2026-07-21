<?php
namespace Boletines\Forms;

use Boletines\Models\ListModel;
use Boletines\Models\Subscriber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página de gestión de suscripción del usuario final.
 *
 * Tres formas de uso:
 *  1) Shortcode  [boletines_preferences]  en cualquier página WP que el admin
 *     haya configurado en Ajustes → Branding → "Página de preferencias".
 *  2) Standalone /?boletines_action=preferences&sid=X&token=Y  (fallback si no
 *     hay página configurada). Devuelve una página HTML completa.
 *  3) Endpoint REST /wp-json/boletines/v1/preferences (POST) para guardar cambios
 *     vía AJAX desde la propia página.
 */
class Preferences {

	public function register(): void {
		add_shortcode( 'boletines_preferences', array( $this, 'shortcode' ) );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		// El standalone se sirve desde Mailer\Tracker (action=preferences).
	}

	public function register_assets(): void {
		wp_register_script(
			'boletines-preferences',
			BOLETINES_URL . 'assets/js/preferences.js',
			array(),
			BOLETINES_VERSION,
			true
		);
		wp_localize_script( 'boletines-preferences', 'BoletinesPreferences', array(
			'restUrl' => esc_url_raw( rest_url( 'boletines/v1/preferences' ) ),
		) );
	}

	public function register_route(): void {
		register_rest_route(
			'boletines/v1',
			'/preferences',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'sid'   => array( 'required' => true ),
					'token' => array( 'required' => true ),
					'lists' => array( 'required' => false ),
					'unsubscribe_all' => array( 'required' => false ),
				),
			)
		);
	}

	public function shortcode( $atts = array() ): string {
		// v2.0.1 — Lectura robusta. Algunos hostings con cache/CDN pierden $_GET
		// tras el redirect del tracker. Probamos varias fuentes en orden.
		$sid   = 0;
		$token = '';

		// 1) $_GET directo (caso normal)
		if ( isset( $_GET['sid'] ) )   $sid   = (int) $_GET['sid'];
		if ( isset( $_GET['token'] ) ) $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		// 2) $_REQUEST (cubre POST si alguna integración los manda así)
		if ( ! $sid && isset( $_REQUEST['sid'] ) )       $sid   = (int) $_REQUEST['sid'];
		if ( ! $token && isset( $_REQUEST['token'] ) )   $token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ) );

		// 3) Parsear del REQUEST_URI por si query_vars fue stripped por otro plugin
		if ( ! $sid || ! $token ) {
			$ru = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
			$q  = parse_url( $ru, PHP_URL_QUERY );
			if ( $q ) {
				parse_str( $q, $parsed );
				if ( ! $sid && ! empty( $parsed['sid'] ) )     $sid   = (int) $parsed['sid'];
				if ( ! $token && ! empty( $parsed['token'] ) ) $token = sanitize_text_field( (string) $parsed['token'] );
			}
		}

		return $this->render( $sid, $token );
	}

	/** Renderiza la página completa (cuando se sirve standalone). */
	public function render_standalone( int $sid, string $token ): void {
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		$accent     = \Boletines\Plugin::form_button_color();
		$accent_fg  = (string) \Boletines\Plugin::get_setting( 'form_button_text_color', '#ffffff' );
		$site_name  = get_bloginfo( 'name' );
		$body       = $this->render( $sid, $token );
		$rest_url   = esc_url_raw( rest_url( 'boletines/v1/preferences' ) );

		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head>';
		echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>' . esc_html__( 'Preferencias de suscripción', 'boletines' ) . ' — ' . esc_html( $site_name ) . '</title>';
		// CSS del plugin (acceso directo, sin pasar por wp_enqueue).
		echo '<link rel="stylesheet" href="' . esc_url( BOLETINES_URL . 'assets/css/form.css?ver=' . BOLETINES_VERSION ) . '">';
		echo '<style>
			body { margin:0; padding:0; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; background:#fff; color:#111827; }
			:root { --bol-accent:' . esc_attr( $accent ) . '; --bol-accent-fg:' . esc_attr( $accent_fg ) . '; }
		</style>';
		echo '</head><body>';
		echo $body;
		// CRÍTICO: definir BoletinesPreferences ANTES del <script src> que lo lee.
		echo '<script>window.BoletinesPreferences = { restUrl: ' . wp_json_encode( $rest_url ) . ' };</script>';
		echo '<script src="' . esc_url( BOLETINES_URL . 'assets/js/preferences.js?ver=' . BOLETINES_VERSION ) . '"></script>';
		echo '</body></html>';
		exit;
	}

	/**
	 * Render principal: devuelve HTML del bloque de gestión.
	 * Valida sid + token antes de mostrar; si falla, mensaje.
	 */
	public function render( int $sid, string $token ): string {
		if ( ! $sid ) {
			$debug = '';
			if ( current_user_can( 'manage_options' ) ) {
				$ru = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
				$qs = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
				$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : '';

				$debug = '<br><br><div style="font-size:12px;color:#6b7280;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:12px;text-align:left;">';
				$debug .= '<strong style="color:#374151;">' . esc_html__( 'Diagnóstico para el administrador', 'boletines' ) . '</strong><br>';
				$debug .= '<code>REQUEST_URI: ' . esc_html( $ru ) . '</code><br>';
				$debug .= '<code>QUERY_STRING: ' . esc_html( $qs ) . '</code><br>';
				$debug .= '<code>HTTP_HOST: ' . esc_html( $host ) . '</code><br><br>';
				$debug .= esc_html__( 'Si QUERY_STRING aparece vacío o incompleto, tu hosting, CDN (Cloudflare, Sucuri) o un plugin de seguridad está pelando los parámetros. Contacta con tu proveedor o desactiva reglas de WAF.', 'boletines' );
				$debug .= '</div>';
			}
			return $this->message_html(
				__( 'Falta el identificador del suscriptor en la URL. Copia y pega el enlace completo del correo.', 'boletines' ) . $debug,
				'error'
			);
		}
		if ( ! $token ) {
			return $this->message_html(
				__( 'Falta el código de verificación en la URL. Copia y pega el enlace completo del correo.', 'boletines' ),
				'error'
			);
		}

		$sub = Subscriber::find_by_id( $sid );
		if ( ! $sub ) {
			return $this->message_html(
				__( 'No encontramos tu suscripción. Si te diste de baja recientemente, vuelve a suscribirte para gestionar tus preferencias.', 'boletines' ),
				'error'
			);
		}
		if ( empty( $sub['token'] ) ) {
			return $this->message_html(
				__( 'Tu suscripción no tiene código de seguridad activo. Vuelve a suscribirte para regenerarlo.', 'boletines' ),
				'error'
			);
		}
		if ( ! hash_equals( (string) $sub['token'], $token ) ) {
			return $this->message_html(
				__( 'El código de verificación no coincide. Por seguridad, pide un nuevo enlace desde el último correo que recibiste.', 'boletines' ),
				'error'
			);
		}

		// Cargar JS y CSS para AJAX.
		wp_enqueue_style( 'boletines-form' );
		$this->enqueue_preferences_js();

		$lists          = array_values( array_filter( ListModel::all(), function ( $l ) { return (int) $l['is_public'] === 1; } ) );
		$selected_lists = array_map( 'intval', Subscriber::list_ids_for( (int) $sub['id'] ) );

		$first_name = $sub['first_name'] ?: '';
		$greeting   = $first_name ? sprintf( __( 'Hola %s', 'boletines' ), $first_name ) : __( 'Hola', 'boletines' );

		ob_start();
		?>
		<div class="bol-preferences" data-bol-preferences data-sid="<?php echo (int) $sub['id']; ?>" data-token="<?php echo esc_attr( $sub['token'] ); ?>">
			<div class="bol-preferences-header">
				<h1><?php esc_html_e( 'Tus suscripciones', 'boletines' ); ?></h1>
				<p>
					<?php echo esc_html( $greeting ); ?>.
					<?php esc_html_e( 'Aquí puedes elegir qué boletines quieres recibir o darte de baja por completo. Tus cambios se guardan al pulsar el botón.', 'boletines' ); ?>
				</p>
			</div>

			<div class="bol-preferences-section">
				<div class="bol-preferences-section-head">
					<h2><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
					<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
						<span style="font-size:14px;color:var(--bol-muted,#6b7280);"><?php esc_html_e( 'Suscribir a todo', 'boletines' ); ?></span>
						<span class="bol-toggle">
							<input type="checkbox" data-bol-all>
							<span class="bol-toggle-slider"></span>
						</span>
					</label>
				</div>

				<?php if ( empty( $lists ) ) : ?>
					<p style="color:var(--bol-muted,#6b7280);"><?php esc_html_e( 'No hay listas disponibles en este momento.', 'boletines' ); ?></p>
				<?php else : ?>
					<div class="bol-preferences-cards">
						<?php foreach ( $lists as $l ) :
							$checked = in_array( (int) $l['id'], $selected_lists, true );
							$desc    = $l['description'] ?: '';
							$cadence = ! empty( $l['cadence_label'] ) ? $l['cadence_label'] : '';
						?>
							<div class="bol-preferences-card">
								<?php if ( $cadence ) : ?>
									<div class="bol-preferences-card-meta"><?php echo esc_html( $cadence ); ?></div>
								<?php endif; ?>
								<h3 class="bol-preferences-card-title"><?php echo esc_html( $l['name'] ); ?></h3>
								<?php if ( $desc ) : ?>
									<p class="bol-preferences-card-desc"><?php echo esc_html( wp_strip_all_tags( $desc ) ); ?></p>
								<?php endif; ?>
								<div class="bol-preferences-card-action">
									<label for="bol-list-<?php echo (int) $l['id']; ?>"><?php echo $checked ? esc_html__( 'Suscrito', 'boletines' ) : esc_html__( 'Suscribirme', 'boletines' ); ?></label>
									<span class="bol-toggle">
										<input type="checkbox" id="bol-list-<?php echo (int) $l['id']; ?>" data-bol-list value="<?php echo (int) $l['id']; ?>" <?php checked( $checked ); ?>>
										<span class="bol-toggle-slider"></span>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="bol-preferences-actions">
				<button type="button" class="bol-form-submit" data-bol-save style="width:auto;padding:12px 32px;">
					<span class="bol-form-submit-label"><?php esc_html_e( 'Guardar preferencias', 'boletines' ); ?></span>
					<span class="bol-form-submit-spinner" aria-hidden="true"></span>
				</button>
				<button type="button" data-bol-unsub-all style="background:transparent;border:0;cursor:pointer;color:var(--bol-muted,#6b7280);text-decoration:underline;font-size:13px;font-family:inherit;padding:8px 12px;">
					<?php esc_html_e( 'Darme de baja de todo', 'boletines' ); ?>
				</button>
			</div>

			<div class="bol-preferences-feedback" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function enqueue_preferences_js(): void {
		// El registro y localize ocurren en register_assets() en wp_enqueue_scripts.
		// Aquí sólo encolamos (funciona también si se llama tarde gracias a footer scripts).
		wp_enqueue_script( 'boletines-preferences' );
	}

	private function message_html( string $msg, string $tone = 'info' ): string {
		wp_enqueue_style( 'boletines-form' );
		return '<div class="bol-preferences"><div class="bol-preferences-feedback is-' . esc_attr( $tone ) . '">' . esc_html( $msg ) . '</div></div>';
	}

	/** REST handler para guardar cambios. */
	public function rest_save( \WP_REST_Request $req ) {
		$sid   = (int) $req->get_param( 'sid' );
		$token = (string) $req->get_param( 'token' );

		$sub = Subscriber::find_by_id( $sid );
		if ( ! $sub || ! hash_equals( (string) $sub['token'], $token ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => __( 'Enlace inválido.', 'boletines' ) ), 403 );
		}

		// Modo "darse de baja de todo".
		if ( ! empty( $req->get_param( 'unsubscribe_all' ) ) ) {
			Subscriber::unsubscribe( (int) $sub['id'] );
			Subscriber::detach_all_lists( (int) $sub['id'] );
			return rest_ensure_response( array(
				'success' => true,
				'message' => __( 'Te has dado de baja correctamente. Sentimos verte ir.', 'boletines' ),
				'unsubscribed' => true,
			) );
		}

		$lists_param = $req->get_param( 'lists' );
		$lists       = is_array( $lists_param ) ? array_map( 'intval', $lists_param ) : array();

		// Sincronizar listas: detach all + attach las elegidas.
		Subscriber::detach_all_lists( (int) $sub['id'] );
		if ( ! empty( $lists ) ) {
			Subscriber::attach_lists( (int) $sub['id'], $lists );
		}

		// Si estaba unsubscribed/bounced y ahora elige listas, lo reactivamos.
		if ( ! empty( $lists ) && in_array( $sub['status'], array( Subscriber::STATUS_UNSUBSCRIBED, Subscriber::STATUS_BOUNCED ), true ) ) {
			global $wpdb;
			$wpdb->update(
				\Boletines\Plugin::table( 'subscribers' ),
				array( 'status' => Subscriber::STATUS_CONFIRMED, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => (int) $sub['id'] )
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => empty( $lists )
				? __( 'Preferencias guardadas. No recibirás más correos hasta que vuelvas a suscribirte.', 'boletines' )
				: __( 'Preferencias guardadas. ¡Gracias!', 'boletines' ),
		) );
	}
}
