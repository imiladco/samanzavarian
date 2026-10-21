/**
 * Photo Stack — behaviour for .sz-stack (markup: Photo_Stack::render()).
 *
 * - Fans out on hover (mouse) or tap (touch, keyboard: the front photo is a
 *   button with aria-expanded). Closes on leaving, Esc, a tap outside, or
 *   when the deck scrolls out of view on a touch screen.
 * - While fanned, any photo comes to the front: click or tap it, swipe
 *   sideways (touch), or ← → (keyboard). It arcs over the deck while the
 *   old front dips under, on the compositor (Web Animations).
 * - The fan never reaches past the window's edge: its width is the room
 *   left, measured on every resize.
 * - A short peek the first time the deck is seen, depth under the mouse,
 *   and hints that say what to do on this device until it has been done.
 * - A photo with a video plays it, muted, while it is in front and the deck
 *   is open (hover with a mouse, a tap on touch). The src is set when the
 *   deck nears the screen (first frames only); the video fades in on its
 *   first frame, and a ring round the caption icon fills with its time.
 *   Never with reduced motion or data saver.
 * - Analytics through szwTrack (GTM dataLayer, else GA4).
 *
 * The resting geometry matches stack.css exactly (same numbers, there in
 * percent, here in pixels), so taking over from the stylesheet moves nothing.
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

	window.szwTrack = window.szwTrack || function ( event, params ) {
		try {
			if ( window.google_tag_manager || ( Array.isArray( window.dataLayer ) && typeof window.gtag !== 'function' ) ) {
				window.dataLayer.push( Object.assign( { event }, params ) );
			} else if ( typeof window.gtag === 'function' ) {
				window.gtag( 'event', event, params );
			}
		} catch ( e ) {}
	};

	const instances = new WeakMap();
	const media = ( q ) => window.matchMedia && window.matchMedia( q ).matches;
	const PEEK = 0.38;

	class SzStack {
		constructor( root ) {
			this.root = root;
			this.deck = root.querySelector( '.sz-stack__deck' );
			this.cards = [ ...this.deck.querySelectorAll( '.sz-stack__card' ) ];
			this.sticker = root.querySelector( '.sz-stack__sticker' );
			this.live = root.querySelector( '.sz-stack__live' );
			this.labels = Object.assign( { show: 'Show more photos', hide: 'Hide the other photos', forward: 'Bring forward:', front: 'in front' }, JSON.parse( root.dataset.labels || '{}' ) );
			this.bringOn = root.dataset.bring === '1';
			this.track = root.dataset.track === '1';
			this.editOpen = root.dataset.editOpen === '1';
			this.reduce = media( '(prefers-reduced-motion: reduce)' );
			this.fine = media( '(hover: hover) and (pointer: fine)' );
			this.parallax = root.dataset.parallax === '1' && this.fine && ! this.reduce;
			this.edit = !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
			this.off = [];
			this.timers = [];
			this.videos = this.cards.map( ( c ) => c.querySelector( '.sz-stack__video' ) );
			this.autoplay = ! this.reduce && ! ( navigator.connection && navigator.connection.saveData );
			this.playing = -1;

			// order[slot] = card; slot 0 is in front
			this.order = this.cards.map( ( c, i ) => i ).sort( ( a, b ) => this.cards[ a ].dataset.slot - this.cards[ b ].dataset.slot );
			this.meta = this.cards.map( ( c ) => {
				const t = c.querySelector( '.sz-stack__cap b' ), s = c.querySelector( '.sz-stack__cap small' ), l = c.querySelector( '.sz-stack__lab' );
				return { title: t ? t.textContent : ( l ? l.textContent : '' ), sub: s ? s.textContent : '' };
			} );
			this.open = this.editOpen;
			this.peek = 0;
			this.hot = -1;
			this.px = 0;
			this.py = 0;
			this.pose = [];
			this.opened = false;

			this.render = this.render.bind( this );
			this.bind();

			if ( 'ResizeObserver' in window ) {
				this.ro = new ResizeObserver( () => this.render() );
				this.ro.observe( this.deck );
			}
			this.on( window, 'resize', this.render );
			this.render();
			requestAnimationFrame( () => root.classList.add( 'is-ready' ) );
			this.scrollDrive();
			this.teach();
			this.prime();
		}

		on( target, type, fn, opts ) {
			target.addEventListener( type, fn, opts );
			this.off.push( () => target.removeEventListener( type, fn, opts ) );
		}

		later( fn, ms ) {
			this.timers.push( setTimeout( fn, ms ) );
		}

		destroy() {
			this.off.forEach( ( f ) => f() );
			this.timers.forEach( clearTimeout );
			clearTimeout( this.closeT );
			clearTimeout( this.driveT );
			if ( this.ro ) {
				this.ro.disconnect();
			}
			if ( this.io ) {
				this.io.disconnect();
			}
			if ( this.near ) {
				this.near.disconnect();
			}
			if ( this.scrollIo ) {
				this.scrollIo.disconnect();
			}
			if ( this.driveRo ) {
				this.driveRo.disconnect();
				this.widget.classList.remove( 'sz-stack-hold' );
			}
			this.video( -1 );
		}

		/* ---------------- geometry ---------------- */

		/** How far a fanned photo may travel before its turned corner meets the window's edge. */
		spread( W, H ) {
			const r = this.deck.getBoundingClientRect(), vw = document.documentElement.clientWidth;
			const a = 13 * Math.PI / 180, bw = W * 0.86 * Math.cos( a ) + H * 0.86 * Math.sin( a );
			const room = Math.min( r.left, vw - r.right ) - 10;
			const deeper = this.cards.length > 3 ? W * 0.06 : 0;
			return Math.max( W * 0.1, Math.min( W * 0.34, W / 2 + room - bw / 2 - deeper ) );
		}

		/** Where a slot sits: f is how far fanned (0 resting, 1 open). Same numbers as stack.css. */
		place( slot, f, W, H, sp ) {
			if ( slot === 0 ) {
				return { x: this.px * 6, y: -0.03 * H * f + this.py * 4, r: -f, s: 1 };
			}
			const d = slot % 2 ? -1 : 1, k = Math.ceil( slot / 2 ) - 1, left = d < 0;
			const rest = { x: d * W * ( 0.07 + 0.04 * k ), y: H * ( ( left ? 0.03 : 0.04 ) + 0.02 * k ), r: d * ( ( left ? 7 : 6 ) + 2 * k ), s: 0.93 - 0.04 * k };
			const fan = { x: d * ( sp + W * 0.06 * k ), y: H * ( ( left ? 0.02 : 0.06 ) + 0.02 * k ), r: d * ( ( left ? 13 : 12 ) + 2 * k ), s: 0.86 - 0.04 * k };
			const p = {};
			[ 'x', 'y', 'r', 's' ].forEach( ( key ) => {
				p[ key ] = rest[ key ] + ( fan[ key ] - rest[ key ] ) * f;
			} );
			p.x -= this.px * 4;
			p.y -= this.py * 3;
			return p;
		}

		css( p ) {
			return 'translate(' + p.x.toFixed( 1 ) + 'px,' + p.y.toFixed( 1 ) + 'px) rotate(' + p.r.toFixed( 2 ) + 'deg) scale(' + p.s.toFixed( 3 ) + ')';
		}

		render() {
			const W = this.deck.offsetWidth, H = this.deck.offsetHeight;
			if ( ! W ) {
				return;
			}
			const f = this.open ? 1 : this.peek, sp = this.spread( W, H ), full = f >= 1;
			// short of room (phones), the front photo steps back a little, so
			// the ones beside it show more than a sliver
			const shrink = 1 - Math.min( 0.12, Math.max( 0, ( W * 0.34 - sp ) / W ) * 0.8 ) * f;
			this.order.forEach( ( card, slot ) => {
				const el = this.cards[ card ], p = this.place( slot, f, W, H, sp );
				if ( slot === 0 ) {
					p.s *= shrink;
				}
				if ( full && card === this.hot && slot > 0 ) {
					p.y -= W * 0.025; // the photo under the mouse rises: it can be picked
					p.s += 0.015;
				}
				this.pose[ card ] = p;
				el.style.transform = this.css( p );
				if ( el.dataset.slot !== String( slot ) ) {
					el.dataset.slot = String( slot );
					el.dataset.side = slot === 0 ? '' : ( slot % 2 ? 'l' : 'r' );
				}
				const m = this.meta[ card ];
				if ( slot === 0 ) {
					el.setAttribute( 'aria-expanded', String( this.open ) );
					el.setAttribute( 'aria-label', m.title + ( m.sub ? ', ' + m.sub : '' ) + '. ' + ( this.open ? this.labels.hide : this.labels.show ) );
					el.tabIndex = 0;
				} else {
					el.removeAttribute( 'aria-expanded' );
					el.setAttribute( 'aria-label', this.labels.forward + ' ' + m.title );
					el.tabIndex = full && this.bringOn ? 0 : -1;
				}
			} );
			// fanned by the scroll looks and plays as fanned by the hand
			this.root.classList.toggle( 'is-open', full );
			this.video( full && this.autoplay ? this.order[ 0 ] : -1 );
		}

		/* ---------------- video ---------------- */

		/** Near the screen: give the videos their src, so a hover starts at once. */
		prime() {
			if ( ! this.autoplay || ! this.videos.some( Boolean ) ) {
				return;
			}
			const load = () => this.videos.forEach( ( v ) => {
				if ( v && ! v.getAttribute( 'src' ) ) {
					v.preload = 'metadata';
					v.src = v.dataset.src;
				}
			} );
			if ( ! ( 'IntersectionObserver' in window ) ) {
				load();
				return;
			}
			this.near = new IntersectionObserver( ( es ) => {
				if ( es.some( ( e ) => e.isIntersecting ) ) {
					this.near.disconnect();
					load();
				}
			}, { rootMargin: '300px 0px' } );
			this.near.observe( this.deck );
		}

		/** Play the video of this card (or none: -1); every other one pauses. */
		video( card ) {
			if ( ! this.videos[ card ] ) {
				card = -1;
			}
			if ( card === this.playing ) {
				return;
			}
			const was = this.playing;
			this.playing = card;
			if ( was >= 0 ) {
				this.videos[ was ].pause();
				this.cards[ was ].classList.remove( 'is-playing' );
			}
			if ( card < 0 ) {
				return;
			}
			const v = this.videos[ card ], el = this.cards[ card ];
			if ( ! v.getAttribute( 'src' ) ) {
				v.src = v.dataset.src;
			}
			const shown = () => {
				if ( this.playing === card ) {
					el.classList.add( 'is-playing' );
					this.ring();
				}
			};
			if ( ! v.paused && v.readyState > 2 ) {
				shown();
			} else {
				v.addEventListener( 'playing', shown, { once: true } );
			}
			const p = v.play();
			if ( p && p.catch ) {
				p.catch( () => {} );
			}
		}

		/** The ring round the caption icon fills with the video's time. */
		ring() {
			if ( this.ringing ) {
				return;
			}
			this.ringing = true;
			let last = -1;
			const step = () => {
				const card = this.playing, v = this.videos[ card ];
				if ( card < 0 || ! v ) {
					this.ringing = false;
					return;
				}
				const p = Number.isFinite( v.duration ) && v.duration > 0 ? v.currentTime / v.duration : 0;
				if ( Math.abs( p - last ) > 0.002 ) {
					last = p;
					this.cards[ card ].style.setProperty( '--szk-p', p.toFixed( 4 ) );
				}
				requestAnimationFrame( step );
			};
			requestAnimationFrame( step );
		}

		/* ---------------- state ---------------- */

		/** Fanned by the hand or all the way by the scroll: a photo can be picked. */
		fanned() {
			return this.open || this.peek >= 1;
		}

		/**
		 * all: the hand closes what the scroll fanned too (a tap on the front photo, Esc, a tap
		 * outside); a hover ending only ends the hover, the scroll's fan stays.
		 */
		setOpen( v, all ) {
			if ( this.editOpen ) {
				v = true;
			}
			const peek = ! v && all && this.peek >= 1;
			if ( this.open === v && ! peek ) {
				return;
			}
			if ( peek ) {
				this.peek = 0; // until the scroll's next step
			}
			this.open = v;
			this.taughtOpen = true;
			this.hot = -1;
			if ( ! v ) {
				this.px = this.py = 0;
			}
			this.render();
			if ( v && ! this.opened && ! this.edit ) {
				this.opened = true;
				if ( this.track ) {
					window.szwTrack( 'sz_stack_open', { photo_count: this.cards.length } );
				}
			}
		}

		/**
		 * Bring a photo to the front: it arcs over the deck, the old front dips under.
		 * The scroll may bring any photo and give the whole order (back to the first: as it was).
		 */
		bring( card, from, next ) {
			const slot = this.order.indexOf( card ), scroll = from === 'scroll';
			if ( slot <= 0 || ! ( this.bringOn || scroll ) ) {
				return;
			}
			const old = this.order[ 0 ], a = this.pose[ card ], b = this.pose[ old ];
			if ( next ) {
				this.order = next.slice();
			} else {
				this.order[ 0 ] = card;
				this.order[ slot ] = old;
			}
			this.hot = -1;

			const moving = [ this.cards[ card ], this.cards[ old ] ];
			const animate = ! this.reduce && typeof moving[ 0 ].animate === 'function' && a && b;
			if ( animate ) {
				moving.forEach( ( el ) => {
					el.style.transition = 'none';
				} );
			}
			this.cards[ old ].classList.add( 'is-leaving' );
			this.later( () => this.cards[ old ].classList.remove( 'is-leaving' ), 320 );
			this.render();

			if ( animate ) {
				const H = this.deck.offsetHeight, inTo = this.pose[ card ], outTo = this.pose[ old ];
				const mid = ( p, q, lift, scale ) => ( { x: ( p.x + q.x ) / 2, y: Math.min( p.y, q.y ) + lift * H, r: ( p.r + q.r ) / 2, s: scale } );
				const run = ( el, p, m, q, delay ) => {
					const anim = el.animate( [ { transform: this.css( p ) }, { transform: this.css( m ), offset: 0.45 }, { transform: this.css( q ) } ], { duration: 640, delay, easing: 'cubic-bezier(.3,.7,.25,1)', fill: 'backwards' } );
					const done = () => {
						el.style.transition = '';
					};
					anim.finished.then( done, done );
				};
				run( this.cards[ card ], a, mid( a, inTo, -0.07, Math.max( a.s, inTo.s ) + 0.035 ), inTo, 0 );
				run( this.cards[ old ], b, { x: ( b.x + outTo.x ) / 2, y: Math.max( b.y, outTo.y ) + 0.02 * H, r: ( b.r + outTo.r ) / 2, s: 0.95 }, outTo, 30 );
			}

			if ( this.sticker && ! this.reduce ) {
				this.sticker.classList.remove( 'is-hop' );
				void this.sticker.offsetWidth;
				this.sticker.classList.add( 'is-hop' );
			}
			if ( scroll ) {
				return; // not the visitor's doing: no hint learnt, nothing announced or reported
			}
			this.root.classList.add( 'is-taught' );
			if ( this.live ) {
				szwText( this.live, this.meta[ card ].title + ' ' + this.labels.front );
			}
			if ( from === 'key' ) {
				this.cards[ card ].focus( { preventScroll: true } );
			}
			if ( this.track && ! this.edit ) {
				window.szwTrack( 'sz_stack_photo', { photo_title: this.meta[ card ].title, photo_position: card + 1, method: from } );
			}
		}

		/** The photo on one side, nearest the front: -1 left, 1 right. */
		beside( dir ) {
			const slot = this.order.findIndex( ( c, s ) => s > 0 && ( s % 2 ? -1 : 1 ) === dir );
			return slot > 0 ? this.order[ slot ] : ( this.order[ 1 ] ?? -1 );
		}

		/* ---------------- input ---------------- */

		bind() {
			let type = 'mouse', down = null, swiped = 0;

			this.on( this.deck, 'pointerdown', ( e ) => {
				type = e.pointerType;
				down = e.pointerType === 'mouse' ? null : { x: e.clientX, y: e.clientY };
			} );

			// swipe sideways while fanned: the photo on the side it reveals comes forward
			this.on( this.deck, 'pointerup', ( e ) => {
				if ( ! down ) {
					return;
				}
				const dx = e.clientX - down.x, dy = e.clientY - down.y;
				down = null;
				if ( Math.abs( dx ) < 40 || Math.abs( dx ) < Math.abs( dy ) * 1.5 ) {
					return;
				}
				swiped = Date.now();
				if ( ! this.fanned() ) {
					this.setOpen( true );
				} else {
					this.bring( this.beside( dx < 0 ? 1 : -1 ), 'swipe' );
				}
			} );

			this.on( this.deck, 'click', ( e ) => {
				const el = e.target.closest( '.sz-stack__card' );
				if ( ! el || Date.now() - swiped < 400 ) {
					return;
				}
				const card = this.cards.indexOf( el ), keyboard = e.detail === 0;
				if ( this.order[ 0 ] !== card ) {
					if ( this.fanned() ) {
						this.bring( card, keyboard ? 'key' : ( type === 'mouse' ? 'click' : 'tap' ) );
					} else {
						this.setOpen( true );
					}
					return;
				}
				// the front photo: a mouse fans by hovering, so its click is for touch and keys
				if ( keyboard || type !== 'mouse' || ! this.fine ) {
					this.setOpen( ! this.fanned(), true );
				}
			} );

			this.on( this.deck, 'keydown', ( e ) => {
				if ( e.key === 'Escape' && this.fanned() && ! this.editOpen ) {
					this.setOpen( false, true );
					this.cards[ this.order[ 0 ] ].focus();
				} else if ( ( e.key === 'ArrowLeft' || e.key === 'ArrowRight' ) && this.fanned() ) {
					e.preventDefault();
					this.bring( this.beside( e.key === 'ArrowLeft' ? -1 : 1 ), 'key' );
				}
			} );

			this.on( this.deck, 'focusout', ( e ) => {
				if ( this.open && ! this.deck.contains( e.relatedTarget ) && ! this.deck.matches( ':hover' ) ) {
					this.setOpen( false );
				}
			} );

			this.on( document, 'pointerdown', ( e ) => {
				if ( this.fanned() && e.pointerType !== 'mouse' && ! this.deck.contains( e.target ) ) {
					this.setOpen( false, true );
				}
			} );

			if ( this.fine ) {
				// a hover is a hand moving over it: the page scrolling the deck under a still
				// cursor is not one (the browser says enter, and moves, all the same: without movement)
				this.on( this.deck, 'pointermove', ( e ) => {
					if ( e.pointerType === 'mouse' && ( e.movementX || e.movementY ) ) {
						clearTimeout( this.closeT );
						if ( ! this.open ) {
							this.setOpen( true );
						}
					}
				} );
				// a little grace, so a hand that slips off the edge does not close it
				this.on( this.deck, 'pointerleave', ( e ) => {
					if ( e.pointerType === 'mouse' ) {
						clearTimeout( this.closeT );
						this.closeT = setTimeout( () => this.setOpen( false ), 220 );
					}
				} );
				this.on( this.deck, 'pointerover', ( e ) => {
					const el = e.target.closest( '.sz-stack__card' ), card = el ? this.cards.indexOf( el ) : -1;
					const hot = this.bringOn && card !== this.order[ 0 ] ? card : -1;
					if ( hot !== this.hot ) {
						this.hot = hot;
						this.render();
					}
				} );
			}

			if ( this.parallax ) {
				let raf = 0;
				this.on( this.deck, 'pointermove', ( e ) => {
					const r = this.deck.getBoundingClientRect();
					this.px = Math.max( -1, Math.min( 1, ( e.clientX - r.left ) / r.width * 2 - 1 ) );
					this.py = Math.max( -1, Math.min( 1, ( e.clientY - r.top ) / r.height * 2 - 1 ) );
					if ( ! raf ) {
						raf = requestAnimationFrame( () => {
							raf = 0;
							if ( this.fanned() ) {
								this.render();
							}
						} );
					}
				} );
			}
		}

		/**
		 * Seen for the first time: a short peek, so a phone visitor learns
		 * the photos move. Out of view on a touch screen: close.
		 */
		teach() {
			if ( ! ( 'IntersectionObserver' in window ) ) {
				return;
			}
			let shown = false;
			const peek = this.root.dataset.peek === '1' && ! this.reduce && ! this.edit;
			this.io = new IntersectionObserver( ( es ) => {
				const e = es[ es.length - 1 ];
				if ( ! e.isIntersecting ) {
					if ( ! this.fine && this.open ) {
						this.setOpen( false );
					}
					return;
				}
				if ( shown || e.intersectionRatio < 0.6 ) {
					return;
				}
				shown = true;
				if ( ! peek ) {
					return;
				}
				this.later( () => {
					if ( this.taughtOpen || this.scrolls() ) {
						return; // the scroll shows it better
					}
					this.peek = PEEK;
					this.render();
					this.later( () => {
						this.peek = 0;
						this.render();
					}, 750 );
				}, 500 );
			}, { threshold: [ 0, 0.6 ] } );
			this.io.observe( this.deck );
		}

		/* ---------------- scroll-driven ---------------- */

		/** On for this screen width (the stylesheet's breakpoints). */
		scrolls() {
			if ( ! this.scrollOn.length ) {
				return false;
			}
			const w = window.innerWidth;
			return this.scrollOn.includes( w > 1024 ? 'd' : ( w >= 768 ? 't' : 'm' ) );
		}

		/**
		 * The page's scroll past the deck plays the hover: fan out on the first
		 * photo, each next one to the front (its video plays), fold back as it
		 * was; backwards when scrolling up. The scroll is the playhead.
		 *
		 * The path is only the stretch where the deck is in view below the header
		 * (four fifths of it at least coming in, all of it at the end, so it folds
		 * in full view), so nothing starts at the screen's foot or changes under
		 * the header. Where its column is taller than it (beside the text on a
		 * desktop) the deck holds still in it, centred, while the text scrolls
		 * past: that room becomes path, without adding any to the page.
		 *
		 * A clock is only a guard: a photo stays at least a second, so a flick
		 * never shuffles; at a reading pace the photos follow the scroll exactly.
		 * Half in view nothing changes; scrolled on past, it folds; out of view
		 * it is put back at rest at once.
		 *
		 * States: 0 at rest, 1…N fanned with photo N in front, N+1 at rest again.
		 * Edge-triggered: a photo the hand chose stays until the next step; a
		 * hand fanning it keeps it fanned.
		 */
		scrollDrive() {
			this.scrollOn = ( this.root.dataset.scroll || '' ).split( ' ' ).filter( Boolean );
			if ( ! this.scrollOn.length || this.reduce || this.edit || this.editOpen ) {
				this.scrollOn = [];
				return;
			}
			this.base = this.order.slice();
			this.state = 0;
			this.moved = 0; // when the state last changed
			this.wanted = 0; // since when the scroll has wanted another state
			this.widget = this.root.closest( '.elementor-widget' ) || this.root;
			let raf = 0, mraf = 0, near = false;
			const frame = () => {
				raf = 0;
				if ( ! this.geo ) {
					return;
				}
				if ( near ) {
					this.follow();
				} else if ( this.state > 0 && this.state <= this.cards.length ) {
					clearTimeout( this.driveT );
					this.setState( this.target().t ); // gone by: at rest, as it was
				}
			};
			this.queueDrive = () => {
				raf = raf || requestAnimationFrame( frame );
			};
			const remeasure = () => {
				mraf = mraf || requestAnimationFrame( () => {
					mraf = 0;
					this.measureDrive();
					this.queueDrive();
				} );
			};
			this.trail = []; // recent scroll positions: how fast it is going
			this.on( window, 'scroll', () => {
				if ( near ) {
					const now = performance.now();
					this.trail.push( [ now, window.scrollY ] );
					while ( this.trail.length > 2 && now - this.trail[ 0 ][ 0 ] > 300 ) {
						this.trail.shift();
					}
					this.queueDrive();
				}
			}, { passive: true } );
			this.on( window, 'resize', remeasure );
			this.on( window, 'load', remeasure );
			if ( 'ResizeObserver' in window ) {
				this.driveRo = new ResizeObserver( remeasure );
				this.driveRo.observe( document.body );
				this.driveRo.observe( this.root );
			}
			if ( 'IntersectionObserver' in window ) {
				this.scrollIo = new IntersectionObserver( ( es ) => {
					near = es[ es.length - 1 ].isIntersecting;
					this.queueDrive();
				}, { rootMargin: '25% 0px' } );
				this.scrollIo.observe( this.deck );
			} else {
				near = true;
			}
			this.measureDrive();
		}

		/** Room kept free at the top for the header bar. */
		inset() {
			return Math.min( 96, document.documentElement.clientHeight * 0.1 );
		}

		/**
		 * Only when sizes change: whether the deck holds in its column, and the
		 * scroll positions where the path starts (the whole deck has come in)
		 * and ends (its top reaches the header).
		 */
		measureDrive() {
			const w = this.widget, col = w.parentElement, vh = document.documentElement.clientHeight, inset = this.inset();
			w.classList.remove( 'sz-stack-hold' );
			w.style.removeProperty( '--szk-hold' );
			if ( ! this.scrolls() ) {
				this.geo = null;
				if ( this.state ) {
					clearTimeout( this.driveT );
					this.setState( 0 ); // not on at this width: at rest
				}
				return;
			}
			const y = window.scrollY, wr = w.getBoundingClientRect(), dr = this.deck.getBoundingClientRect();
			const cr = col && col !== document.body ? col.getBoundingClientRect() : wr;
			// held only over empty room: it is the last thing in a column taller than it (anything below would be covered)
			const pad = col ? parseFloat( getComputedStyle( col ).paddingBottom ) || 0 : 0;
			const dy = dr.top - wr.top, h = dr.height, Ws = wr.top + y, room = w.nextElementSibling ? 0 : cr.bottom - pad - wr.bottom;
			const top = Math.max( inset, ( vh - wr.height ) / 2 );
			const hold = room > 40 && wr.height + top <= vh;
			if ( hold ) {
				w.style.setProperty( '--szk-hold', Math.round( top ) + 'px' );
				w.classList.add( 'sz-stack-hold' );
			}
			// from four fifths of the deck in at the foot to its top at the header: it folds in full view
			const y0 = Ws + dy + 0.8 * h - vh * 0.97;
			const y1 = ( hold ? Ws + room : Ws ) + dy - inset;
			this.geo = { y0, y1: Math.max( y1, y0 + 200 ) };
		}

		/** Where the scroll wants it, and how much of the deck is in view below the header. */
		target() {
			const r = this.deck.getBoundingClientRect(), vh = document.documentElement.clientHeight, N = this.cards.length;
			const p = ( window.scrollY - this.geo.y0 ) / ( this.geo.y1 - this.geo.y0 );
			const seen = Math.max( 0, Math.min( r.bottom, vh ) - Math.max( r.top, this.inset() ) ) / ( r.height || 1 );
			const t = p <= 0 ? 0 : ( p >= 1 ? N + 1 : 1 + Math.min( N - 1, Math.floor( p * N ) ) );
			// "in view": three quarters of it, or of the screen for a deck taller than that
			return { t, seen, whole: seen >= Math.min( 0.75, vh / ( r.height || 1 ) * 0.75 ) };
		}

		follow() {
			clearTimeout( this.driveT );
			const { t, seen, whole } = this.target(), now = Date.now(), N = this.cards.length;
			const rest = t === 0 || t === N + 1;
			if ( t === this.state ) {
				this.wanted = 0;
				return;
			}
			// mostly out of view: straight to where it should be
			if ( seen < 0.4 ) {
				this.wanted = 0;
				this.setState( t );
				return;
			}
			// half in view: nothing starts or changes at an edge; scrolled on past, it folds
			if ( ! whole ) {
				this.wanted = 0;
				if ( rest ) {
					this.setState( t );
				}
				return;
			}
			// flicked past: it is not opened for a glimpse (looked at again in a moment)
			const atRest = this.state === 0 || this.state === N + 1;
			if ( atRest && this.speed() > 0.8 ) {
				this.wanted = 0;
				this.driveT = setTimeout( this.queueDrive, 120 );
				return;
			}
			this.wanted = this.wanted || now;
			// the guard: a breath before it opens, a second for each photo
			const ready = Math.max( this.wanted + ( atRest ? 300 : 0 ), this.moved + ( this.state > 0 && this.state <= N ? 1000 : 0 ) );
			if ( now < ready ) {
				this.driveT = setTimeout( this.queueDrive, ready - now + 16 );
				return;
			}
			// one step at a time; from rest it opens on the photo the scroll is at
			this.setState( rest || atRest ? t : this.state + ( t > this.state ? 1 : -1 ) );
			this.wanted = 0;
			if ( this.state !== t ) {
				this.driveT = setTimeout( this.queueDrive, 1016 );
			}
		}

		/** The scroll's speed over the last 300ms, in px per ms. */
		speed() {
			const tr = this.trail, now = performance.now();
			if ( tr.length < 2 || now - tr[ tr.length - 1 ][ 0 ] > 150 ) {
				return 0; // standing still
			}
			const a = tr[ 0 ], b = tr[ tr.length - 1 ];
			return Math.abs( b[ 1 ] - a[ 1 ] ) / Math.max( 50, b[ 0 ] - a[ 0 ] );
		}

		setState( s ) {
			const N = this.cards.length, fan = s > 0 && s <= N ? 1 : 0;
			const card = fan ? this.base[ s - 1 ] : this.base[ 0 ];
			this.state = s;
			this.moved = Date.now();
			if ( fan !== this.peek ) {
				this.peek = fan;
				if ( ! this.open ) {
					this.render();
				}
			}
			if ( this.order[ 0 ] !== card ) {
				this.bring( card, 'scroll', card === this.base[ 0 ] ? this.base : null );
			} else if ( ! fan && this.order.join() !== this.base.join() ) {
				this.order = this.base.slice(); // at rest: as it was
				this.render();
			}
		}
	}

	function mount( root ) {
		if ( ! root || instances.has( root ) ) {
			return root ? instances.get( root ) : null;
		}
		if ( root.querySelectorAll( '.sz-stack__card' ).length < 2 ) {
			return null;
		}
		const inst = new SzStack( root );
		instances.set( root, inst );
		return inst;
	}

	function mountAll( scope ) {
		( scope || document ).querySelectorAll( '[data-sz-stack]' ).forEach( mount );
	}

	window.szwStack = { mount, get: ( root ) => instances.get( root ) };

	window.addEventListener( 'elementor/frontend/init', () => {
		const Base = window.elementorModules && window.elementorModules.frontend.handlers.Base;

		if ( ! Base ) {
			return;
		}

		class Handler extends Base {
			onInit() {
				super.onInit();
				mount( this.$element[ 0 ].querySelector( '[data-sz-stack]' ) );
			}

			onDestroy() {
				const root = this.$element[ 0 ].querySelector( '[data-sz-stack]' );
				const inst = root && instances.get( root );
				if ( inst ) {
					inst.destroy();
					instances.delete( root );
				}
				super.onDestroy();
			}
		}

		window.elementorFrontend.elementsHandler.attachHandler( 'sz-stack', Handler );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => window.elementorFrontend || mountAll() );
	} else if ( ! window.elementorFrontend ) {
		mountAll();
	}
}() );
