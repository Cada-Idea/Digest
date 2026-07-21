<?php
namespace Boletines\Models;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListModel {

	public static function all(): array {
		global $wpdb;
		$tbl = Plugin::table( 'lists' );
		return $wpdb->get_results( "SELECT * FROM {$tbl} ORDER BY name ASC", ARRAY_A );
	}

	public static function find( int $id ) {
		global $wpdb;
		$tbl = Plugin::table( 'lists' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	public static function create( array $data ): int {
		global $wpdb;
		$tbl   = Plugin::table( 'lists' );
		$slug  = sanitize_title( $data['slug'] ?? $data['name'] ?? '' );
		$slug  = self::ensure_unique_slug( $slug );
		$wpdb->insert(
			$tbl,
			array(
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				'slug'        => $slug,
				'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
				'image_url'   => isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : null,
				'icon_svg'    => isset( $data['icon_svg'] )  ? self::sanitize_svg( $data['icon_svg'] ) : null,
				'is_public'   => ! empty( $data['is_public'] ) ? 1 : 0,
				'created_at'  => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$tbl    = Plugin::table( 'lists' );
		$update = array();
		if ( isset( $data['name'] ) )        $update['name']        = sanitize_text_field( $data['name'] );
		if ( isset( $data['description'] ) ) $update['description'] = wp_kses_post( $data['description'] );
		if ( isset( $data['image_url'] ) )   $update['image_url']   = $data['image_url'] ? esc_url_raw( $data['image_url'] ) : null;
		if ( isset( $data['icon_svg'] ) )    $update['icon_svg']    = $data['icon_svg'] ? self::sanitize_svg( $data['icon_svg'] ) : null;
		if ( isset( $data['is_public'] ) )   $update['is_public']   = $data['is_public'] ? 1 : 0;
		if ( isset( $data['slug'] ) ) {
			$slug             = sanitize_title( $data['slug'] );
			$update['slug']   = self::ensure_unique_slug( $slug, $id );
		}
		if ( ! $update ) return false;
		return false !== $wpdb->update( $tbl, $update, array( 'id' => $id ) );
	}

	/** Sanea SVG inline permitiendo sólo etiquetas seguras y atributos básicos. */
	public static function sanitize_svg( string $svg ): string {
		$svg = trim( $svg );
		if ( $svg === '' ) return '';
		$allowed = array(
			'svg'      => array( 'xmlns' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'width' => true, 'height' => true, 'class' => true, 'aria-hidden' => true, 'role' => true ),
			'g'        => array( 'fill' => true, 'stroke' => true, 'transform' => true ),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true ),
			'ellipse'  => array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
			'title'    => array(),
			'desc'     => array(),
			'defs'     => array(),
			'use'      => array( 'href' => true, 'x' => true, 'y' => true ),
		);
		// Bloquear scripts y handlers de eventos.
		$svg = preg_replace( '#<script\b[^>]*>.*?</script\s*>#is', '', $svg );
		$svg = preg_replace( '#\son\w+\s*=\s*"[^"]*"#i', '', $svg );
		$svg = preg_replace( "#\son\w+\s*=\s*'[^']*'#i", '', $svg );
		return wp_kses( $svg, $allowed );
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( Plugin::table( 'subscriber_list' ), array( 'list_id' => $id ) );
		return false !== $wpdb->delete( Plugin::table( 'lists' ), array( 'id' => $id ) );
	}

	public static function subscriber_count( int $list_id, string $status = 'confirmed' ): int {
		global $wpdb;
		$piv = Plugin::table( 'subscriber_list' );
		$sub = Plugin::table( 'subscribers' );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$piv} sl INNER JOIN {$sub} s ON s.id = sl.subscriber_id WHERE sl.list_id = %d AND s.status = %s",
				$list_id,
				$status
			)
		);
	}

	private static function ensure_unique_slug( string $slug, int $exclude_id = 0 ): string {
		global $wpdb;
		$tbl  = Plugin::table( 'lists' );
		$base = $slug ?: 'lista';
		$try  = $base;
		$i    = 2;
		while ( true ) {
			$found = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$tbl} WHERE slug = %s AND id <> %d LIMIT 1", $try, $exclude_id )
			);
			if ( ! $found ) {
				return $try;
			}
			$try = $base . '-' . $i++;
		}
	}
}
