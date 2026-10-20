/**
 * Scroll-driven sections (markup: Pin_Controls::open()). The engine the
 * three widgets share; each one says what a position means.
 *
 *   const pin = window.szwPin( wrapper, {
 *     update( px, len ) {},     // every frame the position changes (0…len)
 *     stops() { return [px…] }, // optional: where it may come to rest
 *     settle: true,             // optional: rest on the nearest stop (fine pointers only)
 *     live( on ) {},            // optional: told when pinning starts or stops
 *   } );
 *   pin.to( px, smooth )        // put the page where the section reads px
 *
 * Scroll-linked, never scroll-jacked: the page's own scroll is only read.
 * Whether it pins is CSS's call (the stage is sticky or it is not), so the
 * device, reduced motion and the editor need nothing here. Per frame there is
 * one subtraction: where the wrapper starts and how long it is are measured
 * only when sizes change, never while scrolling.
 */
( function () {
	'use strict';
	if ( window.szwPin ) {
		return;
	}
	const fine = () => window.matchMedia && window.matchMedia( '(pointer: fine)' ).matches;

	/** A parent that clips (overflow hidden/auto/scroll) makes sticky stick to it, not to the page. */
	function clipped( el ) {
		for ( let p = el.parentElement; p && p !== document.body && p !== document.documentElement; p = p.parentElement ) {
			const cs = getComputedStyle( p );
			if ( /(hidden|auto|scroll)/.test( cs.overflowY + cs.overflowX ) ) {
				return p;
			}
		}
		return null;
	}

	window.szwPin = function ( root, o ) {
		const stage = root.querySelector( ':scope > .sz-pin__stage' );
		let start = 0, len = 0, on = false, px = -1, raf = 0, near = false, settling = 0, idle = 0;

		const read = () => Math.max( 0, Math.min( len, window.scrollY - start ) );

		const frame = () => {
			raf = 0;
			const now = read();
			if ( now !== px ) {
				px = now;
				root.style.setProperty( '--szp-p', len ? ( px / len ).toFixed( 4 ) : 0 );
				o.update( px, len );
			}
		};
		const queue = () => {
			raf = raf || requestAnimationFrame( frame );
		};

		/** Where it starts and how long it is; whether it pins at all. Only when sizes change. */
		const measure = () => {
			root.classList.remove( 'is-off' );
			let live = getComputedStyle( stage ).position === 'sticky';
			if ( live ) {
				const top = parseFloat( getComputedStyle( root ).getPropertyValue( '--szp-top' ) ) || 0;
				const bad = clipped( root );
				if ( bad || stage.offsetHeight > window.innerHeight - top ) {
					if ( bad && ! root.dataset.szWarned ) {
						root.dataset.szWarned = '1';
						console.info( 'Scroll-driven is off: a parent container clips (Overflow is not Default).', bad ); // eslint-disable-line no-console
					}
					root.classList.add( 'is-off' );
					live = false;
				} else {
					len = parseFloat( getComputedStyle( root, '::after' ).height ) || 0;
					start = root.getBoundingClientRect().top + window.scrollY - top;
				}
			}
			if ( live !== on ) {
				on = live;
				root.classList.toggle( 'is-live', on );
				if ( o.live ) {
					o.live( on );
				}
			}
			px = -1;
			if ( on ) {
				queue();
			}
		};

		/* settle: once the scroll stops, a position close to a stop rests on it */
		const settle = () => {
			const at = read(); // not the last frame's: scrollend can come before that frame
			if ( ! on || ! o.settle || ! o.stops || ! fine() || Date.now() < settling || at <= 0 || at >= len ) {
				return;
			}
			const stops = o.stops( len );
			let best = null;
			stops.forEach( ( s ) => {
				if ( best === null || Math.abs( s - at ) < Math.abs( best - at ) ) {
					best = s;
				}
			} );
			const gap = stops.length > 1 ? len / ( stops.length - 1 ) : len;
			const d = best === null ? 0 : best - at;
			if ( Math.abs( d ) > 2 && Math.abs( d ) < gap * 0.2 ) {
				settling = Date.now() + 700;
				window.scrollTo( { top: start + best, behavior: 'smooth' } );
			}
		};

		const onScroll = () => {
			if ( ! on || ! near ) {
				return;
			}
			queue();
			if ( ! ( 'onscrollend' in window ) ) {
				clearTimeout( idle );
				idle = setTimeout( settle, 160 );
			}
		};
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		if ( 'onscrollend' in window ) {
			window.addEventListener( 'scrollend', () => near && settle() );
		}

		// listen only while it is near the screen
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( ( es ) => {
				near = es.some( ( e ) => e.isIntersecting );
				if ( near ) {
					queue();
				}
			}, { rootMargin: '50% 0px' } ).observe( root );
		} else {
			near = true;
		}

		// sizes: its own, the page's (content above it can grow), the window's
		let mraf = 0;
		const remeasure = () => {
			mraf = mraf || requestAnimationFrame( () => {
				mraf = 0;
				measure();
			} );
		};
		if ( 'ResizeObserver' in window ) {
			const ro = new ResizeObserver( remeasure );
			ro.observe( root );
			ro.observe( document.body );
		}
		window.addEventListener( 'resize', remeasure );
		window.addEventListener( 'load', remeasure );
		measure();

		return {
			get live() {
				return on;
			},
			get length() {
				return len;
			},
			get at() {
				return Math.max( 0, px );
			},
			/** Put the page where the section reads px. */
			to( to, smooth ) {
				if ( ! on ) {
					return;
				}
				settling = Date.now() + 900;
				window.scrollTo( { top: start + Math.max( 0, Math.min( len, to ) ), behavior: smooth && ! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) ? 'smooth' : 'instant' } );
			},
			/** The path's length changed (set --szp-len first). */
			refit: measure,
		};
	};
}() );
