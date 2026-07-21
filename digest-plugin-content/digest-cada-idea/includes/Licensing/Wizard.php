<?php
namespace Boletines\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asistente de activación de Digest Cada Idea.
 *
 * Pantallas:
 *  Paso 1: Bienvenida + formulario de datos (email, nombre, apellido, cumpleaños).
 *  Paso 2: Esperando código — pedir el código que llegó al correo.
 *  Paso 3: ¡Activado!
 *
 * IMPORTANTE: la activación es OPCIONAL. El plugin funciona sin licencia.
 * Activar da updates, soporte, y futuras features Pro.
 */
class Wizard {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99 );
		add_action( 'admin_post_boletines_license_register', array( $this, 'handle_register' ) );
		add_action( 'admin_post_boletines_license_activate', array( $this, 'handle_activate' ) );
		add_action( 'admin_post_boletines_license_deactivate', array( $this, 'handle_deactivate' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_banner' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'boletines',
			__( 'Licencia', 'boletines' ),
			__( 'Licencia', 'boletines' ),
			'manage_options',
			'boletines-license',
			array( $this, 'render' )
		);
	}

	/** Banner discreto en admin si no se ha activado todavía. */
	public function maybe_show_banner(): void {
		if ( Manager::is_active() ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Sólo en pantallas del plugin.
		if ( ! $screen || strpos( $screen->id, 'boletines' ) === false ) return;
		// No mostrar en la propia pantalla de licencia.
		if ( $screen->id === 'digest_page_boletines-license' ) return;

		$url = admin_url( 'admin.php?page=boletines-license' );
		?>
		<div class="notice notice-info" style="display:flex;align-items:center;gap:12px;">
			<div style="flex:1;">
				<strong><?php esc_html_e( 'Desbloquea Digest Pro', 'boletines' ); ?></strong> —
				<?php esc_html_e( 'activa tu licencia para usar las automatizaciones (publicar post/producto, digest, cumpleaños) y multi-dominio. Toma 1 minuto.', 'boletines' ); ?>
			</div>
			<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Activar Pro', 'boletines' ); ?></a>
		</div>
		<?php
	}

	public function render(): void {
		$profile = Manager::profile();
		$status  = $profile['status'];
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Licencia — Digest by Cada Idea', 'boletines' ); ?></h1>
			</div>

			<?php $this->maybe_render_messages(); ?>

			<div class="bol-card" style="max-width:680px;margin:0 auto;">

			<?php if ( $status === Manager::STATUS_ACTIVE ) : ?>
				<div style="text-align:center;padding:32px 16px;">
					<div style="font-size:48px;margin-bottom:12px;">✅</div>
					<h2 style="margin:0 0 8px;font-size:24px;"><?php esc_html_e( 'Licencia activa', 'boletines' ); ?></h2>
					<p style="color:#6b7280;margin:0 0 24px;">
						<?php esc_html_e( 'Tu copia está registrada y vinculada con Readers Cada Idea.', 'boletines' ); ?>
					</p>
					<table style="margin:0 auto 24px;text-align:left;font-size:14px;">
						<tr><td style="padding:4px 12px;color:#6b7280;"><?php esc_html_e( 'Nombre', 'boletines' ); ?>:</td><td><?php echo esc_html( trim( $profile['first_name'] . ' ' . $profile['last_name'] ) ); ?></td></tr>
						<tr><td style="padding:4px 12px;color:#6b7280;"><?php esc_html_e( 'Email', 'boletines' ); ?>:</td><td><?php echo esc_html( $profile['email'] ); ?></td></tr>
						<tr><td style="padding:4px 12px;color:#6b7280;"><?php esc_html_e( 'Cumpleaños', 'boletines' ); ?>:</td><td><?php echo esc_html( $profile['birthday'] ); ?></td></tr>
						<tr><td style="padding:4px 12px;color:#6b7280;"><?php esc_html_e( 'Licencia', 'boletines' ); ?>:</td><td style="font-family:monospace;font-size:12px;"><?php echo esc_html( substr( $profile['license_key'], 0, 8 ) . '••••••••' ); ?></td></tr>
						<tr><td style="padding:4px 12px;color:#6b7280;"><?php esc_html_e( 'Activada', 'boletines' ); ?>:</td><td><?php echo esc_html( $profile['activated_at'] ); ?></td></tr>
					</table>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="boletines_license_deactivate">
						<?php wp_nonce_field( 'boletines_license_deactivate' ); ?>
						<button type="submit" class="bol-btn bol-btn-secondary" onclick="return confirm('¿Desactivar esta copia?')"><?php esc_html_e( 'Desactivar licencia', 'boletines' ); ?></button>
					</form>
				</div>

			<?php elseif ( $status === Manager::STATUS_PENDING ) : ?>
				<div style="padding:24px;">
					<div style="text-align:center;font-size:48px;margin-bottom:12px;">✉️</div>
					<h2 style="margin:0 0 8px;text-align:center;"><?php esc_html_e( 'Revisa tu correo', 'boletines' ); ?></h2>
					<p style="color:#6b7280;text-align:center;margin:0 0 24px;">
						<?php
						/* translators: %s = email */
						printf( esc_html__( 'Te enviamos un código de 6 dígitos a %s. Introdúcelo abajo para completar la activación.', 'boletines' ), '<strong>' . esc_html( $profile['email'] ) . '</strong>' );
						?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
						<input type="hidden" name="action" value="boletines_license_activate">
						<?php wp_nonce_field( 'boletines_license_activate' ); ?>
						<div class="row">
							<label><?php esc_html_e( 'Código de activación', 'boletines' ); ?></label>
							<input type="text" name="code" required pattern="[0-9]{4,8}" maxlength="8" inputmode="numeric" autocomplete="one-time-code" autofocus style="font-size:22px;letter-spacing:6px;text-align:center;font-family:monospace;">
						</div>
						<div style="display:flex;gap:12px;align-items:center;">
							<button type="submit" class="bol-btn"><?php esc_html_e( 'Activar', 'boletines' ); ?></button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-license&resend=1' ) ); ?>" style="color:#6b7280;font-size:13px;"><?php esc_html_e( 'No me llegó el código', 'boletines' ); ?></a>
						</div>
					</form>
				</div>

			<?php else : ?>
				<div style="padding:8px 8px 24px;">
					<h2 style="margin:0 0 4px;"><?php esc_html_e( 'Activa Digest by Cada Idea', 'boletines' ); ?></h2>
					<p style="color:#6b7280;margin:0 0 20px;line-height:1.6;">
						<?php esc_html_e( 'Sin activación el plugin funciona, pero las siguientes funciones requieren licencia Pro:', 'boletines' ); ?>
					</p>
					<ul style="color:#374151;margin:0 0 16px 20px;line-height:1.7;">
						<li><strong>🚀 <?php esc_html_e( 'Automatizaciones', 'boletines' ); ?></strong> — <?php esc_html_e( 'envío automático al publicar un post o producto, digest diario/semanal, correos de cumpleaños', 'boletines' ); ?></li>
						<li><strong>🌐 <?php esc_html_e( 'Multi-dominio', 'boletines' ); ?></strong> — <?php esc_html_e( 'usa la misma licencia en todos tus sitios (Bletia, Seridea, Netvez, Talutil, etc.)', 'boletines' ); ?></li>
					</ul>
					<p style="color:#6b7280;margin:0 0 20px;line-height:1.6;">
						<?php esc_html_e( 'Suscriptores, listas, campañas manuales, formularios, popups y todas las funciones básicas siguen disponibles sin activar.', 'boletines' ); ?>
					</p>
					<p style="color:#6b7280;margin:0 0 20px;line-height:1.6;font-size:13px;">
						<?php esc_html_e( 'Además, al activarte recibirás:', 'boletines' ); ?>
					</p>
					<ul style="color:#374151;margin:0 0 24px 20px;line-height:1.7;font-size:14px;">
						<li><?php esc_html_e( 'Notificaciones de actualizaciones del plugin', 'boletines' ); ?></li>
						<li><?php esc_html_e( 'Tu perfil de lector en cadaidea.com', 'boletines' ); ?></li>
						<li><?php esc_html_e( 'Saludos en tu cumpleaños 🎂 y novedades antes que nadie', 'boletines' ); ?></li>
					</ul>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
						<input type="hidden" name="action" value="boletines_license_register">
						<?php wp_nonce_field( 'boletines_license_register' ); ?>

						<div class="row row-2">
							<div class="row">
								<label><?php esc_html_e( 'Nombre', 'boletines' ); ?> *</label>
								<input type="text" name="first_name" required value="<?php echo esc_attr( $profile['first_name'] ); ?>">
							</div>
							<div class="row">
								<label><?php esc_html_e( 'Apellido', 'boletines' ); ?> *</label>
								<input type="text" name="last_name" required value="<?php echo esc_attr( $profile['last_name'] ); ?>">
							</div>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Email', 'boletines' ); ?> *</label>
							<input type="email" name="email" required value="<?php echo esc_attr( $profile['email'] ?: ( wp_get_current_user()->user_email ?? '' ) ); ?>">
							<span class="help"><?php esc_html_e( 'A esta dirección enviaremos el código de activación.', 'boletines' ); ?></span>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Cumpleaños', 'boletines' ); ?> *</label>
							<input type="date" name="birthday" required value="<?php echo esc_attr( $profile['birthday'] ); ?>">
							<span class="help"><?php esc_html_e( 'Lo usamos sólo para enviarte un saludo el día de tu cumpleaños. No lo compartimos con nadie.', 'boletines' ); ?></span>
						</div>

						<div style="display:flex;gap:12px;align-items:center;margin-top:16px;">
							<button type="submit" class="bol-btn"><?php esc_html_e( 'Enviarme código de activación', 'boletines' ); ?></button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines' ) ); ?>" style="color:#6b7280;font-size:13px;"><?php esc_html_e( 'Activar más tarde', 'boletines' ); ?></a>
						</div>

						<p style="margin-top:16px;font-size:12px;color:#9ca3af;line-height:1.5;">
							<?php esc_html_e( 'Al registrarte, tus datos se envían a cadaidea.com (Cada Idea) y se crea tu perfil de Reader. Puedes desactivar la licencia y borrar tus datos en cualquier momento.', 'boletines' ); ?>
						</p>
					</form>
				</div>
			<?php endif; ?>

			</div>
		</div>
		<?php
	}

	private function maybe_render_messages(): void {
		if ( isset( $_GET['err'] ) ) {
			$msg = sanitize_text_field( wp_unslash( $_GET['err'] ) );
			echo '<div class="bol-notice warning">' . esc_html( $msg ) . '</div>';
		}
		if ( isset( $_GET['ok'] ) ) {
			$msg = sanitize_text_field( wp_unslash( $_GET['ok'] ) );
			echo '<div class="bol-notice success">' . esc_html( $msg ) . '</div>';
		}
		if ( isset( $_GET['resend'] ) ) {
			$profile = Manager::profile();
			if ( $profile['status'] === Manager::STATUS_PENDING && $profile['email'] ) {
				$client = new Client();
				$client->register( $profile['email'], $profile['first_name'], $profile['last_name'], $profile['birthday'] );
				echo '<div class="bol-notice info">' . esc_html__( 'Reenvíamos el código a tu correo.', 'boletines' ) . '</div>';
			}
		}
	}

	public function handle_register(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();
		check_admin_referer( 'boletines_license_register' );

		$data = array(
			'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
			'birthday'   => sanitize_text_field( wp_unslash( $_POST['birthday'] ?? '' ) ),
		);

		if ( ! is_email( $data['email'] ) || ! $data['first_name'] || ! $data['last_name'] || ! $data['birthday'] ) {
			$this->redirect_with( 'err', __( 'Completa todos los campos con datos válidos.', 'boletines' ) );
		}

		Manager::save_profile( $data );

		$client = new Client();
		$res    = $client->register( $data['email'], $data['first_name'], $data['last_name'], $data['birthday'] );

		if ( is_wp_error( $res ) ) {
			$this->redirect_with( 'err', $res->get_error_message() );
		}

		Manager::set_status( Manager::STATUS_PENDING );
		$this->redirect_with( 'ok', __( 'Te enviamos un código por correo. Revísalo e introdúcelo abajo.', 'boletines' ) );
	}

	public function handle_activate(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();
		check_admin_referer( 'boletines_license_activate' );

		$code    = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['code'] ?? '' ) );
		$profile = Manager::profile();

		if ( ! $code ) {
			$this->redirect_with( 'err', __( 'Introduce el código que recibiste por correo.', 'boletines' ) );
		}

		$client = new Client();
		$res    = $client->activate( $profile['email'], $code );

		if ( is_wp_error( $res ) ) {
			$this->redirect_with( 'err', $res->get_error_message() );
		}

		$license_key = (string) ( $res['license_key'] ?? '' );
		$reader_id   = (string) ( $res['reader_id'] ?? '' );

		if ( ! $license_key ) {
			$this->redirect_with( 'err', __( 'La respuesta del servidor no incluyó licencia válida.', 'boletines' ) );
		}

		Manager::set_license_key( $license_key, $reader_id );
		Manager::set_status( Manager::STATUS_ACTIVE );

		$this->redirect_with( 'ok', __( '¡Licencia activada! Tu Reader Cada Idea queda vinculado a esta instalación.', 'boletines' ) );
	}

	public function handle_deactivate(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();
		check_admin_referer( 'boletines_license_deactivate' );
		Manager::deactivate();
		$this->redirect_with( 'ok', __( 'Licencia desactivada. El plugin sigue funcionando con normalidad.', 'boletines' ) );
	}

	private function redirect_with( string $key, string $msg ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=boletines-license&' . $key . '=' . rawurlencode( $msg ) ) );
		exit;
	}

}
