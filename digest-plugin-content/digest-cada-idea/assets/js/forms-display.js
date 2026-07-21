/**
 * Boletines — Forms 2.0 frontend triggers (vanilla JS).
 * Lee la config desde window.BoletinesFormsData (vía wp_localize_script).
 */
(function () {
	'use strict';

	const forms = window.BoletinesFormsData;
	if ( ! Array.isArray( forms ) || forms.length === 0 ) return;

	const COOKIE_PREFIX = 'bol_form_';

	function getCookie( name ) {
		const m = document.cookie.match( new RegExp( '(?:^|; )' + name.replace( /([.*+?^${}()|[\]\\])/g, '\\$1' ) + '=([^;]*)' ) );
		return m ? decodeURIComponent( m[1] ) : '';
	}
	function setCookie( name, value, days ) {
		const exp = new Date( Date.now() + days * 86400000 ).toUTCString();
		document.cookie = name + '=' + encodeURIComponent( value ) + '; expires=' + exp + '; path=/; SameSite=Lax';
	}

	function recordShown( formId, freqDays ) {
		const key = COOKIE_PREFIX + formId;
		const prev = parseInt( getCookie( key + '_count' ) || '0', 10 );
		const ttl = Math.max( 1, freqDays );
		setCookie( key + '_count', String( prev + 1 ), ttl );
		setCookie( key + '_lastshown', String( Date.now() ), ttl );
	}
	function alreadyConverted( formId ) {
		return getCookie( COOKIE_PREFIX + formId + '_done' ) === '1';
	}
	function recordConverted( formId ) {
		setCookie( COOKIE_PREFIX + formId + '_done', '1', 365 );
	}
	function exceededShows( formId, maxShows ) {
		if ( ! maxShows || maxShows <= 0 ) return false;
		return parseInt( getCookie( COOKIE_PREFIX + formId + '_count' ) || '0', 10 ) >= maxShows;
	}
	function recentlyShown( formId, freqDays ) {
		if ( freqDays <= 0 ) return false;
		const last = parseInt( getCookie( COOKIE_PREFIX + formId + '_lastshown' ) || '0', 10 );
		if ( ! last ) return false;
		return ( Date.now() - last ) < ( freqDays * 86400000 );
	}

	function findElement( type, formId ) {
		const sel = ( type === 'bar_top' || type === 'bar_bottom' )
			? '[data-bol-bar][data-form-id="' + formId + '"]'
			: ( type === 'slide_in' )
				? '[data-bol-slidein][data-form-id="' + formId + '"]'
				: '[data-bol-overlay][data-form-id="' + formId + '"]';
		return document.querySelector( sel );
	}

	function showElement( el ) {
		if ( ! el ) return;
		requestAnimationFrame( function () { el.classList.add( 'is-visible' ); } );
	}
	function hideElement( el ) {
		if ( el ) el.classList.remove( 'is-visible' );
	}

	function setupClose( el ) {
		const closeBtn = el.querySelector( '[data-bol-close]' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () { hideElement( el ); } );
		}
		// Click fuera (sólo overlays).
		if ( el.matches( '[data-bol-overlay]' ) ) {
			el.addEventListener( 'click', function ( e ) {
				if ( e.target === el ) hideElement( el );
			} );
		}
	}

	function setupConversionListener( el, formId ) {
		const wrap = el.querySelector( '.bol-form-wrap' );
		if ( ! wrap ) return;
		const obs = new MutationObserver( function () {
			if ( wrap.classList.contains( 'is-done' ) ) {
				recordConverted( formId );
				obs.disconnect();
				setTimeout( function () { hideElement( el ); }, 4000 );
			}
		} );
		obs.observe( wrap, { attributes: true, attributeFilter: [ 'class' ] } );
	}

	function tryShow( config ) {
		if ( alreadyConverted( config.id ) ) return;
		if ( exceededShows( config.id, config.max_shows ) ) return;
		if ( recentlyShown( config.id, config.frequency_days ) ) return;

		const el = findElement( config.type, config.id );
		if ( ! el ) return;

		setupClose( el );
		setupConversionListener( el, config.id );
		showElement( el );
		recordShown( config.id, config.frequency_days );
	}

	function setupForm( config ) {
		switch ( config.type ) {
			case 'popup':
			case 'slide_in':
				if ( config.scroll_percent > 0 ) {
					setupScrollTrigger( config );
				} else {
					setTimeout( function () { tryShow( config ); }, ( config.trigger_seconds || 5 ) * 1000 );
				}
				break;
			case 'exit_intent':
				setupExitIntent( config );
				break;
			case 'bar_top':
			case 'bar_bottom':
				setTimeout( function () { tryShow( config ); }, 800 );
				break;
		}
	}

	function setupScrollTrigger( config ) {
		let triggered = false;
		function check() {
			if ( triggered ) return;
			const h = document.documentElement.scrollHeight - window.innerHeight;
			const pct = h > 0 ? ( window.scrollY / h ) * 100 : 0;
			if ( pct >= config.scroll_percent ) {
				triggered = true;
				tryShow( config );
				window.removeEventListener( 'scroll', check );
			}
		}
		window.addEventListener( 'scroll', check, { passive: true } );
	}

	function setupExitIntent( config ) {
		let triggered = false;
		function check( e ) {
			if ( triggered ) return;
			if ( e.clientY <= 0 ) {
				triggered = true;
				tryShow( config );
				document.removeEventListener( 'mouseleave', check );
			}
		}
		setTimeout( function () { document.addEventListener( 'mouseleave', check ); }, 3000 );
	}

	function init() {
		forms.forEach( setupForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
