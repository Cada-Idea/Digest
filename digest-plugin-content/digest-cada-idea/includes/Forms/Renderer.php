<?php
namespace Boletines\Forms;

use Boletines\Models\Form;
use Boletines\Models\ListModel;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderiza el HTML de un formulario guardado en la BD.
 * Compatible con cualquier tipo (inline, popup, slide-in, bar, etc.).
 */
class Renderer {

	/**
	 * v2.0.2 — Render de un form sintético (no guardado en BD). Usado por
	 * el shortcode [boletines_minimal] para permitir embed estilo Mailchimp.
	 */
	public function render_pseudo( array $pseudo_form ): string {
		if ( ( $pseudo_form['type'] ?? '' ) === Form::TYPE_INLINE_MINIMAL ) {
			return $this->render_minimal( $pseudo_form );
		}
		return $this->render_inner( $pseudo_form );
	}

	/** Renderiza un Form completo (incluyendo wrappers según tipo). */
	public function render( int $form_id ): string {
		$form = Form::find( $form_id );
		if ( ! $form ) return '';

		$inner = $this->render_inner( $form );

		switch ( $form['type'] ) {
			case Form::TYPE_INLINE_MINIMAL:
				return $this->render_minimal( $form );
			case Form::TYPE_POPUP:
			case Form::TYPE_EXIT_INTENT:
				return $this->wrap_popup( $form, $inner );
			case Form::TYPE_SLIDE_IN:
				return $this->wrap_slidein( $form, $inner );
			case Form::TYPE_BAR_TOP:
			case Form::TYPE_BAR_BOTTOM:
				return $this->wrap_bar( $form, $inner );
			case Form::TYPE_WIDGET:
				return $this->wrap_widget( $form );
			default:
				return $inner;
		}
	}

	/** Renderiza el form-wrap interior con sus campos. */
	private function render_inner( array $form ): string {
		$accent     = Plugin::form_button_color();
		$accent_fg  = (string) Plugin::get_setting( 'form_button_text_color', '#ffffff' );
		$uid        = wp_unique_id( 'bf-' );

		// Resolver listas (públicas, las del form).
		$lists = array();
		if ( ! empty( $form['list_ids'] ) ) {
			foreach ( $form['list_ids'] as $lid ) {
				$l = ListModel::find( $lid );
				if ( $l ) $lists[] = $l;
			}
		}

		ob_start();
		?>
		<div class="bol-form-wrap" data-boletines-form data-form-id="<?php echo (int) $form['id']; ?>" data-mode="<?php echo count( $lists ) > 1 ? 'picker' : 'single'; ?>" style="--bol-accent:<?php echo esc_attr( $accent ); ?>;--bol-accent-fg:<?php echo esc_attr( $accent_fg ); ?>;">
			<form class="bol-form-el" novalidate>
				<?php wp_nonce_field( 'boletines_subscribe', 'bol_nonce' ); ?>
				<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="bol-honeypot" aria-hidden="true">
				<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">

				<?php if ( ! empty( $form['title'] ) ) : ?>
					<h3 class="bol-form-title"><?php echo esc_html( $form['title'] ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $form['description'] ) ) : ?>
					<p class="bol-form-desc"><?php echo esc_html( $form['description'] ); ?></p>
				<?php endif; ?>

				<?php $show_fn = (int) $form['show_first_name'] === 1; $show_ln = (int) $form['show_last_name'] === 1; ?>
				<?php if ( $show_fn || $show_ln ) : ?>
					<div class="bol-form-grid-2">
						<?php if ( $show_fn ) : ?>
							<div class="bol-form-field">
								<label for="<?php echo esc_attr( $uid ); ?>-fn"><?php esc_html_e( 'Nombre', 'boletines' ); ?></label>
								<input type="text" id="<?php echo esc_attr( $uid ); ?>-fn" name="first_name" autocomplete="given-name" <?php echo (int) $form['require_first_name'] === 1 ? 'required' : ''; ?>>
							</div>
						<?php endif; ?>
						<?php if ( $show_ln ) : ?>
							<div class="bol-form-field">
								<label for="<?php echo esc_attr( $uid ); ?>-ln"><?php esc_html_e( 'Apellido', 'boletines' ); ?></label>
								<input type="text" id="<?php echo esc_attr( $uid ); ?>-ln" name="last_name" autocomplete="family-name">
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="bol-form-field">
					<label for="<?php echo esc_attr( $uid ); ?>-em"><?php esc_html_e( 'Correo electrónico', 'boletines' ); ?></label>
					<input type="email" id="<?php echo esc_attr( $uid ); ?>-em" name="email" autocomplete="email" required>
				</div>

