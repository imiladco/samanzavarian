/**
 * Scene: AI production. Plays the sheet like a real generation session:
 *
 *   type the prompt → press Generate → frames wait on a sweeping plate →
 *   each resolves from noise to sharp → the approval ring settles on the
 *   picked frame and the label updates.
 *
 * With more than one round (more than three frames) the round is held, its
 * frames are tossed off the sheet while the prompt is erased, and the next
 * round is generated. "Play once" goes through the rounds once and rests on
 * the last; "Keep playing" cycles (a single round regenerates).
 *
 * The server prints the finished first round; that is what shows without
 * JavaScript, with reduced motion, and whenever this scene is paused. Play
 * starts from a blank sheet; inside tabs that happens as the panel fades
 * in, so the switch is never seen.
 * Timing lives in data-szs-ai (Service_Panel → scenes/ai.php).
 */
( function () {
	'use strict';

	const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
	const nextFrame = () => new Promise( ( r ) => requestAnimationFrame( () => requestAnimationFrame( r ) ) );

	function create( scene, api ) {
		const gen = scene.querySelector( '[data-szs-ai]' );
		const noop = { play() {}, pause() {} };

		if ( ! gen ) {
			return noop;
		}

		let cfg;
		try {
			cfg = JSON.parse( gen.dataset.szsAi );
		} catch ( e ) {
			return noop;
		}

		const text = gen.querySelector( '.szs-gen__text' );
		const go = gen.querySelector( '.szs-gen__go' );
		const rounds = Array.from( gen.querySelectorAll( '.szs-gen__grid' ) );
		const pill = scene.querySelector( '.szs-picked' );
		const pillText = pill && pill.querySelector( '.szs-picked__text' );

		let token = 0; // bumps on pause: every running step checks it and stops
		let running = false;
		let round = 0;

		const prompt = ( r ) => cfg.prompts[ r % cfg.prompts.length ] || '';
		const frames = ( r ) => Array.from( rounds[ r ].children );
		const label = ( r ) => cfg.label.replace( '{n}', String( r * 3 + cfg.pick ) );

		function show( r ) {
			rounds.forEach( ( grid, i ) => {
				grid.classList.toggle( 'is-current', i === r );
				grid.classList.toggle( 'szs-off', i !== r );
			} );
			api.refresh(); // videos of the shown round play, the others stop
		}

		/** The sheet a round starts from: empty prompt, frames still loading. */
		function blank( r ) {
			text.textContent = '';
			if ( pill ) {
				pill.classList.add( 'is-waiting' ); // nothing is approved yet
			}
			frames( r ).forEach( ( f ) => {
				f.classList.remove( 'is-picked', 'is-out', 'is-noisy' );
				f.classList.add( 'is-loading' );
			} );
		}

		/** The finished round, as the server prints it. */
		function finish( r ) {
			show( r );
			text.textContent = prompt( r );
			text.classList.remove( 'is-typing' );
			go.classList.remove( 'is-press' );
			frames( r ).forEach( ( f, i ) => {
				f.classList.remove( 'is-loading', 'is-out', 'is-noisy' );
				f.classList.toggle( 'is-picked', i === cfg.pick - 1 );
			} );
			if ( pillText ) {
				pillText.textContent = label( r );
			}
			if ( pill ) {
				pill.classList.remove( 'is-waiting' );
			}
		}

		async function type( str, alive ) {
			text.classList.add( 'is-typing' );
			if ( cfg.speed <= 0 ) {
				text.textContent = str;
			} else {
				for ( let i = 1; i <= str.length; i++ ) {
					if ( ! alive() ) {
						return false;
					}
					text.textContent = str.slice( 0, i );
					// people type unevenly and pause on spaces
					await sleep( cfg.speed * ( str[ i - 1 ] === ' ' ? 1.8 : 0.6 + Math.random() * 0.8 ) );
				}
			}
			await sleep( 300 );
			text.classList.remove( 'is-typing' );
			return alive();
		}

		async function erase( alive ) {
			text.classList.add( 'is-typing' );
			let s = text.textContent;
			while ( s.length ) {
				if ( ! alive() ) {
					return false;
				}
				s = s.slice( 0, -Math.max( 1, Math.ceil( s.length / 12 ) ) );
				text.textContent = s;
				await sleep( 22 );
			}
			return alive();
		}

		async function generate( r, alive ) {
			show( r );
			blank( r );

			if ( ! ( await type( prompt( r ), alive ) ) ) {
				return false;
			}

			go.classList.add( 'is-press' );
			await sleep( 170 );
			go.classList.remove( 'is-press' );

			// "thinking": the plates sweep for a moment
			await sleep( 700 );
			if ( ! alive() ) {
				return false;
			}

			for ( const f of frames( r ) ) {
				f.classList.add( 'is-noisy' );
				f.classList.remove( 'is-loading' );
				await nextFrame(); // let the noisy state paint, then resolve it
				f.classList.remove( 'is-noisy' );
				await sleep( 180 );
				if ( ! alive() ) {
					return false;
				}
			}

			await sleep( 750 );
			if ( ! alive() ) {
				return false;
			}

			const picked = frames( r )[ cfg.pick - 1 ];
			if ( picked ) {
				picked.classList.add( 'is-picked' );
			}
			if ( pillText ) {
				pillText.textContent = label( r );
				pill.classList.remove( 'is-pop', 'is-waiting' );
				void pill.offsetWidth; // restart the animation
				pill.classList.add( 'is-pop' );
			}
			return true;
		}

		async function leave( r, alive ) {
			frames( r ).forEach( ( f, i ) => setTimeout( () => alive() && f.classList.add( 'is-out' ), i * 80 ) );
			if ( ! ( await erase( alive ) ) ) {
				return false;
			}
			await sleep( 420 );
			return alive();
		}

		async function run( tk ) {
			const alive = () => tk === token;

			if ( ! ( await generate( round, alive ) ) ) {
				return;
			}

			for ( ;; ) {
				// "Play once": every round once, then rest on the last.
				if ( 'loop' !== api.mode && round === rounds.length - 1 ) {
					api.done();
					return;
				}
				await sleep( cfg.hold );
				if ( ! alive() || ! ( await leave( round, alive ) ) ) {
					return;
				}
				round = ( round + 1 ) % rounds.length;
				if ( ! ( await generate( round, alive ) ) ) {
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
				// Only when something runs: finish() shows a round, showing a round
				// refreshes the runtime, and the runtime calls pause() again.
				if ( ! running ) {
					return;
				}
				running = false;
				token++;
				// Hidden now; leave it finished, so it never sits half-drawn.
				finish( round );
			},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'ai', create );
	}
}() );
