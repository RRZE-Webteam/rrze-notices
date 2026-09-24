'use strict';

/* global jQuery */

/**
 * Initialize conditional visibility for notice CMB2 fields.
 *
 * @param {Function} $ Scoped jQuery function supplied by WordPress.
 * @return {void}
 */
jQuery( document ).ready( function ( $ ) {
	const noticeMetabox = $( '#rrze_notices_metabox' );
	const visibilityTypeInput = noticeMetabox.find(
		"input[name='notice_visibility']"
	);
	const roleFields = noticeMetabox.find( 'div.notice_visibility_roles' );

	updateRoleFieldVisibility( false );


	visibilityTypeInput.on( 'change', function () {
		updateRoleFieldVisibility( true );
	} );


	/**
	 * Show or hide fields, optionally using a short transition.
	 *
	 * @param {Object}  fields  jQuery field collection to update.
	 * @param {boolean} visible Whether the fields should be visible.
	 * @param {boolean} animate Whether to animate the transition.
	 * @return {void}
	 */
	function setFieldVisibility( fields, visible, animate ) {
		fields.stop( true, true );

		if ( ! animate ) {
			fields.toggle( visible );
			return;
		}

		if ( visible ) {
			fields.slideDown( 150 );
		} else {
			fields.slideUp( 150 );
		}
	}

	/**
	 * Show role choices only when visibility is restricted by user role.
	 *
	 * @param {boolean} animate Whether to animate the transition.
	 * @return {void}
	 */
	function updateRoleFieldVisibility( animate ) {
		const visibilityType = visibilityTypeInput.filter( ':checked' ).val();

		setFieldVisibility( roleFields, visibilityType === 'roles', animate );
	}


} );
