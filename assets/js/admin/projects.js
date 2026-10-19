/**
 * Projects admin: "Choose video" opens the Media Library on videos only and
 * puts the chosen file's URL in the field.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-sz-pick-video]' );
		if ( ! btn || ! window.wp || ! window.wp.media ) {
			return;
		}
		e.preventDefault();
		const field = document.getElementById( 'sz-video' );
		const frame = window.wp.media( {
			title: ( window.szProjects || {} ).title,
			button: { text: ( window.szProjects || {} ).button },
			library: { type: 'video' },
			multiple: false,
		} );
		frame.on( 'select', () => {
			const file = frame.state().get( 'selection' ).first().toJSON();
			field.value = file.url || '';
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		frame.open();
	} );
}() );
