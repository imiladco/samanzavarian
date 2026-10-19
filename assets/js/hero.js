/**
 * Hero widgets (markup: Hero_Floats::render(), Dock::render()).
 *
 * - Floats: each card drifts against the pointer at its own depth, over the
 *   whole container the floats live in (not only over the cards, which let
 *   the pointer through). Mouse only; nothing runs at rest.
 * - Dock: the Mac dock's swell. Each icon grows with the pointer's nearness
 *   (the nearest most, its neighbours less), worked out once a frame while
 *   the pointer is over the dock.
 */
( function () {
	'use strict';
	const done = new WeakSet();
	const calm = () => window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const fine = () => window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

	function floats( el ) {
		const area = el.closest( '.e-con, .elementor-section, section' ) || el;
		const cards = [ ...el.querySelectorAll( '.sz-float' ) ];
		let raf = 0, last = null;
		const apply = () => {
			raf = 0;
			const r = area.getBoundingClientRect();
			const x = last ? ( last.clientX - r.left ) / r.width - 0.5 : 0;
			const y = last ? ( last.clientY - r.top ) / r.height - 0.5 : 0;
			cards.forEach( ( c ) => {
				const d = +c.dataset.depth || 0;
				c.style.setProperty( '--px', ( -x * d ).toFixed( 1 ) + 'px' );
				c.style.setProperty( '--py', ( -y * d ).toFixed( 1 ) + 'px' );
			} );
		};
		area.addEventListener( 'pointermove', ( e ) => {
			if ( e.pointerType !== 'mouse' || calm() ) {
				return;
			}
			last = e;
			raf = raf || requestAnimationFrame( apply );
		} );
		area.addEventListener( 'pointerleave', () => {
			last = null;
			raf = raf || requestAnimationFrame( apply );
		} );
	}

	function dock( el ) {
		const apps = [ ...el.querySelectorAll( '.sz-dock__app' ) ];
		let raf = 0, x = 0;
		const RANGE = 130, GROW = 0.42;
		const apply = () => {
			raf = 0;
			apps.forEach( ( a ) => {
				const r = a.getBoundingClientRect();
				const d = Math.abs( x - ( r.left + r.width / 2 ) );
				const t = Math.max( 0, 1 - d / RANGE );
				// eased, so the swell is round rather than a tent
				a.style.setProperty( '--s', ( 1 + GROW * ( 1 - Math.cos( t * Math.PI ) ) / 2 ).toFixed( 3 ) );
			} );
		};
		el.addEventListener( 'pointermove', ( e ) => {
			if ( e.pointerType !== 'mouse' || calm() || ! fine() || window.innerWidth < 768 ) {
				return;
			}
			x = e.clientX;
			el.classList.add( 'is-near' );
			raf = raf || requestAnimationFrame( apply );
		} );
		el.addEventListener( 'pointerleave', () => {
			cancelAnimationFrame( raf );
			raf = 0;
			apps.forEach( ( a ) => a.style.setProperty( '--s', 1 ) );
			setTimeout( () => el.classList.remove( 'is-near' ), 200 );
		} );
	}

	function mount( el ) {
		if ( ! el || done.has( el ) ) {
			return;
		}
		done.add( el );
		if ( el.hasAttribute( 'data-sz-floats' ) ) {
			floats( el );
		} else if ( el.hasAttribute( 'data-sz-dock' ) ) {
			dock( el );
		}
	}

	const all = () => document.querySelectorAll( '[data-sz-floats],[data-sz-dock]' ).forEach( mount );
	window.addEventListener( 'elementor/frontend/init', () => {
		[ 'sz-floats', 'sz-dock' ].forEach( ( w ) => window.elementorFrontend.hooks.addAction( `frontend/element_ready/${ w }.default`, ( $el ) => mount( $el[ 0 ].querySelector( '[data-sz-floats],[data-sz-dock]' ) ) ) );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
