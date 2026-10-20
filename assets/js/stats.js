/**
 * Stats — counts the figures up when the cards scroll into view (markup:
 * Stats_Cards::render()).
 *
 * Only for a row that is out of view when the script starts: one already on
 * screen keeps its numbers (no visitor watches 106 turn into 0), and so does
 * everything with reduced motion. Each figure's final width is held while it
 * counts; the "+" (or suffix) pops in as the number lands.
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
	const ease = ( k ) => ( k >= 1 ? 1 : 1 - Math.pow( 2, -10 * k ) ); // fast, then settling: like a counter

	function format( n, dec, sep ) {
		const [ i, f ] = Math.abs( n ).toFixed( dec ).split( '.' );
		return ( n < 0 ? '-' : '' ) + i.replace( /\B(?=(\d{3})+(?!\d))/g, sep ) + ( f ? '.' + f : '' );
	}

	function mount( root ) {
		if ( ! root || done.has( root ) ) {
			return;
		}
		done.add( root );

		// the dark card's light follows the pointer
		root.querySelectorAll( '.sz-stat.is-hi' ).forEach( ( c ) => c.addEventListener( 'pointermove', ( e ) => {
			const r = c.getBoundingClientRect();
			c.style.setProperty( '--mx', ( e.clientX - r.left ) + 'px' );
			c.style.setProperty( '--my', ( e.clientY - r.top ) + 'px' );
		} ) );

		const reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		const edit = !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
		if ( root.dataset.count !== '1' || reduce || edit || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		const r = root.getBoundingClientRect();
		if ( r.top < window.innerHeight && r.bottom > 0 ) {
			return; // on screen already: keep the numbers
		}

		const figures = [ ...root.querySelectorAll( '.sz-stat' ) ].map( ( card ) => ( {
			d: card.querySelector( '.sz-stat__d' ),
			mark: card.querySelector( '.sz-stat__plus, .sz-stat__suf' ),
		} ) ).filter( ( f ) => f.d );
		const sep = root.dataset.sep || '';

		// hold each figure's final width, then start from zero
		figures.forEach( ( f ) => {
			f.d.style.minWidth = f.d.getBoundingClientRect().width + 'px';
			f.to = parseFloat( f.d.dataset.to ) || 0;
			f.dec = parseInt( f.d.dataset.dec, 10 ) || 0;
			szwText( f.d, format( 0, f.dec, sep ) );
			if ( f.mark ) {
				f.mark.style.opacity = '0';
			}
		} );

		const D = parseInt( root.dataset.duration, 10 ) || 1400, step = parseInt( root.dataset.stagger, 10 ) || 0;
		const run = () => figures.forEach( ( f, i ) => setTimeout( () => {
			const t0 = performance.now();
			const tick = ( t ) => {
				const k = Math.min( 1, ( t - t0 ) / D );
				szwText( f.d, format( f.to * ease( k ), f.dec, sep ) );
				if ( k < 1 ) {
					requestAnimationFrame( tick );
				} else if ( f.mark ) {
					f.mark.style.opacity = '';
					f.mark.classList.add( 'is-pop' );
				}
			};
			requestAnimationFrame( tick );
		}, i * step ) );

		const io = new IntersectionObserver( ( es ) => {
			if ( es.some( ( e ) => e.intersectionRatio >= 0.6 || ( e.isIntersecting && e.boundingClientRect.height > window.innerHeight * 0.6 ) ) ) {
				io.disconnect();
				run();
			}
		}, { threshold: [ 0, 0.6 ] } );
		io.observe( root );
	}

	function mountAll( scope ) {
		( scope || document ).querySelectorAll( '[data-sz-stats]' ).forEach( mount );
	}

	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-stats.default', ( $el ) => mountAll( $el[ 0 ] ) );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => mountAll() );
	} else {
		mountAll();
	}
}() );
