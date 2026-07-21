<?php
namespace Boletines\Resources;

use Boletines\Models\Resource;
use Boletines\Mailer\Branding;

defined( 'ABSPATH' ) || exit;

/**
 * Mailer del módulo Recursos.
 * Envía el correo con el link de descarga al suscriptor.
 *
 * - Usa el Branding global de Digest (header + footer) para que el correo se
 *   vea consistente con el resto de campañas.
 * - Soporta variables: {first_name}, {email}, {download_url}, {resource_title},
 *   {expires_at}, {site_name}.
 */
class Mailer {

	/**
	 * @param array  $resource     Resource hidratado de Resource::get()
	 * @param array  $subscriber   { email, first_name, last_name }
	 * @param string $token        Token de descarga
	 * @param string $expires_at   Fecha de expiración (mysql)
	 * @return bool
	 */
	public function send_download_link( array $resource, array $subscriber, string $token, string $expires_at ): bool {
		$download_url = add_query_arg( array( 'boletines_download' => $token ), home_url( '/' ) );
		$site_name    = get_bloginfo( 'name' );
		$first_name   = $subscriber['first_name'] ?? '';

		// Subject: usa el del recurso o default.
		$subject_tpl = ! empty( $resource['email_subject'] )
			? $resource['email_subject']
			: sprintf( __( 'Tu descarga: %s', 'boletines' ), $resource['title'] );

		// Body: usa el del recurso o default.
		$body_tpl = ! empty( $resource['email_body'] )
			? $resource['email_body']
			: $this->default_body_template();

		// Variables comunes
		$vars = array(
			'{first_name}'     => $first_name,
			'{email}'          => $subscriber['email'],
			'{download_url}'   => $download_url,
			'{resource_title}' => $resource['title'],
			'{expires_at}'     => mysql2date( get_option( 'date_format', 'Y-m-d' ), $expires_at ),
			'{site_name}'      => $site_name,
		);

		$subject = strtr( $subject_tpl, $vars );
		$body    = strtr( $body_tpl, $vars );

		// Envolver con branding global de Digest si la clase existe.
		if ( class_exists( '\\Boletines\\Mailer\\Branding' ) ) {
			$html_body = Branding::wrap( $body );
			// Pasar el subscriber para que personalize_text también funcione.
			$html_body = Branding::personalize_text( $html_body, $subscriber );
		} else {
			$html_body = '<html><body style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
				. $body
				. '</body></html>';
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		if ( class_exists( '\\Boletines\\Smtp\\Mailer' ) ) {
			\Boletines\Smtp\Mailer::set_digest_context( true );
		}
		$ok = (bool) wp_mail( $subscriber['email'], $subject, $html_body, $headers );
		if ( class_exists( '\\Boletines\\Smtp\\Mailer' ) ) {
			\Boletines\Smtp\Mailer::set_digest_context( false );
		}
		return $ok;
	}

	/**
	 * Plantilla HTML por defecto del email de descarga.
	 */
	private function default_body_template(): string {
		ob_start();
		?>
<p>Hola {first_name},</p>

<p>¡Gracias por suscribirte! Aquí tienes tu descarga:</p>

<p style="text-align:center;margin:32px 0;">
	<a href="{download_url}" style="display:inline-block;background:#111827;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px;">📥 Descargar {resource_title}</a>
</p>

<p style="color:#6b7280;font-size:13px;">
	Este enlace expira el {expires_at}. Si no funciona el botón, copia y pega esta dirección en tu navegador:<br>
	<span style="word-break:break-all;color:#6b7280;font-size:11px;">{download_url}</span>
</p>

<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

<p style="color:#9ca3af;font-size:12px;">
	Recibiste este correo porque te has suscrito en {site_name} con {email}.
</p>
		<?php
		return (string) ob_get_clean();
	}
}
