<?php
namespace Boletines\Snapshots;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sistema de snapshots automáticos antes de cualquier migración del plugin.
 *
 * Cómo funciona:
 *   1. Antes de ejecutar Installer::install() en un cambio de versión,
 *      llamamos a `Manager::capture()` para guardar un dump SQL plano de
 *      las tablas críticas en /wp-content/uploads/digest-backups/.
 *   2. El dump es legible: cualquier admin puede abrirlo, leerlo, importarlo
 *      con phpMyAdmin manualmente si hace falta.
 *   3. Si la migración falla o detecta inconsistencias, el plugin ofrece
 *      un botón "Restaurar snapshot anterior".
 *   4. Los snapshots se rotan: se conservan los últimos 5 por defecto,
 *      configurable con el filtro `boletines_snapshots_keep`.
 *
 * El snapshot NO sustituye al backup del hosting; es una red de seguridad
 * mínima para el plugin específicamente, no para todo el sitio.
 */
class Manager {

	/** Tablas críticas que SIEMPRE se snapshottean (sin prefijo, sin bol_). */
	const CRITICAL_TABLES = array(
		'subscribers',
		'lists',
		'subscriber_list',
		'subscriber_meta',
		'campaigns',
		'campaign_emails',
		'forms',
		'automations',
		'automation_runs',
		'bounces',
		'clicks',
	);

	/**
	 * Captura un snapshot de todas las tablas críticas.
	 *
	 * @param string $label Etiqueta para identificar el snapshot (ej: "pre-upgrade-1.9.0-to-1.9.1").
	 * @return array{file: string, size: int, tables: int, rows: int, error: string}
	 */
	public static function capture( string $label = 'manual' ): array {
		$dir = self::backups_dir();
		if ( ! self::ensure_dir( $dir ) ) {
			return self::error_response( __( 'No se pudo crear el directorio de snapshots.', 'boletines' ) );
		}

		$timestamp = current_time( 'Y-m-d-His' );
		$slug      = sanitize_file_name( $label );
		$filename  = "snapshot-{$slug}-{$timestamp}.sql";
		$filepath  = $dir . '/' . $filename;

		$sql_lines = array();
		$sql_lines[] = '-- Boletines (Digest by Cada Idea) Snapshot';
		$sql_lines[] = '-- Created: ' . current_time( 'mysql' );
		$sql_lines[] = '-- Label: ' . $label;
		$sql_lines[] = '-- Plugin version: ' . ( defined( 'BOLETINES_VERSION' ) ? BOLETINES_VERSION : 'unknown' );
		$sql_lines[] = '-- ';
		$sql_lines[] = '-- ¿Cómo restaurar?';
		$sql_lines[] = '--   1) Importar este archivo desde phpMyAdmin a tu base de datos.';
		$sql_lines[] = '--   2) O usar la página Digest → Salud → Restaurar snapshot.';
		$sql_lines[] = '';
		$sql_lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
		$sql_lines[] = 'SET sql_mode = "NO_AUTO_VALUE_ON_ZERO";';
		$sql_lines[] = '';

		global $wpdb;
		$total_tables = 0;
		$total_rows   = 0;

		foreach ( self::CRITICAL_TABLES as $table_short ) {
			$table = $wpdb->prefix . BOLETINES_TABLE_PREFIX . $table_short;

			// ¿Existe la tabla?
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				$sql_lines[] = "-- Tabla {$table} NO EXISTE, omitida.";
				$sql_lines[] = '';
				continue;
			}

			$total_tables++;

			// Estructura.
			$create_row = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
			if ( $create_row && isset( $create_row[1] ) ) {
				$sql_lines[] = "-- ----------------------------------------------------------";
				$sql_lines[] = "-- Tabla: {$table}";
				$sql_lines[] = "-- ----------------------------------------------------------";
				$sql_lines[] = "DROP TABLE IF EXISTS `{$table}`;";
				$sql_lines[] = $create_row[1] . ';';
				$sql_lines[] = '';
			}

			// Datos en lotes para no fundir la memoria con tablas grandes.
			$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			$total_rows += $row_count;

			if ( $row_count > 0 ) {
				$batch_size = 500;
				$batches    = ceil( $row_count / $batch_size );

				for ( $b = 0; $b < $batches; $b++ ) {
					$offset = $b * $batch_size;
					$rows   = $wpdb->get_results( "SELECT * FROM `{$table}` LIMIT {$batch_size} OFFSET {$offset}", ARRAY_A );
					if ( empty( $rows ) ) {
						continue;
					}

					foreach ( $rows as $row ) {
						$values = array();
						foreach ( $row as $value ) {
							if ( null === $value ) {
								$values[] = 'NULL';
							} else {
								$values[] = "'" . esc_sql( (string) $value ) . "'";
							}
						}
						$sql_lines[] = "INSERT INTO `{$table}` VALUES (" . implode( ',', $values ) . ');';
					}
				}
				$sql_lines[] = '';
			}
		}

