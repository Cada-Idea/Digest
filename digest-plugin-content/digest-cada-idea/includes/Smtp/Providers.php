<?php
namespace Boletines\Smtp;

defined( 'ABSPATH' ) || exit;

class Providers {
	public static function all(): array {
		return array(
			'custom'    => array( 'name' => __( 'SMTP personalizado', 'boletines' ), 'host' => '', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'Configura manualmente cualquier servidor SMTP.', 'boletines' ) ),
			'gmail'     => array( 'name' => 'Gmail / Workspace', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'Usa una contraseña de aplicación de Google.', 'boletines' ), 'help_url' => 'https://support.google.com/accounts/answer/185833' ),
			'outlook'   => array( 'name' => 'Outlook / Office 365', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'SMTP de Microsoft.', 'boletines' ) ),
			'brevo'     => array( 'name' => 'Brevo (Sendinblue)', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'Usuario = email Brevo. Password = SMTP key (no la web). El dominio del From DEBE estar verificado en Brevo.', 'boletines' ), 'help_url' => 'https://help.brevo.com/hc/en-us/articles/7924908994450' ),
			'sendgrid'  => array( 'name' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'fixed_user' => 'apikey', 'description' => __( 'Usuario = "apikey", password = tu API Key.', 'boletines' ) ),
			'mailgun'   => array( 'name' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'Email transaccional.', 'boletines' ) ),
			'ses'       => array( 'name' => 'Amazon SES (SMTP)', 'host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'Credenciales SMTP. Cambia el host por tu región.', 'boletines' ) ),
			'postmark'  => array( 'name' => 'Postmark', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'User y password = server token.', 'boletines' ) ),
			'zoho'      => array( 'name' => 'Zoho Mail', 'host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'SMTP de Zoho.', 'boletines' ) ),
			'mailersend'=> array( 'name' => 'MailerSend', 'host' => 'smtp.mailersend.net', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => '' ),
			'smtp2go'   => array( 'name' => 'SMTP2GO', 'host' => 'mail.smtp2go.com', 'port' => 2525, 'encryption' => 'tls', 'auth' => true, 'description' => '' ),
			'yahoo'     => array( 'name' => 'Yahoo Mail', 'host' => 'smtp.mail.yahoo.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true, 'description' => __( 'Requiere contraseña de aplicación.', 'boletines' ) ),
		);
	}
	public static function get( string $slug ): ?array {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}
}
