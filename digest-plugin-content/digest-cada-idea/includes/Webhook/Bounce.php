<?php
namespace Boletines\Webhook;

use Boletines\Models\Bounce as BounceModel;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint webhook para recibir notificaciones de bounces y quejas.
 *
 * URL pública:  /wp-json/boletines/v1/webhook/bounce?secret=XXXX
 *
 * Acepta dos formatos:
 *
 *  A) FORMATO GENÉRICO (JSON simple) — usable con FluentSMTP / cualquier integración:
 *     POST { "email": "x@y.com", "type": "hard|soft|complaint|block", "reason": "..." }
 *
 *  B) FORMATO AMAZON SES (vía SNS HTTP/S subscription):
 *     - El handler responde la SubscriptionConfirmation accediendo a SubscribeURL.
 *     - Procesa Notification con notificationType=Bounce|Complaint y desempaqueta los emails.
 */
class Bounce {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route(): void {
		register_rest_route(
			'boletines/v1',
			'/webhook/bounce',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_secret' ),
			)
		);
	}

	public function check_secret( \WP_REST_Request $req ): bool {
		$expected = (string) Plugin::get_setting( 'webhook_secret' );
		if ( ! $expected ) return false;
		$got = (string) ( $req->get_param( 'secret' ) ?: $req->get_header( 'X-Boletines-Secret' ) );
		return $got !== '' && hash_equals( $expected, $got );
	}

	public function handle( \WP_REST_Request $req ) {
		$body = $req->get_body();
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new \WP_REST_Response( array( 'error' => 'invalid_json' ), 400 );
		}

		// --- Formato Amazon SES / SNS ---
		if ( isset( $data['Type'] ) ) {
			return $this->handle_sns( $data );
		}

		// --- Formato genérico ---
		$email  = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$type   = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : 'soft';
		$reason = isset( $data['reason'] ) ? sanitize_text_field( $data['reason'] ) : '';
		$source = isset( $data['source'] ) ? sanitize_text_field( $data['source'] ) : 'webhook';

		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response( array( 'error' => 'invalid_email' ), 400 );
		}

		$res = BounceModel::record( $email, $type, $reason, $source );
		return rest_ensure_response( $res );
	}

	private function handle_sns( array $data ) {
		// Confirmación de suscripción SNS: visitar SubscribeURL.
		if ( ! empty( $data['Type'] ) && $data['Type'] === 'SubscriptionConfirmation' && ! empty( $data['SubscribeURL'] ) ) {
			wp_remote_get( $data['SubscribeURL'], array( 'timeout' => 10 ) );
			return rest_ensure_response( array( 'subscribed' => true ) );
		}

		if ( empty( $data['Type'] ) || $data['Type'] !== 'Notification' ) {
			return new \WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$message = isset( $data['Message'] ) ? json_decode( $data['Message'], true ) : null;
		if ( ! is_array( $message ) ) {
			return new \WP_REST_Response( array( 'error' => 'invalid_message' ), 400 );
		}

		$processed = 0;
		$nt = $message['notificationType'] ?? '';

		if ( $nt === 'Bounce' && ! empty( $message['bounce']['bouncedRecipients'] ) ) {
			$is_permanent = isset( $message['bounce']['bounceType'] ) && $message['bounce']['bounceType'] === 'Permanent';
			$type         = $is_permanent ? BounceModel::TYPE_HARD : BounceModel::TYPE_SOFT;
			foreach ( $message['bounce']['bouncedRecipients'] as $r ) {
				if ( ! empty( $r['emailAddress'] ) ) {
					BounceModel::record(
						$r['emailAddress'],
						$type,
						(string) ( $r['diagnosticCode'] ?? '' ),
						'ses_sns'
					);
					$processed++;
				}
			}
		} elseif ( $nt === 'Complaint' && ! empty( $message['complaint']['complainedRecipients'] ) ) {
			foreach ( $message['complaint']['complainedRecipients'] as $r ) {
				if ( ! empty( $r['emailAddress'] ) ) {
					BounceModel::record(
						$r['emailAddress'],
						BounceModel::TYPE_COMPLAINT,
						(string) ( $message['complaint']['complaintFeedbackType'] ?? '' ),
						'ses_sns'
					);
					$processed++;
				}
			}
		}

		return rest_ensure_response( array( 'processed' => $processed ) );
	}
}
