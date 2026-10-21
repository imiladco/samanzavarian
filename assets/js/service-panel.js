/**
 * Service Panel runtime — decides when things move.
 *
 * Per panel there is at most one scene and any number of videos (the
 * scene's frames, or the panel's own image-or-video). They move only while:
 *   • the panel is on screen, in an open tab (or not in tabs), page visible;
 *   • the visitor has not asked for reduced motion;
 *   • nobody pressed pause. The visitor's pause is one choice for every
 *     panel and is remembered across pages (WCAG 2.2.2: a way to stop
 *     motion that lasts longer than five seconds).
 * A scene also follows the widget's Animation setting: "Still" never plays,
 * "Play once" plays its story the first time and then rests (done).
 * The pause button shows only while something actually moves.
 *
 * In the Elementor editor panels start paused — editing next to moving
 * pictures is distracting — and the same button previews one panel.
 *
 * Scene contract:
 *   window.szwScenes.register( name, ( root, api ) => ( { play(), pause() } ) )
 *   api.mode      'once' | 'loop' | 'static'
 *   api.time      the scene's own clock: it runs only while the scene plays,
 *                 so a scene written as a story (await api.time.wait( ms ))
 *                 simply holds still when paused and carries on after.
 *                 api.time.t (ms played), .wait( ms ), .frame( fn( dt ) )
 *   api.done()    a "once" story has finished; the scene rests from now on
 *   api.refresh() re-evaluate (e.g. after putting other videos on show)
 * play()/pause() may be called any number of times. Hand controls a factory
 * wires up always work.
 *
 * Chapters (optional; for a scroll-driven Service Tabs): a scene whose story
 * has chapters (the showreel's pieces, the direction's routes) may return
 *   drive( on )   the page's scroll takes over (on) or gives back (off): while
 *                 driven, the scene does not move on by itself
 *   show( k )     go to chapter k, with the scene's own transition; called
 *                 again before one ends, it goes straight to the latest k
 * and say how many it has in PHP (Service_Panel::chapters), so the page has
 * its height from the first paint. A scene without them is one chapter.
 *
 * Stage: every scene draws on a fixed design stage (.szs-stage). It is
 * scaled here to the panel's width and height (the stylesheet has a stepped
 * guess for the first paint). Transforms only: scaling never shifts layout.
 */
