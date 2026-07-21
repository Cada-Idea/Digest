<?php
namespace Boletines\Admin\Pages;

use Boletines\Diagnostics\Inconsistency;
use Boletines\Diagnostics\Smtp as SmtpDiag;
use Boletines\Snapshots\Manager as Snapshots;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Página "Salud" — diagnóstico, snapshots, prueba SMTP.
 *
 * Pestañas:
 *   - Diagnóstico: chequeos automáticos (tablas, opciones, crons, SMTP).
 *   - Snapshots: lista de backups internos, crear/restaurar/borrar.
 *   - SMTP: estado del envío + botón de test.
 *   - Sistema: info técnica (versiones, prefijo, hooks).
 */
class Health {

	const SLUG = 'boletines-health';

	public function register(): void {
		add_action( 'admin_post_boletines_health_create_snapshot', array( $this, 'handle_create_snapshot' ) );
		add_action( 'admin_post_boletines_health_restore_snapshot', array( $this, 'handle_restore_snapshot' ) );
		add_action( 'admin_post_boletines_health_delete_snapshot', array( $this, 'handle_delete_snapshot' ) );
		add_action( 'admin_post_boletines_health_smtp_test', array( $this, 'handle_smtp_test' ) );
		add_action( 'admin_post_boletines_health_license_test', array( $this, 'handle_license_test' ) );
		add_action( 'admin_post_boletines_health_retry_install', array( $this, 'handle_retry_install' ) );
		add_action( 'admin_post_boletines_health_link_test', array( $this, 'handle_link_test' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'diagnostico';
		$tabs = array(
			'diagnostico'  => __( 'Diagnóstico', 'boletines' ),
			'snapshots'    => __( 'Snapshots', 'boletines' ),
			'conectividad' => __( 'Conectividad', 'boletines' ),
			'urls'         => __( '🔗 Test de URLs', 'boletines' ),
			'sistema'      => __( 'Sistema', 'boletines' ),
		);
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1>
					<span class="dashicons dashicons-heart" style="margin-right:6px;color:#dc2626;"></span>
					<?php esc_html_e( 'Salud del plugin', 'boletines' ); ?>
				</h1>
			</div>

			<?php $this->maybe_render_notices(); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab_id ) ); ?>"
					   class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div style="margin-top:18px;">
				<?php
				switch ( $tab ) {
					case 'snapshots':    $this->render_snapshots(); break;
					case 'conectividad': $this->render_connectivity(); break;
					case 'urls':         $this->render_urls_test(); break;
					case 'sistema':      $this->render_system(); break;
					case 'diagnostico':
					default:             $this->render_diagnostics(); break;
				}
				?>
			</div>
		</div>
		<?php
	}

	// =====================================================================
	// TAB: Diagnóstico
	// =====================================================================

	private function render_diagnostics(): void {
		$findings = Inconsistency::run_all();

		$criticals = array_filter( $findings, fn( $f ) => $f['level'] === 'critical' );
		$warnings  = array_filter( $findings, fn( $f ) => $f['level'] === 'warning' );

		$total_ok = empty( $findings );
		?>
		<?php if ( $total_ok ) : ?>
			<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:20px;margin-bottom:20px;">
				<h2 style="margin:0;color:#14532d;">✅ <?php esc_html_e( 'Todo en orden', 'boletines' ); ?></h2>
				<p style="margin:6px 0 0;color:#15803d;line-height:1.6;">
					<?php esc_html_e( 'No se detectaron inconsistencias. Tablas, opciones, crons y configuración SMTP están bien.', 'boletines' ); ?>
				</p>
			</div>
		<?php else : ?>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
				<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:16px;">
					<div style="font-size:28px;font-weight:700;color:#7f1d1d;"><?php echo count( $criticals ); ?></div>
					<div style="color:#7f1d1d;font-size:13px;"><?php esc_html_e( 'Críticos', 'boletines' ); ?></div>
				</div>
				<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:16px;">
					<div style="font-size:28px;font-weight:700;color:#92400e;"><?php echo count( $warnings ); ?></div>
					<div style="color:#92400e;font-size:13px;"><?php esc_html_e( 'Avisos', 'boletines' ); ?></div>
				</div>
			</div>
		<?php endif; ?>

		<?php foreach ( $findings as $f ) :
			$bg  = $f['level'] === 'critical' ? '#fee2e2' : ( $f['level'] === 'warning' ? '#fef3c7' : '#e0f2fe' );
			$bd  = $f['level'] === 'critical' ? '#dc2626' : ( $f['level'] === 'warning' ? '#d97706' : '#0284c7' );
			$txt = $f['level'] === 'critical' ? '#7f1d1d' : ( $f['level'] === 'warning' ? '#78350f' : '#0c4a6e' );
		?>
			<div style="background:<?php echo esc_attr( $bg ); ?>;border-left:4px solid <?php echo esc_attr( $bd ); ?>;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
				<h3 style="margin:0 0 6px;color:<?php echo esc_attr( $txt ); ?>;font-size:15px;"><?php echo esc_html( $f['title'] ); ?></h3>
				<p style="margin:0 0 8px;color:<?php echo esc_attr( $txt ); ?>;line-height:1.55;font-size:13.5px;"><?php echo esc_html( $f['description'] ); ?></p>
				<p style="margin:0;color:<?php echo esc_attr( $txt ); ?>;font-size:13px;"><strong><?php esc_html_e( 'Qué hacer:', 'boletines' ); ?></strong> <?php echo esc_html( $f['action'] ); ?></p>
			</div>
		<?php endforeach; ?>

		<div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<input type="hidden" name="action" value="boletines_health_retry_install">
				<?php wp_nonce_field( 'boletines_health_retry_install' ); ?>
				<button type="submit" class="button"
					onclick="return confirm('<?php echo esc_js( __( 'Esto vuelve a ejecutar la creación de tablas y settings por defecto. Es no destructivo (no borra datos), pero igual conviene hacer un snapshot antes. ¿Continuar?', 'boletines' ) ); ?>');">
					<?php esc_html_e( 'Reintentar migración / instalación', 'boletines' ); ?>
				</button>
			</form>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=snapshots' ) ); ?>" class="button" style="margin-left:6px;">
				<?php esc_html_e( 'Crear snapshot ahora', 'boletines' ); ?>
			</a>
		</div>
		<?php
	}