				<?php
				$opts        = $form['options'];
				$show_bday   = ! empty( $opts['show_birthday'] );
				$req_bday    = ! empty( $opts['require_birthday'] );
				?>

				<?php if ( $show_bday ) : ?>
					<div class="bol-form-field">
						<label for="<?php echo esc_attr( $uid ); ?>-bd"><?php esc_html_e( 'Tu cumpleaños', 'boletines' ); ?></label>
						<input type="date" id="<?php echo esc_attr( $uid ); ?>-bd" name="birthday" autocomplete="bday" <?php echo $req_bday ? 'required' : ''; ?>>
					</div>
				<?php endif; ?>

				<?php if ( count( $lists ) > 1 ) : ?>
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
												echo ListModel::sanitize_svg( $l['icon_svg'] );
											} elseif ( ! empty( $l['image_url'] ) ) {
												echo '<img src="' . esc_url( $l['image_url'] ) . '" alt="" loading="lazy">';
											} else {
												echo '<svg viewBox="0 0 24 24" fill="none"><path d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
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
				<?php elseif ( count( $lists ) === 1 ) : ?>
					<input type="hidden" name="lists[]" value="<?php echo (int) $lists[0]['id']; ?>">
				<?php endif; ?>

				<button type="submit" class="bol-form-submit">
					<span class="bol-form-submit-label"><?php echo esc_html( $form['button_text'] ?: __( 'Suscribirme', 'boletines' ) ); ?></span>
					<span class="bol-form-submit-spinner" aria-hidden="true"></span>
				</button>
			</form>

			<div class="bol-form-feedback" role="status" aria-live="polite" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * v2.0.2 — Render minimalista estilo Mailchimp. Se adapta al tema:
	 * usa colores del tema (variables CSS comunes + currentColor) y hereda
	 * tipografía. Responsive: desktop = 1 línea (nombre + email + botón);
	 * mobile = stack vertical.
	 *
	 * Listas: si hay varias, los suscriptores van a TODAS las listas configuradas
	 * en el form (no hay picker en minimal, para mantener simplicidad).
	 */
	private function render_minimal( array $form ): string {
		$accent     = Plugin::form_button_color();
		$accent_fg  = (string) Plugin::get_setting( 'form_button_text_color', '#ffffff' );
		$uid        = wp_unique_id( 'bfm-' );
		$show_fn    = (int) $form['show_first_name'] === 1;

		$opts        = $form['options'] ?? array();
		$placeholder_email = $opts['minimal_placeholder_email'] ?? __( 'tu@correo.com', 'boletines' );
		$placeholder_name  = $opts['minimal_placeholder_name']  ?? __( 'Tu nombre', 'boletines' );
		$btn_label   = $form['button_text'] ?: __( 'Enviar', 'boletines' );

		// Pre-resolvemos lists (todas — sin picker en minimal).
		$lists = array();
		if ( ! empty( $form['list_ids'] ) ) {
			foreach ( $form['list_ids'] as $lid ) $lists[] = (int) $lid;
		}

		ob_start();
		?>
		<div class="bol-minimal" data-boletines-form data-form-id="<?php echo (int) $form['id']; ?>" style="--bol-accent:<?php echo esc_attr( $accent ); ?>;--bol-accent-fg:<?php echo esc_attr( $accent_fg ); ?>;">
			<?php if ( ! empty( $form['title'] ) ) : ?>
				<h3 class="bol-minimal-title"><?php echo esc_html( $form['title'] ); ?></h3>
			<?php endif; ?>
			<?php if ( ! empty( $form['description'] ) ) : ?>
				<p class="bol-minimal-desc"><?php echo esc_html( $form['description'] ); ?></p>
			<?php endif; ?>

			<form class="bol-minimal-form bol-form-el" novalidate>
				<?php wp_nonce_field( 'boletines_subscribe', 'bol_nonce' ); ?>
				<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="bol-honeypot" aria-hidden="true">
				<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
				<?php foreach ( $lists as $lid ) : ?>
					<input type="hidden" name="lists[]" value="<?php echo (int) $lid; ?>">
				<?php endforeach; ?>

				<?php if ( $show_fn ) : ?>
					<input type="text" class="bol-minimal-input" name="first_name" placeholder="<?php echo esc_attr( $placeholder_name ); ?>" autocomplete="given-name" <?php echo (int) $form['require_first_name'] === 1 ? 'required' : ''; ?>>
				<?php endif; ?>

				<input type="email" class="bol-minimal-input" name="email" placeholder="<?php echo esc_attr( $placeholder_email ); ?>" autocomplete="email" required>

				<button type="submit" class="bol-minimal-btn">
					<span class="bol-form-submit-label"><?php echo esc_html( $btn_label ); ?></span>
					<span class="bol-form-submit-spinner" aria-hidden="true"></span>
				</button>
			</form>

			<div class="bol-form-feedback bol-minimal-feedback" role="status" aria-live="polite" hidden></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}


	private function wrap_popup( array $form, string $inner ): string {
		$opts = $form['options'];
		$show_close = ! isset( $opts['show_close'] ) || ! empty( $opts['show_close'] );
		$html  = '<div class="bol-form-overlay" data-bol-overlay data-form-id="' . (int) $form['id'] . '">';
		$html .= '<div class="bol-form-popup">';
		if ( $show_close ) {
			$html .= '<button type="button" class="bol-form-close" data-bol-close aria-label="' . esc_attr__( 'Cerrar', 'boletines' ) . '">×</button>';
		}
		$html .= $inner;
		$html .= '</div></div>';
		return $html;
	}

	private function wrap_slidein( array $form, string $inner ): string {
		$opts = $form['options'];
		$show_close = ! isset( $opts['show_close'] ) || ! empty( $opts['show_close'] );
		$html  = '<div class="bol-form-slidein" data-bol-slidein data-form-id="' . (int) $form['id'] . '">';
		if ( $show_close ) {
			$html .= '<button type="button" class="bol-form-close" data-bol-close aria-label="' . esc_attr__( 'Cerrar', 'boletines' ) . '" style="position:absolute;top:8px;right:8px;">×</button>';
		}
		$html .= $inner;
		$html .= '</div>';
		return $html;
	}

	private function wrap_bar( array $form, string $inner ): string {
		$position_class = $form['type'] === Form::TYPE_BAR_TOP ? 'is-top' : 'is-bottom';
		$opts = $form['options'];
		$show_close = ! isset( $opts['show_close'] ) || ! empty( $opts['show_close'] );

		$accent     = Plugin::form_button_color();
		$accent_fg  = (string) Plugin::get_setting( 'form_button_text_color', '#ffffff' );

		ob_start();
		?>
		<div class="bol-form-bar <?php echo esc_attr( $position_class ); ?>" data-bol-bar data-form-id="<?php echo (int) $form['id']; ?>" style="--bol-accent:<?php echo esc_attr( $accent ); ?>;--bol-accent-fg:<?php echo esc_attr( $accent_fg ); ?>;">
			<div class="bol-form-bar-inner">
				<div class="bol-form-bar-text">
					<?php if ( ! empty( $form['title'] ) ) : ?><strong><?php echo esc_html( $form['title'] ); ?></strong><?php endif; ?>
					<?php if ( ! empty( $form['description'] ) ) : ?><span><?php echo esc_html( $form['description'] ); ?></span><?php endif; ?>
				</div>
				<form class="bol-form-el bol-form-bar-form" novalidate data-boletines-form data-form-id="<?php echo (int) $form['id']; ?>">
					<?php wp_nonce_field( 'boletines_subscribe', 'bol_nonce' ); ?>
					<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="bol-honeypot" aria-hidden="true">
					<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
					<?php foreach ( $form['list_ids'] as $lid ) : ?>
						<input type="hidden" name="lists[]" value="<?php echo (int) $lid; ?>">
					<?php endforeach; ?>
					<input type="email" name="email" placeholder="<?php esc_attr_e( 'tu@correo.com', 'boletines' ); ?>" required>
					<button type="submit" class="bol-form-submit">
						<span class="bol-form-submit-label"><?php echo esc_html( $form['button_text'] ?: __( 'Suscribirme', 'boletines' ) ); ?></span>
						<span class="bol-form-submit-spinner" aria-hidden="true"></span>
					</button>
				</form>
				<?php if ( $show_close ) : ?>
					<button type="button" class="bol-form-close" data-bol-close aria-label="<?php esc_attr_e( 'Cerrar', 'boletines' ); ?>">×</button>
				<?php endif; ?>
				<div class="bol-form-feedback" role="status" aria-live="polite" hidden></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Widget minimalista: pill horizontal con email + (opcional) nombre + botón.
	 * Adaptable PC/tablet/móvil — usa flex-wrap para reorganizarse.
	 */
	private function wrap_widget( array $form ): string {
		$accent     = \Boletines\Plugin::form_button_color();
		$accent_fg  = (string) \Boletines\Plugin::get_setting( 'form_button_text_color', '#ffffff' );
		$show_name  = (int) $form['show_first_name'] === 1;

		ob_start();
		?>
		<div class="bol-form-wrap bol-form-widget" data-boletines-form data-form-id="<?php echo (int) $form['id']; ?>" data-mode="single" style="--bol-accent:<?php echo esc_attr( $accent ); ?>;--bol-accent-fg:<?php echo esc_attr( $accent_fg ); ?>;">
			<form class="bol-form-el" novalidate>
				<?php wp_nonce_field( 'boletines_subscribe', 'bol_nonce' ); ?>
				<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="bol-honeypot" aria-hidden="true">
				<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">
				<?php foreach ( $form['list_ids'] as $lid ) : ?>
					<input type="hidden" name="lists[]" value="<?php echo (int) $lid; ?>">
				<?php endforeach; ?>

				<?php if ( ! empty( $form['title'] ) ) : ?>
					<h4 class="bol-form-widget-title"><?php echo esc_html( $form['title'] ); ?></h4>
				<?php endif; ?>
				<?php if ( ! empty( $form['description'] ) ) : ?>
					<p class="bol-form-widget-desc"><?php echo esc_html( $form['description'] ); ?></p>
				<?php endif; ?>

				<div class="bol-form-widget-row <?php echo $show_name ? 'has-name' : ''; ?>">
					<?php if ( $show_name ) : ?>
						<input type="text" name="first_name" placeholder="<?php esc_attr_e( 'Tu nombre', 'boletines' ); ?>" autocomplete="given-name" <?php echo (int) $form['require_first_name'] === 1 ? 'required' : ''; ?>>
					<?php endif; ?>
					<input type="email" name="email" placeholder="<?php esc_attr_e( 'tu@correo.com', 'boletines' ); ?>" required autocomplete="email">
					<button type="submit" class="bol-form-submit" aria-label="<?php esc_attr_e( 'Suscribirme', 'boletines' ); ?>">
						<span class="bol-form-submit-label"><?php echo esc_html( $form['button_text'] ?: '→' ); ?></span>
						<span class="bol-form-submit-spinner" aria-hidden="true"></span>
					</button>
				</div>
			</form>
			<div class="bol-form-feedback" role="status" aria-live="polite" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
