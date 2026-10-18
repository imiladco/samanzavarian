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
 * - Analytics through szwTrack (GTM dataLayer, else GA4).
 *
 * The resting geometry matches stack.css exactly (same numbers, there in
 * percent, here in pixels), so taking over from the stylesheet moves nothing.
 */
( function () {
	'use strict';

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
			this.teach();
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
			if ( this.ro ) {
				this.ro.disconnect();
			}
			if ( this.io ) {
				this.io.disconnect();
			}
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
			const f = this.open ? 1 : this.peek, sp = this.spread( W, H );
			// short of room (phones), the front photo steps back a little, so
			// the ones beside it show more than a sliver
			const shrink = 1 - Math.min( 0.12, Math.max( 0, ( W * 0.34 - sp ) / W ) * 0.8 ) * f;
			this.order.forEach( ( card, slot ) => {
				const el = this.cards[ card ], p = this.place( slot, f, W, H, sp );
				if ( slot === 0 ) {
					p.s *= shrink;
				}
				if ( this.open && card === this.hot && slot > 0 ) {
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
					el.tabIndex = this.open && this.bringOn ? 0 : -1;
				}
			} );
			this.root.classList.toggle( 'is-open', this.open );
		}

		/* ---------------- state ---------------- */

		setOpen( v ) {
			if ( this.editOpen ) {
				v = true;
			}
			if ( this.open === v ) {
				return;
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

		/** Bring a photo to the front: it arcs over the deck, the old front dips under. */
		bring( card, from ) {
			const slot = this.order.indexOf( card );
			if ( slot <= 0 || ! this.bringOn ) {
				return;
			}
			const old = this.order[ 0 ], a = this.pose[ card ], b = this.pose[ old ];
			this.order[ 0 ] = card;
			this.order[ slot ] = old;
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
			this.root.classList.add( 'is-taught' );
			if ( this.live ) {
				this.live.textContent = this.meta[ card ].title + ' ' + this.labels.front;
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
				if ( ! this.open ) {
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
					if ( this.open ) {
						this.bring( card, keyboard ? 'key' : ( type === 'mouse' ? 'click' : 'tap' ) );
					} else {
						this.setOpen( true );
					}
					return;
				}
				// the front photo: a mouse fans by hovering, so its click is for touch and keys
				if ( keyboard || type !== 'mouse' || ! this.fine ) {
					this.setOpen( ! this.open );
				}
			} );

			this.on( this.deck, 'keydown', ( e ) => {
				if ( e.key === 'Escape' && this.open && ! this.editOpen ) {
					this.setOpen( false );
					this.cards[ this.order[ 0 ] ].focus();
				} else if ( ( e.key === 'ArrowLeft' || e.key === 'ArrowRight' ) && this.open ) {
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
				if ( this.open && e.pointerType !== 'mouse' && ! this.deck.contains( e.target ) ) {
					this.setOpen( false );
				}
			} );

			if ( this.fine ) {
				this.on( this.deck, 'pointerenter', ( e ) => {
					if ( e.pointerType === 'mouse' ) {
						clearTimeout( this.closeT );
						this.setOpen( true );
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
							if ( this.open ) {
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
					if ( this.taughtOpen ) {
						return;
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