		// Opciones del plugin.
		$sql_lines[] = "-- ----------------------------------------------------------";
		$sql_lines[] = "-- Opciones (wp_options con prefijo boletines_)";
		$sql_lines[] = "-- ----------------------------------------------------------";
		$options = $wpdb->get_results(
			"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'boletines_%'",
			ARRAY_A
		);
		foreach ( $options as $opt ) {
			$name     = esc_sql( $opt['option_name'] );
			$value    = esc_sql( $opt['option_value'] );
			$autoload = esc_sql( $opt['autoload'] );
			$sql_lines[] = "-- Option: {$name}";
			$sql_lines[] = "DELETE FROM {$wpdb->options} WHERE option_name = '{$name}';";
			$sql_lines[] = "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('{$name}', '{$value}', '{$autoload}');";
			$sql_lines[] = '';
		}

		$sql_lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
		$sql_lines[] = '-- Fin del snapshot.';

		$contents = implode( "\n", $sql_lines );
		$written  = @file_put_contents( $filepath, $contents );

		if ( false === $written ) {
			return self::error_response( __( 'No se pudo escribir el snapshot al disco.', 'boletines' ) );
		}

		// Registro del snapshot en options para el listado posterior.
		$registry = self::registry();
		$registry[ $filename ] = array(
			'file'      => $filename,
			'label'     => $label,
			'timestamp' => current_time( 'mysql' ),
			'size'      => filesize( $filepath ),
			'tables'    => $total_tables,
			'rows'      => $total_rows,
			'version'   => defined( 'BOLETINES_VERSION' ) ? BOLETINES_VERSION : '',
		);
		update_option( 'boletines_snapshots_registry', $registry, false );

		// Rotación: conservar sólo los N últimos.
		self::rotate();

