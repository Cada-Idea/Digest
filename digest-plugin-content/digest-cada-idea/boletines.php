<?php
/**
 * Plugin Name:       Digest by Cada Idea
 * Plugin URI:        https://cadaidea.com/digest
 * Description:       Te dejo que inspires. Sistema completo de boletines: suscriptores, listas, campañas, formularios, popups y branding global de correos. Pro: automatizaciones (post-publish, digest diario/semanal, cumpleaños) y Recursos / Lead Magnets descargables.
 * Version:           2.2.0
 * Author:            Cada Idea
 * Author URI:        https://cadaidea.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       boletines
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.0
 *
 * @package Boletines
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Constantes
// ---------------------------------------------------------------------------
define( 'BOLETINES_VERSION', '2.2.0' );
define( 'BOLETINES_FILE', __FILE__ );
define( 'BOLETINES_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOLETINES_URL', plugin_dir_url( __FILE__ ) );
define( 'BOLETINES_BASENAME', plugin_basename( __FILE__ ) );
define( 'BOLETINES_DB_VERSION', '1.10.2' );
define( 'BOLETINES_TABLE_PREFIX', 'bol_' );
define( 'BOLETINES_TEXT_DOMAIN', 'boletines' );

// ---------------------------------------------------------------------------
// Autoloader PSR-4 sencillo (sin Composer)
// ---------------------------------------------------------------------------
spl_autoload_register( function ( $class ) {
	$prefix   = 'Boletines\\';
	$base_dir = BOLETINES_DIR . 'includes/';

	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// ---------------------------------------------------------------------------
// Activación / Desactivación
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, function () {
	require_once BOLETINES_DIR . 'includes/Installer.php';
	\Boletines\Installer::activate();
} );

register_deactivation_hook( __FILE__, function () {
	require_once BOLETINES_DIR . 'includes/Installer.php';
	\Boletines\Installer::deactivate();
} );

// ---------------------------------------------------------------------------
// Arranque
// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( BOLETINES_TEXT_DOMAIN, false, dirname( BOLETINES_BASENAME ) . '/languages' );
	\Boletines\Plugin::instance()->boot();
	\Boletines\Licensing\Updater::init();
} );
