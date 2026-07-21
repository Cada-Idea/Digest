<?php
namespace Boletines\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contrato que cumple cada módulo Pro.
 *
 * Los módulos son addons internos del plugin: vienen incluidos en el ZIP pero
 * sólo se cargan si están habilitados y la licencia Digest está activa.
 */
interface ModuleInterface {

	public function slug(): string;
	public function name(): string;
	public function description(): string;
	public function icon(): string;
	public function requires_pro(): bool;
	public function register(): void;
	public function settings_url(): string;
}
