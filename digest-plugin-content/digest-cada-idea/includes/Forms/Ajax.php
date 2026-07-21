<?php
namespace Boletines\Forms;

use Boletines\Mailer\Mailer;
use Boletines\Models\Subscriber;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint público AJAX para suscripción desde el frontend.
 * Retorna JSON con success + message para mostrarlo inline (sin recarga).
 *
 * URL: POST /wp-json/boletines/v1/subscribe
 */
class Ajax {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route(): void {
		register_rest_route(
			'boletines/v1',
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'      => array( 'required' => true ),
					'nonce'      => array( 'required' => true ),
					'first_name' => array( 'required' => false ),
					'last_name'  => array( 'required' => false ),
					'lists'      => array( 'required' => false ),
					'website'    => array( 'required' => false ), // honeypot
				),
			)
		);
	}

	public function subscribe( \WP_REST_Request $req ) {
		// Verificar nonce.
		$nonce = (string) $req->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'boletines_subscribe' ) ) {
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Sesión expirada. Recarga la página y vuelve a intentar.', 'boletines' ),
			), 403 );
		}

		// Honeypot.
		if ( ! empty( $req->get_param( 'website' ) ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'rejected' ), 400 );
		}

		$email      = sanitize_email( (string) $req->get_param( 'email' ) );
		$first_name = sanitize_text_field( (string) $req->get_param( 'first_name' ) );
		$last_name  = sanitize_text_field( (string) $req->get_param( 'last_name' ) );
		$form_id    = (int) $req->get_param( 'form_id' );

		// v1.6 — campos personalizados.
		$birthday = sanitize_text_field( (string) $req->get_param( 'birthday' ) );

		// Validar fecha de cumpleaños (YYYY-MM-DD).
		if ( $birthday !== '' && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birthday ) ) {
			$birthday = '';
		}

		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Introduce un correo válido.', 'boletines' ),
			), 400 );
		}

		// Listas: pueden venir como array o string CSV.
		$lists_param = $req->get_param( 'lists' );
		if ( is_array( $lists_param ) ) {
			$lists = array_map( 'intval', $lists_param );
		} elseif ( is_string( $lists_param ) ) {
			$lists = array_map( 'intval', array_filter( explode( ',', $lists_param ) ) );
		} else {
			$lists = array();
		}
		$lists = array_filter( $lists );

		$double_optin = (int) Plugin::get_setting( 'double_optin', 1 ) === 1;
		$status       = $double_optin ? Subscriber::STATUS_PENDING : Subscriber::STATUS_CONFIRMED;

		$existing = Subscriber::find_by_email( $email );
		$was_confirmed_before = $existing && $existing['status'] === Subscriber::STATUS_CONFIRMED;

		$sid = Subscriber::upsert( array(
			'email'      => $email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'status'     => $status,
			'source'     => 'form',
			'ip'         => $this->ip(),
		) );

		if ( ! $sid ) {
			return new \WP_REST_Response( array(
				'success' => false,
				'message' => __( 'No pudimos procesar tu suscripción. Vuelve a intentar más tarde.', 'boletines' ),
			), 500 );
		}

		if ( ! empty( $lists ) ) {
			Subscriber::attach_lists( $sid, $lists );
		}

		// v1.6 — guardar meta personalizada (cumpleaños).
		if ( $birthday !== '' ) {
			Subscriber::set_meta( $sid, 'birthday', $birthday );
		}

		// Si viene de un Form guardado, incrementamos conversiones.
		if ( $form_id > 0 ) {
			\Boletines\Models\Form::increment_conversions( $form_id );
		}

		// Decidir mensaje según situación.
		$sub  = Subscriber::find_by_id( $sid );
		$is_pending = $sub && $sub['status'] === Subscriber::STATUS_PENDING;

		if ( $is_pending ) {
			$this->send_confirmation_email( $sub );
			$message = __( '¡Casi listo! Te enviamos un correo para confirmar tu suscripción. Revisa tu bandeja de entrada (y la carpeta de spam por si acaso).', 'boletines' );
		} elseif ( $was_confirmed_before ) {
			$message = __( 'Ya estabas suscrito — actualizamos tus preferencias. ¡Gracias!', 'boletines' );
		} else {
			$message = __( '¡Listo! Tu suscripción está activa.', 'boletines' );
		}

		return rest_ensure_response( array(
			'success'         => true,
			'pending_confirm' => $is_pending,
			'message'         => $message,
		) );
	}

	private function send_confirmation_email( array $subscriber ): void {
		$mailer      = new Mailer();
		// v2.1.1 — Path-based para evitar que un CDN/WAF pele el query string.
		$confirm_url = \Boletines\Mailer\Branding::confirm_url( $subscriber );
		$subject = (string) Plugin::get_setting( 'confirm_subject', 'Confirma tu suscripción' );
		$body    = (string) Plugin::get_setting( 'confirm_body', '' );
		$body    = strtr( $body, array(
			'{confirmation_url}' => $confirm_url,
			'{first_name}'       => $subscriber['first_name'] ?: '',
			'{site_name}'        => get_bloginfo( 'name' ),
		) );
		// Pasar subscriber para que el footer incluya Gestionar preferencias + Darme de baja.
		$mailer->send_simple( $subscriber['email'], $subject, $body, array(), $subscriber );
	}

	private function ip(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return is_string( $ip ) ? substr( sanitize_text_field( $ip ), 0, 45 ) : '';
	}
}
