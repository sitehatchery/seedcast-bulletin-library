/*
 * Service Gallery box on Edit Service: add photos from the Media Library,
 * drag to reorder, remove. The thumbnails' order is the order saved, kept in
 * one hidden field as a comma separated list of attachment IDs.
 *
 * jQuery only for the Media Library frame and jQuery UI sortable, which
 * WordPress already ships on this screen.
 */
( function ( $ ) {
	'use strict';

	var box = document.querySelector( '.scbl-gallery-box' );
	if ( ! box || ! window.wp || ! wp.media ) {
		return;
	}

	var strings = window.scblGallery || {};
	var list    = box.querySelector( '.scbl-gallery-box__list' );
	var field   = box.querySelector( '.scbl-gallery-box__ids' );
	var hint    = box.querySelector( '.scbl-gallery-box__hint' );
	var frame   = null;

	function sync() {
		var ids = Array.prototype.map.call( list.querySelectorAll( '.scbl-gallery-box__item' ), function ( li ) {
			return li.getAttribute( 'data-id' );
		} );
		field.value = ids.join( ',' );
		if ( hint ) {
			hint.hidden = ids.length < 2;
		}
	}

	function addItem( attachment ) {
		var id = String( attachment.id );
		if ( list.querySelector( '[data-id="' + id + '"]' ) ) {
			return;
		}

		var sizes = attachment.sizes || {};
		var thumb = sizes.thumbnail || sizes.medium || { url: attachment.url };

		var li = document.createElement( 'li' );
		li.className = 'scbl-gallery-box__item';
		li.setAttribute( 'data-id', id );

		var img = document.createElement( 'img' );
		img.src = thumb.url;
		img.alt = '';

		var remove = document.createElement( 'button' );
		remove.type        = 'button';
		remove.className   = 'scbl-gallery-box__remove';
		remove.textContent = '×';
		remove.setAttribute( 'aria-label', strings.remove || 'Remove photo' );

		li.appendChild( img );
		li.appendChild( remove );
		list.appendChild( li );
	}

	box.querySelector( '.scbl-gallery-box__add' ).addEventListener( 'click', function ( e ) {
		e.preventDefault();

		if ( ! frame ) {
			frame = wp.media( {
				title:    strings.title || 'Add photos',
				button:   { text: strings.button || 'Add to gallery' },
				library:  { type: 'image' },
				multiple: 'add'
			} );

			// Start each visit with nothing ticked, so a photo removed from the
			// box is not quietly added back by last visit's selection.
			frame.on( 'open', function () {
				frame.state().get( 'selection' ).reset();
			} );

			frame.on( 'select', function () {
				frame.state().get( 'selection' ).each( function ( attachment ) {
					addItem( attachment.toJSON() );
				} );
				sync();
			} );
		}

		frame.open();
	} );

	list.addEventListener( 'click', function ( e ) {
		var remove = e.target.closest( '.scbl-gallery-box__remove' );
		if ( remove ) {
			e.preventDefault();
			remove.closest( '.scbl-gallery-box__item' ).remove();
			sync();
		}
	} );

	if ( $.fn.sortable ) {
		$( list ).sortable( {
			items:     '> .scbl-gallery-box__item',
			tolerance: 'pointer',
			update:    sync
		} );
	}
}( jQuery ) );
