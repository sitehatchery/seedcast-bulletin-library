/*
 * Sunday admin JS: service editor interactions.
 *
 * Two interactions on the Service editor:
 *   1. Remove a copy row (X button on the copy card)
 *   2. Add a suggestion (moves it into the copies list as a new row)
 *
 * There is no dismiss / persisted state. If the admin removes a
 * suggestion (by simply not adding it), it reappears next time they
 * open the service - the model is intentionally forgetful so admins
 * always have a way to reconsider.
 *
 * Suggestion payloads come in via window.scblSuggestionData (JSON
 * emitted by PHP), keyed by source_id. Any field the source announcement
 * has - including nested structured fields like contact - is available
 * for the copy row to inherit.
 */
( function () {
	'use strict';

	// Update button - pulls current source values into an existing copy's
	// hidden inputs, replacing whatever was there. The visible read-only
	// display doesn't refresh in place; the notice is hidden so the admin
	// sees that "update completed," and on save the new snapshot is stored.
	// (Live-rerendering the visible summary would require duplicating the
	// PHP rendering logic in JS; simpler to trust the admin to save.)
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.scbl-copy-update' );
		if ( ! btn ) return;
		var row = btn.closest( '.scbl-copy' );
		if ( ! row ) return;

		var id  = row.getAttribute( 'data-source-id' ) || '0';
		var src = ( window.scblSuggestionData && window.scblSuggestionData[ id ] ) || null;
		if ( ! src ) {
			// Source data isn't available (source may have been deleted since
			// the page loaded, or the data blob wasn't emitted). Bail out.
			btn.disabled = true;
			return;
		}
		var contact = src.contact || {};

		// Overwrite the copy's hidden inputs with current source values.
		var set = function ( selector, value ) {
			var el = row.querySelector( selector );
			if ( el ) el.value = value == null ? '' : String( value );
		};
		set( '.scbl-copy-title',         src.title    || '' );
		set( '.scbl-copy-body',          src.body     || '' );
		set( '.scbl-copy-time',          src.time     || '' );
		set( '.scbl-copy-location',      src.location || '' );
		set( '.scbl-copy-link',          src.link     || '' );
		set( '.scbl-copy-contact-name',  contact.name  || '' );
		set( '.scbl-copy-contact-email', contact.email || '' );
		set( '.scbl-copy-contact-phone', contact.phone || '' );
		set( '.scbl-copy-image-id',      src.image_id || 0 );
		set( '.scbl-copy-start',         src.start    || '' );
		set( '.scbl-copy-end',           src.end      || '' );

		// Refresh the visible display so the admin can see what will save.
		var titleEl = row.querySelector( '.scbl-copy__title' );
		if ( titleEl ) titleEl.textContent = src.title || '';
		var descEl = row.querySelector( '.scbl-copy__desc' );
		if ( descEl ) descEl.textContent = src.body || '';

		// Refresh the visible thumbnail. If the row didn't have an image
		// slot before (source had no image at Add time), insert one.
		var imgWrap = row.querySelector( '.scbl-copy__image' );
		if ( src.image_url ) {
			if ( ! imgWrap ) {
				imgWrap = document.createElement( 'div' );
				imgWrap.className = 'scbl-copy__image';
				var rowFlex = row.querySelector( '.scbl-copy__row' );
				if ( rowFlex ) rowFlex.insertBefore( imgWrap, rowFlex.firstChild );
			}
			imgWrap.innerHTML = '<img src="' + escapeAttr( src.image_url ) + '" alt="" class="scbl-copy__thumb" />';
		} else if ( imgWrap ) {
			imgWrap.remove();
		}

		// Hide the "changed" notice.
		var notice = btn.closest( '.scbl-copy__update-hint' );
		if ( notice ) {
			notice.classList.add( 'scbl-copy__update-hint--updated' );
			notice.textContent = 'Updated - save the service to keep these changes.';
		}
	} );

	// Remove a copy row - the form just won't include it on save.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.scbl-copy-remove' );
		if ( ! btn ) return;
		var row = btn.closest( '.scbl-copy' );
		if ( row ) row.remove();
	} );

	// Remove a program row.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.scbl-program-remove' );
		if ( ! btn ) return;
		var row = btn.closest( '.scbl-program-row' );
		if ( row ) row.remove();
	} );

	// Update a diverged program copy - same pattern as announcement copies,
	// but reads from window.scblProgramSourceData (Programs and Announcements
	// keep separate data blobs so they never collide on shared source IDs).
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.scbl-program-update' );
		if ( ! btn ) return;
		var row = btn.closest( '.scbl-program-row' );
		if ( ! row ) return;

		var id  = row.getAttribute( 'data-source-id' ) || '0';
		var src = ( window.scblProgramSourceData && window.scblProgramSourceData[ id ] ) || null;
		if ( ! src ) { btn.disabled = true; return; }

		var set = function ( selector, value ) {
			var el = row.querySelector( selector );
			if ( el ) el.value = value == null ? '' : String( value );
		};
		set( '.scbl-program-title',    src.title    || '' );
		set( '.scbl-program-body',     src.body     || '' );
		set( '.scbl-program-time',     src.time     || '' );
		set( '.scbl-program-link',     src.link     || '' );
		set( '.scbl-program-image-id', src.image_id || 0 );

		// Refresh the visible display.
		var titleEl = row.querySelector( '.scbl-program-row__title' );
		if ( titleEl ) titleEl.textContent = src.title || '';
		var timeEl = row.querySelector( '.scbl-program-row__time' );
		if ( timeEl ) timeEl.textContent = src.time || '';
		var descEl = row.querySelector( '.scbl-program-row__desc' );
		if ( descEl ) descEl.textContent = src.body || '';

		var imgWrap = row.querySelector( '.scbl-program-row__image' );
		if ( src.image_url ) {
			if ( ! imgWrap ) {
				imgWrap = document.createElement( 'div' );
				imgWrap.className = 'scbl-program-row__image';
				var rowFlex = row.querySelector( '.scbl-program-row__row' );
				if ( rowFlex ) rowFlex.insertBefore( imgWrap, rowFlex.firstChild );
			}
			imgWrap.innerHTML = '<img src="' + escapeAttr( src.image_url ) + '" alt="" class="scbl-program-row__thumb" />';
		} else if ( imgWrap ) {
			imgWrap.remove();
		}

		var notice = row.querySelector( '.scbl-program-diff' );
		if ( notice ) {
			notice.classList.add( 'scbl-copy__update-hint--updated' );
			notice.textContent = 'Updated - save the service to keep these changes.';
		}
	} );

	// Add a suggestion - build a new copy row and append it, pulling the
	// full record from window.scblSuggestionData so nested fields
	// (contact, image) transfer cleanly rather than being fragilely
	// serialized into HTML data attributes.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.scbl-review-accept' );
		if ( ! btn ) return;
		var item = btn.closest( '.scbl-review-item' );
		if ( ! item ) return;

		var id  = item.getAttribute( 'data-source-id' ) || '0';
		var src = ( window.scblSuggestionData && window.scblSuggestionData[ id ] ) || {};
		var contact = src.contact || {};

		var data = {
			id:            id,
			title:         src.title    || '',
			body:          src.body     || '',
			link:          src.link     || '',
			time:          src.time     || '',
			location:      src.location || '',
			contact_name:  contact.name  || '',
			contact_email: contact.email || '',
			contact_phone: contact.phone || '',
			image_id:      src.image_id  || 0,
			image_url:     src.image_url || '',
			start:         src.start    || '',
			end:           src.end      || ''
		};

		var container = document.getElementById( 'scbl-copies' );
		if ( ! container ) return;

		var next = container.querySelectorAll( '.scbl-copy' ).length;

		var row = document.createElement( 'div' );
		row.className = 'scbl-copy';
		row.setAttribute( 'data-index', next );
		row.setAttribute( 'data-source-id', data.id );

		// Read-only display of the copied announcement, matching the shape
		// rendered by ServiceEditor::render_copy_row on the server. The
		// snapshot is committed via hidden inputs so save preserves it
		// verbatim - editing lives on the source Announcement, not here.
		var meta = [];
		if ( data.time )     meta.push( escapeText( data.time ) );
		if ( data.location ) meta.push( escapeText( data.location ) );

		var contactBits = [];
		if ( data.contact_name )  contactBits.push( escapeText( data.contact_name ) );
		if ( data.contact_email ) contactBits.push( '· ' + escapeText( data.contact_email ) );
		if ( data.contact_phone ) contactBits.push( '· ' + escapeText( data.contact_phone ) );

		row.innerHTML =
			'<div class="scbl-copy__row">' +
				( data.image_url ? '<div class="scbl-copy__image"><img src="' + escapeAttr( data.image_url ) + '" alt="" class="scbl-copy__thumb" /></div>' : '' ) +
				'<div class="scbl-copy__body">' +
					'<div class="scbl-copy__title">' + escapeText( data.title ) + '</div>' +
					( data.body ? '<div class="scbl-copy__desc">' + escapeText( data.body ) + '</div>' : '' ) +
					( meta.length ? '<div class="scbl-copy__meta">' + meta.join( ' · ' ) + '</div>' : '' ) +
					( contactBits.length ? '<div class="scbl-copy__meta">' + contactBits.join( ' ' ) + '</div>' : '' ) +
					( data.link ? '<div class="scbl-copy__meta"><a href="' + escapeAttr( data.link ) + '" target="_blank" rel="noopener">' + escapeText( data.link ) + '</a></div>' : '' ) +
					'<input type="hidden" name="scbl_copies[' + next + '][title]"          value="' + escapeAttr( data.title )         + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][body]"           value="' + escapeAttr( data.body )          + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][time]"           value="' + escapeAttr( data.time )          + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][location]"       value="' + escapeAttr( data.location )      + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][contact_name]"   value="' + escapeAttr( data.contact_name )  + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][contact_email]"  value="' + escapeAttr( data.contact_email ) + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][contact_phone]"  value="' + escapeAttr( data.contact_phone ) + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][link]"           value="' + escapeAttr( data.link )          + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][source_id]"      value="' + escapeAttr( data.id )            + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][image_id]"       value="' + escapeAttr( data.image_id )      + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][start]"          value="' + escapeAttr( data.start )         + '" />' +
					'<input type="hidden" name="scbl_copies[' + next + '][end]"            value="' + escapeAttr( data.end )           + '" />' +
				'</div>' +
				'<button type="button" class="button-link scbl-copy-remove" aria-label="Remove">&times;</button>' +
			'</div>';

		var empty = container.querySelector( '.scbl-copies-empty' );
		if ( empty ) empty.remove();

		container.appendChild( row );
		item.remove();

		var noticeList = document.querySelector( '.scbl-review-list' );
		if ( noticeList && ! noticeList.querySelector( '.scbl-review-item' ) ) {
			var notice = document.querySelector( '.scbl-review-notice' );
			if ( notice ) notice.style.display = 'none';
		}
	} );

	// Add a Program suggestion - same shape as announcements: build a
	// read-only display row and append it to #scbl-programs, pulling
	// the source data from window.scblProgramSourceData.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.scbl-program-suggestion-accept' );
		if ( ! btn ) return;
		var item = btn.closest( '.scbl-program-suggestion' );
		if ( ! item ) return;

		var id  = item.getAttribute( 'data-source-id' ) || '0';
		var src = ( window.scblProgramSourceData && window.scblProgramSourceData[ id ] ) || {};

		var data = {
			id:        id,
			title:     src.title    || '',
			body:      src.body     || '',
			time:      src.time     || '',
			link:      src.link     || '',
			image_id:  src.image_id || 0,
			image_url: src.image_url || ''
		};

		var container = document.getElementById( 'scbl-programs' );
		if ( ! container ) return;

		var next = container.querySelectorAll( '.scbl-program-row' ).length;

		var row = document.createElement( 'div' );
		row.className = 'scbl-program-row';
		row.setAttribute( 'data-index', next );
		row.setAttribute( 'data-source-id', data.id );

		row.innerHTML =
			'<div class="scbl-program-row__row">' +
				( data.image_url ? '<div class="scbl-program-row__image"><img src="' + escapeAttr( data.image_url ) + '" alt="" class="scbl-program-row__thumb" /></div>' : '' ) +
				'<div class="scbl-program-row__body">' +
					'<div class="scbl-program-row__title-row">' +
						'<strong class="scbl-program-row__title">' + escapeText( data.title ) + '</strong>' +
						( data.time ? '<span class="scbl-program-row__time">' + escapeText( data.time ) + '</span>' : '' ) +
					'</div>' +
					( data.body ? '<div class="scbl-program-row__desc">' + escapeText( data.body ) + '</div>' : '' ) +
					( data.link ? '<div class="scbl-program-row__meta"><a href="' + escapeAttr( data.link ) + '" target="_blank" rel="noopener">' + escapeText( data.link ) + '</a></div>' : '' ) +
					'<input type="hidden" class="scbl-program-title"     name="scbl_programs[' + next + '][title]"     value="' + escapeAttr( data.title )    + '" />' +
					'<input type="hidden" class="scbl-program-body"      name="scbl_programs[' + next + '][body]"      value="' + escapeAttr( data.body )     + '" />' +
					'<input type="hidden" class="scbl-program-time"      name="scbl_programs[' + next + '][time]"      value="' + escapeAttr( data.time )     + '" />' +
					'<input type="hidden" class="scbl-program-link"      name="scbl_programs[' + next + '][link]"      value="' + escapeAttr( data.link )     + '" />' +
					'<input type="hidden" class="scbl-program-image-id"  name="scbl_programs[' + next + '][image_id]"  value="' + escapeAttr( data.image_id ) + '" />' +
					'<input type="hidden" class="scbl-program-source-id" name="scbl_programs[' + next + '][source_id]" value="' + escapeAttr( data.id )       + '" />' +
				'</div>' +
				'<button type="button" class="button-link scbl-program-remove" aria-label="Remove">&times;</button>' +
			'</div>';

		var empty = container.querySelector( '.scbl-programs-empty' );
		if ( empty ) empty.remove();

		container.appendChild( row );
		item.remove();

		var list = document.querySelector( '.scbl-program-suggestion-list' );
		if ( list && ! list.querySelector( '.scbl-program-suggestion' ) ) {
			var box = document.querySelector( '.scbl-program-suggestions' );
			if ( box ) box.style.display = 'none';
		}
	} );

	function escapeAttr( s ) {
		return String( s ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}
	function escapeText( s ) {
		return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}
}() );
