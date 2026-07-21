<?php
namespace Boletines\Models;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cola de envío con bloqueo "claim" (estilo Newsletter de Lissa) para evitar
 * que dos ejecuciones concurrentes envíen los mismos correos.
 */
class CampaignEmail {

	const STATUS_QUEUED  = 'queued';
	const STATUS_SENDING = 'sending';
	const STATUS_SENT    = 'sent';
	const STATUS_FAILED  = 'failed';

	/**
	 * Reclama N correos para enviar marcándolos como 'sending'.
	 * Devuelve los registros reclamados (con datos del suscriptor).
	 *
	 * @return array
	 */
	/** v2.1.8 — Correos aún por enviar de campañas activas. */
	public static function count_pending(): int {
		global $wpdb;
		$ce  = Plugin::table( 'campaign_emails' );
		$cmp = Plugin::table( 'campaigns' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$ce} ce
				 INNER JOIN {$cmp} c ON c.id = ce.campaign_id
				 WHERE ce.status = %s AND c.status IN (%s, %s)",
				self::STATUS_QUEUED,
				Campaign::STATUS_SENDING,
				Campaign::STATUS_SCHEDULED
			)
		);
	}

	public static function claim_batch( int $batch_size, int $worker_id ): array {
		global $wpdb;
		$ce  = Plugin::table( 'campaign_emails' );
		$sub = Plugin::table( 'subscribers' );
		$cmp = Plugin::table( 'campaigns' );

		// Marca primero hasta N como 'sending' con un identificador propio en error_msg
		// (lo usamos como token de claim temporal — luego lo limpiamos).
		$claim_token = 'claim:' . $worker_id . ':' . wp_generate_password( 8, false );
		$now         = current_time( 'mysql' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$ce} ce
				 INNER JOIN {$cmp} c ON c.id = ce.campaign_id
				 SET ce.status = %s, ce.error_msg = %s
				 WHERE ce.status = %s
				 AND c.status IN (%s, %s)
				 ORDER BY ce.id ASC
				 LIMIT %d",
				self::STATUS_SENDING,
				$claim_token,
				self::STATUS_QUEUED,
				Campaign::STATUS_SENDING,
				Campaign::STATUS_SCHEDULED,
				$batch_size
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ce.*, s.email, s.first_name, s.last_name, s.token AS subscriber_token,
				        c.subject, c.body_html, c.from_name, c.from_email, c.reply_to, c.preheader
				 FROM {$ce} ce
				 INNER JOIN {$sub} s ON s.id = ce.subscriber_id
				 INNER JOIN {$cmp} c  ON c.id = ce.campaign_id
				 WHERE ce.error_msg = %s",
				$claim_token
			),
			ARRAY_A
		);

		// Limpiamos el token de claim para no dejar residuo en error_msg.
		if ( ! empty( $rows ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$ce} SET error_msg = NULL WHERE error_msg = %s", $claim_token ) );
		}

		return $rows ?: array();
	}

	public static function mark_sent( int $id ): void {
		global $wpdb;
		$wpdb->update(
			Plugin::table( 'campaign_emails' ),
			array(
				'status'   => self::STATUS_SENT,
				'sent_at'  => current_time( 'mysql' ),
				'attempts' => 1,
			),
			array( 'id' => $id )
		);
	}

	public static function mark_failed( int $id, string $error, bool $retry = true ): void {
		global $wpdb;
		$tbl  = Plugin::table( 'campaign_emails' );
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT attempts, subscriber_id FROM {$tbl} WHERE id = %d", $id ), ARRAY_A );
		$next = ( $row ? (int) $row['attempts'] : 0 ) + 1;

		// Hasta 3 intentos: vuelve a queued; al cuarto, fallido definitivo.
		if ( $retry && $next < 3 ) {
			$wpdb->update(
				$tbl,
				array(
					'status'    => self::STATUS_QUEUED,
					'attempts'  => $next,
					'error_msg' => mb_substr( $error, 0, 255 ),
				),
				array( 'id' => $id )
			);
		} else {
			$wpdb->update(
				$tbl,
				array(
					'status'    => self::STATUS_FAILED,
					'attempts'  => $next,
					'error_msg' => mb_substr( $error, 0, 255 ),
				),
				array( 'id' => $id )
			);

			// Registrar como bounce soft local (sin webhook) — ayuda a detectar
			// direcciones inválidas aunque no haya webhook configurado.
			if ( $row && ! empty( $row['subscriber_id'] ) ) {
				$sub = \Boletines\Models\Subscriber::find_by_id( (int) $row['subscriber_id'] );
				if ( $sub ) {
					$type = self::guess_bounce_type( $error );
					\Boletines\Models\Bounce::record( $sub['email'], $type, $error, 'local_send_failure' );
				}
			}
		}
	}

	/** Heurística simple para distinguir hard de soft a partir del mensaje de error. */
	private static function guess_bounce_type( string $error ): string {
		$lower = strtolower( $error );
		$hard_signals = array( 'no such', 'does not exist', 'unknown user', 'invalid recipient', 'mailbox unavailable', 'user unknown', '550 5.1.1', '550 5.1.10' );
		foreach ( $hard_signals as $s ) {
			if ( strpos( $lower, $s ) !== false ) return \Boletines\Models\Bounce::TYPE_HARD;
		}
		return \Boletines\Models\Bounce::TYPE_SOFT;
	}

	public static function find_by_token( string $token ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Plugin::table( 'campaign_emails' ) . ' WHERE tracking_token = %s',
				$token
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function record_open( int $id ): void {
		global $wpdb;
		$tbl = Plugin::table( 'campaign_emails' );
		// Sólo registramos primera apertura.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tbl} SET opened_at = %s WHERE id = %d AND opened_at IS NULL",
				current_time( 'mysql' ),
				$id
			)
		);
		if ( $wpdb->rows_affected > 0 ) {
			// Sumar al contador agregado de la campaña.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT campaign_id FROM {$tbl} WHERE id = %d", $id ), ARRAY_A );
			if ( $row ) {
				$wpdb->query(
					$wpdb->prepare(
						'UPDATE ' . Plugin::table( 'campaigns' ) . ' SET total_opens = total_opens + 1 WHERE id = %d',
						(int) $row['campaign_id']
					)
				);
			}
		}
	}

	public static function record_click( int $id, string $url ): void {
		global $wpdb;
		$wpdb->insert(
			Plugin::table( 'clicks' ),
			array(
				'campaign_email_id' => $id,
				'url'               => esc_url_raw( $url ),
				'clicked_at'        => current_time( 'mysql' ),
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Plugin::table( 'campaign_emails' ) . ' SET clicks_count = clicks_count + 1 WHERE id = %d',
				$id
			)
		);
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT campaign_id FROM ' . Plugin::table( 'campaign_emails' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( $row ) {
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . Plugin::table( 'campaigns' ) . ' SET total_clicks = total_clicks + 1 WHERE id = %d',
					(int) $row['campaign_id']
				)
			);
		}
	}

	/** Suma 1 a total_sent en la campaña. */
	public static function increment_campaign_sent( int $campaign_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Plugin::table( 'campaigns' ) . ' SET total_sent = total_sent + 1 WHERE id = %d',
				$campaign_id
			)
		);
	}

	/** Cierra la campaña si ya no quedan emails en cola. */
	public static function maybe_finalize_campaign( int $campaign_id ): void {
		global $wpdb;
		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Plugin::table( 'campaign_emails' ) . " WHERE campaign_id = %d AND status IN ('queued','sending')",
				$campaign_id
			)
		);
		if ( 0 === $pending ) {
			$wpdb->update(
				Plugin::table( 'campaigns' ),
				array(
					'status'     => Campaign::STATUS_SENT,
					'sent_at'    => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $campaign_id )
			);
		}
	}
}
