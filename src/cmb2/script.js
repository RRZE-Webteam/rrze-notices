"use strict";

/**
 * Initialize conditional visibility for notice and recurrence CMB2 fields.
 *
 * @param {JQueryStatic} $ Scoped jQuery instance supplied by WordPress.
 * @return {void}
 */
jQuery(document).ready(function ($) {
    const noticeMetabox = $("#rrze_notices_metabox");
    const visibilityTypeInput = noticeMetabox.find(
        "input[name='notice_visibility']"
    );
    const roleFields = noticeMetabox.find("div.notice_visibility_roles");
    const repeatCheckbox = noticeMetabox.find("input#event_repeat");
    const repeatIntervalSelect = noticeMetabox.find(
        "select#event_repeat_interval"
    );
    const repeatMonthlyTypeInput = noticeMetabox.find(
        "input[name='event_repeat_monthly_type']"
    );
    const allRecurrenceFields = noticeMetabox.find("div.event-repeat");
    const baseRecurrenceFields = allRecurrenceFields.not(
        ".event-repeat-weekly, .event-repeat-monthly"
    );
    const weeklyFields = noticeMetabox.find("div.event-repeat-weekly");
    const allMonthlyFields = noticeMetabox.find("div.event-repeat-monthly");
    const baseMonthlyFields = allMonthlyFields.not(
        ".event-repeat-monthly-date, .event-repeat-monthly-dow"
    );
    const monthlyDateFields = noticeMetabox.find(
        "div.event-repeat-monthly-date"
    );
    const monthlyWeekdayFields = noticeMetabox.find(
        "div.event-repeat-monthly-dow"
    );

    updateRoleFieldVisibility(false);
    updateRecurrenceFieldVisibility(false);

    visibilityTypeInput.on("change", function () {
        updateRoleFieldVisibility(true);
    });

    repeatCheckbox.on("change", function () {
        updateRecurrenceFieldVisibility(true);
    });

    repeatIntervalSelect.on("change", function () {
        updateIntervalFieldVisibility(true);
    });

    repeatMonthlyTypeInput.on("change", function () {
        updateMonthlyFieldVisibility(true);
    });

    /**
     * Show or hide fields, optionally using a short transition.
     *
     * @param {JQuery} fields Fields to update.
     * @param {boolean} visible Whether the fields should be visible.
     * @param {boolean} animate Whether to animate the transition.
     * @return {void}
     */
    function setFieldVisibility(fields, visible, animate) {
        fields.stop(true, true);

        if (!animate) {
            fields.toggle(visible);
            return;
        }

        if (visible) {
            fields.slideDown(150);
        } else {
            fields.slideUp(150);
        }
    }

    /**
     * Show role choices only when visibility is restricted by user role.
     *
     * @param {boolean} animate Whether to animate the transition.
     * @return {void}
     */
    function updateRoleFieldVisibility(animate) {
        const visibilityType = visibilityTypeInput.filter(":checked").val();

        setFieldVisibility(roleFields, visibilityType === "roles", animate);
    }

    /**
     * Toggle the complete recurrence settings group.
     *
     * @param {boolean} animate Whether to animate the transition.
     * @return {void}
     */
    function updateRecurrenceFieldVisibility(animate) {
        if (!repeatCheckbox.is(":checked")) {
            setFieldVisibility(allRecurrenceFields, false, animate);
            return;
        }

        setFieldVisibility(baseRecurrenceFields, true, animate);
        updateIntervalFieldVisibility(animate);
    }

    /**
     * Show weekly or monthly controls for the selected recurrence interval.
     *
     * @param {boolean} animate Whether to animate the transition.
     * @return {void}
     */
    function updateIntervalFieldVisibility(animate) {
        if (!repeatCheckbox.is(":checked")) {
            setFieldVisibility(weeklyFields, false, animate);
            setFieldVisibility(allMonthlyFields, false, animate);
            return;
        }

        const repeatInterval = repeatIntervalSelect.val();
        const repeatsWeekly = repeatInterval === "week";
        const repeatsMonthly = repeatInterval === "month";

        setFieldVisibility(weeklyFields, repeatsWeekly, animate);
        setFieldVisibility(baseMonthlyFields, repeatsMonthly, animate);

        if (repeatsMonthly) {
            updateMonthlyFieldVisibility(animate);
        } else {
            setFieldVisibility(monthlyDateFields, false, animate);
            setFieldVisibility(monthlyWeekdayFields, false, animate);
        }
    }

    /**
     * Show either date-based or weekday-based monthly recurrence controls.
     *
     * @param {boolean} animate Whether to animate the transition.
     * @return {void}
     */
    function updateMonthlyFieldVisibility(animate) {
        const repeatsMonthly =
            repeatCheckbox.is(":checked") &&
            repeatIntervalSelect.val() === "month";
        const repeatMonthlyType = repeatMonthlyTypeInput
            .filter(":checked")
            .val();

        setFieldVisibility(
            monthlyDateFields,
            repeatsMonthly && repeatMonthlyType === "date",
            animate
        );
        setFieldVisibility(
            monthlyWeekdayFields,
            repeatsMonthly && repeatMonthlyType === "dow",
            animate
        );
    }
});
