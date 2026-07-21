<?php
namespace Boletines\Forms;

use Boletines\Models\ListModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bloque Gutenberg que envuelve el shortcode [boletines_form].
 * Lo dinamizamos con render_callback para que el atributo "list"
 * se actualice si cambian los IDs de las listas.
 */
class Block {

	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) return;

		// Script del editor.
		wp_register_script(
			'boletines-block',
			BOLETINES_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components' ),
			BOLETINES_VERSION,
			true
		);

		// Pasamos las listas al editor para el selector.
		$lists = array_map(
			function ( $l ) {
				return array( 'id' => (int) $l['id'], 'name' => $l['name'] );
			},
			ListModel::all()
		);
		wp_localize_script( 'boletines-block', 'BoletinesBlockData', array( 'lists' => $lists ) );

		register_block_type( 'boletines/form', array(
			'editor_script'   => 'boletines-block',
			'attributes'      => array(
				'listId'      => array( 'type' => 'number', 'default' => 0 ),
				'title'       => array( 'type' => 'string', 'default' => 'Suscríbete a nuestro boletín' ),
				'description' => array( 'type' => 'string', 'default' => 'Recibe nuestras novedades en tu correo.' ),
				'button'      => array( 'type' => 'string', 'default' => 'Suscribirme' ),
				'showName'    => array( 'type' => 'boolean', 'default' => true ),
			),
			'render_callback' => array( $this, 'render_block' ),
		) );
	}

	public function render_block( array $attrs ): string {
		$shortcode = sprintf(
			'[boletines_form list="%d" title="%s" description="%s" button="%s" name="%s"]',
			(int) ( $attrs['listId'] ?? 0 ),
			esc_attr( $attrs['title'] ?? '' ),
			esc_attr( $attrs['description'] ?? '' ),
			esc_attr( $attrs['button'] ?? '' ),
			! empty( $attrs['showName'] ) ? 'optional' : 'hide'
		);
		return do_shortcode( $shortcode );
	}
}
