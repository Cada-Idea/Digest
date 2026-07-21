<?php
namespace Boletines\Shortcodes;

use Boletines\Automations\RssToEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes que se procesan dentro del HTML de una campaña al enviarla.
 *
 *   [boletines_posts ids="1,2,3"]
 *   [boletines_posts category="news" count="3"]
 *   [boletines_popular_posts count="3" days="30"]
 *
 *   [boletines_products ids="1,2,3"]
 *   [boletines_products category="ofertas" count="3"]
 *   [boletines_top_products count="3"]                  → por valoración media
 *   [boletines_top_products count="3" by="sales"]       → por ventas totales
 */
class CampaignBlocks {

	public function register(): void {
		add_shortcode( 'boletines_posts',          array( $this, 'sc_posts' ) );
		add_shortcode( 'boletines_popular_posts',  array( $this, 'sc_popular_posts' ) );
		add_shortcode( 'boletines_products',       array( $this, 'sc_products' ) );
		add_shortcode( 'boletines_top_products',   array( $this, 'sc_top_products' ) );
	}

	public function sc_posts( $atts ): string {
		$atts = shortcode_atts( array(
			'ids'      => '',
			'category' => '',
			'count'    => 3,
		), $atts );
		$posts = $this->fetch_objects( 'post', $atts );
		return $this->render_blocks( $posts );
	}

	public function sc_popular_posts( $atts ): string {
		$atts = shortcode_atts( array(
			'count' => 3,
			'days'  => 30,
		), $atts );
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['count'] ),
			'orderby'        => 'comment_count',
			'order'          => 'DESC',
		);
		if ( (int) $atts['days'] > 0 ) {
			$args['date_query'] = array( array( 'after' => gmdate( 'Y-m-d', time() - ( (int) $atts['days'] * DAY_IN_SECONDS ) ) ) );
		}
		return $this->render_blocks( get_posts( $args ) );
	}

	public function sc_products( $atts ): string {
		if ( ! class_exists( 'WooCommerce' ) ) return '';
		$atts = shortcode_atts( array(
			'ids'      => '',
			'category' => '',
			'count'    => 3,
		), $atts );
		$products = $this->fetch_objects( 'product', $atts, 'product_cat' );
		return $this->render_blocks( $products );
	}

	public function sc_top_products( $atts ): string {
		if ( ! class_exists( 'WooCommerce' ) ) return '';
		$atts = shortcode_atts( array(
			'count' => 3,
			'by'    => 'rating', // rating | sales
		), $atts );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['count'] ),
		);
		if ( $atts['by'] === 'sales' ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'total_sales';
			$args['order']    = 'DESC';
		} else {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_wc_average_rating';
			$args['order']    = 'DESC';
		}
		return $this->render_blocks( get_posts( $args ) );
	}

	// ---- helpers ----

	private function fetch_objects( string $post_type, array $atts, string $taxonomy = 'category' ): array {
		// Ids específicos.
		if ( ! empty( $atts['ids'] ) ) {
			$ids = array_filter( array_map( 'intval', explode( ',', (string) $atts['ids'] ) ) );
			if ( empty( $ids ) ) return array();
			return get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $ids ),
			) );
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) ( $atts['count'] ?? 3 ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $atts['category'] ) ) {
			$slugs = array_filter( array_map( 'trim', explode( ',', (string) $atts['category'] ) ) );
			if ( ! empty( $slugs ) ) {
				$args['tax_query'] = array( array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slugs,
				) );
			}
		}

		return get_posts( $args );
	}

	private function render_blocks( array $objects ): string {
		if ( empty( $objects ) ) return '';
		$out = '';
		foreach ( $objects as $obj ) {
			$out .= RssToEmail::render_object_block( $obj );
		}
		return $out;
	}
}
