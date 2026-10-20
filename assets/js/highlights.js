/**
 * Highlights — the "What I bring" deck (markup: Highlights::render()).
 *
 * - Change: the leaving card slides away in the direction of travel; the
 *   next one's icon springs in, then its title and text; the sheets under
 *   the deck dip; the counter rolls. Web Animations (compositor only).
 *   Reduced motion: a short crossfade, and nothing advances by itself.
 * - Auto-advance with story segments: the current one fills with the time
 *   left. Pauses while keyboard focus is inside, off screen and in a
 *   background tab; runs no frames then. Not under a resting mouse: a pointer
 *   left on the deck froze it and read as broken (owner's report). Picking a
 *   card (button, segment, swipe, key) stops it for good; the pause button
 *   stops or restarts it. In the editor it runs too, and selecting a card in
 *   the panel shows that card and stops it.
 * - Screen readers: the slide region is aria-live only once the visitor has
 *   taken over, so automatic changes are not read out.
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

	const instances = new WeakMap();
	const media = ( q ) => window.matchMedia && window.matchMedia( q ).matches;
	const SPRING = 'cubic-bezier(.34,1.4,.64,1)', EASE = 'cubic-bezier(.22,.8,.24,1)';

	class SzHighlights {
		constructor( root ) {
			this.root = root;
			this.deck = root.querySelector( '.sz-hl__deck' );
			this.region = root.querySelector( '.sz-hl__slides' );
			this.slides = [ ...root.querySelectorAll( '.sz-hl__slide' ) ];
			this.segs = [ ...root.querySelectorAll( '.sz-hl__seg' ) ];
			this.roll = root.querySelector( '.sz-hl__roll' );
			this.pp = root.querySelector( '.sz-hl__pp' );
			this.labels = Object.assign( { pause: 'Pause', play: 'Play' }, JSON.parse( root.dataset.labels || '{}' ) );
			this.reduce = media( '(prefers-reduced-motion: reduce)' );
			this.auto = root.dataset.auto === '1' && ! this.reduce;
			this.playing = this.auto;
			this.dur = parseInt( root.dataset.duration, 10 ) || 5200;
			this.i = 0;
			this.elapsed = 0;
			this.focus = false;
			this.seen = false;
			this.off = [];

			this.loop = this.loop.bind( this );
			this.bind();

			if ( this.pp && this.auto ) {
				this.pp.hidden = false;
			}
			if ( ! this.auto ) {
				this.segs[ 0 ] && this.segs[ 0 ].style.setProperty( '--p', 1 );
			}
			if ( 'IntersectionObserver' in window ) {
				this.io = new IntersectionObserver( ( es ) => {
					this.seen = es[ es.length - 1 ].isIntersecting;
					this.wake();
				}, { threshold: 0.4 } );
				this.io.observe( root );
			} else {
				this.seen = true;
				this.wake();
			}
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

		/* ---------------- the clock ---------------- */

		running() {
			return this.auto && this.playing && this.seen && ! this.focus && document.visibilityState !== 'hidden';
		}

		/** Frames only while the clock can move. */
		wake() {
			if ( this.running() && ! this.raf ) {
				this.last = performance.now();
				this.raf = requestAnimationFrame( this.loop );
			}
		}

		loop( now ) {
			this.raf = 0;
			if ( ! this.running() ) {
				return;
			}
			this.elapsed += now - this.last;
			this.last = now;
			const p = Math.min( 1, this.elapsed / this.dur );
			this.segs[ this.i ] && this.segs[ this.i ].style.setProperty( '--p', p.toFixed( 4 ) );
			if ( p >= 1 ) {
				this.go( this.i + 1, { dir: 1 } );
			}
			this.raf = requestAnimationFrame( this.loop );
		}

		/** The visitor took over: the deck stops, and what they do is read out. */
		takeOver() {
			if ( this.playing ) {
				this.setPlaying( false );
			}
			this.region.setAttribute( 'aria-live', 'polite' );
		}

		setPlaying( v ) {
			this.playing = v && this.auto;
			if ( this.pp ) {
				this.pp.setAttribute( 'aria-pressed', String( ! this.playing ) );
				this.pp.setAttribute( 'aria-label', this.playing ? this.labels.pause : this.labels.play );
			}
			if ( this.playing ) {
				this.region.setAttribute( 'aria-live', 'off' );
				this.wake();
			} else {
				this.segs[ this.i ] && this.segs[ this.i ].style.setProperty( '--p', 1 );
			}
		}

		/* ---------------- a change ---------------- */

		go( to, { user = false, dir = 0 } = {} ) {
			const n = this.slides.length;
			const j = ( ( to % n ) + n ) % n;
			if ( user ) {
				this.takeOver();
			}
			if ( j === this.i ) {
				return;
			}
			dir = dir || ( j > this.i ? 1 : -1 );
			const from = this.slides[ this.i ], next = this.slides[ j ], was = this.i;
			this.i = j;
			this.elapsed = 0;

			// states first: the incoming card is the live one at once
			from.classList.remove( 'is-on' );
			from.setAttribute( 'aria-hidden', 'true' );
			from.inert = true;
			next.classList.add( 'is-on' );
			next.removeAttribute( 'aria-hidden' );
			next.inert = false;
			this.segs.forEach( ( s, k ) => {
				const on = k === j;
				s.setAttribute( 'aria-selected', String( on ) );
				s.tabIndex = on ? 0 : -1;
				s.classList.toggle( 'is-done', k < j );
				s.style.setProperty( '--p', on && this.playing ? 0 : 1 );
			} );
			this.count( j, dir );

			if ( typeof next.animate !== 'function' ) {
				return;
			}
			if ( this.reduce ) {
				next.animate( [ { opacity: 0 }, { opacity: 1 } ], { duration: 200 } );
				return;
			}

			// the leaving card slides away the way we travel
			from.classList.add( 'is-out' );
			const out = from.animate( [ { opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateX(' + ( -dir * 26 ) + 'px)' } ], { duration: 170, easing: 'cubic-bezier(.4,0,1,1)', fill: 'forwards' } );
			const clear = () => {
				if ( this.i !== was ) {
					from.classList.remove( 'is-out' );
				}
				out.cancel();
			};
			out.finished.then( clear, clear );

			// the next one arrives as the old one is nearly gone (never two texts over each other):
			// icon first, with a spring; then title, then text
			const tile = next.querySelector( '.sz-hl__tile' ), title = next.querySelector( '.sz-hl__title' ), text = next.querySelector( '.sz-hl__text' );
			if ( tile ) {
				tile.animate( [ { opacity: 0, transform: 'scale(.55) rotate(' + ( dir * -14 ) + 'deg)' }, { opacity: 1, transform: 'none' } ], { duration: 560, delay: 120, easing: SPRING, fill: 'backwards' } );
			}
			[ title, text ].forEach( ( el, k ) => el && el.animate( [ { opacity: 0, transform: 'translateX(' + ( dir * 18 ) + 'px)' }, { opacity: 1, transform: 'none' } ], { duration: 440, delay: 150 + k * 55, easing: EASE, fill: 'backwards' } ) );

			// the sheets dip, as if the top card was lifted off
			if ( this.root.classList.contains( 'has-sheets' ) ) {
				this.deck.classList.remove( 'is-dip' );
				void this.deck.offsetWidth;
				this.deck.classList.add( 'is-dip' );
			}
		}

		/** The counter rolls: the old number up and out, the new one in from below (or the reverse going back). */
		count( j, dir ) {
			if ( ! this.roll ) {
				return;
			}
			const old = this.roll.lastElementChild, nu = document.createElement( 'span' );
			nu.className = 'sz-hl__i';
			szwText( nu, String( j + 1 ) );
			this.roll.appendChild( nu );
			if ( this.reduce || typeof nu.animate !== 'function' ) {
				old.remove();
				return;
			}
			const d = dir > 0 ? 1 : -1;
			nu.animate( [ { transform: 'translateY(' + ( d * 100 ) + '%)', opacity: 0 }, { transform: 'none', opacity: 1 } ], { duration: 380, easing: EASE } );
			const a = old.animate( [ { transform: 'none', opacity: 1 }, { transform: 'translateY(' + ( -d * 100 ) + '%)', opacity: 0 } ], { duration: 300, easing: EASE, fill: 'forwards' } );
			a.finished.then( () => old.remove(), () => old.remove() );
		}

		/* ---------------- input ---------------- */

		bind() {
			this.root.querySelectorAll( '.sz-hl__btn' ).forEach( ( b ) => this.on( b, 'click', () => this.go( this.i + ( +b.dataset.dir ), { user: true, dir: +b.dataset.dir } ) ) );
			this.segs.forEach( ( s ) => this.on( s, 'click', () => this.go( +s.dataset.i, { user: true } ) ) );

			// segments are tabs: arrows move and choose, Home/End jump
			this.on( this.root, 'keydown', ( e ) => {
				const onSeg = this.segs.includes( document.activeElement ), onDeck = e.target === this.region;
				if ( ! onSeg && ! onDeck ) {
					return;
				}
				const n = this.slides.length;
				const to = { ArrowRight: this.i + 1, ArrowLeft: this.i - 1, Home: 0, End: n - 1 }[ e.key ];
				if ( to === undefined ) {
					return;
				}
				e.preventDefault();
				const dir = e.key === 'ArrowLeft' || e.key === 'Home' ? -1 : 1;
				this.go( to, { user: true, dir } );
				if ( onSeg ) {
					this.segs[ this.i ].focus();
				}
			} );

			// a sideways swipe on the card; vertical scrolling stays the page's
			let down = null;
			this.on( this.region, 'pointerdown', ( e ) => {
				down = e.pointerType === 'mouse' ? null : { x: e.clientX, y: e.clientY };
			} );
			this.on( this.region, 'pointerup', ( e ) => {
				if ( ! down ) {
					return;
				}
				const dx = e.clientX - down.x, dy = e.clientY - down.y;
				down = null;
				if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) * 1.5 ) {
					this.go( this.i + ( dx < 0 ? 1 : -1 ), { user: true, dir: dx < 0 ? 1 : -1 } );
				}
			} );

			if ( this.pp ) {
				this.on( this.pp, 'click', () => {
					this.setPlaying( ! this.playing );
					if ( this.playing ) {
						this.elapsed = 0;
						this.segs[ this.i ].style.setProperty( '--p', 0 );
					}
				} );
			}

			// pause while keyboard focus is inside; resume when it leaves (a mouse click
			// that focuses a button has already taken over, so it needs no pause of its own)
			this.on( this.root, 'focusin', ( e ) => {
				this.focus = e.target !== this.pp && e.target.matches( ':focus-visible' );
			} );
			this.on( this.root, 'focusout', ( e ) => {
				if ( ! this.root.contains( e.relatedTarget ) ) {
					this.focus = false;
					this.wake();
				}
			} );
			this.on( document, 'visibilitychange', () => this.wake() );
		}
	}

	function mount( root ) {
		if ( ! root || instances.has( root ) || root.querySelectorAll( '.sz-hl__slide' ).length < 2 ) {
			return;
		}
		instances.set( root, new SzHighlights( root ) );
	}

	function mountAll( scope ) {
		( scope || document ).querySelectorAll( '[data-sz-hl]' ).forEach( mount );
	}

	window.szwHighlights = { get: ( root ) => instances.get( root ) };

	window.addEventListener( 'elementor/frontend/init', () => {
		const Base = window.elementorModules && window.elementorModules.frontend.handlers.Base;
		if ( ! Base ) {
			return;
		}
		class Handler extends Base {
			onInit() {
				super.onInit();
				mount( this.$element[ 0 ].querySelector( '[data-sz-hl]' ) );
			}

			// a card selected in the editor's panel: show it, and hold still while it is edited
			onEditSettingsChange( name, value ) {
				const inst = instances.get( this.$element[ 0 ].querySelector( '[data-sz-hl]' ) );
				if ( 'activeItemIndex' === name && inst && value ) {
					inst.go( value - 1, { user: true } );
				}
			}
			onDestroy() {
				const root = this.$element[ 0 ].querySelector( '[data-sz-hl]' ), inst = root && instances.get( root );
				if ( inst ) {
					inst.destroy();
					instances.delete( root );
				}
				super.onDestroy();
			}
		}
		window.elementorFrontend.elementsHandler.attachHandler( 'sz-highlights', Handler );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => window.elementorFrontend || mountAll() );
	} else if ( ! window.elementorFrontend ) {
		mountAll();
	}
}() );
