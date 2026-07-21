<?php
namespace Boletines\Mailer;

use Boletines\Plugin;
use Boletines\Models\CampaignEmail;
use Boletines\Models\Subscriber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Núcleo de envío. Usa wp_mail() para que sea compatible con cualquier
 * plugin SMTP (FluentSMTP, WP Mail SMTP, Post SMTP, etc.).
 */
class Mailer {

	/**
	 * Envía un único correo de la cola.
	 *
	 * @param array $row Fila de campaign_emails con datos JOIN del suscriptor y campaña.
	 * @return true|string TRUE si OK, string con mensaje de error si falla.
	 */
	public function send_queued( array $row ) {
		$subscriber = array(
			'id'         => (int) $row['subscriber_id'],
			'email'      => $row['email'],
			'first_name' => $row['first_name'] ?? '',
			'last_name'  => $row['last_name'] ?? '',
			'token'      => $row['subscriber_token'] ?? '',
		);

		// 1) Procesar shortcodes embebidos (posts, productos, populares...).
		$body = do_shortcode( $row['body_html'] );
		// 2) Personalizar variables {first_name}, {site_name}, etc.
		$body = $this->personalize( $body, $subscriber );
		// 3) Envolver con branding (header logo + footer redes + texto custom + preferencias/baja).
		$body = Branding::wrap( $body, array(
			'preheader'  => $row['preheader'] ?? '',
			'subscriber' => $subscriber, // <-- Branding genera legal_footer automáticamente
		) );
		// 4) Inyectar tracking (pixel + click rewrite).
		$body = $this->inject_tracking( $body, $row['tracking_token'] );

		// 5) Personalizar variables {email}, {first_name}, etc. tanto en body como en subject.
		// Se hace DESPUÉS del tracking para que las URLs no se vean afectadas y ANTES del envío.
		$body    = \Boletines\Mailer\Branding::personalize_text( $body, $subscriber );
		$subject = $this->personalize( $row['subject'], $subscriber );

		$from_name  = $row['from_name'] ?: Plugin::get_setting( 'from_name', get_bloginfo( 'name' ) );
		$from_email = $row['from_email'] ?: Plugin::get_setting( 'from_email', get_option( 'admin_email' ) );
		$reply_to   = $row['reply_to'] ?: $from_email;

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $this->encode_header( $from_name ) . ' <' . $from_email . '>',
			'Reply-To: ' . $reply_to,
			// One-Click Unsubscribe (RFC 8058) - obligatorio para Gmail/Yahoo desde 2024.
			'List-Unsubscribe: <' . esc_url_raw( $this->unsubscribe_url( $subscriber ) ) . '>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
			'X-Boletines-Token: ' . $row['tracking_token'],
		);

		// Capturar errores de wp_mail.
		$error_msg = '';
		$catch     = function ( $e ) use ( &$error_msg ) {
			$error_msg = is_object( $e ) && method_exists( $e, 'get_error_message' )
				? $e->get_error_message()
				: 'wp_mail falló sin mensaje';
		};
		add_action( 'wp_mail_failed', $catch );

		$ok = $this->digest_mail( $subscriber['email'], $subject, $body, $headers );

		remove_action( 'wp_mail_failed', $catch );

