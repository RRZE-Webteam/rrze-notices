"use strict";

jQuery(document).ready(function ($) {
    let visibilityTypeInput = $("input[name='notice_visibility']");

    triggerVisibilityTypeField();
    visibilityTypeInput.on("change", function () {
        triggerVisibilityTypeField();
    });

    function triggerVisibilityTypeField() {
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
        triggerRepeatFields();
    }

    repeatCheck.on("change", function () {
        triggerRepeatFields();
    });

    repeatIntervalSelect.on("change", function () {
        triggerIntervalFields();
    });

    repeatMonthlyTypeInput.on("change", function () {
        triggerMonthlyTypeFields();
    });

    function triggerRepeatFields() {
        if (repeatCheck.is(":checked")) {
            $("div.event-repeat").slideDown();
            triggerIntervalFields();
        } else {
            $("div.event-repeat").slideUp();
        }
    }

    function triggerIntervalFields() {
        var repeatInterval = $("option:selected", repeatIntervalSelect).val();
        if (repeatInterval === "week") {
            $("div.event-repeat-weekly").slideDown();
            $("div.event-repeat-monthly").slideUp();
        } else if (repeatInterval === "month") {
            $("div.event-repeat-monthly").slideDown();
            $("div.event-repeat-weekly").slideUp();
            triggerMonthlyTypeFields();
        }
    }

    function triggerMonthlyTypeFields() {
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
