/*
 * Bulletin Library shortcode generator.
 *
 * Builds the shortcode from whatever has been chosen, in the same shape as
 * the Sermon Library and Visitor Card generators. Like the Visitor Card one,
 * no jQuery: this screen has one small job, and one fewer dependency is one
 * fewer thing to conflict with whatever else a church has installed.
 *
 * Each display option carries its shortcode tag in data-tag, and each
 * control carries the attribute it sets in data-param and the shortcode's
 * own default in data-default. An attribute is written out only when it
 * differs from that default, so the result is as short as the choices allow.
 */
( function () {
	'use strict';

	var code = document.getElementById( 'scbl-sc-code' );
	var copy = document.getElementById( 'scbl-sc-copy' );

	if ( ! code ) {
		return;
	}

	var strings = window.scblSc || { copied: 'Copied', copy: 'Copy shortcode' };

	function chosen() {
		return document.querySelector( '.scbl-sc-radio:checked' );
	}

	function panelFor( radio ) {
		return radio ? document.querySelector( '.scbl-sc-panel[data-for="' + radio.value + '"]' ) : null;
	}

	// A checkbox group reports its ticked values in the order they appear,
	// which is the order the shortcode lists them in.
	function valueOf( el ) {
		if ( 'FIELDSET' === el.tagName ) {
			return Array.prototype.map.call( el.querySelectorAll( 'input[type="checkbox"]:checked' ), function ( box ) {
				return box.value;
			} ).join( ',' );
		}
		return el.value;
	}

	// A quote would end the attribute early and a square bracket would end
	// the shortcode, so neither can survive into a value.
	function clean( value ) {
		return String( value ).replace( /["\[\]]/g, '' ).trim();
	}

	function sync() {
		var radio = chosen();
		var panel = panelFor( radio );

		document.querySelectorAll( '.scbl-sc-option' ).forEach( function ( option ) {
			option.classList.toggle( 'is-selected', !! radio && option.contains( radio ) );
		} );
		document.querySelectorAll( '.scbl-sc-panel' ).forEach( function ( p ) {
			p.hidden = p !== panel;
		} );

		if ( ! radio ) {
			return;
		}

		var parts = [];

		if ( panel ) {
			// Rows that only matter after another choice, such as which section
			// leads on a grouped announcements page.
			panel.querySelectorAll( '[data-show-when]' ).forEach( function ( row ) {
				var rule  = row.getAttribute( 'data-show-when' ).split( '=' );
				var other = panel.querySelector( '[data-param="' + rule[0] + '"]' );
				row.hidden = ! other || valueOf( other ) !== rule[1];
			} );

			panel.querySelectorAll( '[data-param]' ).forEach( function ( el ) {
				var row = el.closest( '[data-show-when]' );
				if ( row && row.hidden ) {
					return;
				}

				var value = clean( valueOf( el ) );

				// Matching the default tells the shortcode nothing it does not
				// already assume, so it is left out.
				if ( '' === value || value === el.getAttribute( 'data-default' ) ) {
					return;
				}

				parts.push( el.getAttribute( 'data-param' ) + '="' + value + '"' );
			} );
		}

		code.textContent = '[' + radio.getAttribute( 'data-tag' ) + ( parts.length ? ' ' + parts.join( ' ' ) : '' ) + ']';
	}

	document.addEventListener( 'input', sync );
	document.addEventListener( 'change', sync );

	if ( copy ) {
		copy.addEventListener( 'click', function () {
			var text = code.textContent;

			var done = function () {
				copy.textContent = strings.copied;
				window.setTimeout( function () {
					copy.textContent = strings.copy;
				}, 1600 );
			};

			/*
			 * The clipboard API needs a secure context, and plenty of church
			 * sites are still edited over plain http on a local network. The
			 * textarea fallback is not elegant but it works everywhere.
			 */
			var fallback = function () {
				var scratch = document.createElement( 'textarea' );
				scratch.value = text;
				scratch.setAttribute( 'readonly', '' );
				scratch.style.position = 'absolute';
				scratch.style.left = '-9999px';
				document.body.appendChild( scratch );
				scratch.select();
				try {
					document.execCommand( 'copy' );
					done();
				} catch ( e ) {
					// Nothing sensible left to try. The shortcode is on screen
					// and can be selected by hand.
				}
				document.body.removeChild( scratch );
			};

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( done ).catch( fallback );
			} else {
				fallback();
			}
		} );
	}

	sync();
}() );
