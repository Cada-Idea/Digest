<?php
namespace Boletines\Housekeeping;

defined( 'ABSPATH' ) || exit;

/**
 * Limpieza automática de suscriptores (v2.0).
 * Defaults: inactivos 180 días sin abrir + 3 campañas recibidas; bounces 3.
 * Cron diario 02:00. Por defecto DESACTIVADO (destructivo).
 */
class Cleaner {
	const HOOK = 'boletines_housekeeping_run';

	public static function defaults(): array {
		return array(
			'enabled'           => 0,
			'inactive_days'     => 180,
			'inactive_min_sent' => 3,
			'bounce_threshold'  => 3,
			'dry_run'           => 0,
			'last_run'          => '',
			'last_run_summary'  => '',
		);
	}

	public static function settings(): array {
		$raw = (array) get_option( 'boletines_housekeeping', array() );
		return array_merge( self::defaults(), $raw );
	}

	public static function save_settings( array $partial ): void {
		$current = self::settings();
		foreach ( $partial as $k => $v ) $current[ $k ] = $v;
		update_option( 'boletines_housekeeping', $current, false );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$tomorrow = strtotime( 'tomorrow 02:00', current_time( 'timestamp' ) );
			wp_schedule_event( $tomorrow, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) wp_unschedule_event( $ts, self::HOOK );
	}

	public static function run(): array {
		$cfg = self::settings();
		if ( empty( $cfg['enabled'] ) ) return array( 'skipped' => 'disabled' );

		$dry = ! empty( $cfg['dry_run'] );

		$inactive_ids = self::find_inactive( (int) $cfg['inactive_days'], (int) $cfg['inactive_min_sent'] );
		$bounced_ids  = self::find_bounced( (int) $cfg['bounce_threshold'] );

		$to_delete = array_values( array_unique( array_merge( $inactive_ids, $bounced_ids ) ) );

		$deleted_count = 0;
		if ( ! $dry && $to_delete ) {
			$deleted_count = self::delete_subscribers( $to_delete );
		}

		$summary = array(
			'when'           => current_time( 'mysql' ),
			'inactive_found' => count( $inactive_ids ),
			'bounced_found'  => count( $bounced_ids ),
			'to_delete'      => count( $to_delete ),
			'deleted'        => $deleted_count,
			'dry_run'        => $dry ? 1 : 0,
		);

		self::save_settings( array(
			'last_run'         => $summary['when'],
			'last_run_summary' => wp_json_encode( $summary ),
		) );

		return $summary;
	}

	private static function find_inactive( int $days, int $min_sent ): array {
		global $wpdb;
		if ( $days < 1 )     $days = 1;
		if ( $min_sent < 1 ) $min_sent = 1;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$subs    = $wpdb->prefix . 'boletines_subscribers';
		$cemails = $wpdb->prefix . 'boletines_campaign_emails';

		// ¿Existe la tabla campaign_emails? Algunas instalaciones antiguas pueden no tenerla aún.
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cemails ) );
		if ( ! $exists ) return array();

		$sql = $wpdb->prepare( "
			SELECT s.id
			FROM {$subs} s
			LEFT JOIN {$cemails} ce
			       ON ce.subscriber_id = s.id AND ce.opened_at IS NOT NULL
			WHERE s.created_at < %s
			  AND s.status = 'confirmed'
			GROUP BY s.id
			HAVING MAX(ce.opened_at) IS NULL
			   AND (
			       SELECT COUNT(*) FROM {$cemails} ce2
			       WHERE ce2.subscriber_id = s.id AND ce2.status IN ('sent','opened','clicked')
			   ) >= %d
		", $cutoff, $min_sent );

		$rows = $wpdb->get_col( $sql );
		return array_map( 'intval', (array) $rows );
	}

	private static function find_bounced( int $threshold ): array {
		global $wpdb;
		if ( $threshold < 1 ) $threshold = 1;
		$bounces = $wpdb->prefix . 'boletines_bounces';
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bounces ) );
		if ( ! $exists ) return array();

		$rows = $wpdb->get_col( $wpdb->prepare( "
			SELECT subscriber_id
			FROM {$bounces}
			WHERE subscriber_id IS NOT NULL
			GROUP BY subscriber_id
			HAVING COUNT(*) >= %d
		", $threshold ) );
		return array_map( 'intval', (array) $rows );
	}

	private static function delete_subscribers( array $ids ): int {
		global $wpdb;
		if ( empty( $ids ) ) return 0;
		$ids = array_map( 'intval', $ids );
		$in  = implode( ',', $ids );

		$prefix = $wpdb->prefix;
		$wpdb->query( "DELETE FROM {$prefix}boletines_campaign_emails WHERE subscriber_id IN ({$in})" );
		$wpdb->query( "DELETE FROM {$prefix}boletines_subscriber_list WHERE subscriber_id IN ({$in})" );
		$wpdb->query( "DELETE FROM {$prefix}boletines_bounces WHERE subscriber_id IN ({$in})" );
		$wpdb->query( "DELETE FROM {$prefix}boletines_subscriber_meta WHERE subscriber_id IN ({$in})" );
		return (int) $wpdb->query( "DELETE FROM {$prefix}boletines_subscribers WHERE id IN ({$in})" );
	}
}

class CleanerBootstrap {
	public function register(): void {
		add_action( Cleaner::HOOK, array( Cleaner::class, 'run' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
	}
	public function maybe_schedule(): void {
		$cfg = Cleaner::settings();
		if ( ! empty( $cfg['enabled'] ) ) Cleaner::schedule();
		else                              Cleaner::unschedule();
	}
}
