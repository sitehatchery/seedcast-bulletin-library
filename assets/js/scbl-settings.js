/*
 * Bulletin Library admin JS for the Settings page: the default fallback image
 * picker, and the Quick Links rows.
 *
 * The picker opens the WordPress media library, stores the chosen
 * attachment's ID in a hidden input, and updates the inline preview.
 * Clearing zeroes the ID so the plugin falls back to its shipped default
 * image.
 */
( function () {
	'use strict';

	/*
	 * Quick Links: add and remove rows. Each new row is cloned from the
	 * template PHP printed, with __i__ swapped for an index no row is using,
	 * so the names stay unique whatever has been removed along the way.
	 */
	function initLinks() {
		var rows = document.querySelector( '[data-scbl-links-rows]' );
		var add  = document.querySelector( '[data-scbl-links-add]' );
		var tpl  = document.getElementById( 'scbl-links-tpl' );
		if ( ! rows || ! add || ! tpl ) return;

		var next = rows.querySelectorAll( '.scbl-links-row' ).length;

		add.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var holder = document.createElement( 'div' );
			holder.innerHTML = tpl.innerHTML.replace( /__i__/g, String( next++ ) ).trim();
			var row = holder.firstElementChild;
			if ( ! row ) return;
			rows.appendChild( row );
			var label = row.querySelector( 'input' );
			if ( label ) label.focus();
		} );

		rows.addEventListener( 'click', function ( e ) {
			var remove = e.target.closest( '.scbl-links-row__remove' );
			if ( ! remove ) return;
			e.preventDefault();
			var row = remove.closest( '.scbl-links-row' );
			if ( row ) row.remove();
		} );
	}

	function init() {
		initLinks();

		var wrap = document.querySelector( '.scbl-default-image' );
		if ( ! wrap ) return;

		var btn     = document.getElementById( 'scbl_default_service_image_btn' );
		var clear   = document.getElementById( 'scbl_default_service_image_clear' );
		var hidden  = document.getElementById( 'scbl_default_service_image' );
		var preview = wrap.querySelector( '.scbl-default-image__preview' );
		if ( ! btn || ! hidden || ! preview ) return;

		var l10n = window.scblDefaultImageL10n || {};

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( ! ( window.wp && window.wp.media ) ) return;

			var frame = window.wp.media( {
				title:    l10n.pickTitle  || 'Choose image',
				button:   { text: l10n.pickButton || 'Use this image' },
				library:  { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				if ( ! att || ! att.id ) return;
				hidden.value = att.id;

				var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
				preview.innerHTML = '';
				var img = document.createElement( 'img' );
				img.src = url;
				img.alt = '';
				img.style.maxWidth = '200px';
				img.style.height   = 'auto';
				preview.appendChild( img );

				if ( clear ) {
					clear.style.display = '';
					clear.hidden = false;
				}
			} );

			frame.open();
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				hidden.value = '0';
				preview.innerHTML = '';
				clear.style.display = 'none';
				clear.hidden = true;
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
