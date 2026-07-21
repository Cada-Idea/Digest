<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\Form;
use Boletines\Models\ListModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Forms {

	public function render(): void {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		if ( $action === 'edit' || $action === 'new' ) {
			$this->render_edit();
		} else {
			$this->render_list();
		}
	}

	private function render_list(): void {
		$forms = Form::all();
		$saved = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0;
		$types = Form::types();
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Formularios', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-forms&action=new' ) ); ?>" class="bol-btn">+ <?php esc_html_e( 'Nuevo formulario', 'boletines' ); ?></a>
				</div>
			</div>

			<?php if ( $saved ) : ?><div class="bol-notice success"><?php esc_html_e( 'Formulario guardado.', 'boletines' ); ?></div><?php endif; ?>

			<p style="color:#6b7280;margin-bottom:16px;line-height:1.6;">
				<?php esc_html_e( 'Crea formularios de captura para tu sitio. Los tipos "auto" (popup, slide-in, barra, exit-intent, after-content) se muestran solos según las reglas que configures. Los formularios "inline" se insertan con el shortcode', 'boletines' ); ?>
				<code>[boletines_form id="N"]</code>.
				<br>
				<?php esc_html_e( 'También puedes usar el shortcode rápido sin crear un formulario:', 'boletines' ); ?>
				<code>[boletines_minimal list="1,2" title="..." button="Enviar"]</code>
				<?php esc_html_e( '— estilo Mailchimp, se adapta automáticamente al tema.', 'boletines' ); ?>
			</p>

			<table class="bol-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nombre', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Tipo', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Impresiones', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Conversiones', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'CR', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'boletines' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $forms ) ) : ?>
						<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:40px;"><?php esc_html_e( 'Aún no has creado ningún formulario.', 'boletines' ); ?></td></tr>
					<?php else : foreach ( $forms as $f ) :
						$cr = $f['impressions'] > 0 ? round( $f['conversions'] * 100 / $f['impressions'], 1 ) : 0;
					?>
						<tr>
							<td>
								<strong><?php echo esc_html( $f['name'] ); ?></strong>
								<?php if ( in_array( $f['type'], array( Form::TYPE_INLINE, Form::TYPE_INLINE_MINIMAL, Form::TYPE_WIDGET ), true ) ) : ?>
									<div style="font-size:11px;color:#9ca3af;font-family:monospace;margin-top:2px;">[boletines_form id="<?php echo (int) $f['id']; ?>"]</div>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $types[ $f['type'] ] ?? $f['type'] ); ?></td>
							<td><span class="bol-badge <?php echo $f['status'] === 'active' ? 'sent' : 'paused'; ?>"><?php echo esc_html( $f['status'] ); ?></span></td>
							<td><?php echo number_format_i18n( (int) $f['impressions'] ); ?></td>
							<td><?php echo number_format_i18n( (int) $f['conversions'] ); ?></td>
							<td><?php echo $cr; ?>%</td>
							<td class="bol-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-forms&action=edit&id=' . (int) $f['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'boletines' ); ?></a>
								<a href="#" class="delete bol-delete-form" data-id="<?php echo (int) $f['id']; ?>"><?php esc_html_e( 'Borrar', 'boletines' ); ?></a>
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
		$f  = $id ? Form::find( $id ) : null;
		$is_new = ! $f;
		$lists  = ListModel::all();
		$types  = Form::types();
		$opts   = $f ? $f['options'] : array();
		$selected_lists = $f ? $f['list_ids'] : array();
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php echo $is_new ? esc_html__( 'Nuevo formulario', 'boletines' ) : esc_html__( 'Editar formulario', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-forms' ) ); ?>" class="bol-btn bol-btn-secondary">← <?php esc_html_e( 'Volver', 'boletines' ); ?></a>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
				<input type="hidden" name="action" value="boletines_save_form">
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'boletines_save_form' ); ?>

				<div class="bol-card">
					<div class="row row-2">
						<div class="row">
							<label><?php esc_html_e( 'Nombre interno', 'boletines' ); ?> *</label>
							<input type="text" name="name" required value="<?php echo esc_attr( $f['name'] ?? '' ); ?>">
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Estado', 'boletines' ); ?></label>
							<select name="status">
								<option value="active" <?php selected( $f['status'] ?? 'active', 'active' ); ?>><?php esc_html_e( 'Activo', 'boletines' ); ?></option>
								<option value="paused" <?php selected( $f['status'] ?? '', 'paused' ); ?>><?php esc_html_e( 'Pausado', 'boletines' ); ?></option>
							</select>
						</div>
					</div>

					<div class="row" style="margin-top:14px;">
						<label><?php esc_html_e( 'Tipo', 'boletines' ); ?></label>
						<select name="type" id="bol-form-type">
							<?php foreach ( $types as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['type'] ?? Form::TYPE_INLINE, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="bol-card">
					<h2><?php esc_html_e( 'Contenido visible', 'boletines' ); ?></h2>
					<div class="row">
						<label><?php esc_html_e( 'Título', 'boletines' ); ?></label>
						<input type="text" name="title" value="<?php echo esc_attr( $f['title'] ?? __( 'Suscríbete a nuestro boletín', 'boletines' ) ); ?>">
					</div>
					<div class="row">
						<label><?php esc_html_e( 'Descripción', 'boletines' ); ?></label>
						<textarea name="description" rows="2"><?php echo esc_textarea( $f['description'] ?? __( 'Recibe nuestras novedades en tu correo.', 'boletines' ) ); ?></textarea>
					</div>
					<div class="row">
						<label><?php esc_html_e( 'Texto del botón', 'boletines' ); ?></label>
						<input type="text" name="button_text" value="<?php echo esc_attr( $f['button_text'] ?? __( 'Suscribirme', 'boletines' ) ); ?>">
					</div>
				</div>

				<div class="bol-card">
					<h2><?php esc_html_e( 'Campos a mostrar', 'boletines' ); ?></h2>
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:8px;">
						<input type="checkbox" name="show_first_name" value="1" <?php checked( $f ? (int) $f['show_first_name'] === 1 : true ); ?>>
						<?php esc_html_e( 'Mostrar campo Nombre', 'boletines' ); ?>
					</label>
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:8px;">
						<input type="checkbox" name="require_first_name" value="1" <?php checked( $f && (int) $f['require_first_name'] === 1 ); ?>>
						<?php esc_html_e( 'Hacer que el Nombre sea obligatorio', 'boletines' ); ?>
					</label>
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:8px;">
						<input type="checkbox" name="show_last_name" value="1" <?php checked( $f ? (int) $f['show_last_name'] === 1 : true ); ?>>
						<?php esc_html_e( 'Mostrar campo Apellido', 'boletines' ); ?>
					</label>

					<hr style="margin:12px 0;border:0;border-top:1px solid #f3f4f6;">

					<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:8px;">
						<input type="checkbox" name="show_birthday" value="1" <?php checked( ! empty( $opts['show_birthday'] ) ); ?>>
						<?php esc_html_e( 'Mostrar campo Cumpleaños (para envío automático ese día)', 'boletines' ); ?>
					</label>
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-left:24px;">
						<input type="checkbox" name="require_birthday" value="1" <?php checked( ! empty( $opts['require_birthday'] ) ); ?>>
						<?php esc_html_e( '...y hacerlo obligatorio', 'boletines' ); ?>
					</label>
				</div>

				<div class="bol-card">
					<h2><?php esc_html_e( 'Listas a las que se suscriben', 'boletines' ); ?></h2>
					<?php if ( empty( $lists ) ) : ?>
						<p style="color:#6b7280;"><?php esc_html_e( 'Crea al menos una lista primero.', 'boletines' ); ?></p>
					<?php else : ?>
						<p style="color:#6b7280;font-size:13px;margin-bottom:8px;"><?php esc_html_e( 'Si seleccionas más de una lista, el formulario mostrará un selector de tarjetas para que el usuario elija.', 'boletines' ); ?></p>
						<?php foreach ( $lists as $l ) : ?>
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:6px;">
								<input type="checkbox" name="list_ids[]" value="<?php echo (int) $l['id']; ?>" <?php checked( in_array( (int) $l['id'], $selected_lists, true ) ); ?>>
								<?php echo esc_html( $l['name'] ); ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<div class="bol-card" id="bol-trigger-card">
					<h2><?php esc_html_e( 'Disparador y reglas', 'boletines' ); ?></h2>

					<div class="row row-2">
						<div class="row">
							<label><?php esc_html_e( 'Mostrar segundos después de cargar (popup, slide-in)', 'boletines' ); ?></label>
							<input type="number" name="trigger_seconds" min="0" max="600" value="<?php echo (int) ( $opts['trigger_seconds'] ?? 8 ); ?>">
							<span class="help"><?php esc_html_e( 'Pon 0 si quieres usar scroll en lugar de tiempo.', 'boletines' ); ?></span>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'O mostrar al alcanzar % de scroll', 'boletines' ); ?></label>
							<input type="number" name="scroll_percent" min="0" max="100" value="<?php echo (int) ( $opts['scroll_percent'] ?? 0 ); ?>">
							<span class="help"><?php esc_html_e( '0 = desactivado. 50 = a mitad de página.', 'boletines' ); ?></span>
						</div>
					</div>

					<div class="row row-2" style="margin-top:14px;">
						<div class="row">
							<label><?php esc_html_e( '¿Dónde mostrarlo?', 'boletines' ); ?></label>
							<select name="display_on">
								<?php
								$rules = array(
									'all'      => __( 'En todo el sitio', 'boletines' ),
									'home'     => __( 'Sólo en la portada', 'boletines' ),
									'posts'    => __( 'Sólo en posts', 'boletines' ),
									'pages'    => __( 'Sólo en páginas', 'boletines' ),
									'singular' => __( 'Posts y páginas (no archivos)', 'boletines' ),
									'archive'  => __( 'Sólo en archivos / categorías', 'boletines' ),
								);
								$cur = $opts['display_on'] ?? 'all';
								foreach ( $rules as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cur, $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Dispositivos', 'boletines' ); ?></label>
							<select name="device">
								<?php
								$devs = array( 'all' => __( 'Todos', 'boletines' ), 'mobile' => __( 'Sólo móvil', 'boletines' ), 'desktop' => __( 'Sólo escritorio', 'boletines' ) );
								$dcur = $opts['device'] ?? 'all';
								foreach ( $devs as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $dcur, $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="row row-2" style="margin-top:14px;">
						<div class="row">
							<label><?php esc_html_e( 'Días entre apariciones por visitante', 'boletines' ); ?></label>
							<input type="number" name="frequency_days" min="0" max="365" value="<?php echo (int) ( $opts['frequency_days'] ?? 7 ); ?>">
							<span class="help"><?php esc_html_e( '7 = no se vuelve a mostrar al mismo usuario en 7 días.', 'boletines' ); ?></span>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Máximo de apariciones por visitante', 'boletines' ); ?></label>
							<input type="number" name="max_shows" min="0" max="50" value="<?php echo (int) ( $opts['max_shows'] ?? 3 ); ?>">
							<span class="help"><?php esc_html_e( '0 = sin límite.', 'boletines' ); ?></span>
						</div>
					</div>

					<div class="row" id="bol-after-content-row" style="margin-top:14px;">
						<label><?php esc_html_e( 'Insertar después del párrafo Nº (sólo "Después del contenido")', 'boletines' ); ?></label>
						<input type="number" name="after_paragraph" min="0" max="50" value="<?php echo (int) ( $opts['after_paragraph'] ?? 3 ); ?>">
						<span class="help"><?php esc_html_e( '0 = al final del post. 3 = después del 3er párrafo.', 'boletines' ); ?></span>
					</div>

					<div class="row" style="margin-top:14px;">
						<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
							<input type="checkbox" name="show_close" value="1" <?php checked( ! isset( $opts['show_close'] ) || ! empty( $opts['show_close'] ) ); ?>>
							<?php esc_html_e( 'Mostrar botón de cerrar (×)', 'boletines' ); ?>
						</label>
					</div>
				</div>

				<div>
					<button type="submit" class="bol-btn"><?php esc_html_e( 'Guardar formulario', 'boletines' ); ?></button>
				</div>
			</form>
		</div>
		<script>
		jQuery(function($){
			function syncTypeUI(){
				var t = $('#bol-form-type').val();
				// after-content
				$('#bol-after-content-row').toggle( t === 'after_content' );
				// inline y widget no usan disparador
				$('#bol-trigger-card').toggle( t !== 'inline' && t !== 'widget' );
			}
			$('#bol-form-type').on('change', syncTypeUI);
			syncTypeUI();
		});
		</script>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		check_admin_referer( 'boletines_save_form' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$opts = array(
			'trigger_seconds' => isset( $_POST['trigger_seconds'] ) ? max( 0, (int) $_POST['trigger_seconds'] ) : 8,
			'scroll_percent'  => isset( $_POST['scroll_percent'] )  ? max( 0, min( 100, (int) $_POST['scroll_percent'] ) ) : 0,
			'display_on'      => isset( $_POST['display_on'] )      ? sanitize_text_field( wp_unslash( $_POST['display_on'] ) ) : 'all',
			'device'          => isset( $_POST['device'] )          ? sanitize_text_field( wp_unslash( $_POST['device'] ) ) : 'all',
			'frequency_days'  => isset( $_POST['frequency_days'] )  ? max( 0, (int) $_POST['frequency_days'] ) : 7,
			'max_shows'       => isset( $_POST['max_shows'] )       ? max( 0, (int) $_POST['max_shows'] ) : 3,
			'after_paragraph' => isset( $_POST['after_paragraph'] ) ? max( 0, (int) $_POST['after_paragraph'] ) : 3,
			'show_close'      => isset( $_POST['show_close'] ) ? 1 : 0,
			// v1.6 — campos extra
			'show_birthday'        => ! empty( $_POST['show_birthday'] ),
			'require_birthday'     => ! empty( $_POST['require_birthday'] ),
		);

		Form::save( array(
			'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'type'              => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : Form::TYPE_INLINE,
			'status'            => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'list_ids'          => isset( $_POST['list_ids'] ) ? array_map( 'intval', (array) $_POST['list_ids'] ) : array(),
			'title'             => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'       => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'button_text'       => isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '',
			'show_first_name'   => ! empty( $_POST['show_first_name'] ),
			'show_last_name'    => ! empty( $_POST['show_last_name'] ),
			'require_first_name'=> ! empty( $_POST['require_first_name'] ),
			'options'           => $opts,
		), $id );

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-forms&saved=1' ) );
		exit;
	}
}
