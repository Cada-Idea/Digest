<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\ListModel;
use Boletines\Models\Subscriber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Subscribers {

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
			'list_id'  => isset( $_GET['list'] ) ? (int) $_GET['list'] : 0,
			'per_page' => 25,
			'page'     => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);

		$result = Subscriber::paginate( $args );
		$lists  = ListModel::all();
		$saved  = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0;
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Suscriptores', 'boletines' ); ?> <span style="color:#9ca3af;font-weight:400;font-size:16px;">(<?php echo number_format_i18n( $result['total'] ); ?>)</span></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-tools&tab=import' ) ); ?>" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Importar', 'boletines' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-tools&tab=export' ) ); ?>" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Exportar', 'boletines' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-subscribers&action=new' ) ); ?>" class="bol-btn">+ <?php esc_html_e( 'Añadir', 'boletines' ); ?></a>
				</div>
			</div>

			<?php if ( $saved ) : ?>
				<div class="bol-notice success"><?php esc_html_e( 'Suscriptor guardado.', 'boletines' ); ?></div>
			<?php endif; ?>

			<form method="get" class="bol-filters">
				<input type="hidden" name="page" value="boletines-subscribers">
				<input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre o email...', 'boletines' ); ?>">
				<select name="status">
					<option value=""><?php esc_html_e( 'Todos los estados', 'boletines' ); ?></option>
					<?php foreach ( array(
						Subscriber::STATUS_CONFIRMED    => __( 'Confirmado', 'boletines' ),
						Subscriber::STATUS_PENDING      => __( 'Pendiente', 'boletines' ),
						Subscriber::STATUS_UNSUBSCRIBED => __( 'Dado de baja', 'boletines' ),
						Subscriber::STATUS_BOUNCED      => __( 'Bounced', 'boletines' ),
					) as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $args['status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="list">
					<option value="0"><?php esc_html_e( 'Todas las listas', 'boletines' ); ?></option>
					<?php foreach ( $lists as $l ) : ?>
						<option value="<?php echo (int) $l['id']; ?>" <?php selected( $args['list_id'], (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Filtrar', 'boletines' ); ?></button>
			</form>

			<table class="bol-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Nombre', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Origen', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Alta', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'boletines' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="6" style="text-align:center;color:#6b7280;padding:40px;"><?php esc_html_e( 'No hay suscriptores con esos filtros.', 'boletines' ); ?></td></tr>
					<?php else : foreach ( $result['items'] as $s ) : ?>
						<tr>
							<td><?php echo esc_html( $s['email'] ); ?></td>
							<td><?php echo esc_html( trim( ( $s['first_name'] ?: '' ) . ' ' . ( $s['last_name'] ?: '' ) ) ); ?></td>
							<td><span class="bol-badge <?php echo esc_attr( $s['status'] ); ?>"><?php echo esc_html( $s['status'] ); ?></span></td>
							<td><?php echo esc_html( $s['source'] ?: '—' ); ?></td>
							<td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $s['created_at'] ) ) ); ?></td>
							<td class="bol-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-subscribers&action=edit&id=' . (int) $s['id'] ) ); ?>"><?php esc_html_e( 'Editar', 'boletines' ); ?></a>
								<a href="#" class="delete bol-delete-subscriber" data-id="<?php echo (int) $s['id']; ?>"><?php esc_html_e( 'Borrar', 'boletines' ); ?></a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>

			<?php $this->render_pagination( $result, 'boletines-subscribers', $args ); ?>
		</div>
		<?php
	}

	private function render_edit(): void {
		$id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$sub = $id ? Subscriber::find_by_id( $id ) : null;
		$is_new = ! $sub;

		$selected_lists = $sub ? Subscriber::list_ids_for( (int) $sub['id'] ) : array();
		$lists          = ListModel::all();
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php echo $is_new ? esc_html__( 'Nuevo suscriptor', 'boletines' ) : esc_html__( 'Editar suscriptor', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-subscribers' ) ); ?>" class="bol-btn bol-btn-secondary">← <?php esc_html_e( 'Volver', 'boletines' ); ?></a>
				</div>
			</div>

			<div class="bol-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
					<input type="hidden" name="action" value="boletines_save_subscriber">
					<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
					<?php wp_nonce_field( 'boletines_save_subscriber' ); ?>

					<div class="row row-2">
						<div class="row">
							<label><?php esc_html_e( 'Email', 'boletines' ); ?> *</label>
							<input type="email" name="email" required value="<?php echo esc_attr( $sub['email'] ?? '' ); ?>">
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Estado', 'boletines' ); ?></label>
							<select name="status">
								<?php foreach ( array(
									Subscriber::STATUS_CONFIRMED    => __( 'Confirmado', 'boletines' ),
									Subscriber::STATUS_PENDING      => __( 'Pendiente', 'boletines' ),
									Subscriber::STATUS_UNSUBSCRIBED => __( 'Dado de baja', 'boletines' ),
									Subscriber::STATUS_BOUNCED      => __( 'Bounced', 'boletines' ),
								) as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $sub['status'] ?? 'confirmed', $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="row row-2">
						<div class="row">
							<label><?php esc_html_e( 'Nombre', 'boletines' ); ?></label>
							<input type="text" name="first_name" value="<?php echo esc_attr( $sub['first_name'] ?? '' ); ?>">
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Apellidos', 'boletines' ); ?></label>
							<input type="text" name="last_name" value="<?php echo esc_attr( $sub['last_name'] ?? '' ); ?>">
						</div>
					</div>

					<div class="row">
						<label><?php esc_html_e( 'Listas', 'boletines' ); ?></label>
						<div style="display:flex;flex-wrap:wrap;gap:12px;padding:8px 0;">
							<?php foreach ( $lists as $l ) : ?>
								<label style="display:flex;align-items:center;gap:6px;font-weight:400;">
									<input type="checkbox" name="lists[]" value="<?php echo (int) $l['id']; ?>" <?php checked( in_array( (int) $l['id'], $selected_lists, true ) ); ?>>
									<?php echo esc_html( $l['name'] ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div>
						<button type="submit" class="bol-btn"><?php esc_html_e( 'Guardar suscriptor', 'boletines' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		check_admin_referer( 'boletines_save_subscriber' );

		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$lists = isset( $_POST['lists'] ) ? array_map( 'intval', (array) $_POST['lists'] ) : array();

		$data = array(
			'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'first_name' => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
			'last_name'  => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
			'status'     => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'confirmed',
			'source'     => 'manual',
		);

		$sid = Subscriber::upsert( $data );
		if ( $sid ) {
			Subscriber::detach_all_lists( $sid );
			if ( ! empty( $lists ) ) {
				Subscriber::attach_lists( $sid, $lists );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-subscribers&saved=1' ) );
		exit;
	}

	private function render_pagination( array $result, string $page_slug, array $args ): void {
		if ( $result['pages'] <= 1 ) return;
		$base = remove_query_arg( 'paged' );
		echo '<div class="bol-pagination">';
		for ( $i = 1; $i <= $result['pages']; $i++ ) {
			if ( $i == $result['page'] ) {
				echo '<span class="current">' . (int) $i . '</span>';
			} else {
				echo '<a href="' . esc_url( add_query_arg( 'paged', $i, $base ) ) . '">' . (int) $i . '</a>';
			}
		}
		echo '</div>';
	}
}
