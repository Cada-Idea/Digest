<?php
namespace Boletines\Rest;

use Boletines\Mailer\Mailer;
use Boletines\Models\Campaign;
use Boletines\Models\CampaignEmail;
use Boletines\Models\ListModel;
use Boletines\Models\Subscriber;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api {

	const NAMESPACE = 'boletines/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/test-email',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_email' ),
				'permission_callback' => array( $this, 'check_admin' ),
				'args'                => array(
					'to'      => array( 'required' => true ),
					'subject' => array( 'required' => false ),
					'body'    => array( 'required' => false ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/campaigns/(?P<id>\d+)/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_campaign' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/campaigns/(?P<id>\d+)/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'preview_campaign' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/subscribers/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_subscriber' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/lists/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_list' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/automations/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_automation' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_form' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
	}

	public function check_admin( \WP_REST_Request $req ): bool {
		// REST nonce viene como header X-WP-Nonce (estándar WP).
		return current_user_can( 'manage_options' );
	}

	public function test_email( \WP_REST_Request $req ) {
		$to      = sanitize_email( $req->get_param( 'to' ) );
		$subject = sanitize_text_field( $req->get_param( 'subject' ) ?: __( 'Correo de prueba — Boletines', 'boletines' ) );
		$body    = wp_kses_post( $req->get_param( 'body' ) ?: '<p>Este es un correo de prueba enviado desde el plugin <strong>Boletines</strong>. Si lo recibes, tu configuración SMTP funciona.</p>' );

		if ( ! is_email( $to ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Correo inválido' ), 400 );
		}

		$ok = ( new Mailer() )->send_simple( $to, $subject, $body );
		return array(
			'success' => $ok,
			'message' => $ok ? __( 'Correo enviado.', 'boletines' ) : __( 'Falló el envío. Revisa tu plugin SMTP.', 'boletines' ),
		);
	}

	public function send_campaign( \WP_REST_Request $req ) {
		$id = (int) $req['id'];
		$campaign = Campaign::find( $id );
		if ( ! $campaign ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Campaña no encontrada' ), 404 );
		}
		if ( empty( $campaign['list_ids'] ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Selecciona al menos una lista' ), 400 );
		}
		if ( empty( trim( strip_tags( $campaign['body_html'] ) ) ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'El contenido está vacío' ), 400 );
		}

		// Construir cola y marcar como sending.
		$queued = Campaign::build_queue( $id );
		Campaign::update( $id, array( 'status' => Campaign::STATUS_SENDING ) );

		// Disparar el primer tick inmediatamente para no esperar al cron.
		wp_schedule_single_event( time() + 5, 'boletines_cron_send' );

		return array(
			'success'    => true,
			'queued'     => $queued,
			'message'    => sprintf( __( 'Campaña encolada con %d destinatarios.', 'boletines' ), $queued ),
		);
	}

	public function preview_campaign( \WP_REST_Request $req ) {
		$id = (int) $req['id'];
		$campaign = Campaign::find( $id );
		if ( ! $campaign ) {
			return new \WP_REST_Response( array( 'message' => 'No encontrada' ), 404 );
		}
		return array(
			'subject'   => $campaign['subject'],
			'preheader' => $campaign['preheader'],
			'body_html' => $campaign['body_html'],
		);
	}

	public function delete_subscriber( \WP_REST_Request $req ) {
		$id = (int) $req['id'];
		$ok = Subscriber::delete( $id );
		return array( 'success' => $ok );
	}

	public function delete_list( \WP_REST_Request $req ) {
		$id = (int) $req['id'];
		$ok = ListModel::delete( $id );
		return array( 'success' => $ok );
	}

	public function delete_automation( \WP_REST_Request $req ) {
		$id = (int) $req['id'];
		$ok = \Boletines\Models\Automation::delete( $id );
		return array( 'success' => $ok );
	}

	public function delete_form( \WP_REST_Request $req ) {
		$id = (int) $req['id'];
		$ok = \Boletines\Models\Form::delete( $id );
		return array( 'success' => $ok );
	}
}
