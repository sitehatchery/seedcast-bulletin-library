/*
 * Bulletin Library announcement editor admin JS.
 *
 * Two behaviors on the announcement editor:
 *   1. "Ongoing" checkbox hides the end-date wrap.
 *   2. Contact name field autocompletes from window.scblContacts,
 *      filling name/email/phone when a suggestion is picked. The
 *      autocomplete is a proper combobox listbox with keyboard nav
 *      (Up/Down/Enter/Escape).
 *
 * The contacts list is provided by PHP via wp_localize_script.
 */
( function () {
	'use strict';

	// Ongoing checkbox → hide end-date wrap when checked.
	var cb   = document.getElementById( 'scbl_ann_ongoing' );
	var wrap = document.getElementById( 'scbl_ann_end_wrap' );
	if ( cb && wrap ) {
		cb.addEventListener( 'change', function () {
			wrap.classList.toggle( 'is-hidden', cb.checked );
		} );
	}

	// Contact autocomplete.
	var name  = document.getElementById( 'scbl_ann_contact_name' );
	var email = document.getElementById( 'scbl_ann_contact_email' );
	var phone = document.getElementById( 'scbl_ann_contact_phone' );
	var sug   = document.getElementById( 'scbl_ann_contact_suggestions' );
	if ( ! name || ! sug ) return;

	var activeIndex = -1;
	var currentMatches = [];

	function fillFrom( c ) {
		name.value  = c.name  || '';
		email.value = c.email || '';
		phone.value = c.phone || '';
		close();
	}

	function close() {
		sug.classList.remove( 'is-open' );
		sug.innerHTML = '';
		activeIndex = -1;
		currentMatches = [];
		name.setAttribute( 'aria-expanded', 'false' );
		name.removeAttribute( 'aria-activedescendant' );
	}

	function highlight( index ) {
		var rows = sug.querySelectorAll( '.scbl-contact-suggestion' );
		if ( ! rows.length ) return;
		if ( index < 0 ) index = rows.length - 1;
		if ( index >= rows.length ) index = 0;
		rows.forEach( function ( r, i ) {
			r.classList.toggle( 'is-active', i === index );
			r.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
		} );
		activeIndex = index;
		var id = rows[ index ].id;
		if ( id ) name.setAttribute( 'aria-activedescendant', id );
	}

	function render( q ) {
		sug.innerHTML = '';
		activeIndex = -1;
		if ( ! q || q.length < 1 ) { close(); return; }
		var lq = q.toLowerCase();
		currentMatches = ( window.scblContacts || [] ).filter( function ( c ) {
			return c.name && c.name.toLowerCase().indexOf( lq ) === 0;
		} ).slice( 0, 8 );
		if ( ! currentMatches.length ) { close(); return; }
		currentMatches.forEach( function ( c, i ) {
			var row = document.createElement( 'div' );
			row.className = 'scbl-contact-suggestion';
			row.id = 'scbl-contact-sug-' + i;
			row.setAttribute( 'role', 'option' );
			row.setAttribute( 'aria-selected', 'false' );
			row.textContent = c.name + ( c.email ? '  ' + c.email : '' );
			row.addEventListener( 'mousedown', function ( e ) { e.preventDefault(); fillFrom( c ); } );
			sug.appendChild( row );
		} );
		sug.classList.add( 'is-open' );
		name.setAttribute( 'aria-expanded', 'true' );
	}

	name.addEventListener( 'input', function () { render( name.value ); } );
	name.addEventListener( 'focus', function () { render( name.value ); } );
	name.addEventListener( 'blur',  function () { setTimeout( close, 150 ); } );
	name.addEventListener( 'keydown', function ( e ) {
		if ( ! currentMatches.length ) return;
		if ( e.key === 'ArrowDown' )        { e.preventDefault(); highlight( activeIndex + 1 ); }
		else if ( e.key === 'ArrowUp' )     { e.preventDefault(); highlight( activeIndex - 1 ); }
		else if ( e.key === 'Enter' && activeIndex >= 0 ) {
			e.preventDefault();
			if ( currentMatches[ activeIndex ] ) fillFrom( currentMatches[ activeIndex ] );
		}
		else if ( e.key === 'Escape' )      { close(); }
	} );
}() );
