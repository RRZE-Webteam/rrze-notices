"use strict";

/**
 * Initialize conditional visibility for notice and recurrence CMB2 fields.
 *
 * @param {JQueryStatic} $ Scoped jQuery instance supplied by WordPress.
 * @return {void}
 */
jQuery(document).ready(function ($) {
    let visibilityTypeInput = $("input[name='notice_visibility']");

    updateRoleFieldVisibility();
    visibilityTypeInput.on("change", function () {
        updateRoleFieldVisibility();
    });

    /**
     * Show role choices only when visibility is restricted by user role.
     *
     * @return {void}
     */
    function updateRoleFieldVisibility() {
        var visibilityType = $("input[name='notice_visibility']:checked").val();
        if (typeof visibilityType == "undefined") {
            $("div.notice_visibility_roles").hide();
        } else if (visibilityType === "all") {
            $("div.notice_visibility_roles").slideUp();
        } else if (visibilityType === "roles") {
            $("div.notice_visibility_roles").slideDown();
        }
    }

    let repeatCheck = $("input#event_repeat");
    let repeatIntervalSelect = $("select#event_repeat_interval");
    let repeatMonthlyTypeInput = $("input[name='event_repeat_monthly_type']");

    if (repeatCheck.length > 0) {
        updateRecurrenceFieldVisibility();
    }

    repeatCheck.on("change", function () {
        updateRecurrenceFieldVisibility();
    });

    repeatIntervalSelect.on("change", function () {
        updateIntervalFieldVisibility();
    });

    repeatMonthlyTypeInput.on("change", function () {
        updateMonthlyFieldVisibility();
    });

    /**
     * Toggle the complete recurrence settings group.
     *
     * @return {void}
     */
    function updateRecurrenceFieldVisibility() {
        if (repeatCheck.is(":checked")) {
            $("div.event-repeat").slideDown();
            updateIntervalFieldVisibility();
        } else {
            $("div.event-repeat").slideUp();
        }
    }

    /**
     * Show weekly or monthly controls for the selected recurrence interval.
     *
     * @return {void}
     */
    function updateIntervalFieldVisibility() {
        var repeatInterval = $("option:selected", repeatIntervalSelect).val();
        if (repeatInterval === "week") {
            $("div.event-repeat-weekly").slideDown();
            $("div.event-repeat-monthly").slideUp();
        } else if (repeatInterval === "month") {
            $("div.event-repeat-monthly").slideDown();
            $("div.event-repeat-weekly").slideUp();
            updateMonthlyFieldVisibility();
        }
    }

    /**
     * Show either date-based or weekday-based monthly recurrence controls.
     *
     * @return {void}
     */
    function updateMonthlyFieldVisibility() {
        var repeatMonthlyType = $(
            "input[name='event_repeat_monthly_type']:checked"
        ).val();
        if (typeof repeatMonthlyType == "undefined") {
            $("div.event-repeat-monthly-date").hide();
            $("div.event-repeat-monthly-dow").hide();
        } else if (repeatMonthlyType === "dow") {
            $("div.event-repeat-monthly-dow").slideDown();
            $("div.event-repeat-monthly-date").slideUp();
        } else if (repeatMonthlyType === "date") {
            $("div.event-repeat-monthly-date").slideDown();
            $("div.event-repeat-monthly-dow").slideUp();
        }
    }
});
