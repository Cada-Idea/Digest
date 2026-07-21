<?php
namespace Boletines\Tools;

use Boletines\Models\Subscriber;
use Boletines\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Csv {

	/**
	 * Importa un CSV. Acepta columnas: email (obligatoria), first_name, last_name, status.
	 * Detecta automáticamente delimitador (, ; tab) y la cabecera.
	 *
	 * @param array $tag_ids Sin uso desde v1.4 (tags eliminados). Se mantiene por compat.
	 * @return array ['imported','updated','skipped','errors']
	 */
	public static function import( string $file_path, array $list_ids = array(), array $tag_ids = array(), string $default_status = Subscriber::STATUS_CONFIRMED ): array {
		$result = array( 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );

		if ( ! is_readable( $file_path ) ) {
			$result['errors'][] = __( 'No se pudo leer el archivo.', 'boletines' );
			return $result;
		}

		$fh = fopen( $file_path, 'r' );
		if ( ! $fh ) {
			$result['errors'][] = __( 'No se pudo abrir el archivo.', 'boletines' );
			return $result;
		}

		// Detectar delimitador analizando la primera línea.
		$first_line = fgets( $fh );
		rewind( $fh );
		$delimiter = self::detect_delimiter( (string) $first_line );

		// Saltar BOM si lo hay.
		$bom = "\xef\xbb\xbf";
		if ( strncmp( (string) $first_line, $bom, 3 ) === 0 ) {
			fseek( $fh, 3 );
		}

		$header = fgetcsv( $fh, 0, $delimiter );
		if ( ! $header ) {
			fclose( $fh );
			$result['errors'][] = __( 'El CSV no tiene cabecera válida.', 'boletines' );
			return $result;
		}

		$header = array_map(
			function ( $h ) { return strtolower( trim( (string) $h ) ); },
			$header
		);

		// Mapear columnas (incluye campo birthday).
		$col = array();
		foreach ( array(
			'email', 'first_name', 'last_name', 'status',
			'name', 'firstname', 'first name', 'nombre', 'apellidos', 'apellido', 'correo',
			'birthday', 'cumpleanos', 'cumpleaños', 'fecha_nacimiento', 'fecha de nacimiento',
		) as $alias ) {
			$idx = array_search( $alias, $header, true );
			if ( $idx !== false ) {
				if ( in_array( $alias, array( 'correo' ), true ) ) {
					$col['email'] = $idx;
				} elseif ( in_array( $alias, array( 'name', 'firstname', 'first name', 'nombre' ), true ) ) {
					$col['first_name'] = $idx;
				} elseif ( in_array( $alias, array( 'apellidos', 'apellido' ), true ) ) {
					$col['last_name'] = $idx;
				} elseif ( in_array( $alias, array( 'cumpleanos', 'cumpleaños', 'fecha_nacimiento', 'fecha de nacimiento' ), true ) ) {
					$col['birthday'] = $idx;
				} else {
					$col[ $alias ] = $idx;
				}
			}
		}

		if ( ! isset( $col['email'] ) ) {
			fclose( $fh );
			$result['errors'][] = __( 'El CSV debe tener una columna "email" (o "correo").', 'boletines' );
			return $result;
		}

		$row_num = 1;
		while ( ( $row = fgetcsv( $fh, 0, $delimiter ) ) !== false ) {
			$row_num++;
			if ( empty( $row ) || ( count( $row ) === 1 && trim( (string) $row[0] ) === '' ) ) {
				continue;
			}
			$email = isset( $row[ $col['email'] ] ) ? trim( (string) $row[ $col['email'] ] ) : '';
			if ( ! is_email( $email ) ) {
				$result['skipped']++;
				if ( count( $result['errors'] ) < 10 && $email !== '' ) {
					$result['errors'][] = sprintf( __( 'Fila %d: email inválido (%s)', 'boletines' ), $row_num, $email );
				}
				continue;
			}

			$existing = Subscriber::find_by_email( $email );

			$data = array(
				'email'      => $email,
				'first_name' => isset( $col['first_name'], $row[ $col['first_name'] ] ) ? trim( (string) $row[ $col['first_name'] ] ) : '',
				'last_name'  => isset( $col['last_name'], $row[ $col['last_name'] ] ) ? trim( (string) $row[ $col['last_name'] ] ) : '',
				'status'     => isset( $col['status'], $row[ $col['status'] ] ) && trim( (string) $row[ $col['status'] ] ) !== ''
					? trim( (string) $row[ $col['status'] ] )
					: $default_status,
				'source'     => 'csv',
			);

			$sid = Subscriber::upsert( $data );
			if ( $sid ) {
				if ( $existing ) {
					$result['updated']++;
				} else {
					$result['imported']++;
				}
				if ( ! empty( $list_ids ) ) Subscriber::attach_lists( $sid, $list_ids );

				// v1.7 — guardar birthday del CSV.
				if ( isset( $col['birthday'], $row[ $col['birthday'] ] ) ) {
					$bday = trim( (string) $row[ $col['birthday'] ] );
					// Acepta YYYY-MM-DD o DD/MM/YYYY o DD-MM-YYYY.
					if ( $bday !== '' ) {
						$ts = strtotime( str_replace( '/', '-', $bday ) );
						if ( $ts !== false ) {
							Subscriber::set_meta( $sid, 'birthday', gmdate( 'Y-m-d', $ts ) );
						}
					}
				}
			} else {
				$result['skipped']++;
			}
		}

		fclose( $fh );
		return $result;
	}

	private static function detect_delimiter( string $line ): string {
		$candidates = array( ',' => 0, ';' => 0, "\t" => 0, '|' => 0 );
		foreach ( array_keys( $candidates ) as $d ) {
			$candidates[ $d ] = substr_count( $line, $d );
		}
		arsort( $candidates );
		$best = key( $candidates );
		return $candidates[ $best ] > 0 ? $best : ',';
	}

	/**
	 * Genera un CSV con todos los suscriptores filtrables y lo manda al navegador.
	 */
	public static function export( array $args = array() ): void {
		global $wpdb;
		$tbl = Plugin::table( 'subscribers' );
		$piv = Plugin::table( 'subscriber_list' );

		$args = wp_parse_args(
			$args,
			array( 'status' => '', 'list_id' => 0 )
		);

		$where  = array( '1=1' );
		$params = array();
		$join   = '';
		if ( $args['status'] !== '' ) {
			$where[]  = 's.status = %s';
			$params[] = $args['status'];
		}
		if ( $args['list_id'] ) {
			$join     .= " INNER JOIN {$piv} sl ON sl.subscriber_id = s.id ";
			$where[]   = 'sl.list_id = %d';
			$params[]  = (int) $args['list_id'];
		}

		$sql = "SELECT DISTINCT s.id, s.email, s.first_name, s.last_name, s.status, s.source, s.created_at, s.confirmed_at, s.unsubscribed_at
				FROM {$tbl} s {$join} WHERE " . implode( ' AND ', $where ) . ' ORDER BY s.id ASC';

		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		// Cargar meta + listas para cada suscriptor (eficiente: una query por tabla).
		$ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$meta_by_sub  = array();
		$lists_by_sub = array();
		if ( ! empty( $ids ) ) {
			$ids_in = implode( ',', $ids );
			$meta_rows = $wpdb->get_results(
				"SELECT subscriber_id, meta_key, meta_value FROM " . Plugin::table( 'subscriber_meta' ) . " WHERE subscriber_id IN ({$ids_in})",
				ARRAY_A
			);
			foreach ( $meta_rows as $m ) {
				$meta_by_sub[ (int) $m['subscriber_id'] ][ $m['meta_key'] ] = $m['meta_value'];
			}

			$list_rows = $wpdb->get_results(
				"SELECT sl.subscriber_id, l.name FROM " . Plugin::table( 'subscriber_list' ) . " sl
				 INNER JOIN " . Plugin::table( 'lists' ) . " l ON l.id = sl.list_id
				 WHERE sl.subscriber_id IN ({$ids_in})",
				ARRAY_A
			);
			foreach ( $list_rows as $l ) {
				$lists_by_sub[ (int) $l['subscriber_id'] ][] = $l['name'];
			}
		}

		$filename = 'digest-cada-idea-suscriptores-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xef\xbb\xbf" );
		fputcsv( $out, array(
			'email', 'first_name', 'last_name', 'status',
			'birthday',
			'source', 'created_at', 'confirmed_at', 'unsubscribed_at',
			'lists',
		) );
		foreach ( $rows as $r ) {
			$sub_id = (int) $r['id'];
			$meta   = $meta_by_sub[ $sub_id ] ?? array();
			$lists  = $lists_by_sub[ $sub_id ] ?? array();
			fputcsv( $out, array(
				$r['email'],
				$r['first_name'],
				$r['last_name'],
				$r['status'],
				$meta['birthday'] ?? '',
				$r['source'],
				$r['created_at'],
				$r['confirmed_at'],
				$r['unsubscribed_at'],
				implode( ' | ', $lists ),
			) );
		}
		fclose( $out );
		exit;
	}
}
