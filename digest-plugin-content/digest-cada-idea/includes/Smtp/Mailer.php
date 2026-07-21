<?php
namespace Boletines\Smtp;

defined( 'ABSPATH' ) || exit;

/**
 * SMTP integrado (v2.0).
 * - Detecta otros plugins SMTP y NO interfiere.
 * - Fix Brevo/SES: Sender (Return-Path) sólo si dominio está en verified_domains.
 */
class Mailer {
	const OPTION = 'boletines_smtp_settings';

	/** v2.1.6 — TRUE mientras se envía un correo de Digest, para respetar su propio remitente. */
	private static $digest_ctx = false;

	/**
	 * Marca/desmarca el contexto "correo de Digest". En ese contexto NO se fuerza el
	 * remitente del sistema, de modo que Digest use su propio From (doble remitente).
	 */
	public static function set_digest_context( bool $on ): void {
		self::$digest_ctx = $on;
	}

	public function register(): void {
		if ( self::other_smtp_plugin_active() ) return;
		add_action( 'phpmailer_init',  array( $this, 'configure_phpmailer' ), 10, 1 );
		add_action( 'wp_mail_failed',  array( $this, 'capture_failure' ) );
	}

	public static function other_smtp_plugin_active(): bool {
		$indicators = array(
			'\\FluentMail\\App\\Hooks\\Handlers\\GeneralHandler',
			'\\FluentMail\\App\\App',
			'\\WPMailSMTP\\Core',
			'PostmanWpMail',
			'\\EasyWPSMTP\\Core',
		);
		foreach ( $indicators as $cls ) if ( class_exists( $cls ) ) return true;
		return false;
	}

	public static function settings(): array {
		$raw = (array) get_option( self::OPTION, array() );
		return array_merge( array(
			'enabled'          => 0,
			'provider'         => 'custom',
			'smtp_host'        => '',
			'smtp_port'        => 587,
			'smtp_encryption'  => 'tls',
			'smtp_autotls'     => 1,
			'smtp_auth'        => 1,
			'smtp_username'    => '',
			'smtp_password'    => '',
			'from_email'       => '',
			'from_name'        => '',
			'force_from_email' => 1,
			'force_from_name'  => 1,
			'use_sender'       => 1,
			'verified_domains' => '',
			'debug_mode'       => 0,
			'last_error'       => '',
		), $raw );
	}

	public static function save( array $partial ): void {
		$cur = self::settings();
		foreach ( $partial as $k => $v ) $cur[ $k ] = $v;
		update_option( self::OPTION, $cur, false );
	}

	public function configure_phpmailer( $phpmailer ): void {
		$s = self::settings();
		if ( empty( $s['enabled'] ) || empty( $s['smtp_host'] ) ) return;

		$phpmailer->isSMTP();
		$phpmailer->Host = (string) $s['smtp_host'];
		$phpmailer->Port = (int) $s['smtp_port'];
		$phpmailer->SMTPSecure = ( $s['smtp_encryption'] === 'ssl' ) ? 'ssl' : ( ( $s['smtp_encryption'] === 'tls' ) ? 'tls' : '' );
		$phpmailer->SMTPAutoTLS = ! empty( $s['smtp_autotls'] );

		if ( ! empty( $s['smtp_auth'] ) ) {
			$phpmailer->SMTPAuth = true;
			$prov = Providers::get( $s['provider'] );
			$phpmailer->Username = ( $prov && ! empty( $prov['fixed_user'] ) ) ? $prov['fixed_user'] : (string) $s['smtp_username'];
			$phpmailer->Password = (string) $s['smtp_password'];
		} else {
			$phpmailer->SMTPAuth = false;
		}

		// v2.1.6 — Doble remitente:
		//   · Correos de Digest (contexto activo): respetamos el From que Digest ya puso
		//     en las cabeceras (su propio remitente).
		//   · Resto del sistema (core WP, WooCommerce, etc.): forzamos el remitente
		//     configurado aquí en "Remitente del sistema".
		$is_digest = self::$digest_ctx;

		if ( ! $is_digest && ! empty( $s['from_email'] ) ) {
			$from_email = sanitize_email( $s['from_email'] );
			if ( ! empty( $s['force_from_email'] ) || empty( $phpmailer->From ) )     $phpmailer->From     = $from_email;
			if ( ! empty( $s['force_from_name'] )  || empty( $phpmailer->FromName ) )  $phpmailer->FromName = (string) $s['from_name'];
		}

		// FIX BREVO/SES: Sender (Return-Path) según el From EFECTIVO (sea del sistema o de
		// Digest) y sólo si el dominio está verificado.
		if ( ! empty( $s['use_sender'] ) && ! empty( $phpmailer->From ) ) {
			$eff_from    = (string) $phpmailer->From;
			$from_domain = ( strpos( $eff_from, '@' ) !== false )
				? strtolower( substr( $eff_from, strpos( $eff_from, '@' ) + 1 ) ) : '';
			$verified = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $s['verified_domains'] ) ) ) );
			if ( empty( $verified ) || in_array( $from_domain, $verified, true ) ) {
				$phpmailer->Sender = $eff_from;
			}
		}

		$phpmailer->CharSet = 'UTF-8';
		$phpmailer->Timeout = (int) apply_filters( 'boletines_smtp_timeout', 30 );

		if ( ! empty( $s['debug_mode'] ) ) {
			$phpmailer->SMTPDebug   = 2;
			$phpmailer->Debugoutput = function ( $str, $level ) {
				error_log( '[Boletines SMTP] ' . trim( (string) $str ) );
			};
		}
	}

	public function capture_failure( $wp_error ): void {
		if ( ! is_wp_error( $wp_error ) ) return;
		$msg = mb_substr( (string) $wp_error->get_error_message(), 0, 500 );
		self::save( array( 'last_error' => '[' . current_time( 'mysql' ) . '] ' . $msg ) );
	}

	/**
	 * Envía test. Devuelve [ok, message, debug].
	 */
	public static function send_test( string $to ): array {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) return array( false, __( 'Email no válido.', 'boletines' ), '' );

		$debug = '';
		$cap_dbg = function ( $str, $level ) use ( &$debug ) { $debug .= trim( (string) $str ) . "\n"; };
		add_action( 'phpmailer_init', function ( $pm ) use ( $cap_dbg ) {
			$pm->SMTPDebug   = 2;
			$pm->Debugoutput = $cap_dbg;
		}, 99, 1 );

		$captured_error = '';
		$err_cap = function ( $we ) use ( &$captured_error ) {
			if ( is_wp_error( $we ) ) $captured_error = $we->get_error_message();
		};
		add_action( 'wp_mail_failed', $err_cap );

		$subject = sprintf( __( 'Prueba SMTP — %s', 'boletines' ), get_bloginfo( 'name' ) );
		$body    = __( 'Si recibes este correo, tu SMTP funciona.', 'boletines' )
			. "\n" . __( 'Hora:', 'boletines' ) . ' ' . current_time( 'mysql' );

		$ok = wp_mail( $to, $subject, $body );
		remove_action( 'wp_mail_failed', $err_cap );

		if ( $ok ) return array( true, __( 'Correo enviado.', 'boletines' ), $debug );
		$msg = __( 'Falló el envío.', 'boletines' ) . ( $captured_error !== '' ? ' ' . $captured_error : '' );
		return array( false, $msg, $debug );
	}
}
