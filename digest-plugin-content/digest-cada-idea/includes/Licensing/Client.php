<?php
namespace Boletines\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cliente HTTP que habla con la API de "Readers Cada Idea" en cadaidea.com.
 *
 * ===== ENDPOINTS QUE EL PLUGIN ESPERA EN cadaidea.com =====
 *
 * Diego, tu plugin "Readers Cada Idea" (que instalarás en cadaidea.com)
 * tiene que exponer estos 3 endpoints. Los marca y formatos son fijos
 * para que este cliente los entienda sin más configuración.
 *
 * 1) POST /wp-json/cadaidea/v1/digest/register
 *    Recibe: { email, first_name, last_name, birthday, site_url, plugin_version }
 *    Hace:   crea/actualiza Reader, genera código 6 dígitos, lo envía por correo,
 *            lo guarda en transient/post_meta del Reader expirando en 30 min.
 *    Devuelve: { success: true, message: "..." } o { success: false, message: "..." }
 *
 * 2) POST /wp-json/cadaidea/v1/digest/activate
 *    Recibe: { email, code, site_url }
 *    Hace:   verifica que el código coincida con el guardado, genera license_key,
 *            asocia license_key al Reader, registra site_url como instalación activa.
 *    Devuelve: { success: true, license_key: "...", reader_id: "...", message: "..." }
 *
 * 3) GET  /wp-json/cadaidea/v1/digest/verify?license_key=XYZ&site_url=YYY
 *    Hace:   verifica que la licencia exista y esté activa para ese site_url.
 *    Devuelve: { active: true, expires_at: "..." } o { active: false, reason: "..." }
 *
 * Endpoints son públicos (sin auth) — la "auth" la da el code de un solo uso
 * en register/activate, y la license_key en verify.
 *
 * El plugin SIEMPRE funciona con o sin estos endpoints disponibles.
 * Si la API falla, las llamadas devuelven WP_Error y el wizard lo informa
 * pero el plugin sigue funcionando.
 */
class Client {

	/**
	 * URL base por defecto del servidor de licencias.
	 * Override:
	 *   1) Constante en wp-config.php:
	 *        define( 'BOLETINES_LICENSE_API_BASE', 'https://www.tusitio.com/wp-json/cadaidea/v1/digest' );
	 *   2) Filtro:
	 *        add_filter( 'boletines_license_api_base', fn() => '...' );
	 *
	 * Utilidad: en producción apunta a cadaidea.com (default); en sitios de pruebas
	 * (talutil.com, netvez.com, etc.) defines la constante apuntando a tu sandbox.
	 */
	const DEFAULT_API_BASE = 'https://cadaidea.com/wp-json/cadaidea/v1/digest';

	/**
	 * Devuelve la URL base del API de licencias actual.
	 * Se resuelve en tiempo de ejecución, no es constante, para permitir el filtro.
	 */
	public static function api_base(): string {
		if ( defined( 'BOLETINES_LICENSE_API_BASE' ) && BOLETINES_LICENSE_API_BASE ) {
			$base = (string) BOLETINES_LICENSE_API_BASE;
		} else {
			$base = self::DEFAULT_API_BASE;
		}
		$base = apply_filters( 'boletines_license_api_base', $base );
		return rtrim( (string) $base, '/' );
	}

	public function register( string $email, string $first_name, string $last_name, string $birthday ) {
		return $this->post( '/register', array(
			'email'          => $email,
			'first_name'     => $first_name,
			'last_name'      => $last_name,
			'birthday'       => $birthday,
			'site_url'       => home_url(),
			'plugin_version' => BOLETINES_VERSION,
		) );
	}

	public function activate( string $email, string $code ) {
		return $this->post( '/activate', array(
			'email'    => $email,
			'code'     => $code,
			'site_url' => home_url(),
		) );
	}

	public function verify( string $license_key ) {
		return $this->get( '/verify', array(
			'license_key' => $license_key,
			'site_url'    => home_url(),
		) );
	}

	private function post( string $path, array $body ) {
		$res = wp_remote_post( self::api_base() . $path, array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		) );
		return $this->handle( $res );
	}

	private function get( string $path, array $query ) {
		$url = add_query_arg( $query, self::api_base() . $path );
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		return $this->handle( $res );
	}

	private function handle( $res ) {
		if ( is_wp_error( $res ) ) {
			return new \WP_Error(
				'boletines_api_unreachable',
				__( 'No pudimos conectar con cadaidea.com. Comprueba tu conexión a internet y vuelve a intentar.', 'boletines' ),
				array( 'original' => $res->get_error_message() )
			);
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'boletines_api_invalid_response',
				__( 'La respuesta del servidor no fue válida. Vuelve a intentar en unos minutos.', 'boletines' )
			);
		}
		if ( $code >= 400 || ( isset( $data['success'] ) && $data['success'] === false ) ) {
			$msg = $data['message'] ?? __( 'Error desconocido.', 'boletines' );
			return new \WP_Error( 'boletines_api_error', $msg, array( 'status' => $code, 'data' => $data ) );
		}
		return $data;
	}
}
