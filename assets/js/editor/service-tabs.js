/**
 * Editor-only: registers Service Tabs as a nested element (the same way
 * Elementor registers its own Tabs), and provides the helpers its
 * content_template() calls. Loaded in the editor window, never on the site.
 *
 * window.szwGlyphs is printed next to this file from Icon_Library::GLYPHS, so
 * the editor draws our icons with exactly the markup the server prints.
 */
( function () {
	'use strict';

	const esc = ( s ) => String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	/** Same rule as Icon_Library::svg(). */
	window.szwGlyph = function ( name, cls ) {
		const inner = ( window.szwGlyphs || {} )[ name ];
		if ( ! inner ) {
			return '';
		}
		return '<svg class="' + esc( cls || 'szi szi-' + name ) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
			+ ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + inner + '</svg>';
	};

	/** Any ICONS value to markup — ours inline, the rest through Elementor. */
	window.szwIcon = function ( view, icon ) {
		if ( ! icon || ! icon.value ) {
			return '';
		}
		if ( 'sz-icons' === icon.library ) {
			const m = /(?:^|\s)szi-([a-z0-9-]+)/.exec( typeof icon.value === 'string' ? icon.value : '' );
			return m ? window.szwGlyph( m[ 1 ], 'szi szi-' + m[ 1 ] ) : '';
		}
		const out = elementor.helpers.renderIcon( view, icon, { 'aria-hidden': true }, 'i', 'object' );
		return out && out.rendered ? out.value : '';
	};

	const htmlClass = ( v ) => String( v || '' ).replace( /%[a-fA-F0-9][a-fA-F0-9]/g, '' ).replace( /[^A-Za-z0-9_-]/g, '' );
	// close to sanitize_title(): accents dropped, lower case, dashes
	const slug = ( t ) => String( t || '' ).normalize( 'NFKD' ).replace( /[\u0300-\u036f]/g, '' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );

	/** Same rule as Service_Tabs::ids() (minus the page-wide de-duplication). */
	window.szwTabIds = function ( uid, index, anchor, title ) {
		const n = index + 1;
		let id = htmlClass( anchor ) || htmlClass( slug( title ) );
		if ( ! id || /^[0-9]/.test( id ) ) {
			id = 'sz-tab-' + uid + '-' + n;
		}
		return { tab: id, panel: 'sz-panel-' + uid + '-' + n };
	};

	function register() {
		const NestedView = $e.components.get( 'nested-elements' ).exports.NestedView;

		class View extends NestedView {
			filter( child, index ) {
				child.attributes.dataIndex = index + 1;
				return true;
			}

			// Each child container is a tabpanel; the tab script keeps the
			// active state, this gives it the ids and the first-render state.
			onAddChild( childView ) {
				const index = childView.model.attributes.dataIndex;
				const root = childView._parent.$el.find( '.sz-tabs' );
				const tab = root.find( '.sz-tabs__tab[data-sz-index="' + ( index - 1 ) + '"]' );

				childView.$el.addClass( 'sz-tabs__panel' ).attr( {
					id: tab.attr( 'aria-controls' ),
					role: 'tabpanel',
					'aria-labelledby': tab.attr( 'id' ),
					tabindex: '0',
				} );

				if ( 'true' === tab.attr( 'aria-selected' ) ) {
					childView.$el.addClass( 'is-active' );
				}
			}
		}

		const NestedModel = $e.components.get( 'nested-elements/nested-repeater' ).exports.NestedModelBase;

		/*
		 * Elementor gives a fresh id only to the first level of default
		 * children, and reuses the very same objects for every new widget. Our
		 * containers hold a Service Panel each, which would be saved with an
		 * empty id — and an empty id sends Elementor's element lookup into an
		 * endless recursion on the next load. So: a deep copy per widget, with
		 * an id at every level, repeater items included.
		 */
		class Model extends NestedModel {
			getDefaultChildren() {
				const uid = () => elementorCommon.helpers.getUniqueId().toString();

				// Repeater items are containers too, looked up by _id: fresh ones as well.
				const freshSettings = ( settings ) => {
					const out = JSON.parse( JSON.stringify( settings || {} ) );
					Object.keys( out ).forEach( ( key ) => {
						if ( Array.isArray( out[ key ] ) && out[ key ].every( ( item ) => item && 'object' === typeof item && ! Array.isArray( item ) ) ) {
							out[ key ].forEach( ( item ) => {
								item._id = uid();
							} );
						}
					} );
					return out;
				};

				const withIds = ( el ) => Object.assign( {}, el, {
					id: uid(),
					settings: freshSettings( el.settings ),
					elements: ( el.elements || [] ).map( withIds ),
				} );

				return this.config.defaults.elements.map( ( el ) => Object.assign( withIds( el ), { isLocked: true } ) );
			}
		}

		class ServiceTabs extends elementor.modules.elements.types.NestedElementBase {
			getType() {
				return 'sz-service-tabs';
			}

			getView() {
				return View;
			}

			getModel() {
				return Model;
			}
		}

		elementor.elementsManager.registerElementType( new ServiceTabs() );
	}

	elementorCommon.elements.$window.on( 'elementor/nested-element-type-loaded', register );
}() );
