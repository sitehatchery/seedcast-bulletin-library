/*
 * Bulletin Library announcement editor admin JS.
 *
 * Two behaviors on the announcement editor:
 *   1. Scheduling shows only the fields the chosen frequency uses,
 *      and adds or removes Multiday and Staggered rows.
 *   2. Contact name field autocompletes from window.scblContacts,
 *      filling name/email/phone when a suggestion is picked. The
 *      autocomplete is a proper combobox listbox with keyboard nav
 *      (Up/Down/Enter/Escape).
 *
 * The contacts list is provided by PHP via wp_localize_script.
 */
( function () {
	'use strict';

	// Scheduling. Which fields each frequency uses comes from
	// Schedule::fields() via data-fields: the same table PHP rendered the
	// initial state from and filters the save through. Hidden fields are
	// disabled so they neither submit nor block the form with a required
	// field the admin cannot see.
	var schedule = document.querySelector( '.scbl-schedule' );
	if ( schedule ) {
		initSchedule( schedule );
	}

	function initSchedule( box ) {
		var fields   = JSON.parse( box.getAttribute( 'data-fields' ) || '{}' );
		var freq     = document.getElementById( 'scbl_ann_frequency' );
		var pattern  = document.getElementById( 'scbl_ann_pattern' );
		var useDates = document.getElementById( 'scbl_ann_use_dates' );
		var help     = document.getElementById( 'scbl_ann_frequency_help' );
		var nextRow  = Date.now();

		if ( ! freq || ! pattern || ! useDates ) {
			return;
		}

		function kind() {
			return freq.value === 'recurring' ? pattern.value : freq.value;
		}

		function isVisible( name, k ) {
			var f = fields[ name ];
			if ( ! f ) {
				return false;
			}
			if ( f.show.indexOf( k ) !== -1 ) {
				return true;
			}
			return useDates.checked && f.toggled.indexOf( k ) !== -1;
		}

		function addRow( name ) {
			var tpl  = document.getElementById( 'scbl-schedule-tpl-' + name );
			var list = box.querySelector( '[data-rows="' + name + '"]' );
			if ( ! tpl || ! list ) {
				return;
			}
			var holder = document.createElement( 'div' );
			holder.innerHTML = tpl.innerHTML.replace( /__i__/g, String( nextRow++ ) ).trim();
			if ( holder.firstElementChild ) {
				list.appendChild( holder.firstElementChild );
			}
		}

		function sync() {
			var k = kind();

			// An empty day or date list is a dead end; start it with one row.
			if ( k === 'multiday' && ! box.querySelector( '[data-rows="days"] .scbl-schedule__item' ) ) {
				addRow( 'days' );
			}
			if ( k === 'staggered' && ! box.querySelector( '[data-rows="dates"] .scbl-schedule__item' ) ) {
				addRow( 'dates' );
			}

			box.querySelectorAll( '[data-field]' ).forEach( function ( wrap ) {
				var name     = wrap.getAttribute( 'data-field' );
				var on       = isVisible( name, k );
				var required = on && fields[ name ].required.indexOf( k ) !== -1;
				wrap.classList.toggle( 'is-hidden', ! on );
				wrap.querySelectorAll( 'input, select, button' ).forEach( function ( el ) {
					el.disabled = ! on;
					if ( el.hasAttribute( 'data-required' ) ) {
						el.required = required;
					}
				} );
			} );

			var option = freq.options[ freq.selectedIndex ];
			if ( help && option ) {
				help.textContent = option.getAttribute( 'data-help' ) || '';
			}
		}

		box.addEventListener( 'click', function ( e ) {
			var add = e.target.closest( '[data-add]' );
			if ( add ) {
				e.preventDefault();
				addRow( add.getAttribute( 'data-add' ) );
				sync();
				return;
			}
			var remove = e.target.closest( '.scbl-schedule__remove' );
			if ( remove ) {
				e.preventDefault();
				var item = remove.closest( '.scbl-schedule__item' );
				if ( item ) {
					item.remove();
				}
			}
		} );

		freq.addEventListener( 'change', sync );
		pattern.addEventListener( 'change', sync );
		useDates.addEventListener( 'change', sync );
		sync();
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
