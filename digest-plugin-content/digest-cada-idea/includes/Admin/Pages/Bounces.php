<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\Bounce;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bounces {

	public function render(): void {
		$args = array(
			'type'     => isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '',
			'per_page' => 30,
			'page'     => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
		);
		$result = Bounce::paginate( $args );
		$counts = Bounce::counts_by_type();
		$secret = (string) Plugin::get_setting( 'webhook_secret' );
		$endpoint_url = rest_url( 'boletines/v1/webhook/bounce' );
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header"><h1><?php esc_html_e( 'Bounces y quejas', 'boletines' ); ?></h1></div>

			<div class="bol-kpis">
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Hard bounces', 'boletines' ); ?></div><div class="value red"><?php echo number_format_i18n( $counts[ Bounce::TYPE_HARD ] ); ?></div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Soft bounces', 'boletines' ); ?></div><div class="value amber"><?php echo number_format_i18n( $counts[ Bounce::TYPE_SOFT ] ); ?></div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Quejas (spam)', 'boletines' ); ?></div><div class="value red"><?php echo number_format_i18n( $counts[ Bounce::TYPE_COMPLAINT ] ); ?></div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Bloqueos', 'boletines' ); ?></div><div class="value"><?php echo number_format_i18n( $counts[ Bounce::TYPE_BLOCK ] ); ?></div></div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Configurar webhook', 'boletines' ); ?></h2>
				<p style="color:#6b7280;"><?php esc_html_e( 'Configura tu proveedor SMTP para que envíe los bounces aquí. La detección automática es lo que mantiene tu lista limpia y tu reputación intacta.', 'boletines' ); ?></p>

				<div class="row">
					<label><?php esc_html_e( 'URL del webhook', 'boletines' ); ?></label>
					<input type="text" readonly value="<?php echo esc_attr( $endpoint_url . '?secret=' . rawurlencode( $secret ) ); ?>" onclick="this.select();" style="font-family:monospace;font-size:12px;">
					<span class="help"><?php esc_html_e( 'Pégala en tu proveedor. El secret va en la URL como parámetro o en la cabecera "X-Boletines-Secret".', 'boletines' ); ?></span>
				</div>

				<div class="row" style="margin-top:14px;">
					<label><?php esc_html_e( 'Secret (regenerar invalida la URL anterior)', 'boletines' ); ?></label>
					<div style="display:flex;gap:8px;align-items:center;">
						<input type="text" readonly value="<?php echo esc_attr( $secret ); ?>" style="font-family:monospace;font-size:12px;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
							<input type="hidden" name="action" value="boletines_regen_webhook">
							<?php wp_nonce_field( 'boletines_regen_webhook' ); ?>
							<button type="submit" class="bol-btn bol-btn-secondary" onclick="return confirm('<?php echo esc_js( __( '¿Regenerar el secret? La URL actual dejará de funcionar.', 'boletines' ) ); ?>');"><?php esc_html_e( 'Regenerar', 'boletines' ); ?></button>
						</form>
					</div>
				</div>

				<details style="margin-top:14px;">
					<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Cómo configurarlo en cada proveedor', 'boletines' ); ?></summary>
					<div style="padding:12px 0;line-height:1.7;color:#374151;">
						<p><strong>Amazon SES + SNS:</strong> <?php esc_html_e( 'Crea un topic en SNS, configúralo como destino de bounces/complaints en SES, y suscribe la URL de arriba como tipo "HTTPS". El plugin detecta y procesa automáticamente las notificaciones de SNS.', 'boletines' ); ?></p>
						<p><strong>FluentSMTP:</strong> <?php esc_html_e( 'Si usas SES o Mailgun, configura el webhook directamente en la consola del proveedor (no requiere código adicional desde FluentSMTP).', 'boletines' ); ?></p>
						<p><strong>Mailgun:</strong> <?php esc_html_e( 'En Mailgun → Sending → Webhooks: añade la URL de arriba para los eventos "Permanent Failure" y "Spam Complaints". Mailgun envía formato JSON simple — funciona out-of-the-box.', 'boletines' ); ?></p>
						<p><strong>Postmark / SendGrid / Brevo:</strong> <?php esc_html_e( 'Misma idea: en su sección de Webhooks, configurar la URL de arriba como destino de eventos de bounce y complaint.', 'boletines' ); ?></p>
					</div>
				</details>
			</div>

			<div class="bol-card">
				<form method="get" class="bol-filters" style="margin-bottom:16px;">
					<input type="hidden" name="page" value="boletines-bounces">
					<select name="type">
						<option value=""><?php esc_html_e( 'Todos los tipos', 'boletines' ); ?></option>
						<option value="<?php echo esc_attr( Bounce::TYPE_HARD ); ?>"      <?php selected( $args['type'], Bounce::TYPE_HARD ); ?>>Hard</option>
						<option value="<?php echo esc_attr( Bounce::TYPE_SOFT ); ?>"      <?php selected( $args['type'], Bounce::TYPE_SOFT ); ?>>Soft</option>
						<option value="<?php echo esc_attr( Bounce::TYPE_COMPLAINT ); ?>" <?php selected( $args['type'], Bounce::TYPE_COMPLAINT ); ?>>Complaint</option>
						<option value="<?php echo esc_attr( Bounce::TYPE_BLOCK ); ?>"     <?php selected( $args['type'], Bounce::TYPE_BLOCK ); ?>>Block</option>
					</select>
					<button type="submit" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Filtrar', 'boletines' ); ?></button>
				</form>

				<table class="bol-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Email', 'boletines' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'boletines' ); ?></th>
							<th><?php esc_html_e( 'Razón', 'boletines' ); ?></th>
							<th><?php esc_html_e( 'Origen', 'boletines' ); ?></th>
							<th><?php esc_html_e( 'Fecha', 'boletines' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $result['items'] ) ) : ?>
							<tr><td colspan="5" style="text-align:center;color:#6b7280;padding:40px;"><?php esc_html_e( 'Sin bounces registrados todavía. Buena señal 👌', 'boletines' ); ?></td></tr>
						<?php else : foreach ( $result['items'] as $b ) : ?>
							<tr>
								<td><?php echo esc_html( $b['email'] ); ?></td>
								<td><span class="bol-badge <?php echo $b['type'] === 'hard' || $b['type'] === 'complaint' ? 'bounced' : 'pending'; ?>"><?php echo esc_html( $b['type'] ); ?></span></td>
								<td style="font-size:12px;color:#6b7280;max-width:400px;"><?php echo esc_html( $b['reason'] ?: '—' ); ?></td>
								<td><?php echo esc_html( $b['source'] ?: '—' ); ?></td>
								<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $b['created_at'] ) ) ); ?></td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public function handle_regen(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		check_admin_referer( 'boletines_regen_webhook' );

		Plugin::update_setting( 'webhook_secret', wp_generate_password( 32, false, false ) );
		wp_safe_redirect( admin_url( 'admin.php?page=boletines-bounces' ) );
		exit;
	}
}
