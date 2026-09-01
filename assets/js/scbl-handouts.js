/*
 * Bulletin Library admin JS: handouts row builder.
 *
 * Manages three interactions inside the Handouts meta box: appending a new
 * row from the template, removing a row, and opening the WordPress media
 * picker to attach a file. i18n strings for the picker come from
 * window.scblHandoutsL10n.
 */
( function () {
	'use strict';

	var container = document.getElementById( 'scbl-handouts' );
	var addBtn    = document.getElementById( 'scbl-handout-add' );
	var tpl       = document.getElementById( 'scbl-handout-template' );
	if ( ! container || ! addBtn || ! tpl ) return;

	var l10n = window.scblHandoutsL10n || {};

	addBtn.addEventListener( 'click', function () {
		var idx  = parseInt( container.getAttribute( 'data-index' ), 10 ) || 0;
		var html = tpl.innerHTML.replace( /__INDEX__/g, idx );
		var wrap = document.createElement( 'div' );
		wrap.innerHTML = html.trim();
		// firstElementChild skips any text nodes the parser may have created
		// from whitespace before the row div.
		var newRow = wrap.firstElementChild;
		if ( ! newRow ) return;
		container.appendChild( newRow );
		container.setAttribute( 'data-index', idx + 1 );
	} );

	container.addEventListener( 'click', function ( e ) {
		var removeBtn = e.target.closest( '.scbl-handout-remove' );
		if ( removeBtn ) {
			var row = removeBtn.closest( '.scbl-handout' );
			if ( row ) row.remove();
			return;
		}
		var pickBtn = e.target.closest( '.scbl-handout-pick' );
		if ( pickBtn ) {
			e.preventDefault();
			pickFile( pickBtn );
		}
	} );

	function pickFile( btn ) {
		if ( ! ( window.wp && window.wp.media ) ) {
			window.alert( l10n.mediaMissing || 'Media library not loaded. Please refresh the page and try again.' );
			return;
		}
		var frame = window.wp.media( {
			title:    l10n.pickTitle  || 'Choose file',
			button:   { text: l10n.pickButton || 'Use this file' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			if ( ! att || ! att.id ) return;
			var row = btn.closest( '.scbl-handout' );
			if ( ! row ) return;
			var fileInput = row.querySelector( '.scbl-handout-file-id' );
			var fileName  = row.querySelector( '.scbl-handout-file-name' );
			if ( fileInput ) fileInput.value = att.id;
			if ( fileName )  fileName.textContent = att.filename || att.title || '';
		} );
		frame.open();
	}
}() );
