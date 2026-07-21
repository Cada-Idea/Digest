<?php
namespace Boletines\Admin;

use Boletines\Admin\Pages\Addons as AddonsPage;
use Boletines\Admin\Pages\Automations as AutomationsPage;
use Boletines\Admin\Pages\Bounces as BouncesPage;
use Boletines\Admin\Pages\Campaigns;
use Boletines\Admin\Pages\Dashboard;
use Boletines\Admin\Pages\Forms as FormsPage;
use Boletines\Admin\Pages\Health as HealthPage;
use Boletines\Admin\Pages\Lists;
use Boletines\Admin\Pages\Reports as ReportsPage;
use Boletines\Admin\Pages\Resources as ResourcesPage;
use Boletines\Admin\Pages\Settings;
use Boletines\Admin\Pages\Smtp as SmtpPage;
use Boletines\Admin\Pages\Subscribers;
use Boletines\Admin\Pages\Tools as ToolsPage;
use Boletines\Modules\Manager as Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu {

	const SLUG = 'boletines';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_boletines_save_subscriber', array( new Subscribers(), 'handle_save' ) );
		add_action( 'admin_post_boletines_save_list', array( new Lists(), 'handle_save' ) );
		add_action( 'admin_post_boletines_save_campaign', array( new Campaigns(), 'handle_save' ) );
		add_action( 'admin_post_boletines_delete_campaign', array( new Campaigns(), 'handle_delete' ) );
		add_action( 'admin_post_boletines_test_campaign', array( new Campaigns(), 'handle_test' ) );
		add_action( 'admin_post_boletines_save_settings', array( new Settings(), 'handle_save' ) );
		add_action( 'admin_post_boletines_save_automation', array( new AutomationsPage(), 'handle_save' ) );
		add_action( 'admin_post_boletines_save_form', array( new FormsPage(), 'handle_save' ) );
		add_action( 'admin_post_boletines_regen_webhook', array( new BouncesPage(), 'handle_regen' ) );
		add_action( 'admin_post_boletines_import_csv', array( new ToolsPage(), 'handle_import' ) );
		add_action( 'admin_post_boletines_export_csv', array( new ToolsPage(), 'handle_export' ) );
	}

	public function add_menu(): void {
		$cap  = 'manage_options';
		$icon = $this->menu_icon();

		add_menu_page(
			__( 'Digest by Cada Idea', 'boletines' ),
			__( 'Digest', 'boletines' ),
			$cap,
			self::SLUG,
			array( new Dashboard(), 'render' ),
			$icon,
			26
		);

		add_submenu_page( self::SLUG, __( 'Resumen', 'boletines' ), __( 'Resumen', 'boletines' ), $cap, self::SLUG, array( new Dashboard(), 'render' ) );
		add_submenu_page( self::SLUG, __( 'Campañas', 'boletines' ), __( 'Campañas', 'boletines' ), $cap, self::SLUG . '-campaigns', array( new Campaigns(), 'render' ) );
		add_submenu_page( self::SLUG, __( 'Suscriptores', 'boletines' ), __( 'Suscriptores', 'boletines' ), $cap, self::SLUG . '-subscribers', array( new Subscribers(), 'render' ) );
		add_submenu_page( self::SLUG, __( 'Listas', 'boletines' ), __( 'Listas', 'boletines' ), $cap, self::SLUG . '-lists', array( new Lists(), 'render' ) );
		add_submenu_page( self::SLUG, __( 'Formularios', 'boletines' ), __( 'Formularios', 'boletines' ), $cap, self::SLUG . '-forms', array( new FormsPage(), 'render' ) );

		// Automatizaciones: visible sólo si el módulo está activo.
		if ( Modules::instance()->is_active( 'automations' ) ) {
			add_submenu_page( self::SLUG, __( 'Automatizaciones', 'boletines' ), __( 'Automatizaciones', 'boletines' ), $cap, self::SLUG . '-automations', array( new AutomationsPage(), 'render' ) );
		} else {
			add_submenu_page( null, __( 'Automatizaciones', 'boletines' ), __( 'Automatizaciones', 'boletines' ), $cap, self::SLUG . '-automations', array( new AutomationsPage(), 'render' ) );
		}

		// v1.10 — Recursos (Lead Magnets): visible sólo si el módulo Pro está activo.
		if ( Modules::instance()->is_active( 'resources' ) ) {
			add_submenu_page( self::SLUG, __( 'Recursos', 'boletines' ), __( 'Recursos', 'boletines' ), $cap, self::SLUG . '-resources', array( new ResourcesPage(), 'render' ) );
		} else {
			add_submenu_page( null, __( 'Recursos', 'boletines' ), __( 'Recursos', 'boletines' ), $cap, self::SLUG . '-resources', array( new ResourcesPage(), 'render' ) );
		}

		add_submenu_page( self::SLUG, __( 'Reportes', 'boletines' ), __( 'Reportes', 'boletines' ), $cap, self::SLUG . '-reports', array( new ReportsPage(), 'render' ) );
		add_submenu_page( self::SLUG, __( 'Bounces', 'boletines' ), __( 'Bounces', 'boletines' ), $cap, self::SLUG . '-bounces', array( new BouncesPage(), 'render' ) );
		add_submenu_page( self::SLUG, __( 'Herramientas', 'boletines' ), __( 'Herramientas', 'boletines' ), $cap, self::SLUG . '-tools', array( new ToolsPage(), 'render' ) );

		// v1.9.1 — Salud (diagnóstico + snapshots + SMTP).
		// Si hay inconsistencias críticas, marcamos con punto rojo.
		$health_label = __( 'Salud', 'boletines' );
		if ( \Boletines\Diagnostics\Inconsistency::has_critical() ) {
			$health_label .= ' <span class="awaiting-mod">!</span>';
		}
		add_submenu_page(
			self::SLUG,
			__( 'Salud', 'boletines' ),
			$health_label,
			$cap,
			HealthPage::SLUG,
			array( new HealthPage(), 'render' )
		);

		add_submenu_page( self::SLUG, __( 'Ajustes', 'boletines' ), __( 'Ajustes', 'boletines' ), $cap, self::SLUG . '-settings', array( new Settings(), 'render' ) );

		// v2.0 — SMTP
		try {
			if ( class_exists( '\\Boletines\\Admin\\Pages\\Smtp' ) ) {
				add_submenu_page( self::SLUG, __( 'SMTP', 'boletines' ), __( 'SMTP', 'boletines' ), $cap, self::SLUG . '-smtp', array( SmtpPage::class, 'render' ) );
			}
		} catch ( \Throwable $e ) { error_log( '[Boletines 2.0] menu SMTP: ' . $e->getMessage() ); }

		add_submenu_page(
			self::SLUG,
			__( 'Addons', 'boletines' ),
			'<span style="color:#a78bfa;">' . __( '✨ Addons', 'boletines' ) . '</span>',
			$cap,
			self::SLUG . '-addons',
			array( new AddonsPage(), 'render' )
		);
	}

	private function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm2 0v.01L10 10l6-4.99V5H4zm12 2.41l-5.4 4.5a1 1 0 01-1.2 0L4 7.41V15h12V7.41z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
