<?php
namespace Boletines\Models;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Subscriber {

	const STATUS_PENDING      = 'pending';
	const STATUS_CONFIRMED    = 'confirmed';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';
	const STATUS_BOUNCED      = 'bounced';

	/** Busca por email. */
	public static function find_by_email( string $email ) {
		global $wpdb;
		$table = Plugin::table( 'subscribers' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", strtolower( trim( $email ) ) ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function find_by_id( int $id ) {
		global $wpdb;
		$table = Plugin::table( 'subscribers' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public static function find_by_token( string $token ) {
		global $wpdb;
		$table = Plugin::table( 'subscribers' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Crea o actualiza un suscriptor (idempotente por email).
	 *
	 * @param array $data ['email','first_name','last_name','source','ip','status']
	 * @return int subscriber_id
	 */
	public static function upsert( array $data ): int {
		global $wpdb;
		$table = Plugin::table( 'subscribers' );
		$email = strtolower( trim( $data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return 0;
		}

		$now      = current_time( 'mysql' );
		$existing = self::find_by_email( $email );

		if ( $existing ) {
			$update = array( 'updated_at' => $now );
			foreach ( array( 'first_name', 'last_name', 'source', 'ip', 'lang' ) as $f ) {
				if ( ! empty( $data[ $f ] ) ) {
					$update[ $f ] = $data[ $f ];
				}
			}
			// Si estaba dado de baja y vuelve a suscribirse, lo dejamos en pending para confirmar.
			if ( $existing['status'] === self::STATUS_UNSUBSCRIBED ) {
				$update['status']         = self::STATUS_PENDING;
				$update['unsubscribed_at'] = null;
				$update['token']          = wp_generate_password( 32, false );
			}
			$wpdb->update( $table, $update, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}

		$wpdb->insert(
			$table,
			array(
				'email'       => $email,
				'first_name'  => $data['first_name'] ?? null,
				'last_name'   => $data['last_name'] ?? null,
				'status'      => $data['status'] ?? self::STATUS_PENDING,
				'token'       => wp_generate_password( 32, false ),
				'ip'          => $data['ip'] ?? null,
				'source'      => $data['source'] ?? 'form',
				'lang'        => $data['lang'] ?? null,
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function confirm( int $id ): bool {
		global $wpdb;
		$table = Plugin::table( 'subscribers' );
		$ok    = $wpdb->update(
			$table,
			array(
				'status'       => self::STATUS_CONFIRMED,
				'confirmed_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
		return false !== $ok;
	}

	public static function unsubscribe( int $id ): bool {
		global $wpdb;
		$table = Plugin::table( 'subscribers' );
		$ok    = $wpdb->update(
			$table,
			array(
				'status'           => self::STATUS_UNSUBSCRIBED,
				'unsubscribed_at'  => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
		return false !== $ok;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( Plugin::table( 'subscriber_list' ), array( 'subscriber_id' => $id ) );
		$wpdb->delete( Plugin::table( 'subscriber_meta' ), array( 'subscriber_id' => $id ) );
		return false !== $wpdb->delete( Plugin::table( 'subscribers' ), array( 'id' => $id ) );
	}

	// ===== Meta (v1.6) =====

	public static function set_meta( int $subscriber_id, string $key, $value ): void {
		global $wpdb;
		if ( $subscriber_id <= 0 || $key === '' ) return;
		$key   = sanitize_key( $key );
		$value = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );

		$wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . Plugin::table( 'subscriber_meta' ) . ' (subscriber_id, meta_key, meta_value, updated_at)
			 VALUES (%d, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)',
			$subscriber_id, $key, $value, current_time( 'mysql' )
		) );
	}

	public static function get_meta( int $subscriber_id, string $key, $default = '' ) {
		global $wpdb;
		$row = $wpdb->get_var( $wpdb->prepare(
			'SELECT meta_value FROM ' . Plugin::table( 'subscriber_meta' ) . ' WHERE subscriber_id = %d AND meta_key = %s LIMIT 1',
			$subscriber_id, sanitize_key( $key )
		) );
		return ( $row === null ) ? $default : $row;
	}

	public static function get_all_meta( int $subscriber_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT meta_key, meta_value FROM ' . Plugin::table( 'subscriber_meta' ) . ' WHERE subscriber_id = %d',
			$subscriber_id
		), ARRAY_A );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ $r['meta_key'] ] = $r['meta_value'];
		}
		return $out;
	}

	public static function delete_meta( int $subscriber_id, string $key ): void {
		global $wpdb;
		$wpdb->delete( Plugin::table( 'subscriber_meta' ), array(
			'subscriber_id' => $subscriber_id,
			'meta_key'      => sanitize_key( $key ),
		) );
	}

	/** Asigna a una o varias listas. */
	public static function attach_lists( int $subscriber_id, array $list_ids ): void {
		global $wpdb;
		$pivot = Plugin::table( 'subscriber_list' );
		$now   = current_time( 'mysql' );
		foreach ( $list_ids as $lid ) {
			$lid = (int) $lid;
			if ( $lid <= 0 ) {
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$pivot} (subscriber_id, list_id, created_at) VALUES (%d, %d, %s)",
					$subscriber_id,
					$lid,
					$now
				)
			);
		}
	}

	public static function detach_all_lists( int $subscriber_id ): void {
		global $wpdb;
		$wpdb->delete( Plugin::table( 'subscriber_list' ), array( 'subscriber_id' => $subscriber_id ) );
	}

	public static function list_ids_for( int $subscriber_id ): array {
		global $wpdb;
		$pivot = Plugin::table( 'subscriber_list' );
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT list_id FROM {$pivot} WHERE subscriber_id = %d", $subscriber_id ) ) );
	}

	/**
	 * Listado paginado para admin.
	 *
	 * @param array $args ['search','status','list_id','per_page','page','orderby','order']
	 */
	public static function paginate( array $args = array() ): array {
		global $wpdb;
		$tbl  = Plugin::table( 'subscribers' );
		$piv  = Plugin::table( 'subscriber_list' );

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'list_id'  => 0,
				'per_page' => 25,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$where[]  = '(s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like; $params[] = $like; $params[] = $like;
		}
		if ( $args['status'] !== '' ) {
			$where[]  = 's.status = %s';
			$params[] = $args['status'];
		}

		$join = '';
		if ( $args['list_id'] ) {
			$join     = " INNER JOIN {$piv} sl ON sl.subscriber_id = s.id ";
			$where[]  = 'sl.list_id = %d';
			$params[] = (int) $args['list_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$orderby   = in_array( $args['orderby'], array( 'created_at', 'email', 'status' ), true ) ? $args['orderby'] : 'created_at';
		$order     = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];

		$sql_count = "SELECT COUNT(DISTINCT s.id) FROM {$tbl} s {$join} WHERE {$where_sql}";
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : (int) $wpdb->get_var( $sql_count );

		$sql_rows = "SELECT DISTINCT s.* FROM {$tbl} s {$join} WHERE {$where_sql} ORDER BY s.{$orderby} {$order} LIMIT %d OFFSET %d";
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

	public static function counts_by_status(): array {
		global $wpdb;
		$tbl = Plugin::table( 'subscribers' );
		$out = array(
			self::STATUS_PENDING      => 0,
			self::STATUS_CONFIRMED    => 0,
			self::STATUS_UNSUBSCRIBED => 0,
			self::STATUS_BOUNCED      => 0,
		);
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$tbl} GROUP BY status", ARRAY_A );
		foreach ( $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['c'];
		}
		return $out;
	}
}
