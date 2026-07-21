<?php
namespace Boletines\Forms;

use Boletines\Mailer\Mailer;
use Boletines\Models\Subscriber;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Handler {

	public function register(): void {
		add_action( 'admin_post_boletines_subscribe', array( $this, 'subscribe' ) );
		add_action( 'admin_post_nopriv_boletines_subscribe', array( $this, 'subscribe' ) );
	}

	public function subscribe(): void {
		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : home_url();

		// Verificar nonce.
		if ( ! isset( $_POST['boletines_nonce'] ) || ! wp_verify_nonce( $_POST['boletines_nonce'], 'boletines_subscribe' ) ) {
			$this->bail( $redirect, __( 'Sesión expirada. Vuelve a intentarlo.', 'boletines' ) );
		}

		// Honeypot.
		if ( ! empty( $_POST['website'] ) ) {
			$this->bail( $redirect, __( 'Solicitud rechazada.', 'boletines' ) );
		}

		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$lists      = isset( $_POST['lists'] ) ? array_map( 'intval', explode( ',', wp_unslash( $_POST['lists'] ) ) ) : array();

		if ( ! is_email( $email ) ) {
			$this->bail( $redirect, __( 'Introduce un correo válido.', 'boletines' ) );
		}

		$double_optin = (int) Plugin::get_setting( 'double_optin', 1 ) === 1;
		$status       = $double_optin ? Subscriber::STATUS_PENDING : Subscriber::STATUS_CONFIRMED;

		$sid = Subscriber::upsert(
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'status'     => $status,
				'ip'         => $this->get_ip(),
				'source'     => 'form',
			)
		);

		if ( ! $sid ) {
			$this->bail( $redirect, __( 'No pudimos procesar tu suscripción. Inténtalo más tarde.', 'boletines' ) );
		}

		Subscriber::attach_lists( $sid, $lists );

		// Si es doble opt-in y está pendiente → enviar correo de confirmación.
		$sub = Subscriber::find_by_id( $sid );
		if ( $sub && $sub['status'] === Subscriber::STATUS_PENDING ) {
			$this->send_confirmation_email( $sub );
		}

		$success_url = add_query_arg( 'boletines_subscribed', '1', $redirect );
		wp_safe_redirect( $success_url );
		exit;
	}

	private function send_confirmation_email( array $subscriber ): void {
		$mailer        = new Mailer();
		// v2.1.1 — Path-based para evitar que un CDN/WAF pele el query string.
		$confirm_url   = \Boletines\Mailer\Branding::confirm_url( $subscriber );
		$subject = (string) Plugin::get_setting( 'confirm_subject', 'Confirma tu suscripción' );
		$body    = (string) Plugin::get_setting( 'confirm_body', '' );

		$body = strtr(
			$body,
			array(
				'{confirmation_url}' => $confirm_url,
				'{first_name}'       => $subscriber['first_name'] ?: '',
				'{site_name}'        => get_bloginfo( 'name' ),
			)
		);

		$mailer->send_simple( $subscriber['email'], $subject, $body, array(), $subscriber );
	}

	private function bail( string $redirect, string $error ): void {
		$url = add_query_arg( 'boletines_error', rawurlencode( $error ), $redirect );
		wp_safe_redirect( $url );
		exit;
	}

	private function get_ip(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return is_string( $ip ) ? substr( sanitize_text_field( $ip ), 0, 45 ) : '';
	}
}
