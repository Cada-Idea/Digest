/**
 * Boletines — Página de preferencias del suscriptor.
 * Toggle por lista, "subscribe to all", guardar preferencias, baja total.
 */
(function () {
	'use strict';

	if ( ! window.BoletinesPreferences || ! window.BoletinesPreferences.restUrl ) return;
	const REST_URL = window.BoletinesPreferences.restUrl;

	function init() {
		const wrap = document.querySelector( '[data-bol-preferences]' );
		if ( ! wrap ) return;

		const sid       = parseInt( wrap.dataset.sid, 10 );
		const token     = wrap.dataset.token;
		const allCheck  = wrap.querySelector( '[data-bol-all]' );
		const listChecks = wrap.querySelectorAll( '[data-bol-list]' );
		const saveBtn   = wrap.querySelector( '[data-bol-save]' );
		const unsubBtn  = wrap.querySelector( '[data-bol-unsub-all]' );
		const feedback  = wrap.querySelector( '.bol-preferences-feedback' );

		// Estado inicial del "Suscribir a todo".
		updateAllCheck();

		listChecks.forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				updateAllCheck();
				updateLabel( cb );
			} );
		} );

		if ( allCheck ) {
			allCheck.addEventListener( 'change', function () {
				const checked = allCheck.checked;
				listChecks.forEach( function ( cb ) {
					cb.checked = checked;
					updateLabel( cb );
				} );
			} );
		}

		saveBtn.addEventListener( 'click', function () {
			const lists = [];
			listChecks.forEach( function ( cb ) { if ( cb.checked ) lists.push( parseInt( cb.value, 10 ) ); } );
			submit( { sid: sid, token: token, lists: lists } );
		} );

		if ( unsubBtn ) {
			unsubBtn.addEventListener( 'click', function () {
				if ( ! confirm( '¿Seguro que quieres darte de baja de todos los boletines?' ) ) return;
				submit( { sid: sid, token: token, unsubscribe_all: 1 } );
			} );
		}

		function updateAllCheck() {
			if ( ! allCheck || ! listChecks.length ) return;
			let allChecked = true;
			listChecks.forEach( function ( cb ) { if ( ! cb.checked ) allChecked = false; } );
			allCheck.checked = allChecked;
		}

		function updateLabel( cb ) {
			const card  = cb.closest( '.bol-preferences-card' );
			if ( ! card ) return;
			const label = card.querySelector( '.bol-preferences-card-action label' );
			if ( label ) label.textContent = cb.checked ? 'Suscrito' : 'Suscribirme';
		}

		function submit( payload ) {
			setLoading( true );
			hideFeedback();
			fetch( REST_URL, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
				body: JSON.stringify( payload )
			} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, body: j }; } ); } )
				.then( function ( res ) {
					setLoading( false );
					if ( res.body && res.body.success ) {
						showFeedback( 'success', res.body.message );
						if ( res.body.unsubscribed ) {
							listChecks.forEach( function ( cb ) { cb.checked = false; updateLabel( cb ); } );
							if ( allCheck ) allCheck.checked = false;
						}
					} else {
						showFeedback( 'error', ( res.body && res.body.message ) || 'No se pudo guardar.' );
					}
				} )
				.catch( function () {
					setLoading( false );
					showFeedback( 'error', 'No se pudo conectar.' );
				} );
		}

		function setLoading( on ) {
			if ( on ) {
				saveBtn.classList.add( 'is-loading' );
				saveBtn.setAttribute( 'disabled', 'disabled' );
			} else {
				saveBtn.classList.remove( 'is-loading' );
				saveBtn.removeAttribute( 'disabled' );
			}
		}
		function showFeedback( tone, msg ) {
			feedback.className = 'bol-preferences-feedback is-' + tone;
			feedback.textContent = msg;
			feedback.hidden = false;
			feedback.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
		function hideFeedback() {
			feedback.hidden = true;
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
