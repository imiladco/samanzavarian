/**
 * Scene: Post-production — an editor at work.
 *
 * By hand (always, also with reduced motion or "Still"):
 *   • the sliders are real range inputs: drag or use the keyboard; the number
 *     and the grade of the video clips follow;
 *   • the timeline can be scrubbed: the playhead follows the hand.
 *
 * On its own (while the panel is live):
 *   • the playhead is a real transport — play (1×), 2×, 4×, rewind, stop —
 *     shown next to the timecode, and the timecode is always exactly where
 *     the playhead is (hh:mm:ss:ff, at the chosen frame rate);
 *   • the view behaves like an editor's hand on the scroll: sometimes the
 *     playhead travels and the view pages on, sometimes the playhead holds
 *     and the timeline runs under it, sometimes both move, and now and then
 *     the editor scrolls ahead to look, then back;
 *   • edits happen at the playhead and are reviewed: back up, play the cut
 *     at 1×. Cut, trim (the head rides the edit point, the rest ripples
 *     closer, a snap line shows where), insert, skim at 4×; random, never
 *     the same twice in a row. New clips appear as the timeline moves on,
 *     so no stretch is ever the same;
 *   • every few edits the cut is exported: "Exporting v2 · 37%", then
 *     "Final export ready · v2". "Play once" rests there.
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

	const clamp = ( v, a, b ) => Math.max( a, Math.min( b, v ) );
	const rnd = ( a, b ) => a + Math.random() * ( b - a );
	const ri = ( a, b ) => Math.floor( rnd( a, b + 1 ) );
	const pick = ( a ) => a[ Math.floor( Math.random() * a.length ) ];
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	const signed = ( n ) => ( n > 0 ? '+' + n : n < 0 ? '−' + Math.abs( n ) : '0' );

	/** d in [-1, 1]: how far a slider is from its start, in half-ranges. */
	const EFFECTS = {
		warmth: ( d ) => ( d >= 0
			? `sepia(${ ( 0.42 * d ).toFixed( 3 ) }) saturate(${ ( 1 + 0.3 * d ).toFixed( 3 ) }) hue-rotate(${ ( -8 * d ).toFixed( 1 ) }deg)`
			: `hue-rotate(${ ( -24 * d ).toFixed( 1 ) }deg) saturate(${ ( 1 + 0.18 * d ).toFixed( 3 ) })` ),
		shadows: ( d ) => `brightness(${ ( 1 + 0.22 * d ).toFixed( 3 ) }) contrast(${ ( 1 - 0.14 * d ).toFixed( 3 ) })`,
		exposure: ( d ) => `brightness(${ ( 1 + 0.4 * d ).toFixed( 3 ) })`,
		contrast: ( d ) => `contrast(${ ( 1 + 0.5 * d ).toFixed( 3 ) })`,
		saturation: ( d ) => `saturate(${ ( 1 + 0.9 * d ).toFixed( 3 ) })`,
	};

	function create( root, api ) {
		const grade = root.querySelector( '[data-szs-post]' );
		const noop = { play() {}, pause() {} };
		if ( ! grade ) {
			return noop;
		}
		let cfg;
		try {
			cfg = JSON.parse( grade.dataset.szsPost );
		} catch ( e ) {
			return noop;
		}

		const tl = root.querySelector( '.szs-pe__tl' );
		const lanesEl = tl.querySelector( '.szs-pe__lanes' );
		const timeEl = tl.querySelector( '.szs-pe__time' );
		const modeEl = tl.querySelector( '.szs-pe__mode' );
		const headEl = tl.querySelector( '.szs-pe__head' );
		const pill = root.querySelector( '.szs-pe__pill' );
		const pillText = pill.querySelector( 'span' );
		const L = cfg.labels;
		const FPS = cfg.fps;
		const PPS = cfg.pps;
		const VIEW = tl.offsetWidth || 640;
		const HEAD_MIN = 175;
		const HEAD_MAX = 500;
		const PAGE_TO = 210;

		/* ================= sliders ================= */

		const sliders = Array.from( grade.querySelectorAll( '.szs-pe__input' ) ).map( ( input, i ) => ( {
			input,
			track: input.parentElement,
			knob: input.parentElement.querySelector( '.szs-pe__knob' ),
			width: input.parentElement.clientWidth,
			out: grade.querySelectorAll( '.szs-pe__val' )[ i ],
			min: +input.min,
			max: +input.max,
			start: +input.value,
			effect: input.dataset.effect,
			cur: +input.value,
			to: +input.value,
			next: 0,
			held: false,
			touched: -1e9,
		} ) );

		function applyGrade() {
			const parts = [];
			sliders.forEach( ( s ) => {
				const fx = EFFECTS[ s.effect ];
				const d = clamp( ( +s.input.value - s.start ) / ( ( s.max - s.min ) / 2 ), -1, 1 );
				if ( fx && Math.abs( d ) > 0.001 ) {
					parts.push( fx( d ) );
				}
			} );
			lanesEl.style.setProperty( '--pg', parts.length ? parts.join( ' ' ) : 'none' );
		}
		/* The knob keeps its printed place and moves by `translate`: no layout per frame. */
		function set( s, value ) {
			const v = Math.round( clamp( value, s.min, s.max ) );
			if ( String( v ) !== s.input.value ) {
				s.input.value = v;
			}
			const dx = ( ( value - s.start ) / ( s.max - s.min ) ) * s.width;
			s.knob.style.translate = Math.abs( dx ) < 0.01 ? '' : dx.toFixed( 2 ) + 'px 0';
			s.input.setAttribute( 'aria-valuetext', signed( v ) );
			if ( s.out ) {
				szwText( s.out, signed( v ) );
			}
			applyGrade();
		}
		sliders.forEach( ( s ) => {
			const fromPointer = ( e ) => {
				const r = s.track.getBoundingClientRect();
				s.cur = s.to = s.min + clamp( ( e.clientX - r.left ) / r.width, 0, 1 ) * ( s.max - s.min );
				set( s, s.cur );
			};
			s.track.addEventListener( 'pointerdown', ( e ) => {
				if ( e.button > 0 ) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				s.held = true;
				s.track.classList.add( 'is-held' );
				s.track.setPointerCapture( e.pointerId );
				s.input.focus( { preventScroll: true } );
				fromPointer( e );
			} );
			s.track.addEventListener( 'pointermove', ( e ) => s.held && fromPointer( e ) );
			const release = () => {
				s.held = false;
				s.touched = api.time.t;
				s.track.classList.remove( 'is-held' );
			};
			s.track.addEventListener( 'pointerup', release );
			s.track.addEventListener( 'pointercancel', release );
			s.input.addEventListener( 'input', () => {
				s.touched = api.time.t;
				s.cur = s.to = +s.input.value;
				set( s, s.cur );
			} );
		} );
		if ( 'ResizeObserver' in window ) {
			const ro = new ResizeObserver( () => sliders.forEach( ( s ) => {
				s.width = s.track.clientWidth;
				set( s, s.cur );
			} ) );
			sliders.forEach( ( s ) => ro.observe( s.track ) );
		}

		/* ================= timeline ================= */

		const tc = ( sec ) => {
			sec = Math.max( 0, sec );
			const w = Math.floor( sec );
			return `${ pad( Math.floor( w / 3600 ) ) }:${ pad( Math.floor( w / 60 ) % 60 ) }:${ pad( w % 60 ) }:${ pad( Math.floor( ( sec % 1 ) * FPS + 1e-6 ) ) }`;
		};

		// The clips the server printed are where the edit carries on from.
		const LANES = Array.from( lanesEl.querySelectorAll( '.szs-pe__lane' ) ).map( ( el, i ) => {
			const clips = Array.from( el.children ).map( ( e ) => ( { e, x: parseFloat( e.style.translate ), w: parseFloat( e.style.width ) } ) );
			const last = clips[ clips.length - 1 ];
			return { el, clips, colors: cfg.lanes[ i ] || [ '#0D1B2A' ], audio: el.classList.contains( 'is-audio' ), gap: [ 0.22, 0.05, 0.1 ][ i ] || 0.1, cursor: last ? last.x + last.w + 5 : 0 };
		} );
		const ticks = Array.from( lanesEl.querySelectorAll( '.szs-pe__tick' ) ).map( ( e ) => ( { e, x: parseFloat( e.style.translate ) } ) );
		let tickAt = ticks.length ? Math.round( ( ticks[ ticks.length - 1 ].x / PPS ) * 2 ) + 1 : 0;

		let T = cfg.t0; // where the playhead is, in seconds
		let v = 0; // how fast it moves: 1, 2, 4, -2, 0
		let V = T - cfg.head / PPS; // the time at the view's left edge
		let paging = null;
		let maxT = T;
		let cam = { mode: 'page' };

		// A pool: an element that leaves the view is hidden and handed to the next clip, tick
		// or mark. Nothing is added to the page or taken out while the scene plays, so
		// Elementor (which re-measures the page on every such change) is never woken.
		const pools = new Map();
		const take = ( parent, cls ) => {
			const pool = pools.get( parent ) || [];
			let e = pool.pop();
			if ( e ) {
				// placed where it belongs at once: no slide in from where it last was
				e.className = cls + ' is-fresh';
				e.removeAttribute( 'style' );
				e.hidden = false;
				requestAnimationFrame( () => requestAnimationFrame( () => e.classList.remove( 'is-fresh' ) ) );
			} else {
				e = document.createElement( 'i' );
				e.className = cls;
				parent.appendChild( e );
			}
			pools.set( parent, pool );
			return e;
		};
		const drop = ( parent, e ) => {
			e.hidden = true;
			( pools.get( parent ) || pools.set( parent, [] ).get( parent ) ).push( e );
		};

		// Placed with `translate`, not `left`: edits move clips without moving layout.
		const place = ( c ) => {
			c.e.style.translate = c.x + 'px 0';
			c.e.style.width = c.w + 'px';
		};
		function addClip( L, x, w, extra, like ) {
			const e = take( L.el, 'szs-pe__clip' + ( L.audio ? ' is-audio' : '' ) + ( extra ? ' ' + extra : '' ) );
			if ( ! L.audio ) {
				e.style.backgroundColor = like ? like.e.style.backgroundColor : pick( L.colors );
			}
			const c = { x, w, e };
			place( c );
			L.clips.push( c );
			L.clips.sort( ( p, q ) => p.x - q.x );
			return c;
		}
		function fill() {
			const right = V * PPS + VIEW + 260;
			const left = V * PPS - 420;
			for ( const L of LANES ) {
				while ( L.cursor < right ) {
					if ( Math.random() < L.gap ) {
						L.cursor += ri( 18, 60 );
					}
					const w = ri( 52, 210 );
					addClip( L, L.cursor, w );
					L.cursor += w + 5;
				}
				L.clips = L.clips.filter( ( c ) => ( c.x + c.w < left ? ( drop( L.el, c.e ), false ) : true ) );
			}
			while ( tickAt / 2 < ( right + 200 ) / PPS ) {
				const t = tickAt / 2;
				const whole = tickAt % 2 === 0;
				const e = take( lanesEl, 'szs-pe__tick' + ( whole ? ' is-s' : '' ) );
				// every tick keeps its label element; only the text changes (empty when unlabelled)
				let b = e.firstChild;
				if ( ! b ) {
					b = document.createElement( 'b' );
					e.appendChild( b );
				}
				if ( ! b.firstChild ) {
					b.appendChild( document.createTextNode( '' ) ); // the server's empty label
				}
				const label = whole && tickAt % 4 === 0 ? `${ pad( Math.floor( t / 60 ) % 60 ) }:${ pad( t % 60 ) }` : '';
				b.firstChild.data = label;
				b.hidden = ! label;
				e.style.translate = t * PPS + 'px 0';
				ticks.push( { x: t * PPS, e } );
				tickAt++;
			}
			while ( ticks.length && ticks[ 0 ].x < left - 60 ) {
				drop( lanesEl, ticks.shift().e );
			}
		}

		/* The view: follow, page, drift or look; at an edge it glides a page on. */
		function setCam( mode, extra ) {
			const hx = ( T - V ) * PPS;
			cam = Object.assign( { mode, anchor: clamp( hx, HEAD_MIN + 20, HEAD_MAX - 60 ), rate: rnd( 0.35, 0.6 ) }, extra );
		}
		function draw( dt ) {
			if ( ! paging ) {
				if ( 'follow' === cam.mode ) {
					V += ( T - cam.anchor / PPS - V ) * Math.min( 1, dt / 280 );
				} else if ( 'drift' === cam.mode ) {
					V += ( v * cam.rate * dt ) / 1000;
				} else if ( 'look' === cam.mode ) {
					V += ( cam.to - V ) * Math.min( 1, dt / 420 );
				}
			}
			const hx0 = ( T - V ) * PPS;
			if ( ! paging && 'look' !== cam.mode && ( hx0 > HEAD_MAX || hx0 < HEAD_MIN - 40 ) ) {
				paging = { from: V, t0: api.time.t, back: hx0 < HEAD_MIN - 40 };
			}
			if ( paging ) {
				const k = Math.min( 1, ( api.time.t - paging.t0 ) / 620 );
				const e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow( -2 * k + 2, 3 ) / 2;
				V = paging.from + ( T - ( paging.back ? HEAD_MAX - 60 : PAGE_TO ) / PPS - paging.from ) * e;
				if ( k >= 1 ) {
					paging = null;
				}
			}
			if ( 'look' !== cam.mode ) {
				V = clamp( V, T - ( HEAD_MAX + 60 ) / PPS, T - 40 / PPS );
			}
			maxT = Math.max( maxT, T );
			fill();
			lanesEl.style.transform = `translateX(${ ( -V * PPS ).toFixed( 2 ) }px)`;
			headEl.style.transform = `translateX(${ ( ( T - V ) * PPS ).toFixed( 2 ) }px)`;
			szwText( timeEl, tc( T ) );
			const hx = T * PPS;
			for ( const L of LANES ) {
				for ( const c of L.clips ) {
					c.e.classList.toggle( 'is-on', c.x <= hx && hx < c.x + c.w );
				}
			}
		}
		function go( speed ) {
			v = speed;
			modeEl.dataset.m = 0 === v ? 'stop' : v < 0 ? 'rew' : v > 1 ? 'ff' : 'play';
			szwText( modeEl.lastChild, v ? Math.abs( v ) + '×' : '' );
		}

		/* Visitors can scrub: the playhead follows the hand, and play carries on from there. */
		let scrubX = null;
		let held = 0;
		tl.addEventListener( 'pointerdown', ( e ) => {
			if ( e.button > 0 ) {
				return;
			}
			scrubX = e.clientX;
			held = v;
			go( 0 );
			tl.setPointerCapture( e.pointerId );
			tl.classList.add( 'is-scrub', 'was-scrubbed' );
		} );
		tl.addEventListener( 'pointermove', ( e ) => {
			if ( null === scrubX ) {
				return;
			}
			const r = tl.getBoundingClientRect();
			const k = r.width / VIEW || 1; // the stage may be scaled
			T = Math.max( T + ( e.clientX - scrubX ) / k / PPS, maxT - 8, V - 2 );
			scrubX = e.clientX;
			draw( 0 );
		} );
		const endScrub = () => {
			if ( null === scrubX ) {
				return;
			}
			scrubX = null;
			tl.classList.remove( 'is-scrub' );
			go( held );
		};
		tl.addEventListener( 'pointerup', endScrub );
		tl.addEventListener( 'pointercancel', endScrub );

		api.time.frame( ( dt ) => {
			if ( null === scrubX ) {
				T += ( v * dt ) / 1000;
			}
			draw( dt );
			if ( ! cfg.drift ) {
				return;
			}
			for ( const s of sliders ) {
				if ( s.held || api.time.t - s.touched < 5000 ) {
					continue;
				}
				if ( api.time.t >= s.next ) {
					const span = s.max - s.min;
					s.to = clamp( s.start + rnd( -0.36, 0.36 ) * span, s.min, s.max );
					s.next = api.time.t + rnd( 2600, 4200 );
				}
				s.cur += ( s.to - s.cur ) * Math.min( 1, dt / 420 );
				set( s, s.cur );
			}
		} );

		/* ================= the editor ================= */

		const wait = ( ms ) => api.time.wait( ms );
		const run = async ( speed, ms ) => {
			go( speed );
			await wait( ms );
		};
		/** Glide the playhead to a time: a snap to an edit point. */
		async function seek( to, ms ) {
			go( 0 );
			const from = T;
			const t0 = api.time.t;
			headEl.classList.add( 'is-snap' );
			while ( api.time.t - t0 < ms ) {
				const k = ( api.time.t - t0 ) / ms;
				T = from + ( to - from ) * ( 1 - Math.pow( 1 - k, 3 ) );
				await wait( 16 );
			}
			T = to;
			headEl.classList.remove( 'is-snap' );
		}
		const video = LANES.filter( ( L ) => ! L.audio );
		const under = ( L ) => L.clips.find( ( c ) => c.x + 24 < T * PPS && T * PPS < c.x + c.w - 24 );
		function split( L, c, x ) {
			const end = c.x + c.w;
			c.w = x - c.x - 2;
			place( c );
			return addClip( L, x + 2, end - x - 2, '', c ); // the same shot: same colour
		}
		/** The one time it plays at 1×: watching the cut. */
		async function review( back ) {
			await run( -2, ( back / 2 ) * 1000 );
			await run( 0, 140 );
			await run( 1, back * 1000 + 700 );
		}

		let version = 2;
		let edits = 0;
		const label = ( t, p ) => t.replace( '{v}', version ).replace( '{p}', p );
		const state = ( text, cls ) => {
			pill.className = 'szs-pe__pill' + ( cls ? ' ' + cls : '' );
			szwText( pillText, text );
		};
		let exported = false;
		async function edited() {
			edits++;
			state( label( L.post_editing, 0 ), 'is-edit' );
			if ( edits % cfg.every ) {
				return;
			}
			await run( 0, 300 ); // every few edits, a new version goes out
			for ( let p = 0; p <= 100; p += 5 ) {
				state( label( L.post_exporting, p ), 'is-export' );
				await wait( 55 );
			}
			state( label( L.post_done, 100 ), '' );
			version++;
			exported = true;
		}

		const ACTIONS = {
			async blade() {
				const lane = pick( video.filter( under ) );
				if ( ! lane ) {
					return;
				}
				await run( 0, 160 );
				await run( -2, 600 ); // back up to the moment
				await run( 0, 180 );
				const c = under( lane );
				if ( ! c ) {
					return;
				}
				const x = T * PPS;
				const r = take( lane.el, 'szs-pe__razor' );
				r.style.translate = x + 'px 0';
				void r.offsetWidth;
				r.classList.add( 'is-on' );
				await wait( 300 );
				split( lane, c, x );
				r.style.opacity = '0';
				setTimeout( () => drop( lane.el, r ), 300 );
				await edited();
				await review( 1.4 );
			},
			async trim() {
				const lane = pick( video );
				const hx = T * PPS;
				const c = lane.clips
					.filter( ( o ) => o.w > 110 && Math.abs( o.x + o.w - hx ) < 3 * PPS && o.x + o.w > V * PPS + HEAD_MIN + 20 )
					.sort( ( a, b ) => Math.abs( a.x + a.w - hx ) - Math.abs( b.x + b.w - hx ) )[ 0 ];
				if ( ! c ) {
					return;
				}
				await run( 0, 140 );
				await seek( ( c.x + c.w ) / PPS, 220 ); // snap to the edit point
				c.e.classList.add( 'is-sel' );
				await wait( 200 );
				const d = ri( 20, 46 );
				c.w -= d;
				place( c );
				const snap = take( lane.el, 'szs-pe__snap' );
				snap.style.translate = c.x + c.w + 2.5 + 'px 0';
				await seek( ( c.x + c.w ) / PPS, 480 ); // the head rides the edit point
				lane.clips.filter( ( o ) => o.x > c.x ).forEach( ( o ) => {
					o.x -= d;
					place( o );
				} );
				lane.cursor -= d;
				snap.classList.add( 'is-on' );
				await wait( 380 );
				snap.classList.remove( 'is-on' );
				setTimeout( () => drop( lane.el, snap ), 250 );
				c.e.classList.remove( 'is-sel' );
				await edited();
				await review( 1.6 );
			},
			async insert() {
				const lane = video[ video.length - 1 ];
				if ( ! lane ) {
					return;
				}
				await run( 0, 160 );
				const x = T * PPS;
				const c = under( lane );
				if ( c ) {
					split( lane, c, x );
				}
				const w = ri( 50, 84 );
				lane.clips.filter( ( o ) => o.x >= x ).forEach( ( o ) => {
					o.x += w + 4;
					place( o );
				} );
				lane.cursor += w + 4;
				await wait( 520 );
				const n = addClip( lane, x + 2, w, 'is-drop is-sel' );
				await wait( 650 );
				n.e.classList.remove( 'is-sel', 'is-drop' );
				await edited();
				await review( 1.2 );
			},
			async skim() {
				setCam( pick( [ 'follow', 'drift' ] ) );
				await run( 4, ri( 1000, 1600 ) ); // a stretch that is already fine
			},
			async look() {
				await run( 0, 150 );
				setCam( 'look', { to: V + rnd( 4, 6 ) } ); // scroll ahead to see what is coming
				await wait( 1100 );
				setCam( 'look', { to: T - 240 / PPS } ); // and back to the playhead
				await wait( 800 );
				setCam( 'page' );
			},
		};

		/** Random, never the same twice in a row, every one used before any repeats. */
		let bag = [];
		let lastAction = '';
		const nextAction = () => {
			if ( ! bag.length ) {
				bag = cfg.edits.slice().sort( () => Math.random() - 0.5 );
				if ( bag.length > 1 && bag[ bag.length - 1 ] === lastAction ) {
					bag.unshift( bag.pop() );
				}
			}
			lastAction = bag.pop();
			return lastAction;
		};
		const cruise = () => ( 'real' === cfg.pace ? ( Math.random() < 0.8 ? 1 : 2 ) : ( Math.random() < 0.75 ? 2 : 1 ) );

		let started = false;
		async function story() {
			await run( cruise(), 1400 );
			for ( ;; ) {
				if ( cfg.edits.length ) {
					setCam( 'page' ); // edits happen on a still view
					await ACTIONS[ nextAction() ]();
				}
				// "Play once" rests after the first export (or a while of playing, with no edits chosen)
				if ( ( exported || ! cfg.edits.length ) && 'loop' !== api.mode ) {
					go( 0 );
					api.done();
					return;
				}
				setCam( pick( [ 'follow', 'page', 'drift', 'follow' ] ) );
				await run( cruise(), rnd( 1600, 2800 ) ); // and on
			}
		}

		draw( 0 );

		return {
			play() {
				if ( ! started ) {
					started = true;
					story();
				}
			},
			pause() {},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'post', create );
	}
}() );
