<?php
namespace Boletines\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-actualización nativa de WordPress.
 *
 * Consulta GET {api_base}/version que devuelve:
 *   { "version": "2.2.0", "download_url": "https://...zip", "changelog": "...", "tested": "6.6" }
 *
 * Con esto, WordPress muestra el aviso estándar "Hay una nueva versión
 * disponible" en Plugins, con el botón "Actualizar ahora" — el cliente
 * nunca necesita entrar a ningún servidor ni volver a activar su licencia
 * (la licencia sigue guardada en sus opciones locales, no se toca).
 *
 * Se revisa una vez por día (cacheado en un transient) para no sobrecargar
 * la API en cada carga del admin.
 */
class Updater {

	const CACHE_KEY = 'boletines_update_check';
	const CACHE_TTL  = DAY_IN_SECONDS;

	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'purge_cache' ), 10, 2 );
	}

	private static function remote_info() {
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			return $cached;
		}

		$res = wp_remote_get( Client::api_base() . '/version', array( 'timeout' => 10 ) );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			return false;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	public static function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$info = self::remote_info();
		if ( ! $info ) {
			return $transient;
		}

		if ( version_compare( $info['version'], BOLETINES_VERSION, '>' ) ) {
			$item = (object) array(
				'id'            => BOLETINES_BASENAME,
				'slug'          => dirname( BOLETINES_BASENAME ),
				'plugin'        => BOLETINES_BASENAME,
				'new_version'   => $info['version'],
				'url'           => 'https://www.cadaidea.com',
				'package'       => $info['download_url'] ?? '',
				'tested'        => $info['tested'] ?? '',
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'compatibility' => new \stdClass(),
			);
			$transient->response[ BOLETINES_BASENAME ] = $item;
		} else {
			unset( $transient->response[ BOLETINES_BASENAME ] );
		}

		return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== dirname( BOLETINES_BASENAME ) ) {
			return $result;
		}

		$info = self::remote_info();
		if ( ! $info ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Digest by Cada Idea',
			'slug'          => dirname( BOLETINES_BASENAME ),
			'version'       => $info['version'],
			'author'        => '<a href="https://www.cadaidea.com">Cada Idea</a>',
			'homepage'      => 'https://www.cadaidea.com',
			'requires'      => '6.0',
			'tested'        => $info['tested'] ?? '',
			'download_link' => $info['download_url'] ?? '',
			'sections'      => array(
				'description' => 'Newsletter, suscriptores y campañas para tu sitio.',
				'changelog'   => nl2br( esc_html( $info['changelog'] ?? '' ) ),
			),
		);
	}

	public static function purge_cache( $upgrader, $data ): void {
		if ( isset( $data['action'] ) && $data['action'] === 'update' && isset( $data['type'] ) && $data['type'] === 'plugin' ) {
			delete_transient( self::CACHE_KEY );
		}
	}
}
