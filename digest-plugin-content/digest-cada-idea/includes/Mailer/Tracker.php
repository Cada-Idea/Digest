<?php
namespace Boletines\Mailer;

use Boletines\Models\CampaignEmail;
use Boletines\Models\Subscriber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoints públicos vía rewrite "ligero":
 *  - /?boletines_track=open&t=TOKEN
 *  - /?boletines_track=click&t=TOKEN&u=URL
 *  - /?boletines_action=unsubscribe&sid=ID&token=...
 *  - /?boletines_action=confirm&sid=ID&token=...
 *
 * v2.1.1 — Nuevos endpoints path-based (inmunes a WAFs que pelan query strings):
 *  - /boletines-action/preferences/{sid}/{token}/
 *  - /boletines-action/unsubscribe/{sid}/{token}/
 *  - /boletines-action/confirm/{sid}/{token}/
 *
 * El formato viejo (?boletines_action=...) sigue funcionando como fallback
 * para emails en circulación. Se prefiere el path-based para emails nuevos.
 */
class Tracker {

	public function register(): void {
		// Legacy vía $_GET (ya disponible en init).
		add_action( 'init', array( $this, 'handle' ), 1 );
		// v2.1.3 — Path-based: las query vars del rewrite se parsean en parse_request,
		// DESPUÉS de init. Si lo leemos en init, get_query_var() está vacío y WP acaba
		// mostrando la home. template_redirect corre con las query vars ya listas.
		add_action( 'template_redirect', array( $this, 'handle_path' ), 0 );
	}

	/**
	 * v2.1.3 — Handler de las URLs path-based (/boletines-action/...).
	 * Debe correr en template_redirect (query vars ya parseados).
	 */
	public function handle_path(): void {
		$action_path = get_query_var( 'boletines_action_path' );
		if ( ! $action_path ) {
			return;
		}

		$sid   = (int) get_query_var( 'bol_sid' );
		$token = (string) get_query_var( 'bol_token' );
		$token = $token !== '' ? sanitize_text_field( wp_unslash( $token ) ) : '';
		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST';

		if ( $action_path === 'unsubscribe' ) {
			$this->handle_unsubscribe( $is_post, $sid, $token );
		} elseif ( $action_path === 'confirm' ) {
			$this->handle_confirm( $sid, $token );
		} elseif ( $action_path === 'preferences' ) {
			( new \Boletines\Forms\Preferences() )->render_standalone( $sid, $token );
		} elseif ( $action_path === 'click' ) {
			// Clic path-based: token de campaña + destino en base64url.
			$data = (string) get_query_var( 'bol_click_data' );
			$url  = '';
			if ( $data !== '' ) {
				$pad     = strlen( $data ) % 4;
				$b64     = strtr( $data, '-_', '+/' ) . ( $pad ? str_repeat( '=', 4 - $pad ) : '' );
				$decoded = base64_decode( $b64, true );
				if ( $decoded !== false ) {
					$url = esc_url_raw( $decoded );
				}
			}
			$this->handle_click( $token, $url );
		}
		exit;
	}

	public function handle(): void {
		if ( isset( $_GET['boletines_track'] ) ) {
			$type  = sanitize_text_field( wp_unslash( $_GET['boletines_track'] ) );
			$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
			if ( $type === 'open' ) {
				$this->handle_open( $token );
			} elseif ( $type === 'click' ) {
				$url = isset( $_GET['u'] ) ? esc_url_raw( wp_unslash( $_GET['u'] ) ) : '';
				$this->handle_click( $token, $url );
			}
			exit;
		}

		if ( isset( $_GET['boletines_action'] ) ) {
			$action  = sanitize_text_field( wp_unslash( $_GET['boletines_action'] ) );
			$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST';
			if ( $action === 'unsubscribe' ) {
				$this->handle_unsubscribe( $is_post );
			} elseif ( $action === 'confirm' ) {
				$this->handle_confirm();
			} elseif ( $action === 'preferences' ) {
				$sid   = isset( $_GET['sid'] )   ? (int) $_GET['sid'] : 0;
				$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
				( new \Boletines\Forms\Preferences() )->render_standalone( $sid, $token );
			}
		}
	}

