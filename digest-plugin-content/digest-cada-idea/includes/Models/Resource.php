<?php
namespace Boletines\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Modelo: Recurso (Lead Magnet).
 *
 * Un Recurso es un archivo (attachment WP) o URL externa que el visitante
 * recibe por correo a cambio de suscribirse a una o más listas.
 */
class Resource {

	const STATUS_ACTIVE   = 'active';
	const STATUS_INACTIVE = 'inactive';

	const FILE_TYPE_ATTACHMENT = 'attachment'; // attachment de WP
	const FILE_TYPE_EXTERNAL   = 'external';    // URL externa

	/** Tabla de recursos. */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'boletines_resources';
	}

	/** Tabla de tokens. */
	public static function tokens_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'boletines_resource_tokens';
	}

	/**
	 * Devuelve un recurso por ID.
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Devuelve un recurso por slug.
	 */
	public static function get_by_slug( string $slug ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE slug = %s', $slug ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Lista de recursos (con filtros).
	 * @return array<int,array>
	 */
	public static function all( array $args = array() ): array {
		global $wpdb;
		$args = array_merge( array(
			'status'   => '',          // '' = todos, 'active', 'inactive'
			'limit'    => 100,
			'offset'   => 0,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		), $args );

		$where = '1=1';
		$params = array();
		if ( $args['status'] !== '' ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$orderby = in_array( $args['orderby'], array( 'created_at', 'title', 'downloads_count', 'submissions_count' ), true )
			? $args['orderby'] : 'created_at';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$sql = 'SELECT * FROM ' . self::table()
			. ' WHERE ' . $where
			. ' ORDER BY ' . $orderby . ' ' . $order
			. ' LIMIT %d OFFSET %d';
		$params[] = (int) $args['limit'];
		$params[] = (int) $args['offset'];

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	public static function count( string $status = '' ): int {
		global $wpdb;
		if ( $status !== '' ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s',
				$status
			) );
		}
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}

	/**
	 * Crea o actualiza un recurso.
	 * @return int ID del recurso (>0 si OK, 0 si falló).
	 */
	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;

		// v1.10.1 — Defensive: si current_time falla, usamos gmdate como fallback.
		$now = current_time( 'mysql' );
		if ( empty( $now ) || $now === '0000-00-00 00:00:00' ) {
			$now = gmdate( 'Y-m-d H:i:s' );
		}

		$slug = isset( $data['slug'] ) && $data['slug'] !== '' ? sanitize_title( (string) $data['slug'] ) : '';
		if ( $slug === '' ) {
			$slug = sanitize_title( (string) ( $data['title'] ?? 'recurso' ) );
		}
		if ( $slug === '' ) $slug = 'recurso-' . time(); // último recurso
		$slug = self::ensure_unique_slug( $slug, $id );

		$list_ids = isset( $data['list_ids'] ) ? array_filter( array_map( 'intval', (array) $data['list_ids'] ) ) : array();

		// v1.10.1 — file_attachment_id y cover_image_id: NULL si son 0 (más limpio en BD).
		$file_attachment_id = isset( $data['file_attachment_id'] ) ? (int) $data['file_attachment_id'] : 0;
		$cover_image_id     = isset( $data['cover_image_id'] )     ? (int) $data['cover_image_id']     : 0;

		$row = array(
			'title'              => substr( (string) ( $data['title'] ?? '' ), 0, 255 ),
			'slug'               => substr( $slug, 0, 190 ),
			'description'        => (string) ( $data['description'] ?? '' ),
			'file_type'          => in_array( ( $data['file_type'] ?? 'attachment' ), array( self::FILE_TYPE_ATTACHMENT, self::FILE_TYPE_EXTERNAL ), true )
				? $data['file_type'] : self::FILE_TYPE_ATTACHMENT,
			'file_attachment_id' => $file_attachment_id > 0 ? $file_attachment_id : null,
			'file_external_url'  => isset( $data['file_external_url'] ) && $data['file_external_url'] !== ''
				? esc_url_raw( (string) $data['file_external_url'] ) : null,
			'cover_image_id'     => $cover_image_id > 0 ? $cover_image_id : null,
			'list_ids'           => wp_json_encode( $list_ids ),
			'button_text'        => substr( (string) ( $data['button_text'] ?? '' ), 0, 120 ),
			'success_message'    => (string) ( $data['success_message'] ?? '' ),
			'email_subject'      => substr( (string) ( $data['email_subject'] ?? '' ), 0, 255 ),
			'email_body'         => (string) ( $data['email_body'] ?? '' ),
			'token_ttl_days'     => max( 1, min( 365, (int) ( $data['token_ttl_days'] ?? 7 ) ) ),
			'status'             => in_array( ( $data['status'] ?? self::STATUS_ACTIVE ), array( self::STATUS_ACTIVE, self::STATUS_INACTIVE ), true )
				? $data['status'] : self::STATUS_ACTIVE,
			'updated_at'         => $now,
		);

		// Validación mínima: o tiene attachment_id o tiene external_url.
		if ( empty( $row['file_attachment_id'] ) && empty( $row['file_external_url'] ) ) {
			$row['status'] = self::STATUS_INACTIVE;
		}

		if ( $id > 0 ) {
			$result = $wpdb->update( self::table(), $row, array( 'id' => $id ) );
			if ( $result === false ) {
				return 0;
			}
			return $id;
		}

		$row['created_at']        = $now;
		$row['downloads_count']   = 0;
		$row['submissions_count'] = 0;

		$result = $wpdb->insert( self::table(), $row );
		if ( $result === false ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ) );
		$wpdb->delete( self::tokens_table(), array( 'resource_id' => $id ) );
	}

	public static function increment_submissions( int $id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . ' SET submissions_count = submissions_count + 1 WHERE id = %d',
			$id
		) );
	}

	public static function increment_downloads( int $id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . ' SET downloads_count = downloads_count + 1 WHERE id = %d',
			$id
		) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tokens de descarga
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Crea un token único de descarga.
	 * @return array{token: string, expires_at: string}
	 */
	public static function create_token( int $resource_id, string $email, ?int $subscriber_id, int $ttl_days ): array {
		global $wpdb;
		$token = bin2hex( random_bytes( 24 ) ); // 48 chars hex
		$now   = current_time( 'mysql' );
		$exp   = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC' ) + ( max( 1, $ttl_days ) * DAY_IN_SECONDS ) );

		$wpdb->insert( self::tokens_table(), array(
			'token'         => $token,
			'resource_id'   => $resource_id,
			'subscriber_id' => $subscriber_id,
			'email'         => $email,
			'created_at'    => $now,
			'expires_at'    => $exp,
		) );

		return array( 'token' => $token, 'expires_at' => $exp );
	}

	/**
	 * Resuelve un token. Devuelve null si no existe o expirado.
	 */
	public static function get_token( string $token ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::tokens_table() . ' WHERE token = %s',
			$token
		), ARRAY_A );
		if ( ! $row ) return null;
		if ( strtotime( $row['expires_at'] . ' UTC' ) < time() ) return null;
		return $row;
	}

	public static function mark_token_downloaded( int $token_id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::tokens_table() . ' SET downloaded_at = NOW(), download_count = download_count + 1 WHERE id = %d',
			$token_id
		) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────────

	/** Convierte un row de la BD a array hidratado (list_ids decodificado). */
	private static function hydrate( array $row ): array {
		$lists = array();
		if ( ! empty( $row['list_ids'] ) ) {
			$decoded = json_decode( (string) $row['list_ids'], true );
			if ( is_array( $decoded ) ) $lists = array_map( 'intval', $decoded );
		}
		$row['list_ids_array'] = $lists;
		return $row;
	}

	/** Asegura slug único; añade -2, -3, ... si ya existe. */
	private static function ensure_unique_slug( string $slug, int $exclude_id = 0 ): string {
		global $wpdb;
		$base = $slug;
		$i    = 1;
		while ( true ) {
			$exists = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE slug = %s AND id != %d',
				$slug, $exclude_id
			) );
			if ( $exists === 0 ) return $slug;
			$i++;
			$slug = $base . '-' . $i;
			if ( $i > 100 ) return $base . '-' . wp_generate_password( 6, false, false );
		}
	}

	/** Devuelve la URL del archivo (sea attachment o external). */
	public static function file_url( array $resource ): string {
		if ( $resource['file_type'] === self::FILE_TYPE_EXTERNAL && ! empty( $resource['file_external_url'] ) ) {
			return (string) $resource['file_external_url'];
		}
		if ( ! empty( $resource['file_attachment_id'] ) ) {
			$url = wp_get_attachment_url( (int) $resource['file_attachment_id'] );
			return $url ?: '';
		}
		return '';
	}

	/** Nombre del archivo para mostrar. */
	public static function file_name( array $resource ): string {
		if ( ! empty( $resource['file_attachment_id'] ) ) {
			$path = get_attached_file( (int) $resource['file_attachment_id'] );
			if ( $path ) return basename( $path );
		}
		if ( ! empty( $resource['file_external_url'] ) ) {
			return basename( (string) $resource['file_external_url'] );
		}
		return '';
	}
}
