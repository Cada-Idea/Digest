<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\Resource;
use Boletines\Models\ListModel;

defined( 'ABSPATH' ) || exit;

/**
 * Página admin: Boletines → Recursos.
 * Lista, crear, editar, eliminar recursos.
 */
class Resources {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// v1.10.3 — Herramienta de emergencia: crear tabla AHORA con diagnóstico.
		if ( ! empty( $_GET['force_create'] ) && check_admin_referer( 'boletines_force_create_tables' ) ) {
			$this->force_create_tables();
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		// Handle POST
		if ( ! empty( $_POST['boletines_resource_save'] ) && check_admin_referer( 'boletines_resource_save' ) ) {
			$this->handle_save();
			return;
		}
		if ( ! empty( $_POST['boletines_resource_delete'] ) && check_admin_referer( 'boletines_resource_delete' ) ) {
			$this->handle_delete();
			return;
		}

		echo '<div class="wrap">';
		if ( $action === 'edit' || $action === 'new' ) {
			$this->render_edit( $id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// LIST
	// ─────────────────────────────────────────────────────────────────────────

	private function render_list(): void {
		global $wpdb;
		$resources_table = $wpdb->prefix . 'boletines_resources';
		$tokens_table    = $wpdb->prefix . 'boletines_resource_tokens';
		$tables_ok = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $resources_table ) )
				  && (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tokens_table ) );

		$diag_url = wp_nonce_url(
			admin_url( 'admin.php?page=boletines-resources&force_create=1' ),
			'boletines_force_create_tables'
		);

		$resources = $tables_ok ? Resource::all( array( 'limit' => 200 ) ) : array();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Recursos', 'boletines' ); ?></h1>
		<?php if ( $tables_ok ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-resources&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Añadir nuevo', 'boletines' ); ?>
			</a>
		<?php endif; ?>
		<hr class="wp-header-end">

		<?php if ( ! $tables_ok ) : ?>
			<div class="notice notice-error" style="margin:20px 0;padding:20px;">
				<h2 style="margin-top:0;color:#dc2626;">⚠️ <?php esc_html_e( 'Las tablas de Recursos no existen en tu base de datos', 'boletines' ); ?></h2>
				<p>
					<?php esc_html_e( 'Por algún motivo (puede ser la versión de MySQL, los permisos del usuario de BD, o un conflicto con dbDelta de WordPress), las tablas no se han podido crear automáticamente.', 'boletines' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Solución:', 'boletines' ); ?></strong>
					<?php esc_html_e( 'Pulsa el botón siguiente. Esto ejecutará un CREATE TABLE directo en tu BD y te mostrará exactamente qué responde MySQL.', 'boletines' ); ?>
				</p>
				<p style="margin-top:14px;">
					<a href="<?php echo esc_url( $diag_url ); ?>" class="button button-primary button-large" style="background:#dc2626;border-color:#dc2626;">
						🔧 <?php esc_html_e( 'Diagnosticar y crear tablas ahora', 'boletines' ); ?>
					</a>
				</p>
			</div>
			<?php return; ?>
		<?php endif; ?>

		<p style="color:#6b7280;max-width:780px;">
			<?php esc_html_e( 'Los Recursos son archivos descargables (PDF, ZIP, audio…) que ofreces a cambio de una suscripción. Cada visitante recibe un correo con un enlace único que expira pasados unos días.', 'boletines' ); ?>
		</p>

		<?php if ( empty( $resources ) ) : ?>
			<div class="bol-card" style="text-align:center;padding:48px 24px;">
				<p style="font-size:16px;color:#6b7280;margin-bottom:16px;">
					<?php esc_html_e( 'Aún no has creado ningún recurso.', 'boletines' ); ?>
				</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-resources&action=new' ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Crear mi primer recurso', 'boletines' ); ?>
				</a>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Título', 'boletines' ); ?></th>
						<th style="width:140px;"><?php esc_html_e( 'Shortcode', 'boletines' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Estado', 'boletines' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Suscripciones', 'boletines' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'Descargas', 'boletines' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Acciones', 'boletines' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $resources as $r ) : ?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-resources&action=edit&id=' . (int) $r['id'] ) ); ?>">
										<?php echo esc_html( $r['title'] ); ?>
									</a>
								</strong>
								<div style="color:#6b7280;font-size:12px;margin-top:2px;"><?php echo esc_html( Resource::file_name( $r ) ); ?></div>
							</td>
							<td>
								<code style="font-size:11px;">[boletines_resource id="<?php echo (int) $r['id']; ?>"]</code>
							</td>
							<td>
								<?php if ( $r['status'] === Resource::STATUS_ACTIVE ) : ?>
									<span style="color:#059669;font-weight:600;">● <?php esc_html_e( 'Activo', 'boletines' ); ?></span>
								<?php else : ?>
									<span style="color:#9ca3af;">○ <?php esc_html_e( 'Inactivo', 'boletines' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo (int) $r['submissions_count']; ?></td>
							<td><?php echo (int) $r['downloads_count']; ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-resources&action=edit&id=' . (int) $r['id'] ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Editar', 'boletines' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="bol-card" style="margin-top:24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Página pública', 'boletines' ); ?></h2>
				<p style="color:#6b7280;font-size:13px;">
					<?php esc_html_e( 'Todos los recursos activos aparecen automáticamente en:', 'boletines' ); ?>
					<a href="<?php echo esc_url( home_url( '/recursos' ) ); ?>" target="_blank">
						<?php echo esc_html( home_url( '/recursos' ) ); ?>
					</a>
				</p>
				<p style="color:#6b7280;font-size:13px;">
					<?php esc_html_e( 'Si la URL no funciona, ve a Ajustes → Enlaces permanentes y pulsa "Guardar cambios" para refrescar las reglas.', 'boletines' ); ?>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// EDIT / NEW
	// ─────────────────────────────────────────────────────────────────────────

	private function render_edit( int $id ): void {
		$r = $id > 0 ? Resource::get( $id ) : null;
		$is_new = ! $r;
		$defaults = array(
			'id'                 => 0,
			'title'              => '',
			'slug'               => '',
			'description'        => '',
			'file_type'          => Resource::FILE_TYPE_ATTACHMENT,
			'file_attachment_id' => 0,
			'file_external_url'  => '',
			'cover_image_id'     => 0,
			'list_ids_array'     => array(),
			'button_text'        => __( 'Descargar', 'boletines' ),
			'success_message'    => '',
			'email_subject'      => '',
			'email_body'         => '',
			'token_ttl_days'     => 7,
			'status'             => Resource::STATUS_ACTIVE,
		);
		$r = $r ? array_merge( $defaults, $r ) : $defaults;

		$lists = ListModel::all();

		// Cargar uploader de media
		wp_enqueue_media();
		?>
		<h1><?php echo $is_new ? esc_html__( 'Nuevo recurso', 'boletines' ) : esc_html__( 'Editar recurso', 'boletines' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-resources' ) ); ?>" style="color:#6b7280;text-decoration:none;">
			← <?php esc_html_e( 'Volver al listado', 'boletines' ); ?>
		</a>

		<form method="post" style="max-width:820px;margin-top:18px;">
			<?php wp_nonce_field( 'boletines_resource_save' ); ?>
			<input type="hidden" name="boletines_resource_save" value="1">
			<input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">

			<div class="bol-card">
				<h2><?php esc_html_e( 'Información básica', 'boletines' ); ?></h2>
				<div class="row">
					<label><?php esc_html_e( 'Título', 'boletines' ); ?> *</label>
					<input type="text" name="title" value="<?php echo esc_attr( $r['title'] ); ?>" required>
				</div>
				<div class="row">
					<label><?php esc_html_e( 'Slug (URL amigable)', 'boletines' ); ?></label>
					<input type="text" name="slug" value="<?php echo esc_attr( $r['slug'] ); ?>" placeholder="<?php esc_attr_e( 'se genera del título si lo dejas vacío', 'boletines' ); ?>">
				</div>
				<div class="row">
					<label><?php esc_html_e( 'Descripción', 'boletines' ); ?></label>
					<textarea name="description" rows="4"><?php echo esc_textarea( $r['description'] ); ?></textarea>
					<p class="help"><?php esc_html_e( 'Aparece debajo del título en la tarjeta del recurso.', 'boletines' ); ?></p>
				</div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Archivo del recurso', 'boletines' ); ?></h2>
				<div class="row">
					<label><?php esc_html_e( 'Tipo de archivo', 'boletines' ); ?></label>
					<label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;margin-right:18px;">
						<input type="radio" name="file_type" value="attachment" <?php checked( $r['file_type'], Resource::FILE_TYPE_ATTACHMENT ); ?>>
						<?php esc_html_e( 'Subir archivo a la biblioteca de WordPress', 'boletines' ); ?>
					</label>
					<label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;">
						<input type="radio" name="file_type" value="external" <?php checked( $r['file_type'], Resource::FILE_TYPE_EXTERNAL ); ?>>
						<?php esc_html_e( 'Enlace externo (Dropbox, Drive…)', 'boletines' ); ?>
					</label>
				</div>

				<div class="row">
					<label><?php esc_html_e( 'Archivo (biblioteca WP)', 'boletines' ); ?></label>
					<div class="bol-media-picker" data-target="file_attachment_id">
						<input type="hidden" name="file_attachment_id" id="bol_file_attachment_id" value="<?php echo (int) $r['file_attachment_id']; ?>">
						<button type="button" class="button" onclick="bolMediaPick('file_attachment_id', 'bol_file_label', '<?php esc_attr_e( 'Selecciona archivo', 'boletines' ); ?>')">
							📎 <?php esc_html_e( 'Seleccionar archivo', 'boletines' ); ?>
						</button>
						<span id="bol_file_label" style="margin-left:10px;color:#6b7280;font-size:13px;">
							<?php echo ! empty( $r['file_attachment_id'] ) ? esc_html( basename( (string) get_attached_file( (int) $r['file_attachment_id'] ) ) ) : esc_html__( 'ningún archivo seleccionado', 'boletines' ); ?>
						</span>
					</div>
				</div>

				<div class="row">
					<label><?php esc_html_e( 'URL externa', 'boletines' ); ?></label>
					<input type="url" name="file_external_url" value="<?php echo esc_attr( $r['file_external_url'] ); ?>" placeholder="https://drive.google.com/...">
				</div>

				<div class="row">
					<label><?php esc_html_e( 'Imagen de portada (opcional)', 'boletines' ); ?></label>
					<div class="bol-media-picker">
						<input type="hidden" name="cover_image_id" id="bol_cover_image_id" value="<?php echo (int) $r['cover_image_id']; ?>">
						<button type="button" class="button" onclick="bolMediaPick('cover_image_id', 'bol_cover_label', '<?php esc_attr_e( 'Selecciona imagen', 'boletines' ); ?>', 'image')">
							🖼 <?php esc_html_e( 'Seleccionar imagen', 'boletines' ); ?>
						</button>
						<span id="bol_cover_label" style="margin-left:10px;color:#6b7280;font-size:13px;">
							<?php
							if ( ! empty( $r['cover_image_id'] ) ) {
								$cover_path = get_attached_file( (int) $r['cover_image_id'] );
								echo $cover_path ? esc_html( basename( $cover_path ) ) : '';
							} else {
								esc_html_e( 'ninguna imagen seleccionada', 'boletines' );
							}
							?>
						</span>
					</div>
				</div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Suscripción', 'boletines' ); ?></h2>
				<div class="row">
					<label><?php esc_html_e( 'Apuntar al suscriptor a estas listas', 'boletines' ); ?></label>
					<?php if ( empty( $lists ) ) : ?>
						<p class="help">
							<?php esc_html_e( 'No tienes listas creadas todavía.', 'boletines' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-lists' ) ); ?>">
								<?php esc_html_e( 'Crear una lista →', 'boletines' ); ?>
							</a>
						</p>
					<?php else : ?>
						<div style="max-height:200px;overflow:auto;border:1px solid #e5e7eb;padding:10px;border-radius:6px;">
							<?php foreach ( $lists as $list ) : ?>
								<label style="display:block;padding:4px 0;font-weight:400;">
									<input type="checkbox" name="list_ids[]" value="<?php echo (int) $list['id']; ?>" <?php checked( in_array( (int) $list['id'], $r['list_ids_array'], true ) ); ?>>
									<?php echo esc_html( $list['name'] ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="row">
					<label><?php esc_html_e( 'Texto del botón', 'boletines' ); ?></label>
					<input type="text" name="button_text" value="<?php echo esc_attr( $r['button_text'] ); ?>">
				</div>
				<div class="row">
					<label><?php esc_html_e( 'Mensaje de éxito tras suscribirse', 'boletines' ); ?></label>
					<input type="text" name="success_message" value="<?php echo esc_attr( $r['success_message'] ); ?>" placeholder="<?php esc_attr_e( '¡Listo! Te hemos enviado el enlace al correo.', 'boletines' ); ?>">
				</div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Correo de descarga', 'boletines' ); ?></h2>
				<div class="row">
					<label><?php esc_html_e( 'Asunto del correo', 'boletines' ); ?></label>
					<input type="text" name="email_subject" value="<?php echo esc_attr( $r['email_subject'] ); ?>" placeholder="<?php esc_attr_e( 'Tu descarga: {resource_title}', 'boletines' ); ?>">
				</div>
				<div class="row">
					<label><?php esc_html_e( 'Cuerpo del correo (HTML)', 'boletines' ); ?></label>
					<textarea name="email_body" rows="8" style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $r['email_body'] ); ?></textarea>
					<p class="help">
						<?php esc_html_e( 'Variables: {first_name}, {email}, {download_url}, {resource_title}, {expires_at}, {site_name}. Si lo dejas vacío se usa un template por defecto.', 'boletines' ); ?>
					</p>
				</div>
				<div class="row">
					<label><?php esc_html_e( 'Días hasta que expire el enlace', 'boletines' ); ?></label>
					<input type="number" name="token_ttl_days" min="1" max="365" value="<?php echo (int) $r['token_ttl_days']; ?>" style="width:100px;">
				</div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Estado', 'boletines' ); ?></h2>
				<div class="row">
					<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
						<input type="checkbox" name="status_active" value="1" <?php checked( $r['status'], Resource::STATUS_ACTIVE ); ?>>
						<?php esc_html_e( 'Activo (visible para visitantes)', 'boletines' ); ?>
					</label>
				</div>
			</div>

			<p>
				<button type="submit" class="button button-primary button-large">
					<?php echo $is_new ? esc_html__( 'Crear recurso', 'boletines' ) : esc_html__( 'Guardar cambios', 'boletines' ); ?>
				</button>
				<?php if ( ! $is_new ) : ?>
					&nbsp;&nbsp;
					<button type="submit" name="boletines_resource_delete_btn" value="1" formaction="?page=boletines-resources&action=delete" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e( '¿Eliminar este recurso? Los tokens existentes dejarán de funcionar.', 'boletines' ); ?>')">
						<?php esc_html_e( 'Eliminar recurso', 'boletines' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>

		<script>
		function bolMediaPick(targetField, labelField, title, type) {
			type = type || '';
			if (!window.wp || !window.wp.media) {
				alert('La biblioteca de medios no está disponible.');
				return;
			}
			var frame = wp.media({
				title: title,
				button: { text: 'Usar este archivo' },
				library: type ? { type: type } : {},
				multiple: false
			});
			frame.on('select', function() {
				var att = frame.state().get('selection').first().toJSON();
				document.getElementById('bol_' + targetField).value = att.id;
				document.getElementById(labelField).textContent = att.filename || att.title || att.id;
			});
			frame.open();
		}
		</script>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// HANDLERS
	// ─────────────────────────────────────────────────────────────────────────

	private function handle_save(): void {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$data = array(
			'title'              => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'slug'               => sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ),
			'description'        => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'file_type'          => ( ( $_POST['file_type'] ?? 'attachment' ) === 'external' ) ? Resource::FILE_TYPE_EXTERNAL : Resource::FILE_TYPE_ATTACHMENT,
			'file_attachment_id' => (int) ( $_POST['file_attachment_id'] ?? 0 ),
			'file_external_url'  => esc_url_raw( wp_unslash( $_POST['file_external_url'] ?? '' ) ),
			'cover_image_id'     => (int) ( $_POST['cover_image_id'] ?? 0 ),
			'list_ids'           => isset( $_POST['list_ids'] ) ? array_map( 'intval', (array) $_POST['list_ids'] ) : array(),
			'button_text'        => sanitize_text_field( wp_unslash( $_POST['button_text'] ?? '' ) ),
			'success_message'    => sanitize_text_field( wp_unslash( $_POST['success_message'] ?? '' ) ),
			'email_subject'      => sanitize_text_field( wp_unslash( $_POST['email_subject'] ?? '' ) ),
			'email_body'         => wp_kses_post( wp_unslash( $_POST['email_body'] ?? '' ) ),
			'token_ttl_days'     => (int) ( $_POST['token_ttl_days'] ?? 7 ),
			'status'             => ! empty( $_POST['status_active'] ) ? Resource::STATUS_ACTIVE : Resource::STATUS_INACTIVE,
		);

		$saved_id = Resource::save( $data, $id );

		if ( $saved_id > 0 ) {
			$redirect = admin_url( 'admin.php?page=boletines-resources&action=edit&id=' . $saved_id . '&saved=1' );
			wp_safe_redirect( $redirect );
			exit;
		}

		// v1.10.1 — mostrar el motivo real del fallo, no un mensaje genérico.
		global $wpdb;
		$detail = '';
		if ( ! empty( $wpdb->last_error ) ) {
			$detail = $wpdb->last_error;
		}
		// ¿Existe la tabla?
		$table = Resource::table();
		$table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $table_exists ) {
			$detail = sprintf( __( 'La tabla %s no existe. Intentando crearla automáticamente — recarga e intenta de nuevo.', 'boletines' ), $table );
			// Forzar creación incluyendo fallback raw.
			\Boletines\Installer::ensure_critical_tables();
		}

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'No se pudo guardar el recurso.', 'boletines' ) . '</strong></p>';
		if ( $detail !== '' ) {
			echo '<p><code style="font-size:12px;color:#dc2626;">' . esc_html( $detail ) . '</code></p>';
		}

		// v1.10.2 — Mostrar errores del último intento de migración para diagnóstico.
		$installer_log = (array) get_option( 'boletines_installer_log', array() );
		if ( ! empty( $installer_log['errors'] ) ) {
			echo '<p><strong>' . esc_html__( 'Errores detectados en la última migración:', 'boletines' ) . '</strong></p>';
			echo '<pre style="background:#fef2f2;padding:10px;border-radius:4px;font-size:11px;overflow:auto;max-width:780px;color:#7f1d1d;">';
			foreach ( $installer_log['errors'] as $errinfo ) {
				echo esc_html( sprintf( "Tabla: %s\nError: %s\n\n", $errinfo['table'] ?? '?', $errinfo['error'] ?? '?' ) );
			}
			echo '</pre>';
		}
		if ( ! empty( $installer_log['raw_fallback'] ) ) {
			echo '<p><strong>' . esc_html__( 'Fallback raw CREATE TABLE ejecutado:', 'boletines' ) . '</strong></p>';
			echo '<pre style="background:#fef9e7;padding:10px;border-radius:4px;font-size:11px;overflow:auto;max-width:780px;color:#78350f;">';
			echo esc_html( sprintf( "Cuándo: %s\nError: %s\n",
				$installer_log['raw_fallback']['when'] ?? '?',
				$installer_log['raw_fallback']['resources_error'] ?? '(ninguno — debería estar OK)'
			) );
			echo '</pre>';
		}

		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=boletines-resources' ) ) . '">← ' . esc_html__( 'Volver al listado', 'boletines' ) . '</a></p>';
		echo '</div>';
	}

	private function handle_delete(): void {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id > 0 ) {
			Resource::delete( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=boletines-resources&deleted=1' ) );
		exit;
	}

	/**
	 * v1.10.3 — Herramienta de emergencia. Crea las tablas DIRECTAMENTE con CREATE TABLE
	 * sin pasar por dbDelta, y muestra el resultado paso a paso en pantalla.
	 */
	private function force_create_tables(): void {
		global $wpdb;
		$prefix          = $wpdb->prefix . 'boletines_';
		$resources_table = $prefix . 'resources';
		$tokens_table    = $prefix . 'resource_tokens';
		$charset         = $wpdb->get_charset_collate();

		echo '<div class="wrap"><h1>' . esc_html__( 'Diagnóstico: creación forzada de tablas', 'boletines' ) . '</h1>';

		// Info entorno
		echo '<h2>1. Entorno</h2>';
		echo '<pre style="background:#f3f4f6;padding:10px;border-radius:4px;font-size:12px;">';
		echo 'MySQL version: ' . esc_html( $wpdb->db_version() ) . "\n";
		echo 'Charset: ' . esc_html( $charset ) . "\n";
		echo 'Prefix: ' . esc_html( $wpdb->prefix ) . "\n";
		echo 'Tabla resources esperada: ' . esc_html( $resources_table ) . "\n";
		echo 'Tabla tokens esperada: ' . esc_html( $tokens_table ) . "\n";
		echo '</pre>';

		// Comprobar si las tablas existen ahora
		echo '<h2>2. Estado actual</h2>';
		$exists_r = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $resources_table ) );
		$exists_t = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tokens_table ) );
		echo '<p>resources: ' . ( $exists_r ? '✅ existe' : '❌ no existe' ) . '</p>';
		echo '<p>tokens: ' . ( $exists_t ? '✅ existe' : '❌ no existe' ) . '</p>';

		// Permisos del usuario MySQL
		echo '<h2>3. Permisos MySQL del usuario</h2>';
		$grants = $wpdb->get_results( 'SHOW GRANTS FOR CURRENT_USER()', ARRAY_N );
		echo '<pre style="background:#f3f4f6;padding:10px;border-radius:4px;font-size:11px;overflow:auto;">';
		if ( $grants ) {
			foreach ( $grants as $g ) echo esc_html( $g[0] ) . "\n";
		} else {
			echo '(no se pudo leer grants — ' . esc_html( $wpdb->last_error ) . ')';
		}
		echo '</pre>';

		// Intento 1: CREATE plano sin charset
		echo '<h2>4. Intento de creación (mínimo, sin charset)</h2>';
		$wpdb->suppress_errors( true );
		$sql_min = "CREATE TABLE IF NOT EXISTS `{$resources_table}` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`title` VARCHAR(255) NOT NULL,
			`slug` VARCHAR(191) NOT NULL DEFAULT '',
			`description` TEXT,
			`file_type` VARCHAR(20) NOT NULL DEFAULT 'attachment',
			`file_attachment_id` BIGINT UNSIGNED DEFAULT NULL,
			`file_external_url` TEXT,
			`cover_image_id` BIGINT UNSIGNED DEFAULT NULL,
			`list_ids` TEXT,
			`button_text` VARCHAR(120) DEFAULT NULL,
			`success_message` TEXT,
			`email_subject` VARCHAR(255) DEFAULT NULL,
			`email_body` LONGTEXT,
			`token_ttl_days` INT UNSIGNED NOT NULL DEFAULT 7,
			`status` VARCHAR(20) NOT NULL DEFAULT 'active',
			`downloads_count` INT UNSIGNED NOT NULL DEFAULT 0,
			`submissions_count` INT UNSIGNED NOT NULL DEFAULT 0,
			`created_at` DATETIME NOT NULL,
			`updated_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_slug` (`slug`),
			KEY `idx_status` (`status`)
		)";
		$result_r = $wpdb->query( $sql_min );
		echo '<p>resources: query devolvió <code>' . esc_html( var_export( $result_r, true ) ) . '</code></p>';
		if ( $wpdb->last_error ) {
			echo '<pre style="background:#fef2f2;color:#7f1d1d;padding:10px;border-radius:4px;font-size:11px;">' . esc_html( $wpdb->last_error ) . '</pre>';
		} else {
			echo '<p style="color:#059669;">✅ Sin errores en resources</p>';
		}

