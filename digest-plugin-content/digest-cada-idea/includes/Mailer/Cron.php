<?php
namespace Boletines\Mailer;

use Boletines\Plugin;
use Boletines\Models\Campaign;
use Boletines\Models\CampaignEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron que procesa la cola por lotes respetando el límite de velocidad.
 * Usa un lock transitorio para evitar que dos ejecuciones se pisen
 * (estilo Newsletter de Lissa).
 */
class Cron {

	const LOCK_KEY     = 'boletines_send_lock';
	const COUNTER_KEY  = 'boletines_sent_window';
	const HOOK         = 'boletines_cron_send';
	const SCHEDULE     = 'boletines_every_1min';

	public function register(): void {
		add_action( self::HOOK, array( $this, 'tick' ) );
		// Permite forzar el envío vía URL desde el admin (?action=boletines_run_now).
		add_action( 'admin_post_boletines_run_now', array( $this, 'manual_run' ) );

		// Activar campañas programadas cuya hora ya llegó.
		add_action( self::HOOK, array( $this, 'activate_scheduled' ), 5 );

		// v2.1.8 — Auto-reparación: garantiza que el evento recurrente exista SIEMPRE
		// (en hosting compartido WP-Cron se puede perder). Barato: sólo (re)programa
		// si falta o quedó con un intervalo antiguo.
		add_action( 'init', array( $this, 'ensure_scheduled' ) );

		// v2.1.8 — Endpoint REST para drenar la cola de forma fiable:
		//   /wp-json/boletines/v1/drain?key=SECRET
		// Lo usa el loopback interno y puedes engancharlo a un cron real de Hostinger.
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
	}

