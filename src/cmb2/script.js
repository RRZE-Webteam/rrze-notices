'use strict';

/* global jQuery, rrzeNoticesCmb2 */

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
		fields.attr( 'aria-hidden', String( ! visible ) );

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
	const showEventDateInput = noticeMetabox.find(
		"input[name='notice_display[]'][value='show_event_date']"
	);
	const eventFields = noticeMetabox.find(
		'.cmb2-id-notice-event-start, .cmb2-id-notice-event-end'
	);

	updateEventFieldVisibility( false );

	showEventDateInput.on( 'change', function () {
		updateEventFieldVisibility( true );
	} );

	/**
	 * Show event date fields only when the event-date checkbox is active.
	 *
	 * @param {boolean} animate Whether to animate the transition.
	 * @return {void}
	 */
	function updateEventFieldVisibility( animate ) {
		setFieldVisibility(
			eventFields,
			showEventDateInput.is( ':checked' ),
			animate
		);
	}

	// Clear button for Event End field
	const endDateInputs = noticeMetabox.find(
		'.cmb2-id-notice-event-end input[type="text"], .cmb2-id-notice-event-end input[type="time"]'
	);
	const clearButton = $( '<button/>', {
		type: 'button',
		class: 'button-link rrze-notices-clear-date',
		'aria-label': rrzeNoticesCmb2.clearLabel,
		style: 'margin-left: 8px;',

		html:
			'<span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px;"></span> ' +
			rrzeNoticesCmb2.clearLabel,
	} );
	endDateInputs.last().after( clearButton );

	clearButton.on( 'click', function () {
		endDateInputs.val( '' );
	} );
} );