		return array(
			'file'   => $filename,
			'size'   => filesize( $filepath ),
			'tables' => $total_tables,
			'rows'   => $total_rows,
			'error'  => '',
		);
	}

	/**
	 * Restaura un snapshot ejecutando su SQL.
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function restore( string $filename ): array {
		$dir      = self::backups_dir();
		$filepath = $dir . '/' . basename( $filename ); // basename evita path traversal

		if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
			return array( 'success' => false, 'message' => __( 'El snapshot no existe o no es legible.', 'boletines' ) );
		}

		// Antes de restaurar, captura del estado actual por si la restauración va mal.
		self::capture( 'pre-restore-safety' );

		$contents = file_get_contents( $filepath );
		if ( false === $contents ) {
			return array( 'success' => false, 'message' => __( 'No se pudo leer el archivo.', 'boletines' ) );
		}

		global $wpdb;
		$statements = self::split_statements( $contents );
		$executed = 0;
		$errors   = array();

		foreach ( $statements as $stmt ) {
			$stmt = trim( $stmt );
			if ( '' === $stmt ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $stmt );
			if ( false === $result ) {
				$errors[] = $wpdb->last_error;
			} else {
				$executed++;
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: executed count, 2: error count, 3: first error */
					__( 'Restauración parcial: %1$d sentencias ejecutadas, %2$d errores. Primer error: %3$s', 'boletines' ),
					$executed,
					count( $errors ),
					(string) $errors[0]
				),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of executed statements */
				__( 'Snapshot restaurado correctamente (%d sentencias ejecutadas).', 'boletines' ),
				$executed
			),
		);
	}

	/**
	 * Lista todos los snapshots disponibles, ordenados por timestamp DESC.
	 */
	public static function list_snapshots(): array {
		$registry = self::registry();
		uasort( $registry, function ( $a, $b ) {
			return strcmp( $b['timestamp'], $a['timestamp'] );
		} );
		return $registry;
	}

	/**
	 * Borra un snapshot del disco y del registro.
	 */
	public static function delete( string $filename ): bool {
		$dir      = self::backups_dir();
		$filepath = $dir . '/' . basename( $filename );

		$removed_disk = file_exists( $filepath ) ? @unlink( $filepath ) : true;

		$registry = self::registry();
		if ( isset( $registry[ $filename ] ) ) {
			unset( $registry[ $filename ] );
			update_option( 'boletines_snapshots_registry', $registry, false );
		}

		return $removed_disk;
	}

	/**
	 * Rota los snapshots: conserva los últimos N (por defecto 5).
	 */
	public static function rotate(): void {
		$keep     = (int) apply_filters( 'boletines_snapshots_keep', 5 );
		$snapshots = self::list_snapshots();

		if ( count( $snapshots ) <= $keep ) {
			return;
		}

		$to_delete = array_slice( array_keys( $snapshots ), $keep );
		foreach ( $to_delete as $filename ) {
			self::delete( $filename );
		}
	}

	// -------------------------------------------------------------------
	// Helpers internos
	// -------------------------------------------------------------------

	public static function backups_dir(): string {
		$upload    = wp_upload_dir();
		$base_dir  = isset( $upload['basedir'] ) ? $upload['basedir'] : WP_CONTENT_DIR . '/uploads';
		return $base_dir . '/digest-backups';
	}

	public static function backups_url(): string {
		$upload = wp_upload_dir();
		$base   = isset( $upload['baseurl'] ) ? $upload['baseurl'] : content_url( 'uploads' );
		return $base . '/digest-backups';
	}

	private static function ensure_dir( string $dir ): bool {
		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
		}

		// Proteger con .htaccess y un index.php para que no sea browseable.
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Deny from all\n" );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		return is_writable( $dir );
	}

	private static function registry(): array {
		$value = get_option( 'boletines_snapshots_registry', array() );
		return is_array( $value ) ? $value : array();
	}

	private static function error_response( string $message ): array {
		return array(
			'file'   => '',
			'size'   => 0,
			'tables' => 0,
			'rows'   => 0,
			'error'  => $message,
		);
	}

	/**
	 * Parte un dump SQL en sentencias individuales respetando comillas.
	 * No es un parser perfecto, pero suficiente para los INSERTs que nosotros generamos.
	 */
	private static function split_statements( string $sql ): array {
		$lines = explode( "\n", $sql );
		$statements = array();
		$current    = '';

		foreach ( $lines as $line ) {
			$trim = trim( $line );
			if ( '' === $trim || 0 === strpos( $trim, '--' ) ) {
				continue;
			}
			$current .= ' ' . $line;
			// Sentencia termina con ; al final de línea (no dentro de string).
			if ( substr( $trim, -1 ) === ';' ) {
				$statements[] = trim( $current );
				$current = '';
			}
		}
		if ( '' !== trim( $current ) ) {
			$statements[] = trim( $current );
		}
		return $statements;
	}
}
