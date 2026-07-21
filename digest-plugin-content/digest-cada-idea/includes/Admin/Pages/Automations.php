<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\Automation;
use Boletines\Models\ListModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Automations {

	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		if ( $action === 'edit' || $action === 'new' ) {
			$this->render_edit();
		} else {
			$this->render_list();
		}
	}

	private function render_list(): void {
		$automations = Automation::all();
		$saved       = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0;
		$digest_run  = isset( $_GET['digest_run'] ) ? (int) $_GET['digest_run'] : 0;
		?>
		<div class="wrap boletines-wrap">
			<?php if ( ! \Boletines\Licensing\Manager::is_active() ) : ?>
				<div class="bol-notice" style="background:#fef3c7;border-left:4px solid #f59e0b;color:#92400e;padding:14px 18px;margin:0 0 18px;border-radius:6px;display:flex;align-items:center;gap:14px;">
					<span style="font-size:22px;">🔒</span>
					<div style="flex:1;">
						<strong><?php esc_html_e( 'Las automatizaciones son una función Pro.', 'boletines' ); ?></strong><br>
						<span style="font-size:13px;color:#78350f;">
							<?php esc_html_e( 'Puedes crear y guardar automatizaciones, pero no se ejecutarán mientras no actives tu licencia Digest. Una vez activada, los disparos por publicación de post/producto, los digests y los correos de cumpleaños funcionan automáticamente.', 'boletines' ); ?>
						</span>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-license' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Activar Pro', 'boletines' ); ?></a>
				</div>
			<?php endif; ?>
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Automatizaciones', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-automations&action=new' ) ); ?>" class="bol-btn">+ <?php esc_html_e( 'Nueva automatización', 'boletines' ); ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="boletines_run_digest">
						<?php wp_nonce_field( 'boletines_run_digest' ); ?>
						<button type="submit" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Ejecutar digests ahora', 'boletines' ); ?></button>
					</form>
				</div>
			</div>

			<?php if ( $saved ) : ?><div class="bol-notice success"><?php esc_html_e( 'Automatización guardada.', 'boletines' ); ?></div><?php endif; ?>
			<?php if ( $digest_run ) : ?><div class="bol-notice info"><?php esc_html_e( 'Digests ejecutados (revisa la pantalla de campañas).', 'boletines' ); ?></div><?php endif; ?>

			<p style="color:#6b7280;margin-bottom:16px;">
				<?php esc_html_e( 'Las automatizaciones generan campañas a partir de tus posts publicados, sin que tengas que redactar nada. Tipos disponibles:', 'boletines' ); ?>
				<strong><?php esc_html_e( 'Al publicar', 'boletines' ); ?></strong> <?php esc_html_e( '(envía cada vez que publicas un post),', 'boletines' ); ?>
				<strong><?php esc_html_e( 'Digest diario / semanal', 'boletines' ); ?></strong> <?php esc_html_e( '(junta los posts del periodo y envía resumen).', 'boletines' ); ?>
			</p>

			<table class="bol-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nombre', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Tipo', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Última ejecución', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'boletines' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $automations ) ) : ?>
						<tr><td colspan="5" style="text-align:center;color:#6b7280;padding:40px;"><?php esc_html_e( 'Ninguna automatización configurada.', 'boletines' ); ?></td></tr>
					<?php else : foreach ( $automations as $a ) :
						$type_labels = array(
							Automation::TYPE_POST_PUBLISHED => __( 'Al publicar post', 'boletines' ),
							Automation::TYPE_DIGEST_DAILY   => __( 'Digest diario', 'boletines' ),
							Automation::TYPE_DIGEST_WEEKLY  => __( 'Digest semanal', 'boletines' ),
						);
					?>
						<tr>
							<td><strong><?php echo esc_html( $a['name'] ); ?></strong></td>
							<td><?php echo esc_html( $type_labels[ $a['type'] ] ?? $a['type'] ); ?></td>
							<td><span class="bol-badge <?php echo $a['status'] === 'active' ? 'sent' : 'paused'; ?>"><?php echo esc_html( $a['status'] ); ?></span></td>
							<td><?php echo $a['last_run_at'] ? esc_html( wp_date( 'd/m/Y H:i', strtotime( $a['last_run_at'] ) ) ) : '—'; ?></td>
							<td class="bol-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-automations&action=edit&id=' . (int) $a['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'boletines' ); ?></a>
								<a href="#" class="delete bol-delete-automation" data-id="<?php echo (int) $a['id']; ?>"><?php esc_html_e( 'Borrar', 'boletines' ); ?></a>
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
		$a  = $id ? Automation::find( $id ) : null;
		$is_new = ! $a;
		$lists      = ListModel::all();
		$opts       = $a ? $a['options'] : array();
		$selected_lists = $a ? $a['list_ids'] : array();
		$selected_cats  = ! empty( $opts['category_ids'] ) ? array_map( 'intval', (array) $opts['category_ids'] ) : array();
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php echo $is_new ? esc_html__( 'Nueva automatización', 'boletines' ) : esc_html__( 'Editar automatización', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-automations' ) ); ?>" class="bol-btn bol-btn-secondary">← <?php esc_html_e( 'Volver', 'boletines' ); ?></a>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
				<input type="hidden" name="action" value="boletines_save_automation">
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'boletines_save_automation' ); ?>

				<div class="bol-card">
					<div class="row row-2">
						<div class="row">
							<label><?php esc_html_e( 'Nombre interno', 'boletines' ); ?> *</label>
							<input type="text" name="name" required value="<?php echo esc_attr( $a['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Ej: Notificación nuevo post a suscriptores VIP', 'boletines' ); ?>">
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Estado', 'boletines' ); ?></label>
							<select name="status">
								<option value="active" <?php selected( $a['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Activa', 'boletines' ); ?></option>
								<option value="paused" <?php selected( $a['status'] ?? '', 'paused' ); ?>><?php esc_html_e( 'Pausada', 'boletines' ); ?></option>
							</select>
						</div>
					</div>
					<div class="row" style="margin-top:14px;">
						<label><?php esc_html_e( 'Tipo', 'boletines' ); ?></label>
						<select name="type" id="bol-auto-type">
							<optgroup label="<?php esc_attr_e( 'Disparo instantáneo', 'boletines' ); ?>">
								<option value="<?php echo esc_attr( Automation::TYPE_POST_PUBLISHED ); ?>"    <?php selected( $a['type'] ?? '', Automation::TYPE_POST_PUBLISHED ); ?>><?php esc_html_e( 'Al publicar un post', 'boletines' ); ?></option>
								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<option value="<?php echo esc_attr( Automation::TYPE_PRODUCT_PUBLISHED ); ?>" <?php selected( $a['type'] ?? '', Automation::TYPE_PRODUCT_PUBLISHED ); ?>><?php esc_html_e( 'Al publicar un producto WooCommerce', 'boletines' ); ?></option>
								<?php endif; ?>
							</optgroup>
							<optgroup label="<?php esc_attr_e( 'Resumen periódico', 'boletines' ); ?>">
								<option value="<?php echo esc_attr( Automation::TYPE_DIGEST_DAILY ); ?>"  <?php selected( $a['type'] ?? '', Automation::TYPE_DIGEST_DAILY ); ?>><?php esc_html_e( 'Digest diario (24h)', 'boletines' ); ?></option>
								<option value="<?php echo esc_attr( Automation::TYPE_DIGEST_WEEKLY ); ?>" <?php selected( $a['type'] ?? '', Automation::TYPE_DIGEST_WEEKLY ); ?>><?php esc_html_e( 'Digest semanal (7 días)', 'boletines' ); ?></option>
							</optgroup>
							<optgroup label="<?php esc_attr_e( 'Personalizado', 'boletines' ); ?>">
								<option value="<?php echo esc_attr( Automation::TYPE_BIRTHDAY ); ?>" <?php selected( $a['type'] ?? '', Automation::TYPE_BIRTHDAY ); ?>><?php esc_html_e( '🎂 Email de cumpleaños', 'boletines' ); ?></option>
							</optgroup>
						</select>
					</div>
				</div>

				<div class="bol-card">
					<h2><?php esc_html_e( 'Filtros de contenido', 'boletines' ); ?></h2>

					<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<div class="row" id="bol-digest-pt-row">
						<label><?php esc_html_e( '¿Qué incluir en el digest?', 'boletines' ); ?></label>
						<select name="digest_post_type">
							<option value="post" <?php selected( $opts['digest_post_type'] ?? 'post', 'post' ); ?>><?php esc_html_e( 'Posts', 'boletines' ); ?></option>
							<option value="product" <?php selected( $opts['digest_post_type'] ?? '', 'product' ); ?>><?php esc_html_e( 'Productos WooCommerce', 'boletines' ); ?></option>
						</select>
					</div>
					<?php endif; ?>

					<div class="row" style="margin-top:14px;">
						<label><?php esc_html_e( 'Categorías (opcional, sólo de estas categorías)', 'boletines' ); ?></label>
						<div style="display:flex;flex-wrap:wrap;gap:10px;padding:6px 0;" id="bol-cats-post">
							<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $cat ) : ?>
								<label style="display:flex;align-items:center;gap:6px;font-weight:400;">
									<input type="checkbox" name="category_ids[]" value="<?php echo (int) $cat->term_id; ?>" <?php checked( in_array( (int) $cat->term_id, $selected_cats, true ) ); ?>>
									<?php echo esc_html( $cat->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
						<div style="display:none;flex-wrap:wrap;gap:10px;padding:6px 0;" id="bol-cats-product">
							<?php
							$prod_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
							foreach ( ( is_wp_error( $prod_cats ) ? array() : $prod_cats ) as $cat ) : ?>
								<label style="display:flex;align-items:center;gap:6px;font-weight:400;">
									<input type="checkbox" name="category_ids_product[]" value="<?php echo (int) $cat->term_id; ?>" <?php checked( in_array( (int) $cat->term_id, $selected_cats, true ) ); ?>>
									<?php echo esc_html( $cat->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
						<span class="help"><?php esc_html_e( 'Si no seleccionas ninguna, se incluyen todas las categorías.', 'boletines' ); ?></span>
					</div>
				</div>

				<div class="bol-card">
					<h2><?php esc_html_e( 'Destinatarios', 'boletines' ); ?></h2>
					<?php if ( ! empty( $lists ) ) : ?>
						<?php foreach ( $lists as $l ) : ?>
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:6px;">
								<input type="checkbox" name="list_ids[]" value="<?php echo (int) $l['id']; ?>" <?php checked( in_array( (int) $l['id'], $selected_lists, true ) ); ?>>
								<?php echo esc_html( $l['name'] ); ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<div class="bol-card" id="bol-birthday-card" style="display:none;">
					<h2>🎂 <?php esc_html_e( 'Mensaje de cumpleaños', 'boletines' ); ?></h2>
					<p style="color:#6b7280;font-size:13px;line-height:1.5;margin:0 0 12px;">
						<?php esc_html_e( 'Este correo se envía automáticamente a cada suscriptor el día de su cumpleaños (variable de meta "birthday"). El cron diario lo procesa una vez por persona por día.', 'boletines' ); ?>
					</p>
					<div class="row">
						<label><?php esc_html_e( 'Asunto', 'boletines' ); ?></label>
						<input type="text" name="birthday_subject" value="<?php echo esc_attr( $opts['subject'] ?? __( '¡Feliz cumpleaños, {first_name}! 🎂', 'boletines' ) ); ?>">
						<span class="help"><?php esc_html_e( 'Variables: {first_name}, {site_name}', 'boletines' ); ?></span>
					</div>
					<div class="row">
						<label><?php esc_html_e( 'Cuerpo del correo (HTML)', 'boletines' ); ?></label>
						<textarea name="birthday_body" rows="8"><?php echo esc_textarea( $opts['body_html'] ?? '<p>Hola {first_name},</p><p>Desde {site_name} queremos desearte un feliz cumpleaños. ¡Que tengas un día increíble!</p>' ); ?></textarea>
						<span class="help"><?php esc_html_e( 'Puedes incluir HTML básico. El branding global (logo, footer redes) se aplica automáticamente.', 'boletines' ); ?></span>
					</div>
				</div>

				<div class="bol-card">
					<h2><?php esc_html_e( 'Personalización', 'boletines' ); ?></h2>
					<div class="row">
						<label><?php esc_html_e( 'Asunto del correo', 'boletines' ); ?></label>
						<input type="text" name="subject" value="<?php echo esc_attr( $opts['subject'] ?? '🆕 {post_title}' ); ?>">
						<span class="help"><?php esc_html_e( 'Variables: {post_title}, {post_excerpt}, {site_name}, {count} (digest)', 'boletines' ); ?></span>
					</div>
					<div class="row">
						<label><?php esc_html_e( 'Preview / preheader', 'boletines' ); ?></label>
						<input type="text" name="preview" value="<?php echo esc_attr( $opts['preview'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Vacío = resumen automático del primer post', 'boletines' ); ?>">
						<span class="help"><?php esc_html_e( 'Mismas variables que el asunto.', 'boletines' ); ?></span>
					</div>
				</div>

				<div>
					<button type="submit" class="bol-btn"><?php esc_html_e( 'Guardar automatización', 'boletines' ); ?></button>
				</div>
			</form>
		</div>
		<script>
		jQuery(function($){
			function syncTypeUI(){
				var t = $('#bol-auto-type').val();
				var isProduct = (t === 'product_published');
				var isDigest = (t === 'digest_daily' || t === 'digest_weekly');
				var isBirthday = (t === 'birthday_email');
				$('#bol-digest-pt-row').toggle(isDigest);
				var showProdCats = isProduct || (isDigest && $('select[name=digest_post_type]').val() === 'product');
				$('#bol-cats-product').css('display', showProdCats ? 'flex' : 'none');
				$('#bol-cats-post').css('display', showProdCats ? 'none' : 'flex');
				// Cumpleaños: oculta filtros de contenido y muestra editor de mensaje.
				$('#bol-birthday-card').toggle(isBirthday);
			}
			$('#bol-auto-type').on('change', syncTypeUI);
			$('select[name=digest_post_type]').on('change', syncTypeUI);
			syncTypeUI();
		});
		</script>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		check_admin_referer( 'boletines_save_automation' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : Automation::TYPE_POST_PUBLISHED;

		$is_product_auto = ( $type === Automation::TYPE_PRODUCT_PUBLISHED ) ||
		                   ( in_array( $type, array( Automation::TYPE_DIGEST_DAILY, Automation::TYPE_DIGEST_WEEKLY ), true )
		                     && ( $_POST['digest_post_type'] ?? '' ) === 'product' );

		$cat_ids = $is_product_auto && isset( $_POST['category_ids_product'] )
			? array_map( 'intval', (array) $_POST['category_ids_product'] )
			: ( isset( $_POST['category_ids'] ) ? array_map( 'intval', (array) $_POST['category_ids'] ) : array() );

		$opts = array(
			'subject'           => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'preview'           => isset( $_POST['preview'] ) ? sanitize_text_field( wp_unslash( $_POST['preview'] ) ) : '',
			'category_ids'      => $cat_ids,
			'digest_post_type'  => isset( $_POST['digest_post_type'] ) && $_POST['digest_post_type'] === 'product' ? 'product' : 'post',
		);

		// v1.6 — Birthday config.
		if ( $type === Automation::TYPE_BIRTHDAY ) {
			$opts['subject']   = isset( $_POST['birthday_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday_subject'] ) ) : '';
			$opts['body_html'] = isset( $_POST['birthday_body'] ) ? wp_kses_post( wp_unslash( $_POST['birthday_body'] ) ) : '';
		}

		Automation::save( array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'type'        => $type,
			'status'      => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'list_ids'    => isset( $_POST['list_ids'] ) ? array_map( 'intval', (array) $_POST['list_ids'] ) : array(),
			'tag_ids'     => array(),
			'template_id' => 0,
			'options'     => $opts,
		), $id );

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-automations&saved=1' ) );
		exit;
	}
}