	private function handle_open( string $token ): void {
		if ( $token ) {
			$row = CampaignEmail::find_by_token( $token );
			if ( $row ) {
				CampaignEmail::record_open( (int) $row['id'] );
			}
		}
		// 1x1 transparente.
		nocache_headers();
		header( 'Content-Type: image/gif' );
		echo base64_decode( 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
	}

	private function handle_click( string $token, string $url ): void {
		if ( $token ) {
			$row = CampaignEmail::find_by_token( $token );
			if ( $row && $url ) {
				CampaignEmail::record_click( (int) $row['id'], $url );
			}
		}
		nocache_headers();
		if ( $url ) {
			wp_safe_redirect( $url, 302 );
		} else {
			wp_safe_redirect( home_url() );
		}
	}

	private function handle_unsubscribe( bool $is_post = false, int $sid = 0, string $token = '' ): void {
		// v2.1.1 — Si nos llegaron por path-based usamos esos; si no, leemos de $_REQUEST
		// (formato legacy con query string).
		if ( ! $sid ) {
			$sid = isset( $_REQUEST['sid'] ) ? (int) $_REQUEST['sid'] : 0;
		}
		if ( $token === '' ) {
			$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		}

		$sub = Subscriber::find_by_id( $sid );
		if ( ! $sub || ! hash_equals( $sub['token'], $token ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Enlace inválido o caducado.', 'boletines' ), 403 );
		}

		Subscriber::unsubscribe( $sid );

		if ( $is_post ) {
			// One-Click: respondemos sin contenido.
			status_header( 200 );
			exit;
		}

		// Página simple de confirmación.
		$this->render_simple_page(
			__( 'Te has dado de baja', 'boletines' ),
			sprintf(
				/* translators: %s = email */
				esc_html__( 'Hemos eliminado %s de nuestra lista de envíos. Lamentamos verte ir.', 'boletines' ),
				'<strong>' . esc_html( $sub['email'] ) . '</strong>'
			)
		);
		exit;
	}

	private function handle_confirm( int $sid = 0, string $token = '' ): void {
		// v2.1.1 — Mismo patrón que handle_unsubscribe.
		if ( ! $sid ) {
			$sid = isset( $_GET['sid'] ) ? (int) $_GET['sid'] : 0;
		}
		if ( $token === '' ) {
			$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		}

		$sub = Subscriber::find_by_id( $sid );
		if ( ! $sub || ! hash_equals( $sub['token'], $token ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Enlace inválido o caducado.', 'boletines' ), 403 );
		}

		Subscriber::confirm( $sid );

		// Disparar correo de bienvenida si está activado.
		if ( (int) \Boletines\Plugin::get_setting( 'welcome_enabled' ) === 1 ) {
			$mailer  = new Mailer();
			$subject = (string) \Boletines\Plugin::get_setting( 'welcome_subject', '¡Bienvenido!' );
			$body    = (string) \Boletines\Plugin::get_setting( 'welcome_body', '' );
			$body    = strtr(
				$body,
				array(
					'{first_name}' => $sub['first_name'] ?: '',
					'{site_name}'  => get_bloginfo( 'name' ),
				)
			);
			$mailer->send_simple( $sub['email'], $subject, $body );
		}

		$this->render_simple_page(
			__( 'Suscripción confirmada', 'boletines' ),
			esc_html__( '¡Gracias! Tu suscripción está confirmada y recibirás nuestros próximos envíos.', 'boletines' )
		);
		exit;
	}

	private function render_simple_page( string $title, string $message ): void {
		nocache_headers();
		?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?></title>
<style>
body{margin:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,sans-serif;color:#111827;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px;}
.box{background:#fff;padding:40px;border-radius:12px;max-width:480px;width:100%;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,.06);}
h1{margin:0 0 12px;font-size:24px;}
p{margin:0;line-height:1.5;color:#4b5563;}
a{color:#2563eb;}
</style>
</head>
<body>
<div class="box">
	<h1><?php echo esc_html( $title ); ?></h1>
	<p><?php echo $message; // ya escapado donde aplica. ?></p>
	<p style="margin-top:24px;"><a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?> &rarr;</a></p>
</div>
</body>
</html>
		<?php
	}
}
