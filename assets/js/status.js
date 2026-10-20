/**
 * Status — keeps a .sz-status pill (Status_Pill::render()) true over time:
 * - data-until: from that moment the pill reads available, even on a page
 *   cached before the date passed;
 * - data-tz: the local time there, updated on each new minute.
 * Pills with neither never load this file.
 */
( function () {
	'use strict';
	// text written into the existing text node: Elementor re-measures the page (a forced layout)
	// on every childList change anywhere, and replacing a text node is one; changing its data is not
	const szwText = ( el, s ) => {
		s = String( s );
		const t = el.firstChild;
		if ( t && t.nodeType === 3 && ! t.nextSibling ) {
			if ( t.data !== s ) {
				t.data = s;
			}
		} else if ( el.textContent !== s ) {
			el.textContent = s;
		}
	};

	const timers = new WeakMap();

	function expire( pill ) {
		const until = +pill.dataset.until;
		if ( ! until ) {
			return;
		}
		const flip = () => {
			pill.classList.remove( 'is-limited', 'is-booked' );
			pill.classList.add( 'is-available' );
			const text = pill.querySelector( '.sz-status__text' );
			if ( text && pill.dataset.available ) {
				szwText( text, pill.dataset.available );
			}
			delete pill.dataset.until;
		};
		const left = until - Date.now();
		if ( left <= 0 ) {
			flip();
		} else if ( left < 864e5 ) {
			setTimeout( flip, left ); // a page left open across the moment
		}
	}

	function clock( pill ) {
		const out = pill.querySelector( '.sz-status__time' );
		if ( ! out || ! pill.dataset.tz ) {
			return;
		}
		let fmt;
		try {
			fmt = new Intl.DateTimeFormat( 'en-GB', { hour: pill.dataset.h12 === '1' ? 'numeric' : '2-digit', minute: '2-digit', hour12: pill.dataset.h12 === '1', timeZone: pill.dataset.tz } );
		} catch ( e ) {
			return;
		}
		const tick = () => {
			const now = new Date(), text = fmt.format( now ).replace( /\s?(am|pm)$/i, ( m ) => ' ' + m.trim().toUpperCase() );
			if ( out.textContent !== text ) {
				szwText( out, text );
			}
			out.dateTime = now.toISOString();
			timers.set( pill, setTimeout( tick, 60000 - ( now.getSeconds() * 1000 + now.getMilliseconds() ) + 50 ) );
		};
		tick();
	}

	function mount( pill ) {
		if ( ! pill || pill.dataset.szReady ) {
			return;
		}
		pill.dataset.szReady = '1';
		clearTimeout( timers.get( pill ) );
		expire( pill );
		clock( pill );
	}

	function mountAll( scope ) {
		( scope || document ).querySelectorAll( '[data-sz-status]' ).forEach( mount );
	}

	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-status.default', ( $el ) => mountAll( $el[ 0 ] ) );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => mountAll() );
	} else {
		mountAll();
	}
}() );
