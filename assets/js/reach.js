/**
 * Reach (markup: Reach::render()).
 * - "Online" on WhatsApp and Telegram during working hours in the owner's
 *   time zone, checked in the browser (cached pages stay right) and again
 *   every few minutes while the page is open.
 * - Copy: the button, and on a computer the whole email row (a mail app is
 *   often missing there); the button and the address line confirm.
 * - First view: the rows come in one after another, only if the list starts
 *   out of view (no flash on a list already on screen, nothing in the editor).
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
	const fine = () => window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

	function nowIn( tz ) {
		try {
			const p = new Intl.DateTimeFormat( 'en-US', { timeZone: tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' } ).formatToParts( new Date() );
			const get = ( t ) => ( p.find( ( x ) => x.type === t ) || {} ).value;
			return { day: [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ].indexOf( get( 'weekday' ) ), min: +get( 'hour' ) * 60 + +get( 'minute' ) };
		} catch ( e ) {
			return null;
		}
	}
	const mins = ( s ) => {
		const m = /^(\d{1,2}):?(\d{2})?$/.exec( String( s ).trim() );
		return m ? +m[ 1 ] * 60 + +( m[ 2 ] || 0 ) : null;
	};

	async function copy( text ) {
		try {
			await navigator.clipboard.writeText( text );
			return true;
		} catch ( e ) {
			const t = document.createElement( 'textarea' );
			t.value = text;
			t.setAttribute( 'readonly', '' );
			t.style.cssText = 'position:fixed;opacity:0';
			document.body.appendChild( t );
			t.select();
			let ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( x ) {}
			t.remove();
			return ok;
		}
	}

	/* The glide: one white card moves to the row under the pointer (or with
	   keyboard focus). Entering the list it appears in place; after that it
	   travels, so it reads as one object following you. */
	function glide( root ) {
		const card = root.querySelector( '.sz-reach__glide' );
		if ( ! card ) {
			return;
		}
		let hot = null;
		const to = ( row ) => {
			if ( row === hot ) {
				return;
			}
			if ( hot ) {
				hot.classList.remove( 'is-hot' );
			}
			const first = ! root.classList.contains( 'is-gliding' );
			root.classList.toggle( 'is-instant', first );
			card.style.transform = `translateY(${ row.offsetTop }px)`;
			card.style.height = row.offsetHeight + 'px';
			if ( first ) {
				void card.offsetWidth;
			}
			root.classList.add( 'is-gliding' );
			row.classList.add( 'is-hot' );
			hot = row;
			requestAnimationFrame( () => root.classList.remove( 'is-instant' ) );
		};
		const off = () => {
			root.classList.remove( 'is-gliding' );
			if ( hot ) {
				hot.classList.remove( 'is-hot' );
			}
			hot = null;
		};
		root.querySelectorAll( '.sz-reach__row' ).forEach( ( row ) => {
			row.addEventListener( 'pointerenter', ( e ) => e.pointerType === 'mouse' && to( row ) );
			row.addEventListener( 'focusin', () => to( row ) );
		} );
		root.addEventListener( 'pointerleave', ( e ) => e.pointerType === 'mouse' && ! root.contains( document.activeElement ) && off() );
		root.addEventListener( 'focusout', ( e ) => ! root.contains( e.relatedTarget ) && ! root.matches( ':hover' ) && off() );
	}

	function arrive( root ) {
		const edit = window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode();
		const calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( edit || calm || ! ( 'IntersectionObserver' in window ) || root.getBoundingClientRect().top < window.innerHeight ) {
			return;
		}
		root.querySelectorAll( '.sz-reach__row' ).forEach( ( r, i ) => r.style.setProperty( '--i', i ) );
		root.classList.add( 'is-armed' );
		const io = new IntersectionObserver( ( es ) => {
			if ( es.some( ( e ) => e.isIntersecting ) ) {
				io.disconnect();
				root.classList.add( 'is-in' );
				setTimeout( () => root.classList.remove( 'is-armed', 'is-in' ), 1200 );
			}
		}, { threshold: 0.2 } );
		io.observe( root );
	}

	function mount( root ) {
		if ( ! root || done.has( root ) ) {
			return;
		}
		done.add( root );
		arrive( root );
		glide( root );
		const h = JSON.parse( root.dataset.hours || '{}' ), on = [ ...root.querySelectorAll( '.sz-reach__on' ) ];
		const tick = () => {
			const n = nowIn( h.tz || 'Asia/Tehran' ), a = mins( h.from ), b = mins( h.to );
			const open = !! n && ( h.days || [] ).indexOf( n.day ) > -1 && a !== null && b !== null && n.min >= a && n.min < b;
			on.forEach( ( el ) => ( el.hidden = ! open ) );
		};
		if ( on.length ) {
			tick();
			setInterval( tick, 5 * 60 * 1000 );
		}

		const confirm = ( row, btn ) => {
			const sub = row.querySelector( '.sz-reach__sub' ), was = sub.textContent, bt = btn && btn.firstChild, bwas = bt && bt.textContent;
			row.classList.add( 'is-copied' );
			szwText( sub, '✓ ' + ( root.dataset.copied || 'Copied' ) );
			if ( btn ) {
				btn.classList.add( 'is-done' );
				szwText( bt, root.dataset.copied || 'Copied' );
			}
			window.szwTrack && window.szwTrack( 'sz_reach_copy', {} );
			setTimeout( () => {
				row.classList.remove( 'is-copied' );
				szwText( sub, was );
				if ( btn ) {
					btn.classList.remove( 'is-done' );
					szwText( bt, bwas );
				}
			}, 1800 );
		};
		root.querySelectorAll( '.sz-reach__copy' ).forEach( ( b ) => b.addEventListener( 'click', async () => ( await copy( b.dataset.copy ) ) && confirm( b.closest( '.sz-reach__row' ), b ) ) );
		root.querySelectorAll( '.sz-reach__a[data-copy]' ).forEach( ( a ) => a.addEventListener( 'click', async ( e ) => {
			if ( ! fine() ) {
				return;
			}
			e.preventDefault();
			const row = a.closest( '.sz-reach__row' );
			( await copy( a.dataset.copy ) ) && confirm( row, row.querySelector( '.sz-reach__copy' ) );
		} ) );
		root.querySelectorAll( '.sz-reach__a:not([data-copy])' ).forEach( ( a ) => a.addEventListener( 'click', () => window.szwTrack && window.szwTrack( 'sz_reach_open', { channel: a.closest( '.sz-reach__row' ).dataset.type } ) ) );
	}

	const all = () => document.querySelectorAll( '[data-sz-rc]' ).forEach( mount );
	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-reach.default', ( $el ) => mount( $el[ 0 ].querySelector( '[data-sz-rc]' ) ) );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
