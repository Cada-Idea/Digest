<?php
namespace Boletines\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ): void {
		if ( strpos( (string) $hook, 'boletines' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'boletines-admin',
			BOLETINES_URL . 'assets/css/admin.css',
			array(),
			BOLETINES_VERSION
		);

		wp_enqueue_script(
			'boletines-admin',
			BOLETINES_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-api-fetch' ),
			BOLETINES_VERSION,
			true
		);

		wp_localize_script(
			'boletines-admin',
			'BoletinesAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'boletines/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'version' => BOLETINES_VERSION,
				'i18n'    => array(
					'confirm_send'   => __( '¿Enviar la campaña ahora? Esta acción no se puede deshacer.', 'boletines' ),
					'confirm_delete' => __( '¿Seguro que quieres eliminar este elemento?', 'boletines' ),
					'sending'        => __( 'Enviando...', 'boletines' ),
					'sent'           => __( '¡Hecho!', 'boletines' ),
					'error'          => __( 'Hubo un error.', 'boletines' ),
				),
			)
		);

		// El editor visual TinyMCE para el cuerpo de la campaña.
		if ( strpos( (string) $hook, 'boletines-campaigns' ) !== false ) {
			wp_enqueue_editor();
			wp_enqueue_media();
		}

		// Mediateca para listas (selector de imagen) y settings (selector de logo).
		if ( strpos( (string) $hook, 'boletines-lists' ) !== false || strpos( (string) $hook, 'boletines-settings' ) !== false ) {
			wp_enqueue_media();
		}
	}
}
