<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\Campaign;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vista de reportes por campaña: KPIs, curva de aperturas/clics por hora,
 * top enlaces clicados — todo con SVG inline (sin dependencias externas).
 */
class Reports {

	public function render(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! $id ) {
			$this->render_index();
			return;
		}

		$c = Campaign::find( $id );
		if ( ! $c ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Campaña no encontrada.', 'boletines' ) . '</p></div>';
			return;
		}

		$this->render_campaign_report( $c );
	}

	private function render_index(): void {
		$result = Campaign::paginate( array( 'status' => 'sent', 'per_page' => 30, 'page' => 1 ) );
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header"><h1><?php esc_html_e( 'Reportes', 'boletines' ); ?></h1></div>
			<p style="color:#6b7280;margin-bottom:16px;"><?php esc_html_e( 'Selecciona una campaña enviada para ver su reporte detallado.', 'boletines' ); ?></p>

			<table class="bol-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaña', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Enviada', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Destinatarios', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Open rate', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Click rate', 'boletines' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="6" style="text-align:center;color:#6b7280;padding:40px;"><?php esc_html_e( 'Aún no has enviado campañas.', 'boletines' ); ?></td></tr>
					<?php else : foreach ( $result['items'] as $c ) :
						$or = $c['total_sent'] > 0 ? round( $c['total_opens'] * 100 / $c['total_sent'], 1 ) : 0;
						$cr = $c['total_sent'] > 0 ? round( $c['total_clicks'] * 100 / $c['total_sent'], 1 ) : 0;
					?>
						<tr>
							<td><strong><?php echo esc_html( $c['subject'] ); ?></strong></td>
							<td><?php echo esc_html( $c['sent_at'] ? wp_date( 'd/m/Y', strtotime( $c['sent_at'] ) ) : '—' ); ?></td>
							<td><?php echo number_format_i18n( (int) $c['total_sent'] ); ?></td>
							<td><strong><?php echo $or; ?>%</strong></td>
							<td><strong><?php echo $cr; ?>%</strong></td>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-reports&id=' . (int) $c['id'] ) ); ?>" class="bol-btn bol-btn-secondary" style="font-size:12px;padding:4px 10px;"><?php esc_html_e( 'Ver reporte', 'boletines' ); ?></a></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_campaign_report( array $c ): void {
		$stats   = $this->compute_stats( (int) $c['id'] );
		$or      = $c['total_sent'] > 0 ? round( $c['total_opens']  * 100 / $c['total_sent'], 1 ) : 0;
		$cr      = $c['total_sent'] > 0 ? round( $c['total_clicks'] * 100 / $c['total_sent'], 1 ) : 0;
		$ctor    = $c['total_opens'] > 0 ? round( $c['total_clicks'] * 100 / $c['total_opens'], 1 ) : 0;
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Reporte:', 'boletines' ); ?> <?php echo esc_html( $c['subject'] ); ?></h1>
				<div class="actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-reports' ) ); ?>" class="bol-btn bol-btn-secondary">← <?php esc_html_e( 'Volver', 'boletines' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-campaigns&action=edit&id=' . (int) $c['id'] ) ); ?>" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Editar campaña', 'boletines' ); ?></a>
				</div>
			</div>

			<div class="bol-kpis">
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Enviados', 'boletines' ); ?></div><div class="value"><?php echo number_format_i18n( (int) $c['total_sent'] ); ?></div><div class="sub"><?php echo number_format_i18n( (int) $c['total_recipients'] ); ?> <?php esc_html_e( 'destinatarios', 'boletines' ); ?></div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Aperturas únicas', 'boletines' ); ?></div><div class="value green"><?php echo number_format_i18n( (int) $c['total_opens'] ); ?></div><div class="sub"><?php echo $or; ?>% open rate</div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Clics totales', 'boletines' ); ?></div><div class="value"><?php echo number_format_i18n( (int) $c['total_clicks'] ); ?></div><div class="sub"><?php echo $cr; ?>% click rate</div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'CTOR', 'boletines' ); ?></div><div class="value"><?php echo $ctor; ?>%</div><div class="sub"><?php esc_html_e( 'Click-to-open', 'boletines' ); ?></div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Bounced', 'boletines' ); ?></div><div class="value <?php echo $stats['bounced'] > 0 ? 'red' : ''; ?>"><?php echo number_format_i18n( $stats['bounced'] ); ?></div></div>
				<div class="bol-kpi"><div class="label"><?php esc_html_e( 'Bajas tras envío', 'boletines' ); ?></div><div class="value"><?php echo number_format_i18n( $stats['unsubs_after'] ); ?></div></div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Aperturas y clics por hora', 'boletines' ); ?></h2>
				<?php echo $this->render_line_chart( $stats['timeline'] ); // intencional: salida HTML/SVG ?>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Top enlaces más clicados', 'boletines' ); ?></h2>
				<?php if ( empty( $stats['top_links'] ) ) : ?>
					<p style="color:#6b7280;"><?php esc_html_e( 'Aún no hay clics registrados.', 'boletines' ); ?></p>
				<?php else : ?>
					<?php echo $this->render_bar_chart( $stats['top_links'] ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** Calcula los datos necesarios para los gráficos. */
	private function compute_stats( int $campaign_id ): array {
		global $wpdb;
		$ce  = Plugin::table( 'campaign_emails' );
		$cl  = Plugin::table( 'clicks' );
		$sub = Plugin::table( 'subscribers' );

		// Bounced en la cola de esta campaña.
		$bounced = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ce} WHERE campaign_id = %d AND status = 'failed'", $campaign_id ) );

		// Bajas posteriores al envío (suscriptores en cola que ahora están unsubscribed).
		$unsubs_after = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ce} ce INNER JOIN {$sub} s ON s.id = ce.subscriber_id
			 WHERE ce.campaign_id = %d AND s.status = 'unsubscribed' AND ce.sent_at IS NOT NULL AND s.unsubscribed_at >= ce.sent_at",
			$campaign_id
		) );

		// Timeline: aperturas y clics por hora durante 24h tras inicio.
		$first = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(sent_at) FROM {$ce} WHERE campaign_id = %d AND status = 'sent'", $campaign_id ) );
		$timeline = array();
		if ( $first ) {
			$start_ts = strtotime( $first );
			$buckets  = array();
			for ( $h = 0; $h < 24; $h++ ) {
				$buckets[ $h ] = array( 'opens' => 0, 'clicks' => 0 );
			}

			$opens_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT opened_at FROM {$ce} WHERE campaign_id = %d AND opened_at IS NOT NULL", $campaign_id
			), ARRAY_A );
			foreach ( $opens_rows as $r ) {
				$h = (int) floor( ( strtotime( $r['opened_at'] ) - $start_ts ) / HOUR_IN_SECONDS );
				if ( $h >= 0 && $h < 24 ) $buckets[ $h ]['opens']++;
			}
			$click_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT cl.clicked_at FROM {$cl} cl INNER JOIN {$ce} ON {$ce}.id = cl.campaign_email_id WHERE {$ce}.campaign_id = %d", $campaign_id
			), ARRAY_A );
			foreach ( $click_rows as $r ) {
				$h = (int) floor( ( strtotime( $r['clicked_at'] ) - $start_ts ) / HOUR_IN_SECONDS );
				if ( $h >= 0 && $h < 24 ) $buckets[ $h ]['clicks']++;
			}

			foreach ( $buckets as $h => $v ) {
				$timeline[] = array(
					'label'  => $h . 'h',
					'opens'  => $v['opens'],
					'clicks' => $v['clicks'],
				);
			}
		}

		// Top 10 enlaces.
		$top_links = $wpdb->get_results( $wpdb->prepare(
			"SELECT cl.url, COUNT(*) AS c FROM {$cl} cl
			 INNER JOIN {$ce} ON {$ce}.id = cl.campaign_email_id
			 WHERE {$ce}.campaign_id = %d
			 GROUP BY cl.url ORDER BY c DESC LIMIT 10",
			$campaign_id
		), ARRAY_A );

		return array(
			'bounced'      => $bounced,
			'unsubs_after' => $unsubs_after,
			'timeline'     => $timeline,
			'top_links'    => $top_links,
		);
	}

	/** Gráfico de líneas SVG (aperturas + clics por hora). */
	private function render_line_chart( array $timeline ): string {
		if ( empty( $timeline ) ) {
			return '<p style="color:#6b7280;">' . esc_html__( 'Aún no hay datos. Vuelve cuando los suscriptores empiecen a abrir.', 'boletines' ) . '</p>';
		}

		$w = 800; $h = 240; $pad_l = 36; $pad_r = 12; $pad_t = 12; $pad_b = 32;
		$plot_w = $w - $pad_l - $pad_r;
		$plot_h = $h - $pad_t - $pad_b;

		$max = 1;
		foreach ( $timeline as $t ) {
			$max = max( $max, $t['opens'], $t['clicks'] );
		}

		$count = count( $timeline );
		$step  = $count > 1 ? $plot_w / ( $count - 1 ) : $plot_w;

		$pts_o = array();
		$pts_c = array();
		foreach ( $timeline as $i => $t ) {
			$x = $pad_l + $i * $step;
			$y_o = $pad_t + $plot_h - ( $t['opens']  / $max ) * $plot_h;
			$y_c = $pad_t + $plot_h - ( $t['clicks'] / $max ) * $plot_h;
			$pts_o[] = round( $x, 1 ) . ',' . round( $y_o, 1 );
			$pts_c[] = round( $x, 1 ) . ',' . round( $y_c, 1 );
		}

		// Eje Y: 5 marcas.
		$grid = '';
		for ( $g = 0; $g <= 4; $g++ ) {
			$y = $pad_t + ( $plot_h / 4 ) * $g;
			$v = (int) round( $max - ( $max / 4 ) * $g );
			$grid .= sprintf( '<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#f3f4f6" stroke-width="1" />', $pad_l, $y, $w - $pad_r, $y );
			$grid .= sprintf( '<text x="%d" y="%.1f" font-size="10" fill="#9ca3af" text-anchor="end">%d</text>', $pad_l - 4, $y + 3, $v );
		}

		// Etiquetas eje X (cada 4h).
		$labels = '';
		foreach ( $timeline as $i => $t ) {
			if ( $i % 4 !== 0 ) continue;
			$x = $pad_l + $i * $step;
			$labels .= sprintf( '<text x="%.1f" y="%d" font-size="10" fill="#9ca3af" text-anchor="middle">%s</text>', $x, $h - 12, esc_html( $t['label'] ) );
		}

		$out = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" style="width:100%;height:auto;font-family:-apple-system,Segoe UI,Roboto,sans-serif;" role="img" aria-label="Aperturas y clics por hora">';
		$out .= $grid;
		$out .= '<polyline fill="none" stroke="#16a34a" stroke-width="2" points="' . implode( ' ', $pts_o ) . '" />';
		$out .= '<polyline fill="none" stroke="#2563eb" stroke-width="2" points="' . implode( ' ', $pts_c ) . '" />';
		// Puntos
		foreach ( $timeline as $i => $t ) {
			$x = $pad_l + $i * $step;
			$y_o = $pad_t + $plot_h - ( $t['opens']  / $max ) * $plot_h;
			$y_c = $pad_t + $plot_h - ( $t['clicks'] / $max ) * $plot_h;
			if ( $t['opens']  > 0 ) $out .= sprintf( '<circle cx="%.1f" cy="%.1f" r="2.5" fill="#16a34a" />', $x, $y_o );
			if ( $t['clicks'] > 0 ) $out .= sprintf( '<circle cx="%.1f" cy="%.1f" r="2.5" fill="#2563eb" />', $x, $y_c );
		}
		$out .= $labels;
		$out .= '</svg>';

		// Leyenda
		$out .= '<div style="display:flex;gap:16px;margin-top:8px;font-size:13px;">';
		$out .= '<span style="display:inline-flex;align-items:center;gap:6px;"><span style="width:12px;height:3px;background:#16a34a;display:inline-block;border-radius:2px;"></span>' . esc_html__( 'Aperturas', 'boletines' ) . '</span>';
		$out .= '<span style="display:inline-flex;align-items:center;gap:6px;"><span style="width:12px;height:3px;background:#2563eb;display:inline-block;border-radius:2px;"></span>' . esc_html__( 'Clics', 'boletines' ) . '</span>';
		$out .= '</div>';

		return $out;
	}

	/** Gráfico de barras horizontales (top enlaces). */
	private function render_bar_chart( array $rows ): string {
		$max = 1;
		foreach ( $rows as $r ) $max = max( $max, (int) $r['c'] );

		$out = '<div style="display:flex;flex-direction:column;gap:8px;">';
		foreach ( $rows as $r ) {
			$pct  = round( ( (int) $r['c'] / $max ) * 100 );
			$display = strlen( $r['url'] ) > 60 ? mb_substr( $r['url'], 0, 60 ) . '…' : $r['url'];
			$out .= '<div>';
			$out .= '<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px;color:#374151;">';
			$out .= '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:80%;"><a href="' . esc_url( $r['url'] ) . '" target="_blank" style="color:#374151;text-decoration:none;">' . esc_html( $display ) . '</a></span>';
			$out .= '<strong>' . (int) $r['c'] . '</strong></div>';
			$out .= '<div style="background:#f3f4f6;height:8px;border-radius:4px;overflow:hidden;"><div style="background:#2563eb;height:100%;width:' . $pct . '%;"></div></div>';
			$out .= '</div>';
		}
		$out .= '</div>';
		return $out;
	}
}
