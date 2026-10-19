/* Icon words (Inline_Icons): light the pills up in reading order the first time
   their paragraph scrolls into view. Only when it is out of view as the page
   opens, and never with reduced motion: otherwise they are simply there. */
( function () {
	'use strict';
	function arm() {
		if ( ! ( 'IntersectionObserver' in window ) || ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) ) {
			return;
		}
		var seen = [];
		document.querySelectorAll( '.sz-tok' ).forEach( function ( t ) {
			var box = t.closest( '.elementor-widget, [data-e-type="widget"]' ) || t.parentElement;
			if ( seen.indexOf( box ) < 0 ) {
				seen.push( box );
			}
		} );
		var io = new IntersectionObserver( function ( es ) {
			es.forEach( function ( e ) {
				if ( e.isIntersecting && e.intersectionRatio >= 0.5 ) {
					e.target.classList.add( 'is-sz-lit' );
					io.unobserve( e.target );
				}
			} );
		}, { threshold: [ 0, 0.5 ] } );
		seen.forEach( function ( box ) {
			var r = box.getBoundingClientRect();
			if ( r.top >= window.innerHeight || r.bottom <= 0 ) {
				box.classList.add( 'is-sz-armed' );
				io.observe( box );
			}
		} );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', arm );
	} else {
		arm();
	}
}() );
