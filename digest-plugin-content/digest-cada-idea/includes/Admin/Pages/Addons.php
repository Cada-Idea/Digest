<?php
namespace Boletines\Admin\Pages;

use Boletines\Licensing\Manager as License;
use Boletines\Modules\Manager as Modules;
use Boletines\Modules\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Addons {

	public function register(): void {
		add_action( 'admin_post_boletines_module_toggle', array( $this, 'handle_toggle' ) );
	}

	public function render(): void {
		$modules = Modules::instance()->all();
		$has_lic = License::is_active();
		$wiz_url = admin_url( 'admin.php?page=boletines-license' );
		$msg     = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Digest by Cada Idea — Addons', 'boletines' ); ?></h1>
			</div>

			<?php if ( $msg === 'activated' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Módulo activado.', 'boletines' ); ?></p></div>
			<?php elseif ( $msg === 'deactivated' ) : ?>
				<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Módulo desactivado.', 'boletines' ); ?></p></div>
			<?php elseif ( $msg === 'locked' ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Este módulo requiere una licencia Pro activa.', 'boletines' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $has_lic ) : ?>
				<div style="background:linear-gradient(135deg,#7c3aed 0%,#a78bfa 100%);color:#fff;padding:24px 28px;border-radius:10px;margin:16px 0 24px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
					<div style="flex:1;min-width:280px;">
						<h2 style="margin:0 0 6px;color:#fff;font-size:20px;"><?php esc_html_e( '🔓 Desbloquea los módulos Pro', 'boletines' ); ?></h2>
						<p style="margin:0;line-height:1.6;color:#f3f0ff;">
							<?php esc_html_e( 'Activa tu licencia gratuita de Digest para usar las automatizaciones. Una sola licencia sirve para todos tus sitios.', 'boletines' ); ?>
						</p>
					</div>
					<a href="<?php echo esc_url( $wiz_url ); ?>" class="button button-primary button-hero" style="background:#fff;color:#7c3aed;border-color:#fff;"><?php esc_html_e( 'Activar licencia', 'boletines' ); ?></a>
				</div>
			<?php else : ?>
				<div style="background:#dcfce7;color:#14532d;padding:14px 20px;border-radius:8px;margin:16px 0 24px;border-left:4px solid #16a34a;">
					<strong>✅ <?php esc_html_e( 'Licencia Pro activa.', 'boletines' ); ?></strong>
					<?php esc_html_e( 'Puedes activar y configurar todos los módulos.', 'boletines' ); ?>
				</div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:20px;margin-top:8px;">
				<?php foreach ( $modules as $slug => $module ) : ?>
					<?php $this->render_card( $slug, $module ); ?>
				<?php endforeach; ?>
			</div>

			<div style="margin-top:32px;padding:20px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
				<h3 style="margin:0 0 8px;"><?php esc_html_e( '¿Qué son los Addons?', 'boletines' ); ?></h3>
				<p style="margin:0 0 8px;color:#475569;line-height:1.6;">
					<?php esc_html_e( 'Los addons son módulos Pro incluidos en el plugin Digest. Vienen empaquetados pero sólo se activan cuando tienes licencia y los habilitas aquí.', 'boletines' ); ?>
				</p>
				<p style="margin:0;color:#94a3b8;font-size:13px;font-style:italic;">
					<?php esc_html_e( 'Próximamente: más módulos en futuras versiones.', 'boletines' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_card( string $slug, ModuleInterface $module ): void {
		$is_active = Modules::instance()->is_active( $slug );
		$is_locked = Modules::instance()->is_locked( $slug );

		$state_label = $is_active
			? __( 'Activo', 'boletines' )
			: ( $is_locked ? __( 'Requiere licencia Pro', 'boletines' ) : __( 'Inactivo', 'boletines' ) );
		$state_color = $is_active ? '#16a34a' : ( $is_locked ? '#94a3b8' : '#64748b' );
		$state_bg    = $is_active ? '#dcfce7' : ( $is_locked ? '#f1f5f9' : '#f8fafc' );
		?>
		<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:22px;display:flex;flex-direction:column;<?php if ( $is_locked ) echo 'opacity:.78;'; ?>">
			<div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">
				<div style="width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,#7c3aed 0%,#a78bfa 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
					<span class="dashicons <?php echo esc_attr( $module->icon() ); ?>" style="color:#fff;font-size:24px;width:24px;height:24px;"></span>
				</div>
				<div style="flex:1;min-width:0;">
					<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
						<h3 style="margin:0;font-size:16px;font-weight:600;color:#111827;"><?php echo esc_html( $module->name() ); ?></h3>
						<?php if ( $module->requires_pro() ) : ?>
							<span style="background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;letter-spacing:.5px;padding:3px 8px;border-radius:4px;text-transform:uppercase;">PRO</span>
						<?php endif; ?>
					</div>
					<span style="display:inline-block;font-size:12px;font-weight:500;padding:3px 10px;border-radius:99px;color:<?php echo esc_attr( $state_color ); ?>;background:<?php echo esc_attr( $state_bg ); ?>;">
						● <?php echo esc_html( $state_label ); ?>
					</span>
				</div>
			</div>

			<p style="margin:0 0 18px;color:#4b5563;line-height:1.55;font-size:13.5px;flex:1;">
				<?php echo esc_html( $module->description() ); ?>
			</p>

			<div style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #f1f5f9;padding-top:14px;">
				<?php $this->render_actions( $slug, $module, $is_active, $is_locked ); ?>
			</div>
		</div>
		<?php
	}

	private function render_actions( string $slug, ModuleInterface $module, bool $is_active, bool $is_locked ): void {
		if ( $is_locked ) {
			?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=boletines-license' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Activar licencia', 'boletines' ); ?>
			</a>
			<?php
			return;
		}

		$toggle_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=boletines_module_toggle&module=' . $slug . '&op=' . ( $is_active ? 'deactivate' : 'activate' ) ),
			'boletines_module_toggle_' . $slug
		);

		if ( $is_active ) {
			$settings_url = $module->settings_url();
			if ( $settings_url !== '' ) {
				?>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configurar', 'boletines' ); ?>
				</a>
				<?php
			}
			?>
			<a href="<?php echo esc_url( $toggle_url ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( '¿Desactivar este módulo? Sus datos se conservan.', 'boletines' ) ); ?>');">
				<?php esc_html_e( 'Desactivar', 'boletines' ); ?>
			</a>
			<?php
		} else {
			?>
			<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Activar', 'boletines' ); ?>
			</a>
			<?php
		}
	}

	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		$slug = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
		$op   = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : '';
		check_admin_referer( 'boletines_module_toggle_' . $slug );

		$mgr = Modules::instance();
		$msg = '';

		if ( $op === 'activate' ) {
			if ( $mgr->is_locked( $slug ) ) {
				$msg = 'locked';
			} elseif ( $mgr->activate( $slug ) ) {
				$msg = 'activated';
			}
		} elseif ( $op === 'deactivate' ) {
			if ( $mgr->deactivate( $slug ) ) {
				$msg = 'deactivated';
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-addons&msg=' . $msg ) );
		exit;
	}
}
