<?php
namespace Boletines\Modules\Automations;

use Boletines\Modules\ModuleInterface;
use Boletines\Automations\RssToEmail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Módulo: Automatizaciones (post-publish, digest, cumpleaños, RSS-to-email).
 * Wrapper sobre Boletines\Automations\RssToEmail.
 */
class Module implements ModuleInterface {

	public function slug(): string {
		return 'automations';
	}

	public function name(): string {
		return __( 'Automatizaciones', 'boletines' );
	}

	public function description(): string {
		return __( 'Envío automático al publicar un post o producto, digest diario/semanal, correos de cumpleaños y RSS-to-email.', 'boletines' );
	}

	public function icon(): string {
		return 'dashicons-update';
	}

	public function requires_pro(): bool {
		return true;
	}

	public function register(): void {
		( new RssToEmail() )->register();
	}

	public function settings_url(): string {
		return admin_url( 'admin.php?page=boletines-automations' );
	}
}
