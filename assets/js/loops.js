/**
 * Shared by every widget: no animation runs where no one can see it, and a
 * decorative loop rests between its beats.
 *
 * - Off screen: each widget root (.sz-w) gets .sz-asleep while it is out of
 *   view, which pauses every animation inside it (sz-base.css). A running
 *   animation, even an invisible or composited one, makes the browser
 *   restyle the page every frame; paused, it costs nothing.
 * - Beats: an element with data-sz-beat="ms" (and data-sz-beat-delay) gets
 *   .is-beat for one play of its animation, every ms, only while on screen
 *   and the tab is visible. Between beats nothing is running at all, which
 *   an "infinite" CSS loop with a long still part cannot do.
 */
( function () {
	'use strict';
	if ( window.szwLoops ) {
		return;
	}
	const calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const seen = new WeakSet();
	const visible = new WeakSet();
	const io = 'IntersectionObserver' in window
		? new IntersectionObserver( ( es ) => es.forEach( ( e ) => {
			e.target.classList.toggle( 'sz-asleep', ! e.isIntersecting );
			if ( e.isIntersecting ) {
				visible.add( e.target );
			} else {
				visible.delete( e.target );
			}
		} ), { rootMargin: '120px 0px' } )
		: null;

	function beat( el ) {
		const every = Math.max( 1500, +el.dataset.szBeat || 8000 );
		const root = el.closest( '.sz-w' ) || el;
		const play = () => {
			if ( document.hidden || ( io && ! visible.has( root ) && ! root.matches( ':hover' ) ) ) {
				return;
			}
			el.classList.remove( 'is-beat' );
			void el.offsetWidth;
			el.classList.add( 'is-beat' );
		};
		// the beat ends when its animations do
		el.addEventListener( 'animationend', () => el.getAnimations( { subtree: true } ).length || el.classList.remove( 'is-beat' ) );
		setTimeout( () => {
			play();
			setInterval( play, every );
		}, +el.dataset.szBeatDelay || 0 );
	}

	function scan( ctx ) {
		( ctx || document ).querySelectorAll( '.sz-w' ).forEach( ( r ) => {
			if ( io && ! seen.has( r ) ) {
				seen.add( r );
				io.observe( r );
			}
		} );
		if ( calm ) {
			return;
		}
		( ctx || document ).querySelectorAll( '[data-sz-beat]' ).forEach( ( el ) => {
			if ( ! seen.has( el ) ) {
				seen.add( el );
				beat( el );
			}
		} );
	}
	window.szwLoops = { scan };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => scan() );
	} else {
		scan();
	}
	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', ( $el ) => scan( $el[ 0 ] ) );
	} );
}() );
