<?php
namespace Boletines\Admin\Pages;

use Boletines\Models\ListModel;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public function render(): void {
		$saved = isset( $_GET['saved'] ) ? (int) $_GET['saved'] : 0;
		$lists = ListModel::all();
		$tab   = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

		$tabs = array(
			'general'    => __( 'General', 'boletines' ),
			'sending'    => __( 'Envío', 'boletines' ),
			'optin'      => __( 'Doble opt-in', 'boletines' ),
			'forms'      => __( 'Estilo formularios', 'boletines' ),
			'branding'   => __( 'Branding correos', 'boletines' ),
			'woocommerce'=> __( 'WooCommerce', 'boletines' ),
			'sync'       => __( 'Sincronización WP', 'boletines' ),
			'test'       => __( 'Probar SMTP', 'boletines' ),
		);
		?>
		<div class="wrap boletines-wrap">
			<div class="boletines-header">
				<h1><?php esc_html_e( 'Ajustes', 'boletines' ); ?></h1>
			</div>

			<?php if ( $saved ) : ?>
				<div class="bol-notice success"><?php esc_html_e( 'Ajustes guardados.', 'boletines' ); ?></div>
			<?php endif; ?>

			<div class="bol-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, admin_url( 'admin.php?page=boletines-settings' ) ) ); ?>" class="<?php echo $tab === $key ? 'active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>

			<div class="bol-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bol-form">
					<input type="hidden" name="action" value="boletines_save_settings">
					<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
					<?php wp_nonce_field( 'boletines_save_settings' ); ?>

					<?php if ( $tab === 'general' ) : ?>
						<div class="row row-2">
							<div class="row">
								<label><?php esc_html_e( 'Nombre del remitente', 'boletines' ); ?></label>
								<input type="text" name="from_name" value="<?php echo esc_attr( Plugin::get_setting( 'from_name', get_bloginfo( 'name' ) ) ); ?>">
							</div>
							<div class="row">
								<label><?php esc_html_e( 'Email del remitente', 'boletines' ); ?></label>
								<input type="email" name="from_email" value="<?php echo esc_attr( Plugin::get_setting( 'from_email', get_option( 'admin_email' ) ) ); ?>">
								<span class="help"><?php esc_html_e( 'Usa un dominio que coincida con tu sitio (mejor para SPF/DKIM).', 'boletines' ); ?></span>
							</div>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Reply-To', 'boletines' ); ?></label>
							<input type="email" name="reply_to" value="<?php echo esc_attr( Plugin::get_setting( 'reply_to', get_option( 'admin_email' ) ) ); ?>">
						</div>

					<?php elseif ( $tab === 'sending' ) : ?>
						<div class="row row-2">
							<div class="row">
								<label><?php esc_html_e( 'Velocidad (correos/hora)', 'boletines' ); ?></label>
								<input type="number" name="speed_per_hour" min="12" max="100000" value="<?php echo (int) Plugin::get_setting( 'speed_per_hour', 240 ); ?>">
								<span class="help"><?php esc_html_e( 'Empieza con 240 (4/min). Sube si tu hosting/SMTP lo aguanta.', 'boletines' ); ?></span>
							</div>
							<div class="row">
								<label><?php esc_html_e( 'Tamaño de lote por cron', 'boletines' ); ?></label>
								<input type="number" name="batch_size" min="1" max="500" value="<?php echo (int) Plugin::get_setting( 'batch_size', 20 ); ?>">
								<span class="help"><?php esc_html_e( 'Cuántos correos enviar por cada ejecución (cada minuto). La cola se procesa sola y continúa automáticamente hasta terminar, respetando la velocidad/hora.', 'boletines' ); ?></span>
							</div>
						</div>
						<div class="bol-notice info" style="margin-top:8px;">
							<strong><?php esc_html_e( 'Envío 100% automático (opcional pero recomendado en hosting compartido):', 'boletines' ); ?></strong>
							<p style="margin:6px 0;"><?php esc_html_e( 'WordPress solo ejecuta su cron cuando alguien visita el sitio. Para garantizar el envío aunque no haya visitas, crea un cron real en Hostinger (hPanel → Cron Jobs) cada minuto que llame a esta URL:', 'boletines' ); ?></p>
							<code style="display:block;word-break:break-all;background:#0f172a;color:#a7f3d0;padding:8px 10px;border-radius:6px;font-size:12px;"><?php echo esc_html( \Boletines\Mailer\Cron::drain_url() ); ?></code>
							<p style="margin:6px 0 0;"><?php esc_html_e( 'Comando cron sugerido:', 'boletines' ); ?> <code>curl -s "<?php echo esc_url( \Boletines\Mailer\Cron::drain_url() ); ?>" >/dev/null 2>&1</code></p>
						</div>
						<div class="bol-notice info" style="margin-top:8px;">
							<strong><?php esc_html_e( 'Recomendación:', 'boletines' ); ?></strong>
							<?php esc_html_e( 'Para enviar volumen alto, instala FluentSMTP y conéctalo con Amazon SES o Brevo. El plugin enviará por wp_mail() así que cualquier configuración SMTP funciona.', 'boletines' ); ?>
						</div>

						<hr style="margin:20px 0;border:0;border-top:1px solid #e5e7eb;">

						<h3 style="margin:0 0 8px;"><?php esc_html_e( 'Política de bounces', 'boletines' ); ?></h3>
						<div class="row">
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
								<input type="checkbox" name="auto_unsub_hard_bounce" value="1" <?php checked( (int) Plugin::get_setting( 'auto_unsub_hard_bounce', 1 ) === 1 ); ?>>
								<?php esc_html_e( 'Marcar como bounced automáticamente al recibir un hard bounce o queja', 'boletines' ); ?>
							</label>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Umbral de soft bounces (en 30 días) para marcar como bounced', 'boletines' ); ?></label>
							<input type="number" name="soft_bounce_threshold" min="1" max="20" value="<?php echo (int) Plugin::get_setting( 'soft_bounce_threshold', 3 ); ?>">
						</div>

						<hr style="margin:24px 0;border:0;border-top:1px solid #e5e7eb;">

						<?php
						$hk = null;
						try { if ( class_exists( '\\Boletines\\Housekeeping\\Cleaner' ) ) $hk = \Boletines\Housekeeping\Cleaner::settings(); }
						catch ( \Throwable $e ) { $hk = null; }
						if ( $hk !== null ) : ?>
							<h3 style="margin:0 0 8px;"><?php esc_html_e( 'Limpieza automática de la lista', 'boletines' ); ?></h3>
							<p style="color:#6b7280;font-size:13px;"><?php esc_html_e( 'Elimina permanentemente inactivos y rebotados. Cron diario a las 02:00.', 'boletines' ); ?></p>
							<div class="row"><label style="display:flex;align-items:center;gap:8px;font-weight:400;"><input type="checkbox" name="hk_enabled" value="1" <?php checked( (int) $hk['enabled'] === 1 ); ?>> <strong><?php esc_html_e( 'Activar limpieza automática diaria', 'boletines' ); ?></strong></label></div>
							<div class="row"><label style="display:flex;align-items:center;gap:8px;font-weight:400;color:#92400e;"><input type="checkbox" name="hk_dry_run" value="1" <?php checked( (int) $hk['dry_run'] === 1 ); ?>> <?php esc_html_e( 'Modo simulación (cuenta pero no borra)', 'boletines' ); ?></label></div>
							<div class="row"><label><?php esc_html_e( 'Eliminar inactivos: días sin abrir ningún correo', 'boletines' ); ?></label><input type="number" name="hk_inactive_days" min="30" max="3650" value="<?php echo (int) $hk['inactive_days']; ?>"></div>
							<div class="row"><label><?php esc_html_e( 'Mínimo campañas recibidas antes de considerar inactivo', 'boletines' ); ?></label><input type="number" name="hk_inactive_min_sent" min="1" max="50" value="<?php echo (int) $hk['inactive_min_sent']; ?>"></div>
							<div class="row"><label><?php esc_html_e( 'Eliminar rebotados: número de bounces', 'boletines' ); ?></label><input type="number" name="hk_bounce_threshold" min="1" max="20" value="<?php echo (int) $hk['bounce_threshold']; ?>"></div>
							<?php if ( ! empty( $hk['last_run'] ) ) :
								$sum = json_decode( (string) $hk['last_run_summary'], true ); ?>
								<div class="bol-notice info"><strong><?php esc_html_e( 'Última ejecución:', 'boletines' ); ?></strong> <?php echo esc_html( $hk['last_run'] ); ?>
									<?php if ( is_array( $sum ) ) :
										printf( ' — inactivos: %d · rebotados: %d · eliminados: %d', (int) $sum['inactive_found'], (int) $sum['bounced_found'], (int) $sum['deleted'] );
										if ( ! empty( $sum['dry_run'] ) ) echo ' <em>(simulación)</em>';
									endif; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>

					<?php elseif ( $tab === 'optin' ) : ?>
						<div class="row">
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
								<input type="checkbox" name="double_optin" value="1" <?php checked( (int) Plugin::get_setting( 'double_optin', 1 ) === 1 ); ?>>
								<?php esc_html_e( 'Activar doble opt-in (enviar correo de confirmación)', 'boletines' ); ?>
							</label>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Asunto del correo de confirmación', 'boletines' ); ?></label>
							<input type="text" name="confirm_subject" value="<?php echo esc_attr( Plugin::get_setting( 'confirm_subject', 'Confirma tu suscripción' ) ); ?>">
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Cuerpo del correo de confirmación', 'boletines' ); ?></label>
							<textarea name="confirm_body" rows="8"><?php echo esc_textarea( Plugin::get_setting( 'confirm_body', '' ) ); ?></textarea>
							<span class="help"><?php esc_html_e( 'Variables: {confirmation_url}, {first_name}, {site_name}', 'boletines' ); ?></span>
						</div>

						<hr style="margin:20px 0;border:0;border-top:1px solid #e5e7eb;">

						<div class="row">
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
								<input type="checkbox" name="welcome_enabled" value="1" <?php checked( (int) Plugin::get_setting( 'welcome_enabled', 0 ) === 1 ); ?>>
								<?php esc_html_e( 'Enviar correo de bienvenida tras la confirmación', 'boletines' ); ?>
							</label>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Asunto de bienvenida', 'boletines' ); ?></label>
							<input type="text" name="welcome_subject" value="<?php echo esc_attr( Plugin::get_setting( 'welcome_subject', '¡Bienvenido!' ) ); ?>">
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Cuerpo de bienvenida', 'boletines' ); ?></label>
							<textarea name="welcome_body" rows="6"><?php echo esc_textarea( Plugin::get_setting( 'welcome_body', '' ) ); ?></textarea>
						</div>

					<?php elseif ( $tab === 'forms' ) : ?>
						<?php
						$detected = Plugin::detect_theme_primary_color();
						$current  = (string) Plugin::get_setting( 'form_button_color', 'auto' );
						$current_text = (string) Plugin::get_setting( 'form_button_text_color', '#ffffff' );
						?>
						<div class="bol-notice info">
							<strong><?php esc_html_e( 'Color principal detectado en tu tema:', 'boletines' ); ?></strong>
							<?php if ( $detected ) : ?>
								<span style="display:inline-block;width:18px;height:18px;border-radius:4px;border:1px solid #ccc;vertical-align:middle;background:<?php echo esc_attr( $detected ); ?>;margin:0 6px;"></span>
								<code><?php echo esc_html( $detected ); ?></code>
							<?php else : ?>
								<?php esc_html_e( 'No se pudo detectar (el tema no expone palette en theme.json ni editor-color-palette).', 'boletines' ); ?>
							<?php endif; ?>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Color del botón', 'boletines' ); ?></label>
							<select name="form_button_color_mode" id="bol-color-mode">
								<option value="auto"   <?php selected( $current, 'auto' ); ?>><?php esc_html_e( 'Auto — usar el color principal del tema', 'boletines' ); ?></option>
								<option value="custom" <?php selected( $current !== 'auto' && $current !== '' ); ?>><?php esc_html_e( 'Personalizado', 'boletines' ); ?></option>
							</select>
						</div>

						<div class="row" id="bol-color-custom" style="<?php echo $current === 'auto' ? 'display:none;' : ''; ?>">
							<label><?php esc_html_e( 'Color personalizado del botón', 'boletines' ); ?></label>
							<div style="display:flex;gap:8px;align-items:center;">
								<input type="color" name="form_button_color_hex" value="<?php echo esc_attr( $current !== 'auto' && $current ? $current : ( $detected ?: '#2563eb' ) ); ?>" style="width:60px;height:40px;padding:2px;cursor:pointer;">
								<input type="text" name="form_button_color_text" value="<?php echo esc_attr( $current !== 'auto' && $current ? $current : '' ); ?>" placeholder="#2563eb" style="font-family:monospace;max-width:120px;">
							</div>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Color del texto del botón', 'boletines' ); ?></label>
							<div style="display:flex;gap:8px;align-items:center;">
								<input type="color" name="form_button_text_color_hex" value="<?php echo esc_attr( $current_text ); ?>" style="width:60px;height:40px;padding:2px;cursor:pointer;">
								<input type="text" name="form_button_text_color_text" value="<?php echo esc_attr( $current_text ); ?>" style="font-family:monospace;max-width:120px;">
							</div>
							<span class="help"><?php esc_html_e( 'Normalmente blanco, salvo que el color de fondo sea muy claro.', 'boletines' ); ?></span>
						</div>

						<div class="bol-card" style="margin-top:16px;background:#f9fafb;">
							<h2 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;"><?php esc_html_e( 'Vista previa', 'boletines' ); ?></h2>
							<button type="button" id="bol-preview-btn" style="padding:12px 22px;border:0;border-radius:10px;font-weight:600;font-size:15px;cursor:default;background:<?php echo esc_attr( $current !== 'auto' && $current ? $current : ( $detected ?: '#2563eb' ) ); ?>;color:<?php echo esc_attr( $current_text ); ?>;"><?php esc_html_e( 'Suscribirme', 'boletines' ); ?></button>
						</div>

						<script>
						jQuery(function($){
							function updatePreview(){
								var bg = $('input[name=form_button_color_text]').val() || $('input[name=form_button_color_hex]').val();
								var fg = $('input[name=form_button_text_color_text]').val() || $('input[name=form_button_text_color_hex]').val();
								if (/^#[0-9a-fA-F]{6}$/.test(bg)) $('#bol-preview-btn').css('background', bg);
								if (/^#[0-9a-fA-F]{6}$/.test(fg)) $('#bol-preview-btn').css('color', fg);
							}
							$('input[name=form_button_color_hex]').on('input change', function(){ $('input[name=form_button_color_text]').val(this.value); updatePreview(); });
							$('input[name=form_button_color_text]').on('input change', function(){ if (/^#[0-9a-fA-F]{6}$/.test(this.value)) $('input[name=form_button_color_hex]').val(this.value); updatePreview(); });
							$('input[name=form_button_text_color_hex]').on('input change', function(){ $('input[name=form_button_text_color_text]').val(this.value); updatePreview(); });
							$('input[name=form_button_text_color_text]').on('input change', function(){ if (/^#[0-9a-fA-F]{6}$/.test(this.value)) $('input[name=form_button_text_color_hex]').val(this.value); updatePreview(); });
							$('#bol-color-mode').on('change', function(){ $('#bol-color-custom').toggle($(this).val() === 'custom'); });
						});
						</script>

					<?php elseif ( $tab === 'branding' ) : ?>
						<?php
						$logo_url     = (string) Plugin::get_setting( 'email_logo_url', '' );
						$logo_width   = (int) Plugin::get_setting( 'email_logo_width', 160 );
						$brand_color  = (string) Plugin::get_setting( 'email_brand_color', '' );
						$social_links = (string) Plugin::get_setting( 'email_social_links', '' );
						$pref_page    = (int) Plugin::get_setting( 'preferences_page_id', 0 );
						$pages        = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'number' => 200 ) );
						?>
						<div class="bol-notice info">
							<strong><?php esc_html_e( 'Estos ajustes se aplican a TODOS los correos:', 'boletines' ); ?></strong>
							<?php esc_html_e( 'confirmaciones de suscripción, campañas, automatizaciones (RSS, digests). Una sola configuración para todo.', 'boletines' ); ?>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Logotipo del correo', 'boletines' ); ?></label>
							<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
								<input type="url" name="email_logo_url" id="bol-logo-url" value="<?php echo esc_attr( $logo_url ); ?>" placeholder="https://..." style="flex:1;">
								<button type="button" class="bol-btn bol-btn-secondary" id="bol-pick-logo"><?php esc_html_e( 'Elegir desde mediateca', 'boletines' ); ?></button>
							</div>
							<div id="bol-logo-preview" style="<?php echo empty( $logo_url ) ? 'display:none;' : ''; ?>padding:14px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;text-align:center;">
								<?php if ( $logo_url ) : ?>
									<img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:100%;height:auto;width:<?php echo (int) $logo_width; ?>px;display:inline-block;">
								<?php endif; ?>
							</div>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Ancho del logotipo (px)', 'boletines' ); ?></label>
							<input type="number" name="email_logo_width" id="bol-logo-width" min="40" max="600" value="<?php echo (int) $logo_width; ?>" style="max-width:120px;">
							<span class="help"><?php esc_html_e( 'Ancho en píxeles. Entre 40 y 600. La altura se ajusta proporcionalmente.', 'boletines' ); ?></span>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Color de marca para correos (opcional)', 'boletines' ); ?></label>
							<div style="display:flex;gap:8px;align-items:center;">
								<input type="color" name="email_brand_color_hex" value="<?php echo esc_attr( $brand_color ?: '#2563eb' ); ?>" style="width:60px;height:40px;padding:2px;cursor:pointer;">
								<input type="text" name="email_brand_color" value="<?php echo esc_attr( $brand_color ); ?>" placeholder="<?php esc_attr_e( 'Vacío = usar el color del botón', 'boletines' ); ?>" style="font-family:monospace;max-width:160px;">
							</div>
							<span class="help"><?php esc_html_e( 'Se usa para enlaces y títulos en el correo. Si lo dejas vacío, se usa el mismo color del botón de los formularios.', 'boletines' ); ?></span>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Redes sociales y enlaces del pie (separados por |)', 'boletines' ); ?></label>
							<textarea name="email_social_links" rows="6" placeholder="<?php esc_attr_e( 'Una por línea. Formato: Etiqueta|URL', 'boletines' ); ?>" style="font-family:monospace;font-size:13px;"><?php echo esc_textarea( $social_links ); ?></textarea>
							<span class="help">
								<strong><?php esc_html_e( 'Formato:', 'boletines' ); ?></strong>
								<?php esc_html_e( 'una entrada por línea. Cada línea: etiqueta + separador (| , : o tab) + URL. Ejemplos:', 'boletines' ); ?>
								<br>
								<code>Facebook|https://facebook.com/cadaidea</code><br>
								<code>Instagram|https://instagram.com/cadaidea</code><br>
								<code>LinkedIn|https://linkedin.com/company/cadaidea</code><br>
								<code>YouTube|https://youtube.com/@cadaidea</code><br>
								<?php esc_html_e( 'Aparecerán en una sola línea separados por |, sobre el aviso de baja.', 'boletines' ); ?>
							</span>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Texto extra del pie (centrado bajo las redes)', 'boletines' ); ?></label>
							<?php $footer_text = (string) Plugin::get_setting( 'email_footer_text', '' ); ?>
							<div style="margin:8px 0 10px;">
								<div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;"><?php esc_html_e( 'Insertar variable (haz clic para añadir al texto):', 'boletines' ); ?></div>
								<div style="display:block;line-height:2.2;">
									<button type="button" class="button button-small bol-insert-var" data-target="email_footer_text" data-var="{first_name}" style="margin-right:4px;font-family:monospace;">+ {first_name}</button>
									<button type="button" class="button button-small bol-insert-var" data-target="email_footer_text" data-var="{last_name}" style="margin-right:4px;font-family:monospace;">+ {last_name}</button>
									<button type="button" class="button button-small bol-insert-var" data-target="email_footer_text" data-var="{full_name}" style="margin-right:4px;font-family:monospace;">+ {full_name}</button>
									<button type="button" class="button button-small bol-insert-var" data-target="email_footer_text" data-var="{site_name}" style="margin-right:4px;font-family:monospace;">+ {site_name}</button>
									<button type="button" class="button button-small bol-insert-var" data-target="email_footer_text" data-var="{site_url}" style="margin-right:4px;font-family:monospace;">+ {site_url}</button>
									<button type="button" class="button button-small bol-insert-var" data-target="email_footer_text" data-var="{current_year}" style="margin-right:4px;font-family:monospace;">+ {current_year}</button>
								</div>
							</div>
							<textarea name="email_footer_text" id="email_footer_text" rows="4" placeholder="<?php esc_attr_e( 'Ej: © {current_year} {site_name}. Hola {first_name}, visita https://cadaidea.com/blog', 'boletines' ); ?>"><?php echo esc_textarea( $footer_text ); ?></textarea>
							<span class="help">
								<?php esc_html_e( 'Texto libre. Las URLs que pegues como', 'boletines' ); ?>
								<code>https://cadaidea.com/contacto</code>
								<?php esc_html_e( 'se convierten automáticamente en enlaces — no necesitas escribir HTML. Las variables como', 'boletines' ); ?>
								<code>{first_name}</code>
								<?php esc_html_e( 'se reemplazan con los datos del suscriptor al enviar.', 'boletines' ); ?>
							</span>
						</div>

						<div class="row">
							<label><?php esc_html_e( 'Enlaces del pie (uno por línea: Etiqueta|URL)', 'boletines' ); ?></label>
							<?php $footer_links = (string) Plugin::get_setting( 'email_footer_links', '' ); ?>
							<textarea name="email_footer_links" rows="5" placeholder="<?php esc_attr_e( "Blog|https://cadaidea.com/blog\nContacto|https://cadaidea.com/contacto\nPrivacidad|https://cadaidea.com/privacidad", 'boletines' ); ?>" style="font-family:monospace;font-size:13px;"><?php echo esc_textarea( $footer_links ); ?></textarea>
							<span class="help">
								<?php esc_html_e( 'Una línea por enlace. Formato:', 'boletines' ); ?>
								<code>Etiqueta|URL</code>.
								<?php esc_html_e( 'Aparecen centrados, separados por · entre el texto del pie y el aviso de baja. Mismo formato que las redes sociales, pero con bullet · en vez de pipe |.', 'boletines' ); ?>
							</span>
						</div>

						<script>
						document.addEventListener('click', function(e) {
							if (!e.target.classList.contains('bol-insert-var')) return;
							e.preventDefault();
							var ta = document.getElementById(e.target.dataset.target);
							if (!ta) return;
							var v = e.target.dataset.var;
							var start = ta.selectionStart || 0;
							var end = ta.selectionEnd || 0;
							ta.value = ta.value.substring(0, start) + v + ta.value.substring(end);
							ta.selectionStart = ta.selectionEnd = start + v.length;
							ta.focus();
						});
						</script>

						<div class="row">
							<label><?php esc_html_e( 'Página de "Gestionar preferencias"', 'boletines' ); ?></label>
							<select name="preferences_page_id">
								<option value="0">— <?php esc_html_e( 'Ninguna (usar página standalone automática)', 'boletines' ); ?> —</option>
								<?php foreach ( $pages as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>" <?php selected( $pref_page, (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="help">
								<?php esc_html_e( 'Crea una página en WordPress, pega dentro el shortcode', 'boletines' ); ?>
								<code>[boletines_preferences]</code>
								<?php esc_html_e( 'y selecciónala aquí. Si no eliges ninguna, el plugin sirve la página por su cuenta.', 'boletines' ); ?>
							</span>
						</div>

						<script>
						jQuery(function($){
							$('#bol-pick-logo').on('click', function(e){
								e.preventDefault();
								var frame = wp.media({ title: 'Elegir logotipo', button: { text: 'Usar este logo' }, multiple: false });
								frame.on('select', function(){
									var att = frame.state().get('selection').first().toJSON();
									$('#bol-logo-url').val( att.url );
									$('#bol-logo-preview').show().html('<img src="'+ att.url +'" style="max-width:100%;height:auto;width:'+ ($('#bol-logo-width').val() || 160) +'px;display:inline-block;">');
								});
								frame.open();
							});
							$('input[name=email_brand_color_hex]').on('input change', function(){ $('input[name=email_brand_color]').val(this.value); });
							$('input[name=email_brand_color]').on('input change', function(){ if (/^#[0-9a-fA-F]{6}$/.test(this.value)) $('input[name=email_brand_color_hex]').val(this.value); });
						});
						</script>

					<?php elseif ( $tab === 'woocommerce' ) : ?>
						<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
							<div class="bol-notice warning"><?php esc_html_e( 'WooCommerce no está activo en este sitio.', 'boletines' ); ?></div>
						<?php endif; ?>
						<div class="row">
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
								<input type="checkbox" name="wc_optin_enabled" value="1" <?php checked( (int) Plugin::get_setting( 'wc_optin_enabled', 1 ) === 1 ); ?>>
								<?php esc_html_e( 'Mostrar casilla de suscripción en el checkout', 'boletines' ); ?>
							</label>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Texto de la casilla', 'boletines' ); ?></label>
							<input type="text" name="wc_optin_label" value="<?php echo esc_attr( Plugin::get_setting( 'wc_optin_label', __( 'Quiero recibir novedades y ofertas por correo', 'boletines' ) ) ); ?>">
						</div>
						<div class="row">
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
								<input type="checkbox" name="wc_require_optin" value="1" <?php checked( (int) Plugin::get_setting( 'wc_require_optin', 1 ) === 1 ); ?>>
								<?php esc_html_e( 'Sólo suscribir si el cliente marca la casilla (recomendado para GDPR)', 'boletines' ); ?>
							</label>
							<span class="help"><?php esc_html_e( 'Si lo desactivas, todos los compradores quedarán suscritos automáticamente. Hazlo sólo si tu política de privacidad lo cubre.', 'boletines' ); ?></span>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Lista destino para clientes Woo', 'boletines' ); ?></label>
							<select name="wc_target_list">
								<option value="0">— <?php esc_html_e( 'Primera lista disponible', 'boletines' ); ?></option>
								<?php foreach ( $lists as $l ) : ?>
									<option value="<?php echo (int) $l['id']; ?>" <?php selected( (int) Plugin::get_setting( 'wc_target_list', 0 ), (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

					<?php elseif ( $tab === 'test' ) : ?>
						<p><?php esc_html_e( 'Envía un correo de prueba para comprobar tu configuración SMTP.', 'boletines' ); ?></p>
						<div class="row" style="max-width:400px;">
							<label><?php esc_html_e( 'Enviar a', 'boletines' ); ?></label>
							<input type="email" id="bol-test-email-to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
						</div>
						<div>
							<button type="button" class="bol-btn bol-test-email"><?php esc_html_e( 'Enviar prueba', 'boletines' ); ?></button>
						</div>
					<?php elseif ( $tab === 'sync' ) : ?>
						<?php $sync_count = get_transient( 'boletines_wp_sync_count_' . get_current_user_id() ); ?>
						<?php if ( isset( $_GET['synced'] ) && $sync_count !== false ) : delete_transient( 'boletines_wp_sync_count_' . get_current_user_id() ); ?>
							<div class="bol-notice success" style="margin-bottom:16px;"><?php printf( esc_html__( 'Sincronizados %d usuarios.', 'boletines' ), (int) $sync_count ); ?></div>
						<?php endif; ?>

						<div class="row">
							<label style="display:flex;align-items:center;gap:8px;font-weight:400;">
								<input type="checkbox" name="wp_users_sync" value="1" <?php checked( (int) Plugin::get_setting( 'wp_users_sync', 0 ) === 1 ); ?>>
								<?php esc_html_e( 'Suscribir automáticamente a los nuevos usuarios de WordPress', 'boletines' ); ?>
							</label>
							<span class="help"><?php esc_html_e( 'Cada vez que alguien se registre en tu sitio (rol cualquiera), se creará un suscriptor.', 'boletines' ); ?></span>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Estado inicial', 'boletines' ); ?></label>
							<select name="wp_users_status">
								<option value="pending"   <?php selected( Plugin::get_setting( 'wp_users_status', 'pending' ), 'pending' );   ?>><?php esc_html_e( 'Pendiente (envía correo de confirmación)', 'boletines' ); ?></option>
								<option value="confirmed" <?php selected( Plugin::get_setting( 'wp_users_status', 'pending' ), 'confirmed' ); ?>><?php esc_html_e( 'Confirmado directamente', 'boletines' ); ?></option>
							</select>
						</div>
						<div class="row">
							<label><?php esc_html_e( 'Lista destino', 'boletines' ); ?></label>
							<select name="wp_users_list">
								<option value="0">— <?php esc_html_e( 'Ninguna', 'boletines' ); ?> —</option>
								<?php foreach ( $lists as $l ) : ?>
									<option value="<?php echo (int) $l['id']; ?>" <?php selected( (int) Plugin::get_setting( 'wp_users_list', 0 ), (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div style="margin-top:24px;padding-top:20px;border-top:1px solid #f3f4f6;">
							<h3 style="margin:0 0 8px;"><?php esc_html_e( 'Sincronización masiva (una sola vez)', 'boletines' ); ?></h3>
							<p style="color:#6b7280;margin:0 0 12px;"><?php esc_html_e( 'Importa todos los usuarios actuales como suscriptores con la configuración de arriba.', 'boletines' ); ?></p>
						</div>
						<!-- El botón de sync masivo va fuera del form principal abajo. -->
					<?php endif; ?>

					<?php if ( $tab !== 'test' ) : ?>
						<div style="margin-top:20px;border-top:1px solid #f3f4f6;padding-top:16px;">
							<button type="submit" class="bol-btn"><?php esc_html_e( 'Guardar ajustes', 'boletines' ); ?></button>
						</div>
					<?php endif; ?>
				</form>

				<?php if ( $tab === 'sync' ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
						<input type="hidden" name="action" value="boletines_sync_wp_users">
						<?php wp_nonce_field( 'boletines_sync_wp_users' ); ?>
						<button type="submit" class="bol-btn bol-btn-secondary"><?php esc_html_e( 'Sincronizar ahora todos los usuarios actuales', 'boletines' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		}
		check_admin_referer( 'boletines_save_settings' );

		$tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : 'general';

		$settings = get_option( 'boletines_settings', array() );

		if ( $tab === 'general' ) {
			$settings['from_name']  = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';
			$settings['from_email'] = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
			$settings['reply_to']   = isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '';
		} elseif ( $tab === 'sending' ) {
			$settings['speed_per_hour']         = max( 12, (int) ( $_POST['speed_per_hour'] ?? 240 ) );
			$settings['batch_size']             = max( 1, (int) ( $_POST['batch_size'] ?? 20 ) );
			$settings['auto_unsub_hard_bounce'] = isset( $_POST['auto_unsub_hard_bounce'] ) ? 1 : 0;
			$settings['soft_bounce_threshold']  = max( 1, (int) ( $_POST['soft_bounce_threshold'] ?? 3 ) );

			// v2.0 — housekeeping
			try {
				if ( class_exists( '\\Boletines\\Housekeeping\\Cleaner' ) ) {
					\Boletines\Housekeeping\Cleaner::save_settings( array(
						'enabled'           => isset( $_POST['hk_enabled'] ) ? 1 : 0,
						'dry_run'           => isset( $_POST['hk_dry_run'] ) ? 1 : 0,
						'inactive_days'     => max( 30, (int) ( $_POST['hk_inactive_days']     ?? 180 ) ),
						'inactive_min_sent' => max( 1,  (int) ( $_POST['hk_inactive_min_sent'] ?? 3 ) ),
						'bounce_threshold'  => max( 1,  (int) ( $_POST['hk_bounce_threshold']  ?? 3 ) ),
					) );
				}
			} catch ( \Throwable $e ) { error_log( '[Boletines 2.0] HK save: ' . $e->getMessage() ); }
		} elseif ( $tab === 'optin' ) {
			$settings['double_optin']    = isset( $_POST['double_optin'] ) ? 1 : 0;
			$settings['confirm_subject'] = isset( $_POST['confirm_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_subject'] ) ) : '';
			$settings['confirm_body']    = isset( $_POST['confirm_body'] ) ? wp_kses_post( wp_unslash( $_POST['confirm_body'] ) ) : '';
			$settings['welcome_enabled'] = isset( $_POST['welcome_enabled'] ) ? 1 : 0;
			$settings['welcome_subject'] = isset( $_POST['welcome_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['welcome_subject'] ) ) : '';
			$settings['welcome_body']    = isset( $_POST['welcome_body'] ) ? wp_kses_post( wp_unslash( $_POST['welcome_body'] ) ) : '';
		} elseif ( $tab === 'woocommerce' ) {
			$settings['wc_optin_enabled'] = isset( $_POST['wc_optin_enabled'] ) ? 1 : 0;
			$settings['wc_require_optin'] = isset( $_POST['wc_require_optin'] ) ? 1 : 0;
			$settings['wc_optin_label']   = isset( $_POST['wc_optin_label'] ) ? sanitize_text_field( wp_unslash( $_POST['wc_optin_label'] ) ) : '';
			$settings['wc_target_list']   = isset( $_POST['wc_target_list'] ) ? (int) $_POST['wc_target_list'] : 0;
		} elseif ( $tab === 'sync' ) {
			$settings['wp_users_sync']   = isset( $_POST['wp_users_sync'] ) ? 1 : 0;
			$settings['wp_users_status'] = isset( $_POST['wp_users_status'] ) && in_array( $_POST['wp_users_status'], array( 'pending', 'confirmed' ), true )
				? sanitize_text_field( wp_unslash( $_POST['wp_users_status'] ) )
				: 'pending';
			$settings['wp_users_list']   = isset( $_POST['wp_users_list'] ) ? (int) $_POST['wp_users_list'] : 0;
		} elseif ( $tab === 'forms' ) {
			$mode = isset( $_POST['form_button_color_mode'] ) ? $_POST['form_button_color_mode'] : 'auto';
			if ( $mode === 'auto' ) {
				$settings['form_button_color'] = 'auto';
			} else {
				$hex = isset( $_POST['form_button_color_text'] ) ? trim( wp_unslash( $_POST['form_button_color_text'] ) ) : '';
				if ( $hex === '' && isset( $_POST['form_button_color_hex'] ) ) {
					$hex = trim( wp_unslash( $_POST['form_button_color_hex'] ) );
				}
				$settings['form_button_color'] = preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ? strtolower( $hex ) : 'auto';
			}
			$txt = isset( $_POST['form_button_text_color_text'] ) ? trim( wp_unslash( $_POST['form_button_text_color_text'] ) ) : '';
			if ( $txt === '' && isset( $_POST['form_button_text_color_hex'] ) ) {
				$txt = trim( wp_unslash( $_POST['form_button_text_color_hex'] ) );
			}
			$settings['form_button_text_color'] = preg_match( '/^#[0-9a-fA-F]{6}$/', $txt ) ? strtolower( $txt ) : '#ffffff';
		} elseif ( $tab === 'branding' ) {
			$settings['email_logo_url']    = isset( $_POST['email_logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['email_logo_url'] ) ) : '';
			$settings['email_logo_width'] = max( 40, min( 600, (int) ( $_POST['email_logo_width'] ?? 160 ) ) );

			$brand = isset( $_POST['email_brand_color'] ) ? trim( wp_unslash( $_POST['email_brand_color'] ) ) : '';
			if ( $brand === '' && isset( $_POST['email_brand_color_hex'] ) ) {
				$brand = trim( wp_unslash( $_POST['email_brand_color_hex'] ) );
			}
			$settings['email_brand_color'] = preg_match( '/^#[0-9a-fA-F]{6}$/', $brand ) ? strtolower( $brand ) : '';

			$settings['email_social_links']  = isset( $_POST['email_social_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['email_social_links'] ) ) : '';
			$settings['email_footer_text']   = isset( $_POST['email_footer_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['email_footer_text'] ) ) : '';
			$settings['email_footer_links']  = isset( $_POST['email_footer_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['email_footer_links'] ) ) : '';
			$settings['preferences_page_id'] = isset( $_POST['preferences_page_id'] ) ? (int) $_POST['preferences_page_id'] : 0;
		}

		update_option( 'boletines_settings', $settings );

		wp_safe_redirect( admin_url( 'admin.php?page=boletines-settings&tab=' . $tab . '&saved=1' ) );
		exit;
	}
}
