<?php
namespace Boletines\Licensing;

use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestiona el estado de la licencia (Digest Cada Idea).
 *
 * Estados:
 *  - inactive    : recién instalado, sin registrar
 *  - pending     : registrado, esperando código de activación por correo
 *  - active      : código verificado, licencia válida
 *  - expired     : código verificado pero la licencia expiró
 *
 * Nota: la activación es OPCIONAL — el plugin funciona en cualquier estado.
 * Activar da: notificaciones de updates, soporte, y features Pro (futuras).
 */
class Manager {

	const STATUS_INACTIVE = 'inactive';
	const STATUS_PENDING  = 'pending';
	const STATUS_ACTIVE   = 'active';
	const STATUS_EXPIRED  = 'expired';

	public static function status(): string {
		return (string) Plugin::get_setting( 'license_status', self::STATUS_INACTIVE );
	}

	public static function is_active(): bool {
		return self::status() === self::STATUS_ACTIVE;
	}

	public static function profile(): array {
		return array(
			'status'       => self::status(),
			'email'        => (string) Plugin::get_setting( 'license_email', '' ),
			'first_name'   => (string) Plugin::get_setting( 'license_first_name', '' ),
			'last_name'    => (string) Plugin::get_setting( 'license_last_name', '' ),
			'birthday'     => (string) Plugin::get_setting( 'license_birthday', '' ),
			'license_key'  => (string) Plugin::get_setting( 'license_key', '' ),
			'activated_at' => (string) Plugin::get_setting( 'license_activated_at', '' ),
			'reader_id'    => (string) Plugin::get_setting( 'license_reader_id', '' ),
		);
	}

	public static function save_profile( array $data ): void {
		$mapped = array(
			'license_email'      => sanitize_email( $data['email'] ?? '' ),
			'license_first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
			'license_last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
			'license_birthday'   => sanitize_text_field( $data['birthday'] ?? '' ),
		);
		foreach ( $mapped as $k => $v ) {
			Plugin::update_setting( $k, $v );
		}
	}

	public static function set_status( string $status ): void {
		Plugin::update_setting( 'license_status', $status );
	}

	public static function set_license_key( string $key, string $reader_id = '' ): void {
		Plugin::update_setting( 'license_key', $key );
		Plugin::update_setting( 'license_activated_at', current_time( 'mysql' ) );
		if ( $reader_id !== '' ) {
			Plugin::update_setting( 'license_reader_id', $reader_id );
		}
	}

	public static function deactivate(): void {
		Plugin::update_setting( 'license_status', self::STATUS_INACTIVE );
		Plugin::update_setting( 'license_key', '' );
		Plugin::update_setting( 'license_activated_at', '' );
		Plugin::update_setting( 'license_reader_id', '' );
	}
}
