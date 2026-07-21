<?php
/**
 * Digest by Cada Idea — uninstall.
 * Se ejecuta sólo cuando el usuario elige "Borrar" desde la pantalla de Plugins.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$prefix = $wpdb->prefix . 'bol_';

// -----------------------------------------------------------------------------
// Tablas del plugin
// -----------------------------------------------------------------------------
$tables = array(
	$prefix . 'clicks',
	$prefix . 'campaign_emails',
	$prefix . 'campaigns',
	$prefix . 'subscriber_list',
	$prefix . 'subscriber_meta',
	$prefix . 'subscriber_tag', // legacy
	$prefix . 'tags',           // legacy
	$prefix . 'templates',      // legacy
	$prefix . 'forms',
	$prefix . 'automation_runs',
	$prefix . 'automations',
	$prefix . 'bounces',
	$prefix . 'lists',
	$prefix . 'subscribers',
);

foreach ( $tables as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$t}" );
}

// -----------------------------------------------------------------------------
// Opciones del plugin
// -----------------------------------------------------------------------------
delete_option( 'boletines_settings' );
delete_option( 'boletines_db_version' );
delete_option( 'boletines_first_install' );
delete_option( 'boletines_last_migration' );
delete_option( 'boletines_installer_log' );
delete_option( 'boletines_snapshots_registry' );

// -----------------------------------------------------------------------------
// Crons
// -----------------------------------------------------------------------------
foreach ( array( 'boletines_cron_send', 'boletines_cron_daily' ) as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
	wp_clear_scheduled_hook( $hook );
}

// -----------------------------------------------------------------------------
// Directorio de snapshots (los borramos todos, son del plugin)
// -----------------------------------------------------------------------------
$upload   = wp_upload_dir();
$base_dir = isset( $upload['basedir'] ) ? $upload['basedir'] : WP_CONTENT_DIR . '/uploads';
$snap_dir = $base_dir . '/digest-backups';

if ( is_dir( $snap_dir ) ) {
	$files = glob( $snap_dir . '/*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}
	}
	@rmdir( $snap_dir );
}

// -----------------------------------------------------------------------------
// Transients
// -----------------------------------------------------------------------------
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_boletines_%' OR option_name LIKE '_transient_timeout_boletines_%'"
);
