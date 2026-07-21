<?php
namespace Boletines\Resources;

use Boletines\Models\Resource;

defined( 'ABSPATH' ) || exit;

/**
 * Downloader: gestiona la descarga del archivo de un recurso vía token.
 *
 * Trigger: URL home_url('/?boletines_download=TOKEN')
 *
 * Estrategia:
 *   - Si el archivo es ATTACHMENT y existe en disco → stream con headers
 *     correctos (Content-Type, Content-Disposition).
 *   - Si el archivo es EXTERNAL → 302 redirect al URL externo.
 *
 * Tokens expirados → 410 Gone con mensaje amigable.
 * Tokens inválidos → 404.
 */
class Downloader {

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_handle_download' ), 5 );
	}

	public function maybe_handle_download(): void {
		if ( empty( $_GET['boletines_download'] ) ) return;

		$token = sanitize_text_field( wp_unslash( (string) $_GET['boletines_download'] ) );
		if ( $token === '' ) return;

		$row = Resource::get_token( $token );
		if ( ! $row ) {
			$this->error_page( 410, __( 'Este enlace ha expirado o no es válido.', 'boletines' ) );
			exit;
		}

		$resource = Resource::get( (int) $row['resource_id'] );
		if ( ! $resource ) {
			$this->error_page( 404, __( 'El recurso ya no existe.', 'boletines' ) );
			exit;
		}

		// Marcar como descargado e incrementar contador del recurso.
		Resource::mark_token_downloaded( (int) $row['id'] );
		Resource::increment_downloads( (int) $resource['id'] );

		// Despachar: external → redirect, attachment → stream
		if ( $resource['file_type'] === Resource::FILE_TYPE_EXTERNAL && ! empty( $resource['file_external_url'] ) ) {
			wp_redirect( $resource['file_external_url'], 302 );
			exit;
		}

		$attachment_id = (int) ( $resource['file_attachment_id'] ?? 0 );
		if ( $attachment_id < 1 ) {
			$this->error_page( 404, __( 'El archivo del recurso no está configurado.', 'boletines' ) );
			exit;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$this->error_page( 404, __( 'El archivo del recurso no se encuentra.', 'boletines' ) );
			exit;
		}

		$this->stream_file( $file_path, basename( $file_path ) );
	}

	private function stream_file( string $path, string $filename ): void {
		// Limpiar cualquier output previo (typical de hooks/plugins).
		while ( ob_get_level() > 0 ) ob_end_clean();

		$mime = wp_check_filetype( $filename );
		$content_type = $mime['type'] ?? 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		// Stream en bloques para no agotar memoria con archivos grandes.
		$fp = fopen( $path, 'rb' );
		if ( $fp ) {
			while ( ! feof( $fp ) ) {
				echo fread( $fp, 8192 );
				flush();
			}
			fclose( $fp );
		}
		exit;
	}

	private function error_page( int $status, string $message ): void {
		status_header( $status );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html><head><meta charset="utf-8"><title>'
			. esc_html__( 'Descarga no disponible', 'boletines' )
			. '</title><style>body{font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px;color:#1f2937;text-align:center;}h1{font-size:22px;}p{color:#6b7280;}</style>'
			. '</head><body>'
			. '<h1>' . esc_html__( 'Descarga no disponible', 'boletines' ) . '</h1>'
			. '<p>' . esc_html( $message ) . '</p>'
			. '<p><a href="' . esc_url( home_url( '/recursos' ) ) . '" style="color:#3b82f6;text-decoration:none;">'
			. esc_html__( '← Ver otros recursos', 'boletines' ) . '</a></p>'
			. '</body></html>';
	}
}
