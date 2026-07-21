<?php
namespace Boletines\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detector de inconsistencias del estado del plugin.
 *
 * Caso principal a detectar: tras una migración (db_version cambió), las
 * tablas críticas existen pero están vacías. Esto es sospechoso en
 * instalaciones que NO son nuevas (ya tenían un db_version anterior).
 *
 * También detecta otras anomalías: tablas faltantes, opciones huérfanas,
 * crons rotos, etc.
 */
class Inconsistency {

	/**
	 * Ejecuta todas las comprobaciones y devuelve un array con los hallazgos.
	 *
	 * @return array<int, array{
	 *   id: string,
	 *   level: string,     // 'critical' | 'warning' | 'info'
	 *   title: string,
	 *   description: string,
	 *   action: string     // sugerencia de acción
	 * }>
	 */
	public static function run_all(): array {
		$findings = array();

		$findings = array_merge( $findings, self::check_table_existence() );
		$findings = array_merge( $findings, self::check_empty_after_migration() );
		$findings = array_merge( $findings, self::check_options() );
		$findings = array_merge( $findings, self::check_cron() );

		return $findings;
	}

	/**
	 * Versión rápida: ¿hay alguna inconsistencia crítica que debamos mostrar
	 * como banner persistente en el admin?
	 */
	public static function has_critical(): bool {
		foreach ( self::run_all() as $finding ) {
			if ( $finding['level'] === 'critical' ) {
				return true;
			}
		}
		return false;
	}

	// -------------------------------------------------------------------
	// Comprobaciones individuales
	// -------------------------------------------------------------------