	// =====================================================================
	// TAB: Snapshots
	// =====================================================================

	private function render_snapshots(): void {
		$snapshots = Snapshots::list_snapshots();
		?>
		<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px 20px;margin-bottom:20px;">
			<p style="margin:0;color:#0c4a6e;line-height:1.55;">
				<strong>💾 <?php esc_html_e( 'Snapshots automáticos', 'boletines' ); ?></strong> —
				<?php esc_html_e( 'El plugin guarda automáticamente un volcado SQL de tus tablas críticas antes de cada actualización. Aquí puedes verlos, descargarlos o restaurarlos. Se conservan los últimos 5 snapshots.', 'boletines' ); ?>
			</p>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
			<input type="hidden" name="action" value="boletines_health_create_snapshot">
			<?php wp_nonce_field( 'boletines_health_create_snapshot' ); ?>
			<button type="submit" class="button button-primary">
				<span class="dashicons dashicons-backup" style="margin:3px 4px 0 0;"></span>
				<?php esc_html_e( 'Crear snapshot ahora', 'boletines' ); ?>
			</button>
		</form>

		<?php if ( empty( $snapshots ) ) : ?>
			<div style="padding:30px;background:#f8fafc;border-radius:8px;text-align:center;color:#64748b;">
				<?php esc_html_e( 'No hay snapshots todavía. Pulsa "Crear snapshot ahora" para hacer el primero.', 'boletines' ); ?>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Etiqueta', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Versión', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Tablas', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Filas', 'boletines' ); ?></th>
						<th><?php esc_html_e( 'Tamaño', 'boletines' ); ?></th>
						<th style="width:280px;"><?php esc_html_e( 'Acciones', 'boletines' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $snapshots as $filename => $snap ) :
						$download_url = Snapshots::backups_url() . '/' . $filename;
					?>
						<tr>
							<td><code><?php echo esc_html( $snap['timestamp'] ); ?></code></td>
							<td><?php echo esc_html( $snap['label'] ); ?></td>
							<td><code><?php echo esc_html( $snap['version'] ); ?></code></td>
							<td><?php echo (int) $snap['tables']; ?></td>
							<td><?php echo number_format_i18n( $snap['rows'] ); ?></td>
							<td><?php echo esc_html( size_format( $snap['size'] ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( $download_url ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'Descargar', 'boletines' ); ?></a>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="boletines_health_restore_snapshot">
									<input type="hidden" name="filename" value="<?php echo esc_attr( $filename ); ?>">
									<?php wp_nonce_field( 'boletines_health_restore_snapshot' ); ?>
									<button type="submit" class="button button-small button-link-delete"
										onclick="return confirm('<?php echo esc_js( __( '⚠ Restaurar este snapshot SOBRESCRIBE las tablas actuales con las del snapshot. Se creará un snapshot de seguridad antes. ¿Continuar?', 'boletines' ) ); ?>');">
										<?php esc_html_e( 'Restaurar', 'boletines' ); ?>
									</button>
								</form>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="boletines_health_delete_snapshot">
									<input type="hidden" name="filename" value="<?php echo esc_attr( $filename ); ?>">
									<?php wp_nonce_field( 'boletines_health_delete_snapshot' ); ?>
									<button type="submit" class="button button-small"
										onclick="return confirm('<?php echo esc_js( __( '¿Borrar este snapshot?', 'boletines' ) ); ?>');">
										<?php esc_html_e( 'Borrar', 'boletines' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top:16px;color:#64748b;font-size:13px;">
				<?php
				printf(
					/* translators: %s: full path */
					esc_html__( 'Los snapshots se guardan en: %s', 'boletines' ),
					'<code>' . esc_html( Snapshots::backups_dir() ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	// =====================================================================
	// TAB: Conectividad (SMTP saliente + servidor de licencias)
	// =====================================================================

	private function render_connectivity(): void {
		$status       = SmtpDiag::status();
		$current_user = wp_get_current_user();
		$bg = $status['status'] === 'ok' ? '#dcfce7' : ( $status['status'] === 'critical' ? '#fee2e2' : '#fef3c7' );
		$bd = $status['status'] === 'ok' ? '#16a34a' : ( $status['status'] === 'critical' ? '#dc2626' : '#d97706' );

		// Resultado del último test de servidor de licencias (si lo hay).
		$lic_key    = 'boletines_health_license_test_' . get_current_user_id();
		$lic_result = get_transient( $lic_key );
		if ( $lic_result ) {
			delete_transient( $lic_key );
		}
		?>
		<h2 style="margin-top:0;"><?php esc_html_e( '📧 Envío de correos (SMTP)', 'boletines' ); ?></h2>

		<div style="background:<?php echo esc_attr( $bg ); ?>;border-left:4px solid <?php echo esc_attr( $bd ); ?>;border-radius:8px;padding:18px 22px;margin-bottom:20px;">
			<h3 style="margin:0 0 6px;font-size:16px;"><?php echo esc_html( $status['message'] ); ?></h3>
			<p style="margin:0 0 10px;line-height:1.55;"><?php echo esc_html( $status['details'] ); ?></p>
			<?php if ( ! empty( $status['action_url'] ) ) : ?>
				<a href="<?php echo esc_url( $status['action_url'] ); ?>" class="button button-primary"><?php echo esc_html( $status['action_label'] ); ?></a>
			<?php endif; ?>
		</div>

		<h3><?php esc_html_e( 'Prueba rápida de envío', 'boletines' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Envía un correo de prueba para confirmar que wp_mail() funciona en tu sitio.', 'boletines' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="boletines_health_smtp_test">
			<?php wp_nonce_field( 'boletines_health_smtp_test' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bh-smtp-to"><?php esc_html_e( 'Enviar a', 'boletines' ); ?></label></th>
					<td><input type="email" id="bh-smtp-to" name="test_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" class="regular-text" required></td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Enviar prueba', 'boletines' ); ?></button></p>
		</form>

		<details style="margin-top:12px;">
			<summary style="cursor:pointer;color:#64748b;font-size:13px;"><?php esc_html_e( '¿Qué plugin SMTP recomendamos?', 'boletines' ); ?></summary>
			<ul style="line-height:1.7;margin-top:8px;">
				<li><strong>FluentSMTP</strong> — <?php esc_html_e( 'Gratis, open source, soporta los principales proveedores (Gmail, Outlook, SendGrid, SES, Mailgun, Brevo, Postmark…). Es nuestra recomendación principal.', 'boletines' ); ?></li>
				<li><strong>WP Mail SMTP</strong> — <?php esc_html_e( 'Versión gratuita popular; las funciones avanzadas son de pago.', 'boletines' ); ?></li>
			</ul>
		</details>

		<hr style="margin:32px 0;border:0;border-top:1px solid #e5e7eb;">

		<h2><?php esc_html_e( '🔑 Servidor de licencias', 'boletines' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Prueba la conexión con el servidor que verifica tu licencia Pro. Si falla, la activación de licencia tampoco funcionará.', 'boletines' ); ?></p>

		<?php
		$api_base    = \Boletines\Licensing\Client::api_base();
		$api_default = \Boletines\Licensing\Client::DEFAULT_API_BASE;
		$is_override = ( defined( 'BOLETINES_LICENSE_API_BASE' ) && BOLETINES_LICENSE_API_BASE )
			|| ( rtrim( $api_base, '/' ) !== rtrim( $api_default, '/' ) );
		?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Servidor configurado', 'boletines' ); ?></th>
				<td>
					<code style="font-size:12px;word-break:break-all;"><?php echo esc_html( $api_base ); ?></code>
					<?php if ( $is_override ) : ?>
						<br><span style="color:#7c3aed;font-size:12px;">⚙ <?php esc_html_e( 'Override activo (constante BOLETINES_LICENSE_API_BASE o filtro)', 'boletines' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="boletines_health_license_test">
			<?php wp_nonce_field( 'boletines_health_license_test' ); ?>
			<p><button type="submit" class="button button-primary">🔍 <?php esc_html_e( 'Probar conexión con el servidor de licencias', 'boletines' ); ?></button></p>
		</form>

		<?php if ( $lic_result && is_array( $lic_result ) ) : ?>
			<?php $this->render_license_diag_result( $lic_result ); ?>
		<?php endif; ?>
		<?php
	}

	private function render_license_diag_result( array $r ): void {
		$ok    = ! empty( $r['ok'] );
		$bg    = $ok ? '#dcfce7' : '#fee2e2';
		$bd    = $ok ? '#16a34a' : '#dc2626';
		$txt   = $ok ? '#14532d' : '#7f1d1d';
		?>
		<div style="background:<?php echo esc_attr( $bg ); ?>;border-left:3px solid <?php echo esc_attr( $bd ); ?>;border-radius:4px;padding:14px 18px;margin-top:14px;color:<?php echo esc_attr( $txt ); ?>;">
			<div style="font-weight:600;margin-bottom:6px;font-size:14px;"><?php echo esc_html( $r['title'] ?? '' ); ?></div>
			<?php if ( ! empty( $r['detail'] ) ) : ?>
				<div style="font-size:13px;line-height:1.6;"><?php echo wp_kses_post( $r['detail'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $r['url'] ) ) : ?>
				<div style="font-size:11px;margin-top:8px;opacity:.7;font-family:monospace;word-break:break-all;"><?php echo esc_html( $r['url'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $r['http_code'] ) ) : ?>
				<div style="font-size:11px;margin-top:2px;opacity:.7;"><?php echo esc_html( sprintf( 'HTTP %s', $r['http_code'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $r['next_steps'] ) ) : ?>
				<div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(0,0,0,.1);font-size:13px;">
					<strong><?php esc_html_e( 'Qué revisar:', 'boletines' ); ?></strong>
					<?php echo wp_kses_post( $r['next_steps'] ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// =====================================================================
	// TAB: Test de URLs (v2.1.1)
	// =====================================================================

	private function render_urls_test(): void {
		global $wpdb;
		$subs_table = \Boletines\Plugin::table( 'subscribers' );

		// Recoger ID del suscriptor (de query string o de form enviado).
		$sid_input = isset( $_GET['sid'] ) ? (int) $_GET['sid'] : 0;
		if ( ! $sid_input && isset( $_POST['sid'] ) ) {
			$sid_input = (int) $_POST['sid'];
		}

		// Listado de suscriptores recientes para el selector.
		$subs = $wpdb->get_results(
			"SELECT id, email, first_name, status, token FROM {$subs_table} ORDER BY id DESC LIMIT 30",
			ARRAY_A
		);

		// Estado de las rewrite rules.
		$rewrite_rules = get_option( 'rewrite_rules' );
		$rules_ok      = is_array( $rewrite_rules ) && (
			isset( $rewrite_rules['^boletines-action/preferences/([^/]+)/([^/]+)/?$'] ) ||
			isset( $rewrite_rules['boletines-action/preferences/([^/]+)/([^/]+)/?$'] )
		);

		?>
		<div class="bol-urls-test">
			<h2>🔗 <?php esc_html_e( 'Test de URLs de correo (v2.1.1)', 'boletines' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Genera las URLs que el plugin pondría en los correos y permite hacer clic sin enviar ningún email. Sirve para verificar que el formato path-based funciona y que las rewrite rules están activas.', 'boletines' ); ?>
			</p>

			<!-- Estado de rewrite rules -->
			<div style="background:<?php echo $rules_ok ? '#dcfce7' : '#fee2e2'; ?>;border-left:3px solid <?php echo $rules_ok ? '#16a34a' : '#dc2626'; ?>;border-radius:4px;padding:12px 16px;margin:16px 0;">
				<strong><?php echo $rules_ok ? '✅' : '❌'; ?>
					<?php
					if ( $rules_ok ) {
						esc_html_e( 'Rewrite rules path-based registradas correctamente.', 'boletines' );
					} else {
						esc_html_e( 'Las rewrite rules path-based NO están registradas. Solución:', 'boletines' );
						echo ' <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Abre Ajustes → Enlaces permanentes y guarda sin cambiar nada', 'boletines' ) . '</a>';
					}
					?>
				</strong>
			</div>

			<!-- Selector de suscriptor -->
			<form method="get" action="" style="margin:18px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<input type="hidden" name="tab" value="urls">
				<label style="display:block;font-weight:600;margin-bottom:8px;">
					<?php esc_html_e( '1. Elige un suscriptor de prueba:', 'boletines' ); ?>
				</label>
				<select name="sid" onchange="this.form.submit()" style="min-width:380px;max-width:100%;">
					<option value="0">— <?php esc_html_e( 'Selecciona un suscriptor', 'boletines' ); ?> —</option>
					<?php foreach ( (array) $subs as $s ) : ?>
						<option value="<?php echo (int) $s['id']; ?>" <?php selected( $sid_input, (int) $s['id'] ); ?>>
							#<?php echo (int) $s['id']; ?> — <?php echo esc_html( $s['email'] ); ?>
							(<?php echo esc_html( $s['status'] ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
			</form>

			<?php if ( $sid_input ) :
				$sub = $wpdb->get_row( $wpdb->prepare(
					"SELECT id, email, first_name, status, token FROM {$subs_table} WHERE id = %d",
					$sid_input
				), ARRAY_A );

				if ( ! $sub ) {
					echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Suscriptor no encontrado.', 'boletines' ) . '</p></div>';
				} else {
					$pref_path  = \Boletines\Mailer\Branding::path_action_url( 'preferences',  $sub['id'], $sub['token'] );
					$unsub_path = \Boletines\Mailer\Branding::path_action_url( 'unsubscribe', $sub['id'], $sub['token'] );
					$conf_path  = \Boletines\Mailer\Branding::path_action_url( 'confirm',     $sub['id'], $sub['token'] );

					// URLs legacy (formato viejo con query string) — construidas manualmente
					// para mostrar al admin qué emails viejos están enviando sus suscriptores.
					$pref_legacy  = add_query_arg( array( 'boletines_action' => 'preferences',  'sid' => $sub['id'], 'token' => $sub['token'] ), home_url( '/' ) );
					$unsub_legacy = add_query_arg( array( 'boletines_action' => 'unsubscribe', 'sid' => $sub['id'], 'token' => $sub['token'] ), home_url( '/' ) );

					$label_email = $sub['email'] . ' (#' . (int) $sub['id'] . ', ' . esc_html( $sub['status'] ) . ')';
				?>
				<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px;margin-top:8px;">
					<p style="margin:0 0 12px;font-size:14px;color:#374151;">
						<strong><?php esc_html_e( 'Suscriptor:', 'boletines' ); ?></strong> <?php echo esc_html( $label_email ); ?>
					</p>

					<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 16px;margin:0 0 16px;">
						<p style="margin:0 0 10px;font-size:13px;color:#1e3a8a;">
							📧 <?php esc_html_e( 'Prueba REAL: envía un correo a este suscriptor con todos los enlaces (redes, artículo externo, preferencias, baja) pasando por el mismo motor que una campaña. Ábrelo en tu bandeja y haz clic en cada uno.', 'boletines' ); ?>
						</p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
							<input type="hidden" name="action" value="boletines_health_link_test">
							<input type="hidden" name="sid" value="<?php echo (int) $sub['id']; ?>">
							<?php wp_nonce_field( 'boletines_health_link_test' ); ?>
							<button type="submit" class="button button-primary">
								<?php printf( esc_html__( 'Enviar correo de prueba a %s', 'boletines' ), esc_html( $sub['email'] ) ); ?>
							</button>
						</form>
					</div>

					<h3 style="margin:18px 0 8px;font-size:15px;">🆕 <?php esc_html_e( 'Formato path-based (v2.1.1, recomendado)', 'boletines' ); ?></h3>
					<p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Estas URLs son inmunes a WAFs que pelan query strings. Click para probar:', 'boletines' ); ?></p>

					<?php
					$rows = array(
						'Preferencias'  => $pref_path,
						'Unsubscribe'   => $unsub_path,
						'Confirmar opt-in' => $conf_path,
					);
					foreach ( $rows as $label => $url ) : ?>
						<div style="display:flex;align-items:center;gap:8px;margin:6px 0;padding:8px 10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;">
							<strong style="min-width:140px;color:#14532d;"><?php echo esc_html( $label ); ?></strong>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" style="flex:1;font-family:monospace;font-size:12px;word-break:break-all;color:#0f766e;"><?php echo esc_html( $url ); ?></a>
							<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="button button-small"><?php esc_html_e( 'Probar', 'boletines' ); ?></a>
						</div>
					<?php endforeach; ?>

					<h3 style="margin:24px 0 8px;font-size:15px;">📜 <?php esc_html_e( 'Formato legacy con query string (fallback)', 'boletines' ); ?></h3>
					<p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Para emails viejos en circulación. Si el WAF pela los parámetros, fallará:', 'boletines' ); ?></p>

					<?php
					$rows_legacy = array(
						'Preferencias' => $pref_legacy,
						'Unsubscribe'  => $unsub_legacy,
					);
					foreach ( $rows_legacy as $label => $url ) : ?>
						<div style="display:flex;align-items:center;gap:8px;margin:6px 0;padding:8px 10px;background:#fef9c3;border:1px solid #fde047;border-radius:6px;">
							<strong style="min-width:140px;color:#713f12;"><?php echo esc_html( $label ); ?></strong>
							<code style="flex:1;font-size:11px;word-break:break-all;color:#854d0e;"><?php echo esc_html( $url ); ?></code>
						</div>
					<?php endforeach; ?>

					<hr style="margin:24px 0;border:0;border-top:1px solid #e5e7eb;">

					<h3 style="margin:0 0 8px;font-size:15px;">🩺 <?php esc_html_e( 'Si la URL path-based da 404', 'boletines' ); ?></h3>
					<ol style="margin:0 0 0 18px;font-size:13px;line-height:1.7;">
						<li><?php esc_html_e( 'Ve a Ajustes → Enlaces permanentes y pulsa "Guardar cambios" sin modificar nada. Esto regenera el .htaccess.', 'boletines' ); ?></li>
						<li><?php esc_html_e( 'Si tu hosting usa nginx, pídele al soporte que incluya las reglas del .htaccess.', 'boletines' ); ?></li>
						<li><?php esc_html_e( 'Verifica que los permalinks NO estén en "Simple" (deben ser "Nombre de la entrada" o cualquier otro).', 'boletines' ); ?></li>
					</ol>
				</div>
			<?php } ?>
			<?php else : ?>
				<div class="notice notice-info inline">
					<p>👆 <?php esc_html_e( 'Selecciona un suscriptor arriba para ver sus URLs de prueba.', 'boletines' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// =====================================================================
	// TAB: Sistema
	// =====================================================================

	private function render_system(): void {
		global $wpdb;
		$prefix = $wpdb->prefix . BOLETINES_TABLE_PREFIX;

		$tables = array(
			'subscribers', 'lists', 'subscriber_list', 'subscriber_meta',
			'campaigns', 'campaign_emails', 'forms',
			'automations', 'automation_runs', 'bounces', 'clicks',
		);

		$api_base    = \Boletines\Licensing\Client::api_base();
		$api_default = \Boletines\Licensing\Client::DEFAULT_API_BASE;
		$api_override = '';
		if ( defined( 'BOLETINES_LICENSE_API_BASE' ) && BOLETINES_LICENSE_API_BASE ) {
			$api_override = __( '⚙ Override por constante BOLETINES_LICENSE_API_BASE en wp-config.php', 'boletines' );
		} elseif ( $api_base !== rtrim( $api_default, '/' ) ) {
			$api_override = __( '⚙ Override por filtro boletines_license_api_base', 'boletines' );
		}

		$info = array(
			__( 'Versión del plugin', 'boletines' )     => BOLETINES_VERSION,
			__( 'Versión BD del plugin', 'boletines' )  => get_option( 'boletines_db_version', '—' ),
			__( 'Versión de WordPress', 'boletines' )   => get_bloginfo( 'version' ),
			__( 'Versión de PHP', 'boletines' )         => PHP_VERSION,
			__( 'Prefijo de tablas', 'boletines' )      => $wpdb->prefix . BOLETINES_TABLE_PREFIX,
			__( 'Charset BD', 'boletines' )             => $wpdb->charset,
			__( 'Memoria PHP', 'boletines' )            => ini_get( 'memory_limit' ),
			__( 'Primera instalación', 'boletines' )    => get_option( 'boletines_first_install', '—' ),
			__( 'Última migración', 'boletines' )       => get_option( 'boletines_last_migration', '—' ),
			__( 'OpenSSL', 'boletines' )                => function_exists( 'openssl_encrypt' ) ? __( 'Sí', 'boletines' ) : __( 'No', 'boletines' ),
			__( 'cURL', 'boletines' )                   => function_exists( 'curl_version' ) ? __( 'Sí', 'boletines' ) : __( 'No', 'boletines' ),
			__( 'WP-Cron deshabilitado', 'boletines' )  => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? __( 'Sí', 'boletines' ) : __( 'No', 'boletines' ),
			__( 'Servidor de licencias', 'boletines' )  => $api_base . ( $api_override ? ' — ' . $api_override : '' ),
		);
		?>
		<h2><?php esc_html_e( 'Información del sistema', 'boletines' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<?php foreach ( $info as $label => $value ) : ?>
					<tr><th style="width:280px;"><?php echo esc_html( $label ); ?></th><td><code><?php echo esc_html( $value ); ?></code></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:30px;"><?php esc_html_e( 'Estado de las tablas', 'boletines' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tabla', 'boletines' ); ?></th>
					<th><?php esc_html_e( 'Existe', 'boletines' ); ?></th>
					<th><?php esc_html_e( 'Filas', 'boletines' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tables as $short ) :
					$table  = $prefix . $short;
					$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
					$rows   = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) : 0;
				?>
					<tr>
						<td><code><?php echo esc_html( $table ); ?></code></td>
						<td><?php echo $exists ? '✅' : '<span style="color:#dc2626;">❌</span>'; ?></td>
						<td><?php echo $exists ? esc_html( number_format_i18n( $rows ) ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:30px;"><?php esc_html_e( 'Hooks para developers', 'boletines' ); ?></h2>
		<ul style="line-height:1.7;list-style:disc;margin-left:20px;">
			<li><code>boletines_snapshots_keep</code> — <?php esc_html_e( 'Filtro del número de snapshots conservados (default 5).', 'boletines' ); ?></li>
			<li><code>boletines_modules</code> — <?php esc_html_e( 'Filtro para registrar módulos Pro adicionales.', 'boletines' ); ?></li>
			<li><code>boletines_module_activated</code> / <code>boletines_module_deactivated</code> — <?php esc_html_e( 'Acciones al cambiar estado de módulo.', 'boletines' ); ?></li>
		</ul>
		<?php
	}

	// =====================================================================
	// Notices
	// =====================================================================

	private function maybe_render_notices(): void {
		$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		switch ( $msg ) {
			case 'snapshot_created':
				$info = get_transient( 'boletines_health_last_snapshot_info' );
				delete_transient( 'boletines_health_last_snapshot_info' );
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Snapshot creado.', 'boletines' ) . '</strong>';
				if ( is_array( $info ) ) {
					echo ' ' . sprintf(
						/* translators: 1: tables, 2: rows, 3: size */
						esc_html__( '%1$d tablas, %2$s filas, %3$s.', 'boletines' ),
						(int) $info['tables'],
						number_format_i18n( (int) $info['rows'] ),
						esc_html( size_format( (int) $info['size'] ) )
					);
				}
				echo '</p></div>';
				break;
			case 'snapshot_restored':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Snapshot restaurado. Se creó un snapshot de seguridad antes.', 'boletines' ) . '</p></div>';
				break;
			case 'snapshot_restore_failed':
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'La restauración falló parcial o totalmente. Revisa los logs.', 'boletines' ) . '</p></div>';
				break;
			case 'snapshot_deleted':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Snapshot eliminado.', 'boletines' ) . '</p></div>';
				break;
			case 'smtp_test_sent':
				$res = get_transient( 'boletines_health_smtp_test_result_' . get_current_user_id() );
				delete_transient( 'boletines_health_smtp_test_result_' . get_current_user_id() );
				if ( is_array( $res ) ) {
					$cls = $res['success'] ? 'notice-success' : 'notice-error';
					echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				}
				break;
			case 'install_retried':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Instalación re-ejecutada. Revisa diagnóstico para confirmar el estado.', 'boletines' ) . '</p></div>';
				break;
			case 'link_test_sent':
				$res = get_transient( 'boletines_health_link_test_' . get_current_user_id() );
				delete_transient( 'boletines_health_link_test_' . get_current_user_id() );
				if ( is_array( $res ) ) {
					$cls = ! empty( $res['success'] ) ? 'notice-success' : 'notice-error';
					echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $res['message'] ) . '</p></div>';
				}
				break;
		}
	}

	// =====================================================================
	// Handlers
	// =====================================================================

	public function handle_create_snapshot(): void {
		$this->require_caps( 'boletines_health_create_snapshot' );
		$result = Snapshots::capture( 'manual' );
		if ( '' === $result['error'] ) {
			set_transient( 'boletines_health_last_snapshot_info', $result, 60 );
			$this->redirect( 'snapshots', 'snapshot_created' );
		}
		wp_die( esc_html( $result['error'] ) );
	}

	public function handle_restore_snapshot(): void {
		$this->require_caps( 'boletines_health_restore_snapshot' );
		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
		$result   = Snapshots::restore( $filename );
		$this->redirect( 'snapshots', $result['success'] ? 'snapshot_restored' : 'snapshot_restore_failed' );
	}

	public function handle_delete_snapshot(): void {
		$this->require_caps( 'boletines_health_delete_snapshot' );
		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
		Snapshots::delete( $filename );
		$this->redirect( 'snapshots', 'snapshot_deleted' );
	}

	public function handle_smtp_test(): void {
		$this->require_caps( 'boletines_health_smtp_test' );
		$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		$res = SmtpDiag::send_test( $to );
		set_transient( 'boletines_health_smtp_test_result_' . get_current_user_id(), $res, 60 );
		$this->redirect( 'conectividad', 'smtp_test_sent' );
	}

	public function handle_license_test(): void {
		$this->require_caps( 'boletines_health_license_test' );

		$base = \Boletines\Licensing\Client::api_base();
		$url  = add_query_arg(
			array(
				'license_key' => 'connection-test-diag',
				'site_url'    => home_url(),
			),
			$base . '/verify'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		$result   = $this->interpret_license_diag( $response, $url );

		set_transient( 'boletines_health_license_test_' . get_current_user_id(), $result, 60 );
		$this->redirect( 'conectividad', '' );
	}

	/**
	 * Interpreta la respuesta del test de servidor de licencias.
	 *
	 * @param mixed  $response wp_remote_get response
	 * @param string $url      URL probada
	 */
	private function interpret_license_diag( $response, string $url ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'         => false,
				'title'      => __( '❌ No se pudo conectar', 'boletines' ),
				'detail'     => sprintf(
					/* translators: %s: error message */
					__( 'Error de conexión: %s', 'boletines' ),
					'<code>' . esc_html( $response->get_error_message() ) . '</code>'
				),
				'next_steps' => '<ul style="margin:4px 0 0 1em;list-style:disc;"><li>' .
					esc_html__( 'Comprueba que el dominio del servidor es accesible desde este sitio.', 'boletines' ) . '</li><li>' .
					esc_html__( 'Si hay certificado SSL inválido o auto-firmado: la conexión falla por seguridad.', 'boletines' ) .
					'</li></ul>',
				'url'        => $url,
				'http_code'  => '',
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code === 200 && is_array( $data ) && array_key_exists( 'active', $data ) ) {
			return array(
				'ok'         => true,
				'title'      => __( '✅ Conexión correcta', 'boletines' ),
				'detail'     => __( 'El servidor de licencias responde correctamente. La activación de licencia funcionará.', 'boletines' ),
				'next_steps' => '',
				'url'        => $url,
				'http_code'  => (string) $code,
			);
		}

		if ( is_array( $data ) && isset( $data['code'] ) && $data['code'] === 'rest_no_route' ) {
			return array(
				'ok'         => false,
				'title'      => __( '❌ El servidor no encuentra la ruta del API', 'boletines' ),
				'detail'     => __( 'El servidor responde pero no encuentra el endpoint <code>/cadaidea/v1/digest/verify</code>. El plugin servidor de licencias no está activo allí, o algo bloquea el namespace <code>cadaidea/v1</code>.', 'boletines' ),
				'next_steps' => '<ul style="margin:4px 0 0 1em;list-style:disc;"><li>' .
					esc_html__( 'Si tienes plugins de seguridad (WP Ghost, Wordfence): añade /wp-json/cadaidea/* a la lista blanca.', 'boletines' ) . '</li><li>' .
					esc_html__( 'Tras cambios en el servidor: refresca enlaces permanentes (Ajustes → Enlaces permanentes → Guardar).', 'boletines' ) .
					'</li></ul>',
				'url'        => $url,
				'http_code'  => (string) $code,
			);
		}

		if ( $code === 403 ) {
			return array(
				'ok'         => false,
				'title'      => __( '❌ Acceso prohibido (HTTP 403)', 'boletines' ),
				'detail'     => __( 'El servidor rechaza la petición. Casi siempre es un plugin de seguridad (firewall/WAF) bloqueando wp-json.', 'boletines' ),
				'next_steps' => '<ul style="margin:4px 0 0 1em;list-style:disc;"><li>' .
					esc_html__( 'Revisa los plugins de seguridad del servidor de licencias y permite el namespace cadaidea/v1.', 'boletines' ) . '</li><li>' .
					esc_html__( 'Si tu hosting tiene firewall propio: pide soporte permitir rutas /wp-json/.', 'boletines' ) .
					'</li></ul>',
				'url'        => $url,
				'http_code'  => (string) $code,
			);
		}

		if ( $code >= 500 ) {
			return array(
				'ok'         => false,
				'title'      => __( '❌ Error del servidor', 'boletines' ),
				'detail'     => sprintf(
					/* translators: %s: response body */
					__( 'El servidor de licencias devolvió un error interno. Respuesta: %s', 'boletines' ),
					'<code style="display:block;margin-top:4px;padding:6px;background:#fff;border-radius:3px;max-height:120px;overflow:auto;font-size:11px;">' . esc_html( substr( strip_tags( $body ), 0, 400 ) ) . '</code>'
				),
				'next_steps' => '<ul style="margin:4px 0 0 1em;list-style:disc;"><li>' .
					esc_html__( 'Si administras el servidor de licencias: revisa los logs PHP.', 'boletines' ) . '</li><li>' .
					esc_html__( 'Si no lo administras: reporta el problema al equipo de soporte.', 'boletines' ) .
					'</li></ul>',
				'url'        => $url,
				'http_code'  => (string) $code,
			);
		}

		return array(
			'ok'         => false,
			'title'      => __( '⚠ Respuesta inesperada', 'boletines' ),
			'detail'     => sprintf(
				/* translators: %s: body excerpt */
				__( 'El servidor respondió pero no en el formato esperado. Primeros 300 caracteres: %s', 'boletines' ),
				'<code style="display:block;margin-top:4px;padding:6px;background:#fff;border-radius:3px;font-size:11px;">' . esc_html( substr( strip_tags( $body ), 0, 300 ) ) . '</code>'
			),
			'next_steps' => '',
			'url'        => $url,
			'http_code'  => (string) $code,
		);
	}

	public function handle_retry_install(): void {
		$this->require_caps( 'boletines_health_retry_install' );
		// Snapshot de seguridad antes de reintentar
		Snapshots::capture( 'pre-retry-install' );
		// Forzar re-instalación
		\Boletines\Installer::install();
		$this->redirect( 'diagnostico', 'install_retried' );
	}

	public function handle_link_test(): void {
		$this->require_caps( 'boletines_health_link_test' );
		global $wpdb;
		$sid = isset( $_POST['sid'] ) ? (int) $_POST['sid'] : 0;
		$subs_table = \Boletines\Plugin::table( 'subscribers' );
		$sub = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, email, first_name, status, token FROM {$subs_table} WHERE id = %d",
			$sid
		), ARRAY_A );

		$res = array( 'success' => false, 'message' => __( 'Suscriptor no encontrado.', 'boletines' ) );
		if ( $sub ) {
			$out = ( new \Boletines\Mailer\Mailer() )->send_link_test( $sub );
			if ( $out === true ) {
				$res = array( 'success' => true, 'message' => sprintf( __( 'Correo de prueba enviado a %s. Ábrelo y prueba cada enlace.', 'boletines' ), $sub['email'] ) );
			} else {
				$res = array( 'success' => false, 'message' => sprintf( __( 'No se pudo enviar: %s', 'boletines' ), (string) $out ) );
			}
		}
		set_transient( 'boletines_health_link_test_' . get_current_user_id(), $res, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=urls&sid=' . $sid . '&msg=link_test_sent' ) );
		exit;
	}

	private function require_caps( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private function redirect( string $tab, string $msg ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab . '&msg=' . $msg ) );
		exit;
	}
}
