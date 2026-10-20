/**
 * Header (markup: Brand_Mark::render(), Nav_Menu::render()).
 *
 * - The pill that glides to the link under the pointer.
 * - The link whose #section is on screen.
 * - Phones: the menu button opens the sheet; Escape, a tap outside or a link closes it.
 * - The glass bar (the container holding a menu with "Glass bar"): a little
 *   more glass once the page has scrolled, out of the way while scrolling
 *   down, back on the first scroll up.
 * - A link to #top scrolls to the top (there may be no #top element); the
 *   brand's tile turns once on the way.
 * - One indicator: the pill rests on the section's link and glides away to
 *   follow the pointer, then back.
 * - The bar shows how far down the page you are (a line moved by transform).
 * - Phones: a scrim behind the open menu; a tap on it closes the menu.
 */
( function () {
	'use strict';
	const done = new WeakSet();
	const calm = () => window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const edit = () => !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );

	function glide( nav ) {
		const pill = nav.querySelector( '.sz-nav__glide' );
		let on = null;
		nav.classList.add( 'has-glide' );
		const place = ( a ) => {
			pill.style.width = a.offsetWidth + 'px';
			pill.style.transform = `translateX(${ a.offsetLeft }px)`;
		};
		// at rest, on the link of the section on screen
		nav.szRest = () => {
			const here = nav.querySelector( '.sz-nav__a.is-here' );
			if ( on ) {
				return;
			}
			nav.classList.toggle( 'is-resting', !! here );
			if ( here ) {
				place( here );
			}
		};
		const to = ( a ) => {
			if ( window.innerWidth < 768 && nav.classList.contains( 'is-phone-menu' ) ) {
				return;
			}
			const first = ! nav.classList.contains( 'is-gliding' ) && ! nav.classList.contains( 'is-resting' );
			nav.classList.toggle( 'is-instant', first );
			place( a );
			if ( first ) {
				void pill.offsetWidth;
			}
			nav.classList.add( 'is-gliding' );
			on = a;
			requestAnimationFrame( () => nav.classList.remove( 'is-instant' ) );
		};
		nav.querySelectorAll( '.sz-nav__a' ).forEach( ( a ) => {
			a.addEventListener( 'pointerenter', ( e ) => e.pointerType === 'mouse' && to( a ) );
			a.addEventListener( 'focus', () => to( a ) );
		} );
		nav.querySelector( '.sz-nav__sheet' ).addEventListener( 'pointerleave', () => {
			nav.classList.remove( 'is-gliding' );
			on = null;
			nav.szRest();
		} );
		window.addEventListener( 'resize', () => nav.szRest() );
		nav.addEventListener( 'focusout', ( e ) => ! nav.contains( e.relatedTarget ) && ! on && nav.classList.remove( 'is-gliding' ) );
	}

	function spy( nav ) {
		const links = [ ...nav.querySelectorAll( 'a.sz-nav__a[href*="#"]' ) ].map( ( a ) => {
			const id = decodeURIComponent( a.getAttribute( 'href' ).split( '#' )[ 1 ] || '' );
			const t = id && a.href.split( '#' )[ 0 ] === location.href.split( '#' )[ 0 ] && document.getElementById( id );
			return t ? { a, t } : null;
		} ).filter( Boolean );
		if ( ! links.length || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		const seen = new Map();
		const io = new IntersectionObserver( ( es ) => {
			es.forEach( ( x ) => seen.set( x.target, x.intersectionRatio ) );
			let best = null, most = 0.15;
			links.forEach( ( l ) => {
				if ( ( seen.get( l.t ) || 0 ) > most ) {
					most = seen.get( l.t );
					best = l.t;
				}
			} );
			links.forEach( ( l ) => {
				l.a.classList.toggle( 'is-here', l.t === best );
				nav.szRest && requestAnimationFrame( nav.szRest );
				l.t === best ? l.a.setAttribute( 'aria-current', 'location' ) : l.a.removeAttribute( 'aria-current' );
			} );
		}, { threshold: [ 0, 0.15, 0.3, 0.5, 0.75, 1 ] } );
		new Set( links.map( ( l ) => l.t ) ).forEach( ( t ) => io.observe( t ) );
	}

	function sheet( nav ) {
		const btn = nav.querySelector( '.sz-nav__menu' );
		if ( ! btn ) {
			return;
		}
		const scrim = document.createElement( 'div' );
		scrim.className = 'sz-nav-scrim';
		document.body.appendChild( scrim );
		const set = ( open ) => {
			nav.classList.toggle( 'is-open', open );
			scrim.classList.toggle( 'is-on', open );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		};
		btn.addEventListener( 'click', () => set( ! nav.classList.contains( 'is-open' ) ) );
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
				set( false );
				btn.focus();
			}
		} );
		document.addEventListener( 'pointerdown', ( e ) => ! nav.contains( e.target ) && set( false ) );
		nav.querySelectorAll( '.sz-nav__a, .sz-nav__cta' ).forEach( ( a ) => a.addEventListener( 'click', () => set( false ) ) );
	}

	function bar( nav ) {
		const wd = nav.closest( '.elementor-widget-sz-nav' );
		if ( ! wd || ! wd.classList.contains( 'sz-nav-glass-yes' ) ) {
			return;
		}
		const host = wd.parentElement.closest( '.e-con, .elementor-section' );
		if ( ! host || edit() ) {
			return;
		}
		host.classList.add( 'sz-navbar' );
		const line = document.createElement( 'span' );
		line.className = 'sz-nav-progress';
		line.setAttribute( 'aria-hidden', 'true' );
		host.appendChild( line );
		let last = window.scrollY, raf = 0, max = 1;
		const measure = () => ( max = Math.max( 1, document.documentElement.scrollHeight - window.innerHeight ) );
		measure();
		window.addEventListener( 'resize', measure, { passive: true } );
		window.addEventListener( 'load', measure );
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( measure ).observe( document.body );
		}
		const hide = nav.hasAttribute( 'data-hide' );
		const tick = () => {
			raf = 0;
			const y = window.scrollY;
			if ( ( y > 8 ) !== host.classList.contains( 'is-scrolled' ) ) {
				host.classList.toggle( 'is-scrolled', y > 8 );
			}
			// transform only: no restyle of the bar on every frame
			line.style.transform = `scaleX(${ Math.min( 1, y / max ).toFixed( 4 ) })`;
			if ( hide && ! nav.classList.contains( 'is-open' ) ) {
				if ( y > last + 4 && y > 120 ) {
					host.classList.add( 'is-away' );
				} else if ( y < last - 4 || y < 120 ) {
					host.classList.remove( 'is-away' );
				}
			}
			last = y;
		};
		window.addEventListener( 'scroll', () => ( raf = raf || requestAnimationFrame( tick ) ), { passive: true } );
		tick();
	}

	function mount( el ) {
		if ( ! el || done.has( el ) ) {
			return;
		}
		done.add( el );
		if ( el.matches( '[data-sz-nav]' ) ) {
			glide( el );
			spy( el );
			sheet( el );
			bar( el );
		}
	}

	// #top: to the top, smoothly, whether or not an element has that id
	document.addEventListener( 'click', ( e ) => {
		const a = e.target.closest && e.target.closest( 'a.sz-brand[href="#top"], a.sz-nav__a[href="#top"]' );
		if ( a && a.classList.contains( 'sz-brand' ) && ! calm() ) {
			a.classList.remove( 'is-spin' );
			void a.offsetWidth;
			a.classList.add( 'is-spin' );
		}
		if ( a && ! document.getElementById( 'top' ) ) {
			e.preventDefault();
			window.scrollTo( { top: 0, behavior: calm() ? 'auto' : 'smooth' } );
		}
	} );

	const all = () => document.querySelectorAll( '[data-sz-nav]' ).forEach( mount );
	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-nav.default', ( $el ) => mount( $el[ 0 ].querySelector( '[data-sz-nav]' ) ) );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
