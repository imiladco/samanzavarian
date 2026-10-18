/**
 * Service Panel runtime — decides when things move.
 *
 * A panel is "live" while it is on screen, in an open tab (or not in tabs at
 * all), the page is in the foreground and the visitor has not asked for
 * reduced motion. Only then:
 *   • its videos play (data-sz-autoplay; never the autoplay attribute), and
 *   • its scene plays (each scene script registers a controller here).
 * Everything else is paused, so four tabs of scenes and video cost what one
 * visible tab costs.
 *
 * Scene contract:
 *   window.szwScenes.register( 'ai', ( root ) => ( { play(), pause() } ) )
 * root is the scene's .szs element. play() and pause() may be called any
 * number of times; with reduced motion play() is never called, and the
 * server markup — the design's finished state — is what shows. A factory
 * may also wire up controls the visitor uses by hand; those work always.
 */
( function () {
	'use strict';

	const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const onScreen = new WeakMap();
	const controllers = new WeakMap();
	const factories = {};
	const tracked = new Set();

	const io = 'IntersectionObserver' in window
		? new IntersectionObserver( ( entries ) => entries.forEach( ( e ) => {
			onScreen.set( e.target, e.isIntersecting );
			update( e.target );
		} ), { threshold: 0.15 } )
		: null;

	/** On screen, in an open tab, not in a hidden round, page visible. */
	function live( el ) {
		const panel = el.closest( '.sz-tabs__panel' );
		return ( ! panel || panel.classList.contains( 'is-active' ) )
			&& ! el.closest( '.szs-off' )
			&& ( io ? onScreen.get( el ) === true : true )
			&& document.visibilityState === 'visible'
			&& ! reduce.matches;
	}

	function update( el ) {
		if ( el.tagName === 'VIDEO' ) {
			if ( live( el ) ) {
				const p = el.play();
				if ( p && p.catch ) {
					p.catch( () => {} );
				}
			} else if ( ! el.paused ) {
				el.pause();
			}
			return;
		}

		const ctrl = controllers.get( el );
		if ( ctrl ) {
			if ( live( el ) ) {
				ctrl.play();
			} else {
				ctrl.pause();
			}
		}
	}

	function track( el ) {
		if ( tracked.has( el ) ) {
			return;
		}
		tracked.add( el );
		if ( io ) {
			io.observe( el );
		}
		update( el );
	}

	function scan( scope ) {
		const root = scope || document;

		root.querySelectorAll( '.sz-svc video[data-sz-autoplay]' ).forEach( ( v ) => {
			v.muted = true; // required for autoplay everywhere; set as a property too
			track( v );
		} );

		root.querySelectorAll( '.sz-svc .szs[data-szs]' ).forEach( ( el ) => {
			const factory = factories[ el.dataset.szs ];
			// Created even with reduced motion (a scene may have controls the
			// visitor uses by hand); live() keeps play() from ever being called.
			if ( ! factory || controllers.has( el ) ) {
				return;
			}
			controllers.set( el, factory( el, { refresh } ) );
			track( el );
		} );
	}

	/** Re-evaluate everything (tab switched, round changed, page shown…). */
	function refresh() {
		tracked.forEach( ( el ) => {
			if ( ! el.isConnected ) {
				tracked.delete( el );
				if ( io ) {
					io.unobserve( el );
				}
				const ctrl = controllers.get( el );
				if ( ctrl ) {
					ctrl.pause();
				}
				return;
			}
			update( el );
		} );
	}

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
