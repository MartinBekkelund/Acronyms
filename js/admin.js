/**
 * Admin JavaScript for the Acronyms plugin.
 *
 * Handles delete confirmation dialogs.
 *
 * @package Acronyms
 */

( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.acronyms-delete-link' );

		if ( ! link ) {
			return;
		}

		if ( ! window.confirm( acronymsAdmin.confirmDelete ) ) {
			event.preventDefault();
		}
	} );
} )();