		$sql_min_t = "CREATE TABLE IF NOT EXISTS `{$tokens_table}` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`token` VARCHAR(64) NOT NULL,
			`resource_id` BIGINT UNSIGNED NOT NULL,
			`subscriber_id` BIGINT UNSIGNED DEFAULT NULL,
			`email` VARCHAR(191) NOT NULL,
			`created_at` DATETIME NOT NULL,
			`expires_at` DATETIME NOT NULL,
			`downloaded_at` DATETIME DEFAULT NULL,
			`download_count` INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_token` (`token`),
			KEY `idx_resource` (`resource_id`),
			KEY `idx_email` (`email`)
		)";
		$result_t = $wpdb->query( $sql_min_t );
		echo '<p>tokens: query devolvió <code>' . esc_html( var_export( $result_t, true ) ) . '</code></p>';
		if ( $wpdb->last_error ) {
			echo '<pre style="background:#fef2f2;color:#7f1d1d;padding:10px;border-radius:4px;font-size:11px;">' . esc_html( $wpdb->last_error ) . '</pre>';
		} else {
			echo '<p style="color:#059669;">✅ Sin errores en tokens</p>';
		}
		$wpdb->suppress_errors( false );

		// Re-verificar
		echo '<h2>5. Verificación final</h2>';
		$exists_r2 = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $resources_table ) );
		$exists_t2 = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tokens_table ) );
		echo '<p>resources: ' . ( $exists_r2 ? '✅ existe' : '❌ NO existe' ) . '</p>';
		echo '<p>tokens: ' . ( $exists_t2 ? '✅ existe' : '❌ NO existe' ) . '</p>';

		if ( $exists_r2 && $exists_t2 ) {
			echo '<div class="notice notice-success" style="margin:20px 0;"><p><strong>' . esc_html__( '¡Tablas creadas correctamente!', 'boletines' ) . '</strong> '
				. '<a href="' . esc_url( admin_url( 'admin.php?page=boletines-resources&action=new' ) ) . '" class="button button-primary">'
				. esc_html__( 'Crear mi primer recurso →', 'boletines' )
				. '</a></p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Las tablas siguen sin crearse. Copia toda esta pantalla y compártela para diagnosticar.', 'boletines' ) . '</p></div>';
		}

		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=boletines-resources' ) ) . '">← ' . esc_html__( 'Volver', 'boletines' ) . '</a></p>';
		echo '</div>';
	}
}
