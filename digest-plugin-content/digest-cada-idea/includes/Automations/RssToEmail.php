<?php
namespace Boletines\Automations;

use Boletines\Models\Automation;
use Boletines\Models\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convierte posts (y productos WooCommerce) publicados en campañas automáticas.
 *
 * Tipos:
 * - post_published      → al publicar un post, dispara una campaña con el contenido del post.
 * - product_published   → al publicar un producto WooCommerce, dispara una campaña.
 * - digest_daily        → cron diario que junta posts/productos del periodo.
 * - digest_weekly       → cada 7 días.
 *
 * Layout de cada item:
 *   <table 2 cols>
 *     col1: imagen
 *     col2: título (link), línea meta (autor | categoría | fecha | tiempo lectura),
 *           excerpt, botón ("Leer más" o "Ver producto")
 *   </table>
 *
 * Responsive: en móvil colapsa a 1 columna usando media-query (Outlook ignora pero queda OK).
 */
class RssToEmail {

	public function register(): void {
		// Bug fix v1.6: usamos `wp_after_insert_post` en lugar de `transition_post_status`.
		// El segundo se dispara ANTES de que Gutenberg guarde imagen destacada y términos
		// (los manda en peticiones REST separadas), por lo que el correo salía sin imagen
		// y con la categoría "por defecto". `wp_after_insert_post` (WP 5.6+) se dispara
		// DESPUÉS de toda la metadata, garantizando datos completos.
		add_action( 'wp_after_insert_post', array( $this, 'on_after_insert' ), 20, 4 );
		add_action( 'boletines_cron_daily', array( $this, 'run_digests' ) );
		add_action( 'boletines_cron_daily', array( $this, 'send_birthday_emails' ) );
		add_action( 'admin_post_boletines_run_digest', array( $this, 'admin_run_digest' ) );
	}

	/**
	 * Hook moderno post-save. $update=true si era edición, false si nuevo.
	 * $post_before tiene el estado anterior (o null si nuevo).
	 */
	public function on_after_insert( $post_id, $post, $update, $post_before ): void {
		if ( ! $post || ! ( $post instanceof \WP_Post ) ) return;
		if ( $post->post_status !== 'publish' ) return;
		// Sólo transiciones a publish (no actualizaciones de un post ya publicado).
		if ( $post_before && $post_before->post_status === 'publish' ) return;
		if ( ! in_array( $post->post_type, array( 'post', 'product' ), true ) ) return;

		// 🔒 Las automatizaciones son una feature Pro: requiere licencia Digest activa.
		if ( ! \Boletines\Licensing\Manager::is_active() ) {
			return;
		}

		$automations = Automation::all( true );
		foreach ( $automations as $a ) {
			$expected_post_type = ( $a['type'] === Automation::TYPE_PRODUCT_PUBLISHED ) ? 'product' : 'post';
			if ( $a['type'] !== Automation::TYPE_POST_PUBLISHED && $a['type'] !== Automation::TYPE_PRODUCT_PUBLISHED ) continue;
			if ( $post->post_type !== $expected_post_type ) continue;

			$opts         = $a['options'];
			$category_ids = ! empty( $opts['category_ids'] ) ? array_map( 'intval', (array) $opts['category_ids'] ) : array();

			if ( ! empty( $category_ids ) ) {
				$taxonomy  = ( $post->post_type === 'product' ) ? 'product_cat' : 'category';
				$post_cats = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
				if ( is_wp_error( $post_cats ) || empty( array_intersect( $category_ids, (array) $post_cats ) ) ) continue;
			}

			if ( Automation::already_ran( (int) $a['id'], (int) $post->ID, $post->post_type ) ) continue;

			$cid = $this->create_campaign_from_object( $a, $post );
			if ( $cid ) {
				Automation::mark_run( (int) $a['id'], (int) $post->ID, $post->post_type, $cid );
				Automation::update_last_run( (int) $a['id'] );
			}
		}
	}

