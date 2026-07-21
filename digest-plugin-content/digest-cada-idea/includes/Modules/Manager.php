<?php
namespace Boletines\Modules;

use Boletines\Plugin;
use Boletines\Licensing\Manager as License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestor central de módulos Pro.
 *
 * Reglas:
 *   ACTIVO si: licencia activa + slug en enabled_modules
 *   DISPONIBLE-INACTIVO si: licencia activa pero slug no en enabled_modules
 *   BLOQUEADO si: no hay licencia activa
 */
class Manager {

	private static $instance = null;
	private $modules = array();
	private $booted = false;

	public static function instance(): Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->register_default_modules();
	}

	private function register_default_modules(): void {
		$this->modules['automations'] = new Automations\Module();
		$this->modules['resources']   = new Resources\Module();
		$this->modules = apply_filters( 'boletines_modules', $this->modules );
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( $this->modules as $slug => $module ) {
			if ( $this->is_active( $slug ) ) {
				$module->register();
			}
		}
	}

	public function is_active( string $slug ): bool {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return false;
		}
		$module = $this->modules[ $slug ];
		if ( $module->requires_pro() && ! License::is_active() ) {
			return false;
		}
		return in_array( $slug, $this->enabled_slugs(), true );
	}

	public function is_locked( string $slug ): bool {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return false;
		}
		return $this->modules[ $slug ]->requires_pro() && ! License::is_active();
	}

	public function all(): array {
		return $this->modules;
	}

	public function get( string $slug ) {
		return $this->modules[ $slug ] ?? null;
	}

	public function enabled_slugs(): array {
		$value = Plugin::get_setting( 'enabled_modules', array() );
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_map( 'strval', $value ) );
	}

	public function activate( string $slug ): bool {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return false;
		}
		if ( $this->is_locked( $slug ) ) {
			return false;
		}
		$enabled = $this->enabled_slugs();
		if ( in_array( $slug, $enabled, true ) ) {
			return false;
		}
		$enabled[] = $slug;
		Plugin::update_setting( 'enabled_modules', $enabled );
		do_action( 'boletines_module_activated', $slug );
		return true;
	}

	public function deactivate( string $slug ): bool {
		if ( ! isset( $this->modules[ $slug ] ) ) {
			return false;
		}
		$enabled = $this->enabled_slugs();
		if ( ! in_array( $slug, $enabled, true ) ) {
			return false;
		}
		$enabled = array_values( array_diff( $enabled, array( $slug ) ) );
		Plugin::update_setting( 'enabled_modules', $enabled );
		do_action( 'boletines_module_deactivated', $slug );
		return true;
	}
}
