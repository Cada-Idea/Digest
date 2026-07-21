<?php
namespace Boletines\Models;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Campaign {

	const STATUS_DRAFT     = 'draft';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_SENDING   = 'sending';
	const STATUS_SENT      = 'sent';
	const STATUS_PAUSED    = 'paused';

	public static function find( int $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Plugin::table( 'campaigns' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( $row ) {
			$row['list_ids'] = $row['list_ids'] ? array_map( 'intval', explode( ',', $row['list_ids'] ) ) : array();
		}
		return $row ?: null;
	}

	public static function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			Plugin::table( 'campaigns' ),
			array(
				'subject'    => sanitize_text_field( $data['subject'] ?? '' ),
				'preheader'  => sanitize_text_field( $data['preheader'] ?? '' ),
				'body_html'  => wp_kses_post( $data['body_html'] ?? '' ),
				'from_name'  => sanitize_text_field( $data['from_name'] ?? Plugin::get_setting( 'from_name' ) ),
				'from_email' => sanitize_email( $data['from_email'] ?? Plugin::get_setting( 'from_email' ) ),
				'reply_to'   => sanitize_email( $data['reply_to'] ?? Plugin::get_setting( 'reply_to' ) ),
				'list_ids'   => isset( $data['list_ids'] ) ? implode( ',', array_map( 'intval', (array) $data['list_ids'] ) ) : '',
				'status'     => self::STATUS_DRAFT,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$update = array( 'updated_at' => current_time( 'mysql' ) );

		$map = array(
			'subject'    => 'sanitize_text_field',
			'preheader'  => 'sanitize_text_field',
			'body_html'  => 'wp_kses_post',
			'from_name'  => 'sanitize_text_field',
			'from_email' => 'sanitize_email',
			'reply_to'   => 'sanitize_email',
		);
		foreach ( $map as $k => $sanitizer ) {
			if ( isset( $data[ $k ] ) ) {
				$update[ $k ] = call_user_func( $sanitizer, $data[ $k ] );
			}
		}
		if ( isset( $data['list_ids'] ) ) {
			$update['list_ids'] = implode( ',', array_map( 'intval', (array) $data['list_ids'] ) );
		}
		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_text_field( $data['status'] );
		}
		if ( isset( $data['scheduled_at'] ) ) {
			$update['scheduled_at'] = $data['scheduled_at'] ?: null;
		}

		return false !== $wpdb->update( Plugin::table( 'campaigns' ), $update, array( 'id' => $id ) );
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( Plugin::table( 'campaign_emails' ), array( 'campaign_id' => $id ) );
		return false !== $wpdb->delete( Plugin::table( 'campaigns' ), array( 'id' => $id ) );
	}

	public static function paginate( array $args = array() ): array {
		global $wpdb;
		$tbl  = Plugin::table( 'campaigns' );
		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();
		if ( $args['search'] !== '' ) {
			$where[]  = '(subject LIKE %s)';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		if ( $args['status'] !== '' ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		$where_sql = implode( ' AND ', $where );
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];

		$sql_count = "SELECT COUNT(*) FROM {$tbl} WHERE {$where_sql}";
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : (int) $wpdb->get_var( $sql_count );

		$sql_rows = "SELECT * FROM {$tbl} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$params2  = array_merge( $params, array( (int) $args['per_page'], $offset ) );
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql_rows, $params2 ), ARRAY_A );

		return array(
			'items'    => $rows,
			'total'    => $total,
			'per_page' => (int) $args['per_page'],
			'page'     => (int) $args['page'],
			'pages'    => (int) ceil( $total / max( 1, (int) $args['per_page'] ) ),
		);
	}

	/**
	 * Construye la cola de envío (campaign_emails) a partir de las listas elegidas.
	 * Devuelve cantidad encolada.
	 */
	public static function build_queue( int $campaign_id ): int {
		global $wpdb;
		$campaign = self::find( $campaign_id );
		if ( ! $campaign ) {
			return 0;
		}

		$list_ids = $campaign['list_ids'];
		if ( empty( $list_ids ) ) {
			return 0;
		}

		$piv  = Plugin::table( 'subscriber_list' );
		$sub  = Plugin::table( 'subscribers' );
		$mail = Plugin::table( 'campaign_emails' );

		$ph = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT s.id FROM {$sub} s INNER JOIN {$piv} sl ON sl.subscriber_id = s.id
			 WHERE s.status = 'confirmed' AND sl.list_id IN ({$ph})",
			$list_ids
		) ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$now    = current_time( 'mysql' );
		$values = array();
		$args   = array();
		foreach ( $ids as $sid ) {
			$values[] = '(%d, %d, %s, %s)';
			$args[]   = $campaign_id;
			$args[]   = $sid;
			$args[]   = 'queued';
			$args[]   = wp_generate_password( 32, false );
		}
		$chunks = array_chunk( $values, 200 );
		$args_o = $args;

		foreach ( $chunks as $i => $chunk ) {
			$slice = array_slice( $args_o, $i * 200 * 4, count( $chunk ) * 4 );
			$sql_i = "INSERT IGNORE INTO {$mail} (campaign_id, subscriber_id, status, tracking_token) VALUES " . implode( ',', $chunk );
			$wpdb->query( $wpdb->prepare( $sql_i, $slice ) );
		}

		$wpdb->update(
			Plugin::table( 'campaigns' ),
			array(
				'total_recipients' => count( $ids ),
				'updated_at'       => $now,
			),
			array( 'id' => $campaign_id )
		);

		return count( $ids );
	}

	/** Cuenta cuántos emails quedan por enviar. */
	public static function pending_in_queue( int $campaign_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Plugin::table( 'campaign_emails' ) . " WHERE campaign_id = %d AND status = 'queued'",
				$campaign_id
			)
		);
	}
}
