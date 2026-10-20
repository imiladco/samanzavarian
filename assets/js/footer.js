/**
 * Footer widgets (markup: Footer_Links, Back_To_Top, Mail_Row ::render()).
 * - Links and sticker: their entrance plays once, the first time they come
 *   into view, and only if they were out of view as the page opened.
 * - Back to Top: under the pointer the ring speeds up and eases back down
 *   after; a click sends the arrow off and glides the page to the top.
 * - Mail Row: Copy copies (or opens the mail app if the browser will not)
 *   and says so, then turns back.
 * Nothing is added to the page or taken out: classes and text only.
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
	const done = new WeakSet();
	const calm = () => window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/** Plays `is-armed` → `is-lit` once, when `el` is first seen; nothing if it is in view already. */
	function entrance( el, after ) {
		if ( calm() || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		const r = el.getBoundingClientRect();
		if ( r.top < window.innerHeight && r.bottom > 0 ) {
			return;
		}
		el.classList.add( 'is-armed' );
		const io = new IntersectionObserver( ( es ) => {
			if ( es.some( ( e ) => e.isIntersecting ) ) {
				io.disconnect();
				el.classList.add( 'is-lit' );
				el.classList.remove( 'is-armed' );
				if ( after ) {
					setTimeout( () => el.classList.remove( 'is-lit' ), after );
				}
			}
		}, { threshold: 0.35 } );
		io.observe( el );
	}

	function totop( el ) {
		if ( el.classList.contains( 'has-slap' ) ) {
			entrance( el, 1000 );
		}
		const ring = el.querySelector( '.sz-totop__ring' );
		// the ring's speed eases toward `to` (the CSS animation keeps its place)
		let rate = 1, to = 1, raf = 0;
		const step = () => {
			const a = ring && ring.getAnimations ? ring.getAnimations()[ 0 ] : null;
			rate += ( to - rate ) * 0.08;
			if ( Math.abs( to - rate ) < 0.01 ) {
				rate = to;
			}
			if ( a ) {
				a.playbackRate = rate;
			}
			raf = rate === to ? 0 : requestAnimationFrame( step );
		};
		const speed = ( v ) => {
			if ( ! ring || calm() || ! el.classList.contains( 'has-spin' ) ) {
				return;
			}
			to = v;
			raf = raf || requestAnimationFrame( step );
		};
		el.addEventListener( 'pointerenter', ( e ) => e.pointerType === 'mouse' && speed( 5 ) );
		el.addEventListener( 'pointerleave', () => speed( 1 ) );
		el.addEventListener( 'click', ( e ) => {
			const href = el.getAttribute( 'href' ) || '';
			if ( href !== '#top' && href !== '#' ) {
				return;
			}
			const target = href === '#top' && document.getElementById( 'top' );
			e.preventDefault();
			if ( ! calm() ) {
				el.classList.remove( 'is-launch' );
				void el.offsetWidth;
				el.classList.add( 'is-launch' );
			}
			const smooth = calm() ? 'auto' : 'smooth';
			if ( target ) {
				target.scrollIntoView( { behavior: smooth } );
			} else {
				window.scrollTo( { top: 0, behavior: smooth } );
			}
			// the reader's place moves with them: the next Tab starts at the top
			const first = target || document.body;
			if ( first !== document.body && ! first.hasAttribute( 'tabindex' ) ) {
				first.setAttribute( 'tabindex', '-1' );
			}
			first.focus( { preventScroll: true } );
		} );
		el.addEventListener( 'animationend', ( e ) => e.animationName === 'szt-launch' && el.classList.remove( 'is-launch' ) );
	}

	async function copyText( s ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			await navigator.clipboard.writeText( s );
			return;
		}
		const t = document.createElement( 'textarea' ); // only where the clipboard API is missing
		t.value = s;
		t.setAttribute( 'readonly', '' );
		t.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
		document.body.appendChild( t );
		t.select();
		const ok = document.execCommand( 'copy' );
		t.remove();
		if ( ! ok ) {
			throw new Error( 'copy' );
		}
	}

	function mailrow( el ) {
		const btn = el.querySelector( '.sz-mailrow__copy' );
		const sr = el.querySelector( '.sz-mailrow__sr' );
		const mail = el.querySelector( '.sz-mailrow__mail' );
		if ( ! btn ) {
			return;
		}
		let back = 0;
		btn.addEventListener( 'click', async () => {
			try {
				await copyText( btn.dataset.copy );
			} catch ( e ) {
				if ( mail ) {
					window.location.href = mail.href;
				}
				return;
			}
			btn.classList.add( 'is-done' );
			if ( sr ) {
				szwText( sr, ( btn.dataset.done || 'Copied' ) + ': ' + btn.dataset.copy );
			}
			clearTimeout( back );
			back = setTimeout( () => {
				btn.classList.remove( 'is-done' );
				if ( sr ) {
					szwText( sr, ' ' );
				}
			}, 1800 );
		} );
	}

	function mount( el ) {
		if ( ! el || done.has( el ) ) {
			return;
		}
		done.add( el );
		if ( el.hasAttribute( 'data-sz-totop' ) ) {
			totop( el );
		} else if ( el.hasAttribute( 'data-sz-mailrow' ) ) {
			mailrow( el );
		} else if ( el.hasAttribute( 'data-sz-flinks' ) && el.classList.contains( 'has-rise' ) ) {
			entrance( el );
		}
	}

	const SEL = '[data-sz-flinks],[data-sz-totop],[data-sz-mailrow]';
	const all = () => document.querySelectorAll( SEL ).forEach( mount );
	window.addEventListener( 'elementor/frontend/init', () => {
		[ 'sz-flinks', 'sz-totop', 'sz-mailrow' ].forEach( ( w ) => window.elementorFrontend.hooks.addAction( `frontend/element_ready/${ w }.default`, ( $el ) => $el[ 0 ].querySelectorAll( SEL ).forEach( mount ) ) );
	} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', all );
	} else {
		all();
	}
}() );
