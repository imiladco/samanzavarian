/**
 * Scene: Content systems — a rolling plan that runs on rules.
 *
 * Dating (always, also with reduced motion — it is information, not motion):
 * in "real week" mode the week number and "today" come from the visitor's
 * own clock.
 *
 * Played: the planner moves to today (yesterday on the left, five days
 * ahead) and rolls on a day at a time, "Today" fixed in place. Time runs as
 * a smart time-lapse: quiet hours fly by (a quiet day takes about the
 * configured seconds, never quite the same), and time slows as a post's
 * moment comes, so it can be seen going out. Every change has a cause:
 *   • a post goes out at its time (shown under it), only if it is ready,
 *     then it is shared to stories (a ring by the day's name);
 *   • the team makes posts in office hours, a few at a time, nearest
 *     deadline first, starting some days before each is due (the chip fills);
 *   • next week is planned on the planning day at 09:00; until then its
 *     days say "Not planned yet";
 *   • in some weeks a trend gets a fast-tracked Reel; it takes a production
 *     slot, so another post may not be ready on time: it moves to the next
 *     free day and the notification says why;
 *   • a Reel that does well gets a carousel version later in the week.
 * Between events the notification shows the next post and where it stands.
 *
 * "Play once": seven days, then the plan rests. "Keep playing": on and on.
 */
