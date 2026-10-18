/**
 * Scene: Creative direction — from chaos to direction.
 *
 * Each board:
 *   1. references land on the board in a loose pile (the chosen three among them);
 *   2. a pointer picks the three, one by one (they get the orange ring);
 *   3. the others are cleared away;
 *   4. the chosen three glide into the design's composition;
 *   5. the palette's colours fly out of the pictures into the swatch;
 *   6. the direction note is written.
 * Then the board is put away and the next one starts. "Play once" goes
 * through the boards once and rests on the last; "Keep playing" cycles.
 *
 * The server prints board one finished (the design); that shows without
 * JavaScript, with reduced motion and whenever the scene is paused.
 */
( function () {
	'use strict';

	const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

	// The pile: spots as % of the art box (left, top), and a tilt for each.
	const SPOTS = [ [ 6, 16 ], [ 31, 7 ], [ 55, 12 ], [ 73, 26 ], [ 15, 47 ], [ 41, 41 ], [ 63, 50 ], [ 27, 27 ] ];
	const TILTS = [ -12, 8, -5, 13, 6, -9, 11, -3 ];

	const POINTER = '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 2l15 11-6.5 1.2L16 21l-3 1.4-3.4-6.6L4 20z" fill="#0D1B2A" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';

	function create( scene, api ) {
		const swatch = scene.querySelector( '[data-szs-direction]' );
		const noop = { play() {}, pause() {} };

		if ( ! swatch ) {
			return noop;
		}

		let cfg;
		try {
			cfg = JSON.parse( swatch.dataset.szsDirection );
		} catch ( e ) {
			return noop;
		}

		const boards = cfg.boards && cfg.boards.length ? cfg.boards : null;
		if ( ! boards ) {
			return noop;
		}

		const picks = Array.from( scene.querySelectorAll( '.szs-mood' ) );
		const note = scene.querySelector( '.szs-note' );
		const noteText = note && note.querySelector( '.szs-note__text' );
		const narrow = () => scene.clientWidth <= 484;

		let refs = [];
		let flyers = [];
		let cursor = null;

		/** Centre of an element, in px from the art box's corner. */
		function centre( el ) {
			const a = scene.getBoundingClientRect();
			const r = el.getBoundingClientRect();
			return { x: ( r.left + r.right ) / 2 - a.left, y: ( r.top + r.bottom ) / 2 - a.top };
		}

		function dress( el, item, base ) {
			el.className = base + ' szs-mesh szs-mesh--' + item.mesh;
			el.innerHTML = item.html || ''; // printed and escaped by the server (Service_Panel::media_html)
		}

		/** Where a spot of the pile is, in px of the art box. */
		function pile( spot, i, height ) {
			const w = scene.clientWidth;
			const pw = ( narrow() ? 0.3 : 0.22 ) * w;
			return {
				x: ( SPOTS[ spot ][ 0 ] / 100 ) * w,
				y: ( SPOTS[ spot ][ 1 ] / 100 ) * scene.clientHeight,
				w: pw,
				r: TILTS[ ( spot + i ) % TILTS.length ],
			};
		}

		/** A reference that is not chosen: new on the board, placed for real. */
		function place( el, spot, i ) {
			const p = pile( spot, i );
			el.style.left = p.x + 'px';
			el.style.top = p.y + 'px';
			el.style.width = p.w + 'px';
			el.style.setProperty( '--ar', p.r + 'deg' );
		}

		/**
		 * A chosen picture never leaves its place in the layout: it is moved
		 * into the pile by transform only (FLIP), so the page never shifts —
		 * moving left/top/width counted as layout shift (CLS) against the site.
		 */
		function placePick( el, spot, i ) {
			const p = pile( spot, i );
			const fw = el.offsetWidth;
			const fh = el.offsetHeight;
			const sc = p.w / fw;
			const tx = p.x + p.w / 2 - ( el.offsetLeft + fw / 2 );
			const ty = p.y + ( fh * sc ) / 2 - ( el.offsetTop + fh / 2 );
			el.style.transform = `translate(${ tx.toFixed( 1 ) }px, ${ ty.toFixed( 1 ) }px) rotate(${ p.r }deg) scale(${ sc.toFixed( 4 ) })`;
		}

		function unplace( el ) {
			[ 'transform', 'left', 'top', 'width', '--ar' ].forEach( ( prop ) => el.style.removeProperty( prop ) );
		}

		function swatchDots( b, filled ) {
			swatch.innerHTML = '';
			boards[ b ].palette.forEach( ( c ) => {
				const dot = document.createElement( 'b' );
				dot.style.background = c;
				if ( ! filled ) {
					dot.classList.add( 'is-empty' );
				}
				swatch.appendChild( dot );
			} );
		}

		function cleanup() {
			refs.forEach( ( r ) => r.remove() );
			flyers.forEach( ( f ) => f.remove() );
			refs = [];
			flyers = [];
			if ( cursor ) {
				cursor.classList.remove( 'is-on', 'is-click' );
			}
		}

		/** Board b finished, as the server prints board one. */
		function finish( b ) {
			cleanup();
			const board = boards[ b ];
			picks.forEach( ( el, i ) => {
				dress( el, board.picks[ i ], 'szs-mood szs-mood--' + 'abc'[ i ] + ' szs-a' );
				unplace( el );
			} );
			swatchDots( b, true );
			swatch.classList.remove( 'is-away' );
			if ( note ) {
				note.classList.remove( 'is-away' );
				noteText.textContent = board.note;
				noteText.classList.remove( 'is-typing' );
			}
		}

		async function play( b, alive ) {
			const board = boards[ b ];

			// 0. a clean board
			cleanup();
			swatch.classList.add( 'is-away' );
			if ( note ) {
				note.classList.add( 'is-away' );
			}
			await sleep( 350 );
			if ( ! alive() ) {
				return false;
			}
			swatchDots( b, false );
			if ( noteText ) {
				noteText.textContent = '';
			}

			// 1. the pile: chosen and not, spots shuffled
			const count = Math.min( SPOTS.length, 3 + board.refs.length );
			const spots = SPOTS.slice( 0, count ).map( ( s, i ) => i ).sort( () => Math.random() - 0.5 );
			const all = [];

			picks.forEach( ( el, i ) => {
				dress( el, board.picks[ i ], 'szs-mood szs-mood--' + 'abc'[ i ] + ' szs-a is-landing' );
				placePick( el, spots[ i ], i );
				all.push( el );
			} );
			board.refs.forEach( ( item, i ) => {
				if ( 3 + i >= count ) {
					return;
				}
				const el = document.createElement( 'i' );
				dress( el, item, 'szs-ref is-landing' );
				el.setAttribute( 'aria-hidden', 'true' );
				place( el, spots[ 3 + i ], 3 + i );
				scene.appendChild( el );
				refs.push( el );
				all.push( el );
			} );

			// set up in place without animating there, then let them land
			all.forEach( ( el ) => {
				el.style.transition = 'none';
			} );
			void scene.offsetWidth;
			all.forEach( ( el ) => {
				el.style.transition = '';
			} );
			void scene.offsetWidth;
			for ( const el of all.slice().sort( () => Math.random() - 0.5 ) ) {
				el.classList.remove( 'is-landing' );
				await sleep( 90 );
			}
			await sleep( 700 );
			if ( ! alive() ) {
				return false;
			}

			// 2. choose three
			if ( ! cursor ) {
				cursor = document.createElement( 'span' );
				cursor.className = 'szs-cursor';
				cursor.setAttribute( 'aria-hidden', 'true' );
				cursor.innerHTML = POINTER;
				scene.appendChild( cursor );
			}
			const start = centre( scene.querySelector( '.szs-ref' ) || picks[ 0 ] );
			cursor.style.left = start.x + 'px';
			cursor.style.top = start.y + 'px';
			cursor.classList.add( 'is-on' );
			for ( const el of picks ) {
				const c = centre( el );
				cursor.style.left = c.x + 'px';
				cursor.style.top = c.y + 'px';
				await sleep( 700 );
				if ( ! alive() ) {
					return false;
				}
				cursor.classList.add( 'is-click' );
				await sleep( 150 );
				cursor.classList.remove( 'is-click' );
				el.classList.add( 'is-chosen' );
				await sleep( 200 );
			}
			cursor.classList.remove( 'is-on' );

			// 3. clear the rest
			refs.forEach( ( el, i ) => setTimeout( () => el.classList.add( 'is-cleared' ), i * 70 ) );
			await sleep( 650 );
			if ( ! alive() ) {
				return false;
			}
			refs.forEach( ( r ) => r.remove() );
			refs = [];

			// 4. compose
			picks.forEach( unplace );
			await sleep( 950 );
			picks.forEach( ( el ) => el.classList.remove( 'is-chosen' ) );
			if ( ! alive() ) {
				return false;
			}

			// 5. palette, pulled from the pictures
			swatch.classList.remove( 'is-away' );
			await sleep( 350 );
			const dots = Array.from( swatch.children );
			dots.forEach( ( dot, i ) => {
				setTimeout( () => {
					if ( ! alive() ) {
						return;
					}
					const from = centre( picks[ i % picks.length ] );
					const to = centre( dot );
					const fly = document.createElement( 'span' );
					fly.className = 'szs-fly';
					fly.style.left = from.x + 'px';
					fly.style.top = from.y + 'px';
					fly.style.background = boards[ b ].palette[ i ];
					scene.appendChild( fly );
					flyers.push( fly );
					void fly.offsetWidth;
					fly.style.transform = `translate(${ to.x - from.x }px, ${ to.y - from.y }px) scale(1)`;
					setTimeout( () => {
						dot.classList.remove( 'is-empty' );
						fly.remove();
					}, 820 );
				}, i * 200 );
			} );
			await sleep( dots.length * 200 + 900 );
			if ( ! alive() ) {
				return false;
			}

			// 6. the note
			if ( note ) {
				note.classList.remove( 'is-away' );
				await sleep( 300 );
				noteText.classList.add( 'is-typing' );
				for ( let i = 1; i <= board.note.length; i++ ) {
					if ( ! alive() ) {
						return false;
					}
					noteText.textContent = board.note.slice( 0, i );
					await sleep( board.note[ i - 1 ] === ' ' ? 55 : 22 + Math.random() * 26 );
				}
				await sleep( 400 );
				noteText.classList.remove( 'is-typing' );
			}
			return alive();
		}

		let token = 0;
		let running = false;
		let current = 0;

		async function run( tk ) {
			const alive = () => tk === token;

			if ( ! ( await play( current, alive ) ) ) {
				return;
			}
			for ( ;; ) {
				// "Play once": every board once, then rest on the last.
				if ( 'loop' !== api.mode && current === boards.length - 1 ) {
					api.done();
					return;
				}
				await sleep( cfg.hold );
				if ( ! alive() ) {
					return;
				}
				// put the board away
				picks.forEach( ( el ) => el.classList.add( 'is-gone' ) );
				await sleep( 500 );
				current = ( current + 1 ) % boards.length;
				if ( ! ( await play( current, alive ) ) ) {
					return;
				}
			}
		}

		return {
			play() {
				if ( running ) {
					return;
				}
				running = true;
				run( ++token );
			},
			pause() {
				if ( ! running ) {
					return;
				}
				running = false;
				token++;
				finish( current );
			},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'direction', create );
	}
}() );
