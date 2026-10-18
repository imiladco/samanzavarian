/**
 * Scene: Content systems — a content calendar that plans itself.
 *
 * Dating (always, also with reduced motion — it is information, not motion):
 * in "real week" mode the calendar shows the visitor's own ISO week number
 * and highlights today, from their device's clock.
 *
 * While live: the week empties, its posts land day by day and "This week
 * is planned · N posts scheduled" comes in. "Play once" rests there; "Keep
 * playing" then lifts and turns the page like a desk calendar to the next
 * week, which fills from the next plan. Weeks count on (wrapping after week 52/53); only the first
 * page — the current week — has a "today".
 *
 * Paused, it shows the current page finished, as the server prints it.
 */
( function () {
	'use strict';

	const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
	const DAY = 86400000;

	/** ISO 8601 week number of a date (weeks start Monday; week 1 holds Jan 4th). */
	function isoWeek( date ) {
		const d = new Date( Date.UTC( date.getFullYear(), date.getMonth(), date.getDate() ) );
		const day = d.getUTCDay() || 7;
		d.setUTCDate( d.getUTCDate() + 4 - day );
		const jan1 = new Date( Date.UTC( d.getUTCFullYear(), 0, 1 ) );
		return Math.ceil( ( ( d - jan1 ) / DAY + 1 ) / 7 );
	}

	function create( scene, api ) {
		const week = scene.querySelector( '[data-szs-content]' );
		const noop = { play() {}, pause() {} };

		if ( ! week ) {
			return noop;
		}

		let cfg;
		try {
			cfg = JSON.parse( week.dataset.szsContent );
		} catch ( e ) {
			return noop;
		}

		const notif = scene.querySelector( '.szs-notif' );
		const notifText = notif && notif.querySelector( '.szs-notif__text' );
		const no = week.querySelector( '.szs-week__no' );
		const days = Array.from( week.querySelectorAll( '.szs-day' ) );
		const weeks = cfg.weeks && cfg.weeks.length ? cfg.weeks : [ [ [], [], [], [], [], [], [] ] ];

		// Page 0 is this week. Live: the visitor's date; fixed: the settings.
		const now = new Date();
		const today = cfg.live ? ( now.getDay() + 6 ) % 7 : cfg.today - 1; // 0 = Monday, -1 = none
		const monday = new Date( now.getTime() - ( ( now.getDay() + 6 ) % 7 ) * DAY );

		const weekNo = ( page ) => ( cfg.live
			? isoWeek( new Date( monday.getTime() + page * 7 * DAY ) )
			: ( ( cfg.week - 1 + page ) % 52 ) + 1 );
		const plan = ( page ) => weeks[ page % weeks.length ];
		const count = ( page ) => plan( page ).reduce( ( n, d ) => n + d.length, 0 );

		function post( p ) {
			const el = document.createElement( 'em' );
			el.className = 'szs-post';
			const type = typeof p === 'number' ? cfg.types[ p ] : null;
			el.textContent = type ? type.name : ( p && p.name ) || '';
			if ( type ) {
				el.style.background = type.bg || '';
				el.style.color = type.fg || '';
				el.style.boxShadow = type.line ? '0 0 0 1px ' + type.line : 'none';
			}
			return el;
		}

		/** Header and "today" for a page, days emptied. */
		function page( k ) {
			if ( no ) {
				no.textContent = cfg.label.replace( '{n}', String( weekNo( k ) ) );
			}
			days.forEach( ( d, i ) => {
				d.classList.toggle( 'is-today', 0 === k && i === today );
				d.querySelectorAll( '.szs-post' ).forEach( ( e ) => e.remove() );
			} );
		}

		function announce( k ) {
			if ( notifText ) {
				notifText.textContent = cfg.text.replace( '{count}', String( count( k ) ) );
			}
			if ( notif ) {
				notif.classList.remove( 'is-away' );
			}
		}

		/** The finished page, all at once. */
		function finish( k ) {
			page( k );
			plan( k ).forEach( ( posts, i ) => posts.forEach( ( p ) => days[ i ] && days[ i ].appendChild( post( p ) ) ) );
			announce( k );
		}

		async function fill( k, alive ) {
			for ( let i = 0; i < 7; i++ ) {
				for ( const p of plan( k )[ i ] || [] ) {
					if ( ! alive() ) {
						return false;
					}
					const el = post( p );
					el.classList.add( 'is-drop' );
					days[ i ].appendChild( el );
					days[ i ].classList.remove( 'is-hit' );
					void days[ i ].offsetWidth;
					days[ i ].classList.add( 'is-hit' );
					await sleep( cfg.drop );
				}
			}
			await sleep( 350 );
			return alive();
		}

		let leaf = null;

		async function turn( k, alive ) {
			if ( notif ) {
				notif.classList.add( 'is-away' );
			}
			await sleep( 250 );
			if ( ! alive() ) {
				return false;
			}
			// A copy of the page lifts off the top edge; the next week is
			// already underneath.
			leaf = document.createElement( 'div' );
			leaf.className = 'szs-week__leaf';
			leaf.setAttribute( 'aria-hidden', 'true' );
			Array.from( week.children ).forEach( ( c ) => leaf.appendChild( c.cloneNode( true ) ) );
			week.appendChild( leaf );
			page( k + 1 );
			await sleep( 900 );
			if ( leaf ) {
				leaf.remove();
				leaf = null;
			}
			return alive();
		}

		let token = 0;
		let running = false;
		let current = 0;
		let started = false;

		async function run( tk ) {
			const alive = () => tk === token;

			if ( ! started ) {
				// The first time, plan this week in front of the visitor.
				started = true;
				page( current );
				if ( notif ) {
					notif.classList.add( 'is-away' );
				}
				await sleep( 500 );
				if ( ! alive() || ! ( await fill( current, alive ) ) ) {
					return;
				}
				announce( current );
			}

			// "Play once": this week is planned, and that is the story.
			if ( 'loop' !== api.mode ) {
				api.done();
				return;
			}

			for ( ;; ) {
				await sleep( cfg.hold );
				if ( ! alive() || ! ( await turn( current, alive ) ) ) {
					return;
				}
				current++;
				if ( ! ( await fill( current, alive ) ) ) {
					return;
				}
				announce( current );
			}
		}

		// Date the calendar right away (not motion: the visitor's own week).
		if ( cfg.live ) {
			finish( 0 );
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
				if ( leaf ) {
					leaf.remove();
					leaf = null;
				}
				finish( current );
			},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'content', create );
	}
}() );
