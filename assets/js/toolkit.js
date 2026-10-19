/* Toolkit (markup: Toolkit::render()): the rows rise in the first time the
   card scrolls into view. Only when it is out of view as the page opens,
   never with reduced motion or in the editor: otherwise it is simply there. */
( function () {
	'use strict';
	function arm( root ) {
		if ( root.dataset.szArmed || root.dataset.arrive !== '1' ) {
			return;
		}
		root.dataset.szArmed = '1';
		const editor = window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode();
		if ( editor || ! ( 'IntersectionObserver' in window ) || ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) ) {
			return;
		}
		const r = root.getBoundingClientRect();
		if ( r.top < window.innerHeight && r.bottom > 0 ) {
			return;
		}
		root.classList.add( 'is-armed' );
		const io = new IntersectionObserver( ( es ) => {
			if ( es.some( ( e ) => e.isIntersecting ) ) {
				io.disconnect();
				root.classList.add( 'is-lit' );
				// once played, the card goes back to plain (no transitions left behind)
				setTimeout( () => root.classList.remove( 'is-armed', 'is-lit' ), 2400 );
			}
		}, { threshold: 0.25 } );
		io.observe( root );
	}
	const all = () => document.querySelectorAll( '[data-sz-tk]' ).forEach( arm );
	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-toolkit.default', ( $el ) => {
			const root = $el[ 0 ].querySelector( '[data-sz-tk]' );
			root && arm( root );
		} );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
