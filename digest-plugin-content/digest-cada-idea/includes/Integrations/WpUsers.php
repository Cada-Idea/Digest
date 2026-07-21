<?php
namespace Boletines\Integrations;

use Boletines\Models\Subscriber;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sincronización con usuarios de WordPress:
 *  - Al registrarse un usuario nuevo, se suscribe automáticamente.
 *  - Al actualizar email, se actualiza el suscriptor correspondiente.
 *  - Al borrar usuario, NO borra el suscriptor (puede seguir activo).
 *  - Botón de sincronización masiva inicial desde Ajustes.
 */
class WpUsers {

	public function register(): void {
		if ( ! (int) Plugin::get_setting( 'wp_users_sync', 0 ) ) {
			// Dejamos siempre disponible la acción de sincronización masiva.
			add_action( 'admin_post_boletines_sync_wp_users', array( $this, 'handle_bulk_sync' ) );
			return;
		}
		add_action( 'user_register', array( $this, 'on_register' ), 20 );
		add_action( 'profile_update', array( $this, 'on_update' ), 20, 2 );
		add_action( 'admin_post_boletines_sync_wp_users', array( $this, 'handle_bulk_sync' ) );
	}

	public function on_register( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) return;

		$status   = (string) Plugin::get_setting( 'wp_users_status', 'pending' );
		$list_id  = (int) Plugin::get_setting( 'wp_users_list', 0 );

		$sid = Subscriber::upsert( array(
			'email'      => $user->user_email,
			'first_name' => $user->first_name ?: '',
			'last_name'  => $user->last_name ?: '',
			'status'     => $status === 'confirmed' ? Subscriber::STATUS_CONFIRMED : Subscriber::STATUS_PENDING,
			'source'     => 'wp_user',
		) );

		if ( $sid && $list_id ) {
			Subscriber::attach_lists( $sid, array( $list_id ) );
		}
	}

	public function on_update( int $user_id, $old_user_data ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) return;
		// Si cambió el email, buscamos por el viejo y lo migramos por upsert al nuevo.
		Subscriber::upsert( array(
			'email'      => $user->user_email,
			'first_name' => $user->first_name ?: '',
			'last_name'  => $user->last_name ?: '',
			'source'     => 'wp_user',
		) );
	}

	public function handle_bulk_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'No tienes permisos.', 'boletines' ) );
		check_admin_referer( 'boletines_sync_wp_users' );

		$status   = (string) Plugin::get_setting( 'wp_users_status', 'pending' );
		$list_id  = (int) Plugin::get_setting( 'wp_users_list', 0 );

		$users = get_users( array(
			'fields' => array( 'ID', 'user_email', 'first_name', 'last_name' ),
			'number' => 5000,
		) );

		$count = 0;
		foreach ( $users as $u ) {
			if ( ! is_email( $u->user_email ) ) continue;
			// Algunos usuarios pueden no tener el meta cargado.
			$first = get_user_meta( $u->ID, 'first_name', true );
			$last  = get_user_meta( $u->ID, 'last_name', true );
			$sid = Subscriber::upsert( array(
				'email'      => $u->user_email,
				'first_name' => $first ?: '',
				'last_name'  => $last  ?: '',
				'status'     => $status === 'confirmed' ? Subscriber::STATUS_CONFIRMED : Subscriber::STATUS_PENDING,
				'source'     => 'wp_user',
			) );
			if ( $sid && $list_id ) {
				Subscriber::attach_lists( $sid, array( $list_id ) );
			}
			$count++;
		}

		set_transient( 'boletines_wp_sync_count_' . get_current_user_id(), $count, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=boletines-settings&tab=sync&synced=1' ) );
		exit;
	}
}
