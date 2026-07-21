<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\ListModel;
use Boletines\Models\Subscriber;
use Boletines\Tools\Csv;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tools {

	public function render(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'import';
		$lists = ListModel::all();
		$result = get_transient( 'boletines_import_result_' . get_current_user_id() );
		if ( $result ) delete_transient( 'boletines_import_result_' . get_current_user_id() );
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header"><h1><?php esc_html_e( 'Herramientas', 'boletines' ); ?></h1></div>

			<div class="bol-tabs">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-tools&tab=import' ) ); ?>" class="<?php echo $tab === 'import' ? 'active' : ''; ?>"><?php esc_html_e( 'Importar CSV', 'boletines' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-tools&tab=export' ) ); ?>" class="<?php echo $tab === 'export' ? 'active' : ''; ?>"><?php esc_html_e( 'Exportar CSV', 'boletines' ); ?></a>
			</div>

			<?php if ( $result ) : ?>
				<div class="bol-notice <?php echo empty( $result['errors'] ) ? 'success' : 'warning'; ?>">
					<strong><?php esc_html_e( 'Importación finalizada.', 'boletines' ); ?></strong><br>
					<?php
					printf(
						esc_html__( 'Nuevos: %1$d · Actualizados: %2$d · Omitidos: %3$d', 'boletines' ),
						(int) $result['imported'], (int) $result['updated'], (int) $result['skipped']
					);
					if ( ! empty( $result['errors'] ) ) {
						echo '<details style="margin-top:8px;"><summary>' . esc_html__( 'Detalles', 'boletines' ) . '</summary><ul>';
						foreach ( $result['errors'] as $e ) echo '<li>' . esc_html( $e ) . '</li>';
						echo '</ul></details>';
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $tab === 'import' ) : ?>
				<div class="bol-card">
					<h2><?php esc_html_e( 'Importar suscriptores desde CSV', 'boletines' ); ?></h2>
					<p style="color:#6b7280;"><?php esc_html_e( 'Sube un CSV. Detectamos automáticamente el delimitador (coma, punto y coma o tab) y la cabecera.', 'boletines' ); ?></p>
					<p style="color:#6b7280;font-size:13px;line-height:1.6;">
						<strong><?php esc_html_e( 'Columnas reconocidas', 'boletines' ); ?>:</strong>
						<code>email</code> (obligatoria),
						<code>first_name</code> / <code>nombre</code>,
						<code>last_name</code> / <code>apellido</code>,
						<code>status</code>,
						<code>birthday</code> / <code>cumpleaños</code> (YYYY-MM-DD o DD/MM/YYYY).
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="bol-form">
						<input type="hidden" name="action" value="boletines_import_csv">
						<?php wp_nonce_field( 'boletines_import_csv' ); ?>

						<div class="row">
							<label><?php esc_html_e( 'Archivo CSV', 'boletines' ); ?> *</label>
							<input type="file" name="csv_file" accept=".csv,text/csv" required>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Estado por defecto', 'boletines' ); ?></label>
							<select name="default_status">
								<option value="confirmed"><?php esc_html_e( 'Confirmado (no envía correo de confirmación)', 'boletines' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pendiente (requerirá confirmación)', 'boletines' ); ?></option>
							</select>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Asignar a listas', 'boletines' ); ?></label>
							<div style="display:flex;flex-wrap:wrap;gap:12px;padding:8px 0;">
								<?php foreach ( $lists as $l ) : ?>
									<label style="display:flex;align-items:center;gap:6px;font-weight:400;">
										<input type="checkbox" name="list_ids[]" value="<?php echo (int) $l['id']; ?>">
										<?php echo esc_html( $l['name'] ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div>
							<button type="submit" class="bol-btn"><?php esc_html_e( 'Importar', 'boletines' ); ?></button>
						</div>
					</form>
				</div>

				<div class="bol-card">
					<h2><?php esc_html_e( 'Ejemplo de CSV', 'boletines' ); ?></h2>
					<pre style="background:#f9fafb;padding:12px;border-radius:6px;font-size:13px;overflow:auto;">email,first_name,last_name,status,birthday
juan@example.com,Juan,Pérez,confirmed,1990-05-12
maria@example.com,María,Gómez,confirmed,1985-11-30
test@example.com,Test,,pending,</pre>
					<p style="color:#9ca3af;font-size:12px;line-height:1.5;margin-top:8px;">
						<?php esc_html_e( 'La fecha acepta YYYY-MM-DD o DD/MM/YYYY.', 'boletines' ); ?>
					</p>
				</div>

			<?php elseif ( $tab === 'export' ) : ?>
				<div class="bol-card">
					<h2><?php esc_html_e( 'Exportar suscriptores a CSV', 'boletines' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
						<input type="hidden" name="action" value="boletines_export_csv">
						<?php wp_nonce_field( 'boletines_export_csv' ); ?>

						<div class="row row-2">
							<div class="row">
								<label><?php esc_html_e( 'Estado', 'boletines' ); ?></label>
								<select name="status">
									<option value=""><?php esc_html_e( 'Todos', 'boletines' ); ?></option>
									<?php foreach ( array(
										Subscriber::STATUS_CONFIRMED    => __( 'Confirmados', 'boletines' ),
										Subscriber::STATUS_PENDING      => __( 'Pendientes', 'boletines' ),
										Subscriber::STATUS_UNSUBSCRIBED => __( 'Dados de baja', 'boletines' ),
										Subscriber::STATUS_BOUNCED      => __( 'Bounced', 'boletines' ),
									) as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="row">
								<label><?php esc_html_e( 'Lista', 'boletines' ); ?></label>
								<select name="list_id">
									<option value="0"><?php esc_html_e( 'Todas las listas', 'boletines' ); ?></option>
									<?php foreach ( $lists as $l ) : ?>
										<option value="<?php echo (int) $l['id']; ?>"><?php echo esc_html( $l['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div>
							<button type="submit" class="bol-btn"><?php esc_html_e( 'Descargar CSV', 'boletines' ); ?></button>
						</div>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		check_admin_referer( 'boletines_import_csv' );

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=boletines-tools&tab=import' ) );
			exit;
		}

		$list_ids = isset( $_POST['list_ids'] ) ? array_map( 'intval', (array) $_POST['list_ids'] ) : array();
		$status   = isset( $_POST['default_status'] ) && in_array( $_POST['default_status'], array( 'confirmed', 'pending' ), true )
			? sanitize_text_field( wp_unslash( $_POST['default_status'] ) )
			: 'confirmed';

		$result = Csv::import( $_FILES['csv_file']['tmp_name'], $list_ids, array(), $status );
		set_transient( 'boletines_import_result_' . get_current_user_id(), $result, 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-tools&tab=import' ) );
		exit;
	}

	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		check_admin_referer( 'boletines_export_csv' );

		Csv::export( array(
			'status'  => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			'list_id' => isset( $_POST['list_id'] ) ? (int) $_POST['list_id'] : 0,
		) );
	}
}
