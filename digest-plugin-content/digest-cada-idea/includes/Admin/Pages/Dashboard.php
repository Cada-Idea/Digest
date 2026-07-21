<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\Campaign;
use Boletines\Models\ListModel;
use Boletines\Models\Subscriber;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard {

	public function render(): void {
		global $wpdb;

		$counts        = Subscriber::counts_by_status();
		$total         = array_sum( $counts );
		$lists         = ListModel::all();
		$last_campaigns = Campaign::paginate( array( 'per_page' => 5, 'page' => 1 ) );

		$sent_24h = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Plugin::table( 'campaign_emails' ) . " WHERE status = 'sent' AND sent_at >= %s",
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);
		$queued = (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . Plugin::table( 'campaign_emails' ) . " WHERE status = 'queued'"
		);

		$next_cron = wp_next_scheduled( 'boletines_cron_send' );
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Boletines — Resumen', 'boletines' ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-campaigns&action=new' ) ); ?>" class="bol-btn">+ <?php esc_html_e( 'Nueva campaña', 'boletines' ); ?></a>
				</div>
			</div>

			<div class="bol-kpis">
				<div class="bol-kpi">
					<div class="label"><?php esc_html_e( 'Suscriptores totales', 'boletines' ); ?></div>
					<div class="value"><?php echo number_format_i18n( $total ); ?></div>
				</div>
				<div class="bol-kpi">
					<div class="label"><?php esc_html_e( 'Confirmados', 'boletines' ); ?></div>
					<div class="value green"><?php echo number_format_i18n( $counts[ Subscriber::STATUS_CONFIRMED ] ); ?></div>
				</div>
				<div class="bol-kpi">
					<div class="label"><?php esc_html_e( 'Pendientes', 'boletines' ); ?></div>
					<div class="value amber"><?php echo number_format_i18n( $counts[ Subscriber::STATUS_PENDING ] ); ?></div>
				</div>
				<div class="bol-kpi">
					<div class="label"><?php esc_html_e( 'Bajas', 'boletines' ); ?></div>
					<div class="value"><?php echo number_format_i18n( $counts[ Subscriber::STATUS_UNSUBSCRIBED ] ); ?></div>
				</div>
				<div class="bol-kpi">
					<div class="label"><?php esc_html_e( 'Enviados últimas 24h', 'boletines' ); ?></div>
					<div class="value"><?php echo number_format_i18n( $sent_24h ); ?></div>
				</div>
				<div class="bol-kpi">
					<div class="label"><?php esc_html_e( 'En cola', 'boletines' ); ?></div>
					<div class="value <?php echo $queued > 0 ? 'amber' : ''; ?>"><?php echo number_format_i18n( $queued ); ?></div>
					<div class="sub"><?php
						if ( $next_cron ) {
							/* translators: %s = relative time */
							printf( esc_html__( 'Próximo cron: %s', 'boletines' ), esc_html( human_time_diff( time(), $next_cron ) ) );
						}
					?></div>
				</div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Últimas campañas', 'boletines' ); ?></h2>
				<?php if ( empty( $last_campaigns['items'] ) ) : ?>
					<p><?php esc_html_e( 'Aún no hay campañas. Crea la primera para arrancar.', 'boletines' ); ?></p>
				<?php else : ?>
					<table class="bol-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Asunto', 'boletines' ); ?></th>
								<th><?php esc_html_e( 'Estado', 'boletines' ); ?></th>
								<th><?php esc_html_e( 'Destinatarios', 'boletines' ); ?></th>
								<th><?php esc_html_e( 'Enviados', 'boletines' ); ?></th>
								<th><?php esc_html_e( 'Aperturas', 'boletines' ); ?></th>
								<th><?php esc_html_e( 'Clics', 'boletines' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $last_campaigns['items'] as $c ) : ?>
								<tr>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-campaigns&action=edit&id=' . (int) $c['id'] ) ); ?>">
											<?php echo esc_html( $c['subject'] ); ?>
										</a>
									</td>
									<td><span class="bol-badge <?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $c['status'] ); ?></span></td>
									<td><?php echo number_format_i18n( (int) $c['total_recipients'] ); ?></td>
									<td><?php echo number_format_i18n( (int) $c['total_sent'] ); ?></td>
									<td><?php echo number_format_i18n( (int) $c['total_opens'] ); ?></td>
									<td><?php echo number_format_i18n( (int) $c['total_clicks'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Tus listas', 'boletines' ); ?></h2>
				<table class="bol-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Lista', 'boletines' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'boletines' ); ?></th>
							<th><?php esc_html_e( 'Confirmados', 'boletines' ); ?></th>
							<th><?php esc_html_e( 'Shortcode', 'boletines' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $lists as $l ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-lists&action=edit&id=' . (int) $l['id'] ) ); ?>"><?php echo esc_html( $l['name'] ); ?></a></td>
								<td><code><?php echo esc_html( $l['slug'] ); ?></code></td>
								<td><?php echo number_format_i18n( ListModel::subscriber_count( (int) $l['id'] ) ); ?></td>
								<td><code>[boletines_form list="<?php echo (int) $l['id']; ?>"]</code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Diagnóstico rápido', 'boletines' ); ?></h2>
				<ul style="margin:0;padding-left:20px;line-height:1.8;">
					<li>
						<?php esc_html_e( 'Cron de WordPress:', 'boletines' ); ?>
						<?php echo defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON
							? '<strong style="color:#dc2626;">' . esc_html__( 'Desactivado (DISABLE_WP_CRON)', 'boletines' ) . '</strong> — ' . esc_html__( 'configura un cron real del servidor.', 'boletines' )
							: '<strong style="color:#16a34a;">' . esc_html__( 'Activo', 'boletines' ) . '</strong>'; ?>
					</li>
					<li>
						<?php esc_html_e( 'Próxima ejecución de envíos:', 'boletines' ); ?>
						<strong><?php echo $next_cron ? esc_html( wp_date( 'd/m/Y H:i', $next_cron ) ) : esc_html__( 'no programada', 'boletines' ); ?></strong>
					</li>
					<li>
						<?php esc_html_e( 'Velocidad configurada:', 'boletines' ); ?>
						<strong><?php echo number_format_i18n( (int) Plugin::get_setting( 'speed_per_hour', 240 ) ); ?> <?php esc_html_e( 'correos/hora', 'boletines' ); ?></strong>
					</li>
					<li>
						<?php esc_html_e( 'SMTP:', 'boletines' ); ?>
						<?php
						if ( ! function_exists( 'is_plugin_active' ) ) {
							require_once ABSPATH . 'wp-admin/includes/plugin.php';
						}
						$smtp_plugins = array(
							'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
							'fluent-smtp/fluent-smtp.php'   => 'FluentSMTP',
							'post-smtp/postman-smtp.php'    => 'Post SMTP',
						);
						$active = '';
						foreach ( $smtp_plugins as $file => $name ) {
							if ( is_plugin_active( $file ) ) { $active = $name; break; }
						}
						echo $active
							? '<strong style="color:#16a34a;">' . esc_html( $active ) . '</strong>'
							: '<strong style="color:#d97706;">' . esc_html__( 'Ninguno detectado', 'boletines' ) . '</strong> — ' . esc_html__( 'instala FluentSMTP para mejor entregabilidad.', 'boletines' );
						?>
					</li>
				</ul>
			</div>
		</div>
		<?php
	}
}
