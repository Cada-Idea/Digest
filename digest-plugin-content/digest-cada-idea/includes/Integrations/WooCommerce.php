<?php
namespace Boletines\Integrations;

use Boletines\Models\ListModel;
use Boletines\Models\Subscriber;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integración con WooCommerce: añade casilla opt-in en checkout y sincroniza
 * compras con la lista elegida en ajustes.
 */
class WooCommerce {

	public function register(): void {
		// Casilla en checkout (block-based + clásico).
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_checkout_field' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_optin_meta' ) );

		// Al completar pedido, suscribir si marcó opt-in.
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_subscribe' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_subscribe' ) );
	}

	public function add_checkout_field( array $fields ): array {
		$enabled = (int) Plugin::get_setting( 'wc_optin_enabled', 1 );
		if ( ! $enabled ) {
			return $fields;
		}

		$fields['order']['boletines_optin'] = array(
			'type'    => 'checkbox',
			'label'   => Plugin::get_setting( 'wc_optin_label', __( 'Quiero recibir novedades y ofertas por correo', 'boletines' ) ),
			'class'   => array( 'form-row-wide' ),
			'default' => 1,
		);
		return $fields;
	}

	public function save_optin_meta( int $order_id ): void {
		if ( isset( $_POST['boletines_optin'] ) && $_POST['boletines_optin'] ) {
			update_post_meta( $order_id, '_boletines_optin', 1 );
		}
	}

	public function maybe_subscribe( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Si exigimos opt-in y no lo marcó, salir.
		$require_optin = (int) Plugin::get_setting( 'wc_require_optin', 1 );
		$has_optin     = (int) get_post_meta( $order_id, '_boletines_optin', true ) === 1;
		if ( $require_optin && ! $has_optin ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( ! is_email( $email ) ) {
			return;
		}

		$list_id = (int) Plugin::get_setting( 'wc_target_list', 0 );
		if ( ! $list_id ) {
			$lists   = ListModel::all();
			$list_id = ! empty( $lists ) ? (int) $lists[0]['id'] : 0;
		}

		$sid = Subscriber::upsert(
			array(
				'email'      => $email,
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				// Cliente que pasa por checkout: lo damos por confirmado (consintió la transacción).
				'status'     => Subscriber::STATUS_CONFIRMED,
				'source'     => 'woocommerce',
				'ip'         => $order->get_customer_ip_address(),
			)
		);

		if ( $sid && $list_id ) {
			Subscriber::attach_lists( $sid, array( $list_id ) );
		}
	}
}
