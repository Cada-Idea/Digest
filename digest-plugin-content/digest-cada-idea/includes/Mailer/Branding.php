<?php
namespace Boletines\Mailer;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Branding global para todos los correos del plugin.
 * Envuelve el body_html con cabecera (logo) y pie (redes sociales + texto custom + legal).
 *
 * Si se le pasa `subscriber` en $vars, genera automáticamente los links
 * de "Gestionar preferencias" + "Darse de baja". Así basta llamar:
 *
 *   Branding::wrap( $html, array( 'subscriber' => $sub ) );
 *
 * y el footer queda completo, en TODOS los correos (campañas, confirmaciones,
 * automatizaciones, cumpleaños) — sin código duplicado.
 */
class Branding {

	public static function wrap( string $body_html, array $vars = array() ): string {
		if ( stripos( $body_html, '<html' ) !== false || stripos( $body_html, '<body' ) !== false ) {
			return self::resolve_placeholders( $body_html );
		}

		$accent     = Plugin::form_button_color();
		$accent_fg  = (string) Plugin::get_setting( 'form_button_text_color', '#ffffff' );
		$brand_col  = (string) Plugin::get_setting( 'email_brand_color', '' );
		if ( $brand_col === '' ) $brand_col = $accent;

		$logo_url  = (string) Plugin::get_setting( 'email_logo_url', '' );
		$logo_w    = max( 40, min( 600, (int) Plugin::get_setting( 'email_logo_width', 160 ) ) );
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// HEADER
		if ( $logo_url ) {
			$header_html = sprintf(
				'<tr><td align="center" style="padding:24px 24px 8px;background:#ffffff;"><a href="%s" target="_blank" style="text-decoration:none;"><img src="%s" alt="%s" width="%d" style="display:block;border:0;outline:none;height:auto;width:%dpx;max-width:100%%;" /></a></td></tr>',
				esc_url( $site_url ), esc_url( $logo_url ), esc_attr( $site_name ), $logo_w, $logo_w
			);
		} else {
			$header_html = sprintf(
				'<tr><td align="center" style="padding:24px 24px 12px;background:#ffffff;"><a href="%s" style="font-size:20px;font-weight:600;color:%s;text-decoration:none;">%s</a></td></tr>',
				esc_url( $site_url ), esc_attr( $brand_col ), esc_html( $site_name )
			);
		}

		// BODY
		$body_resolved = self::resolve_placeholders( $body_html, $accent, $accent_fg );
		$body_section  = sprintf(
			'<tr><td style="padding:8px 24px 24px;background:#ffffff;color:#374151;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;">%s</td></tr>',
			$body_resolved
		);

		// FOOTER (con redes + texto custom + legal/unsubscribe)
		$footer_html = self::footer_html( $vars, $brand_col );

		// Preheader
		$preheader = isset( $vars['preheader'] ) ? esc_html( $vars['preheader'] ) : '';
		$preheader_html = $preheader
			? '<div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:#fff;opacity:0;">' . $preheader . '</div>'
			: '';

		$responsive_css = '
			<style>
				@media only screen and (max-width: 480px){
					.bol-stack { display:block !important; width:100% !important; padding:0 0 16px !important; }
					.bol-stack img { max-width:100% !important; width:100% !important; }
					.bol-container { width:100% !important; padding:0 !important; }
				}
				body { margin:0; padding:0; background:#f3f4f6; }
				a { color: ' . esc_attr( $brand_col ) . '; }
			</style>';

		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $responsive_css . '</head>'
			. '<body>' . $preheader_html
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f3f4f6;">'
			. '<tr><td align="center" style="padding:24px 12px;">'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="bol-container" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;">'
			. $header_html
			. $body_section
			. $footer_html
			. '</table></td></tr></table></body></html>';
	}

	private static function resolve_placeholders( string $html, ?string $accent = null, ?string $accent_fg = null ): string {
		$accent    = $accent    ?: Plugin::form_button_color();
		$accent_fg = $accent_fg ?: (string) Plugin::get_setting( 'form_button_text_color', '#ffffff' );
		return strtr( $html, array(
			'{{accent}}'    => $accent,
			'{{accent_fg}}' => $accent_fg,
		) );
	}

	/**
	 * Pie del correo. Estructura (de arriba a abajo):
	 *   1. Redes sociales (línea separada por |)
	 *   2. Texto custom HTML del admin (centrado, configurable en Ajustes → Branding)
	 *   3. Bloque legal: "Recibes este correo..." + Gestionar preferencias + Darme de baja
	 */
	private static function footer_html( array $vars, string $brand_col ): string {
		$social_raw        = (string) Plugin::get_setting( 'email_social_links', '' );
		$social_links_html = self::parse_social_links( $social_raw );
		$custom_footer     = (string) Plugin::get_setting( 'email_footer_text', '' );
		$footer_links_raw  = (string) Plugin::get_setting( 'email_footer_links', '' );
		$footer_links_html = self::parse_social_links( $footer_links_raw ); // mismo parser Etiqueta|URL

		$sub = $vars['subscriber'] ?? null;

		// Personalizar variables + auto-linkear URLs sueltas en el texto custom.
		if ( $custom_footer !== '' ) {
			$custom_footer = self::personalize_text( $custom_footer, $sub );
			$custom_footer = self::autolink_urls( $custom_footer );
		}

		// Si nos pasaron subscriber, generamos legal_footer automáticamente.
		// Si no, generamos uno mínimo sin links de preferencias.
		$legal_html = isset( $vars['legal_footer'] )
			? $vars['legal_footer']
			: self::build_legal_footer( $sub );

		$out  = '<tr><td style="padding:0;background:#f9fafb;border-top:1px solid #e5e7eb;">';
		$out .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">';

		// 1) Redes sociales.
		if ( $social_links_html ) {
			$out .= '<tr><td align="center" style="padding:18px 24px 6px;font-size:13px;color:#6b7280;line-height:1.6;">'
				. $social_links_html
				. '</td></tr>';
		}

		// 2) Texto custom (con variables resueltas y URLs auto-linkeadas).
		if ( $custom_footer !== '' ) {
			$out .= '<tr><td align="center" style="padding:6px 24px;font-size:13px;color:#374151;line-height:1.6;">'
				. wp_kses_post( $custom_footer )
				. '</td></tr>';
		}

		// 3) Enlaces del pie (label|URL, separados por ·).
		if ( $footer_links_html ) {
			// El parser social devuelve los enlaces separados por | con espacios; aquí los
			// queremos con · para diferenciarlos visualmente de los iconos sociales.
			$footer_links_html = str_replace( '&nbsp;|&nbsp;', '&nbsp;·&nbsp;', $footer_links_html );
			$out .= '<tr><td align="center" style="padding:6px 24px;font-size:13px;color:#6b7280;line-height:1.6;">'
				. $footer_links_html
				. '</td></tr>';
		}

		// 4) Bloque legal (preferencias + baja).
		$out .= '<tr><td align="center" style="padding:10px 24px 18px;font-size:12px;color:#9ca3af;line-height:1.5;">'
			. $legal_html
			. '</td></tr>';

		$out .= '</table></td></tr>';
		return $out;
	}

	/**
	 * Reemplaza variables {first_name}, {site_name}, {email}, etc. en texto plano.
	 * Acepta también la sintaxis con doble llave {{email}} por compatibilidad.
	 */
	public static function personalize_text( string $text, ?array $subscriber ): string {
		$first = $subscriber['first_name'] ?? '';
		$last  = $subscriber['last_name']  ?? '';
		$email = $subscriber['email']      ?? '';
		$map = array(
			'{first_name}'    => $first,
			'{last_name}'     => $last,
			'{full_name}'     => trim( $first . ' ' . $last ),
			'{email}'         => $email,
			'{site_name}'     => get_bloginfo( 'name' ),
			'{site_url}'      => home_url(),
			'{current_year}'  => wp_date( 'Y' ),
			// Compatibilidad con doble llave (por si el usuario usa {{email}})
			'{{first_name}}'  => $first,
			'{{last_name}}'   => $last,
			'{{full_name}}'   => trim( $first . ' ' . $last ),
			'{{email}}'       => $email,
			'{{site_name}}'   => get_bloginfo( 'name' ),
			'{{site_url}}'    => home_url(),
			'{{current_year}}'=> wp_date( 'Y' ),
		);
		return strtr( $text, $map );
	}

	/**
	 * Convierte URLs http(s):// en texto plano a enlaces <a>.
	 * Evita enlazar URLs que ya están dentro de un href o tras una etiqueta cerrada.
	 */
	public static function autolink_urls( string $text ): string {
		return preg_replace_callback(
			'#(?<![">\'])\b(https?://[^\s<]+)#i',
			function ( $m ) {
				$url = rtrim( $m[1], '.,;:!?)' );
				return '<a href="' . esc_url( $url ) . '" style="color:inherit;text-decoration:underline;">' . esc_html( $url ) . '</a>';
			},
			$text
		);
	}

	/**
	 * Construye el bloque legal con preferencias + unsubscribe si hay subscriber.
	 */
	public static function build_legal_footer( ?array $subscriber ): string {
		$site_name = get_bloginfo( 'name' );
		$site = '<a href="' . esc_url( home_url() ) . '" style="color:#6b7280;">' . esc_html( $site_name ) . '</a>';
		$out  = sprintf(
			/* translators: %s = nombre del sitio */
			esc_html__( 'Recibes este correo porque te suscribiste en %s.', 'boletines' ),
			$site
		);

		if ( $subscriber && ! empty( $subscriber['id'] ) && ! empty( $subscriber['token'] ) ) {
			$pref_url  = self::preferences_url( $subscriber );
			$unsub_url = self::unsubscribe_url( $subscriber );
			$out .= '<br>';
			$out .= '<a href="' . esc_url( $pref_url ) . '" style="color:#6b7280;text-decoration:underline;">' . esc_html__( 'Gestionar preferencias', 'boletines' ) . '</a>';
			$out .= ' &nbsp;·&nbsp; ';
			$out .= '<a href="' . esc_url( $unsub_url ) . '" style="color:#6b7280;text-decoration:underline;">' . esc_html__( 'Darme de baja', 'boletines' ) . '</a>';
		}

		return $out;
	}

	public static function preferences_url( array $subscriber ): string {
		$page_id = (int) Plugin::get_setting( 'preferences_page_id', 0 );
		// v2.1.1 — Si hay página de preferencias configurada seguimos usándola,
		// pero las URLs path-based se prefieren si el admin no forza el legacy.
		if ( $page_id > 0 && get_post( $page_id ) && (int) Plugin::get_setting( 'force_legacy_query_strings', 0 ) === 1 ) {
			return add_query_arg( array(
				'sid'   => $subscriber['id'],
				'token' => $subscriber['token'],
			), get_permalink( $page_id ) );
		}
		// v2.1.1 — Path-based por defecto (inmune a WAFs/CDNs que pelan query strings).
		return self::path_action_url( 'preferences', $subscriber['id'], $subscriber['token'] );
	}

	public static function unsubscribe_url( array $subscriber ): string {
		// v2.1.1 — Path-based por defecto.
		return self::path_action_url( 'unsubscribe', $subscriber['id'], $subscriber['token'] );
	}

	public static function confirm_url( array $subscriber ): string {
		// v2.1.1 — Path-based por defecto.
		return self::path_action_url( 'confirm', $subscriber['id'], $subscriber['token'] );
	}

	/**
	 * v2.1.3 — URL de tracking de clic PATH-BASED (inmune a CDN/WAF que pelan query strings).
	 *   https://site.com/boletines-action/click/{token}/{base64url(destino)}/
	 * El destino va en base64url (A-Za-z0-9-_) para ser seguro dentro del path.
	 */
	public static function click_url( string $token, string $url ): string {
		$b64 = rtrim( strtr( base64_encode( $url ), '+/', '-_' ), '=' );
		return user_trailingslashit( home_url( '/boletines-action/click/' . rawurlencode( $token ) . '/' . $b64 ) );
	}

	/**
	 * v2.1.1 — Construye una URL path-based del tipo:
	 *   https://site.com/boletines-action/{action}/{sid}/{token}/
	 *
	 * Estas URLs son inmunes a reglas de CDN/WAF que pelan query strings
	 * (Cloudflare Strip Query Strings, Sucuri, mod_security, etc.).
	 * El handler (Tracker) las reconoce vía rewrite rules registradas
	 * en Plugin::register_rewrite_rules().
	 */
	public static function path_action_url( string $action, $sid, string $token ): string {
		$sid   = (int) $sid;
		$token = (string) $token;
		// user_trailingslashit deja la URL limpia tipo /token/
		return user_trailingslashit( home_url( '/boletines-action/' . $action . '/' . $sid . '/' . rawurlencode( $token ) ) );
	}

	public static function parse_social_links( string $raw ): string {
		$raw = trim( $raw );
		if ( $raw === '' ) return '';

		$lines = preg_split( '/[\r\n]+/', $raw );
		$items = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) continue;

			$parts = preg_split( '/\s*\|\s*|\s*,\s*|\s*:\s*|\t+/', $line, 2 );
			if ( count( $parts ) === 2 && filter_var( $parts[1], FILTER_VALIDATE_URL ) ) {
				$label = trim( $parts[0] );
				$url   = $parts[1];
			} elseif ( filter_var( $parts[0], FILTER_VALIDATE_URL ) ) {
				$url   = $parts[0];
				$label = wp_parse_url( $url, PHP_URL_HOST );
			} else {
				continue;
			}
			$items[] = sprintf(
				'<a href="%s" target="_blank" style="color:#374151;text-decoration:none;">%s</a>',
				esc_url( $url ),
				esc_html( $label )
			);
		}
		return implode( ' &nbsp;|&nbsp; ', $items );
	}
}
