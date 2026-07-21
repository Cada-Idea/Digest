<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\ListModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lists {

	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		if ( $action === 'edit' || $action === 'new' ) {
			$this->render_edit();
		} else {
			$this->render_list();
		}
	}

	private function render_list(): void {
		$lists = ListModel::all();
		$saved = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0;
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Listas', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-lists&action=new' ) ); ?>" class="bol-btn">+ <?php esc_html_e( 'Nueva lista', 'boletines' ); ?></a>
				</div>
			</div>

			<?php if ( $saved ) : ?>
				<div class="bol-notice success"><?php esc_html_e( 'Lista guardada.', 'boletines' ); ?></div>
			<?php endif; ?>

			<table class="bol-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nombre', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Suscriptores', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Shortcode', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'boletines' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $lists ) ) : ?>
						<tr><td colspan="5" style="text-align:center;color:#6b7280;padding:40px;"><?php esc_html_e( 'No hay listas todavía.', 'boletines' ); ?></td></tr>
					<?php else : foreach ( $lists as $l ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $l['name'] ); ?></strong>
								<?php if ( $l['description'] ) : ?>
									<div style="color:#6b7280;font-size:13px;margin-top:2px;"><?php echo esc_html( $l['description'] ); ?></div>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $l['slug'] ); ?></code></td>
							<td><?php echo number_format_i18n( ListModel::subscriber_count( (int) $l['id'] ) ); ?></td>
							<td><code>[boletines_form list="<?php echo (int) $l['id']; ?>"]</code></td>
							<td class="bol-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-lists&action=edit&id=' . (int) $l['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'boletines' ); ?></a>
								<a href="#" class="delete bol-delete-list" data-id="<?php echo (int) $l['id']; ?>"><?php esc_html_e( 'Borrar', 'boletines' ); ?></a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_edit(): void {
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$list = $id ? ListModel::find( $id ) : null;
		$is_new = ! $list;
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php echo $is_new ? esc_html__( 'Nueva lista', 'boletines' ) : esc_html__( 'Editar lista', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-lists' ) ); ?>" class="bol-btn bol-btn-secondary">← <?php esc_html_e( 'Volver', 'boletines' ); ?></a>
				</div>
			</div>

			<div class="bol-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
					<input type="hidden" name="action" value="boletines_save_list">
					<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
					<?php wp_nonce_field( 'boletines_save_list' ); ?>

					<div class="row">
						<label><?php esc_html_e( 'Nombre', 'boletines' ); ?> *</label>
						<input type="text" name="name" required value="<?php echo esc_attr( $list['name'] ?? '' ); ?>">
					</div>

					<div class="row">
						<label><?php esc_html_e( 'Slug', 'boletines' ); ?></label>
						<input type="text" name="slug" value="<?php echo esc_attr( $list['slug'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'se generará desde el nombre', 'boletines' ); ?>">
						<span class="help"><?php esc_html_e( 'Identificador interno único.', 'boletines' ); ?></span>
					</div>

					<div class="row">
						<label><?php esc_html_e( 'Descripción', 'boletines' ); ?></label>
						<textarea name="description"><?php echo esc_textarea( $list['description'] ?? '' ); ?></textarea>
					</div>

					<div class="row">
						<label><?php esc_html_e( 'Imagen / icono', 'boletines' ); ?></label>
						<p class="help" style="margin:0 0 8px;">
							<?php esc_html_e( 'Esta imagen aparece junto a la lista en formularios tipo "selector". Puedes usar una imagen de la mediateca o pegar un SVG inline.', 'boletines' ); ?>
						</p>

						<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
							<input type="url" name="image_url" id="bol-list-image-url" value="<?php echo esc_attr( $list['image_url'] ?? '' ); ?>" placeholder="https://..." style="flex:1;">
							<button type="button" class="bol-btn bol-btn-secondary bol-pick-media" data-target="#bol-list-image-url" data-preview="#bol-list-image-preview"><?php esc_html_e( 'Elegir imagen', 'boletines' ); ?></button>
						</div>
						<div id="bol-list-image-preview" style="margin-bottom:14px;<?php echo empty( $list['image_url'] ) ? 'display:none;' : ''; ?>">
							<?php if ( ! empty( $list['image_url'] ) ) : ?>
								<img src="<?php echo esc_url( $list['image_url'] ); ?>" alt="" style="max-width:120px;height:auto;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#f9fafb;">
							<?php endif; ?>
						</div>
					</div>

					<div class="row">
						<label><?php esc_html_e( 'O bien — SVG inline (alternativa a la imagen)', 'boletines' ); ?></label>
						<textarea name="icon_svg" rows="6" style="font-family:monospace;font-size:12px;" placeholder='&lt;svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"&gt;...&lt;/svg&gt;'><?php echo esc_textarea( $list['icon_svg'] ?? '' ); ?></textarea>
						<span class="help"><?php esc_html_e( 'Pega aquí el código SVG completo. Se sanea (sin scripts ni handlers). Útil para iconos pequeños y nítidos.', 'boletines' ); ?></span>
					</div>

					<div class="row">
						<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
							<input type="checkbox" name="is_public" value="1" <?php checked( $list ? (int) $list['is_public'] === 1 : true ); ?>>
							<?php esc_html_e( 'Pública (visible en formularios)', 'boletines' ); ?>
						</label>
					</div>

					<div>
						<button type="submit" class="bol-btn"><?php esc_html_e( 'Guardar lista', 'boletines' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<script>
		jQuery(function($){
			$('.bol-pick-media').on('click', function(e){
				e.preventDefault();
				var btn = $(this);
				var frame = wp.media({
					title: '<?php echo esc_js( __( 'Elegir imagen para la lista', 'boletines' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Usar esta imagen', 'boletines' ) ); ?>' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$( btn.data('target') ).val(att.url);
					var $prev = $( btn.data('preview') );
					$prev.html('<img src="' + att.url + '" alt="" style="max-width:120px;height:auto;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#f9fafb;">').show();
				});
				frame.open();
			});
		});
		</script>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		check_admin_referer( 'boletines_save_list' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
			'image_url'   => isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '',
			'icon_svg'    => isset( $_POST['icon_svg'] )  ? wp_unslash( $_POST['icon_svg'] ) : '', // se sanea en el modelo
			'is_public'   => isset( $_POST['is_public'] ) ? 1 : 0,
		);

		if ( $id ) {
			ListModel::update( $id, $data );
		} else {
			ListModel::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-lists&saved=1' ) );
		exit;
	}
}
