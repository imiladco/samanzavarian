/**
 * Reply Promise (markup: Reply_Promise::render()).
 * - The day line is counted again here, in the owner's time zone, so a
 *   cached page names the right day; checked again every few minutes while
 *   the page is open.
 * - The clock sweeps once when the promise comes into view, and under the
 *   pointer; at rest nothing moves.
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
	const done = new WeakSet();
	const calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/** `n` working days after today in `tz`, skipping `off` (0 = Sunday); same count as form.js. */
	function replyDay( n, off, tz ) {
		let p;
		try {
			p = new Intl.DateTimeFormat( 'en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' } ).format( new Date() ).split( '-' ).map( Number );
		} catch ( e ) {
			const t = new Date();
			p = [ t.getFullYear(), t.getMonth() + 1, t.getDate() ];
		}
		const d = new Date( Date.UTC( p[ 0 ], p[ 1 ] - 1, p[ 2 ], 12 ) );
		const skip = new Set( off ).size >= 7 ? [] : off;
		let left = n;
		while ( left > 0 ) {
			d.setUTCDate( d.getUTCDate() + 1 );
			if ( skip.indexOf( d.getUTCDay() ) < 0 ) {
				left--;
			}
		}
		const f = ( o ) => new Intl.DateTimeFormat( 'en-GB', Object.assign( { timeZone: 'UTC' }, o ) ).format( d );
		return f( { weekday: 'long' } ) + ', ' + f( { day: 'numeric' } ) + ' ' + f( { month: 'long' } );
	}

	function sweep( el ) {
		if ( calm ) {
			return;
		}
		el.classList.remove( 'is-sweep' );
		void el.offsetWidth;
		el.classList.add( 'is-sweep' );
	}

	function mount( el ) {
		if ( ! el || done.has( el ) ) {
			return;
		}
		done.add( el );
		const cfg = JSON.parse( el.dataset.config || '{}' );
		const line = el.querySelector( '.sz-promise__day' );
		const update = () => {
			if ( ! line ) {
				return;
			}
			const b = line.querySelector( 'b' );
			const day = replyDay( cfg.days || 2, cfg.off || [], cfg.tz || 'Asia/Tehran' );
			if ( b && b.textContent !== day ) {
				szwText( b, day );
				line.classList.remove( 'is-swap' );
				void line.offsetWidth;
				line.classList.add( 'is-swap' );
			}
		};
		update();
		setInterval( update, 5 * 60 * 1000 );

		if ( 'IntersectionObserver' in window ) {
			const io = new IntersectionObserver( ( es ) => {
				if ( es.some( ( e ) => e.isIntersecting ) ) {
					io.disconnect();
					setTimeout( () => sweep( el ), 150 );
				}
			}, { threshold: 0.6 } );
			io.observe( el );
		}
		el.addEventListener( 'pointerenter', ( e ) => e.pointerType === 'mouse' && sweep( el ) );
	}

	const all = () => document.querySelectorAll( '[data-sz-promise]' ).forEach( mount );
	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-promise.default', ( $el ) => mount( $el[ 0 ].querySelector( '[data-sz-promise]' ) ) );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
