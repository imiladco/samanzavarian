/**
 * Selected work — behaviour for .sz-work (markup: Work_Rail::render()).
 *
 * The HTML already shows the first filter's cards; this adds:
 * - filters: a thumb that slides to the active one; arrow keys, Home/End;
 *   the result read out ("3 projects in Photo"); the active filter kept in
 *   view when the row scrolls (phones), with fades at a cut edge.
 * - the filter transition: leaving cards fade out where they were, staying
 *   cards slide (translate only — a card that changes size fades instead of
 *   being stretched), new cards rise one after another.
 * - the rail: mouse drag with a throw that settles on a card; page buttons
 *   (as many cards as are in view); a scrubber; the position "1–3 of 8";
 *   arrow keys / Home / End when the rail has focus.
 * - cards: tilt and glare under a mouse; a preview video that is fetched
 *   the first time the card is hovered and plays only while it is.
 * - the address: #work-photo opens Photo (on load, on hashchange, on a
 *   click on such a link); choosing a filter writes it back (replaceState).
 * - analytics through szwTrack (GTM dataLayer, else GA4).
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

	class SzWork {
		constructor( root ) {
			this.root = root;
			this.rail = root.querySelector( '.sz-work__rail' );
			this.box = root.querySelector( '.sz-work__box' );
			this.list = root.querySelector( '.sz-work__filters' );
			this.fwrap = root.querySelector( '.sz-work__fwrap' );
			this.thumb = root.querySelector( '.sz-work__thumb' );
			this.pos = root.querySelector( '.sz-work__pos' );
			this.prog = root.querySelector( '.sz-work__prog' );
			this.bar = this.prog && this.prog.querySelector( 'i' );
			this.prev = root.querySelector( '.sz-work__btn[data-dir="-1"]' );
			this.next = root.querySelector( '.sz-work__btn[data-dir="1"]' );
			this.live = root.querySelector( '.sz-work__live' );
			this.cards = [ ...this.rail.querySelectorAll( '.sz-work__card' ) ];
			this.buttons = this.list ? [ ...this.list.querySelectorAll( '.sz-work__filter' ) ] : [];
			this.labels = Object.assign( { of: 'of', one: 'project', many: 'projects', in: 'in' }, JSON.parse( root.dataset.szLabels || '{}' ) );
			this.writeHash = root.dataset.szHash === '1';
			this.track = root.dataset.szTrack === '1';
			this.wide = root.dataset.szWide === '1';
			this.reduce = media( '(prefers-reduced-motion: reduce)' );
			this.fine = media( '(pointer: fine)' );
			this.edit = !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
			this.current = ( this.buttons.find( ( b ) => b.getAttribute( 'aria-pressed' ) === 'true' ) || { dataset: { f: 'all' } } ).dataset.f;
			this.timers = [];
			this.off = [];

			this.sync = this.sync.bind( this );
			this.layoutAll = this.layoutAll.bind( this );

			this.bindFilters();
			this.bindRail();
			this.bindScrub();
			this.bindDrag();
			this.bindCards();
			this.bindHash();

			if ( 'ResizeObserver' in window ) {
				this.ro = new ResizeObserver( this.layoutAll );
				this.ro.observe( root );
			}
			this.on( window, 'resize', this.layoutAll );

			this.layoutAll();
			( document.fonts ? document.fonts.ready : Promise.resolve() ).then( () => {
				this.layoutAll();
				requestAnimationFrame( () => root.classList.add( 'is-ready' ) );
			} );
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
			clearTimeout( this.snapBack );
			if ( this.ro ) {
				this.ro.disconnect();
			}
		}

		/* ---------------- measuring ---------------- */

		/** Bleed: how far the window's edges are from the widget's. */
		bleed() {
			if ( ! this.root.classList.contains( 'is-bleed' ) ) {
				return;
			}
			const r = this.root.getBoundingClientRect();
			const W = document.documentElement.clientWidth;
			this.root.style.setProperty( '--szw-bl', Math.max( 0, Math.floor( r.left ) ) + 'px' );
			this.root.style.setProperty( '--szw-br', Math.max( 0, Math.floor( W - r.right ) ) + 'px' );
		}

		layoutAll() {
			this.bleed();
			this.moveThumb( true );
			this.edges();
			this.sync();
		}

		visible() {
			return this.cards.filter( ( c ) => ! c.hidden );
		}

		projects() {
			return this.visible().filter( ( c ) => c.dataset.g !== '*' );
		}

		start() {
			return parseFloat( getComputedStyle( this.rail ).paddingLeft ) || 0;
		}

		/** Snap points: where each visible card starts. */
		starts() {
			const s = this.start();
			return this.visible().map( ( c ) => c.offsetLeft - s );
		}

		/** First and last card at least half in view. */
		range() {
			const vis = this.visible(), L = this.rail.scrollLeft, s = this.start(), W = this.rail.clientWidth - s - ( parseFloat( getComputedStyle( this.rail ).paddingRight ) || 0 );
			let a = -1, b = -1;
			vis.forEach( ( c, i ) => {
				const x0 = c.offsetLeft - s - L, x1 = x0 + c.offsetWidth;
				if ( x0 > -c.offsetWidth * 0.5 && x1 < W + c.offsetWidth * 0.5 ) {
					if ( a < 0 ) {
						a = i;
					}
					b = i;
				}
			} );
			return [ Math.max( 0, a ), Math.max( 0, b ) ];
		}

		/**
		 * Scroll to x. With snap off until it arrives: re-enabled mid-way (as
		 * when a drag ends) the browser snaps back to the nearest card first
		 * and the smooth scroll never happens.
		 */
		go( x ) {
			const rail = this.rail;
			rail.style.scrollSnapType = 'none';
			rail.scrollTo( { left: x, behavior: this.reduce ? 'auto' : 'smooth' } );
			clearTimeout( this.snapBack );
			const done = () => {
				clearTimeout( this.snapBack );
				rail.removeEventListener( 'scrollend', done );
				rail.style.scrollSnapType = '';
			};
			if ( 'onscrollend' in window ) {
				rail.addEventListener( 'scrollend', done );
			}
			this.snapBack = setTimeout( done, 900 );
		}

		nearest( x ) {
			return this.starts().reduce( ( best, s ) => ( Math.abs( s - x ) < Math.abs( best - x ) ? s : best ), 0 );
		}

		/** Position, progress, buttons, and each card's "i of n" name. */
		sync() {
			const rail = this.rail, max = rail.scrollWidth - rail.clientWidth;
			this.root.classList.toggle( 'is-static', max < 4 );

			if ( this.prev ) {
				this.prev.disabled = rail.scrollLeft < 8;
				this.next.disabled = rail.scrollLeft > max - 8;
			}

			if ( this.bar ) {
				const track = this.prog.clientWidth, w = Math.min( track, Math.max( track * ( rail.clientWidth / Math.max( 1, rail.scrollWidth ) ), 40 ) );
				this.bar.style.width = w + 'px';
				this.bar.style.transform = 'translateX(' + ( max > 0 ? ( rail.scrollLeft / max ) * ( track - w ) : 0 ) + 'px)';
			}

			// position among the projects (the closing card is not one)
			const [ a0, b0 ] = this.range(), list = this.projects(), n = list.length, a = Math.min( a0, n - 1 ), b = Math.min( b0, n - 1 );
			if ( this.pos ) {
				const text = '<b>' + ( a + 1 ) + ( b > a ? '–' + ( b + 1 ) : '' ) + '</b> ' + this.esc( this.labels.of ) + ' ' + n;
				if ( this.pos.innerHTML !== text ) {
					this.pos.innerHTML = text;
				}
			}
			list.forEach( ( c, i ) => {
				const t = c.querySelector( '.sz-work__title' );
				c.setAttribute( 'aria-label', ( i + 1 ) + ' ' + this.labels.of + ' ' + n + ': ' + ( t ? t.textContent : '' ) );
			} );
		}

		esc( s ) {
			const d = document.createElement( 'span' );
			d.textContent = s;
			return d.innerHTML;
		}

		/* ---------------- filters ---------------- */

		button( f ) {
			return this.buttons.find( ( b ) => b.dataset.f === f );
		}

		moveThumb( instant ) {
			const b = this.button( this.current );
			if ( ! b || ! this.thumb ) {
				return;
			}
			if ( instant ) {
				this.thumb.style.transition = 'none';
			}
			this.thumb.style.width = b.offsetWidth + 'px';
			this.thumb.style.transform = 'translateX(' + b.offsetLeft + 'px)';
			if ( instant ) {
				void this.thumb.offsetWidth;
				this.thumb.style.transition = '';
			}
			// keep the active filter in view when the row scrolls (phones)
			const list = this.list, l = b.offsetLeft - 24, r = b.offsetLeft + b.offsetWidth + 24 - list.clientWidth;
			if ( l < list.scrollLeft ) {
				list.scrollTo( { left: l, behavior: instant || this.reduce ? 'auto' : 'smooth' } );
			} else if ( r > list.scrollLeft ) {
				list.scrollTo( { left: r, behavior: instant || this.reduce ? 'auto' : 'smooth' } );
			}
		}

		edges() {
			if ( ! this.list ) {
				return;
			}
			const l = this.list;
			this.fwrap.classList.toggle( 'can-l', l.scrollLeft > 2 );
			this.fwrap.classList.toggle( 'can-r', l.scrollLeft < l.scrollWidth - l.clientWidth - 2 );
		}

		bindFilters() {
			if ( ! this.list ) {
				return;
			}
			this.on( this.list, 'scroll', () => this.edges(), { passive: true } );
			this.buttons.forEach( ( b ) => this.on( b, 'click', () => this.filter( b.dataset.f, { user: true } ) ) );
			this.on( this.list, 'keydown', ( e ) => {
				const i = this.buttons.indexOf( document.activeElement );
				if ( i < 0 ) {
					return;
				}
				const to = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: this.buttons.length - 1 }[ e.key ];
				if ( to === undefined ) {
					return;
				}
				e.preventDefault();
				const b = this.buttons[ ( to + this.buttons.length ) % this.buttons.length ];
				b.focus();
				this.filter( b.dataset.f, { user: true } );
			} );
		}

		inFilter( card, f ) {
			if ( card.dataset.g === '*' || f === 'all' ) {
				return true;
			}
			const b = this.button( f );
			return !! b && ( card.dataset.g === f || ( '' !== b.dataset.match && card.dataset.g === b.dataset.match ) );
		}

		/** Which cards a filter shows; its first project is the wide one. */
		layout( f ) {
			let lead = null;
			this.cards.forEach( ( c ) => {
				const show = this.inFilter( c, f );
				c.hidden = ! show;
				c.classList.remove( 'is-wide' );
				if ( show && ! lead && c.dataset.g !== '*' ) {
					lead = c;
				}
			} );
			if ( ! this.wide ) {
				return;
			}
			// under "All", the hand-picked featured project; elsewhere the filter's first
			const pick = f === 'all' ? ( this.cards.find( ( c ) => c.querySelector( '.sz-work__flag' ) ) || lead ) : lead;
			if ( pick ) {
				pick.classList.add( 'is-wide' );
			}
		}

		filter( f, opts = {} ) {
			const b = this.button( f );
			if ( ! b || ( f === this.current && ! opts.force ) ) {
				return;
			}
			this.current = f;
			this.buttons.forEach( ( x ) => x.setAttribute( 'aria-pressed', String( x === b ) ) );
			this.moveThumb( !! opts.quiet );

			const n = this.cards.filter( ( c ) => c.dataset.g !== '*' && this.inFilter( c, f ) ).length;
			const label = b.dataset.label || b.textContent;

			if ( ! opts.quiet ) {
				this.live.textContent = n + ' ' + ( n === 1 ? this.labels.one : this.labels.many ) + ( f === 'all' ? '' : ' ' + this.labels.in + ' ' + label );
			}
			if ( opts.user ) {
				this.hash( f );
				if ( this.track ) {
					window.szwTrack( 'sz_work_filter', { filter_name: label, filter_id: f, project_count: n } );
				}
			}

			this.stopPreviews();

			if ( opts.quiet || this.reduce ) {
				this.layout( f );
				this.rail.scrollLeft = 0;
				this.sync();
				return;
			}
			this.flip( f );
		}

		/** FLIP: where every card is, then where it will be. */
		flip( f ) {
			const box = this.box.getBoundingClientRect();
			const before = new Map( this.visible().map( ( c ) => [ c, c.getBoundingClientRect() ] ) );

			this.cards.forEach( ( c ) => {
				c.classList.remove( 'is-tilt' );
				c.style.transform = '';
			} );
			this.layout( f );

			// leaving cards: ghosts fade out where they were
			this.cards.forEach( ( c ) => {
				if ( ! before.has( c ) || ! c.hidden ) {
					return;
				}
				const r = before.get( c ), g = c.cloneNode( true );
				g.classList.add( 'sz-work__ghost' );
				g.removeAttribute( 'href' );
				g.removeAttribute( 'id' );
				g.setAttribute( 'aria-hidden', 'true' );
				g.hidden = false;
				Object.assign( g.style, { left: r.left - box.left + 'px', top: r.top - box.top + 'px', width: r.width + 'px', height: r.height + 'px', transform: '' } );
				this.box.appendChild( g );
				requestAnimationFrame( () => g.classList.add( 'is-out' ) );
				this.later( () => g.remove(), 380 );
			} );

			this.rail.style.scrollSnapType = 'none';
			this.rail.scrollLeft = 0;
			let k = 0;
			this.visible().forEach( ( c ) => {
				const a = before.get( c ), b = c.getBoundingClientRect();
				if ( a ) {
					// stayed: slide from the old place. A card that changed size
					// fades in on the way instead of being stretched.
					c.style.transition = 'none';
					c.style.transform = 'translate(' + ( a.left - b.left ) + 'px,' + ( a.top - b.top ) + 'px)';
					if ( Math.abs( a.width - b.width ) > 2 ) {
						c.style.opacity = '0.25';
					}
					c.dataset.flip = '1';
					requestAnimationFrame( () => requestAnimationFrame( () => {
						c.style.transition = 'transform .55s cubic-bezier(.22,.8,.24,1),opacity .45s ease';
						c.style.transform = '';
						c.style.opacity = '';
						this.later( () => {
							c.style.transition = '';
							delete c.dataset.flip;
						}, 600 );
					} ) );
				} else {
					// new: rises in, one after another
					c.classList.add( 'is-enter' );
					this.later( () => c.classList.remove( 'is-enter' ), 60 + 45 * k++ );
				}
			} );
			this.later( () => {
				this.rail.style.scrollSnapType = '';
				this.sync();
			}, 620 );
			this.sync();
		}

		/* ---------------- the address ---------------- */

		hash( f ) {
			if ( ! this.writeHash || this.edit || ! window.history || ! history.replaceState ) {
				return;
			}
			const b = this.button( f );
			const url = location.pathname + location.search + ( f === 'all' || ! b ? '' : '#' + b.id );
			try {
				history.replaceState( history.state, '', url );
			} catch ( e ) {}
		}

		/** Open the filter whose id is in the address or in a clicked #link. */
		fromHash( hash, quiet ) {
			const id = decodeURIComponent( ( hash || '' ).replace( /^#/, '' ) );
			const b = id && this.buttons.find( ( x ) => x.id === id );
			if ( ! b ) {
				return false;
			}
			this.filter( b.dataset.f, { quiet, force: quiet } );
			return true;
		}

		bindHash() {
			if ( ! this.buttons.length ) {
				return;
			}
			this.fromHash( location.hash, true );
			this.on( window, 'hashchange', () => this.fromHash( location.hash, false ) );
			// a link to the filter that is already in the address fires no hashchange
			this.on( document, 'click', ( e ) => {
				const a = e.target.closest && e.target.closest( 'a[href*="#"]' );
				if ( a && a.hash && a.hash === location.hash && a.pathname === location.pathname ) {
					this.fromHash( a.hash, false );
				}
			} );
		}

		/* ---------------- the rail ---------------- */

		bindRail() {
			this.on( this.rail, 'scroll', this.sync, { passive: true } );

			const page = ( dir ) => {
				const [ a, b ] = this.range(), ss = this.starts(), per = Math.max( 1, b - a + 1 );
				const i = Math.max( 0, Math.min( ss.length - 1, a + dir * per ) );
				this.go( dir > 0 && i === ss.length - 1 ? this.rail.scrollWidth : ss[ i ] );
			};
			if ( this.prev ) {
				this.on( this.prev, 'click', () => page( -1 ) );
				this.on( this.next, 'click', () => page( 1 ) );
			}

			this.on( this.rail, 'keydown', ( e ) => {
				if ( e.target !== this.rail ) {
					return;
				}
				const ss = this.starts(), [ a ] = this.range();
				const to = { ArrowRight: ss[ Math.min( ss.length - 1, a + 1 ) ], ArrowLeft: ss[ Math.max( 0, a - 1 ) ], Home: 0, End: this.rail.scrollWidth }[ e.key ];
				if ( to === undefined ) {
					return;
				}
				e.preventDefault();
				this.go( to );
			} );
		}

		/** The progress bar is a scrubber: drag its thumb, or click the track. */
		bindScrub() {
			if ( ! this.prog ) {
				return;
			}
			let scrub = null;
			this.on( this.prog, 'pointerdown', ( e ) => {
				const r = this.prog.getBoundingClientRect(), w = this.bar.offsetWidth, max = this.rail.scrollWidth - this.rail.clientWidth;
				const x0 = r.left + new DOMMatrixReadOnly( getComputedStyle( this.bar ).transform ).m41;
				scrub = { x: e.clientX, s: this.rail.scrollLeft, k: max / Math.max( 1, r.width - w ) };
				this.prog.setPointerCapture( e.pointerId );
				this.prog.classList.add( 'is-held' );
				this.rail.style.scrollSnapType = 'none';
				if ( e.clientX < x0 || e.clientX > x0 + w ) {
					this.rail.scrollLeft = ( ( e.clientX - r.left - w / 2 ) / Math.max( 1, r.width - w ) ) * max;
					scrub.s = this.rail.scrollLeft;
				}
			} );
			this.on( this.prog, 'pointermove', ( e ) => {
				if ( scrub ) {
					this.rail.scrollLeft = scrub.s + ( e.clientX - scrub.x ) * scrub.k;
				}
			} );
			const end = () => {
				if ( ! scrub ) {
					return;
				}
				scrub = null;
				this.prog.classList.remove( 'is-held' );
				this.go( this.nearest( this.rail.scrollLeft ) );
			};
			this.on( this.prog, 'pointerup', end );
			this.on( this.prog, 'pointercancel', end );
		}

		/**
		 * Drag with a mouse: follows the hand, keeps the throw, settles on a
		 * card. A deliberate drag (over 40px) always lands at least one card
		 * further that way, however slow: springing back from a drag nearly
		 * half a wide card long read as the slider refusing to move.
		 */
		bindDrag() {
			if ( ! this.fine ) {
				return;
			}
			let d = null, dragged = false;
			this.on( this.rail, 'pointerdown', ( e ) => {
				if ( e.button || e.pointerType !== 'mouse' ) {
					return;
				}
				d = { x: e.clientX, s: this.rail.scrollLeft, trail: [] };
				dragged = false;
			} );
			this.on( window, 'pointermove', ( e ) => {
				if ( ! d ) {
					return;
				}
				const dx = e.clientX - d.x;
				if ( ! dragged && Math.abs( dx ) > 6 ) {
					dragged = true;
					this.rail.classList.add( 'is-dragging' );
					this.untilt();
				}
				if ( dragged ) {
					const now = performance.now();
					this.rail.scrollLeft = d.s - dx;
					// the last tenth of a second of movement gives the throw
					d.trail.push( [ now, d.s - dx ] );
					while ( d.trail.length > 2 && now - d.trail[ 0 ][ 0 ] > 100 ) {
						d.trail.shift();
					}
				}
			} );
			this.on( window, 'pointerup', () => {
				if ( ! d ) {
					return;
				}
				const { s, trail } = d;
				d = null;
				if ( ! dragged ) {
					return;
				}
				this.rail.style.scrollSnapType = 'none';
				this.rail.classList.remove( 'is-dragging' );
				const now = performance.now(), first = trail[ 0 ], last = trail[ trail.length - 1 ];
				const v = last && now - last[ 0 ] < 120 && last[ 0 ] > first[ 0 ] ? ( last[ 1 ] - first[ 1 ] ) / ( last[ 0 ] - first[ 0 ] ) : 0; // px per ms
				const at = this.rail.scrollLeft, moved = at - s;
				let to = this.nearest( at + v * 260 ); // the throw carries on, then settles on a card
				if ( Math.abs( moved ) > 40 && ( to - s ) * moved <= 0 ) {
					const ss = this.starts().concat( this.rail.scrollWidth - this.rail.clientWidth );
					const ahead = ss.filter( ( x ) => ( moved > 0 ? x > s + 2 : x < s - 2 ) );
					to = ahead.length ? ( moved > 0 ? Math.min( ...ahead ) : Math.max( ...ahead ) ) : to;
				}
				this.go( to );
				setTimeout( () => {
					dragged = false;
				}, 0 );
			} );
			// a drag is not a click on the card it ended on
			this.on( this.rail, 'click', ( e ) => {
				if ( dragged ) {
					e.preventDefault();
					e.stopPropagation();
				}
			}, true );
			this.on( this.rail, 'dragstart', ( e ) => e.preventDefault() );
		}

		/* ---------------- cards ---------------- */

		untilt() {
			this.cards.forEach( ( c ) => {
				c.classList.remove( 'is-tilt' );
				if ( ! c.classList.contains( 'is-enter' ) && ! c.dataset.flip ) {
					c.style.transform = '';
				}
			} );
		}

		bindCards() {
			// tilt and glare under the pointer (mouse only)
			if ( this.fine && ! this.reduce && this.root.dataset.szTilt === '1' ) {
				this.on( this.rail, 'pointermove', ( e ) => {
					const c = e.target.closest( '.sz-work__card' );
					if ( ! c || c.dataset.flip || this.rail.classList.contains( 'is-dragging' ) ) {
						return;
					}
					const r = c.getBoundingClientRect(), x = ( e.clientX - r.left ) / r.width, y = ( e.clientY - r.top ) / r.height;
					c.classList.add( 'is-tilt' );
					c.style.transform = 'perspective(900px) rotateX(' + ( ( 0.5 - y ) * 6 ) + 'deg) rotateY(' + ( ( x - 0.5 ) * 8 ) + 'deg) scale(1.015)';
					c.style.setProperty( '--gx', x * 100 + '%' );
					c.style.setProperty( '--gy', y * 100 + '%' );
				} );
				this.on( this.rail, 'pointerout', ( e ) => {
					const c = e.target.closest( '.sz-work__card' );
					if ( c && ! c.contains( e.relatedTarget ) && ! c.dataset.flip ) {
						c.classList.remove( 'is-tilt' );
						c.style.transform = '';
					}
				} );
			}

			// preview video: fetched on the first hover, plays only while hovered
			if ( this.fine && ! this.reduce ) {
				this.on( this.rail, 'pointerover', ( e ) => {
					const c = e.target.closest( '.sz-work__card' );
					if ( c && ! c.contains( e.relatedTarget ) ) {
						this.preview( c, true );
					}
				} );
				this.on( this.rail, 'pointerout', ( e ) => {
					const c = e.target.closest( '.sz-work__card' );
					if ( c && ! c.contains( e.relatedTarget ) ) {
						this.preview( c, false );
					}
				} );
			}

			// analytics: which project, from which filter and position
			if ( this.track ) {
				this.on( this.root, 'click', ( e ) => {
					const a = e.target.closest( 'a' );
					if ( ! a || ! this.root.contains( a ) ) {
						return;
					}
					if ( a.dataset.szOut ) {
						window.szwTrack( 'sz_work_profile', { source: a.dataset.szOut, filter_id: this.current } );
						return;
					}
					if ( ! a.classList.contains( 'sz-work__card' ) ) {
						return;
					}
					const t = a.querySelector( '.sz-work__title' ), cat = a.querySelector( '.sz-work__cat' );
					window.szwTrack( 'sz_work_open', {
						project_title: t ? t.textContent : '',
						project_category: cat ? cat.textContent : '',
						position: this.projects().indexOf( a ) + 1,
						filter_id: this.current,
						link_url: a.href,
					} );
				} );
			}
		}

		preview( card, on ) {
			const v = card.querySelector( '.sz-work__video' );
			if ( ! v || this.rail.classList.contains( 'is-dragging' ) ) {
				return;
			}
			if ( ! on ) {
				v.pause();
				card.classList.remove( 'is-playing' );
				return;
			}
			if ( ! v.src && v.dataset.src ) {
				v.src = v.dataset.src;
				v.addEventListener( 'playing', () => v.matches( ':hover' ) || card.matches( ':hover' ) ? card.classList.add( 'is-playing' ) : v.pause() );
			}
			const p = v.play();
			if ( p && p.then ) {
				p.then( () => card.matches( ':hover' ) && card.classList.add( 'is-playing' ) ).catch( () => {} );
			}
		}

		stopPreviews() {
			this.cards.forEach( ( c ) => {
				const v = c.querySelector( '.sz-work__video' );
				if ( v && ! v.paused ) {
					v.pause();
				}
				c.classList.remove( 'is-playing' );
			} );
		}
	}

	function mount( root ) {
		if ( ! root || instances.has( root ) ) {
			return root ? instances.get( root ) : null;
		}
		const inst = new SzWork( root );
		instances.set( root, inst );
		return inst;
	}

	function mountAll( scope ) {
		( scope || document ).querySelectorAll( '[data-sz-work]' ).forEach( mount );
	}

	window.szwWork = { mount, get: ( root ) => instances.get( root ) };

	window.addEventListener( 'elementor/frontend/init', () => {
		const Base = window.elementorModules && window.elementorModules.frontend.handlers.Base;

		if ( ! Base ) {
			return;
		}

		class Handler extends Base {
			onInit() {
				super.onInit();
				mount( this.$element[ 0 ].querySelector( '[data-sz-work]' ) );
			}

			onDestroy() {
				const root = this.$element[ 0 ].querySelector( '[data-sz-work]' );
				const inst = root && instances.get( root );
				if ( inst ) {
					inst.destroy();
					instances.delete( root );
				}
				super.onDestroy();
			}
		}

		window.elementorFrontend.elementsHandler.attachHandler( 'sz-work', Handler );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => window.elementorFrontend || mountAll() );
	} else if ( ! window.elementorFrontend ) {
		mountAll();
	}
}() );