( function () {
	'use strict';

	const DAYMS = 864e5;
	const H0 = 6;
	const HN = 18;
	const TOP = 26;
	const ROW = 36;
	const CHIP0 = TOP + 12;
	const GAP = 8;
	const CADENCE = [ [ 0, 2, 4, 5 ], [ 0, 2, 3, 5 ], [ 1, 2, 4, 6 ], [ 0, 1, 3, 4, 5 ], [ 0, 2, 3, 4, 6 ], [ 0, 1, 2, 4, 5 ], [ 0, 1, 3, 4, 6 ] ];

	const rnd = ( a, b ) => a + Math.random() * ( b - a );
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	const fmtH = ( h ) => pad( Math.floor( h ) % 24 ) + ':' + pad( Math.floor( ( h % 1 ) * 60 + 1e-6 ) );
	const fill = ( t, map ) => String( t || '' ).replace( /\{(\w+)\}/g, ( m, k ) => ( k in map ? map[ k ] : m ) );
	const esc = ( s ) => String( s ).replace( /[&<>"]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ] ) );

	function isoWeek( date ) {
		const d = new Date( Date.UTC( date.getFullYear(), date.getMonth(), date.getDate() ) );
		const day = d.getUTCDay() || 7;
		d.setUTCDate( d.getUTCDate() + 4 - day );
		const y = new Date( Date.UTC( d.getUTCFullYear(), 0, 1 ) );
		return Math.ceil( ( ( d - y ) / DAYMS + 1 ) / 7 );
	}
	function rng( seed ) {
		return () => {
			seed |= 0;
			seed = ( seed + 0x6D2B79F5 ) | 0;
			let t = Math.imul( seed ^ ( seed >>> 15 ), 1 | seed );
			t = ( t + Math.imul( t ^ ( t >>> 7 ), 61 | t ) ) ^ t;
			return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
		};
	}

	function create( root, api ) {
		const card = root.querySelector( '[data-szs-content]' );
		const noop = { play() {}, pause() {} };
		if ( ! card ) {
			return noop;
		}
		let cfg;
		try {
			cfg = JSON.parse( card.dataset.szsContent );
		} catch ( e ) {
			return noop;
		}

		const L = cfg.labels;
		const R = cfg.rules;
		const F = cfg.formats;
		const DAYS = cfg.days;
		const strip = card.querySelector( '.szs-cp__strip' );
		const win = card.querySelector( '.szs-cp__win' );
		const clock = card.querySelector( '.szs-cp__clock' );
		const wno = card.querySelector( '.szs-cp__wno' );
		const notif = root.querySelector( '.szs-cp__notif' );
		const tx = notif && notif.querySelector( '.szs-cp__tx' );
		const COL = ( ( win.clientWidth || 430 ) - GAP * 6 ) / 7;
		const STEP = COL + GAP;
		const WINDOW = R.lead * 24;
		const FAST = HN / R.quiet; // plan hours per real second on a quiet day
		const SLOW = 2.2;
		const READ = 4;

		/* ---------- dates ---------- */
		const now = new Date();
		let today = cfg.live ? ( now.getDay() + 6 ) % 7 : cfg.today;
		let hour = cfg.live ? Math.max( H0, Math.min( 23.5, now.getHours() + now.getMinutes() / 60 ) ) : 10;
		const monday0 = new Date( now.getFullYear(), now.getMonth(), now.getDate() - ( ( now.getDay() + 6 ) % 7 ) );
		const dateOf = ( D ) => new Date( monday0.getTime() + D * DAYMS );
		const weekOf = ( D ) => Math.floor( D / 7 );
		const wd = ( D ) => ( ( D % 7 ) + 7 ) % 7;
		const weekNo = ( k ) => ( cfg.live ? isoWeek( dateOf( k * 7 ) ) : ( ( ( cfg.week - 1 + k ) % 52 ) + 52 ) % 52 + 1 );
		const dayWord = ( D ) => ( D === today ? L.content_today_word : DAYS[ wd( D ) ] );

		/* ---------- the plan ---------- */
		const items = [];
		const planned = new Set( [ 0 ] );
		const flags = {};
		cfg.plan.forEach( ( p ) => items.push( { type: p.type, name: p.name, D: p.d, h: p.h, status: 'ready', p: 1 } ) );

		/* A believable week: a cadence of one post a day spread over the week, formats by their
		   share, no format two days running, weekday-only formats on weekdays, usual times. */
		function plan( seed ) {
			const r = rng( seed );
			const pick = ( a ) => a[ Math.floor( r() * a.length ) ];
			const days = pick( CADENCE );
			const pool = [];
			F.forEach( ( f, i ) => {
				for ( let k = 0; k < f.weight; k++ ) {
					pool.push( i );
				}
			} );
			if ( ! pool.length ) {
				return [];
			}
			const types = days.map( () => pick( pool ) );
			for ( let pass = 0; pass < 8; pass++ ) {
				for ( let k = 0; k < types.length; k++ ) {
					const clash = ( k > 0 && types[ k ] === types[ k - 1 ] && days[ k ] - days[ k - 1 ] === 1 ) || ( F[ types[ k ] ].weekdays && days[ k ] > 4 );
					if ( clash ) {
						types[ k ] = pick( pool );
					}
				}
			}
			return days.filter( ( d, k ) => ! ( F[ types[ k ] ].weekdays && d > 4 ) ).map( ( d ) => {
				const k = days.indexOf( d );
				return { type: types[ k ], d, h: pick( F[ types[ k ] ].times ) };
			} );
		}

		let view = 0;
		const cols = new Map();
		let holdUntil = 0;
		let lingerUntil = 0;
		let msgKey = '';
		let events = [];
		let rate = 0;
		let pace = 1;
		let days = 0;
		const nowH = () => today * 24 + hour;
		const due = ( i ) => i.D * 24 + i.h;
		const on = ( D ) => items.filter( ( i ) => i.D === D );
		const nameOf = ( i ) => ( i.type >= 0 ? F[ i.type ].name : i.name );

		function settle( i ) {
			const left = due( i ) - nowH();
			if ( left <= 0 ) {
				i.status = 'live';
			} else if ( left <= 24 ) {
				i.status = 'ready';
			} else if ( left <= WINDOW ) {
				i.status = 'prod';
				i.p = Math.max( 0.1, 1 - ( left - 24 ) / ( WINDOW - 24 ) );
			} else {
				i.status = 'idea';
				i.p = 0;
			}
		}
		function planWeek( k, quiet ) {
			planned.add( k );
			const fresh = plan( weekNo( k ) * 131 + dateOf( k * 7 ).getFullYear() ).map( ( p ) => ( { type: p.type, D: k * 7 + p.d, h: p.h, status: 'idea', p: 0 } ) );
			items.push( ...fresh );
			if ( quiet ) {
				fresh.forEach( settle );
			}
			cols.forEach( ( c, D ) => c.classList.toggle( 'is-unplanned', ! planned.has( weekOf( D ) ) ) );
			render();
			if ( ! quiet ) {
				fresh.forEach( ( i ) => i.el && i.el.classList.add( 'is-pop' ) );
				say( fill( L.content_planned, { n: weekNo( k ), count: fresh.length } ), fill( L.content_planned2, { lead: R.lead } ), 'plan' + k, '', 2600 );
			}
		}

		/* ---------- drawing ---------- */
		function paint( i ) {
			const e = i.el;
			if ( ! e ) {
				return;
			}
			e.classList.toggle( 'is-idea', 'idea' === i.status );
			e.classList.toggle( 'is-ready', 'ready' === i.status );
			e.classList.toggle( 'is-live', 'live' === i.status );
			e.style.setProperty( '--p', 'prod' === i.status ? i.p.toFixed( 3 ) : 1 );
			e.lastChild.textContent = fmtH( i.h );
		}
		function ring( c, n, quiet ) {
			const r = document.createElement( 'i' );
			r.className = 'szs-cp__ring' + ( quiet ? ' is-on is-quiet' : '' );
			r.style.left = COL - 13 - n * 10 + 'px';
			c.appendChild( r );
			if ( ! quiet ) {
				void r.offsetWidth;
				r.classList.add( 'is-on' );
			}
		}
		function column( D ) {
			const c = document.createElement( 'div' );
			c.className = 'szs-cp__day';
			c.innerHTML = '<b></b>';
			c.firstChild.textContent = DAYS[ wd( D ) ];
			c.dataset.unplanned = L.content_unplanned;
			c.style.left = D * STEP + 'px';
			c.style.width = COL + 'px';
			c.classList.toggle( 'is-unplanned', ! planned.has( weekOf( D ) ) );
			strip.insertBefore( c, strip.firstChild );
			on( D ).filter( ( i ) => 'live' === i.status ).forEach( ( i, n ) => ring( c, n, true ) );
			return c;
		}
		/** Days from one before the view to two after; `from` keeps earlier ones while the view slides away. */
		function render( from ) {
			const lo = Math.min( view, undefined === from ? view : from ) - 1;
			const hi = view + 8;
			for ( let D = lo; D <= hi; D++ ) {
				if ( ! cols.has( D ) ) {
					cols.set( D, column( D ) );
				}
			}
			cols.forEach( ( c, D ) => {
				if ( D < lo || D > hi ) {
					c.remove();
					cols.delete( D );
				}
			} );
			for ( const i of items ) {
				const vis = i.D >= lo && i.D <= hi;
				if ( vis && ! i.el ) {
					i.el = document.createElement( 'em' );
					i.el.className = 'szs-cp__chip';
					i.el.innerHTML = `<span>${ esc( nameOf( i ) ) }</span><small></small>`;
					if ( i.type >= 0 ) {
						i.el.setAttribute( 'style', F[ i.type ].style );
					}
					strip.appendChild( i.el );
					paint( i );
				}
				if ( ! vis && i.el ) {
					i.el.remove();
					i.el = null;
				}
			}
			layout();
		}
		function layout() {
			const seen = {};
			items.filter( ( i ) => i.el ).sort( ( a, b ) => due( a ) - due( b ) ).forEach( ( i ) => {
				const k = ( seen[ i.D ] = ( seen[ i.D ] || 0 ) + 1 ) - 1;
				// `translate`, not left/top: a post moving to another day shifts no layout
				i.el.style.translate = `${ i.D * STEP + 4 }px ${ CHIP0 + k * ROW }px`;
				i.el.style.width = COL - 8 + 'px';
			} );
		}
		function setToday( live ) {
			cols.forEach( ( c, D ) => {
				c.classList.toggle( 'is-today', D === today );
				c.classList.toggle( 'is-past', live && D < today );
				c.firstChild.textContent = live && D === today ? L.content_today : DAYS[ wd( D ) ];
			} );
		}
		function roll( ms ) {
			strip.style.transition = ms ? `transform ${ ms }ms cubic-bezier(.5,0,.25,1)` : 'none';
			strip.style.transform = `translateX(${ ( -view * STEP ).toFixed( 1 ) }px)`;
		}

		/* ---------- the notification ---------- */
		function say( title, text, key, kind, hold ) {
			if ( ! tx || key === msgKey ) {
				return;
			}
			msgKey = key;
			notif.dataset.k = kind || '';
			const old = tx.lastElementChild;
			const nu = document.createElement( 'span' );
			nu.className = 'b-in';
			nu.innerHTML = `<b>${ esc( title ) }</b>${ esc( text ) }`;
			tx.appendChild( nu );
			void nu.offsetWidth;
			nu.classList.remove( 'b-in' );
			if ( old ) {
				old.classList.add( 'b-out' );
				setTimeout( () => old.remove(), 550 );
			}
			if ( hold ) {
				holdUntil = api.time.t + hold;
				lingerUntil = api.time.t + 700; // a beat to notice it, then time runs on
			}
		}
		function idle() {
			const next = items.filter( ( i ) => 'live' !== i.status && due( i ) > nowH() ).sort( ( a, b ) => due( a ) - due( b ) )[ 0 ];
			if ( ! next ) {
				return;
			}
			const state = { ready: L.content_s_ready, prod: L.content_s_making, idea: L.content_s_planned }[ next.status ];
			say( fill( L.content_next, { type: nameOf( next ), day: dayWord( next.D ), time: fmtH( next.h ) } ), state, 'next' + items.indexOf( next ) + next.status + ( next.D === today ), '' );
		}

		/* ---------- what happens ---------- */
		function publish( i ) {
			i.status = 'live';
			paint( i );
			const c = cols.get( i.D );
			if ( c ) {
				ring( c, on( i.D ).filter( ( x ) => 'live' === x.status ).length - 1 );
			}
			if ( i.el ) {
				i.el.classList.remove( 'is-pulse' );
				void i.el.offsetWidth;
				i.el.classList.add( 'is-pulse' );
			}
			say( fill( L.content_published, { type: nameOf( i ), day: DAYS[ wd( i.D ) ], time: fmtH( i.h ) } ), L.content_published2, 'live' + api.time.t, 'live', 2100 );
			const k = weekOf( i.D );
			if ( R.repurpose && i.type === reel && ! flags[ 'rep' + k ] && Math.random() < 0.5 ) {
				flags[ 'rep' + k ] = 1;
				events.push( { at: ( i.D + 1 ) * 24 + 10, fn: () => repurpose( i ) } );
			}
		}
		function slip( i ) {
			const to = [ 1, 2, 3 ].map( ( k ) => i.D + k ).find( ( D ) => planned.has( weekOf( D ) ) && on( D ).length < 2 && ! on( D ).some( ( x ) => x.type === i.type ) );
			if ( undefined === to ) {
				publish( i );
				return;
			}
			const why = items.some( ( x ) => x.rush && 'live' !== x.status ) ? L.content_moved_trend : L.content_moved_late;
			if ( i.el ) {
				i.el.classList.add( 'is-lift' );
			}
			i.D = to;
			setTimeout( () => {
				render();
				setTimeout( () => i.el && i.el.classList.remove( 'is-lift' ), 700 );
			}, 300 );
			say( fill( L.content_moved, { type: nameOf( i ), day: DAYS[ wd( to ) ], time: fmtH( i.h ) } ), why, 'slip' + api.time.t, 'move', 2600 );
		}
		const reel = F.findIndex( ( f ) => /reel/i.test( f.name ) );
		const carousel = F.findIndex( ( f ) => /carousel/i.test( f.name ) );
		function trend() {
			if ( reel < 0 ) {
				return;
			}
			const free = ( D ) => planned.has( weekOf( D ) ) && on( D ).length < 2 && ! on( D ).some( ( y ) => y.type === reel || Math.abs( y.h - 19.5 ) < 5 );
			const D = [ today + 1, today + 2 ].find( free );
			if ( undefined === D ) {
				return;
			}
			const i = { type: reel, D, h: 19.5, status: 'prod', p: 0, rush: true };
			items.push( i );
			render();
			if ( i.el ) {
				i.el.classList.add( 'is-pop' );
			}
			say( fill( L.content_trend, { type: F[ reel ].name, day: DAYS[ wd( D ) ], time: '19:30' } ), L.content_trend2, 'trend' + api.time.t, 'trend', 2600 );
		}
		function repurpose( src ) {
			if ( carousel < 0 ) {
				return;
			}
			const D = [ today + 2, today + 3, today + 4 ].find( ( x ) => planned.has( weekOf( x ) ) && on( x ).length < 2 && ! items.some( ( y ) => Math.abs( y.D - x ) <= 1 && y.type === carousel ) );
			if ( undefined === D ) {
				return;
			}
			const i = { type: carousel, D, h: F[ carousel ].times[ F[ carousel ].times.length - 1 ], status: 'idea', p: 0 };
			items.push( i );
			render();
			if ( i.el ) {
				i.el.classList.add( 'is-pop' );
			}
			say( fill( L.content_repurpose, { day: DAYS[ wd( src.D ) ], type: nameOf( src ), x: rnd( 1.8, 3.2 ).toFixed( 1 ) } ), fill( L.content_repurpose2, { day: DAYS[ wd( D ) ] } ), 'rep' + api.time.t, 'trend', 2600 );
		}
		/* Production: office hours, a few at a time, nearest deadline first; a trend jumps the queue. */
		function produce( dh ) {
			const t = nowH();
			const work = hour >= R.work[ 0 ] && hour < R.work[ 1 ];
			items.filter( ( i ) => ( 'idea' === i.status || 'prod' === i.status ) && due( i ) - t <= WINDOW && due( i ) > t )
				.sort( ( a, b ) => ( b.rush ? 1 : 0 ) - ( a.rush ? 1 : 0 ) || due( a ) - due( b ) )
				.forEach( ( i, k ) => {
					const active = k < R.cap;
					if ( active && 'idea' === i.status ) {
						i.status = 'prod';
					}
					if ( active && work ) {
						i.p = Math.min( 1, i.p + dh / ( R.work[ 1 ] - R.work[ 0 ] ) / ( i.rush ? 0.6 : 1 ) );
					}
					if ( 'prod' === i.status && i.p >= 1 ) {
						i.status = 'ready';
						if ( i.el ) {
							i.el.classList.remove( 'is-flash' );
							void i.el.offsetWidth;
							i.el.classList.add( 'is-flash' );
						}
					}
					paint( i );
				} );
		}

		/* ---------- time ---------- */
		function nextDay() {
			today++;
			days++;
			hour = H0;
			pace = rnd( 0.7, 1.45 );
			view = today - 1;
			render();
			roll( 620 );
			setToday( true );
			if ( 0 === wd( today ) && wno ) {
				const old = wno.lastElementChild;
				const nu = document.createElement( 'span' );
				nu.className = 'b-in';
				nu.textContent = fill( L.content_week_label, { n: weekNo( weekOf( today ) ) } );
				wno.appendChild( nu );
				void nu.offsetWidth;
				nu.classList.remove( 'b-in' );
				if ( old ) {
					old.classList.add( 'b-out' );
					setTimeout( () => old.remove(), 550 );
				}
			}
		}

		let started = false;
		let stopped = false;
		api.time.frame( ( dt ) => {
			if ( ! started || stopped ) {
				return;
			}
			const soon = items.some( ( i ) => i.D === today && 'live' !== i.status && i.h - hour < 1.5 && i.h - hour > -0.3 ) ||
				( wd( today ) === R.planDay && ! planned.has( weekOf( today ) + 1 ) && hour < 9 && 9 - hour < 1.5 );
			rate += ( ( soon ? SLOW : api.time.t < lingerUntil ? READ : FAST * pace ) - rate ) * Math.min( 1, dt / 200 );
			const dh = ( rate * dt ) / 1000;
			hour = Math.min( 24, hour + dh );
			if ( clock ) {
				clock.textContent = DAYS[ wd( today ) ] + ' ' + fmtH( Math.floor( hour * 4 ) / 4 );
			}
			produce( dh );
			for ( const i of items ) {
				if ( i.D === today && 'live' !== i.status && hour >= i.h ) {
					( 'ready' === i.status ? publish : slip )( i );
				}
			}
			events = events.filter( ( e ) => ( nowH() >= e.at ? ( e.fn(), false ) : true ) );
			const k = weekOf( today );
			if ( ( wd( today ) > R.planDay || ( wd( today ) === R.planDay && hour >= 9 ) ) && ! planned.has( k + 1 ) ) {
				planWeek( k + 1 );
			}
			if ( undefined === flags[ 'trend' + k ] ) {
				flags[ 'trend' + k ] = R.trend && Math.random() < 0.55 ? 0 : 1; // not every week has a trend worth chasing
			}
			if ( ! flags[ 'trend' + k ] && wd( today ) >= 1 && wd( today ) <= 3 && hour >= 10 && hour <= 18 && Math.random() < dh / 20 ) {
				flags[ 'trend' + k ] = 1;
				trend();
			}
			if ( api.time.t > holdUntil ) {
				idle();
			}
			if ( hour >= 24 ) {
				if ( 'loop' !== api.mode && days >= 6 ) {
					// "Play once": a week has gone by; rest on the evening of the last day.
					stopped = true;
					hour = 23.75;
					api.done();
					return;
				}
				nextDay();
			}
		} );

		// Date the plan right away: the visitor's week and day (not motion).
		strip.textContent = ''; // the server's resting week, redrawn from the same plan
		render();
		roll( 0 );
		setToday( false );
		if ( cfg.live && wno && wno.firstElementChild ) {
			wno.firstElementChild.textContent = fill( L.content_week_label, { n: weekNo( 0 ) } );
		}

		return {
			play() {
				if ( started ) {
					return;
				}
				started = true;
				// Switching on: the state of things right now, all at once; the planner moves to today.
				items.forEach( ( i ) => {
					settle( i );
					paint( i );
				} );
				cols.forEach( ( c, D ) => {
					c.querySelectorAll( '.szs-cp__ring' ).forEach( ( r ) => r.remove() );
					on( D ).filter( ( i ) => 'live' === i.status ).forEach( ( i, n ) => ring( c, n, true ) );
				} );
				if ( 0 === today ) {
					planWeek( -1, true );
				}
				if ( wd( today ) > R.planDay || ( wd( today ) === R.planDay && hour >= 9 ) ) {
					planWeek( 1, true );
				}
				const from = view;
				view = today - 1;
				render( from );
				roll( 1100 );
				setToday( true );
				api.time.wait( 1150 ).then( () => render() );
			},
			pause() {},
		};
	}

	if ( window.szwScenes ) {
		window.szwScenes.register( 'content', create );
	}
}() );