	public function run_digests(): void {
		// 🔒 Pro feature.
		if ( ! \Boletines\Licensing\Manager::is_active() ) return;

		$automations = Automation::all( true );
		$now = time();

		foreach ( $automations as $a ) {
			$is_digest = in_array( $a['type'], array( Automation::TYPE_DIGEST_DAILY, Automation::TYPE_DIGEST_WEEKLY ), true );
			if ( ! $is_digest ) continue;

			$last_run = $a['last_run_at'] ? strtotime( $a['last_run_at'] ) : 0;
			$interval = $a['type'] === Automation::TYPE_DIGEST_WEEKLY ? 7 * DAY_IN_SECONDS : DAY_IN_SECONDS;
			if ( $last_run && ( $now - $last_run ) < $interval ) continue;

			$since     = $last_run ?: ( $now - $interval );
			$post_type = ! empty( $a['options']['digest_post_type'] ) ? $a['options']['digest_post_type'] : 'post';

			$args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'date_query'     => array( array( 'after' => gmdate( 'Y-m-d H:i:s', $since ) ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			);

			if ( ! empty( $a['options']['category_ids'] ) ) {
				$tax = $post_type === 'product' ? 'product_cat' : 'category';
				$args['tax_query'] = array( array(
					'taxonomy' => $tax,
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', $a['options']['category_ids'] ),
				) );
			}

			$objects = get_posts( $args );

			if ( empty( $objects ) ) {
				Automation::update_last_run( (int) $a['id'] );
				continue;
			}

			$cid = $this->create_digest_campaign( $a, $objects );
			if ( $cid ) {
				foreach ( $objects as $p ) {
					Automation::mark_run( (int) $a['id'], (int) $p->ID, $p->post_type, $cid );
				}
				Automation::update_last_run( (int) $a['id'] );
			}
		}
	}

	public function admin_run_digest(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		check_admin_referer( 'boletines_run_digest' );
		$this->run_digests();
		$this->send_birthday_emails();
		wp_safe_redirect( admin_url( 'admin.php?page=boletines-automations&digest_run=1' ) );
		exit;
	}

	/**
	 * Cron diario: envía correos de cumpleaños a quien cumple hoy.
	 * Usa el meta 'birthday' (YYYY-MM-DD). Sólo a confirmados.
	 * Se ejecuta una vez por día por suscriptor (registro en automation_runs).
	 */
	public function send_birthday_emails(): void {
		// 🔒 Pro feature.
		if ( ! \Boletines\Licensing\Manager::is_active() ) return;

		global $wpdb;
		$automations = Automation::all( true );
		$birthday_autos = array_filter( $automations, function ( $a ) {
			return $a['type'] === Automation::TYPE_BIRTHDAY;
		} );
		if ( empty( $birthday_autos ) ) return;

		$today_m = (int) wp_date( 'm' );
		$today_d = (int) wp_date( 'd' );

		foreach ( $birthday_autos as $a ) {
			$opts     = $a['options'];
			$list_ids = $a['list_ids'];

			// Construir filtro de listas si hay.
			$join  = '';
			$where = '';
			$params = array( $today_m, $today_d );
			if ( ! empty( $list_ids ) ) {
				$ph    = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
				$join  = 'INNER JOIN ' . \Boletines\Plugin::table( 'subscriber_list' ) . ' sl ON sl.subscriber_id = s.id';
				$where = " AND sl.list_id IN ($ph)";
				$params = array_merge( $params, $list_ids );
			}

			$sql = "SELECT DISTINCT s.* FROM " . \Boletines\Plugin::table( 'subscribers' ) . " s
				INNER JOIN " . \Boletines\Plugin::table( 'subscriber_meta' ) . " m
					ON m.subscriber_id = s.id AND m.meta_key = 'birthday'
				$join
				WHERE s.status = 'confirmed'
				AND MONTH(m.meta_value) = %d AND DAY(m.meta_value) = %d
				$where";

			$subs = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

			if ( empty( $subs ) ) {
				Automation::update_last_run( (int) $a['id'] );
				continue;
			}

			$subject_tpl = $opts['subject']   ?? __( '¡Feliz cumpleaños, {first_name}! 🎂', 'boletines' );
			$body_tpl    = $opts['body_html'] ?? __( '<p>Hola {first_name},</p><p>Desde {site_name} queremos desearte un feliz cumpleaños. ¡Que tengas un día increíble!</p>', 'boletines' );

			$mailer = new \Boletines\Mailer\Mailer();
			$today_tag = 'bday_' . wp_date( 'Ymd' ); // ≤ 15 chars, cabe en VARCHAR(20)
			foreach ( $subs as $sub ) {
				// Evitar duplicados: object_id = subscriber_id, object_type = bday_YYYYMMDD.
				if ( Automation::already_ran( (int) $a['id'], (int) $sub['id'], $today_tag ) ) continue;

				$subject = strtr( $subject_tpl, array(
					'{first_name}' => $sub['first_name'] ?: '',
					'{site_name}'  => get_bloginfo( 'name' ),
				) );
				$body = strtr( $body_tpl, array(
					'{first_name}' => $sub['first_name'] ?: '',
					'{site_name}'  => get_bloginfo( 'name' ),
				) );

				$ok = $mailer->send_simple( $sub['email'], $subject, $body, array(), $sub );
				if ( $ok ) {
					Automation::mark_run( (int) $a['id'], (int) $sub['id'], $today_tag );
				}
			}
			Automation::update_last_run( (int) $a['id'] );
		}
	}

	private function create_campaign_from_object( array $automation, \WP_Post $post ): int {
		$opts        = $automation['options'];
		$is_product  = $post->post_type === 'product';
		$default_subj = $is_product ? '🛒 Nuevo en la tienda: {post_title}' : '🆕 {post_title}';
		$subject_tpl  = $opts['subject'] ?? $default_subj;

		$auto_excerpt = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 25 );
		$post_vars = array(
			'{post_title}'   => $post->post_title,
			'{post_excerpt}' => $auto_excerpt,
			'{site_name}'    => get_bloginfo( 'name' ),
		);
		$subject = strtr( $subject_tpl, $post_vars );

		$preview_tpl = trim( (string) ( $opts['preview'] ?? '' ) );
		$preview = $preview_tpl !== '' ? strtr( $preview_tpl, $post_vars ) : $auto_excerpt;

		$body_html = '<h2 style="margin:0 0 18px;font-size:22px;color:#111827;">'
			. esc_html( $is_product ? __( 'Nuevo producto', 'boletines' ) : __( 'Nuevo artículo', 'boletines' ) )
			. '</h2>'
			. self::render_object_block( $post );

		$cid = Campaign::create( array(
			'subject'   => $subject,
			'preheader' => $preview,
			'body_html' => $body_html,
			'list_ids'  => $automation['list_ids'],
		) );

		Campaign::build_queue( $cid );
		Campaign::update( $cid, array( 'status' => Campaign::STATUS_SENDING ) );
		wp_schedule_single_event( time() + 5, 'boletines_cron_send' );
		return $cid;
	}