( function () {
	'use strict';

	/**
	 * Analytics: to Google Tag Manager's dataLayer when GTM is on the page,
	 * else to GA4's gtag() when that is installed directly, else nowhere.
	 * Never both, so nothing is counted twice. Parameters use GA4's
	 * snake_case so they map to custom dimensions as they are.
	 */
	window.szwTrack = window.szwTrack || function ( event, params ) {
		try {
			if ( window.google_tag_manager || ( Array.isArray( window.dataLayer ) && typeof window.gtag !== 'function' ) ) {
				window.dataLayer.push( Object.assign( { event }, params ) );
			} else if ( typeof window.gtag === 'function' ) {
				window.gtag( 'event', event, params );
			}
		} catch ( e ) {}
	};

	// A service's button was clicked (only buttons with analytics on carry data-sz-track).
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest && e.target.closest( '.sz-svc__btn[data-sz-track]' );
		if ( btn ) {
			window.szwTrack( 'sz_service_cta', { service_name: btn.dataset.szTrack, link_url: btn.getAttribute( 'href' ) || '' } );
		}
	} );

	const PREF = 'szwMotionPaused';
	const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const onScreen = new WeakMap();
	const panels = new Map(); // .sz-svc -> state
	const factories = {};

	const inEditor = () => document.body.classList.contains( 'elementor-editor-active' );

	/** A clock that only moves while its scene plays. */
	function makeClock() {
		const c = { t: 0, on: false, waiters: [], frames: new Set(), last: 0, raf: 0 };
		c.wait = ( ms ) => new Promise( ( r ) => c.waiters.push( { at: c.t + ms, r } ) );
		c.frame = ( fn ) => {
			c.frames.add( fn );
			return () => c.frames.delete( fn );
		};
		const loop = ( now ) => {
			if ( ! c.on ) {
				return;
			}
			const dt = Math.min( now - c.last, 100 );
			c.last = now;
			c.t += dt;
			c.frames.forEach( ( fn ) => fn( dt ) );
			if ( c.waiters.length ) {
				const due = c.waiters.filter( ( w ) => c.t >= w.at );
				c.waiters = c.waiters.filter( ( w ) => c.t < w.at );
				due.forEach( ( w ) => w.r() );
			}
			c.raf = requestAnimationFrame( loop );
		};
		c.run = ( on ) => {
			if ( on === c.on ) {
				return;
			}
			c.on = on;
			if ( on ) {
				c.last = performance.now();
				c.raf = requestAnimationFrame( loop );
			} else {
				cancelAnimationFrame( c.raf );
			}
		};
		return c;
	}

	/** Scale a scene's design stage to its panel: as large as fits, never larger than designed. */
	const stages = 'ResizeObserver' in window ? new ResizeObserver( ( entries ) => entries.forEach( ( e ) => fit( e.target ) ) ) : null;
	function fit( art ) {
		const stage = art.querySelector( ':scope > .szs-stage' );
		if ( ! stage || ! stage.offsetWidth ) {
			return;
		}
		// a scene may run 12% under the card below it, as designed
		const k = Math.min( 1, art.clientWidth / stage.offsetWidth, art.clientHeight / ( stage.offsetHeight * 0.88 ) );
		const ty = Math.max( 0, ( art.clientHeight - stage.offsetHeight * k ) / 2 );
		stage.style.setProperty( '--k', k.toFixed( 4 ) );
		stage.style.setProperty( '--ty', ty.toFixed( 1 ) + 'px' );
		stage.classList.add( 'is-fit' );
	}

	let userPaused = false;
	try {
		userPaused = window.localStorage.getItem( PREF ) === '1';
	} catch ( e ) {}

	const io = 'IntersectionObserver' in window
		? new IntersectionObserver( ( entries ) => {
			entries.forEach( ( e ) => onScreen.set( e.target, e.isIntersecting ) );
			refresh();
		}, { threshold: 0.15 } )
		: null;

	const paused = ( state ) => ( inEditor() ? ! state.preview : userPaused );

	function visible( svc ) {
		const tab = svc.closest( '.sz-tabs__panel' );
		return ( ! tab || tab.classList.contains( 'is-active' ) )
			&& ( io ? onScreen.get( svc ) === true : true )
			&& document.visibilityState === 'visible';
	}

	const shownVideos = ( svc ) => Array.from( svc.querySelectorAll( 'video[data-sz-autoplay]' ) ).filter( ( v ) => ! v.closest( '.szs-off' ) );

	function update( svc, state ) {
		const free = visible( svc ) && ! reduce.matches && ! paused( state );
		// a scene the scroll drives keeps its clock (its transitions run on it) even after a "once" story
		const sceneOn = !! state.ctrl && 'static' !== state.mode && ( ! state.done || !! state.driven );

		if ( state.root ) {
			// is-live: on screen and allowed to move (placeholder footage keeps
			// playing after a "once" story); is-running: the story itself plays.
			state.root.classList.toggle( 'is-live', free && 'static' !== state.mode );
			state.root.classList.toggle( 'is-running', !! state.ctrl && sceneOn && free );
		}

		if ( state.ctrl ) {
			state.root.classList.toggle( 'is-paused', paused( state ) && sceneOn );
			state.root.classList.toggle( 'is-settled', state.done );
			if ( sceneOn && free ) {
				state.time.run( true );
				state.ctrl.play();
			} else {
				state.time.run( false );
				state.ctrl.pause();
			}
		}

		const vids = Array.from( svc.querySelectorAll( 'video[data-sz-autoplay]' ) );
		const shown = shownVideos( svc );
		vids.forEach( ( v ) => {
			v.muted = true; // required for autoplay everywhere; set as a property too
			if ( free && shown.includes( v ) ) {
				if ( 'none' === v.preload ) {
					v.preload = 'auto'; // fetched only once it is actually shown
				}
				const p = v.play();
				if ( p && p.catch ) {
					p.catch( () => {} );
				}
			} else if ( ! v.paused ) {
				v.pause();
			}
		} );

		if ( state.button ) {
			const moving = ( sceneOn || shown.length > 0 ) && ! reduce.matches;
			state.button.hidden = ! moving;
			state.button.setAttribute( 'aria-pressed', paused( state ) ? 'true' : 'false' );
			state.button.setAttribute( 'aria-label', paused( state ) ? state.button.dataset.labelPlay : state.button.dataset.labelPause );
			state.button.classList.toggle( 'is-paused', paused( state ) );
		}
	}

	/** Re-evaluate every panel (tab switched, round changed, page shown, pause pressed…). */
	function refresh() {
		panels.forEach( ( state, svc ) => {
			if ( ! svc.isConnected ) {
				if ( state.ctrl ) {
					state.time.run( false );
					state.ctrl.pause();
				}
				panels.delete( svc );
				if ( io ) {
					io.unobserve( svc );
				}
				if ( stages && state.root ) {
					stages.unobserve( state.root );
				}
				return;
			}
			update( svc, state );
		} );
	}

	function scan( scope ) {
		( scope || document ).querySelectorAll( '.sz-svc' ).forEach( ( svc ) => {
			let state = panels.get( svc );
			if ( ! state ) {
				const root = svc.querySelector( '.szs[data-szs]' );
				state = {
					root,
					mode: root ? root.dataset.szsMotion || 'once' : 'loop',
					button: svc.querySelector( ':scope > .sz-svc__motion' ),
					ctrl: null,
					time: makeClock(),
					done: false,
					preview: false,
				};
				panels.set( svc, state );
				// chapters: the tabs' scroll tells the scene where it is (see the contract above)
				svc.addEventListener( 'sz:driven', ( e ) => {
					state.driven = !! e.detail.on;
					if ( state.ctrl && state.ctrl.drive ) {
						state.ctrl.drive( state.driven );
					}
				} );
				svc.addEventListener( 'sz:chapter', ( e ) => {
					state.chapter = e.detail.index;
					if ( state.ctrl && state.ctrl.show && state.driven ) {
						state.ctrl.show( state.chapter );
					}
				} );
				if ( io ) {
					io.observe( svc );
				}
				if ( root ) {
					fit( root );
					if ( stages ) {
						stages.observe( root );
					}
				}
			}
			// The scene's script may register after the panel is first seen.
			if ( state.root && ! state.ctrl && factories[ state.root.dataset.szs ] ) {
				state.ctrl = factories[ state.root.dataset.szs ]( state.root, {
					mode: state.mode,
					time: state.time,
					refresh,
					done() {
						state.done = true;
						refresh();
					},
				} );
				// the scroll may have spoken before the scene's script arrived
				if ( state.driven && state.ctrl.drive ) {
					state.ctrl.drive( true );
					if ( state.ctrl.show && undefined !== state.chapter ) {
						state.ctrl.show( state.chapter );
					}
				}
			}
		} );

		refresh();
	}

	// The pause / play button (one handler for every panel).
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest && e.target.closest( '.sz-svc__motion' );
		const state = btn && panels.get( btn.parentElement );
		if ( ! state ) {
			return;
		}
		if ( inEditor() ) {
			state.preview = ! state.preview; // preview this one panel
		} else {
			userPaused = ! userPaused; // the visitor's choice, for all of them
			try {
				window.localStorage.setItem( PREF, userPaused ? '1' : '0' );
			} catch ( err ) {}
		}
		refresh();
	} );

	window.szwScenes = {
		register( name, factory ) {
			factories[ name ] = factory;
			scan();
		},
	};

	document.addEventListener( 'sz:change', refresh );
	document.addEventListener( 'visibilitychange', refresh );
	if ( reduce.addEventListener ) {
		reduce.addEventListener( 'change', refresh );
	}

	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-service-panel.default', ( $el ) => {
			refresh(); // drop the instance the editor just replaced
			scan( $el[ 0 ] );
		} );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => scan() );
	} else {
		scan();
	}

	window.szwPanels = { scan, refresh };
}() );
