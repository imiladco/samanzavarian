/**
 * Quote & Reply — the arrival and the reply box (markup: Chat_Reply::render()).
 *
 * Arrival: the first time the widget scrolls into view, typing dots, the
 * bubble, the heart, then the quick replies. Only when it is out of view as
 * the script starts (an on-screen widget never blinks out to replay), never
 * with reduced motion, never in the editor.
 *
 * Reply: a quick reply types its message into the field; sending hands it
 * to the page's contact form (message box filled, the chip's option ticked,
 * scroll, first empty field focused), or to WhatsApp or email. The sent
 * message stays as an outgoing bubble. Analytics never include the text.
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

	window.szwTrack = window.szwTrack || function ( event, params ) {
		try {
			if ( window.google_tag_manager || ( Array.isArray( window.dataLayer ) && typeof window.gtag !== 'function' ) ) {
				window.dataLayer.push( Object.assign( { event }, params ) );
			} else if ( typeof window.gtag === 'function' ) {
				window.gtag( 'event', event, params );
			}
		} catch ( e ) {}
	};

	const done = new WeakSet();
	const media = ( q ) => window.matchMedia && window.matchMedia( q ).matches;
	const visible = ( el ) => !! ( el && el.getClientRects().length );

	/** Set a field's value so that frameworks (Elementor's forms included) notice. */
	function fill( el, value ) {
		const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
		const set = Object.getOwnPropertyDescriptor( proto, 'value' ).set;
		set.call( el, value );
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function mount( root ) {
		if ( ! root || done.has( root ) ) {
			return;
		}
		done.add( root );

		const reduce = media( '(prefers-reduced-motion: reduce)' );
		const edit = !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
		const L = Object.assign( { delivered: 'Delivered', opened: 'Opening WhatsApp…', mailed: 'Opening your email…', left: '{n} left', moved: '' }, JSON.parse( root.dataset.labels || '{}' ) );
		const track = root.dataset.track === '1';

		/* ---------------- the arrival ---------------- */
		root.querySelectorAll( '.sz-chat__chip' ).forEach( ( c, k ) => c.style.setProperty( '--k', k ) );
		if ( root.dataset.intro === '1' && ! reduce && ! edit && 'IntersectionObserver' in window ) {
			const r = root.getBoundingClientRect();
			if ( r.top >= window.innerHeight || r.bottom <= 0 ) {
				root.classList.add( 'is-armed' );
				const io = new IntersectionObserver( ( es ) => {
					if ( ! es.some( ( e ) => e.intersectionRatio >= 0.5 ) ) {
						return;
					}
					io.disconnect();
					const step = ( cls, ms ) => new Promise( ( ok ) => setTimeout( () => {
						root.classList.add( cls );
						ok();
					}, ms ) );
					step( 'is-typing', 150 )
						.then( () => step( 'is-said', 950 ) )
						.then( () => step( 'is-seen', 480 ) )
						.then( () => step( 'is-ready', 260 ) );
				}, { threshold: [ 0, 0.5 ] } );
				io.observe( root.querySelector( '.sz-chat__msg' ) );
			}
		}

		/* ---------------- the reply box ---------------- */
		const form = root.querySelector( '.sz-chat__reply' );
		if ( ! form ) {
			return;
		}
		const input = form.querySelector( '.sz-chat__input' ), send = form.querySelector( '.sz-chat__send' ), composer = form.querySelector( '.sz-chat__composer' );
		const left = form.querySelector( '.sz-chat__left' ), live = form.querySelector( '.sz-chat__live' ), sent = form.querySelector( '.sz-chat__sent' );
		const chips = [ ...form.querySelectorAll( '.sz-chat__chip' ) ];
		const max = parseInt( input.getAttribute( 'maxlength' ), 10 ) || 300;
		let chosen = null, typing = 0;

		const state = () => {
			const has = !! input.value.trim();
			send.disabled = ! has;
			composer.classList.toggle( 'is-ready', has );
			const rest = max - input.value.length;
			szwText( left, rest <= 40 ? L.left.replace( '{n}', rest ) : '' );
			left.classList.toggle( 'is-low', rest <= 10 );
		};
		input.addEventListener( 'input', () => {
			if ( ! typing && chosen && input.value !== chosen.dataset.text ) {
				chosen.setAttribute( 'aria-pressed', 'false' ); // edited by hand: no longer that quick reply
				chosen = null;
			}
			state();
		} );

		// a quick reply writes its message, letter by letter (under a second)
		chips.forEach( ( chip ) => chip.addEventListener( 'click', () => {
			chips.forEach( ( c ) => c.setAttribute( 'aria-pressed', String( c === chip ) ) );
			chosen = chip;
			const text = chip.dataset.text || '';
			clearTimeout( typing );
			if ( track ) {
				window.szwTrack( 'sz_reply_quick', { reply_label: chip.textContent.trim() } );
			}
			const end = () => {
				typing = 0;
				input.readOnly = false;
				state();
				input.focus( { preventScroll: true } );
				input.setSelectionRange( text.length, text.length );
			};
			if ( reduce ) {
				input.value = text;
				end();
				return;
			}
			input.readOnly = true;
			input.value = '';
			const each = Math.max( 8, Math.min( 22, 700 / Math.max( 1, text.length ) ) );
			let n = 0;
			const tick = () => {
				n = Math.min( text.length, n + ( each < 10 ? 2 : 1 ) );
				input.value = text.slice( 0, n );
				input.scrollLeft = input.scrollWidth;
				state();
				typing = n < text.length ? setTimeout( tick, each ) : 0;
				if ( ! typing ) {
					end();
				}
			};
			tick();
		} ) );

		/** The page's contact form's message box: the one named, else a form's textarea that is not ours. */
		const target = () => {
			const named = root.dataset.field && document.querySelector( root.dataset.field );
			if ( named ) {
				return named;
			}
			const boxes = [ ...document.querySelectorAll( '.elementor-form textarea, form textarea, textarea' ) ].filter( ( t ) => ! root.contains( t ) && visible( t ) && ! t.disabled && ! t.readOnly );
			return boxes[ 0 ] || null;
		};

		/** In the form, tick the option a quick reply names (by value or by its label's text). */
		const tick = ( scope, choice ) => {
			if ( ! scope || ! choice ) {
				return;
			}
			const want = choice.trim().toLowerCase();
			const label = ( el ) => ( ( el.labels && el.labels[ 0 ] && el.labels[ 0 ].textContent ) || ( el.closest( 'label' ) || {} ).textContent || '' ).trim().toLowerCase();
			const opt = [ ...scope.querySelectorAll( 'input[type=radio], input[type=checkbox]' ) ].find( ( i ) => i.value.trim().toLowerCase() === want || label( i ) === want );
			if ( opt ) {
				if ( ! opt.checked ) {
					opt.click();
				}
				return;
			}
			for ( const sel of scope.querySelectorAll( 'select' ) ) {
				const o = [ ...sel.options ].find( ( x ) => x.value.trim().toLowerCase() === want || x.textContent.trim().toLowerCase() === want );
				if ( o ) {
					sel.value = o.value;
					sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					return;
				}
			}
		};

		const bubble = ( text, note ) => {
			sent.innerHTML = '';
			const p = document.createElement( 'p' ), s = document.createElement( 'small' );
			p.className = 'sz-chat__out';
			szwText( p, text );
			szwText( s, note );
			sent.append( p, s );
			if ( ! reduce && p.animate ) {
				p.animate( [ { opacity: 0, transform: 'translateY(18px) scale(.94)' }, { opacity: 1, transform: 'none' } ], { duration: 420, easing: 'cubic-bezier(.34,1.4,.64,1)' } );
				s.animate( [ { opacity: 0 }, { opacity: 1 } ], { duration: 300, delay: 250, fill: 'backwards' } );
			}
		};

		// Enter on an empty field: the disabled send button blocks the submit, so nudge here
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' && ! input.value.trim() ) {
				e.preventDefault();
				composer.classList.remove( 'is-nudge' );
				void composer.offsetWidth;
				composer.classList.add( 'is-nudge' );
			}
		} );

		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			const text = input.value.trim();
			if ( ! text || typing ) {
				composer.classList.remove( 'is-nudge' );
				void composer.offsetWidth;
				composer.classList.add( 'is-nudge' );
				input.focus();
				return;
			}
			let how = root.dataset.action || 'form';
			const box = how === 'form' ? target() : null;
			if ( how === 'form' && ! box ) {
				how = root.dataset.phone ? 'whatsapp' : ( root.dataset.email ? 'email' : 'none' );
			}
			if ( how === 'none' ) {
				return;
			}
			const choice = chosen ? chosen.dataset.choice : '';
			bubble( text, how === 'form' ? L.delivered : ( how === 'whatsapp' ? L.opened : L.mailed ) );
			input.value = '';
			chips.forEach( ( c ) => c.setAttribute( 'aria-pressed', 'false' ) );
			chosen = null;
			state();
			if ( track ) {
				window.szwTrack( 'sz_reply_send', { destination: how, message_length: text.length, from_quick_reply: !! choice } );
			}

			if ( how === 'form' ) {
				fill( box, text );
				const scope = box.form || box.closest( 'form' ) || document;
				tick( scope, choice );
				szwText( live, L.moved );
				const go = () => {
					( box.form || box ).scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'start' } );
					setTimeout( () => {
						const empty = [ ...scope.querySelectorAll( 'input:not([type=hidden]):not([type=radio]):not([type=checkbox]):not([type=submit]), textarea, select' ) ].find( ( f ) => visible( f ) && ! f.value && f !== box );
						( empty || box ).focus( { preventScroll: true } );
					}, reduce ? 0 : 750 );
				};
				setTimeout( go, reduce ? 0 : 650 );
			} else if ( how === 'whatsapp' ) {
				window.open( 'https://wa.me/' + root.dataset.phone + '?text=' + encodeURIComponent( text ), '_blank', 'noopener' );
			} else {
				window.location.href = 'mailto:' + root.dataset.email + '?subject=' + encodeURIComponent( root.dataset.subject || '' ) + '&body=' + encodeURIComponent( text );
			}
		} );

		state();
	}

	function mountAll( scope ) {
		( scope || document ).querySelectorAll( '[data-sz-chat]' ).forEach( mount );
	}

	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-chat.default', ( $el ) => mountAll( $el[ 0 ] ) );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => mountAll() );
	} else {
		mountAll();
	}
}() );
