<?php
namespace Boletines;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea/actualiza las tablas y la configuración por defecto.
 */
class Installer {

	public static function activate(): void {
		self::install();
		// Programar cron al activar.
		if ( ! wp_next_scheduled( 'boletines_cron_send' ) ) {
			wp_schedule_event( time() + 30, 'boletines_every_1min', 'boletines_cron_send' );
		}
		// v1.10 — registrar reglas de /recursos y flush para que la URL funcione.
		add_rewrite_rule( '^recursos/?$', 'index.php?boletines_resources_page=1', 'top' );
		// v2.1.1 — reglas path-based para preferencias/unsubscribe/confirm.
		// Inmunizan los links de los correos contra CDNs/WAFs que pelan query strings.
		add_rewrite_rule( '^boletines-action/preferences/([^/]+)/([^/]+)/?$',  'index.php?boletines_action_path=preferences&bol_sid=$matches[1]&bol_token=$matches[2]',  'top' );
		add_rewrite_rule( '^boletines-action/unsubscribe/([^/]+)/([^/]+)/?$', 'index.php?boletines_action_path=unsubscribe&bol_sid=$matches[1]&bol_token=$matches[2]', 'top' );
		add_rewrite_rule( '^boletines-action/confirm/([^/]+)/([^/]+)/?$',     'index.php?boletines_action_path=confirm&bol_sid=$matches[1]&bol_token=$matches[2]',     'top' );
		add_rewrite_rule( '^boletines-action/click/([^/]+)/([^/]+)/?$',       'index.php?boletines_action_path=click&bol_token=$matches[1]&bol_click_data=$matches[2]', 'top' );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'boletines_cron_send' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'boletines_cron_send' );
		}
		$daily = wp_next_scheduled( 'boletines_cron_daily' );
		if ( $daily ) {
			wp_unschedule_event( $daily, 'boletines_cron_daily' );
		}
		flush_rewrite_rules();
	}

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . BOLETINES_TABLE_PREFIX;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// v1.9.1 — Trackear si esta es la primera instalación (no existía db_version antes).
		$previous_version = get_option( 'boletines_db_version', '' );
		$is_first_install = ( '' === $previous_version );

		// v1.9.1 — Snapshot automático ANTES de cualquier cambio (sólo si NO es primera instalación
		// y la versión va a cambiar). Esto nos da una red de seguridad por si la migración rompe algo.
		if ( ! $is_first_install && $previous_version !== BOLETINES_DB_VERSION ) {
			// Cargamos el Snapshots\Manager manualmente porque podemos estar fuera del autoloader.
			if ( ! class_exists( '\\Boletines\\Snapshots\\Manager' ) ) {
				$snap_file = BOLETINES_DIR . 'includes/Snapshots/Manager.php';
				if ( file_exists( $snap_file ) ) {
					require_once $snap_file;
				}
			}
			if ( class_exists( '\\Boletines\\Snapshots\\Manager' ) ) {
				\Boletines\Snapshots\Manager::capture( 'pre-upgrade-' . $previous_version . '-to-' . BOLETINES_DB_VERSION );
			}
		}

		// v1.9.1 — Iniciar log de la migración.
		$migration_log = array(
			'started'          => current_time( 'mysql' ),
			'previous_version' => $previous_version,
			'target_version'   => BOLETINES_DB_VERSION,
			'is_first_install' => $is_first_install,
			'errors'           => array(),
		);

		$tables = array();

		// Suscriptores
		$tables[] = "CREATE TABLE {$prefix}subscribers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			first_name VARCHAR(120) DEFAULT NULL,
			last_name VARCHAR(120) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			token VARCHAR(64) NOT NULL,
			ip VARCHAR(45) DEFAULT NULL,
			source VARCHAR(60) DEFAULT 'form',
			lang VARCHAR(10) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			confirmed_at DATETIME DEFAULT NULL,
			unsubscribed_at DATETIME DEFAULT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_email (email),
			KEY idx_status (status),
			KEY idx_token (token)
		) {$charset};";

		// Listas
		$tables[] = "CREATE TABLE {$prefix}lists (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			slug VARCHAR(190) NOT NULL,
			description TEXT DEFAULT NULL,
			image_url VARCHAR(500) DEFAULT NULL,
			icon_svg LONGTEXT DEFAULT NULL,
			is_public TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_slug (slug)
		) {$charset};";

		// Pivote suscriptor-lista
		$tables[] = "CREATE TABLE {$prefix}subscriber_list (
			subscriber_id BIGINT UNSIGNED NOT NULL,
			list_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (subscriber_id, list_id),
			KEY idx_list (list_id)
		) {$charset};";

		// Campañas
		$tables[] = "CREATE TABLE {$prefix}campaigns (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subject VARCHAR(255) NOT NULL,
			preheader VARCHAR(255) DEFAULT NULL,
			body_html LONGTEXT NOT NULL,
			from_name VARCHAR(190) DEFAULT NULL,
			from_email VARCHAR(190) DEFAULT NULL,
			reply_to VARCHAR(190) DEFAULT NULL,
			list_ids TEXT DEFAULT NULL,
			tag_ids TEXT DEFAULT NULL,
			segment_logic VARCHAR(10) NOT NULL DEFAULT 'OR',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			scheduled_at DATETIME DEFAULT NULL,
			sent_at DATETIME DEFAULT NULL,
			total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
			total_sent INT UNSIGNED NOT NULL DEFAULT 0,
			total_opens INT UNSIGNED NOT NULL DEFAULT 0,
			total_clicks INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_sched (scheduled_at)
		) {$charset};";

		// Cola de envío + tracking por destinatario
		$tables[] = "CREATE TABLE {$prefix}campaign_emails (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			tracking_token VARCHAR(64) NOT NULL,
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			error_msg VARCHAR(255) DEFAULT NULL,
			sent_at DATETIME DEFAULT NULL,
			opened_at DATETIME DEFAULT NULL,
			clicks_count INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_token (tracking_token),
			KEY idx_status (campaign_id, status),
			KEY idx_sub (subscriber_id)
		) {$charset};";

		// Clics
		$tables[] = "CREATE TABLE {$prefix}clicks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_email_id BIGINT UNSIGNED NOT NULL,
			url VARCHAR(2048) NOT NULL,
			clicked_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_email (campaign_email_id)
		) {$charset};";

		// Tags y subscriber_tag se eliminan en v1.4 — ya no se usan.

		// Plantillas eliminadas en v1.5.

		// Formularios (v1.4) — sustituye al sistema simple de v1.0.
		$tables[] = "CREATE TABLE {$prefix}forms (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			type VARCHAR(40) NOT NULL DEFAULT 'inline',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			list_ids TEXT DEFAULT NULL,
			title VARCHAR(255) DEFAULT NULL,
			description TEXT DEFAULT NULL,
			button_text VARCHAR(120) DEFAULT NULL,
			show_first_name TINYINT(1) NOT NULL DEFAULT 1,
			show_last_name TINYINT(1) NOT NULL DEFAULT 1,
			require_first_name TINYINT(1) NOT NULL DEFAULT 0,
			options LONGTEXT DEFAULT NULL,
			impressions INT UNSIGNED NOT NULL DEFAULT 0,
			conversions INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_type_status (type, status)
		) {$charset};";

		// Automatizaciones (v1.2): RSS-to-email, digest, etc.
		$tables[] = "CREATE TABLE {$prefix}automations (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			type VARCHAR(40) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			list_ids TEXT DEFAULT NULL,
			tag_ids TEXT DEFAULT NULL,
			template_id BIGINT UNSIGNED DEFAULT NULL,
			options LONGTEXT DEFAULT NULL,
			last_run_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_type_status (type, status)
		) {$charset};";

		// Registro de qué posts se enviaron por automatización (para no duplicar).
		$tables[] = "CREATE TABLE {$prefix}automation_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			automation_id BIGINT UNSIGNED NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(20) NOT NULL DEFAULT 'post',
			campaign_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_run (automation_id, object_id, object_type),
			KEY idx_auto (automation_id)
		) {$charset};";

		// Bounces y quejas (v1.2)
		$tables[] = "CREATE TABLE {$prefix}bounces (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id BIGINT UNSIGNED DEFAULT NULL,
			email VARCHAR(190) NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'soft',
			reason VARCHAR(500) DEFAULT NULL,
			source VARCHAR(40) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_email (email),
			KEY idx_subscriber (subscriber_id),
			KEY idx_type (type)
		) {$charset};";

		// v1.6 — Custom fields por suscriptor (cumpleaños, lo que venga).
		$tables[] = "CREATE TABLE {$prefix}subscriber_meta (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			meta_key VARCHAR(80) NOT NULL,
			meta_value TEXT DEFAULT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_sub_key (subscriber_id, meta_key),
			KEY idx_key (meta_key)
		) {$charset};";

		// v1.10 — Recursos / Lead Magnets.
		// Un Recurso es un archivo o URL que el visitante descarga a cambio de
		// suscribirse. Se le envía un email con un link de descarga firmado.
		// NOTA dbDelta: usar VARCHAR(191) para columnas indexadas (límite utf8mb4
		// = 191 chars × 4 bytes = 764 < 767). El doble espacio en PRIMARY KEY
		// es obligatorio para dbDelta.
		$tables[] = "CREATE TABLE {$prefix}resources (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			slug VARCHAR(191) NOT NULL DEFAULT '',
			description TEXT,
			file_type VARCHAR(20) NOT NULL DEFAULT 'attachment',
			file_attachment_id BIGINT UNSIGNED DEFAULT NULL,
			file_external_url TEXT,
			cover_image_id BIGINT UNSIGNED DEFAULT NULL,
			list_ids TEXT,
			button_text VARCHAR(120) DEFAULT NULL,
			success_message TEXT,
			email_subject VARCHAR(255) DEFAULT NULL,
			email_body LONGTEXT,
			token_ttl_days INT UNSIGNED NOT NULL DEFAULT 7,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			downloads_count INT UNSIGNED NOT NULL DEFAULT 0,
			submissions_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_slug (slug),
			KEY idx_status (status)
		) {$charset};";

		// v1.10 — Tokens de descarga. Uno por (recurso, suscriptor, momento).
		$tables[] = "CREATE TABLE {$prefix}resource_tokens (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(64) NOT NULL,
			resource_id BIGINT UNSIGNED NOT NULL,
			subscriber_id BIGINT UNSIGNED DEFAULT NULL,
			email VARCHAR(191) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00',
			expires_at DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00',
			downloaded_at DATETIME DEFAULT NULL,
			download_count INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_token (token),
			KEY idx_resource (resource_id),
			KEY idx_email (email)
		) {$charset};";

		// v1.10.1 — Capturar errores reales de dbDelta para diagnóstico.
		// Suppress_errors evita que un fail crashee el resto. Después
		// chequeamos $wpdb->last_error y guardamos en el log.
		foreach ( $tables as $i => $sql ) {
			$wpdb->suppress_errors( true );
			$wpdb->flush();
			$dbdelta_result = dbDelta( $sql );
			$err = $wpdb->last_error;
			$wpdb->suppress_errors( false );

			if ( $err ) {
				// Extraer nombre de la tabla del CREATE para el log.
				if ( preg_match( '/CREATE TABLE\s+(\S+)/i', $sql, $m ) ) {
					$tbl = $m[1];
				} else {
					$tbl = 'table_' . $i;
				}
				$migration_log['errors'][] = array(
					'table' => $tbl,
					'error' => $err,
					'sql'   => substr( $sql, 0, 200 ),
				);
			}
		}

		// Opciones por defecto.
		if ( false === get_option( 'boletines_settings' ) ) {
			$defaults = array(
				'from_name'        => get_bloginfo( 'name' ),
				'from_email'       => get_option( 'admin_email' ),
				'reply_to'         => get_option( 'admin_email' ),
				'speed_per_hour'   => 240,   // 240 correos/hora ≈ 4/min ≈ seguro para casi cualquier hosting
				'batch_size'       => 20,    // por ejecución de cron
				'double_optin'     => 1,
				'confirm_subject'  => 'Confirma tu suscripción',
				'confirm_body'     => "Hola,\n\nGracias por suscribirte. Por favor confirma tu suscripción haciendo clic en este enlace:\n\n{confirmation_url}\n\nSi no te suscribiste tú, ignora este correo.",
				'welcome_enabled'  => 0,
				'welcome_subject'  => '¡Bienvenido!',
				'welcome_body'     => "Hola {first_name},\n\nGracias por confirmar tu suscripción a {site_name}.\n\nUn saludo.",
				'unsubscribe_page' => 0,
				'confirm_page'     => 0,
				'wc_optin_enabled' => 1,
				'wc_require_optin' => 1,
				'wc_target_list'   => 0,
				'wc_optin_label'   => __( 'Quiero recibir novedades y ofertas por correo', 'boletines' ),
				// v1.1 — sincronización con usuarios WP
				'wp_users_sync'    => 0,
				'wp_users_list'    => 0,
				'wp_users_status'  => 'pending',
				// v1.2 — webhook bounces
				'webhook_secret'   => wp_generate_password( 32, false, false ),
				'auto_unsub_hard_bounce' => 1,
				'soft_bounce_threshold'  => 3,
			);
			add_option( 'boletines_settings', $defaults );
		}

		// Crear lista por defecto SÓLO si esta es una instalación nueva.
		// Bug histórico: si la tabla lists existe pero está vacía por otro motivo
		// (corrupción, migración fallida, datos perdidos), no queremos enmascarar
		// el problema creando una lista por defecto. Sólo seedeamos en instalación
		// nueva real.
		if ( $is_first_install ) {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}lists" );
			if ( 0 === $count ) {
				$wpdb->insert(
					$prefix . 'lists',
					array(
						'name'        => __( 'Lista principal', 'boletines' ),
						'slug'        => 'principal',
						'description' => __( 'Lista por defecto creada automáticamente.', 'boletines' ),
						'is_public'   => 1,
						'created_at'  => current_time( 'mysql' ),
					)
				);
			}
		}

		update_option( 'boletines_db_version', BOLETINES_DB_VERSION );

		// v1.9.1 — Marcar fecha de primera instalación si no existe.
		if ( $is_first_install && ! get_option( 'boletines_first_install' ) ) {
			update_option( 'boletines_first_install', current_time( 'mysql' ) );
		}

		// v1.9.1 — Marcar fecha de la última migración.
		update_option( 'boletines_last_migration', current_time( 'mysql' ) );

		// v1.4 — eliminar tablas de tags (función removida del plugin).
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}subscriber_tag" );
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}tags" );

		// v1.5 — eliminar tabla de plantillas (función removida del plugin).
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}templates" );

		// Migración suave: garantizar que existan los settings nuevos en instalaciones antiguas.
		$settings = get_option( 'boletines_settings', array() );
		$dirty = false;
		if ( empty( $settings['webhook_secret'] ) ) {
			$settings['webhook_secret'] = wp_generate_password( 32, false, false );
			$dirty = true;
		}
		if ( ! isset( $settings['auto_unsub_hard_bounce'] ) ) {
			$settings['auto_unsub_hard_bounce'] = 1;
			$dirty = true;
		}
		if ( ! isset( $settings['soft_bounce_threshold'] ) ) {
			$settings['soft_bounce_threshold'] = 3;
			$dirty = true;
		}
		// v1.4 — color del botón
		if ( ! isset( $settings['form_button_color'] ) ) {
			$settings['form_button_color'] = 'auto';
			$dirty = true;
		}
		if ( ! isset( $settings['form_button_text_color'] ) ) {
			$settings['form_button_text_color'] = '#ffffff';
			$dirty = true;
		}
		// v1.5 — branding global de correos
		if ( ! isset( $settings['email_logo_url'] ) )    { $settings['email_logo_url']    = ''; $dirty = true; }
		if ( ! isset( $settings['email_logo_width'] ) )  { $settings['email_logo_width']  = 160; $dirty = true; }
		if ( ! isset( $settings['email_brand_color'] ) ) { $settings['email_brand_color'] = ''; $dirty = true; }
		if ( ! isset( $settings['email_social_links'] ) ){ $settings['email_social_links']= ''; $dirty = true; }
		if ( ! isset( $settings['preferences_page_id'] ) ){ $settings['preferences_page_id'] = 0; $dirty = true; }
		// v1.7 — texto custom del pie + sistema de licencia (Digest Cada Idea)
		if ( ! isset( $settings['email_footer_text'] ) ) { $settings['email_footer_text'] = ''; $dirty = true; }
		// v1.8 — enlaces extra del pie (Etiqueta|URL por línea)
		if ( ! isset( $settings['email_footer_links'] ) ) { $settings['email_footer_links'] = ''; $dirty = true; }
		if ( ! isset( $settings['license_status'] ) )    { $settings['license_status']    = 'inactive'; $dirty = true; }
		if ( ! isset( $settings['license_email'] ) )     { $settings['license_email']     = ''; $dirty = true; }
		if ( ! isset( $settings['license_first_name'] ) ){ $settings['license_first_name']= ''; $dirty = true; }
		if ( ! isset( $settings['license_last_name'] ) ) { $settings['license_last_name'] = ''; $dirty = true; }
		if ( ! isset( $settings['license_birthday'] ) )  { $settings['license_birthday']  = ''; $dirty = true; }
		if ( ! isset( $settings['license_key'] ) )       { $settings['license_key']       = ''; $dirty = true; }
		if ( ! isset( $settings['license_activated_at'] ) ){ $settings['license_activated_at'] = ''; $dirty = true; }
		if ( ! isset( $settings['license_reader_id'] ) ) { $settings['license_reader_id'] = ''; $dirty = true; }
		// v1.9 — sistema modular de Addons (automatizaciones como módulo Pro).
		if ( ! isset( $settings['enabled_modules'] ) ) {
			$settings['enabled_modules'] = array( 'automations' );
			$dirty = true;
		}
		if ( $dirty ) update_option( 'boletines_settings', $settings );

		// Programar cron diario para automatizaciones recurrentes.
		if ( ! wp_next_scheduled( 'boletines_cron_daily' ) ) {
			wp_schedule_event(
				strtotime( 'tomorrow 06:00' ) ?: time() + 3600,
				'daily',
				'boletines_cron_daily'
			);
		}

		// v1.9.1 — Finalizar y guardar log de la migración.
		$migration_log['finished'] = current_time( 'mysql' );
		$migration_log['success']  = true;
		update_option( 'boletines_installer_log', $migration_log, false );
	}

	/**
	 * v1.10.1 — Verifica idempotentemente que las tablas críticas existen.
	 * Si una falta, dispara install() de nuevo. Es seguro llamar en cada boot.
	 *
	 * v1.10.2 — Si tras install() siguen faltando (dbDelta puede fallar silencioso
	 * por razones específicas del servidor), ejecuta CREATE TABLE directo como
	 * fallback de último recurso.
	 */
	public static function ensure_critical_tables(): void {
		global $wpdb;
		$prefix = $wpdb->prefix . BOLETINES_TABLE_PREFIX;

		// Tablas críticas del módulo Recursos (1.10.0).
		$critical = array(
			$prefix . 'resources',
			$prefix . 'resource_tokens',
		);

		$missing_initial = array();
		foreach ( $critical as $table ) {
			$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) $missing_initial[] = $table;
		}

		if ( empty( $missing_initial ) ) return;

		// Intento 1: ejecutar install() para que dbDelta intente crearlas.
		self::install();

		// Re-comprobar
		$still_missing = array();
		foreach ( $critical as $table ) {
			$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) $still_missing[] = $table;
		}

		if ( empty( $still_missing ) ) return;

		// Intento 2: CREATE TABLE plano, sin dbDelta. Última opción.
		$charset = $wpdb->get_charset_collate();
		self::raw_create_resources_tables( $prefix, $charset );
	}

	/**
	 * Fallback de creación de tablas sin dbDelta.
	 * Se usa cuando dbDelta falla por razones específicas del servidor MySQL
	 * (versión antigua, modo SQL estricto, charset incompatible, etc.).
	 */
	private static function raw_create_resources_tables( string $prefix, string $charset ): void {
		global $wpdb;

		// Versión MÍNIMA y conservadora: sin defaults complejos, sin UNIQUE en
		// VARCHAR largos (problemáticos en utf8mb4 sin innodb_large_prefix).
		$resources_table = $prefix . 'resources';
		$tokens_table    = $prefix . 'resource_tokens';

		$wpdb->suppress_errors( true );

		$sql_resources = "CREATE TABLE IF NOT EXISTS `{$resources_table}` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`title` VARCHAR(255) NOT NULL,
			`slug` VARCHAR(191) NOT NULL DEFAULT '',
			`description` TEXT,
			`file_type` VARCHAR(20) NOT NULL DEFAULT 'attachment',
			`file_attachment_id` BIGINT UNSIGNED DEFAULT NULL,
			`file_external_url` TEXT,
			`cover_image_id` BIGINT UNSIGNED DEFAULT NULL,
			`list_ids` TEXT,
			`button_text` VARCHAR(120) DEFAULT NULL,
			`success_message` TEXT,
			`email_subject` VARCHAR(255) DEFAULT NULL,
			`email_body` LONGTEXT,
			`token_ttl_days` INT UNSIGNED NOT NULL DEFAULT 7,
			`status` VARCHAR(20) NOT NULL DEFAULT 'active',
			`downloads_count` INT UNSIGNED NOT NULL DEFAULT 0,
			`submissions_count` INT UNSIGNED NOT NULL DEFAULT 0,
			`created_at` DATETIME NOT NULL,
			`updated_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_slug` (`slug`),
			KEY `idx_status` (`status`)
		) ENGINE=InnoDB {$charset}";
		$wpdb->query( $sql_resources );

		$sql_tokens = "CREATE TABLE IF NOT EXISTS `{$tokens_table}` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`token` VARCHAR(64) NOT NULL,
			`resource_id` BIGINT UNSIGNED NOT NULL,
			`subscriber_id` BIGINT UNSIGNED DEFAULT NULL,
			`email` VARCHAR(191) NOT NULL,
			`created_at` DATETIME NOT NULL,
			`expires_at` DATETIME NOT NULL,
			`downloaded_at` DATETIME DEFAULT NULL,
			`download_count` INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_token` (`token`),
			KEY `idx_resource` (`resource_id`),
			KEY `idx_email` (`email`)
		) ENGINE=InnoDB {$charset}";
		$wpdb->query( $sql_tokens );

		// Log para diagnóstico
		$log = (array) get_option( 'boletines_installer_log', array() );
		$log['raw_fallback'] = array(
			'when'      => current_time( 'mysql' ),
			'resources_error' => $wpdb->last_error,
		);
		update_option( 'boletines_installer_log', $log, false );

		$wpdb->suppress_errors( false );
	}
}

// Intervalos personalizados de cron.
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['boletines_every_1min'] ) ) {
		$schedules['boletines_every_1min'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Cada minuto (Boletines)', 'boletines' ),
		);
	}
	if ( ! isset( $schedules['boletines_every_5min'] ) ) {
		$schedules['boletines_every_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Cada 5 minutos (Boletines)', 'boletines' ),
		);
	}
	return $schedules;
} );