	private function create_digest_campaign( array $automation, array $objects ): int {
		$opts    = $automation['options'];
		$is_product_digest = ! empty( $objects ) && $objects[0]->post_type === 'product';
		$default = $is_product_digest ? '🛒 Lo nuevo en {site_name}' : '📰 Lo nuevo de {site_name}';

		$first = $objects[0] ?? null;
		$first_title   = $first ? $first->post_title : '';
		$first_excerpt = $first ? wp_trim_words( wp_strip_all_tags( $first->post_excerpt ?: $first->post_content ), 25 ) : '';

		$post_vars = array(
			'{post_title}'   => $first_title,
			'{post_excerpt}' => $first_excerpt,
			'{site_name}'    => get_bloginfo( 'name' ),
			'{count}'        => (string) count( $objects ),
		);
		$subject = strtr( $opts['subject'] ?? $default, $post_vars );

		$preview_tpl = trim( (string) ( $opts['preview'] ?? '' ) );
		$preview = $preview_tpl !== '' ? strtr( $preview_tpl, $post_vars ) : $first_excerpt;

		$blocks = '';
		foreach ( $objects as $p ) {
			$blocks .= self::render_object_block( $p );
		}

		$body_html = '<h2 style="margin:0 0 18px;font-size:22px;color:#111827;">' . esc_html( $subject ) . '</h2>' . $blocks;

		$cid = Campaign::create( array(
			'subject'   => $subject,
			'preheader' => $preview,
			'body_html' => $body_html,
			'list_ids'  => $automation['list_ids'],
		) );

		Campaign::build_queue( $cid );
		Campaign::update( $cid, array( 'status' => Campaign::STATUS_SENDING ) );
		wp_schedule_single_event( time() + 5, 'boletines_cron_send' );
		return $cid;
	}

