<?php
namespace Boletines\Models;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bounce {

	const TYPE_HARD      = 'hard';      // dirección no existe → desuscribir.
	const TYPE_SOFT      = 'soft';      // temporal (buzón lleno, etc.) → reintentar.
	const TYPE_COMPLAINT = 'complaint'; // marcado como spam → desuscribir.
	const TYPE_BLOCK     = 'block';     // bloqueado por el destinatario → desuscribir.

	/**
	 * Registra un bounce y aplica la política configurada.
	 * Devuelve un resumen con el id y la acción tomada.
	 */
	public static function record( string $email, string $type, string $reason = '', string $source = 'webhook' ): array {
		global $wpdb;
		$email = strtolower( trim( $email ) );
		if ( ! is_email( $email ) ) {
			return array( 'success' => false, 'reason' => 'invalid_email' );
		}

		$type = in_array( $type, array( self::TYPE_HARD, self::TYPE_SOFT, self::TYPE_COMPLAINT, self::TYPE_BLOCK ), true ) ? $type : self::TYPE_SOFT;

		$sub = Subscriber::find_by_email( $email );

		$wpdb->insert(
			Plugin::table( 'bounces' ),
			array(
				'subscriber_id' => $sub ? (int) $sub['id'] : null,
				'email'         => $email,
				'type'          => $type,
				'reason'        => mb_substr( (string) $reason, 0, 500 ),
				'source'        => mb_substr( (string) $source, 0, 40 ),
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$bounce_id = (int) $wpdb->insert_id;

		$action = 'logged';
		$auto_unsub = (int) Plugin::get_setting( 'auto_unsub_hard_bounce', 1 ) === 1;
		$threshold  = max( 1, (int) Plugin::get_setting( 'soft_bounce_threshold', 3 ) );

		if ( $sub ) {
			$is_terminal = in_array( $type, array( self::TYPE_HARD, self::TYPE_COMPLAINT, self::TYPE_BLOCK ), true );

			if ( $is_terminal && $auto_unsub ) {
				$wpdb->update(
					Plugin::table( 'subscribers' ),
					array(
						'status'     => Subscriber::STATUS_BOUNCED,
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => (int) $sub['id'] )
				);
				$action = 'marked_bounced';
			} elseif ( $type === self::TYPE_SOFT ) {
				// Cuántos soft bounces tiene en los últimos 30 días?
				$count = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . Plugin::table( 'bounces' ) . ' WHERE subscriber_id = %d AND type = %s AND created_at >= %s',
						(int) $sub['id'],
						self::TYPE_SOFT,
						gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) )
					)
				);
				if ( $count >= $threshold ) {
					$wpdb->update(
						Plugin::table( 'subscribers' ),
						array(
							'status'     => Subscriber::STATUS_BOUNCED,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => (int) $sub['id'] )
					);
					$action = 'marked_bounced_soft_threshold';
				}
			}
		}

		return array(
			'success'   => true,
			'bounce_id' => $bounce_id,
			'action'    => $action,
		);
	}

	public static function paginate( array $args = array() ): array {
		global $wpdb;
		$tbl = Plugin::table( 'bounces' );
		$args = wp_parse_args( $args, array( 'type' => '', 'per_page' => 25, 'page' => 1 ) );

		$where  = array( '1=1' );
		$params = array();
		if ( $args['type'] !== '' ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}
		$where_sql = implode( ' AND ', $where );
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];

		$sql_count = "SELECT COUNT(*) FROM {$tbl} WHERE {$where_sql}";
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : (int) $wpdb->get_var( $sql_count );

		$sql_rows = "SELECT * FROM {$tbl} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$params2  = array_merge( $params, array( (int) $args['per_page'], (int) $offset ) );
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql_rows, $params2 ), ARRAY_A );

		return array(
			'items'    => $rows,
			'total'    => $total,
			'per_page' => (int) $args['per_page'],
			'page'     => (int) $args['page'],
			'pages'    => (int) ceil( $total / max( 1, (int) $args['per_page'] ) ),
		);
	}

	public static function counts_by_type(): array {
		global $wpdb;
		$tbl = Plugin::table( 'bounces' );
		$out = array( self::TYPE_HARD => 0, self::TYPE_SOFT => 0, self::TYPE_COMPLAINT => 0, self::TYPE_BLOCK => 0 );
		$rows = $wpdb->get_results( "SELECT type, COUNT(*) AS c FROM {$tbl} GROUP BY type", ARRAY_A );
		foreach ( $rows as $r ) {
			$out[ $r['type'] ] = (int) $r['c'];
		}
		return $out;
	}
}
