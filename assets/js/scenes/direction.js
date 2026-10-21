/**
 * Scene: Creative direction — three routes for one campaign, and the client's choice.
 *
 * How a direction really gets chosen:
 *   1. before direction: the three pictures look like three different shoots;
 *   2. the director shows each route on the same three pictures: the palette
 *      and the note change, a layout grid shows for a moment, and a scan line
 *      restyles the pictures one after another;
 *   3. the client chooses one ("Approved direction").
 * "Play once" ends on the choice; "Keep playing" starts again from 1.
 * The server prints route one applied (the design), which is also what shows
 * with "Still" or reduced motion.
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

	const shuffle = ( a ) => {
		for ( let i = a.length - 1; i > 0; i-- ) {
			const j = Math.floor( Math.random() * ( i + 1 ) );
			[ a[ i ], a[ j ] ] = [ a[ j ], a[ i ] ];
		}
		return a;
	};

	function swap( box, html, tag ) {
		const old = box.lastElementChild;
		const nu = document.createElement( tag );
		nu.className = 'b-in';
		nu.innerHTML = html; // escaped by the server
		box.appendChild( nu );
		void nu.offsetWidth;
		nu.classList.remove( 'b-in' );
		if ( old ) {
			old.classList.add( 'b-out' );
			setTimeout( () => old.remove(), 550 );
		}
	}

	function create( root, api ) {
		const swatch = root.querySelector( '[data-szs-direction]' );
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
		if ( ! cfg.routes || ! cfg.routes.length ) {
			return noop;
		}

		const dots = Array.from( swatch.children );
		const status = root.querySelector( '.szs-dir__status' );
		const head = root.querySelector( '.szs-dir__nh' );
		const nt = root.querySelector( '.szs-dir__nt' );
		const grid = root.querySelector( '.szs-dir__grid' );
		const clips = Array.from( root.querySelectorAll( '.szs-dir__clip' ) );
		const L = cfg.labels;
		const fill = ( text, map ) => text.replace( /\{(\w+)\}/g, ( m, k ) => ( k in map ? map[ k ] : m ) );

		const say = ( text, dot ) => text && swap( status, `<i style="--dot:${ dot }"></i>${ text }`, 'span' );

		function palette( pal ) {
			dots.forEach( ( b, i ) => setTimeout( () => {
				b.style.backgroundColor = pal[ i ] || '';
				b.classList.add( 'is-hit' );
				setTimeout( () => b.classList.remove( 'is-hit' ), 380 );
			}, i * 90 ) );
		}

		/** Restyle the three pictures, one after another. */
		async function restyle( pics, how, accent ) {
			[ 'a', 'b', 'c' ].forEach( ( slot, i ) => setTimeout( () => {
				const clip = clips[ i ];
				if ( ! clip ) {
					return;
				}
				const old = clip.lastElementChild;
				const tmp = document.createElement( 'div' );
				tmp.innerHTML = pics[ slot ]; // escaped by the server
				const nu = tmp.firstElementChild;
				nu.classList.add( 'wipe' === how ? 'is-wipe' : 'is-fade' );
				clip.appendChild( nu );
				let scan = null;
				if ( 'wipe' === how ) {
					scan = document.createElement( 'i' );
					scan.className = 'szs-dir__scan';
					scan.style.setProperty( '--acc', accent );
					clip.appendChild( scan );
				}
				void nu.offsetWidth;
				nu.classList.add( 'is-on' );
				if ( scan ) {
					scan.classList.add( 'is-on' );
				}
				setTimeout( () => {
					if ( old && old !== nu ) {
						old.remove();
					}
					if ( scan ) {
						scan.style.opacity = '0';
						setTimeout( () => scan.remove(), 300 );
					}
					api.refresh(); // videos in the new pictures
				}, 950 );
			}, i * 200 ) );
			await api.time.wait( 1400 );
		}

		async function show( route, letter, count ) {
			const map = { letter, count, name: route.name };
			szwText( head, fill( L.dir_route_title, map ) );
			say( fill( L.dir_say_route, map ), route.pal[ 1 ] || '#FF6B2C' );
			swap( nt, route.note, 'span' );
			palette( route.pal );
			await api.time.wait( 1300 );
			say( fill( L.dir_say_apply, map ), route.pal[ 1 ] || '#FF6B2C' );
			grid.classList.add( 'is-on' );
			await api.time.wait( 350 );
			await restyle( route.pics, 'wipe', route.pal[ 1 ] || '#FF6B2C' );
			grid.classList.remove( 'is-on' );
		}

		let started = false;

		async function run() {
			await api.time.wait( 400 );
			for ( ;; ) {
				// 1. before direction
				szwText( head, L.dir_note_title );
				say( L.dir_say_before, cfg.before.pal[ 1 ] );
				swap( nt, `<em>${ cfg.before.note }</em>`, 'span' );
				palette( cfg.before.pal );
				await restyle( cfg.before.pics, 'fade' );
				await api.time.wait( 2800 );
				if ( driven ) {
					return;
				}

				// 2. the routes: the first (the design's), then the others in any order
				const order = [ 0 ].concat( shuffle( cfg.routes.map( ( r, i ) => i ).slice( 1 ) ) );
				const count = order.length;
				for ( let n = 0; n < count; n++ ) {
					if ( driven ) {
						return; // the scroll took over
					}
					await show( cfg.routes[ order[ n ] ], 'ABCDEFGH'[ n ], count );
					await api.time.wait( cfg.hold );
				}
				if ( driven ) {
					return;
				}

				// 3. the client's choice
				const k = cfg.choice ? Math.min( cfg.choice, count ) - 1 : Math.floor( Math.random() * count );
				const pick = cfg.routes[ order[ k ] ];
				const map = { letter: 'ABCDEFGH'[ k ], count, name: pick.name };
				szwText( head, fill( L.dir_chosen, map ) );
				say( fill( L.dir_say_chosen, map ), '#1F9D60' );
				swap( nt, pick.note, 'span' );
				palette( pick.pal );
				if ( k !== count - 1 ) {
					await api.time.wait( 600 );
					await restyle( pick.pics, 'wipe', pick.pal[ 1 ] || '#FF6B2C' );
				}
				if ( 'loop' !== api.mode ) {
					api.done();
					return;
				}
				await api.time.wait( 4200 );
			}
		}

		/* ---- chapters: a scroll-driven Service Tabs shows before, each route (the editor's order) and the choice ---- */
		const count = cfg.routes.length;
		const choice = cfg.choice ? Math.min( cfg.choice, count ) - 1 : 0;
		async function chapter( c ) {
			if ( 0 === c ) {
				szwText( head, L.dir_note_title );
				say( L.dir_say_before, cfg.before.pal[ 1 ] );
				swap( nt, `<em>${ cfg.before.note }</em>`, 'span' );
				palette( cfg.before.pal );
				await restyle( cfg.before.pics, 'fade' );
			} else if ( c <= count ) {
				await show( cfg.routes[ c - 1 ], 'ABCDEFGH'[ c - 1 ], count );
			} else {
				const pick = cfg.routes[ choice ];
				const map = { letter: 'ABCDEFGH'[ choice ], count, name: pick.name };
				szwText( head, fill( L.dir_chosen, map ) );
				say( fill( L.dir_say_chosen, map ), '#1F9D60' );
				swap( nt, pick.note, 'span' );
				palette( pick.pal );
				await restyle( pick.pics, 'wipe', pick.pal[ 1 ] || '#FF6B2C' );
			}
		}
		let driven = false, target = -1, at = -1, steering = false;
		async function steer() {
			if ( steering ) {
				return; // the chapter under way ends, then the latest target follows
			}
			steering = true;
			while ( driven && target >= 0 && target !== at ) {
				at = target;
				await chapter( at );
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
			pause() {},
			drive( on ) {
				driven = on;
				if ( on ) {
					started = true; // the story never starts while the scroll drives
				} else if ( target >= 0 ) {
					started = false; // handed back: the story plays by itself again
				}
			},
			show( k ) {
				target = Math.max( 0, Math.min( count + 1, k | 0 ) );
				steer();
			},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'direction', create );
	}
}() );