	/**
	 * Renderiza un post o producto en bloque 2 columnas (imagen + meta+texto+botón).
	 * Compatible con clientes de correo: usa <table>, no flex/grid. Responsive con MSO + media query.
	 */
	public static function render_object_block( \WP_Post $post ): string {
		$is_product = $post->post_type === 'product';
		$thumb      = get_the_post_thumbnail_url( $post, 'medium_large' );
		$url        = get_permalink( $post );
		$title      = $post->post_title;
		$excerpt    = $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );

		// Meta: autor | categoría | fecha actualización | tiempo lectura
		$author = get_the_author_meta( 'display_name', $post->post_author );
		$tax    = $is_product ? 'product_cat' : 'category';
		$cats   = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'names' ) );
		$cat    = ! is_wp_error( $cats ) && ! empty( $cats ) ? $cats[0] : '';
		$date   = wp_date( 'd M Y', strtotime( $post->post_modified ) );

		// Tiempo de lectura: 200 palabras/min para posts.
		if ( $is_product ) {
			$reading = '';
		} else {
			$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
			$mins       = max( 1, (int) round( $word_count / 200 ) );
			$reading    = sprintf( _n( '%d min de lectura', '%d min de lectura', $mins, 'boletines' ), $mins );
		}

		$meta_parts = array_filter( array(
			$author ? esc_html( $author ) : '',
			$cat    ? esc_html( $cat ) : '',
			esc_html( $date ),
			$reading ? esc_html( $reading ) : '',
		) );
		$meta_line = implode( ' &nbsp;|&nbsp; ', $meta_parts );

		$btn_label = $is_product ? __( 'Ver producto', 'boletines' ) : __( 'Leer más', 'boletines' );

		// Para productos, el botón puede ir a la tienda; pero el url específico es la propia página del producto.
		// Si Diego prefiere "ir a la tienda" como botón secundario, podríamos añadirlo. Por ahora url del producto.
		$btn_url = $url;

		// Construcción HTML — tabla email-safe.
		$thumb_html = $thumb
			? '<a href="' . esc_url( $url ) . '" style="display:block;"><img src="' . esc_url( $thumb ) . '" width="220" alt="" style="display:block;width:100%;max-width:220px;height:auto;border-radius:6px;border:0;outline:none;text-decoration:none;" /></a>'
			: '<div style="width:100%;max-width:220px;height:120px;background:#f3f4f6;border-radius:6px;"></div>';

		ob_start();
		?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 28px;">
	<tr>
		<td class="bol-stack" valign="top" width="240" style="padding:0 16px 0 0;width:240px;vertical-align:top;">
			<?php echo $thumb_html; ?>
		</td>
		<td class="bol-stack" valign="top" style="vertical-align:top;">
			<h3 style="margin:0 0 6px;font-size:18px;line-height:1.3;font-weight:600;">
				<a href="<?php echo esc_url( $url ); ?>" style="color:#111827;text-decoration:none;"><?php echo esc_html( $title ); ?></a>
			</h3>
			<?php if ( $meta_line !== '' ) : ?>
				<p style="margin:0 0 10px;font-size:12px;color:#6b7280;line-height:1.4;"><?php echo $meta_line; // ya escapado ?></p>
			<?php endif; ?>
			<?php if ( $excerpt ) : ?>
				<p style="margin:0 0 14px;font-size:14px;color:#374151;line-height:1.55;"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>
			<a href="<?php echo esc_url( $btn_url ); ?>" style="display:inline-block;padding:9px 18px;background:{{accent}};color:{{accent_fg}};text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
				<?php echo esc_html( $btn_label ); ?> →
			</a>
		</td>
	</tr>
</table>
		<?php
		return ob_get_clean();
	}
}
