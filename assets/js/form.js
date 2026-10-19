/**
 * Project Form (markup: Project_Form::render(); the server side: Leads).
 *
 * - Validation in words beside the fields: on leaving a field once it has
 *   something (or was wrong), and on sending; the first wrong field gets
 *   focus. The server checks again.
 * - Timeline and budget are radios that clear when tapped again.
 * - The message box: examples take turns while it is empty and in focus;
 *   the counter shows from 80% of the limit.
 * - A draft of everything typed is kept in this browser until it is sent
 *   ("Your draft is back · Clear").
 * - Sending: JSON to the REST route, with the time spent on the form (a
 *   person takes longer than a bot). Sent: the form gives way to a
 *   confirmation that names the day of the reply, working days only, in the
 *   owner's time zone. Not sent: a message with the email address; the
 *   draft stays.
 */
( function () {
	'use strict';

	const done = new WeakSet();
	const reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const store = {
		get( k ) {
			try {
				return JSON.parse( window.localStorage.getItem( k ) || 'null' );
			} catch ( e ) {
				return null;
			}
		},
		set( k, v ) {
			try {
				window.localStorage.setItem( k, JSON.stringify( v ) );
			} catch ( e ) {}
		},
		drop( k ) {
			try {
				window.localStorage.removeItem( k );
			} catch ( e ) {}
		},
	};
	const track = ( event, params ) => window.szwTrack && window.szwTrack( event, params || {} );

	/** The reply day: `n` working days after today in time zone `tz`, skipping `off` (0 = Sunday). */
	function replyDay( n, off, tz ) {
		let parts;
		try {
			parts = new Intl.DateTimeFormat( 'en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' } ).format( new Date() ).split( '-' ).map( Number );
		} catch ( e ) {
			const d = new Date();
			parts = [ d.getFullYear(), d.getMonth() + 1, d.getDate() ];
		}
		const d = new Date( Date.UTC( parts[ 0 ], parts[ 1 ] - 1, parts[ 2 ], 12 ) );
		let left = n;
		while ( left > 0 ) {
			d.setUTCDate( d.getUTCDate() + 1 );
			if ( off.indexOf( d.getUTCDay() ) < 0 ) {
				left--;
			}
		}
		return new Intl.DateTimeFormat( document.documentElement.lang || 'en', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' } ).format( d );
	}

	function mount( root ) {
		if ( ! root || done.has( root ) ) {
			return;
		}
		done.add( root );

		const cfg = JSON.parse( root.dataset.config || '{}' ), L = cfg.l || {};
		const form = root.querySelector( '.sz-pf__form' ), doneCard = root.querySelector( '.sz-pf__done' );
		const btn = form.querySelector( '.sz-pf__btn' ), btxt = form.querySelector( '.sz-pf__btxt' );
		const toast = form.querySelector( '.sz-pf__toast' ), draftBar = form.querySelector( '.sz-pf__draft' ), note = form.querySelector( '.sz-pf__note' );
		const showDraft = ( on ) => {
			draftBar.hidden = ! on;
			note.hidden = on;
		};
		const msg = form.elements.message, count = form.querySelector( '.sz-pf__count' ), ex = form.querySelector( '.sz-pf__ex' );
		const editor = !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
		const KEY = 'szpf-draft-' + root.dataset.id;
		const opened = Date.now();
		let touched = false;

		/* ---------- checks ---------- */

		const val = ( n ) => ( form.elements[ n ] ? String( form.elements[ n ].value || '' ).trim() : '' );
		const rules = {
			name: () => val( 'name' ).length >= 2,
			email: () => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( val( 'email' ) ),
			phone: () => ! val( 'phone' ) || ( /^[+0-9 ()\-.]+$/.test( val( 'phone' ) ) && ( ( d ) => d.length >= 7 && d.length <= 15 )( val( 'phone' ).replace( /\D/g, '' ) ) ),
			message: () => val( 'message' ).length >= 10,
			link: () => ! val( 'link' ) || /^https?:\/\/[^\s/]+\.\S+/i.test( val( 'link' ) ),
		};
		const groups = [ [ 'name', 'email', 'phone' ], [ 'message', 'link' ] ];

		function check( only ) {
			let first = null;
			groups.forEach( ( g ) => {
				let say = '';
				g.forEach( ( n ) => {
					const el = form.elements[ n ];
					if ( ! el ) {
						return;
					}
					const wrong = ! rules[ n ]();
					const shown = ! only || only.indexOf( n ) > -1 || el.getAttribute( 'aria-invalid' ) === 'true';
					if ( ! shown ) {
						return;
					}
					el.closest( '.sz-pf__f' ).classList.toggle( 'is-bad', wrong );
					el.setAttribute( 'aria-invalid', wrong ? 'true' : 'false' );
					if ( wrong && ! say ) {
						say = L[ 'e_' + n ] || '';
						first = first || el;
					}
				} );
				const err = form.elements[ g[ 0 ] ] && document.getElementById( form.elements[ g[ 0 ] ].getAttribute( 'aria-describedby' ) );
				if ( err ) {
					err.textContent = say;
				}
			} );
			return first;
		}

		Object.keys( rules ).forEach( ( n ) => {
			const el = form.elements[ n ];
			if ( ! el ) {
				return;
			}
			el.addEventListener( 'blur', () => {
				if ( el.value || el.getAttribute( 'aria-invalid' ) === 'true' ) {
					check( [ n ] );
				}
			} );
			el.addEventListener( 'input', () => el.getAttribute( 'aria-invalid' ) === 'true' && check( [ n ] ) );
		} );

		/* ---------- radios that clear when tapped again ---------- */

		form.querySelectorAll( 'input[type=radio]' ).forEach( ( r ) => {
			let was = false;
			r.addEventListener( 'pointerdown', () => ( was = r.checked ) );
			r.addEventListener( 'click', () => {
				if ( was ) {
					r.checked = false;
					was = false;
					form.dispatchEvent( new Event( 'input' ) );
				}
			} );
		} );

		/* ---------- the message box ---------- */

		const max = cfg.max || 1200;
		const counter = () => {
			if ( ! count ) {
				return;
			}
			const n = msg.value.length;
			count.textContent = `${ n } / ${ max }`;
			count.classList.toggle( 'is-on', n >= max * 0.8 );
			count.classList.toggle( 'is-max', n >= max );
		};
		msg.addEventListener( 'input', counter );

		if ( ex && ( cfg.examples || [] ).length > 1 && ! reduce ) {
			let k = 0, timer = 0;
			const next = () => {
				ex.classList.add( 'is-swap' );
				setTimeout( () => {
					k = ( k + 1 ) % cfg.examples.length;
					ex.textContent = cfg.examples[ k ];
					ex.classList.remove( 'is-swap' );
				}, 350 );
			};
			msg.addEventListener( 'focus', () => {
				clearInterval( timer );
				timer = setInterval( () => ! msg.value && next(), 3200 );
			} );
			msg.addEventListener( 'blur', () => clearInterval( timer ) );
		}

		/* ---------- the draft ---------- */

		const fields = [ 'name', 'email', 'phone', 'message', 'link' ];
		const snapshot = () => {
			const d = { services: [ ...form.querySelectorAll( 'input[name="services[]"]:checked' ) ].map( ( i ) => i.value ) };
			fields.forEach( ( n ) => form.elements[ n ] && ( d[ n ] = form.elements[ n ].value ) );
			[ 'timeline', 'budget' ].forEach( ( n ) => {
				const c = form.querySelector( `input[name="${ n }"]:checked` );
				d[ n ] = c ? c.value : '';
			} );
			return d;
		};
		const empty = ( d ) => ! fields.some( ( n ) => ( d[ n ] || '' ).trim() );
		let saveT = 0;
		form.addEventListener( 'input', () => {
			if ( ! touched ) {
				touched = true;
				track( 'sz_form_start', {} );
			}
			if ( editor ) {
				return;
			}
			clearTimeout( saveT );
			saveT = setTimeout( () => {
				const d = snapshot();
				return empty( d ) ? store.drop( KEY ) : store.set( KEY, d );
			}, 400 );
		} );
		form.addEventListener( 'change', () => form.dispatchEvent( new Event( 'input' ) ) );

		const restore = ( d ) => {
			fields.forEach( ( n ) => form.elements[ n ] && d[ n ] && ( form.elements[ n ].value = d[ n ] ) );
			form.querySelectorAll( 'input[name="services[]"]' ).forEach( ( i ) => ( i.checked = ( d.services || [] ).indexOf( i.value ) > -1 ) );
			[ 'timeline', 'budget' ].forEach( ( n ) => form.querySelectorAll( `input[name="${ n }"]` ).forEach( ( i ) => ( i.checked = i.value === d[ n ] ) ) );
			counter();
		};
		const saved = ! editor && store.get( KEY );
		if ( saved && ! empty( saved ) ) {
			restore( saved );
			showDraft( true );
		}
		draftBar.querySelector( '.sz-pf__clear' ).addEventListener( 'click', () => {
			store.drop( KEY );
			form.reset();
			counter();
			showDraft( false );
			form.querySelectorAll( '[aria-invalid]' ).forEach( ( el ) => {
				el.removeAttribute( 'aria-invalid' );
				el.closest( '.sz-pf__f' ).classList.remove( 'is-bad' );
			} );
			form.querySelectorAll( '.sz-pf__err' ).forEach( ( e ) => ( e.textContent = '' ) );
			( form.elements.name || msg ).focus();
		} );

		/* ---------- sending ---------- */

		const busy = ( on ) => {
			btn.disabled = on;
			btn.setAttribute( 'aria-busy', on ? 'true' : 'false' );
			btxt.textContent = on ? L.sending : L.send;
		};
		const fail = ( text ) => {
			toast.textContent = text;
			toast.hidden = false;
		};

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			toast.hidden = true;
			const wrong = check();
			if ( wrong ) {
				wrong.focus();
				return;
			}
			if ( editor ) {
				return;
			}
			const d = snapshot();
			d.website = form.elements.website ? form.elements.website.value : '';
			d.t = Date.now() - opened;
			d.page = window.location.href.split( '#' )[ 0 ];
			busy( true );
			try {
				const r = await fetch( root.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify( d ) } );
				const j = await r.json().catch( () => ( {} ) );
				if ( r.status === 429 ) {
					fail( L.e_rate );
					return;
				}
				if ( ! r.ok || ! j.ok ) {
					// the server disagreed about a field: show it as the browser would have
					if ( j.errors && j.errors.length && j.errors.every( ( x ) => rules[ x ] ) ) {
						j.errors.forEach( ( x ) => form.elements[ x ] && form.elements[ x ].setAttribute( 'aria-invalid', 'true' ) );
					}
					throw new Error( 'not sent' );
				}
				store.drop( KEY );
				showDraft( false );
				track( 'sz_form_sent', { services: ( d.services || [] ).join( ', ' ), has_phone: !! d.phone, timeline: d.timeline, budget: d.budget } );
				sent( d );
			} catch ( err ) {
				fail( ( L.e_fail || '%s' ).replace( '%s', cfg.email || '' ) );
				track( 'sz_form_error', {} );
			} finally {
				busy( false );
			}
		} );

		/* ---------- sent ---------- */

		function sent( d ) {
			const first = ( d.name || '' ).split( /\s+/ )[ 0 ];
			const day = replyDay( cfg.days || 2, cfg.off || [ 5 ], cfg.tz || 'Asia/Tehran' );
			doneCard.querySelector( '.sz-pf__oktext' ).textContent = ( L.ok_text || '%1$s %2$s' ).replace( '%1$s', first ).replace( '%2$s', day );
			const sum = doneCard.querySelector( '.sz-pf__sum' );
			sum.innerHTML = '';
			[ ...( d.services || [] ), d.timeline, d.budget ].filter( Boolean ).forEach( ( t ) => {
				const li = document.createElement( 'li' );
				li.textContent = t;
				sum.appendChild( li );
			} );
			// the card takes the form's place at the form's height (nothing jumps), then eases to its own size
			const h = form.offsetHeight;
			doneCard.style.minHeight = h + 'px';
			form.hidden = true;
			doneCard.hidden = false;
			if ( ! reduce ) {
				setTimeout( () => {
					doneCard.style.transition = 'min-height .6s cubic-bezier(.22,.8,.24,1)';
					doneCard.style.minHeight = Math.min( h, 420 ) + 'px';
				}, 900 );
			} else {
				doneCard.style.minHeight = Math.min( h, 420 ) + 'px';
			}
			doneCard.focus( { preventScroll: true } );
			const top = root.getBoundingClientRect().top;
			if ( top < 0 ) {
				root.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'start' } );
			}
		}

		doneCard.querySelector( '.sz-pf__again' ).addEventListener( 'click', () => {
			form.reset();
			counter();
			showDraft( false );
			doneCard.hidden = true;
			doneCard.style.transition = '';
			form.hidden = false;
			( form.elements.name || msg ).focus();
		} );

		counter();
	}

	const all = () => document.querySelectorAll( '[data-sz-pf]' ).forEach( mount );
	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/sz-form.default', ( $el ) => mount( $el[ 0 ].querySelector( '[data-sz-pf]' ) ) );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
