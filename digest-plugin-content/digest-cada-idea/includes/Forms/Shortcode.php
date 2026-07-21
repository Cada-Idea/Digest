<?php
namespace Boletines\Forms;

use Boletines\Models\ListModel;
use Boletines\Models\Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode [boletines_form ...]
 *
 * Atributos:
 *   list        IDs de listas separados por comas. Si vacío, usa todas las públicas.
 *   mode        single | picker | auto    (default: auto — single si hay 1 lista, picker si hay varias)
 *   title       Título visible
 *   description Subtítulo
 *   button      Texto del botón
 *   name        required | optional | hide
 *   last_name   required | optional | hide
 */
class Shortcode {

	public function register(): void {
		add_shortcode( 'boletines_form', array( $this, 'render' ) );
		add_shortcode( 'boletines_minimal', array( $this, 'render_minimal_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * v2.0.2 — [boletines_minimal list="1,2" title="..." button="Enviar" name="optional"]
	 * Renderiza un form minimalista (estilo Mailchimp) que se adapta al tema.
	 * No requiere crear un Form en admin: se configura por atributos del shortcode.
	 */
	public function render_minimal_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'list'              => '',
			'title'             => __( 'Suscríbete a nuestra lista exclusiva', 'boletines' ),
			'description'       => '',
			'button'            => __( 'Enviar', 'boletines' ),
			'name'              => 'optional',  // 'no' | 'optional' | 'required'
			'placeholder_email' => __( 'tu@correo.com', 'boletines' ),
			'placeholder_name'  => __( 'Tu nombre', 'boletines' ),
		), $atts, 'boletines_minimal' );

		$list_ids = array_filter( array_map( 'intval', explode( ',', (string) $atts['list'] ) ) );

		// Form sintético en memoria (no guardado en BD).
		$pseudo_form = array(
			'id'                 => 0, // ID 0 = inline ad-hoc
			'type'               => Form::TYPE_INLINE_MINIMAL,
			'title'              => (string) $atts['title'],
			'description'        => (string) $atts['description'],
			'button_text'        => (string) $atts['button'],
			'show_first_name'    => $atts['name'] === 'no' ? 0 : 1,
			'require_first_name' => $atts['name'] === 'required' ? 1 : 0,
			'show_last_name'     => 0,
			'require_last_name'  => 0,
			'list_ids'           => $list_ids,
			'options'            => array(
				'minimal_placeholder_email' => (string) $atts['placeholder_email'],
				'minimal_placeholder_name'  => (string) $atts['placeholder_name'],
			),
		);

		wp_enqueue_style( 'boletines-form' );
		wp_enqueue_script( 'boletines-form' );

		$renderer = new Renderer();
		return $renderer->render_pseudo( $pseudo_form );
	}

