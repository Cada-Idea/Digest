<?php
namespace Boletines\Resources;

use Boletines\Models\Resource;
use Boletines\Models\Subscriber;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoint público REST para captura de leads desde un Recurso.
 *
 * Ruta: POST /wp-json/boletines/v1/resources/request
 * Payload: { email, first_name, resource_id, _wpnonce }
 *
 * Flujo:
 *   1) Valida nonce + email + recurso activo
 *   2) Crea/obtiene Subscriber (single opt-in: estado confirmed inmediato)
 *   3) Lo adjunta a las listas configuradas en el recurso
 *   4) Genera token único de descarga
 *   5) Envía email con el link
 *   6) Devuelve éxito al frontend
 */
class Ajax {

	const NAMESPACE = 'boletines/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/resources/request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true', // público
				'args'                => array(
					'email'       => array( 'required' => true ),
					'resource_id' => array( 'required' => true ),
				),
			)
		);
	}

	public function handle_request( \WP_REST_Request $req ) {
		// 1) Nonce
		$nonce = $req->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Sesión expirada. Recarga la página.', 'boletines' ), array( 'status' => 403 ) );
		}

		// 2) Validar entrada
		$email       = sanitize_email( (string) $req->get_param( 'email' ) );
		$first_name  = sanitize_text_field( (string) $req->get_param( 'first_name' ) );
		$resource_id = (int) $req->get_param( 'resource_id' );

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Correo no válido.', 'boletines' ), array( 'status' => 400 ) );
		}
		if ( $resource_id < 1 ) {
			return new \WP_Error( 'invalid_resource', __( 'Recurso no válido.', 'boletines' ), array( 'status' => 400 ) );
		}

		// 3) Recurso activo
		$resource = Resource::get( $resource_id );
		if ( ! $resource || $resource['status'] !== Resource::STATUS_ACTIVE ) {
			return new \WP_Error( 'inactive_resource', __( 'Este recurso ya no está disponible.', 'boletines' ), array( 'status' => 410 ) );
		}

		// 4) Crear/recuperar Subscriber via upsert. Single opt-in: confirmamos siempre.
		$sid = Subscriber::upsert( array(
			'email'      => $email,
			'first_name' => $first_name,
			'source'     => 'resource:' . $resource_id,
			'status'     => 'confirmed',
		) );

		if ( ! $sid ) {
			return new \WP_Error( 'create_failed', __( 'No se pudo registrar tu suscripción. Inténtalo de nuevo.', 'boletines' ), array( 'status' => 500 ) );
		}

		// upsert puede haber dejado en pending si el suscriptor venía de
		// unsubscribed. Forzamos confirm (single opt-in = consentimiento explícito al pedir el recurso).
		Subscriber::confirm( (int) $sid );

		// 5) Adjuntar a las listas configuradas del recurso.
		if ( ! empty( $resource['list_ids_array'] ) ) {
			Subscriber::attach_lists( (int) $sid, $resource['list_ids_array'] );
		}

		// 6) Crear token de descarga
		$token = Resource::create_token( $resource_id, $email, (int) $sid, (int) $resource['token_ttl_days'] );

		// 7) Enviar email con link
		$sent = ( new Mailer() )->send_download_link(
			$resource,
			array( 'email' => $email, 'first_name' => $first_name, 'last_name' => '' ),
			$token['token'],
			$token['expires_at']
		);

		// 8) Estadística
		Resource::increment_submissions( $resource_id );

		return rest_ensure_response( array(
			'success'    => true,
			'email_sent' => (bool) $sent,
			'message'    => $resource['success_message'] ?: __( '¡Listo! Te hemos enviado el enlace de descarga al correo.', 'boletines' ),
		) );
	}
}
