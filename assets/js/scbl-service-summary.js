/*
 * Sunday admin JS: "Generate overview" on the Service editor.
 *
 * Asks for an overview written from the week's sermon and puts it in the
 * editor, classic or block, for the admin to review and save. Nothing is
 * saved from here.
 */
( function () {
	'use strict';

	var config = window.scblServiceSummary;
	if ( ! config ) return;

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.scbl-summary-generate' );
		if ( ! btn ) return;
		e.preventDefault();

		if ( currentText().trim() !== '' && ! window.confirm( config.i18n.replace ) ) return;

		var box     = btn.closest( '.inside' ) || document;
		var spinner = box.querySelector( '.scbl-summary-spinner' );
		var status  = box.querySelector( '.scbl-summary-status' );
		var dateEl  = document.getElementById( 'scbl_service_date' );

		btn.disabled = true;
		if ( spinner ) spinner.classList.add( 'is-active' );
		setStatus( status, config.i18n.working );

		var body = new FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'post_id', btn.getAttribute( 'data-post-id' ) || '0' );
		body.append( 'date', dateEl ? dateEl.value : '' );

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) {
				if ( json && json.success && json.data && json.data.text ) {
					insert( json.data.text );
					setStatus( status, config.i18n.done );
				} else {
					setStatus( status, ( json && json.data && json.data.message ) || config.i18n.failed );
				}
			} )
			.catch( function () {
				setStatus( status, config.i18n.failed );
			} )
			.then( function () {
				btn.disabled = false;
				if ( spinner ) spinner.classList.remove( 'is-active' );
			} );
	} );

	function setStatus( el, text ) {
		if ( el ) el.textContent = text;
	}

	function paragraphs( text ) {
		return text.split( /\n\s*\n/ ).map( function ( p ) { return p.trim(); } ).filter( Boolean );
	}

	function blockEditor() {
		return window.wp && wp.data && wp.blocks && wp.data.select( 'core/block-editor' );
	}

	function classicEditor() {
		var mce = window.tinymce && window.tinymce.get( 'content' );
		return mce && ! mce.isHidden() ? mce : null;
	}

	function currentText() {
		if ( blockEditor() ) {
			return wp.data.select( 'core/editor' ).getEditedPostContent().replace( /<[^>]*>/g, '' );
		}
		var mce = classicEditor();
		if ( mce ) return mce.getContent( { format: 'text' } );
		var area = document.getElementById( 'content' );
		return area ? area.value : '';
	}

	function insert( text ) {
		var parts = paragraphs( text );

		if ( blockEditor() ) {
			wp.data.dispatch( 'core/block-editor' ).resetBlocks( parts.map( function ( p ) {
				return wp.blocks.createBlock( 'core/paragraph', { content: escapeText( p ) } );
			} ) );
			return;
		}

		var mce = classicEditor();
		if ( mce ) {
			mce.setContent( parts.map( function ( p ) { return '<p>' + escapeText( p ) + '</p>'; } ).join( '' ) );
			mce.fire( 'change' );
			return;
		}

		// The Text tab, which keeps paragraphs as blank lines.
		var area = document.getElementById( 'content' );
		if ( area ) area.value = parts.join( '\n\n' );
	}

	function escapeText( s ) {
		return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}
}() );
