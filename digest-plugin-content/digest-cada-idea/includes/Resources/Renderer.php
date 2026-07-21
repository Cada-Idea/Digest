<?php
namespace Boletines\Resources;

use Boletines\Models\Resource;

defined( 'ABSPATH' ) || exit;

/**
 * Renderer de Recursos (shortcodes + página automática /recursos).
 */
class Renderer {

	public function register(): void {
		add_shortcode( 'boletines_resource',  array( $this, 'shortcode_single' ) );
		add_shortcode( 'boletines_resources', array( $this, 'shortcode_list' ) );

		// Página automática /recursos
		add_action( 'init',           array( $this, 'register_rewrite' ) );
		add_action( 'template_redirect', array( $this, 'handle_resources_page' ) );
		add_filter( 'query_vars',     array( $this, 'register_query_var' ) );
	}

	public function register_query_var( $vars ) {
		$vars[] = 'boletines_resources_page';
		return $vars;
	}

	public function register_rewrite(): void {
		add_rewrite_rule( '^recursos/?$', 'index.php?boletines_resources_page=1', 'top' );
	}

	/**
	 * Si se solicita /recursos, renderizamos un template propio que se inyecta
	 * dentro del tema (header/footer del tema, contenido nuestro).
	 */
	public function handle_resources_page(): void {
		if ( ! get_query_var( 'boletines_resources_page' ) ) return;

		// Renderizamos como una "página virtual" — interceptamos con the_content.
		add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 99 );

		// WordPress espera un post real. Construimos uno virtual.
		global $wp_query, $post;
		$post = (object) array(
			'ID'             => 0,
			'post_author'    => 0,
			'post_date'      => current_time( 'mysql' ),
			'post_content'   => '',
			'post_title'     => __( 'Recursos', 'boletines' ),
			'post_excerpt'   => '',
			'post_status'    => 'publish',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_name'      => 'recursos',
			'post_type'      => 'page',
			'filter'         => 'raw',
		);
		$post = new \WP_Post( $post );
		$wp_query->post        = $post;
		$wp_query->posts       = array( $post );
		$wp_query->post_count  = 1;
		$wp_query->found_posts = 1;
		$wp_query->is_page     = true;
		$wp_query->is_singular = true;
		$wp_query->is_home     = false;
		$wp_query->is_404      = false;
	}

	public function filter_title( $title, $id = null ) {
		if ( get_query_var( 'boletines_resources_page' ) && $id === 0 ) {
			return __( 'Recursos', 'boletines' );
		}
		return $title;
	}

	public function filter_content( $content ) {
		if ( ! get_query_var( 'boletines_resources_page' ) ) return $content;
		return $this->shortcode_list( array( 'per_page' => 30 ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Shortcodes
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * [boletines_resource id="5"]  — un recurso individual con su formulario.
	 */
	public function shortcode_single( $atts ): string {
		$atts = shortcode_atts( array(
			'id'   => 0,
			'slug' => '',
		), $atts, 'boletines_resource' );

		$resource = null;
		if ( ! empty( $atts['id'] ) ) {
			$resource = Resource::get( (int) $atts['id'] );
		} elseif ( $atts['slug'] !== '' ) {
			$resource = Resource::get_by_slug( (string) $atts['slug'] );
		}

		if ( ! $resource || $resource['status'] !== Resource::STATUS_ACTIVE ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="bol-resource-error">[boletines_resource] ' . esc_html__( 'recurso no encontrado o inactivo.', 'boletines' ) . '</div>';
			}
			return '';
		}

		return $this->render_card( $resource );
	}

	/**
	 * [boletines_resources per_page="12"]  — listado con grid.
	 */
	public function shortcode_list( $atts ): string {
		$atts = shortcode_atts( array(
			'per_page' => 12,
		), $atts, 'boletines_resources' );

		$resources = Resource::all( array(
			'status'  => Resource::STATUS_ACTIVE,
			'limit'   => max( 1, (int) $atts['per_page'] ),
			'orderby' => 'created_at',
			'order'   => 'DESC',
		) );

		if ( empty( $resources ) ) {
			return '<div class="bol-resources-empty"><p>' . esc_html__( 'Próximamente recursos disponibles.', 'boletines' ) . '</p></div>';
		}

		$this->enqueue_assets();
		ob_start();
		?>
		<div class="bol-resources-grid">
			<?php foreach ( $resources as $r ) : ?>
				<?php echo $this->render_card( $r, true ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Card individual (shareada entre single y list)
	// ─────────────────────────────────────────────────────────────────────────

	private function render_card( array $resource, bool $compact = false ): string {
		$this->enqueue_assets();

		$cover_url = '';
		if ( ! empty( $resource['cover_image_id'] ) ) {
			$cover_url = wp_get_attachment_image_url( (int) $resource['cover_image_id'], $compact ? 'medium' : 'large' );
		}
		$button_text     = ! empty( $resource['button_text'] ) ? $resource['button_text'] : __( 'Descargar', 'boletines' );
		$success_message = ! empty( $resource['success_message'] ) ? $resource['success_message'] : __( '¡Listo! Te hemos enviado el enlace de descarga al correo.', 'boletines' );

		$uid = 'bol-res-' . (int) $resource['id'] . '-' . wp_generate_password( 4, false, false );
		ob_start();
		?>
		<div class="bol-resource-card<?php echo $compact ? ' bol-resource-card--compact' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>">
			<?php if ( $cover_url ) : ?>
				<div class="bol-resource-cover">
					<img src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $resource['title'] ); ?>" loading="lazy">
				</div>
			<?php endif; ?>
			<div class="bol-resource-body">
				<h3 class="bol-resource-title"><?php echo esc_html( $resource['title'] ); ?></h3>
				<?php if ( ! empty( $resource['description'] ) ) : ?>
					<div class="bol-resource-description"><?php echo wp_kses_post( wpautop( $resource['description'] ) ); ?></div>
				<?php endif; ?>

				<form class="bol-resource-form" data-resource-id="<?php echo (int) $resource['id']; ?>" data-success-message="<?php echo esc_attr( $success_message ); ?>">
					<?php wp_nonce_field( 'boletines_resource_request', 'bol_res_nonce' ); ?>
					<div class="bol-resource-fields">
						<input type="email" name="email" required placeholder="<?php esc_attr_e( 'Tu correo electrónico', 'boletines' ); ?>" autocomplete="email">
						<input type="text" name="first_name" placeholder="<?php esc_attr_e( 'Tu nombre (opcional)', 'boletines' ); ?>" autocomplete="given-name">
					</div>
					<button type="submit" class="bol-resource-btn"><?php echo esc_html( $button_text ); ?></button>
					<div class="bol-resource-feedback" role="status" aria-live="polite"></div>
				</form>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function enqueue_assets(): void {
		static $enqueued = false;
		if ( $enqueued ) return;
		$enqueued = true;

		wp_enqueue_style(
			'boletines-resources',
			BOLETINES_URL . 'assets/css/resources.css',
			array(),
			BOLETINES_VERSION
		);
		wp_enqueue_script(
			'boletines-resources',
			BOLETINES_URL . 'assets/js/resources.js',
			array(),
			BOLETINES_VERSION,
			true
		);
		wp_localize_script( 'boletines-resources', 'BoletinesResources', array(
			'ajaxUrl' => rest_url( 'boletines/v1/resources/request' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'strings' => array(
				'sending' => __( 'Enviando…', 'boletines' ),
				'error'   => __( 'Algo salió mal. Inténtalo de nuevo.', 'boletines' ),
			),
		) );
	}
}