	/**
	 * Verifica que todas las tablas críticas existan en la BD.
	 */
	public static function check_table_existence(): array {
		global $wpdb;
		$findings = array();
		$tables   = self::critical_tables();

		foreach ( $tables as $short_name => $full_name ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name ) );
			if ( ! $exists ) {
				$findings[] = array(
					'id'          => 'missing_table_' . $short_name,
					'level'       => 'critical',
					'title'       => sprintf(
						/* translators: %s: table name */
						__( 'Tabla faltante: %s', 'boletines' ),
						$full_name
					),
					'description' => __( 'Esta tabla debería existir pero no se encuentra en la base de datos. Probablemente un fallo de instalación.', 'boletines' ),
					'action'      => __( 'Ve a Digest → Salud y pulsa "Reintentar migración" para volver a ejecutar el Installer. Si no soluciona, restaura un snapshot.', 'boletines' ),
				);
			}
		}

		return $findings;
	}

	/**
	 * El check más importante: si las tablas existen pero están vacías,
	 * Y el plugin NO es una instalación nueva (tenía db_version previo
	 * que vino antes que el actual), eso es muy sospechoso.
	 */
	public static function check_empty_after_migration(): array {
		global $wpdb;
		$findings = array();

		// ¿Cuándo fue la primera instalación?
		$first_install = get_option( 'boletines_first_install' );
		if ( ! $first_install ) {
			// Si nunca se registró, consideramos esta sesión la primera.
			return $findings;
		}

		// Si la primera instalación fue hace menos de 5 minutos, asumimos que
		// estamos en pleno proceso de instalación inicial y no hay nada raro.
		$first_install_ts = strtotime( $first_install );
		if ( $first_install_ts && ( time() - $first_install_ts ) < 300 ) {
			return $findings;
		}

		// Comprobar tablas críticas que esperamos tengan datos en instalaciones
		// activas. "Subscribers" y "lists" son las dos críticas.
		$prefix = $wpdb->prefix . BOLETINES_TABLE_PREFIX;

		$expected_with_data = array(
			'lists'       => __( 'Listas', 'boletines' ),
			'subscribers' => __( 'Suscriptores', 'boletines' ),
		);

		foreach ( $expected_with_data as $short => $label ) {
			$table   = $prefix . $short;
			$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				continue; // ya lo cubre check_table_existence
			}
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

			// Caso especial: lists puede tener 1 fila (la "Lista principal" por defecto)
			// pero eso no significa que el usuario tenga datos reales.
			$threshold = ( $short === 'lists' ) ? 2 : 1;

			if ( $count < $threshold ) {
				$findings[] = array(
					'id'          => 'empty_after_migration_' . $short,
					'level'       => 'critical',
					'title'       => sprintf(
						/* translators: %s: table label like "Suscriptores" */
						__( '%s aparece vacío y tu instalación NO es nueva', 'boletines' ),
						$label
					),
					'description' => sprintf(
						/* translators: 1: table label, 2: first install date */
						__( 'La tabla de %1$s está vacía, pero tu plugin se instaló por primera vez el %2$s. Si nunca habías usado esta sección y la dejaste vacía adrede, ignora este aviso. Si tenías datos, deberías revisar inmediatamente.', 'boletines' ),
						$label,
						$first_install
					),
					'action'      => __( 'Revisa los snapshots disponibles en Digest → Salud y restaura el más reciente con datos. Si no hay snapshots, contacta a soporte de tu hosting para restaurar un backup de la BD.', 'boletines' ),
				);
			}
		}

		return $findings;
	}

	/**
	 * Verifica que las opciones críticas existan y tengan estructura correcta.
	 */
	public static function check_options(): array {
		$findings = array();

		$settings = get_option( 'boletines_settings', null );
		if ( null === $settings || false === $settings ) {
			$findings[] = array(
				'id'          => 'missing_settings_option',
				'level'       => 'critical',
				'title'       => __( 'Opción boletines_settings ausente', 'boletines' ),
				'description' => __( 'La opción principal de configuración no existe. El plugin no puede funcionar sin ella.', 'boletines' ),
				'action'      => __( 'Reactiva el plugin desde Plugins → Plugins instalados para regenerar las opciones por defecto.', 'boletines' ),
			);
		} elseif ( ! is_array( $settings ) ) {
			$findings[] = array(
				'id'          => 'corrupt_settings_option',
				'level'       => 'critical',
				'title'       => __( 'Opción boletines_settings corrupta', 'boletines' ),
				'description' => __( 'La opción existe pero no es un array. La configuración está corrupta.', 'boletines' ),
				'action'      => __( 'Restaura el snapshot más reciente desde Digest → Salud.', 'boletines' ),
			);
		}

		// db_version coincide con BOLETINES_DB_VERSION
		$db_version = get_option( 'boletines_db_version', '' );
		if ( defined( 'BOLETINES_DB_VERSION' ) && $db_version !== BOLETINES_DB_VERSION ) {
			$findings[] = array(
				'id'          => 'db_version_mismatch',
				'level'       => 'warning',
				'title'       => __( 'La versión de la BD no coincide con la del código', 'boletines' ),
				'description' => sprintf(
					/* translators: 1: db version, 2: code version */
					__( 'BD: %1$s — Código: %2$s. La migración no se completó.', 'boletines' ),
					$db_version,
					BOLETINES_DB_VERSION
				),
				'action'      => __( 'Ve a Digest → Salud → Reintentar migración. Si persiste, revisa los logs de PHP del hosting.', 'boletines' ),
			);
		}

		return $findings;
	}

	/**
	 * Verifica que WP-Cron esté funcionando y que nuestros eventos estén programados.
	 */
	public static function check_cron(): array {
		$findings = array();

		// WP-Cron deshabilitado por completo.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$findings[] = array(
				'id'          => 'wp_cron_disabled',
				'level'       => 'warning',
				'title'       => __( 'WP-Cron está deshabilitado', 'boletines' ),
				'description' => __( 'Tienes definido DISABLE_WP_CRON en tu wp-config. Si no has configurado un cron real del sistema operativo, los envíos programados no funcionarán.', 'boletines' ),
				'action'      => __( 'Configura un cron del sistema que llame a wp-cron.php cada 5-15 minutos, o quita la constante DISABLE_WP_CRON.', 'boletines' ),
			);
		}

		// Nuestros eventos programados.
		$expected_hooks = array(
			'boletines_cron_send'  => __( 'Envío de campañas', 'boletines' ),
			'boletines_cron_daily' => __( 'Tarea diaria (cleanup, digests)', 'boletines' ),
		);

		foreach ( $expected_hooks as $hook => $label ) {
			$next = wp_next_scheduled( $hook );
			if ( ! $next ) {
				$findings[] = array(
					'id'          => 'cron_not_scheduled_' . $hook,
					'level'       => 'warning',
					'title'       => sprintf(
						/* translators: %s: cron label */
						__( 'Evento de cron no programado: %s', 'boletines' ),
						$label
					),
					'description' => sprintf(
						/* translators: %s: hook name */
						__( 'El hook %s no está programado. Los envíos no se procesarán hasta que se programe.', 'boletines' ),
						$hook
					),
					'action'      => __( 'Desactiva y reactiva el plugin desde Plugins → Plugins instalados para reprogramar los crons.', 'boletines' ),
				);
			}
		}

		return $findings;
	}

	// -------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------

	private static function critical_tables(): array {
		global $wpdb;
		$prefix = $wpdb->prefix . BOLETINES_TABLE_PREFIX;
		$tables = array(
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
		$result = array();
		foreach ( $tables as $short ) {
			$result[ $short ] = $prefix . $short;
		}
		return $result;
	}
}
