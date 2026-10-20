/**
 * Career Timeline (markup: Career_Timeline::render()).
 *
 * - The playhead is the way through the career. It stands on the selected
 *   role's first frame and glides to each role picked (clip, arrows,
 *   Home/End, the bar's buttons). Drag it, or the ruler, to scrub: the card
 *   follows the role under it as it passes, the flag reads the month; let
 *   go and it settles on that role's first frame.
 * - Play runs the career like a reel: the playhead plays through every role
 *   (equal time each, so a short role is as readable as a long one) and the
 *   card follows; the narrow timeline pans along. Picking anything stops it;
 *   it pauses off screen and in a background tab.
 * - Skimming (mouse): a quiet line follows the pointer over the timeline
 *   with the month it is on.
 * - Picking a role: the old card slides out against the direction of
 *   travel, the new one comes in from it, icon first. Every role shares one
 *   grid cell, so nothing below moves.
 * - Arrival: the first time the box scrolls into view, the "Now" line runs
 *   from the first role to today and lays each clip down as it passes; then
 *   the playhead drops in. Only when the box is out of view as the page
 *   opens, never with reduced motion or in the editor.
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
	const SPRING = 'cubic-bezier(.34,1.4,.64,1)', EASE = 'cubic-bezier(.22,.8,.24,1)', GLIDE = 'cubic-bezier(.3,1.2,.5,1)';
	const DWELL = 2600;

	class SzCareer {
		constructor( root ) {
			this.root = root;
			this.clips = [ ...root.querySelectorAll( '.sz-cr__clip' ) ].sort( ( a, b ) => a.dataset.i - b.dataset.i );
			this.span = this.clips.map( ( c ) => [ +c.dataset.s, +c.dataset.e ] );
			this.panels = [ ...root.querySelectorAll( '.sz-cr__panel' ) ];
			this.scroller = root.querySelector( '.sz-cr__scroll' );
			this.heads = root.querySelector( '.sz-cr__heads' );
			this.area = root.querySelector( '.sz-cr__area' );
			this.head = root.querySelector( '.sz-cr__head' );
			this.knob = root.querySelector( '.sz-cr__knob' );
			this.now = root.querySelector( '.sz-cr__now' );
			this.skim = root.querySelector( '.sz-cr__skim' );
			this.ruler = root.querySelector( '.sz-cr__ruler' );
			this.pos = root.querySelector( '.sz-cr__pos' );
			this.btns = [ ...root.querySelectorAll( '.sz-cr__btn' ) ];
			this.playBtn = root.querySelector( '.sz-cr__play' );
			this.swipe = root.querySelector( '.sz-cr__swipe' );
			this.labels = Object.assign( { of: 'of', play: 'Play', pause: 'Pause', now: 'Now' }, JSON.parse( root.dataset.labels || '{}' ) );
			this.t0 = +this.area.dataset.t0;
			this.t1 = +this.area.dataset.t1;
			this.tNow = +this.area.dataset.now;
			this.reduce = media( '(prefers-reduced-motion: reduce)' );
			this.editor = !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
			this.cur = Math.max( 0, this.clips.findIndex( ( c ) => c.getAttribute( 'aria-selected' ) === 'true' ) );
			this.fmt = this.monthFormat();
			this.off = [];
			this.bind();
			this.center( this.cur, true );
			this.edges();
			this.near();
			this.watch();
			this.bindPin();
		}

		/* ---------------- scroll-driven (Pin_Controls, js/pin.js) ---------------- */

		/**
		 * Scrolling is the playhead. The section holds still and the page's
		 * scroll plays the career from the first role to now, as Play does:
		 * equal scroll for every role, the playhead crossing it, the card
		 * following the role under it, the narrow timeline panning along. Up
		 * runs it back. Everything it needs is measured when sizes change; a
		 * frame only writes (the playhead's place, the pan, the flag's month).
		 */
		bindPin() {
			const wrap = this.root.parentElement && this.root.parentElement.closest( '.sz-pin' );
			if ( ! wrap || ! window.szwPin || this.editor || ! this.head ) {
				return;
			}
			const n = this.clips.length, step = +wrap.dataset.szPinStep || 45, edge = +wrap.dataset.szPinEdge || 10;
			const unit = ( len ) => len / ( n * step + 2 * edge );
			this.pinAt = ( raw ) => ( edge + raw * step ) * unit( this.pin.length );
			const geo = () => {
				const s = this.scroller;
				this.geo = { aL: this.area.offsetLeft, aW: this.area.offsetWidth, nowX: this.now ? this.now.offsetLeft : -1e9, hw: this.heads ? this.heads.offsetWidth : 0, sW: s.clientWidth, max: s.scrollWidth - s.clientWidth };
			};
			this.pinRaw = 0;
			this.pin = window.szwPin( wrap, {
				update: ( px, len ) => {
					if ( ! this.geo ) {
						geo();
					}
					const raw = Math.max( 0, Math.min( n, ( px / unit( len ) - edge ) / step ) );
					this.pinRaw = raw;
					const k = Math.min( n - 1, Math.floor( raw ) ), p = raw - k;
					const s = this.span[ k ][ 0 ], e = k < n - 1 ? Math.max( s + 0.05, this.span[ k + 1 ][ 0 ] ) : Math.max( s + 0.05, this.tNow );
					const t = Math.min( this.tNow, s + ( e - s ) * p );
					if ( k !== this.cur ) {
						this.pick( k, { auto: true, keepHead: true, keepView: true } );
					}
					// the playhead: its place, its month while it travels, the year at rest on a role
					const x = ( ( t - this.t0 ) / ( this.t1 - this.t0 ) ) * this.geo.aW;
					this.head.style.left = this.pct( t );
					this.head.setAttribute( 'aria-valuenow', t.toFixed( 2 ) );
					szwText( this.knob, p > 0.02 && raw < n ? this.month( t ) : String( Math.floor( t ) ) );
					this.root.classList.toggle( 'is-near', Math.abs( x - this.geo.nowX ) < 64 );
					// the timeline pans so the playhead stays in the middle
					this.panning = false;
					if ( this.geo.max > 0 ) {
						this.scroller.scrollLeft = Math.max( 0, Math.min( this.geo.max, this.geo.aL + x - ( this.geo.sW + this.geo.hw ) / 2 ) );
					}
				},
				live: ( on ) => {
					this.root.classList.toggle( 'is-pinned', on );
					geo();
				},
			} );
			this.on( window, 'resize', geo, { passive: true } );
		}

		/** Play, pinned: the page scrolls itself through the rest of the career, at Play's pace. */
		playPinned() {
			const len = this.pin.length, speed = this.pinAt( 1 ) - this.pinAt( 0 ); // px per role
			if ( this.pin.at >= len - 2 ) {
				this.pin.to( 0, false );
			}
			this.playing = true;
			this.playBtn.setAttribute( 'aria-pressed', 'true' );
			szwText( this.playBtn.lastChild, this.labels.pause );
			// a hand anywhere takes over (not the Play button itself: its click toggles)
			const hand = ( e ) => {
				if ( ! ( e.target && e.target.closest && e.target.closest( '.sz-cr__play' ) ) ) {
					this.stop( false );
				}
			};
			const kinds = [ 'wheel', 'touchstart', 'pointerdown', 'keydown' ];
			kinds.forEach( ( k ) => window.addEventListener( k, hand, { passive: true, capture: true } ) );
			this.unhand = () => kinds.forEach( ( k ) => window.removeEventListener( k, hand, { capture: true } ) );
			let last = performance.now(), y = this.pin.at;
			const tick = ( now ) => {
				if ( ! this.playing ) {
					return;
				}
				y = Math.min( len, y + ( ( now - last ) / DWELL ) * speed );
				last = now;
				this.pin.to( y, false );
				if ( y >= len ) {
					this.stop( false );
					return;
				}
				this.raf = requestAnimationFrame( tick );
			};
			this.raf = requestAnimationFrame( tick );
		}

		on( t, type, fn, opts ) {
			t.addEventListener( type, fn, opts );
			this.off.push( () => t.removeEventListener( type, fn, opts ) );
		}

		destroy() {
			this.off.forEach( ( f ) => f() );
			this.stop( true );
			cancelAnimationFrame( this.raf );
			if ( this.io ) {
				this.io.disconnect();
			}
		}

		/* ---------------- time ⇄ place ---------------- */

		pct( t ) {
			return ( ( t - this.t0 ) / ( this.t1 - this.t0 ) ) * 100 + '%';
		}

		timeAt( clientX ) {
			const r = this.area.getBoundingClientRect();
			const t = this.t0 + ( ( clientX - r.left ) / r.width ) * ( this.t1 - this.t0 );
			return Math.max( this.t0, Math.min( this.tNow, t ) );
		}

		/** The role under a moment: the latest one running then, else the last one begun. */
		roleAt( t ) {
			let best = -1;
			this.span.forEach( ( [ s, e ], i ) => {
				if ( s <= t && t < e && ( best < 0 || s >= this.span[ best ][ 0 ] ) ) {
					best = i;
				}
			} );
			if ( best < 0 ) {
				this.span.forEach( ( [ s ], i ) => {
					if ( s <= t ) {
						best = i;
					}
				} );
			}
			return Math.max( 0, best );
		}

		monthFormat() {
			try {
				return new Intl.DateTimeFormat( document.documentElement.lang || 'en', { month: 'short', year: 'numeric' } );
			} catch ( e ) {
				return null;
			}
		}

		month( t ) {
			const y = Math.floor( t ), m = Math.min( 11, Math.floor( ( t - y ) * 12 ) );
			return this.fmt ? this.fmt.format( new Date( y, m, 1 ) ) : `${ m + 1 }/${ y }`;
		}

		/** Put the playhead at a moment; `glide` animates from where it was (compositor only). */
		setHead( t, { glide = false, label = null } = {} ) {
			if ( ! this.head ) {
				return;
			}
			const from = glide && ! this.reduce ? this.head.offsetLeft : null;
			this.head.style.left = this.pct( t );
			this.head.setAttribute( 'aria-valuenow', t.toFixed( 2 ) );
			const text = label !== null ? label : String( Math.floor( t ) );
			this.head.setAttribute( 'aria-valuetext', text );
			szwText( this.knob, text );
			if ( from !== null ) {
				const d = from - this.head.offsetLeft;
				if ( Math.abs( d ) > 1 ) {
					this.head.animate( [ { transform: `translateX(${ d }px)` }, { transform: 'none' } ], { duration: 560, easing: GLIDE } );
				}
			}
			this.near();
		}

		/** The "Now" flag steps aside when the playhead stands on it. */
		near() {
			if ( this.head && this.now ) {
				this.root.classList.toggle( 'is-near', Math.abs( this.head.offsetLeft - this.now.offsetLeft ) < 64 );
			}
		}

		/* ---------------- wiring ---------------- */

		bind() {
			this.clips.forEach( ( c, i ) => {
				this.on( c, 'click', () => this.pick( i ) );
				this.on( c, 'keydown', ( e ) => this.keys( e, i, false ) );
			} );
			this.btns.forEach( ( b ) => this.on( b, 'click', () => this.pick( this.cur + ( +b.dataset.dir ), { from: b } ) ) );
			if ( this.head ) {
				this.on( this.head, 'keydown', ( e ) => this.keys( e, this.cur, true ) );
			}
			if ( this.playBtn ) {
				this.on( this.playBtn, 'click', () => ( this.playing ? this.pause() : this.play() ) );
			}

			// scrubbing: the playhead or the ruler, dragged
			[ this.head, this.ruler ].forEach( ( el ) => el && this.on( el, 'pointerdown', ( e ) => this.scrubStart( e ) ) );

			// skimming, for a mouse
			let skimRaf = 0, skimX = 0;
			this.on( this.area, 'pointermove', ( e ) => {
				if ( e.pointerType !== 'mouse' || this.scrubbing ) {
					return;
				}
				skimX = e.clientX;
				if ( ! skimRaf ) {
					skimRaf = requestAnimationFrame( () => {
						skimRaf = 0;
						const r = this.area.getBoundingClientRect(), t = this.timeAt( skimX );
						this.skim.style.transform = `translateX(${ ( ( t - this.t0 ) / ( this.t1 - this.t0 ) ) * r.width }px)`;
						szwText( this.skim.firstChild, this.month( t ) );
						this.root.classList.add( 'is-skim' );
					} );
				}
			} );
			this.on( this.area, 'pointerleave', () => this.root.classList.remove( 'is-skim' ) );

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
			this.on( window, 'resize', () => {
				this.edges();
				this.near();
			}, { passive: true } );
			this.on( document, 'visibilitychange', () => document.hidden && this.playing && this.pause() );
		}

		keys( e, i, fromHead ) {
			const k = e.key, last = this.clips.length - 1;
			const to = k === 'ArrowRight' || k === 'ArrowDown' ? i + 1 : k === 'ArrowLeft' || k === 'ArrowUp' ? i - 1 : k === 'Home' ? 0 : k === 'End' ? last : null;
			if ( to !== null && to >= 0 && to <= last ) {
				e.preventDefault();
				this.pick( to, { focus: ! fromHead } );
			} else if ( fromHead && ( k === ' ' || k === 'Enter' ) && this.playBtn ) {
				e.preventDefault();
				this.playing ? this.pause() : this.play();
			}
		}

		edges() {
			const s = this.scroller;
			s.classList.toggle( 'is-end', s.scrollLeft >= s.scrollWidth - s.clientWidth - 2 );
		}

		/** Scroll a clip (or a point) to the middle of the visible timeline, right of the track headers. */
		center( i, instant ) {
			const c = this.clips[ i ];
			c && this.reveal( this.area.offsetLeft + c.offsetLeft + c.offsetWidth / 2, instant ? 'auto' : 'smooth' );
		}

		reveal( x, behavior ) {
			const s = this.scroller, max = s.scrollWidth - s.clientWidth;
			if ( max <= 0 ) {
				return;
			}
			const hw = this.heads ? this.heads.offsetWidth : 0;
			const left = Math.max( 0, Math.min( max, x - ( s.clientWidth + hw ) / 2 ) );
			if ( behavior === 'now' ) {
				s.scrollLeft = left;
			} else {
				s.scrollTo( { left, behavior: this.reduce ? 'auto' : behavior } );
			}
		}

		/* ---------------- picking a role ---------------- */

		pick( i, opts = {} ) {
			const n = this.clips.length;
			if ( ! opts.auto ) {
				this.stop( true );
			}
			// pinned: the page goes to the role's first frame, and the scroll does the rest
			if ( this.pin && this.pin.live && ! opts.auto && i >= 0 && i < n ) {
				this.pin.to( this.pinAt( i ), true );
				if ( opts.focus ) {
					this.clips[ i ].focus( { preventScroll: true } );
				}
				return;
			}
			if ( i < 0 || i >= n ) {
				return;
			}
			if ( i === this.cur ) {
				if ( ! opts.keepHead ) {
					this.setHead( this.span[ i ][ 0 ], { glide: true } );
				}
				return;
			}
			const was = this.cur, dir = i > was ? 1 : -1;
			this.cur = i;
			this.clips.forEach( ( c, k ) => {
				c.setAttribute( 'aria-selected', k === i ? 'true' : 'false' );
				c.tabIndex = k === i ? 0 : -1;
			} );
			if ( this.pos ) {
				szwText( this.pos, `${ i + 1 } ${ this.labels.of } ${ n }` );
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
			if ( ! opts.keepHead ) {
				this.setHead( this.span[ i ][ 0 ], { glide: true } );
			}
			if ( opts.focus ) {
				this.clips[ i ].focus( { preventScroll: true } );
			}
			if ( ! opts.keepView ) {
				this.center( i );
			}
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

		/* ---------------- scrubbing ---------------- */

		scrubStart( e ) {
			if ( e.button !== 0 || ! this.head ) {
				return;
			}
			e.preventDefault();
			this.stop( true );
			const el = e.currentTarget;
			el.setPointerCapture && el.setPointerCapture( e.pointerId );
			this.scrubbing = true;
			this.root.classList.add( 'is-scrub' );
			this.root.classList.remove( 'is-skim' );
			this.head.getAnimations().forEach( ( a ) => a.cancel() );
			let last = null;
			const move = ( ev ) => {
				const t = this.timeAt( ev.clientX );
				last = t;
				this.setHead( t, { label: this.month( t ) } );
				const i = this.roleAt( t );
				if ( i !== this.cur ) {
					this.pick( i, { keepHead: true, keepView: true, auto: true } );
				}
			};
			const up = () => {
				el.removeEventListener( 'pointermove', move );
				el.removeEventListener( 'pointerup', up );
				el.removeEventListener( 'pointercancel', up );
				this.scrubbing = false;
				this.root.classList.remove( 'is-scrub' );
				// pinned: the page takes the place let go, so the next scroll goes on from there
				if ( this.pin && this.pin.live && last !== null ) {
					const k = this.cur, n = this.clips.length, s = this.span[ k ][ 0 ];
					const e = k < n - 1 ? Math.max( s + 0.05, this.span[ k + 1 ][ 0 ] ) : Math.max( s + 0.05, this.tNow );
					this.pin.to( this.pinAt( k + Math.max( 0, Math.min( 0.999, ( last - s ) / ( e - s ) ) ) ), false );
					return;
				}
				// settle on the role's first frame
				this.setHead( this.span[ this.cur ][ 0 ], { glide: true } );
				this.center( this.cur );
			};
			el.addEventListener( 'pointermove', move );
			el.addEventListener( 'pointerup', up );
			el.addEventListener( 'pointercancel', up );
			move( e );
		}

		/* ---------------- play: the career as a reel ---------------- */

		play() {
			if ( ! this.head || this.playing ) {
				return;
			}
			if ( this.pin && this.pin.live ) {
				this.playPinned();
				return;
			}
			const last = this.clips.length - 1;
			if ( ! this.reel ) {
				this.reel = { k: this.cur === last ? 0 : this.cur, p: 0 };
			}
			this.playing = true;
			this.playBtn.setAttribute( 'aria-pressed', 'true' );
			szwText( this.playBtn.lastChild, this.labels.pause );
			this.pos && this.pos.setAttribute( 'aria-live', 'off' );
			this.pick( this.reel.k, { auto: true, keepHead: true, keepView: true } );
			let t0 = performance.now() - this.reel.p * DWELL, k = this.reel.k;
			const step = ( now ) => {
				if ( ! this.playing ) {
					return;
				}
				let p = ( now - t0 ) / DWELL;
				if ( p >= 1 ) {
					if ( k >= last ) {
						this.reel = null;
						this.stop( false );
						this.setHead( this.span[ this.cur ][ 0 ], { glide: true } );
						return;
					}
					k++;
					t0 = now;
					p = 0;
					this.pick( k, { auto: true, keepHead: true, keepView: true } );
				}
				this.reel = { k, p };
				const s = this.span[ k ][ 0 ], e = k < last ? Math.max( s + 0.05, this.span[ k + 1 ][ 0 ] ) : Math.max( s + 0.05, this.tNow );
				if ( this.reduce ) {
					this.setHead( s );
				} else {
					this.setHead( s + ( e - s ) * p );
					this.reveal( this.area.offsetLeft + this.head.offsetLeft, 'now' );
				}
				this.raf = requestAnimationFrame( step );
			};
			this.raf = requestAnimationFrame( step );
		}

		pause() {
			this.stop( false );
		}

		/** Stop playing; `reset` forgets where the reel was (the visitor took over). */
		stop( reset ) {
			if ( reset ) {
				this.reel = null;
			}
			if ( ! this.playing ) {
				return;
			}
			this.playing = false;
			cancelAnimationFrame( this.raf );
			if ( this.unhand ) {
				this.unhand();
				this.unhand = null;
			}
			this.playBtn.setAttribute( 'aria-pressed', 'false' );
			szwText( this.playBtn.lastChild, this.labels.play );
			this.pos && this.pos.setAttribute( 'aria-live', 'polite' );
			if ( reset && ! ( this.pin && this.pin.live ) ) {
				this.setHead( this.span[ this.cur ][ 0 ], { label: null } );
			}
		}

		/* ---------------- arrival: "Now" runs to today ---------------- */

		watch() {
			if ( ! ( 'IntersectionObserver' in window ) ) {
				this.root.classList.add( 'is-seen' );
				return;
			}
			const r = this.root.getBoundingClientRect();
			const away = r.top >= window.innerHeight || r.bottom <= 0;
			const arrive = this.root.dataset.arrive === '1' && away && ! this.reduce && ! this.editor && this.now && this.now.animate;
			if ( arrive ) {
				this.root.classList.add( 'is-armed' );
				if ( this.scroller.scrollWidth > this.scroller.clientWidth ) {
					this.scroller.scrollLeft = 0;
				}
			}
			let first = true;
			this.io = new IntersectionObserver( ( es ) => {
				const e = es[ es.length - 1 ], seen = e.isIntersecting && e.intersectionRatio >= 0.35;
				if ( ! seen ) {
					// off screen: a playing reel waits
					if ( ! e.isIntersecting && this.playing ) {
						this.pause();
					}
					return;
				}
				if ( first ) {
					first = false;
					this.root.classList.add( 'is-seen' );
					if ( arrive ) {
						this.arrive();
					}
				}
			}, { threshold: [ 0, 0.35 ] } );
			this.io.observe( this.root );
		}

		arrive() {
			const x1 = this.now.offsetLeft;
			const x0 = Math.min( ...this.clips.map( ( c ) => c.offsetLeft ) );
			const span = Math.max( 1, x1 - x0 );
			// about 1.6s for the whole run, a little longer on wide timelines
			const D = Math.round( Math.min( 2200, Math.max( 1300, span * 1.9 ) ) );
			this.root.classList.remove( 'is-armed' );

			this.now.animate(
				[ { transform: `translateX(${ x0 - x1 }px)`, opacity: 0 }, { opacity: 1, offset: 0.06 }, { transform: 'none', opacity: 1 } ],
				{ duration: D, easing: 'linear', fill: 'backwards' }
			);
			this.clips.forEach( ( c ) => {
				const a = c.offsetLeft - x0, w = c.offsetWidth;
				c.animate(
					[ { clipPath: 'inset(-6px calc(100% + 6px) -6px -6px round 14px)' }, { clipPath: 'inset(-6px -6px -6px -6px round 14px)' } ],
					{ duration: Math.max( 120, ( w / span ) * D ), delay: ( a / span ) * D, easing: 'linear', fill: 'backwards' }
				);
			} );
			this.root.querySelectorAll( '.sz-cr__ruler span' ).forEach( ( s, k ) => {
				s.animate( [ { opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' } ], { duration: 360, delay: k * 50, easing: EASE, fill: 'backwards' } );
			} );
			// then the playhead drops onto the current role
			this.head && this.head.animate( [ { opacity: 0, transform: 'translateY(-10px)' }, { opacity: 1, transform: 'none' } ], { duration: 480, delay: D + 80, easing: SPRING, fill: 'backwards' } );

			// a narrow timeline pans along with "Now" (until a hand takes it)
			const s = this.scroller;
			if ( s.scrollWidth > s.clientWidth ) {
				this.panning = true;
				const start = performance.now();
				const step = ( now ) => {
					if ( ! this.panning ) {
						return;
					}
					const p = Math.min( 1, ( now - start ) / D );
					this.reveal( this.area.offsetLeft + x0 + span * p, 'now' );
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
