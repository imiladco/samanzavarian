/**
 * Scene: Post-production.
 *
 * By hand (always — also with reduced motion): the sliders are real range
 * inputs. Drag anywhere on a track, or use the keyboard; the number and the
 * grade on the clips (and the preview image) follow.
 *
 * On its own (only while the panel is live; "Play once" does one export
 * pass, lets the sliders drift home and rests, "Keep playing" repeats):
 *   • sliders drift slowly around their starting value, as if someone were
 *     grading — never the one a visitor is holding or just let go of;
 *   • the playhead runs across the timeline as the export's progress
 *     ("Exporting 46%"), clips get trimmed on the way, then "Final export
 *     ready" pops in, holds, and the edit rewinds for the next pass.
 *
 * The grade is measured from each slider's starting value, so at rest the
 * clips are exactly the design's colours. Settings come in data-szs-post.
 */
( function () {
	'use strict';

	const clamp = ( v, a, b ) => Math.max( a, Math.min( b, v ) );

	/** d in [-1, 1]: how far from the start, in half-ranges. */
	const EFFECTS = {
		warmth: ( d ) => ( d >= 0
			? `sepia(${ ( 0.42 * d ).toFixed( 3 ) }) saturate(${ ( 1 + 0.3 * d ).toFixed( 3 ) }) hue-rotate(${ ( -8 * d ).toFixed( 1 ) }deg)`
			: `hue-rotate(${ ( -24 * d ).toFixed( 1 ) }deg) saturate(${ ( 1 + 0.18 * d ).toFixed( 3 ) })` ),
		shadows: ( d ) => `brightness(${ ( 1 + 0.22 * d ).toFixed( 3 ) }) contrast(${ ( 1 - 0.14 * d ).toFixed( 3 ) })`,
		exposure: ( d ) => `brightness(${ ( 1 + 0.4 * d ).toFixed( 3 ) })`,
		contrast: ( d ) => `contrast(${ ( 1 + 0.5 * d ).toFixed( 3 ) })`,
		saturation: ( d ) => `saturate(${ ( 1 + 0.9 * d ).toFixed( 3 ) })`,
	};

	const signed = ( n ) => ( n > 0 ? '+' + n : n < 0 ? '−' + Math.abs( n ) : '0' );

	function create( scene, api ) {
		const grade = scene.querySelector( '[data-szs-post]' );
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

		/* ---------------- sliders ---------------- */

		const sliders = Array.from( grade.querySelectorAll( '.szs-grade__input' ) ).map( ( input, i ) => ( {
			input,
			track: input.parentElement,
			knob: input.parentElement.querySelector( '.szs-grade__knob' ),
			width: input.parentElement.clientWidth,
			out: grade.querySelectorAll( '.szs-grade__val' )[ i ],
			min: +input.min,
			max: +input.max,
			start: +input.value,
			effect: input.dataset.effect,
			seed: 1.7 + i * 2.3,
			cur: +input.value, // drift's own position, fractional, so motion is smooth
			held: false,
			touched: 0, // last time a visitor moved it
		} ) );

		function applyGrade() {
			const parts = [];
			sliders.forEach( ( s ) => {
				const fx = EFFECTS[ s.effect ];
				const half = ( s.max - s.min ) / 2;
				const d = clamp( ( +s.input.value - s.start ) / half, -1, 1 );
				if ( fx && Math.abs( d ) > 0.001 ) {
					parts.push( fx( d ) );
				}
			} );
			scene.style.setProperty( '--szs-grade', parts.length ? parts.join( ' ' ) : 'none' );
		}

		/*
		 * The knob keeps its printed place (the start value) in the layout and
		 * moves by `translate` — no layout per frame, no layout shift.
		 */
		function knob( s ) {
			const span = s.max - s.min;
			const dx = ( ( +s.input.value - s.start ) / span ) * s.width;
			s.knob.style.translate = Math.abs( dx ) < 0.01 ? '' : dx.toFixed( 2 ) + 'px 0';
		}

		function set( s, value, fromDrift ) {
			if ( ! fromDrift ) {
				s.cur = clamp( value, s.min, s.max );
			}
			const v = Math.round( clamp( value, s.min, s.max ) );
			if ( String( v ) !== s.input.value ) {
				s.input.value = v;
			}
			knob( s );
			s.input.setAttribute( 'aria-valuetext', signed( v ) );
			if ( s.out ) {
				s.out.textContent = signed( v );
			}
			applyGrade();
		}

		sliders.forEach( ( s ) => {
			const fromPointer = ( e ) => {
				const r = s.track.getBoundingClientRect();
				set( s, s.min + clamp( ( e.clientX - r.left ) / r.width, 0, 1 ) * ( s.max - s.min ) );
			};

			s.track.addEventListener( 'pointerdown', ( e ) => {
				if ( e.button > 0 ) {
					return;
				}
				e.preventDefault();
				s.held = true;
				s.track.classList.add( 'is-held' );
				s.track.setPointerCapture( e.pointerId );
				s.input.focus( { preventScroll: true } );
				fromPointer( e );
			} );
			s.track.addEventListener( 'pointermove', ( e ) => s.held && fromPointer( e ) );
			const release = () => {
				s.held = false;
				s.touched = performance.now();
				s.track.classList.remove( 'is-held' );
			};
			s.track.addEventListener( 'pointerup', release );
			s.track.addEventListener( 'pointercancel', release );

			// keyboard and assistive tech change the input itself
			s.input.addEventListener( 'input', () => {
				s.touched = performance.now();
				set( s, +s.input.value );
			} );
		} );

		/* ---------------- timeline ---------------- */

		const tl = scene.querySelector( '.szs-tl' );
		const head = tl && tl.querySelector( '.szs-tl__head' );
		const rows = tl ? Array.from( tl.querySelectorAll( '.szs-tl__row' ) ).map( ( r ) => Array.from( r.children ) ) : [];
		const lengths = rows.map( ( clips ) => clips.map( ( c ) => parseFloat( c.style.flexGrow ) || 1 ) );
		const pill = scene.querySelector( '.szs-pill' );
		const pillText = pill && pill.querySelector( '.szs-pill__text' );
		let tlWidth = tl ? tl.clientWidth : 0;

		// sizes, read once and on resize — never in the animation loop
		if ( 'ResizeObserver' in window ) {
			const ro = new ResizeObserver( () => {
				sliders.forEach( ( s ) => {
					s.width = s.track.clientWidth;
					knob( s );
				} );
				tlWidth = tl ? tl.clientWidth : 0;
			} );
			sliders.forEach( ( s ) => ro.observe( s.track ) );
			if ( tl ) {
				ro.observe( tl );
			}
		}

		/**
		 * Playhead at h (0–1) of the clip area you can see: inside the 16px
		 * padding and short of the 30px the card hangs past the panel. It rests
		 * at the design's 46%, and moves from there with `translate`.
		 */
		function headAt( h ) {
			if ( head ) {
				const x = 16 + h * ( tlWidth - 62 ) - 0.46 * tlWidth;
				head.style.translate = x.toFixed( 1 ) + 'px 0';
			}
		}

		function lengthsBack() {
			rows.forEach( ( clips, r ) => clips.forEach( ( c, k ) => {
				c.style.flexGrow = lengths[ r ][ k ];
			} ) );
		}

		/** One editing gesture: a cut moves — one clip grows, its neighbour shrinks. */
		function trim() {
			const options = [];
			rows.forEach( ( clips, r ) => clips.forEach( ( c, k ) => {
				if ( k < clips.length - 1 ) {
					options.push( [ r, k ] );
				}
			} ) );
			if ( ! options.length ) {
				return;
			}
			const [ r, k ] = options[ Math.floor( Math.random() * options.length ) ];
			const a = rows[ r ][ k ];
			const b = rows[ r ][ k + 1 ];
			const ga = parseFloat( a.style.flexGrow ) || 1;
			const gb = parseFloat( b.style.flexGrow ) || 1;
			const step = Math.random() < 0.5 && ga > 1 ? -1 : gb > 1 ? 1 : 0;
			a.style.flexGrow = ga + step;
			b.style.flexGrow = gb - step;
		}

		function pillState( working, p ) {
			if ( ! pill ) {
				return;
			}
			pill.classList.toggle( 'is-working', working );
			pillText.textContent = working ? cfg.working.replace( '{p}', String( p ) ) : cfg.done;
		}

		/* ---------------- the loop ---------------- */

		let raf = 0;
		let last = 0;
		let running = false;
		let phase = 'run';
		let phaseAt = 0;
		let cuts = [];
		let settling = false; // "play once": drift home, then rest
		let hinted = false;

		function startRun( now ) {
			phase = 'run';
			phaseAt = now;
			cuts = [ 0.28 + Math.random() * 0.1, 0.6 + Math.random() * 0.12 ];
			tl.classList.remove( 'is-rewinding' );
		}

		function tick( now ) {
			if ( ! running ) {
				return;
			}

			const dt = last ? Math.min( 64, now - last ) : 16;
			last = now;

			if ( cfg.drift ) {
				sliders.forEach( ( s ) => {
					if ( s.held || ( s.touched && now - s.touched < 3500 ) ) {
						return;
					}
					// Two slow waves make an unhurried, never-repeating hand; the
					// value eases toward them, so it glides from wherever it is —
					// the design's start, or where a visitor left it.
					const half = ( s.max - s.min ) / 2;
					const wave = ( Math.sin( now * 0.00042 + s.seed ) + 0.55 * Math.sin( now * 0.00113 + s.seed * 2.1 ) ) / 1.55;
					const target = settling ? s.start : s.start + wave * half * 0.32;
					s.cur += ( target - s.cur ) * Math.min( 1, dt * 0.0016 );
					if ( Math.round( s.cur ) !== +s.input.value ) {
						set( s, s.cur, true );
					}
				} );
			}

			if ( tl ) {
				const t = now - phaseAt;
				if ( 'run' === phase ) {
					const h = clamp( t / cfg.run, 0, 1 );
					headAt( h );
					pillState( true, Math.round( h * 100 ) );
					while ( cuts.length && h >= cuts[ 0 ] ) {
						cuts.shift();
						trim();
					}
					if ( h >= 1 ) {
						phase = 'done';
						phaseAt = now;
						pillState( false );
						if ( pill ) {
							pill.classList.remove( 'is-pop' );
							void pill.offsetWidth;
							pill.classList.add( 'is-pop' );
						}
					}
				} else if ( 'done' === phase && 'loop' !== api.mode ) {
					// One pass told the story: sliders drift home, the playhead
					// glides back to the design's spot, and the scene rests.
					if ( ! settling ) {
						settling = true;
						tl.classList.add( 'is-rewinding' );
						head.style.translate = '0px 0'; // the design's spot
						lengthsBack();
					}
					const home = sliders.every( ( s ) => s.touched || Math.round( s.cur ) === s.start );
					if ( t > 1800 && home ) {
						running = false;
						rest();
						api.done();
						return;
					}
				} else if ( 'done' === phase && t > 2400 ) {
					// rewind: the head glides back, the cuts go back to the edit
					phase = 'rewind';
					phaseAt = now;
					tl.classList.add( 'is-rewinding' );
					headAt( 0 );
					lengthsBack();
				} else if ( 'rewind' === phase && t > 700 ) {
					startRun( now );
				}
			}

			raf = requestAnimationFrame( tick );
		}

		function rest() {
			if ( tl ) {
				tl.classList.remove( 'is-playing', 'is-rewinding' );
				if ( head ) {
					head.style.translate = '';
				}
				lengthsBack();
			}
			pillState( false );
		}

		// Server values may come before the script; settle the knobs once.
		sliders.forEach( ( s ) => set( s, +s.input.value ) );

		return {
			play() {
				if ( running ) {
					return;
				}
				running = true;
				last = 0;
				settling = false;
				// The first time, show that the knobs can be dragged: two pulses.
				if ( ! hinted ) {
					hinted = true;
					grade.classList.add( 'is-hint' );
					setTimeout( () => grade.classList.remove( 'is-hint' ), 2600 );
				}
				if ( tl ) {
					tl.classList.add( 'is-playing' );
					startRun( performance.now() );
				}
				raf = requestAnimationFrame( tick );
			},
			pause() {
				if ( ! running ) {
					return;
				}
				running = false;
				cancelAnimationFrame( raf );
				// Back to the design's resting timeline. A visitor's grade stays.
				rest();
			},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'post', create );
	}
}() );
