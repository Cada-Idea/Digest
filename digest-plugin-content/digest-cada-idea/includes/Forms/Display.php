<?php
namespace Boletines\Forms;

use Boletines\Models\Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inyecta los formularios "auto" en el frontend según sus reglas.
 *
 * El bug del popup en v1.4 era de orden de ejecución: enqueueábamos los assets
 * dentro de `wp_footer`, pero los scripts del footer se imprimen ANTES de
 * que nuestro callback corra. En v1.5 la detección se hace en `wp_enqueue_scripts`
 * (antes del footer) y los datos se pasan vía `wp_localize_script`.
 */
class Display {

	/** @var Renderer */
	private $renderer;

	/** @var array<int, array> Formularios elegibles para esta request. */
	private $eligible = array();

	/** @var bool */
	private $detected = false;

	public function register(): void {
		$this->renderer = new Renderer();
		add_action( 'wp_enqueue_scripts', array( $this, 'detect_and_enqueue' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_html' ), 50 );
		add_filter( 'the_content', array( $this, 'inject_after_content' ), 50 );
	}

	/** Detección temprana: en wp_enqueue_scripts ya conocemos la página, podemos elegir forms. */
	public function detect_and_enqueue(): void {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) return;

		$forms = Form::all( true );
		if ( empty( $forms ) ) return;

		$payload = array();
		foreach ( $forms as $form ) {
			if ( ! in_array( $form['type'], Form::auto_types(), true ) ) continue;
			if ( $form['type'] === Form::TYPE_AFTER_CONTENT ) continue;
			if ( ! Form::should_display( $form ) ) continue;

			$opts = $form['options'];
			$this->eligible[] = $form;

			$payload[] = array(
				'id'              => (int) $form['id'],
				'type'            => $form['type'],
				'trigger_seconds' => isset( $opts['trigger_seconds'] ) ? (int) $opts['trigger_seconds'] : 5,
				'scroll_percent'  => isset( $opts['scroll_percent'] )  ? (int) $opts['scroll_percent']  : 0,
				'frequency_days'  => isset( $opts['frequency_days'] )  ? (int) $opts['frequency_days']  : 7,
				'max_shows'       => isset( $opts['max_shows'] )       ? (int) $opts['max_shows']       : 0,
			);
		}

		$this->detected = true;

		if ( empty( $payload ) ) return;

		// Asegurar que los assets básicos del form están encolados.
		wp_enqueue_style( 'boletines-form' );
		wp_enqueue_script( 'boletines-form' );

		// Registrar y encolar el script de display, dependiendo del form básico.
		wp_register_script(
			'boletines-forms-display',
			BOLETINES_URL . 'assets/js/forms-display.js',
			array( 'boletines-form' ),
			BOLETINES_VERSION,
			true
		);
		wp_localize_script( 'boletines-forms-display', 'BoletinesFormsData', $payload );
		wp_enqueue_script( 'boletines-forms-display' );
	}

	/** Imprime el HTML de los formularios elegibles directamente al final del body. */
	public function render_html(): void {
		if ( ! $this->detected || empty( $this->eligible ) ) return;

		foreach ( $this->eligible as $form ) {
			echo $this->renderer->render( (int) $form['id'] );
			Form::increment_impressions( (int) $form['id'] );
		}
	}

	/**
	 * Filtro para after_content: inyecta el form después del párrafo Nº.
	 */
	public function inject_after_content( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$forms = Form::all( true );
		foreach ( $forms as $form ) {
			if ( $form['type'] !== Form::TYPE_AFTER_CONTENT ) continue;
			if ( ! Form::should_display( $form ) ) continue;

			// Asegurar assets.
			wp_enqueue_style( 'boletines-form' );
			wp_enqueue_script( 'boletines-form' );

			$opts  = $form['options'];
			$after = isset( $opts['after_paragraph'] ) ? max( 0, (int) $opts['after_paragraph'] ) : 0;
			$html  = $this->renderer->render( (int) $form['id'] );

			Form::increment_impressions( (int) $form['id'] );

			if ( $after === 0 ) {
				$content .= $html;
			} else {
				$content = $this->insert_after_paragraph( $content, $after, $html );
			}
		}

		return $content;
	}

	private function insert_after_paragraph( string $content, int $n, string $insert ): string {
		$parts = explode( '</p>', $content );
		if ( count( $parts ) <= $n ) {
			return $content . $insert;
		}
		array_splice( $parts, $n, 0, $insert . '<p style="margin:0;padding:0;">' );
		return implode( '</p>', $parts );
	}
}