		if ( $ok ) {
			return true;
		}
		return $error_msg ?: 'wp_mail devolvió FALSE';
	}

	/**
	 * Envía un correo "sencillo" (no de campaña): doble opt-in, bienvenida, etc.
	 */
	public function send_simple( string $to, string $subject, string $body_html, array $headers_extra = array(), ?array $subscriber = null ): bool {
		$from_name  = Plugin::get_setting( 'from_name', get_bloginfo( 'name' ) );
		$from_email = Plugin::get_setting( 'from_email', get_option( 'admin_email' ) );
		$reply_to   = Plugin::get_setting( 'reply_to', $from_email );

		$headers = array_merge(
			array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $this->encode_header( $from_name ) . ' <' . $from_email . '>',
				'Reply-To: ' . $reply_to,
			),
			$headers_extra
		);

		$body = Branding::wrap( wpautop( $body_html ), array(
			'preheader'  => '',
			'subscriber' => $subscriber, // si es null, Branding mostrará footer mínimo sin links de preferencias
		) );

		return (bool) $this->digest_mail( $to, $subject, $body, $headers );
	}

	/**
	 * v2.1.4 — Envía un correo de PRUEBA que pasa por el MISMO pipeline que una
	 * campaña real (branding + redes + preferencias/baja + tracking de clics
	 * path-based), para verificar en la bandeja de entrada que cada enlace va a
	 * su destino. No requiere una campaña; usa un token de prueba.
	 *
	 * @return true|string TRUE si OK, string con el mensaje de error.
	 */
	public function send_link_test( array $subscriber ) {
		$token = 'test-' . wp_generate_password( 20, false );

		// Enlace externo de ejemplo (último post publicado, o home).
		$recent = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
		$post_url   = $recent ? get_permalink( $recent[0] ) : home_url( '/' );
		$post_title = $recent ? get_the_title( $recent[0] ) : get_bloginfo( 'name' );

		$body  = '<p style="font-size:15px;color:#374151;">' . esc_html__( 'Correo de prueba de enlaces. Haz clic en cada uno y verifica que te lleva a su destino:', 'boletines' ) . '</p>';
		$body .= '<p><a href="' . esc_url( $post_url ) . '" style="color:#0f766e;font-weight:600;">' . esc_html__( '➡ Enlace externo de ejemplo: ', 'boletines' ) . esc_html( $post_title ) . '</a></p>';
		$body .= '<p style="font-size:13px;color:#6b7280;">' . esc_html__( 'Abajo, en el pie, prueba también los iconos de redes sociales, "Gestionar preferencias" y "Darme de baja".', 'boletines' ) . '</p>';

		// 1) Branding (header + footer redes + preferencias/baja con este suscriptor).
		$html = Branding::wrap( $body, array(
			'preheader'  => __( 'Prueba de enlaces', 'boletines' ),
			'subscriber' => $subscriber,
		) );
		// 2) Tracking (pixel + click path-based). Excluye los enlaces de acción propios.
		$html = $this->inject_tracking( $html, $token );

		$from_name  = Plugin::get_setting( 'from_name', get_bloginfo( 'name' ) );
		$from_email = Plugin::get_setting( 'from_email', get_option( 'admin_email' ) );
		$reply_to   = Plugin::get_setting( 'reply_to', $from_email );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $this->encode_header( $from_name ) . ' <' . $from_email . '>',
			'Reply-To: ' . $reply_to,
			'List-Unsubscribe: <' . esc_url_raw( $this->unsubscribe_url( $subscriber ) ) . '>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
		);

		$error_msg = '';
		$catch = function ( $e ) use ( &$error_msg ) {
			$error_msg = is_object( $e ) && method_exists( $e, 'get_error_message' ) ? $e->get_error_message() : 'wp_mail falló';
		};
		add_action( 'wp_mail_failed', $catch );
		$ok = $this->digest_mail( $subscriber['email'], __( '[Prueba] Verificación de enlaces del correo', 'boletines' ), $html, $headers );
		remove_action( 'wp_mail_failed', $catch );

		return $ok ? true : ( $error_msg ?: 'wp_mail devolvió FALSE' );
	}

	/**
	 * v2.1.6 — Envía marcando el contexto Digest para que el SMTP respete el
	 * remitente propio de Digest (doble remitente).
	 */
	private function digest_mail( $to, $subject, $body, array $headers ) {
		if ( class_exists( '\\Boletines\\Smtp\\Mailer' ) ) {
			\Boletines\Smtp\Mailer::set_digest_context( true );
		}
		$ok = wp_mail( $to, $subject, $body, $headers );
		if ( class_exists( '\\Boletines\\Smtp\\Mailer' ) ) {
			\Boletines\Smtp\Mailer::set_digest_context( false );
		}
		return $ok;
	}

	/**
	 * v2.1.6 — Correo de PRUEBA de una campaña concreta a un suscriptor, con el MISMO
	 * pipeline que el envío real (shortcodes, branding, tracking). No toca cola ni stats.
	 *
	 * @return true|string
	 */
	public function send_campaign_test( array $campaign, array $subscriber ) {
		$row = array(
			'subscriber_id'    => (int) ( $subscriber['id'] ?? 0 ),
			'email'            => $subscriber['email'] ?? '',
			'first_name'       => $subscriber['first_name'] ?? '',
			'last_name'        => $subscriber['last_name'] ?? '',
			'subscriber_token' => $subscriber['token'] ?? '',
			'body_html'        => $campaign['body_html'] ?? '',
			'preheader'        => $campaign['preheader'] ?? '',
			'subject'          => '[Prueba] ' . ( $campaign['subject'] ?? '' ),
			'from_name'        => $campaign['from_name'] ?? '',
			'from_email'       => $campaign['from_email'] ?? '',
			'reply_to'         => $campaign['reply_to'] ?? '',
			'tracking_token'   => 'test-' . wp_generate_password( 20, false ),
		);
		return $this->send_queued( $row );
	}

	private function personalize( string $text, array $subscriber ): string {
		// Delegamos a Branding::personalize_text (única fuente de verdad para las
		// variables permitidas: {first_name}, {last_name}, {full_name}, {email},
		// {site_name}, {site_url}, {current_year}. Acepta también doble llave).
		return \Boletines\Mailer\Branding::personalize_text( $text, $subscriber );
	}

	/**
	 * Reemplaza enlaces por la URL de tracking + añade pixel 1x1.
	 */
	private function inject_tracking( string $html, string $token ): string {
		// v2.1.3 — Reemplazar href="..." con tracking PATH-BASED (inmune a CDN/WAF).
		$html = preg_replace_callback(
			'#<a\s+([^>]*?)href\s*=\s*"([^"]+)"([^>]*)>#i',
			function ( $m ) use ( $token ) {
				$url = $m[2];
				if ( strpos( $url, 'mailto:' ) === 0 || strpos( $url, '#' ) === 0 || strpos( $url, '{' ) === 0 ) {
					return $m[0];
				}
				// No re-envolver tracking ya hecho (legacy o path-based).
				if ( strpos( $url, 'boletines_track=' ) !== false || strpos( $url, '/boletines-action/click/' ) !== false ) {
					return $m[0];
				}
				// v2.1.3 — Nunca envolver nuestros propios endpoints de acción
				// (preferencias/baja/confirmar): deben quedar path-based intactos
				// o el CDN los rompe y acaban redirigiendo a la home.
				if ( strpos( $url, '/boletines-action/' ) !== false || strpos( $url, 'boletines_action=' ) !== false ) {
					return $m[0];
				}
				$tracked = \Boletines\Mailer\Branding::click_url( $token, $url );
				return '<a ' . $m[1] . 'href="' . esc_url( $tracked ) . '"' . $m[3] . '>';
			},
			$html
		);

		// Pixel de apertura.
		$pixel_url = home_url( '/?boletines_track=open&t=' . rawurlencode( $token ) );
		$pixel     = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;" />';

		// Insertar antes de </body> si existe; si no, al final.
		if ( stripos( $html, '</body>' ) !== false ) {
			$html = str_ireplace( '</body>', $pixel . '</body>', $html );
		} else {
			$html .= $pixel;
		}

		return $html;
	}

	/**
	 * Devuelve el HTML del bloque legal con unsubscribe + preferencias.
	 * @deprecated v1.7 — la lógica vive en Branding\Branding::build_legal_footer().
	 * Mantenido por compatibilidad si lo llama código externo.
	 */
	private function legal_footer_html( array $subscriber ): string {
		return Branding::build_legal_footer( $subscriber );
	}

	private function preferences_url( array $subscriber ): string {
		return Branding::preferences_url( $subscriber );
	}

	private function unsubscribe_url( array $subscriber ): string {
		// v2.1.1 — Usa path-based para ser inmune a CDNs/WAFs que pelan query strings.
		return Branding::unsubscribe_url( $subscriber );
	}

	private function encode_header( string $value ): string {
		// Si el nombre tiene caracteres no-ASCII, codificar como MIME.
		if ( preg_match( '/[\x80-\xff]/', $value ) ) {
			return '=?UTF-8?B?' . base64_encode( $value ) . '?=';
		}
		return '"' . str_replace( '"', '', $value ) . '"';
	}
}
