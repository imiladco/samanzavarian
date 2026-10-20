/**
 * Hero widgets (markup: Hero_Floats::render(), Dock::render()).
 *
 * - Floats: each card drifts against the pointer at its own depth, over the
 *   whole container the floats live in (not only over the cards, which let
 *   the pointer through). Mouse only; nothing runs at rest.
 * - Floats also pause their gentle float while the hero is off screen.
 * - Magnets ([data-sz-magnet="px"]: the title's tile, buttons) lean toward a
 *   pointer that comes near, up to that many pixels.
 * - Dock: the item whose #section is on screen gets its dot.
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
		if ( el.classList.contains( 'has-bob' ) && 'IntersectionObserver' in window ) {
			new IntersectionObserver( ( es ) => es.forEach( ( x ) => el.classList.toggle( 'is-away', ! x.isIntersecting ) ) ).observe( el );
		}
		if ( ! el.hasAttribute( 'data-drift' ) ) {
			return;
		}
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

	function spy( el ) {
		const links = [ ...el.querySelectorAll( 'a.sz-dock__a[href*="#"]' ) ].map( ( a ) => {
			const id = decodeURIComponent( ( a.getAttribute( 'href' ).split( '#' )[ 1 ] || '' ) );
			const url = a.href.split( '#' )[ 0 ];
			const target = id && url === location.href.split( '#' )[ 0 ] && document.getElementById( id );
			return target ? { a, target } : null;
		} ).filter( Boolean );
		if ( ! links.length || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		const seen = new Map();
		const pick = () => {
			let best = null, most = 0;
			links.forEach( ( l ) => {
				const v = seen.get( l.target ) || 0;
				if ( v > most ) {
					most = v;
					best = l.target;
				}
			} );
			// several items may point at one section: they all get the dot
			links.forEach( ( l ) => l.a.classList.toggle( 'is-here', !! best && l.target === best && most > 0.15 ) );
		};
		const io = new IntersectionObserver( ( es ) => {
			es.forEach( ( x ) => seen.set( x.target, x.intersectionRatio ) );
			pick();
		}, { threshold: [ 0, 0.15, 0.3, 0.5, 0.75, 1 ] } );
		new Set( links.map( ( l ) => l.target ) ).forEach( ( t ) => io.observe( t ) );
	}

	/* magnets: one listener for all, a frame at a time, only while the pointer moves */
	const magnets = new Set();
	let mraf = 0, mx = 0, my = 0;
	const pull = () => {
		mraf = 0;
		magnets.forEach( ( m ) => {
			if ( ! m.isConnected ) {
				magnets.delete( m );
				return;
			}
			const r = m.getBoundingClientRect(), max = +m.dataset.szMagnet || 8;
			const dx = mx - ( r.left + r.width / 2 ), dy = my - ( r.top + r.height / 2 );
			const reach = Math.max( r.width, r.height ) / 2 + 70, d = Math.hypot( dx, dy );
			const k = d < reach ? ( 1 - d / reach ) : 0;
			m.style.setProperty( '--mx', ( dx / reach * max * k * 2 ).toFixed( 1 ) + 'px' );
			m.style.setProperty( '--my', ( dy / reach * max * k * 2 ).toFixed( 1 ) + 'px' );
		} );
	};
	window.addEventListener( 'pointermove', ( e ) => {
		if ( ! magnets.size || e.pointerType !== 'mouse' || calm() ) {
			return;
		}
		mx = e.clientX;
		my = e.clientY;
		mraf = mraf || requestAnimationFrame( pull );
	}, { passive: true } );

	function dock( el ) {
		if ( el.hasAttribute( 'data-spy' ) ) {
			spy( el );
		}
		if ( ! el.hasAttribute( 'data-swell' ) ) {
			return;
		}
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
		if ( el.hasAttribute( 'data-sz-magnet' ) ) {
			magnets.add( el );
		} else if ( el.hasAttribute( 'data-sz-floats' ) ) {
			floats( el );
		} else if ( el.hasAttribute( 'data-sz-dock' ) ) {
			dock( el );
		}
	}

	// while the page scrolls, the floats hold still (moving shadows under glass cost frames); they go on
	// after. Their own class, not one on <html>: that would restyle the whole page twice per scroll.
	let still = 0;
	window.addEventListener( 'scroll', () => {
		const bobs = document.querySelectorAll( '.sz-floats.has-bob:not(.sz-asleep)' );
		if ( ! bobs.length ) {
			return;
		}
		if ( ! still ) {
			bobs.forEach( ( f ) => f.classList.add( 'is-still' ) );
		}
		clearTimeout( still );
		still = setTimeout( () => {
			still = 0;
			document.querySelectorAll( '.sz-floats.is-still' ).forEach( ( f ) => f.classList.remove( 'is-still' ) );
		}, 160 );
	}, { passive: true } );

	/**
	 * The title's width. On a phone (where it stays on one line) with "Fill the width on phones"
	 * on, the name is set to fill its box exactly, edge to edge, larger or smaller. Elsewhere it
	 * only gets smaller, when a word would run wider than the box. Measured when sizes or fonts
	 * change, from the size the Style tab gives it; never per frame.
	 */
	function fitTitle( t ) {
		const phone = () => window.matchMedia( '(max-width: 767px)' ).matches;
		const run = () => {
			t.style.fontSize = '';
			const box = t.clientWidth;
			if ( ! box ) {
				return;
			}
			const oneLine = getComputedStyle( t ).flexWrap === 'nowrap';
			const fill = oneLine && phone() && t.dataset.fit === '1';
			const words = [ ...t.querySelectorAll( '.sz-htitle__w' ) ];
			const parts = [ ...t.querySelectorAll( ':scope > .sz-htitle__w, :scope > .sz-htitle__app' ) ];
			// one line: from the first part's left edge to the last one's right (scrollWidth never reads less than the box)
			// layout boxes, not getBoundingClientRect: the entrance moves the words with transforms
			const span = () => Math.max( ...parts.map( ( el ) => el.offsetLeft + el.offsetWidth ) ) - Math.min( ...parts.map( ( el ) => el.offsetLeft ) );
			const need = () => ( oneLine ? ( parts.length ? span() : 0 ) : Math.max( 0, ...words.map( ( w ) => w.offsetWidth ) ) );
			// a few rounds: the icon tile between the words does not scale with the type
			for ( let k = 0; k < 5; k++ ) {
				const n = need();
				if ( ! n || ( fill ? Math.abs( n - box ) <= 1 : n <= box + 1 ) ) {
					break;
				}
				t.style.fontSize = ( parseFloat( getComputedStyle( t ).fontSize ) * ( box / n ) * ( fill ? 0.995 : 0.98 ) ).toFixed( 2 ) + 'px';
			}
		};
		let raf = 0;
		const queue = () => ( raf = raf || requestAnimationFrame( () => {
			raf = 0;
			run();
		} ) );
		run();
		window.addEventListener( 'resize', queue, { passive: true } );
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( queue );
		}
	}
	const titles = new WeakSet();
	const fitTitles = ( scope ) => ( scope || document ).querySelectorAll( '.sz-htitle' ).forEach( ( t ) => {
		if ( ! titles.has( t ) ) {
			titles.add( t );
			fitTitle( t );
		}
	} );

	const SEL = '[data-sz-floats],[data-sz-dock],[data-sz-magnet]';
	const all = () => {
		document.querySelectorAll( SEL ).forEach( mount );
		fitTitles();
	};
	window.addEventListener( 'elementor/frontend/init', () => {
		[ 'sz-floats', 'sz-dock', 'sz-htitle', 'sz-btn' ].forEach( ( w ) => window.elementorFrontend.hooks.addAction( `frontend/element_ready/${ w }.default`, ( $el ) => {
			$el[ 0 ].querySelectorAll( SEL ).forEach( mount );
			fitTitles( $el[ 0 ] );
		} ) );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