	/** Reprograma el evento recurrente si falta o está con el intervalo viejo (5 min). */
	public function ensure_scheduled(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			$evt = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( self::HOOK ) : null;
			if ( $evt && isset( $evt->schedule ) && $evt->schedule === self::SCHEDULE ) {
				return; // ya está bien.
			}
			// Intervalo antiguo → limpiar y reprogramar al de 1 min.
			wp_clear_scheduled_hook( self::HOOK );
		}
		wp_schedule_event( time() + 30, self::SCHEDULE, self::HOOK );
	}

	public function register_rest(): void {
		register_rest_route( 'boletines/v1', '/drain', array(
			'methods'             => array( 'GET', 'POST' ),
			'permission_callback' => '__return_true',
			'callback'            => array( $this, 'rest_drain' ),
		) );
	}

	public function rest_drain( $request ) {
		$key = (string) $request->get_param( 'key' );
		if ( ! hash_equals( self::secret(), $key ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'error' => 'forbidden' ), 403 );
		}
		$this->tick();
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/** Secreto estable para el endpoint de drenaje. */
	public static function secret(): string {
		$s = get_option( 'boletines_cron_secret' );
		if ( ! $s ) {
			$s = wp_generate_password( 32, false );
			update_option( 'boletines_cron_secret', $s, false );
		}
		return $s;
	}

	public static function drain_url(): string {
		return add_query_arg( 'key', self::secret(), rest_url( 'boletines/v1/drain' ) );
	}

	/** Loopback no bloqueante para continuar drenando de inmediato. */
	private function spawn_loopback(): void {
		// Portabilidad: algunos hosts bloquean auto-peticiones (loopback). Se puede
		// desactivar con define('BOLETINES_DISABLE_LOOPBACK', true); el cron de 1 min
		// (o el cron externo) sigue drenando igualmente, sólo que por lotes.
		if ( defined( 'BOLETINES_DISABLE_LOOPBACK' ) && BOLETINES_DISABLE_LOOPBACK ) {
			return;
		}
		wp_remote_post( self::drain_url(), array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		) );
	}

	public function manual_run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		check_admin_referer( 'boletines_run_now' );
		$this->tick();
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=boletines' ) );
		exit;
	}

	/** Pasa campañas 'scheduled' a 'sending' cuando llega su hora. */
	public function activate_scheduled(): void {
		global $wpdb;
		$tbl = Plugin::table( 'campaigns' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tbl} SET status = %s, updated_at = %s
				 WHERE status = %s AND scheduled_at IS NOT NULL AND scheduled_at <= %s",
				Campaign::STATUS_SENDING,
				current_time( 'mysql' ),
				Campaign::STATUS_SCHEDULED,
				current_time( 'mysql' )
			)
		);

		// Construir cola para las que acaban de pasar a 'sending' y aún no la tienen.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id FROM {$tbl} WHERE status = %s AND total_recipients = 0", Campaign::STATUS_SENDING ),
			ARRAY_A
		);
		foreach ( $rows as $r ) {
			Campaign::build_queue( (int) $r['id'] );
		}
	}

	public function tick(): void {
		// Lock para que dos ejecuciones no envíen los mismos correos.
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}
		set_transient( self::LOCK_KEY, time(), 5 * MINUTE_IN_SECONDS );

		try {
			$speed_per_hour = max( 1, (int) Plugin::get_setting( 'speed_per_hour', 240 ) );
			$batch_size     = max( 1, (int) Plugin::get_setting( 'batch_size', 20 ) );

			// Cuántos hemos mandado en la última hora?
			$sent_in_window = $this->sent_in_last_hour();
			$remaining      = max( 0, $speed_per_hour - $sent_in_window );

			if ( 0 === $remaining ) {
				return;
			}

			$to_send = min( $batch_size, $remaining );
			$rows    = CampaignEmail::claim_batch( $to_send, getmypid() ?: 1 );
			if ( empty( $rows ) ) {
				return;
			}

			$mailer = new Mailer();
			$sent_count = 0;

			foreach ( $rows as $row ) {
				$result = $mailer->send_queued( $row );

				if ( true === $result ) {
					CampaignEmail::mark_sent( (int) $row['id'] );
					CampaignEmail::increment_campaign_sent( (int) $row['campaign_id'] );
					$this->bump_counter();
					$sent_count++;
				} else {
					CampaignEmail::mark_failed( (int) $row['id'], (string) $result );
				}
			}

			// Cerrar campañas que ya terminaron.
			$campaign_ids = array_unique( array_map( 'intval', wp_list_pluck( $rows, 'campaign_id' ) ) );
			foreach ( $campaign_ids as $cid ) {
				CampaignEmail::maybe_finalize_campaign( $cid );
			}

			// v2.1.8 — Continuación automática: si aún queda cola Y cupo en esta hora,
			// seguimos drenando de inmediato (loopback) en vez de esperar al próximo
			// tick. Si el cupo se agotó, el tick de 1 min reanuda solo al liberarse la
			// ventana rodante de 1 hora. Así nunca hay que "Procesar cola" a mano.
			$remaining_after = $remaining - $sent_count;
			if ( $sent_count > 0 && $remaining_after > 0 && CampaignEmail::count_pending() > 0 ) {
				// Liberamos el lock antes de disparar el loopback.
				delete_transient( self::LOCK_KEY );
				$this->spawn_loopback();
			}
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Cuenta cuántos correos se enviaron en la última hora consultando timestamps.
	 * Usamos un contador en memoria (transient) para velocidad y la BD como respaldo.
	 */
	private function sent_in_last_hour(): int {
		global $wpdb;
		$tbl = Plugin::table( 'campaign_emails' );
		// v2.1.8 — El umbral debe usar el MISMO reloj que sent_at (hora local vía
		// current_time), no UTC (gmdate/time), o el throttle correos/hora no se aplica.
		$threshold = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - HOUR_IN_SECONDS );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tbl} WHERE status = %s AND sent_at >= %s",
				CampaignEmail::STATUS_SENT,
				$threshold
			)
		);
	}

	private function bump_counter(): void {
		// Reservado para una optimización futura con transients.
	}
}
