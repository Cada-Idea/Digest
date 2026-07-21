<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\Campaign;
use Boletines\Models\ListModel;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Campaigns {

	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		if ( $action === 'edit' || $action === 'new' ) {
			$this->render_edit();
		} else {
			$this->render_list();
		}
	}

	private function render_list(): void {
		$args = array(
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'status'   => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'per_page' => 20,
			'page'     => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);
		$result = Campaign::paginate( $args );
		$saved  = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0;
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Campañas', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-campaigns&action=new' ) ); ?>" class="bol-btn">+ <?php esc_html_e( 'Nueva campaña', 'boletines' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="boletines_run_now">
						<?php wp_nonce_field( 'boletines_run_now' ); ?>
						<button type="submit" class="bol-btn bol-btn-secondary" title="<?php esc_attr_e( 'Procesa la cola ahora sin esperar al cron', 'boletines' ); ?>">
							⚡ <?php esc_html_e( 'Procesar cola ahora', 'boletines' ); ?>
						</button>
					</form>
				</div>
			</div>

			<?php if ( $saved ) : ?>
				<div class="bol-notice success"><?php esc_html_e( 'Campaña guardada.', 'boletines' ); ?></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="bol-notice success"><?php esc_html_e( 'Campaña eliminada.', 'boletines' ); ?></div>
			<?php endif; ?>

			<form method="get" class="bol-filters">
				<input type="hidden" name="page" value="boletines-campaigns">
				<input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Buscar por asunto...', 'boletines' ); ?>">
				<select name="status">
					<option value=""><?php esc_html_e( 'Todos los estados', 'boletines' ); ?></option>
					<?php foreach ( array(
						Campaign::STATUS_DRAFT     => __( 'Borrador', 'boletines' ),
						Campaign::STATUS_SCHEDULED => __( 'Programada', 'boletines' ),
						Campaign::STATUS_SENDING   => __( 'Enviando', 'boletines' ),
						Campaign::STATUS_SENT      => __( 'Enviada', 'boletines' ),
						Campaign::STATUS_PAUSED    => __( 'Pausada', 'boletines' ),
					) as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $args['status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Filtrar', 'boletines' ); ?></button>
			</form>

			<table class="bol-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Asunto', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Progreso', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Aperturas', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Clics', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Creada', 'boletines' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:40px;"><?php esc_html_e( 'Aún no hay campañas. Crea la primera para arrancar.', 'boletines' ); ?></td></tr>
					<?php else : foreach ( $result['items'] as $c ) :
						$total  = max( 1, (int) $c['total_recipients'] );
						$pct    = min( 100, round( ( (int) $c['total_sent'] / $total ) * 100 ) );
						$open_r = $c['total_sent'] > 0 ? round( ( (int) $c['total_opens'] / (int) $c['total_sent'] ) * 100, 1 ) : 0;
						$clk_r  = $c['total_sent'] > 0 ? round( ( (int) $c['total_clicks'] / (int) $c['total_sent'] ) * 100, 1 ) : 0;
					?>
						<tr>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-campaigns&action=edit&id=' . (int) $c['id'] ) ); ?>"><strong><?php echo esc_html( $c['subject'] ); ?></strong></a>
							</td>
							<td><span class="bol-badge <?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $c['status'] ); ?></span></td>
							<td>
								<?php echo number_format_i18n( (int) $c['total_sent'] ); ?> / <?php echo number_format_i18n( (int) $c['total_recipients'] ); ?>
								<div class="bol-progress" style="margin-top:4px;"><div style="width:<?php echo esc_attr( $pct ); ?>%;"></div></div>
							</td>
							<td><?php echo (int) $c['total_opens']; ?> <span style="color:#9ca3af;">(<?php echo $open_r; ?>%)</span></td>
							<td><?php echo (int) $c['total_clicks']; ?> <span style="color:#9ca3af;">(<?php echo $clk_r; ?>%)</span></td>
							<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $c['created_at'] ) ) ); ?></td>
							<td class="bol-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-campaigns&action=edit&id=' . (int) $c['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'boletines' ); ?></a>
								<?php if ( in_array( $c['status'], array( Campaign::STATUS_SENDING, Campaign::STATUS_SENT ), true ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-reports&id=' . (int) $c['id'] ) ); ?>"><?php esc_html_e( 'Reporte', 'boletines' ); ?></a>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar esta campaña de forma permanente? Se borrarán también sus registros de envío.', 'boletines' ) ); ?>');">
									<input type="hidden" name="action" value="boletines_delete_campaign">
									<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
									<?php wp_nonce_field( 'boletines_delete_campaign_' . (int) $c['id'] ); ?>
									<button type="submit" class="button-link" style="color:#b91c1c;cursor:pointer;background:none;border:0;padding:0;font:inherit;"><?php esc_html_e( 'Eliminar', 'boletines' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_edit(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$c  = $id ? Campaign::find( $id ) : null;
		$is_new = ! $c;

		// Plantillas eliminadas en v1.5 — los shortcodes embebidos cubren el caso.
		$lists = ListModel::all();
		$selected_lists = $c ? ( $c['list_ids'] ?? array() ) : array();
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1>
					<?php echo $is_new ? esc_html__( 'Nueva campaña', 'boletines' ) : esc_html__( 'Editar campaña', 'boletines' ); ?>
					<?php if ( $c && ! empty( $c['status'] ) ) : ?>
						<span class="bol-badge <?php echo esc_attr( $c['status'] ); ?>" style="vertical-align:middle;margin-left:8px;font-size:11px;"><?php echo esc_html( $c['status'] ); ?></span>
					<?php endif; ?>
				</h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-campaigns' ) ); ?>" class="bol-btn bol-btn-secondary">← <?php esc_html_e( 'Volver', 'boletines' ); ?></a>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form" style="max-width:none;">
				<input type="hidden" name="action" value="boletines_save_campaign">
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'boletines_save_campaign' ); ?>

				<div class="bol-2col">
					<div class="main">
						<div class="bol-card">
							<div class="row">
								<label><?php esc_html_e( 'Asunto', 'boletines' ); ?> *</label>
								<input type="text" name="subject" required value="<?php echo esc_attr( $c['subject'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'p.ej. Novedades de octubre 🎉', 'boletines' ); ?>">
								<span class="help"><?php esc_html_e( 'Variables disponibles: {first_name}, {last_name}, {full_name}, {email}, {site_name}, {site_url}, {current_year}', 'boletines' ); ?></span>
							</div>
							<div class="row" style="margin-top:14px;">
								<label><?php esc_html_e( 'Preheader (texto pre-vista)', 'boletines' ); ?></label>
								<input type="text" name="preheader" value="<?php echo esc_attr( $c['preheader'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Texto corto que aparece junto al asunto en la bandeja de entrada', 'boletines' ); ?>">
							</div>
						</div>

						<div class="bol-card">
							<h2><?php esc_html_e( 'Contenido', 'boletines' ); ?></h2>
							<div class="bol-editor-templates">
								<button type="button" data-tpl="heading">+ <?php esc_html_e( 'Titular', 'boletines' ); ?></button>
								<button type="button" data-tpl="paragraph">+ <?php esc_html_e( 'Párrafo', 'boletines' ); ?></button>
								<button type="button" data-tpl="button">+ <?php esc_html_e( 'Botón CTA', 'boletines' ); ?></button>
								<button type="button" data-tpl="image">+ <?php esc_html_e( 'Imagen', 'boletines' ); ?></button>
								<button type="button" data-tpl="divider">+ <?php esc_html_e( 'Separador', 'boletines' ); ?></button>
								<button type="button" data-tpl="signature">+ <?php esc_html_e( 'Firma', 'boletines' ); ?></button>
							</div>
							<div class="bol-editor-templates" style="margin-top:6px;">
								<span style="font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;align-self:center;margin-right:4px;"><?php esc_html_e( 'Bloques dinámicos', 'boletines' ); ?>:</span>
								<button type="button" class="bol-insert-block" data-block="posts">+ <?php esc_html_e( 'Posts', 'boletines' ); ?></button>
								<button type="button" class="bol-insert-block" data-block="popular_posts">+ <?php esc_html_e( 'Posts populares', 'boletines' ); ?></button>
								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<button type="button" class="bol-insert-block" data-block="products">+ <?php esc_html_e( 'Productos', 'boletines' ); ?></button>
									<button type="button" class="bol-insert-block" data-block="top_products">+ <?php esc_html_e( 'Top productos', 'boletines' ); ?></button>
								<?php endif; ?>
							</div>
							<?php
							wp_editor(
								$c['body_html'] ?? '',
								'body_html',
								array(
									'textarea_name' => 'body_html',
									'media_buttons' => true,
									'textarea_rows' => 18,
									'editor_height' => 500,
									'tinymce'       => array(
										'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
										'toolbar2' => 'styleselect,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo',
									),
								)
							);
							?>
						</div>
					</div>

					<div class="sidebar">
						<div class="bol-card">
							<h2><?php esc_html_e( 'Destinatarios', 'boletines' ); ?></h2>
							<?php if ( empty( $lists ) ) : ?>
								<p style="color:#6b7280;"><?php esc_html_e( 'Crea una lista primero.', 'boletines' ); ?></p>
							<?php else : ?>
								<?php foreach ( $lists as $l ) : ?>
									<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:6px;">
										<input type="checkbox" name="list_ids[]" value="<?php echo (int) $l['id']; ?>" <?php checked( in_array( (int) $l['id'], $selected_lists, true ) ); ?>>
										<?php echo esc_html( $l['name'] ); ?>
										<span style="color:#9ca3af;font-size:12px;">(<?php echo number_format_i18n( ListModel::subscriber_count( (int) $l['id'] ) ); ?>)</span>
									</label>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

						<div class="bol-card">
							<h2><?php esc_html_e( 'Remitente', 'boletines' ); ?></h2>
							<div class="row">
								<label><?php esc_html_e( 'Nombre', 'boletines' ); ?></label>
								<input type="text" name="from_name" value="<?php echo esc_attr( $c['from_name'] ?? Plugin::get_setting( 'from_name' ) ); ?>">
							</div>
							<div class="row" style="margin-top:10px;">
								<label><?php esc_html_e( 'Email', 'boletines' ); ?></label>
								<input type="email" name="from_email" value="<?php echo esc_attr( $c['from_email'] ?? Plugin::get_setting( 'from_email' ) ); ?>">
							</div>
							<div class="row" style="margin-top:10px;">
								<label><?php esc_html_e( 'Reply-To', 'boletines' ); ?></label>
								<input type="email" name="reply_to" value="<?php echo esc_attr( $c['reply_to'] ?? Plugin::get_setting( 'reply_to' ) ); ?>">
							</div>
						</div>

						<div class="bol-card">
							<h2><?php esc_html_e( 'Programar', 'boletines' ); ?></h2>
							<div class="row">
								<label><?php esc_html_e( 'Enviar el', 'boletines' ); ?></label>
								<input type="datetime-local" name="scheduled_at" value="<?php echo esc_attr( ! empty( $c['scheduled_at'] ) ? gmdate( 'Y-m-d\TH:i', strtotime( $c['scheduled_at'] ) ) : '' ); ?>">
								<span class="help"><?php esc_html_e( 'Déjalo vacío para enviar inmediatamente.', 'boletines' ); ?></span>
							</div>
						</div>

						<div class="bol-card">
							<h2><?php esc_html_e( 'Acciones', 'boletines' ); ?></h2>
							<button type="submit" name="save_action" value="draft" class="bol-btn bol-btn-secondary" style="width:100%;margin-bottom:8px;"><?php esc_html_e( 'Guardar borrador', 'boletines' ); ?></button>
							<?php if ( $c && ! empty( $c['id'] ) && in_array( $c['status'] ?? 'draft', array( Campaign::STATUS_DRAFT, Campaign::STATUS_PAUSED ), true ) ) : ?>
								<button type="button" class="bol-btn bol-btn-success bol-send-campaign" data-id="<?php echo (int) $c['id']; ?>" style="width:100%;">
									✈ <?php esc_html_e( 'Enviar ahora', 'boletines' ); ?>
								</button>
								<p class="help" style="margin-top:8px;"><?php esc_html_e( 'Encola los correos. El envío real se hace por lotes según tu velocidad configurada.', 'boletines' ); ?></p>
							<?php elseif ( $c && ( $c['status'] ?? '' ) === Campaign::STATUS_SENDING ) : ?>
								<div class="bol-notice info" style="margin:0;"><?php esc_html_e( 'Esta campaña está enviándose. Procesa la cola desde la lista de campañas para acelerar.', 'boletines' ); ?></div>
							<?php endif; ?>
						</div>

						<?php if ( $c && ! empty( $c['id'] ) && in_array( $c['status'] ?? '', array( Campaign::STATUS_SENDING, Campaign::STATUS_SENT ), true ) ) : ?>
							<div class="bol-card">
								<h2><?php esc_html_e( 'Estadísticas', 'boletines' ); ?></h2>
								<p style="margin:4px 0;"><strong><?php echo number_format_i18n( (int) ( $c['total_recipients'] ?? 0 ) ); ?></strong> <?php esc_html_e( 'destinatarios', 'boletines' ); ?></p>
								<p style="margin:4px 0;"><strong><?php echo number_format_i18n( (int) ( $c['total_sent'] ?? 0 ) ); ?></strong> <?php esc_html_e( 'enviados', 'boletines' ); ?></p>
								<p style="margin:4px 0;"><strong><?php echo number_format_i18n( (int) ( $c['total_opens'] ?? 0 ) ); ?></strong> <?php esc_html_e( 'aperturas', 'boletines' ); ?></p>
								<p style="margin:4px 0;"><strong><?php echo number_format_i18n( (int) ( $c['total_clicks'] ?? 0 ) ); ?></strong> <?php esc_html_e( 'clics', 'boletines' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</form>

			<?php if ( $c && ! empty( $c['id'] ) ) :
				$sent_test = isset( $_GET['test'] ) ? sanitize_text_field( wp_unslash( $_GET['test'] ) ) : '';
			?>
			<div class="bol-card" style="margin-top:16px;max-width:520px;">
				<h2>📧 <?php esc_html_e( 'Enviar prueba de esta campaña', 'boletines' ); ?></h2>
				<p class="help" style="margin:0 0 10px;"><?php esc_html_e( 'Escribe el correo donde quieres recibir la prueba (branding, redes, tracking y enlaces incluidos). No afecta a la cola ni a las estadísticas. Si el correo es de un suscriptor, los enlaces de preferencias/baja funcionarán con sus datos.', 'boletines' ); ?></p>
				<?php if ( $sent_test === 'ok' ) : ?>
					<div class="bol-notice success" style="margin:0 0 10px;"><?php esc_html_e( 'Correo de prueba enviado.', 'boletines' ); ?></div>
				<?php elseif ( $sent_test === 'fail' ) : ?>
					<div class="bol-notice error" style="margin:0 0 10px;"><?php echo esc_html( get_transient( 'boletines_campaign_test_err_' . get_current_user_id() ) ?: __( 'No se pudo enviar.', 'boletines' ) ); delete_transient( 'boletines_campaign_test_err_' . get_current_user_id() ); ?></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;">
					<input type="hidden" name="action" value="boletines_test_campaign">
					<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
					<?php wp_nonce_field( 'boletines_test_campaign_' . (int) $c['id'] ); ?>
					<input type="email" name="test_email" required style="flex:1;" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" placeholder="tucorreo@ejemplo.com">
					<button type="submit" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Enviar prueba', 'boletines' ); ?></button>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		check_admin_referer( 'boletines_save_campaign' );

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$scheduled = isset( $_POST['scheduled_at'] ) && $_POST['scheduled_at']
			? gmdate( 'Y-m-d H:i:s', strtotime( wp_unslash( $_POST['scheduled_at'] ) ) )
			: null;

		$data = array(
			'subject'    => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'preheader'  => isset( $_POST['preheader'] ) ? sanitize_text_field( wp_unslash( $_POST['preheader'] ) ) : '',
			'body_html'  => isset( $_POST['body_html'] ) ? wp_kses_post( wp_unslash( $_POST['body_html'] ) ) : '',
			'from_name'  => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
			'from_email' => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
			'reply_to'   => isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '',
			'list_ids'   => isset( $_POST['list_ids'] ) ? array_map( 'intval', (array) $_POST['list_ids'] ) : array(),
		);

		if ( $scheduled ) {
			$data['scheduled_at'] = $scheduled;
			$data['status']       = Campaign::STATUS_SCHEDULED;
		}

		if ( $id ) {
			Campaign::update( $id, $data );
			$cid = $id;
		} else {
			$cid = Campaign::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-campaigns&action=edit&id=' . $cid . '&saved=1' ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		check_admin_referer( 'boletines_delete_campaign_' . $id );
		if ( $id ) {
			Campaign::delete( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=boletines-campaigns&deleted=1' ) );
		exit;
	}

	public function handle_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		check_admin_referer( 'boletines_test_campaign_' . $id );

		global $wpdb;
		$email    = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		$campaign = $id ? Campaign::find( $id ) : null;

		$result = 'fail';
		if ( $campaign && is_email( $email ) ) {
			// Si el correo pertenece a un suscriptor, usamos sus datos (enlaces reales);
			// si no, construimos uno sintético para la prueba.
			$sub = $wpdb->get_row( $wpdb->prepare(
				'SELECT id, email, first_name, last_name, status, token FROM ' . Plugin::table( 'subscribers' ) . ' WHERE email = %s',
				$email
			), ARRAY_A );
			if ( ! $sub ) {
				$sub = array( 'id' => 0, 'email' => $email, 'first_name' => '', 'last_name' => '', 'token' => '' );
			}
			$out = ( new \Boletines\Mailer\Mailer() )->send_campaign_test( $campaign, $sub );
			if ( $out === true ) {
				$result = 'ok';
			} else {
				set_transient( 'boletines_campaign_test_err_' . get_current_user_id(), (string) $out, 60 );
			}
		} else {
			set_transient( 'boletines_campaign_test_err_' . get_current_user_id(), __( 'Correo o campaña no válidos.', 'boletines' ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-campaigns&action=edit&id=' . $id . '&test=' . $result ) );
		exit;
	}
}