	public function register_assets(): void {
		wp_register_style(
			'boletines-form',
			BOLETINES_URL . 'assets/css/form.css',
			array(),
			BOLETINES_VERSION
		);
		wp_register_script(
			'boletines-form',
			BOLETINES_URL . 'assets/js/form.js',
			array(),
			BOLETINES_VERSION,
			true
		);
		wp_localize_script( 'boletines-form', 'BoletinesForm', array(
			'restUrl' => esc_url_raw( rest_url( 'boletines/v1/subscribe' ) ),
		) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array(
			'id'          => 0,
			'list'        => '',
			'mode'        => 'auto',
			'title'       => __( 'Suscríbete a nuestro boletín', 'boletines' ),
			'description' => __( 'Recibe nuestras novedades en tu correo.', 'boletines' ),
			'button'      => __( 'Suscribirme', 'boletines' ),
			'name'        => 'optional',
			'last_name'   => 'optional',
		), $atts, 'boletines_form' );

		// Si hay id="N", renderizar form guardado.
		if ( (int) $atts['id'] > 0 ) {
			wp_enqueue_style( 'boletines-form' );
			wp_enqueue_script( 'boletines-form' );
			$renderer = new Renderer();
			return $renderer->render( (int) $atts['id'] );
		}

		// Resolver listas.
		$list_ids = array_filter( array_map( 'intval', explode( ',', (string) $atts['list'] ) ) );
		$lists    = array();
		if ( ! empty( $list_ids ) ) {
			foreach ( $list_ids as $lid ) {
				$l = ListModel::find( $lid );
				if ( $l && (int) $l['is_public'] === 1 ) $lists[] = $l;
			}
		} else {
			$lists = array_values( array_filter( ListModel::all(), function ( $l ) {
				return (int) $l['is_public'] === 1;
			} ) );
		}

		// Modo: auto = picker si hay >1 lista.
		$mode = $atts['mode'];
		if ( $mode === 'auto' ) {
			$mode = count( $lists ) > 1 ? 'picker' : 'single';
		}

		wp_enqueue_style( 'boletines-form' );
		wp_enqueue_script( 'boletines-form' );

		// Color del botón resuelto (auto-detect del tema o setting manual).
		$accent     = \Boletines\Plugin::form_button_color();
		$accent_fg  = (string) \Boletines\Plugin::get_setting( 'form_button_text_color', '#ffffff' );

		$uid = wp_unique_id( 'bf-' );

		ob_start();
		?>
		<div class="bol-form-wrap" data-boletines-form data-mode="<?php echo esc_attr( $mode ); ?>" style="--bol-accent:<?php echo esc_attr( $accent ); ?>;--bol-accent-fg:<?php echo esc_attr( $accent_fg ); ?>;">
			<form class="bol-form-el" novalidate>
				<?php wp_nonce_field( 'boletines_subscribe', 'bol_nonce' ); ?>
				<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="bol-honeypot" aria-hidden="true">

				<?php if ( $atts['title'] ) : ?>
					<h3 class="bol-form-title"><?php echo esc_html( $atts['title'] ); ?></h3>
				<?php endif; ?>
				<?php if ( $atts['description'] ) : ?>
					<p class="bol-form-desc"><?php echo esc_html( $atts['description'] ); ?></p>
				<?php endif; ?>

				<?php if ( $atts['name'] !== 'hide' || $atts['last_name'] !== 'hide' ) : ?>
					<div class="bol-form-grid-2">
						<?php if ( $atts['name'] !== 'hide' ) : ?>
							<div class="bol-form-field">
								<label for="<?php echo esc_attr( $uid ); ?>-fn"><?php esc_html_e( 'Nombre', 'boletines' ); ?></label>
								<input type="text" id="<?php echo esc_attr( $uid ); ?>-fn" name="first_name" autocomplete="given-name" <?php echo $atts['name'] === 'required' ? 'required' : ''; ?>>
							</div>
						<?php endif; ?>
						<?php if ( $atts['last_name'] !== 'hide' ) : ?>
							<div class="bol-form-field">
								<label for="<?php echo esc_attr( $uid ); ?>-ln"><?php esc_html_e( 'Apellido', 'boletines' ); ?></label>
								<input type="text" id="<?php echo esc_attr( $uid ); ?>-ln" name="last_name" autocomplete="family-name" <?php echo $atts['last_name'] === 'required' ? 'required' : ''; ?>>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="bol-form-field">
					<label for="<?php echo esc_attr( $uid ); ?>-em"><?php esc_html_e( 'Correo electrónico', 'boletines' ); ?></label>
					<input type="email" id="<?php echo esc_attr( $uid ); ?>-em" name="email" autocomplete="email" required>
				</div>

				<?php if ( $mode === 'picker' && count( $lists ) > 0 ) : ?>
					<div class="bol-form-field">
						<label class="bol-form-picker-label"><?php esc_html_e( '¿A qué quieres suscribirte?', 'boletines' ); ?></label>
						<div class="bol-list-cards">
							<?php foreach ( $lists as $l ) : ?>
								<label class="bol-list-card">
									<input type="checkbox" name="lists[]" value="<?php echo (int) $l['id']; ?>">
									<div class="bol-list-card-inner">
										<div class="bol-list-card-icon">
											<?php
											if ( ! empty( $l['icon_svg'] ) ) {
												echo \Boletines\Models\ListModel::sanitize_svg( $l['icon_svg'] );
											} elseif ( ! empty( $l['image_url'] ) ) {
												echo '<img src="' . esc_url( $l['image_url'] ) . '" alt="" loading="lazy">';
											} else {
												echo $this->default_icon();
											}
											?>
										</div>
										<div class="bol-list-card-text">
											<strong><?php echo esc_html( $l['name'] ); ?></strong>
											<?php if ( ! empty( $l['description'] ) ) : ?>
												<span><?php echo esc_html( wp_trim_words( $l['description'], 14 ) ); ?></span>
											<?php endif; ?>
										</div>
										<svg class="bol-list-card-check" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php elseif ( count( $lists ) > 0 ) : ?>
					<?php $single = $lists[0]; ?>
					<input type="hidden" name="lists[]" value="<?php echo (int) $single['id']; ?>">
				<?php endif; ?>

				<button type="submit" class="bol-form-submit">
					<span class="bol-form-submit-label"><?php echo esc_html( $atts['button'] ); ?></span>
					<span class="bol-form-submit-spinner" aria-hidden="true"></span>
				</button>
			</form>

			<div class="bol-form-feedback" role="status" aria-live="polite" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function default_icon(): string {
		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}
}
