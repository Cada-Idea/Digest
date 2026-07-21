<?php
namespace Boletines\Models;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form {

	const TYPE_INLINE         = 'inline';
	const TYPE_INLINE_MINIMAL = 'inline_minimal';
	const TYPE_WIDGET         = 'widget';
	const TYPE_POPUP         = 'popup';
	const TYPE_EXIT_INTENT   = 'exit_intent';
	const TYPE_SLIDE_IN      = 'slide_in';
	const TYPE_BAR_TOP       = 'bar_top';
	const TYPE_BAR_BOTTOM    = 'bar_bottom';
	const TYPE_AFTER_CONTENT = 'after_content';

	const STATUS_ACTIVE = 'active';
	const STATUS_PAUSED = 'paused';

	public static function types(): array {
		return array(
			self::TYPE_INLINE         => __( 'Inline (en página vía shortcode/bloque)', 'boletines' ),
			self::TYPE_INLINE_MINIMAL => __( 'Minimalista (estilo Mailchimp, se adapta al tema)', 'boletines' ),
			self::TYPE_WIDGET         => __( 'Widget minimalista (sidebar / footer)', 'boletines' ),
			self::TYPE_POPUP         => __( 'Pop-up con disparador temporal', 'boletines' ),
			self::TYPE_EXIT_INTENT   => __( 'Pop-up al intentar salir (exit-intent)', 'boletines' ),
			self::TYPE_SLIDE_IN      => __( 'Slide-in (esquina inferior)', 'boletines' ),
			self::TYPE_BAR_TOP       => __( 'Barra fija arriba', 'boletines' ),
			self::TYPE_BAR_BOTTOM    => __( 'Barra fija abajo', 'boletines' ),
			self::TYPE_AFTER_CONTENT => __( 'Después del contenido del post', 'boletines' ),
		);
	}

	/** Tipos que se inyectan automáticamente en el frontend (no necesitan shortcode). */
	public static function auto_types(): array {
		return array(
			self::TYPE_POPUP, self::TYPE_EXIT_INTENT, self::TYPE_SLIDE_IN,
			self::TYPE_BAR_TOP, self::TYPE_BAR_BOTTOM, self::TYPE_AFTER_CONTENT,
		);
	}

	public static function all( bool $active_only = false ): array {
		global $wpdb;
		$tbl  = Plugin::table( 'forms' );
		$sql  = "SELECT * FROM {$tbl}";
		if ( $active_only ) {
			$sql .= " WHERE status = '" . self::STATUS_ACTIVE . "'";
		}
		$sql .= ' ORDER BY id DESC';
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['list_ids'] = $r['list_ids'] ? array_map( 'intval', explode( ',', $r['list_ids'] ) ) : array();
			$r['options']  = $r['options']  ? (array) json_decode( $r['options'], true ) : array();
		}
		return $rows;
	}

	public static function find( int $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Plugin::table( 'forms' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( $row ) {
			$row['list_ids'] = $row['list_ids'] ? array_map( 'intval', explode( ',', $row['list_ids'] ) ) : array();
			$row['options']  = $row['options']  ? (array) json_decode( $row['options'], true ) : array();
		}
		return $row ?: null;
	}

	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$values = array(
			'name'              => sanitize_text_field( $data['name'] ?? '' ),
			'type'              => self::sanitize_type( $data['type'] ?? self::TYPE_INLINE ),
			'status'            => in_array( $data['status'] ?? '', array( self::STATUS_ACTIVE, self::STATUS_PAUSED ), true ) ? $data['status'] : self::STATUS_ACTIVE,
			'list_ids'          => isset( $data['list_ids'] ) ? implode( ',', array_map( 'intval', (array) $data['list_ids'] ) ) : '',
			'title'             => sanitize_text_field( $data['title'] ?? '' ),
			'description'       => sanitize_textarea_field( $data['description'] ?? '' ),
			'button_text'       => sanitize_text_field( $data['button_text'] ?? __( 'Suscribirme', 'boletines' ) ),
			'show_first_name'   => ! empty( $data['show_first_name'] ) ? 1 : 0,
			'show_last_name'    => ! empty( $data['show_last_name'] )  ? 1 : 0,
			'require_first_name'=> ! empty( $data['require_first_name'] ) ? 1 : 0,
			'options'           => isset( $data['options'] ) ? wp_json_encode( $data['options'] ) : null,
			'updated_at'        => $now,
		);

		if ( $id ) {
			$wpdb->update( Plugin::table( 'forms' ), $values, array( 'id' => $id ) );
			return $id;
		}
		$values['created_at'] = $now;
		$wpdb->insert( Plugin::table( 'forms' ), $values );
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( Plugin::table( 'forms' ), array( 'id' => $id ) );
	}

	public static function increment_impressions( int $id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Plugin::table( 'forms' ) . ' SET impressions = impressions + 1 WHERE id = %d', $id ) );
	}

	public static function increment_conversions( int $id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Plugin::table( 'forms' ) . ' SET conversions = conversions + 1 WHERE id = %d', $id ) );
	}

	private static function sanitize_type( string $type ): string {
		$valid = array_keys( self::types() );
		return in_array( $type, $valid, true ) ? $type : self::TYPE_INLINE;
	}

	/**
	 * Decide si un formulario debe mostrarse en la página actual según sus reglas.
	 */
	public static function should_display( array $form ): bool {
		if ( $form['status'] !== self::STATUS_ACTIVE ) return false;

		$opts = $form['options'];

		// Dispositivo.
		$device = $opts['device'] ?? 'all';
		if ( $device !== 'all' ) {
			$is_mobile = wp_is_mobile();
			if ( $device === 'mobile' && ! $is_mobile ) return false;
			if ( $device === 'desktop' && $is_mobile ) return false;
		}

		// Reglas de URL.
		$display_on = $opts['display_on'] ?? 'all';
		if ( $display_on === 'all' ) return true;
		if ( $display_on === 'home'   && ( is_front_page() || is_home() ) ) return true;
		if ( $display_on === 'posts'  && is_singular( 'post' ) )            return true;
		if ( $display_on === 'pages'  && is_page() )                         return true;
		if ( $display_on === 'singular' && is_singular() )                   return true;
		if ( $display_on === 'archive'  && is_archive() )                    return true;

		return false;
	}
}
