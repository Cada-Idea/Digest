<?php
namespace Boletines\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detector de configuración SMTP en el sitio.
 *
 * Detecta si hay un plugin SMTP instalado, cuál es, y si está activo.
 * También detecta conflictos (varios plugins SMTP activos a la vez).
 *
 * El plugin Digest NO incluye SMTP propio. Esta clase solo detecta lo que
 * el usuario tiene y le avisa si conviene instalar uno.
 */
class Smtp {

	/**
	 * Plugins SMTP conocidos.
	 * Clave: ruta del archivo principal (plugin_basename).
	 * Valor: nombre amigable.
	 */
	const KNOWN_PLUGINS = array(
		'wp-mail-smtp/wp_mail_smtp.php'        => 'WP Mail SMTP',
		'fluent-smtp/fluent-smtp.php'          => 'FluentSMTP',
		'easy-wp-smtp/easy-wp-smtp.php'        => 'Easy WP SMTP',
		'post-smtp/postman-smtp.php'           => 'Post SMTP',
		'wp-smtp/wp-smtp.php'                  => 'WP SMTP',
		'gmail-smtp/main.php'                  => 'Gmail SMTP',
		'sendgrid-email-delivery-simplified/wpsendgrid.php' => 'SendGrid',
		'mailgun/mailgun.php'                  => 'Mailgun',
		'wp-ses/wp-ses.php'                    => 'WP Offload SES',
	);

	/**
	 * Devuelve la lista de plugins SMTP activos detectados.
	 *
	 * @return array<string, string> [plugin_basename => friendly_name]
	 */
	public static function active_plugins(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = array();
		foreach ( self::KNOWN_PLUGINS as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[ $file ] = $name;
			}
		}
		return $active;
	}

	/**
	 * ¿Hay al menos un plugin SMTP activo?
	 */
	public static function is_configured(): bool {
		return ! empty( self::active_plugins() );
	}

	/**
	 * ¿Hay conflicto (más de un plugin SMTP activo)?
	 */
	public static function has_conflict(): bool {
		return count( self::active_plugins() ) > 1;
	}

	/**
	 * Devuelve el resumen del estado para mostrar en el admin.
	 *
	 * @return array{
	 *   status: string,    // 'ok' | 'warning' | 'critical' | 'info'
	 *   message: string,
	 *   details: string,
	 *   action_label: string,
	 *   action_url: string
	 * }
	 */
	public static function status(): array {
		$active = self::active_plugins();

		if ( count( $active ) > 1 ) {
			return array(
				'status'       => 'critical',
				'message'      => __( '⚠ Múltiples plugins SMTP activos: conflicto detectado', 'boletines' ),
				'details'      => sprintf(
					/* translators: %s: comma-separated plugin list */
					__( 'Detectados: %s. Sólo uno debe estar activo, los demás interceptan también el envío y causan resultados impredecibles.', 'boletines' ),
					implode( ', ', $active )
				),
				'action_label' => __( 'Ir a Plugins', 'boletines' ),
				'action_url'   => admin_url( 'plugins.php' ),
			);
		}

		if ( count( $active ) === 1 ) {
			$name = reset( $active );
			return array(
				'status'       => 'ok',
				'message'      => sprintf(
					/* translators: %s: plugin name */
					__( '✅ SMTP configurado vía %s', 'boletines' ),
					$name
				),
				'details'      => __( 'Los correos saldrán por SMTP. Si tienes problemas de entrega, revisa la configuración del plugin SMTP.', 'boletines' ),
				'action_label' => '',
				'action_url'   => '',
			);
		}

		// Ningún plugin SMTP detectado.
		return array(
			'status'       => 'warning',
			'message'      => __( '⚠ No hay plugin SMTP configurado', 'boletines' ),
			'details'      => __( 'Tus correos salen por PHP mail() del servidor. La entregabilidad será baja (muchos van a spam). Recomendamos instalar FluentSMTP (gratis, excelente).', 'boletines' ),
			'action_label' => __( 'Instalar FluentSMTP', 'boletines' ),
			'action_url'   => self::install_url( 'fluent-smtp' ),
		);
	}

	/**
	 * Construye la URL para instalar un plugin desde el repositorio de WP.org.
	 */
	private static function install_url( string $slug ): string {
		if ( current_user_can( 'install_plugins' ) ) {
			return wp_nonce_url(
				self_admin_url( 'update.php?action=install-plugin&plugin=' . $slug ),
				'install-plugin_' . $slug
			);
		}
		return 'https://wordpress.org/plugins/' . $slug . '/';
	}

	/**
	 * Test funcional de envío. Envía un correo al admin y comprueba si llegó al cuerpo de wp_mail.
	 * Devuelve true si wp_mail() devolvió true (no garantiza entrega).
	 */
	public static function send_test( string $to ): array {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return array(
				'success' => false,
				'message' => __( 'Email destino no válido.', 'boletines' ),
			);
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Prueba de envío — Digest', 'boletines' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: site, 2: time */
			__( "Este es un correo de prueba enviado por Digest para verificar que wp_mail() funciona.\n\nSitio: %1\$s\nHora: %2\$s\n\nSi recibiste este correo, el envío básico funciona. Para mejor entregabilidad, asegúrate de tener configurado un plugin SMTP.", 'boletines' ),
			get_bloginfo( 'name' ),
			current_time( 'mysql' )
		);

		$error_message = '';
		$handler = function ( $wp_error ) use ( &$error_message ) {
			if ( is_wp_error( $wp_error ) ) {
				$error_message = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $handler );

		$sent = wp_mail( $to, $subject, $message );

		remove_action( 'wp_mail_failed', $handler );

		if ( $sent ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: email */
					__( 'Correo enviado a %s. Revisa la bandeja de entrada (y la carpeta spam).', 'boletines' ),
					$to
				),
			);
		}

		return array(
			'success' => false,
			'message' => $error_message !== '' ? $error_message : __( 'wp_mail() devolvió false. Revisa la configuración de tu SMTP.', 'boletines' ),
		);
	}
}
