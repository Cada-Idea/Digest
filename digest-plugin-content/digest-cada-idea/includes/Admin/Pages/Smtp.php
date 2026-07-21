<?php
namespace Boletines\Admin\Pages;

use Boletines\Smtp\Mailer;
use Boletines\Smtp\Providers;

defined( 'ABSPATH' ) || exit;

class Smtp {
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( ! empty( $_POST['boletines_smtp_save'] ) && check_admin_referer( 'boletines_smtp_save' ) ) {
			self::handle_save();
		}
		$test_result = null;
		if ( ! empty( $_POST['boletines_smtp_test'] ) && check_admin_referer( 'boletines_smtp_test' ) ) {
			$to = sanitize_email( wp_unslash( $_POST['test_to'] ?? '' ) );
			list( $ok, $msg, $debug ) = Mailer::send_test( $to );
			$test_result = compact( 'ok', 'msg', 'debug' );
		}

		$s   = Mailer::settings();
		$tab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'provider';
		$other = Mailer::other_smtp_plugin_active();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SMTP', 'boletines' ); ?></h1>
			<?php if ( $other ) : ?>
				<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Otro plugin SMTP activo:', 'boletines' ); ?></strong> <?php esc_html_e( 'detectamos FluentSMTP/WP Mail SMTP. Boletines SMTP NO interfiere mientras esos estén activos.', 'boletines' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-smtp&subtab=provider' ) ); ?>" class="nav-tab <?php echo $tab==='provider'?'nav-tab-active':''; ?>"><?php esc_html_e( 'Configuración', 'boletines' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-smtp&subtab=test' ) ); ?>" class="nav-tab <?php echo $tab==='test'?'nav-tab-active':''; ?>"><?php esc_html_e( 'Prueba', 'boletines' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-smtp&subtab=help' ) ); ?>" class="nav-tab <?php echo $tab==='help'?'nav-tab-active':''; ?>"><?php esc_html_e( 'Ayuda', 'boletines' ); ?></a>
			</h2>

			<?php if ( $tab === 'provider' ) self::tab_provider( $s );
			elseif ( $tab === 'test' )       self::tab_test( $s, $test_result );
			else                              self::tab_help(); ?>
		</div>
		<?php
	}

	private static function tab_provider( array $s ): void {
		?>
		<form method="post" style="max-width:780px;">
			<?php wp_nonce_field( 'boletines_smtp_save' ); ?>
			<input type="hidden" name="boletines_smtp_save" value="1">

			<div class="bol-card">
				<h2><?php esc_html_e( 'Activación', 'boletines' ); ?></h2>
				<label style="display:flex;align-items:center;gap:8px;">
					<input type="checkbox" name="enabled" value="1" <?php checked( (int) $s['enabled'] === 1 ); ?>>
					<strong><?php esc_html_e( 'Enviar correos por SMTP', 'boletines' ); ?></strong>
				</label>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Proveedor', 'boletines' ); ?></h2>
				<div class="row">
					<label><?php esc_html_e( 'Proveedor', 'boletines' ); ?></label>
					<select name="provider" id="bol-smtp-provider">
						<?php foreach ( Providers::all() as $slug => $p ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $s['provider'], $slug ); ?>
								data-host="<?php echo esc_attr( $p['host'] ); ?>" data-port="<?php echo esc_attr( $p['port'] ); ?>" data-enc="<?php echo esc_attr( $p['encryption'] ); ?>">
								<?php echo esc_html( $p['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php $cur = Providers::get( $s['provider'] ); if ( $cur ) : ?>
						<p class="help"><?php echo esc_html( $cur['description'] ); ?>
						<?php if ( ! empty( $cur['help_url'] ) ) : ?> <a href="<?php echo esc_url( $cur['help_url'] ); ?>" target="_blank">📖</a><?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="row"><label>Host</label><input type="text" name="smtp_host" id="bol-smtp-host" value="<?php echo esc_attr( $s['smtp_host'] ); ?>"></div>
				<div class="row" style="display:flex;gap:14px;">
					<div style="flex:0 0 120px;"><label>Puerto</label><input type="number" name="smtp_port" id="bol-smtp-port" value="<?php echo (int) $s['smtp_port']; ?>"></div>
					<div style="flex:1;"><label>Cifrado</label>
						<select name="smtp_encryption" id="bol-smtp-enc">
							<option value="tls"  <?php selected( $s['smtp_encryption'], 'tls' ); ?>>TLS (587)</option>
							<option value="ssl"  <?php selected( $s['smtp_encryption'], 'ssl' ); ?>>SSL (465)</option>
							<option value="none" <?php selected( $s['smtp_encryption'], 'none' ); ?>>Ninguno</option>
						</select>
					</div>
				</div>
				<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="smtp_autotls" value="1" <?php checked( (int) $s['smtp_autotls'] === 1 ); ?>> Auto-TLS</label>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Autenticación', 'boletines' ); ?></h2>
				<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="smtp_auth" value="1" <?php checked( (int) $s['smtp_auth'] === 1 ); ?>> <?php esc_html_e( 'Requiere autenticación', 'boletines' ); ?></label>
				<div class="row"><label>Usuario</label><input type="text" name="smtp_username" value="<?php echo esc_attr( $s['smtp_username'] ); ?>" autocomplete="off"></div>
				<div class="row"><label>Contraseña / API key</label><input type="password" name="smtp_password" value="<?php echo esc_attr( $s['smtp_password'] ); ?>" autocomplete="new-password"></div>
			</div>

			<div class="bol-card">
				<h2><?php esc_html_e( 'Remitente del sistema (From)', 'boletines' ); ?></h2>
				<p class="description" style="margin:0 0 10px;"><?php esc_html_e( 'Se usa para TODOS los correos de WordPress (recuperar contraseña, WooCommerce, etc.). Los correos de Digest usan su propio remitente configurado en Ajustes → General, así puedes tener dos remitentes distintos.', 'boletines' ); ?></p>
				<div class="row"><label>From email</label><input type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>"></div>
				<div class="row"><label>From name</label><input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>"></div>
				<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="force_from_email" value="1" <?php checked( (int) $s['force_from_email'] === 1 ); ?>> <?php esc_html_e( 'Forzar From email en todos los correos', 'boletines' ); ?></label>
				<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="force_from_name" value="1" <?php checked( (int) $s['force_from_name'] === 1 ); ?>> <?php esc_html_e( 'Forzar From name', 'boletines' ); ?></label>
			</div>

			<div class="bol-card" style="background:#fef9e7;border-left:4px solid #f59e0b;">
				<h2 style="color:#92400e;">⚠️ <?php esc_html_e( 'Compatibilidad Brevo / SES', 'boletines' ); ?></h2>
				<p style="font-size:13px;"><?php esc_html_e( 'Estos proveedores RECHAZAN envíos si el dominio del From no está verificado.', 'boletines' ); ?></p>
				<div class="row"><label><?php esc_html_e( 'Dominios verificados (coma separados)', 'boletines' ); ?></label><input type="text" name="verified_domains" value="<?php echo esc_attr( $s['verified_domains'] ); ?>" placeholder="cadaidea.com"></div>
				<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="use_sender" value="1" <?php checked( (int) $s['use_sender'] === 1 ); ?>> <?php esc_html_e( 'Configurar Return-Path/Sender', 'boletines' ); ?></label>
			</div>

			<div class="bol-card">
				<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="debug_mode" value="1" <?php checked( (int) $s['debug_mode'] === 1 ); ?>> <?php esc_html_e( 'Modo debug (escribe diálogo SMTP a error_log)', 'boletines' ); ?></label>
				<?php if ( ! empty( $s['last_error'] ) ) : ?>
					<div class="row"><label><?php esc_html_e( 'Último error', 'boletines' ); ?></label><textarea readonly rows="2" style="background:#fef2f2;color:#7f1d1d;width:100%;font-family:monospace;font-size:11px;"><?php echo esc_textarea( $s['last_error'] ); ?></textarea></div>
				<?php endif; ?>
			</div>

			<p><button type="submit" class="bol-btn"><?php esc_html_e( 'Guardar', 'boletines' ); ?></button></p>
		</form>
		<script>
		(function(){
			var sel = document.getElementById('bol-smtp-provider');
			if (!sel) return;
			sel.addEventListener('change', function(){
				var opt = sel.options[sel.selectedIndex];
				if (opt.dataset.host) document.getElementById('bol-smtp-host').value = opt.dataset.host;
				if (opt.dataset.port) document.getElementById('bol-smtp-port').value = opt.dataset.port;
				if (opt.dataset.enc)  document.getElementById('bol-smtp-enc').value  = opt.dataset.enc;
			});
		})();
		</script>
		<?php
	}

	private static function tab_test( array $s, $result ): void {
		$default_to = wp_get_current_user()->user_email ?? '';
		?>
		<div class="bol-card" style="max-width:780px;">
			<form method="post">
				<?php wp_nonce_field( 'boletines_smtp_test' ); ?>
				<input type="hidden" name="boletines_smtp_test" value="1">
				<div class="row"><label><?php esc_html_e( 'Enviar prueba a', 'boletines' ); ?></label><input type="email" name="test_to" value="<?php echo esc_attr( $default_to ); ?>" required></div>
				<button type="submit" class="bol-btn"><?php esc_html_e( 'Enviar', 'boletines' ); ?></button>
			</form>
			<?php if ( $result ) : ?>
				<div class="notice <?php echo $result['ok'] ? 'notice-success' : 'notice-error'; ?>" style="margin-top:14px;">
					<p><strong><?php echo $result['ok'] ? '✅' : '❌'; ?></strong> <?php echo esc_html( $result['msg'] ); ?></p>
				</div>
				<?php if ( ! empty( $result['debug'] ) ) : ?>
					<div class="row"><label><?php esc_html_e( 'Diálogo SMTP', 'boletines' ); ?></label><textarea rows="14" readonly style="width:100%;font-family:monospace;font-size:11px;background:#f9fafb;"><?php echo esc_textarea( $result['debug'] ); ?></textarea></div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function tab_help(): void {
		?>
		<div class="bol-card" style="max-width:780px;">
			<h2>Brevo paso a paso</h2>
			<ol style="line-height:1.7;">
				<li>Brevo → Senders, Domains → verifica tu dominio (DKIM+SPF)</li>
				<li>Crea una SMTP key en Settings → SMTP & API</li>
				<li>Aquí: Provider=Brevo, Usuario=email, Password=SMTP key</li>
				<li>Dominios verificados: el dominio del From</li>
				<li>Guarda y prueba</li>
			</ol>
			<h2>Errores comunes</h2>
			<dl>
				<dt><strong>Could not authenticate</strong></dt><dd>Usuario/password incorrectos.</dd>
				<dt><strong>Sender rejected: Domain not verified</strong></dt><dd>Verifica DKIM+SPF en tu proveedor.</dd>
				<dt><strong>SMTP connect() failed</strong></dt><dd>Hosting bloquea el puerto. Prueba 2525.</dd>
			</dl>
		</div>
		<?php
	}

	private static function handle_save(): void {
		Mailer::save( array(
			'enabled'          => isset( $_POST['enabled'] ) ? 1 : 0,
			'provider'         => isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'custom',
			'smtp_host'        => isset( $_POST['smtp_host'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_host'] ) ) : '',
			'smtp_port'        => max( 1, (int) ( $_POST['smtp_port'] ?? 587 ) ),
			'smtp_encryption'  => in_array( ( $_POST['smtp_encryption'] ?? 'tls' ), array( 'tls', 'ssl', 'none' ), true ) ? $_POST['smtp_encryption'] : 'tls',
			'smtp_autotls'     => isset( $_POST['smtp_autotls'] ) ? 1 : 0,
			'smtp_auth'        => isset( $_POST['smtp_auth'] ) ? 1 : 0,
			'smtp_username'    => isset( $_POST['smtp_username'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_username'] ) ) : '',
			'smtp_password'    => isset( $_POST['smtp_password'] ) ? wp_unslash( $_POST['smtp_password'] ) : '',
			'from_email'       => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
			'from_name'        => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
			'force_from_email' => isset( $_POST['force_from_email'] ) ? 1 : 0,
			'force_from_name'  => isset( $_POST['force_from_name'] ) ? 1 : 0,
			'use_sender'       => isset( $_POST['use_sender'] ) ? 1 : 0,
			'verified_domains' => isset( $_POST['verified_domains'] ) ? sanitize_text_field( wp_unslash( $_POST['verified_domains'] ) ) : '',
			'debug_mode'       => isset( $_POST['debug_mode'] ) ? 1 : 0,
		) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SMTP guardado.', 'boletines' ) . '</p></div>';
	}
}
