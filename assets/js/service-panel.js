/**
 * Service Panel — plays a panel's videos only while someone can see them:
 * on screen, in an open tab, page in the foreground. Everything else stays
 * paused, so four tabs of video cost what one visible tab costs.
 *
 * Videos are marked data-sz-autoplay (never autoplay): the browser does not
 * start them on its own, and with prefers-reduced-motion they stay paused on
 * their first frame.
 */
( function () {
	'use strict';

	const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const seen = new WeakSet();
	const onScreen = new WeakMap();

	const io = 'IntersectionObserver' in window
		? new IntersectionObserver( ( entries ) => entries.forEach( ( e ) => {
			onScreen.set( e.target, e.isIntersecting );
			update( e.target );
		} ), { threshold: 0.15 } )
		: null;

	function visible( video ) {
		const panel = video.closest( '.sz-tabs__panel' );
		return ( ! panel || panel.classList.contains( 'is-active' ) )
			&& ( io ? onScreen.get( video ) === true : true )
			&& document.visibilityState === 'visible';
	}

	function update( video ) {
		if ( ! reduce.matches && visible( video ) ) {
			const p = video.play();
			if ( p && p.catch ) {
				p.catch( () => {} );
			}
		} else if ( ! video.paused ) {
			video.pause();
		}
	}

	function scan( scope ) {
		( scope || document ).querySelectorAll( '.sz-svc video[data-sz-autoplay]' ).forEach( ( v ) => {
			if ( seen.has( v ) ) {
				return;
			}
			seen.add( v );
			v.muted = true; // required for autoplay on every browser, set as a property too
			if ( io ) {
				io.observe( v );
			}
			update( v );
		} );
	}

	const all = () => document.querySelectorAll( '.sz-svc video[data-sz-autoplay]' ).forEach( update );

	document.addEventListener( 'sz:change', all );
	document.addEventListener( 'visibilitychange', all );
	if ( reduce.addEventListener ) {
		reduce.addEventListener( 'change', all );
	}

	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-service-panel.default', ( $el ) => scan( $el[ 0 ] ) );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => scan() );
	} else {
		scan();
	}

	window.szwPanels = { scan };
}() );
