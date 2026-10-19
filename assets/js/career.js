/**
 * Career Timeline (markup: Career_Timeline::render()).
 *
 * - Picking a clip (click, arrows, Home/End, the bar's buttons) shows its
 *   role: the old inspector slides out against the direction of travel, the
 *   new one comes in from it, icon first. Every role shares one grid cell,
 *   so nothing below moves. The timeline scrolls the clip to the middle.
 * - Arrival: the first time the box scrolls into view, the playhead plays
 *   from the first role to "Now" and each clip is laid down as it passes;
 *   on a narrow screen the timeline pans along with it. Only when the box is
 *   out of view as the page opens, never with reduced motion or in the
 *   editor; otherwise it is simply there. Web Animations, compositor only
 *   (the clips' reveal is a clip-path on their own layer).
 * - The timeline's edges fade only where there is more to scroll, and the
 *   phone's swipe hint leaves once the visitor has swiped.
 */
( function () {
	'use strict';

	const instances = new WeakMap();
	const media = ( q ) => window.matchMedia && window.matchMedia( q ).matches;
	const SPRING = 'cubic-bezier(.34,1.4,.64,1)', EASE = 'cubic-bezier(.22,.8,.24,1)';

	class SzCareer {
		constructor( root ) {
			this.root = root;
			this.clips = [ ...root.querySelectorAll( '.sz-cr__clip' ) ].sort( ( a, b ) => a.dataset.i - b.dataset.i );
			this.panels = [ ...root.querySelectorAll( '.sz-cr__panel' ) ];
			this.scroller = root.querySelector( '.sz-cr__scroll' );
			this.tracks = root.querySelector( '.sz-cr__tracks' );
			this.head = root.querySelector( '.sz-cr__head' );
			this.pos = root.querySelector( '.sz-cr__pos' );
			this.btns = [ ...root.querySelectorAll( '.sz-cr__btn' ) ];
			this.swipe = root.querySelector( '.sz-cr__swipe' );
			this.reduce = media( '(prefers-reduced-motion: reduce)' );
			this.editor = !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
			this.cur = Math.max( 0, this.clips.findIndex( ( c ) => c.getAttribute( 'aria-selected' ) === 'true' ) );
			this.off = [];
			this.bind();
			this.center( this.cur, true );
			this.edges();
			this.watch();
		}

		on( t, type, fn, opts ) {
			t.addEventListener( type, fn, opts );
			this.off.push( () => t.removeEventListener( type, fn, opts ) );
		}

		destroy() {
			this.off.forEach( ( f ) => f() );
			cancelAnimationFrame( this.raf );
			if ( this.io ) {
				this.io.disconnect();
			}
		}

		bind() {
			this.clips.forEach( ( c, i ) => {
				this.on( c, 'click', () => this.pick( i ) );
				this.on( c, 'keydown', ( e ) => {
					const k = e.key, last = this.clips.length - 1;
					const to = k === 'ArrowRight' || k === 'ArrowDown' ? i + 1 : k === 'ArrowLeft' || k === 'ArrowUp' ? i - 1 : k === 'Home' ? 0 : k === 'End' ? last : null;
					if ( to !== null && to >= 0 && to <= last ) {
						e.preventDefault();
						this.pick( to, { focus: true } );
					}
				} );
			} );
			this.btns.forEach( ( b ) => this.on( b, 'click', () => this.pick( this.cur + ( +b.dataset.dir ), { from: b } ) ) );

			let raf = 0;
			this.on( this.scroller, 'scroll', () => {
				if ( ! raf ) {
					raf = requestAnimationFrame( () => {
						raf = 0;
						this.edges();
					} );
				}
			}, { passive: true } );
			// a hand on the timeline: the hint has done its job, and any pan stops
			const took = () => {
				this.panning = false;
				if ( this.swipe && this.scroller.scrollWidth > this.scroller.clientWidth ) {
					this.swipe.classList.add( 'is-done' );
				}
			};
			[ 'pointerdown', 'wheel', 'touchstart' ].forEach( ( t ) => this.on( this.scroller, t, took, { passive: true } ) );
			this.on( window, 'resize', () => this.edges(), { passive: true } );
		}

		edges() {
			const s = this.scroller, max = s.scrollWidth - s.clientWidth;
			s.classList.toggle( 'is-start', s.scrollLeft <= 2 );
			s.classList.toggle( 'is-end', s.scrollLeft >= max - 2 );
		}

		center( i, instant ) {
			const s = this.scroller, c = this.clips[ i ];
			if ( ! c || s.scrollWidth <= s.clientWidth ) {
				return;
			}
			const left = Math.max( 0, Math.min( s.scrollWidth - s.clientWidth, c.offsetLeft + c.offsetWidth / 2 - s.clientWidth / 2 + this.tracks.offsetLeft ) );
			s.scrollTo( { left, behavior: instant || this.reduce ? 'auto' : 'smooth' } );
		}

		/* ---------------- picking a role ---------------- */

		pick( i, opts = {} ) {
			const n = this.clips.length;
			if ( i < 0 || i >= n || i === this.cur ) {
				return;
			}
			const was = this.cur, dir = i > was ? 1 : -1;
			this.cur = i;
			this.clips.forEach( ( c, k ) => {
				c.setAttribute( 'aria-selected', k === i ? 'true' : 'false' );
				c.tabIndex = k === i ? 0 : -1;
			} );
			if ( this.pos ) {
				this.pos.textContent = `${ i + 1 } ${ this.root.dataset.of || 'of' } ${ n }`;
			}
			this.btns.forEach( ( b ) => {
				b.disabled = +b.dataset.dir < 0 ? i === 0 : i === n - 1;
			} );
			// the button pressed went dim at the end of the line: keep focus in the bar
			if ( opts.from && opts.from.disabled ) {
				const other = this.btns.find( ( b ) => b !== opts.from );
				other && other.focus();
			}
			this.swap( was, i, dir );
			if ( opts.focus ) {
				this.clips[ i ].focus( { preventScroll: true } );
			}
			this.center( i );
		}

		swap( was, i, dir ) {
			const out = this.panels[ was ], inn = this.panels[ i ];
			if ( ! out || ! inn ) {
				return;
			}
			[ out, inn ].forEach( ( p ) => p.getAnimations && p.getAnimations( { subtree: true } ).forEach( ( a ) => a.finish() ) );
			this.panels.forEach( ( p ) => {
				const on = p === inn;
				p.classList.toggle( 'is-on', on );
				p.classList.remove( 'is-out' );
				p.toggleAttribute( 'inert', ! on );
				if ( on ) {
					p.removeAttribute( 'aria-hidden' );
				} else {
					p.setAttribute( 'aria-hidden', 'true' );
				}
			} );
			if ( ! out.animate ) {
				return;
			}
			if ( this.reduce ) {
				inn.animate( [ { opacity: 0 }, { opacity: 1 } ], { duration: 160 } );
				return;
			}
			// the old role leaves against the direction of travel…
			out.classList.add( 'is-out' );
			const leave = out.animate( [ { opacity: 1, transform: 'none' }, { opacity: 0, transform: `translateX(${ -dir * 14 }px)` } ], { duration: 180, easing: 'ease-in' } );
			leave.onfinish = leave.oncancel = () => out.classList.remove( 'is-out' );
			// …the new one comes in from it, icon first
			const tile = inn.querySelector( '.sz-cr__tile' ), id = inn.querySelector( '.sz-cr__id' ), about = inn.querySelector( '.sz-cr__about' );
			const from = { opacity: 0, transform: `translateX(${ dir * 18 }px)` }, to = { opacity: 1, transform: 'none' };
			tile && tile.animate( [ { opacity: 0, transform: 'scale(.6) rotate(-8deg)' }, { opacity: 1, transform: 'none' } ], { duration: 520, delay: 90, easing: SPRING, fill: 'backwards' } );
			id && id.animate( [ from, to ], { duration: 420, delay: 120, easing: EASE, fill: 'backwards' } );
			about && about.animate( [ from, to ], { duration: 440, delay: 170, easing: EASE, fill: 'backwards' } );
		}

		/* ---------------- arrival: the playhead plays to "Now" ---------------- */

		watch() {
			if ( ! ( 'IntersectionObserver' in window ) ) {
				this.root.classList.add( 'is-seen' );
				return;
			}
			const r = this.root.getBoundingClientRect();
			const away = r.top >= window.innerHeight || r.bottom <= 0;
			const play = this.root.dataset.arrive === '1' && away && ! this.reduce && ! this.editor && this.head && this.head.animate;
			if ( play ) {
				this.root.classList.add( 'is-armed' );
				if ( this.scroller.scrollWidth > this.scroller.clientWidth ) {
					this.scroller.scrollLeft = 0;
				}
			}
			this.io = new IntersectionObserver( ( es ) => {
				const e = es[ es.length - 1 ];
				if ( ! e.isIntersecting || e.intersectionRatio < 0.35 ) {
					return;
				}
				this.io.disconnect();
				this.root.classList.add( 'is-seen' );
				if ( play ) {
					this.play();
				}
			}, { threshold: [ 0, 0.35 ] } );
			this.io.observe( this.root );
		}

		play() {
			const tracks = this.tracks, W = tracks.clientWidth;
			const x1 = this.head.offsetLeft;
			const x0 = Math.min( ...this.clips.map( ( c ) => c.offsetLeft ) );
			const span = Math.max( 1, x1 - x0 );
			// about 1.6s for the whole run, a little longer on wide timelines
			const D = Math.round( Math.min( 2200, Math.max( 1300, span * 1.9 ) ) );
			const linear = 'linear';
			this.root.classList.remove( 'is-armed' );

			this.head.animate(
				[ { transform: `translateX(${ x0 - x1 }px)`, opacity: 0 }, { opacity: 1, offset: 0.06 }, { transform: 'none', opacity: 1 } ],
				{ duration: D, easing: linear, fill: 'backwards' }
			);
			this.clips.forEach( ( c ) => {
				const a = c.offsetLeft - x0, w = c.offsetWidth;
				c.animate(
					[ { clipPath: 'inset(-6px calc(100% + 6px) -6px -6px round 14px)' }, { clipPath: 'inset(-6px -6px -6px -6px round 14px)' } ],
					{ duration: Math.max( 120, ( w / span ) * D ), delay: ( a / span ) * D, easing: linear, fill: 'backwards' }
				);
			} );
			this.root.querySelectorAll( '.sz-cr__ruler span' ).forEach( ( s, k ) => {
				s.animate( [ { opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' } ], { duration: 360, delay: k * 50, easing: EASE, fill: 'backwards' } );
			} );

			// a narrow timeline pans along with the playhead (until a hand takes it)
			const s = this.scroller;
			if ( s.scrollWidth > s.clientWidth && W ) {
				this.panning = true;
				const t0 = performance.now(), max = s.scrollWidth - s.clientWidth;
				const step = ( now ) => {
					if ( ! this.panning ) {
						return;
					}
					const p = Math.min( 1, ( now - t0 ) / D ), x = x0 + span * p;
					s.scrollLeft = Math.max( 0, Math.min( max, x + tracks.offsetLeft - s.clientWidth * 0.62 ) );
					if ( p < 1 ) {
						this.raf = requestAnimationFrame( step );
					} else {
						this.panning = false;
						this.center( this.cur );
					}
				};
				this.raf = requestAnimationFrame( step );
			}
		}
	}

	function mount( root ) {
		if ( ! root || instances.has( root ) ) {
			return;
		}
		instances.set( root, new SzCareer( root ) );
	}

	window.szwCareer = { get: ( root ) => instances.get( root ) };

	window.addEventListener( 'elementor/frontend/init', () => {
		const Base = window.elementorModules && window.elementorModules.frontend.handlers.Base;
		if ( ! Base ) {
			return;
		}
		class Handler extends Base {
			onInit() {
				super.onInit();
				mount( this.$element[ 0 ].querySelector( '[data-sz-cr]' ) );
			}

			// a role picked in the editor's panel: show it
			onEditSettingsChange( name, value ) {
				const inst = instances.get( this.$element[ 0 ].querySelector( '[data-sz-cr]' ) );
				if ( 'activeItemIndex' === name && inst && value ) {
					const i = inst.clips.findIndex( ( c ) => +c.dataset.k === value - 1 );
					if ( i > -1 ) {
						inst.pick( i );
					}
				}
			}

			onDestroy() {
				const root = this.$element[ 0 ].querySelector( '[data-sz-cr]' ), inst = root && instances.get( root );
				if ( inst ) {
					inst.destroy();
					instances.delete( root );
				}
				super.onDestroy();
			}
		}
		window.elementorFrontend.elementsHandler.attachHandler( 'sz-career', Handler );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => window.elementorFrontend || document.querySelectorAll( '[data-sz-cr]' ).forEach( mount ) );
	} else if ( ! window.elementorFrontend ) {
		document.querySelectorAll( '[data-sz-cr]' ).forEach( mount );
	}
}() );
