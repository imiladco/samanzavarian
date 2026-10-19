/**
 * Editor preview: Elementor draws Text Editor and Heading widgets (and
 * Elementor 4's Paragraph and Heading) in the browser while you type, so
 * the server never sees them; turn the {icon:words} codes into pills here
 * too (same markup as Inline_Icons::pill).
 */
( function () {
	'use strict';
	var RE = /\{([a-z]+)(\*?):([^{}|<>]+?)(?:\|([^{}<>\s]+))?\}/g;

	function pill( icon, orange, words, link, i ) {
		var glyphs = window.szwGlyphs || {};
		var el = document.createElement( link ? 'a' : 'span' );
		el.className = 'sz-tok' + ( orange ? ' is-o' : '' ) + ( link ? ' is-link' : '' );
		el.style.setProperty( '--i', i );
		if ( link ) {
			el.setAttribute( 'href', link );
		}
		var parts = words.trim().split( /\s+/ ), lead = document.createElement( 'span' ), tile = document.createElement( 'span' );
		lead.className = 'sz-tok__lead';
		tile.className = 'sz-tok__tile';
		tile.setAttribute( 'aria-hidden', 'true' );
		tile.innerHTML = '<svg class="sz-tok__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + glyphs[ icon ] + '</svg>';
		lead.appendChild( tile );
		lead.appendChild( document.createTextNode( parts.shift() ) );
		el.appendChild( lead );
		if ( parts.length ) {
			el.appendChild( document.createTextNode( ' ' + parts.join( ' ' ) ) );
		}
		return el;
	}

	function convert( root ) {
		var glyphs = window.szwGlyphs || {}, i = 0;
		var walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT ), nodes = [], n;
		while ( ( n = walker.nextNode() ) ) {
			if ( n.nodeValue.indexOf( '{' ) > -1 && ! n.parentNode.closest( '.sz-tok' ) ) {
				nodes.push( n );
			}
		}
		nodes.forEach( function ( node ) {
			var text = node.nodeValue, frag = document.createDocumentFragment(), last = 0, m, changed = false;
			RE.lastIndex = 0;
			while ( ( m = RE.exec( text ) ) ) {
				if ( ! glyphs[ m[ 1 ] ] ) {
					continue;
				}
				frag.appendChild( document.createTextNode( text.slice( last, m.index ) ) );
				frag.appendChild( pill( m[ 1 ], m[ 2 ] === '*', m[ 3 ], m[ 4 ] || '', i++ ) );
				last = RE.lastIndex;
				changed = true;
			}
			if ( changed ) {
				frag.appendChild( document.createTextNode( text.slice( last ) ) );
				node.parentNode.replaceChild( frag, node );
			}
		} );
	}

	var TYPES = [ 'text-editor', 'heading', 'e-paragraph', 'e-heading' ];
	var SCOPE = TYPES.map( function ( t ) {
		return '.elementor-widget-' + t;
	} ).join( ',' );

	// never under the cursor: text being edited on the canvas stays as typed
	function editing( el ) {
		return !! ( el.closest( '[contenteditable="true"]' ) || el.querySelector( '[contenteditable="true"]' ) );
	}

	function scan() {
		document.querySelectorAll( SCOPE ).forEach( function ( w ) {
			if ( ! editing( w ) ) {
				convert( w );
			}
		} );
	}

	window.addEventListener( 'elementor/frontend/init', function () {
		TYPES.forEach( function ( name ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '.default', function ( $el ) {
				if ( ! editing( $el[ 0 ] ) ) {
					convert( $el[ 0 ] );
				}
			} );
		} );

		// Elementor 4's widgets redraw themselves without element_ready (and
		// after inline editing ends): catch every redraw, once per frame
		var queued = false;
		new MutationObserver( function () {
			if ( ! queued ) {
				queued = true;
				window.requestAnimationFrame( function () {
					queued = false;
					scan();
				} );
			}
		} ).observe( document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: [ 'contenteditable' ] } );
		scan();
	} );
}() );
