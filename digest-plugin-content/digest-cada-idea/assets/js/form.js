/**
 * Boletines — Frontend form behavior (vanilla JS, sin jQuery).
 * Hace submit por AJAX al endpoint REST y muestra feedback inline.
 */
(function () {
	'use strict';

	if ( ! window.BoletinesForm || ! window.BoletinesForm.restUrl ) return;

	const REST_URL = window.BoletinesForm.restUrl;

	function init() {
		const wrappers = document.querySelectorAll( '[data-boletines-form]' );
		wrappers.forEach( setup );
	}

	function setup( wrap ) {
		const form     = wrap.querySelector( 'form' );
		const feedback = wrap.querySelector( '.bol-form-feedback' );
		const button   = wrap.querySelector( '.bol-form-submit' );
		if ( ! form || ! button ) return;

		// Si ya estaba marcado (idempotencia) lo evitamos.
		if ( form.dataset.bolBound === '1' ) return;
		form.dataset.bolBound = '1';

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			submit( form, button, feedback, wrap );
		} );
	}

	function submit( form, button, feedback, wrap ) {
		// Construir payload
		const fd     = new FormData( form );
		const lists  = [];
		fd.getAll( 'lists[]' ).forEach( function ( v ) { lists.push( parseInt( v, 10 ) || 0 ); } );

		// Validación mínima cliente
		const email = ( fd.get( 'email' ) || '' ).toString().trim();
		if ( ! email || email.indexOf( '@' ) < 1 ) {
			showFeedback( feedback, 'error', 'Introduce un correo válido.' );
			return;
		}

		const payload = {
			nonce:         fd.get( 'bol_nonce' ),
			email:         email,
			first_name:    ( fd.get( 'first_name' )    || '' ).toString().trim(),
			last_name:     ( fd.get( 'last_name' )     || '' ).toString().trim(),
			phone_country: ( fd.get( 'phone_country' ) || '' ).toString().trim(),
			phone:         ( fd.get( 'phone' )         || '' ).toString().trim(),
			birthday:      ( fd.get( 'birthday' )      || '' ).toString().trim(),
			lists:         lists,
			form_id:       parseInt( fd.get( 'form_id' ) || '0', 10 ) || 0,
			website:       fd.get( 'website' ) || ''
		};

		setLoading( button, true );
		hideFeedback( feedback );

		fetch( REST_URL, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
			body: JSON.stringify( payload )
		} )
			.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, status: r.status, body: j }; } ); } )
			.then( function ( res ) {
				setLoading( button, false );
				if ( res.body && res.body.success ) {
					const tone = res.body.pending_confirm ? 'info' : 'success';
					showFeedback( feedback, tone, res.body.message );
					// Marcamos done — el CSS oculta el form.
					wrap.classList.add( 'is-done' );
					try { form.reset(); } catch ( _ ) {}
				} else {
					const msg = ( res.body && res.body.message ) ? res.body.message : 'No pudimos procesar tu suscripción.';
					showFeedback( feedback, 'error', msg );
				}
			} )
			.catch( function () {
				setLoading( button, false );
				showFeedback( feedback, 'error', 'No pudimos conectar. Revisa tu conexión y vuelve a intentar.' );
			} );
	}

	function setLoading( button, on ) {
		if ( on ) {
			button.classList.add( 'is-loading' );
			button.setAttribute( 'disabled', 'disabled' );
		} else {
			button.classList.remove( 'is-loading' );
			button.removeAttribute( 'disabled' );
		}
	}

	function showFeedback( el, tone, html ) {
		if ( ! el ) return;
		el.className = 'bol-form-feedback is-' + tone;
		el.innerHTML = html;
		el.hidden    = false;
	}
	function hideFeedback( el ) {
		if ( ! el ) return;
		el.hidden = true;
		el.className = 'bol-form-feedback';
		el.innerHTML = '';
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
