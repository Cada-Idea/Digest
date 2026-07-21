<?php
namespace Boletines\Models;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Automation {

	const TYPE_POST_PUBLISHED    = 'post_published';
	const TYPE_PRODUCT_PUBLISHED = 'product_published';
	const TYPE_DIGEST_DAILY      = 'digest_daily';
	const TYPE_DIGEST_WEEKLY     = 'digest_weekly';
	const TYPE_BIRTHDAY          = 'birthday_email';

	const STATUS_ACTIVE = 'active';
	const STATUS_PAUSED = 'paused';

	public static function all( bool $active_only = false ): array {
		global $wpdb;
		$tbl  = Plugin::table( 'automations' );
		$sql  = "SELECT * FROM {$tbl}";
		if ( $active_only ) {
			$sql .= " WHERE status = '" . self::STATUS_ACTIVE . "'";
		}
		$sql .= ' ORDER BY name ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['list_ids'] = $r['list_ids'] ? array_map( 'intval', explode( ',', $r['list_ids'] ) ) : array();
			$r['tag_ids']  = $r['tag_ids']  ? array_map( 'intval', explode( ',', $r['tag_ids'] ) )  : array();
			$r['options']  = $r['options']  ? (array) json_decode( $r['options'], true ) : array();
		}
		return $rows;
	}

	public static function find( int $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Plugin::table( 'automations' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( $row ) {
			$row['list_ids'] = $row['list_ids'] ? array_map( 'intval', explode( ',', $row['list_ids'] ) ) : array();
			$row['tag_ids']  = $row['tag_ids']  ? array_map( 'intval', explode( ',', $row['tag_ids'] ) )  : array();
			$row['options']  = $row['options']  ? (array) json_decode( $row['options'], true ) : array();
		}
		return $row ?: null;
	}

	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$values = array(
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'type'        => sanitize_text_field( $data['type'] ?? self::TYPE_POST_PUBLISHED ),
			'status'      => in_array( $data['status'] ?? '', array( self::STATUS_ACTIVE, self::STATUS_PAUSED ), true ) ? $data['status'] : self::STATUS_ACTIVE,
			'list_ids'    => isset( $data['list_ids'] ) ? implode( ',', array_map( 'intval', (array) $data['list_ids'] ) ) : '',
			'tag_ids'     => isset( $data['tag_ids'] )  ? implode( ',', array_map( 'intval', (array) $data['tag_ids'] ) )  : '',
			'template_id' => ! empty( $data['template_id'] ) ? (int) $data['template_id'] : null,
			'options'     => isset( $data['options'] ) ? wp_json_encode( $data['options'] ) : null,
			'updated_at'  => $now,
		);

		if ( $id ) {
			$wpdb->update( Plugin::table( 'automations' ), $values, array( 'id' => $id ) );
			return $id;
		}
		$values['created_at'] = $now;
		$wpdb->insert( Plugin::table( 'automations' ), $values );
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( Plugin::table( 'automation_runs' ), array( 'automation_id' => $id ) );
		return false !== $wpdb->delete( Plugin::table( 'automations' ), array( 'id' => $id ) );
	}

	public static function mark_run( int $automation_id, int $object_id, string $object_type = 'post', ?int $campaign_id = null ): bool {
		global $wpdb;
		return false !== $wpdb->insert(
			Plugin::table( 'automation_runs' ),
			array(
				'automation_id' => $automation_id,
				'object_id'     => $object_id,
				'object_type'   => $object_type,
				'campaign_id'   => $campaign_id,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	public static function already_ran( int $automation_id, int $object_id, string $object_type = 'post' ): bool {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . Plugin::table( 'automation_runs' ) . ' WHERE automation_id = %d AND object_id = %d AND object_type = %s LIMIT 1',
				$automation_id,
				$object_id,
				$object_type
			)
		) > 0;
	}

	public static function update_last_run( int $id ): void {
		global $wpdb;
		$wpdb->update(
			Plugin::table( 'automations' ),
			array( 'last_run_at' => current_time( 'mysql' ) ),
			array( 'id' => $id )
		);
	}
}
