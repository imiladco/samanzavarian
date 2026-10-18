/**
 * Service Tabs — behaviour for .sz-tabs (markup: Service_Tabs::render()).
 *
 * WAI-ARIA tabs with automatic activation: one tab stop; arrow keys move and
 * select; Home/End jump. aria-orientation follows the layout on screen, which
 * can change per Elementor breakpoint, so it is re-read on every resize.
 *
 * Panels are found by position (the n-th child of the stage), not by id: in
 * the editor Elementor renders the children itself, and they may arrive after
 * the widget's own markup.
 *
 * Links: a tab with a Link ID opens from any <a href="#that-id"> on the page,
 * and from the address on load. With "update address" on, opening a tab
 * writes its ID back with history.replaceState (no history spam, no jump).
 *
 * Other scripts can open a tab:  el.dispatchEvent(new CustomEvent('sz:select', {detail: index}))
 * and listen for changes:        el.addEventListener('sz:change', e => e.detail.index)
 */
( function () {
	'use strict';

	const TABS = '.sz-tabs__tab';
	const instances = new WeakMap();

	class SzTabs {
		constructor( root ) {
			this.root = root;
			this.list = root.querySelector( '.sz-tabs__list' );
			this.hl = root.querySelector( '.sz-tabs__hl' );
			this.stage = root.querySelector( '.sz-tabs__stage' );
			this.writeHash = root.dataset.szHash === '1';

			this.onClick = this.onClick.bind( this );
			this.onKey = this.onKey.bind( this );
			this.onHash = this.onHash.bind( this );
			this.place = this.place.bind( this );

			this.list.addEventListener( 'click', this.onClick );
			this.list.addEventListener( 'keydown', this.onKey );
			root.addEventListener( 'sz:select', ( e ) => this.select( +e.detail ) );
			window.addEventListener( 'hashchange', this.onHash );

			if ( 'ResizeObserver' in window ) {
				this.ro = new ResizeObserver( this.place );
				this.ro.observe( this.list );
			} else {
				window.addEventListener( 'resize', this.place );
			}

			this.onHash( null, true );
			this.sync();
			this.place();

			// Fonts change tab sizes; place again once they are in, then let the
			// highlight animate from here on.
			( document.fonts ? document.fonts.ready : Promise.resolve() ).then( () => {
				this.place();
				requestAnimationFrame( () => root.classList.add( 'is-ready' ) );
			} );
		}

		tabs() {
			return Array.from( this.list.querySelectorAll( TABS ) );
		}

		panels() {
			return this.stage ? Array.from( this.stage.children ).filter( ( el ) => ! el.classList.contains( 'elementor-empty-view' ) ) : [];
		}

		current() {
			const i = this.tabs().findIndex( ( t ) => t.getAttribute( 'aria-selected' ) === 'true' );
			return i < 0 ? 0 : i;
		}

		/** Make index the open tab. */
		select( index, opts = {} ) {
			const tabs = this.tabs();

			if ( ! tabs.length ) {
				return;
			}

			index = Math.max( 0, Math.min( tabs.length - 1, index | 0 ) );
			const changed = index !== this.current();

			tabs.forEach( ( tab, i ) => {
				const on = i === index;
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.tabIndex = on ? 0 : -1;
			} );

			this.sync( index );
			this.place();

			const tab = tabs[ index ];

			if ( opts.focus ) {
				tab.focus( { preventScroll: true } );
			}

			// In a row that scrolls, bring the chosen tab into view inside the row
			// only — never scroll the page.
			if ( this.list.scrollWidth > this.list.clientWidth ) {
				this.list.scrollTo( { left: tab.offsetLeft, behavior: opts.instant ? 'auto' : 'smooth' } );
			}

			if ( changed && opts.user && this.writeHash && tab.id && ! tab.id.startsWith( 'sz-tab-' ) && history.replaceState ) {
				history.replaceState( null, '', '#' + tab.id );
			}

			if ( changed ) {
				this.root.dispatchEvent( new CustomEvent( 'sz:change', { bubbles: true, detail: { index, tab, panel: this.panels()[ index ] || null } } ) );
			}
		}

		/** Panels follow the tabs: active class, ids for assistive tech. */
		sync( index = this.current() ) {
			const tabs = this.tabs();

			this.panels().forEach( ( panel, i ) => {
				const on = i === index;
				panel.classList.add( 'sz-tabs__panel' );
				panel.classList.toggle( 'is-active', on );

				if ( tabs[ i ] ) {
					panel.setAttribute( 'role', 'tabpanel' );
					panel.setAttribute( 'aria-labelledby', tabs[ i ].id );
					if ( ! panel.id ) {
						panel.id = tabs[ i ].getAttribute( 'aria-controls' );
					}
					if ( ! panel.hasAttribute( 'tabindex' ) ) {
						panel.tabIndex = 0;
					}
				}
			} );
		}

		/** Put the highlight under the active tab, and keep orientation honest. */
		place() {
			const tab = this.tabs()[ this.current() ];

			if ( ! tab || ! this.hl ) {
				return;
			}

			const row = getComputedStyle( this.list ).flexDirection.startsWith( 'row' );
			this.list.setAttribute( 'aria-orientation', row ? 'horizontal' : 'vertical' );

			const s = this.root.style;
			s.setProperty( '--szt-hl-x', tab.offsetLeft + 'px' );
			s.setProperty( '--szt-hl-y', tab.offsetTop + 'px' );
			s.setProperty( '--szt-hl-w', tab.offsetWidth + 'px' );
			s.setProperty( '--szt-hl-h', tab.offsetHeight + 'px' );
		}

		onClick( e ) {
			const tab = e.target.closest( TABS );

			if ( tab && this.list.contains( tab ) ) {
				this.select( this.tabs().indexOf( tab ), { user: true } );
			}
		}

		onKey( e ) {
			const tabs = this.tabs();
			const i = tabs.indexOf( e.target.closest( TABS ) );

			if ( i < 0 ) {
				return;
			}

			const rtl = getComputedStyle( this.list ).direction === 'rtl';
			const next = rtl ? 'ArrowLeft' : 'ArrowRight';
			const prev = rtl ? 'ArrowRight' : 'ArrowLeft';
			const n = tabs.length;
			const map = {
				ArrowDown: ( i + 1 ) % n,
				[ next ]: ( i + 1 ) % n,
				ArrowUp: ( i - 1 + n ) % n,
				[ prev ]: ( i - 1 + n ) % n,
				Home: 0,
				End: n - 1,
			};

			if ( e.key in map ) {
				e.preventDefault();
				this.select( map[ e.key ], { focus: true, user: true } );
			}
		}

		/** Open the tab whose id is in the address (on load and on #link clicks). */
		onHash( e, initial = false ) {
			const id = decodeURIComponent( location.hash.slice( 1 ) );

			if ( ! id ) {
				return;
			}

			const index = this.tabs().findIndex( ( t ) => t.id === id );

			if ( index < 0 ) {
				return;
			}

			this.select( index, { instant: initial } );

			if ( ! initial ) {
				this.root.scrollIntoView( { block: 'start', behavior: matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
			}
		}

		destroy() {
			this.list.removeEventListener( 'click', this.onClick );
			this.list.removeEventListener( 'keydown', this.onKey );
			window.removeEventListener( 'hashchange', this.onHash );
			window.removeEventListener( 'resize', this.place );
			if ( this.ro ) {
				this.ro.disconnect();
			}
		}
	}

	function mount( root ) {
		if ( ! root || instances.has( root ) ) {
			return instances.get( root );
		}
		const inst = new SzTabs( root );
		instances.set( root, inst );
		return inst;
	}

	function mountAll( scope ) {
		( scope || document ).querySelectorAll( '[data-sz-tabs]' ).forEach( mount );
	}

	window.szwTabs = { mount, get: ( root ) => instances.get( root ) };

	// Inside Elementor (frontend and editor preview) mount through its handler
	// API, which also tells us which tab the editor panel is working on.
	window.addEventListener( 'elementor/frontend/init', () => {
		const Base = window.elementorModules && window.elementorModules.frontend.handlers.Base;

		if ( ! Base ) {
			return;
		}

		class Handler extends Base {
			onInit() {
				super.onInit();
				const root = this.$element[ 0 ].querySelector( '[data-sz-tabs]' );
				this.tabs = mount( root );

				if ( ! this.tabs ) {
					return;
				}

				// A child container may render after us in the editor.
				requestAnimationFrame( () => this.tabs.sync() );

				if ( ! window.elementorFrontend.isEditMode() ) {
					return;
				}

				// The editor re-renders the widget on every settings change. Reopen
				// the tab being worked on instead of jumping back to the first.
				const remembered = this.getEditSettings( 'activeItemIndex' );
				if ( remembered ) {
					this.tabs.select( remembered - 1, { instant: true } );
				}

				// And remember a tab opened by clicking it in the preview, the way
				// Elementor's own Tabs do (this also opens its item in the panel).
				root.addEventListener( 'sz:change', ( e ) => {
					const editor = window.parent && window.parent.$e && window.parent.elementor;
					if ( ! editor || this.getEditSettings( 'activeItemIndex' ) === e.detail.index + 1 ) {
						return;
					}
					try {
						window.parent.$e.run( 'document/repeater/select', {
							container: window.parent.elementor.getContainer( this.getID() ),
							index: e.detail.index + 1,
							options: { useHistory: false },
						} );
					} catch ( err ) {}
				} );
			}

			// The editor sets this when a tab's repeater item or anything inside
			// its container is selected: show that tab.
			onEditSettingsChange( name, value ) {
				if ( 'activeItemIndex' === name && this.tabs ) {
					this.tabs.select( value - 1, { instant: true } );
				}
			}

			onDestroy() {
				const root = this.$element[ 0 ].querySelector( '[data-sz-tabs]' );
				const inst = root && instances.get( root );
				if ( inst ) {
					inst.destroy();
					instances.delete( root );
				}
				super.onDestroy();
			}
		}

		window.elementorFrontend.elementsHandler.attachHandler( 'sz-service-tabs', Handler );
	} );

	// Without Elementor's frontend script (e.g. a cached page that dropped it),
	// still work.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => window.elementorFrontend || mountAll() );
	} else if ( ! window.elementorFrontend ) {
		mountAll();
	}
}() );
