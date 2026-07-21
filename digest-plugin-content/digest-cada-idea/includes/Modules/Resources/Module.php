<?php
namespace Boletines\Modules\Resources;

use Boletines\Modules\ModuleInterface;
use Boletines\Resources\Renderer;
use Boletines\Resources\Ajax;
use Boletines\Resources\Downloader;

defined( 'ABSPATH' ) || exit;

/**
 * Módulo Pro: Recursos / Lead Magnets.
 */
class Module implements ModuleInterface {

	public function slug(): string {
		return 'resources';
	}

	public function name(): string {
		return __( 'Recursos (Lead Magnets)', 'boletines' );
	}

	public function description(): string {
		return __( 'Ofrece archivos descargables (PDF, ZIP, audio, video…) a cambio de suscripciones. Cada visitante recibe un correo con un enlace único y temporal.', 'boletines' );
	}

	public function icon(): string {
		return 'dashicons-download';
	}

	public function requires_pro(): bool {
		return true;
	}

	public function register(): void {
		( new Renderer() )->register();
		( new Ajax() )->register();
		( new Downloader() )->register();
	}

	public function settings_url(): string {
		return admin_url( 'admin.php?page=boletines-resources' );
	}
}
