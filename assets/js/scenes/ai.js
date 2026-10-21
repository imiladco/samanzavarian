/**
 * Scene: AI visual production — a client's brief, and the reel of what was delivered.
 *
 * The server prints the resting picture: the first piece in focus, its
 * neighbours soft at the sides, the first brief and result. Here:
 *
 *   • first time it plays: the camera pulls back to the whole reel, every
 *     piece numbered with its shape, so it is clear there are several;
 *     then it glides to the first piece and pushes in;
 *   • a piece plays for its length (a video's own; the time bar and the
 *     clock show it), then the camera pulls back, glides to the next piece
 *     and pushes in with a focus pull and autofocus brackets. The brief and
 *     the result change on the way. Next = random, every piece once per
 *     round, never the same twice in a row.
 *
 * "Play once": one round of the reel, then back to the first piece, resting.
 * "Keep playing": rounds without end.
 *
 * Only the piece in focus plays its video (and the two involved in a glide,
 * while the reel is in view); the rest stay paused (.szs-off).
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

	const W = 412; // the window, design px (see AI_Showreel::WIN)
	const OUT = 0.5; // scale of the reel when pulled back

	const shuffle = ( a ) => {
		for ( let i = a.length - 1; i > 0; i-- ) {
			const j = Math.floor( Math.random() * ( i + 1 ) );
			[ a[ i ], a[ j ] ] = [ a[ j ], a[ i ] ];
		}
		return a;
	};
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	const clock = ( s ) => Math.floor( s / 60 ) + ':' + pad( Math.floor( s % 60 ) );

	/** Swap the text of a ticker: the old line leaves up, the new one comes in from below. */
	function swap( box, html, tag ) {
		const old = box.lastElementChild;
		const nu = document.createElement( tag );
		nu.className = 'b-in';
		nu.innerHTML = html; // printed and escaped by the server
		box.appendChild( nu );
		void nu.offsetWidth;
		nu.classList.remove( 'b-in' );
		if ( old ) {
			old.classList.add( 'b-out' );
			setTimeout( () => old.remove(), 550 );
		}
	}

	function create( root, api ) {
		const sheet = root.querySelector( '[data-szs-ai]' );
		const noop = { play() {}, pause() {} };
		if ( ! sheet ) {
			return noop;
		}
		let cfg;
		try {
			cfg = JSON.parse( sheet.dataset.szsAi );
		} catch ( e ) {
			return noop;
		}

		const strip = sheet.querySelector( '.szs-ai__strip' );
		const items = Array.from( strip.children );
		const af = sheet.querySelector( '.szs-ai__af' );
		const bar = sheet.querySelector( '.szs-ai__prog i' );
		const brief = sheet.querySelector( '.szs-ai__brief' );
		const okBox = root.querySelector( '.szs-ai__ok' );
		const ok = okBox && okBox.querySelector( 'span' );
		const hudN = sheet.querySelector( '.szs-ai__n' );
		const hudF = sheet.querySelector( '.szs-ai__f' );
		const H = strip.parentElement.clientHeight || 204;
		const n = items.length;
		const glide = cfg.glide || 1;
		const geo = items.map( ( e ) => ( { x: e.offsetLeft, w: e.offsetWidth } ) );
		const total = geo.length ? geo[ n - 1 ].x + geo[ n - 1 ].w : W;

		if ( n < 2 || ! cfg.pieces ) {
			return noop;
		}

		const at = ( cx, k ) => `translate(${ ( W / 2 - k * cx ).toFixed( 2 ) }px,${ ( H / 2 - ( k * H ) / 2 ).toFixed( 2 ) }px) scale(${ k })`;
		const cam = ( i, k ) => at( geo[ i ].x + geo[ i ].w / 2, k );
		const move = ( t, ms, ease ) => {
			strip.style.transition = `transform ${ Math.round( ms / glide ) }ms ${ ease }`;
			strip.style.transform = t;
			return api.time.wait( ms / glide );
		};
		const video = ( i ) => items[ i ].querySelector( 'video' );
		/** Seconds a piece stays: its setting, or its video's real length. */
		const secs = ( i ) => {
			const v = video( i );
			const d = v && isFinite( v.duration ) && v.duration > 0 && ! cfg.pieces[ i ].fixed ? v.duration : cfg.pieces[ i ].secs;
			return Math.max( 2, Math.min( 30, d ) );
		};
		const label = ( i, s ) => {
			const f = cfg.pieces[ i ].format;
			return ( f ? f + ' · ' : '' ) + clock( s ) + ' / ' + clock( secs( i ) );
		};
		/** Which pieces may play their video: the focused one, and those in a glide. */
		const showOnly = ( list ) => {
			items.forEach( ( e, k ) => e.classList.toggle( 'szs-off', ! list.includes( k ) ) );
			api.refresh();
		};
		const focus = ( i ) => {
			items.forEach( ( e, k ) => e.classList.toggle( 'is-focus', k === i ) );
			if ( i < 0 ) {
				return;
			}
			const w = geo[ i ].w;
			af.style.left = ( ( W - w ) / 2 + w * 0.18 ).toFixed( 1 ) + 'px';
			af.style.width = ( w * 0.64 ).toFixed( 1 ) + 'px';
		};

		let cur = 0;
		let shownAt = 0;
		let shownSec = -1;
		let holding = false;
		let started = false;

		// The time bar and the clock follow the piece's own time.
		api.time.frame( () => {
			if ( ! holding ) {
				return;
			}
			const len = secs( cur ) * 1000;
			const e = Math.min( len, api.time.t - shownAt );
			bar.style.transform = `scaleX(${ ( e / len ).toFixed( 4 ) })`;
			const sec = Math.floor( e / 1000 );
			if ( sec !== shownSec ) {
				shownSec = sec;
				szwText( hudF, label( cur, sec ) );
			}
		} );

		async function pushIn( j ) {
			items[ j ].classList.remove( 'is-pull' );
			void items[ j ].offsetWidth;
			items[ j ].classList.add( 'is-pull' );
			focus( j );
			const v = video( j );
			if ( v ) {
				v.currentTime = 0;
			}
			if ( cfg.af ) {
				af.classList.remove( 'is-hunt' );
				void af.offsetWidth;
				af.classList.add( 'is-hunt' );
			}
			const arrive = move( cam( j, 1 ), 820, 'cubic-bezier(.16,.84,.24,1)' );
			await api.time.wait( 620 / glide );
			root.classList.remove( 'is-out' );
			items.forEach( ( e ) => e.classList.remove( 'is-target' ) );
			showOnly( [ j ] );
			cur = j;
			shownSec = -1;
			szwText( hudN, pad( j + 1 ) + ' / ' + pad( n ) );
			szwText( hudF, label( j, 0 ) );
			bar.style.transform = 'scaleX(0)';
			shownAt = api.time.t;
			holding = true;
			await arrive;
		}

		async function go( j ) {
			const i = cur;
			holding = false;
			items[ j ].classList.add( 'is-target' );
			showOnly( [ i, j ] );
			root.classList.add( 'is-out' );
			focus( -1 );
			await move( cam( i, OUT ), 520, 'cubic-bezier(.55,0,.35,1)' ); // pull back
			swap( brief, cfg.pieces[ j ].brief, 'div' ); // the brief changes on the way
			await api.time.wait( 130 / glide );
			await move( cam( j, OUT ), Math.min( 1300, 560 + Math.abs( j - i ) * 150 ), 'cubic-bezier(.65,0,.35,1)' ); // glide
			await api.time.wait( 60 );
			if ( ok ) {
				okBox.hidden = ! cfg.pieces[ j ].result;
				swap( ok, cfg.pieces[ j ].result, 'em' );
			}
			await pushIn( j ); // push in, focus pulls
		}

		/** Random, every piece once per round, never twice in a row. */
		let queue = [];
		const next = () => {
			if ( ! queue.length ) {
				queue = shuffle( items.map( ( e, k ) => k ) );
				if ( queue[ queue.length - 1 ] === cur ) {
					[ queue[ 0 ], queue[ queue.length - 1 ] ] = [ queue[ queue.length - 1 ], queue[ 0 ] ];
				}
			}
			return queue.pop();
		};

		async function run() {
			if ( cfg.overview ) {
				// The whole reel first.
				root.classList.add( 'is-out', 'is-intro' );
				focus( -1 );
				showOnly( items.map( ( e, k ) => k ) );
				await api.time.wait( 250 );
				await move( at( total / 2, ( W - 24 ) / total ), 1000, 'cubic-bezier(.55,0,.25,1)' );
				await api.time.wait( 1300 );
				if ( driven ) {
					return; // the scroll took over: it places the pieces from here
				}
				root.classList.remove( 'is-intro' );
				items[ 0 ].classList.add( 'is-target' );
				showOnly( [ 0 ] );
				await move( cam( 0, OUT ), 900, 'cubic-bezier(.65,0,.35,1)' );
				await pushIn( 0 );
			} else {
				shownAt = api.time.t;
				holding = true;
			}

			// "Play once": every other piece once, then home to the first.
			let left = 'loop' === api.mode ? Infinity : n - 1;
			queue = shuffle( items.map( ( e, k ) => k ).filter( ( k ) => k !== 0 ) );
			for ( ;; ) {
				await api.time.wait( Math.max( 0, secs( cur ) * 1000 - ( api.time.t - shownAt ) ) );
				if ( driven ) {
					return;
				}
				if ( left-- <= 0 ) {
					break;
				}
				if ( driven ) {
					return;
				}
				await go( next() );
			}
			if ( cur !== 0 ) {
				await go( 0 );
			}
			holding = false;
			bar.style.transform = 'scaleX(0)';
			szwText( hudF, label( 0, 0 ) );
			api.done();
		}

		/* ---- chapters: a scroll-driven Service Tabs shows the pieces one by one, in the editor's order ---- */
		let driven = false, target = -1, placed = false, steering = false;
		async function steer() {
			if ( steering ) {
				return; // the transition under way ends at the latest target
			}
			steering = true;
			while ( driven && target >= 0 && ( ! placed || target !== cur ) ) {
				const j = target;
				if ( ! placed ) {
					// first time: from wherever the reel stands straight to the piece
					placed = true;
					root.classList.remove( 'is-intro' );
					root.classList.add( 'is-out' );
					items.forEach( ( e ) => e.classList.remove( 'is-target' ) );
					items[ j ].classList.add( 'is-target' );
					showOnly( [ j ] );
					if ( ok ) {
						okBox.hidden = ! cfg.pieces[ j ].result;
						swap( ok, cfg.pieces[ j ].result, 'em' );
					}
					swap( brief, cfg.pieces[ j ].brief, 'div' );
					await move( cam( j, OUT ), 520, 'cubic-bezier(.65,0,.35,1)' );
					await pushIn( j );
				} else {
					await go( j );
				}
			}
			steering = false;
		}

		return {
			play() {
				if ( ! started && ! driven ) {
					started = true;
					run();
				}
			},
			pause() {}, // the clock stops, so the story simply holds where it is
			drive( on ) {
				driven = on;
				if ( on ) {
					started = true; // the story never starts while the scroll drives
				} else if ( target >= 0 ) {
					// handed back: the reel carries on by itself from the piece in view
					started = false;
				}
			},
			show( k ) {
				target = Math.max( 0, Math.min( n - 1, k | 0 ) );
				steer();
			},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'ai', create );
	}
}() );
