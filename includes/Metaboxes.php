<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Defines CMB2 notice fields and normalizes their saved values.
 */
class Metaboxes
{
    /**
     * Register metabox and field-normalization hooks.
     *
     * @return void
     */
    public static function registerHooks(): void
    {
        add_action('cmb2_admin_init', [__CLASS__, 'registerNoticeMetabox']);
        add_action('cmb2_save_field_notice_visibility', [__CLASS__, 'saveNoticeVisibilityField'], 10, 3);
        add_action('cmb2_save_field_event_start', [__CLASS__, 'saveEventStartField'], 10, 3);
        add_action('cmb2_save_field_event_end', [__CLASS__, 'saveEventEndField'], 10, 3);
        add_action('cmb2_save_field_event_repeat_lastdate', [__CLASS__, 'saveEventRepeatLastDateField'], 10, 3);
    }

    /**
     * Register the complete notice-settings metabox and its fields.
     *
     * @return void
     */
    public static function registerNoticeMetabox(): void
    {
        $objectTypes = [CPTNotice::POST_TYPE];
        $startOfWeek = get_option('start_of_week');

        $cmb = new_cmb2_box([
            'id' => 'rrze_notices_metabox',
            'title' => __('Notice Settings', 'rrze-notices'),
            'object_types' => $objectTypes,
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true
        ]);

        self::addNoticeFields($cmb);
        self::addEventFields($cmb, (int) $startOfWeek);
        self::addRecurrenceFields($cmb, (int) $startOfWeek);
    }

    /**
     * Add notice presentation and audience fields.
     *
     * @param \CMB2 $cmb Notice settings metabox.
     * @return void
     */
    private static function addNoticeFields(\CMB2 $cmb): void
    {
        // Notice Type options
        $cmb->add_field([
            'id'   => 'notice_type',
            'name' => __('Type', 'rrze-notices'),
            'type' => 'radio',
            'default' => 'info',
            'options' => [
                'info' => __('Info', 'rrze-notices'),
                'warning' => __('Warning', 'rrze-notices')
            ],
        ]);
        // Notice Display options
        $cmb->add_field([
            'name' => __('Display', 'rrze-notices'),
            'id' => 'notice_display',
            'type' => 'multicheck',
            'options' => self::getDisplayOptions()
        ]);
        // Notice Visibility
        $cmb->add_field([
            'name' => __('Visibility', 'rrze-notices'),
            'id' => 'notice_visibility',
            'type' => 'radio',
            'options' => [
                'all' => __('All', 'rrze-notices'),
                'roles' => __('By User Role', 'rrze-notices'),
            ],
            'default' => 'all'
        ]);
        $cmb->add_field([
            'name' => __('User Roles', 'rrze-notices')
                . '<br><span style="font-weight: normal;color: #666;font-size: 13px;">'
                . __('(Visibility based on user role)', 'rrze-notices') . '</span>',
            'id' => 'notice_visibility_roles',
            'type' => 'multicheck',
            'options' => self::getRoleVisibilityOptions(),
            'classes' => ['notice_visibility_roles'],
        ]);
        // Notice Start days->timestamp
        $cmb->add_field([
            'name' => __('Notice Start', 'rrze-notices'),
            'desc' => __('days before the start of the event', 'rrze-notices'),
            'id' => 'notice_start',
            'type' => 'text_small',
            'attributes' => [
                'type' => 'number',
                'min' => '0',
                'max' => '30',
                'required' => 'required'
            ],
            'sanitization_cb' => [__CLASS__, 'sanitizeNoticeLeadTime'],
            'escape_cb' => [__CLASS__, 'formatNoticeLeadTime'],
        ]);
    }

    /**
     * Add event boundary fields.
     *
     * @param \CMB2 $cmb         Notice settings metabox.
     * @param int   $startOfWeek WordPress weekday index.
     * @return void
     */
    private static function addEventFields(\CMB2 $cmb, int $startOfWeek): void
    {
        // Event Start date-time->timestamp
        $cmb->add_field([
            'name' => __('Event Start', 'rrze-notices'),
            'id' => 'event_start',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('Y/m/d', 'event date format', 'rrze-notices'),
            'time_format' => _x('g:i a', 'event time format', 'rrze-notices'),
            'attributes' => [
                'data-datepicker' => json_encode(
                    [
                        'firstDay' => $startOfWeek,
                        'dayNames' => Utils::getDaysOfWeek('short'),
                        'dayNamesMin' => Utils::getDaysOfWeek('min'),
                        'monthNamesShort' => Utils::getMonthNames('short')
                    ]
                ),
                'data-timepicker' => json_encode(
                    [
                        'stepMinute' => 5,
                    ]
                ),
                'required' => 'required'
            ]
        ]);
        // End date-time->timestamp
        $cmb->add_field([
            'name' => __('Event End', 'rrze-notices'),
            'id' => 'event_end',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('Y/m/d', 'event date format', 'rrze-notices'),
            'time_format' => _x('g:i a', 'event time format', 'rrze-notices'),
            'attributes' => [
                'data-datepicker' => json_encode(
                    [
                        'firstDay' => $startOfWeek,
                        'dayNames' => Utils::getDaysOfWeek('short'),
                        'dayNamesMin' => Utils::getDaysOfWeek('min'),
                        'monthNamesShort' => Utils::getMonthNames('short')
                    ]
                ),
                'data-timepicker' => json_encode(
                    [
                        'stepMinute' => 5,
                    ]
                ),
                'required' => 'required'
            ]
        ]);
        // All day event
        $cmb->add_field([
            'name' => __('All Day Event', 'rrze-notices') . '<br><span style="font-weight: normal;color: #666;font-size: 13px;">' . __('(Time settings will be ignored)', 'rrze-notices') . '</span>',
            'id' => 'event_all_day',
            'type' => 'checkbox',
        ]);
    }

    /**
     * Add recurrence rule, exception, and addition fields.
     *
     * @param \CMB2 $cmb         Notice settings metabox.
     * @param int   $startOfWeek WordPress weekday index.
     * @return void
     */
    private static function addRecurrenceFields(\CMB2 $cmb, int $startOfWeek): void
    {
        // Event repeat
        $cmb->add_field([
            'name' => __('Event Repeat', 'rrze-notices'),
            'id' => 'event_repeat',
            'type' => 'checkbox',
        ]);
        // Repeat until
        $cmb->add_field([
            'name' => __('Repeat Until', 'rrze-notices'),
            'desc' => __('(optional)', 'rrze-notices'),
            'id' => 'event_repeat_lastdate',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('Y/m/d', 'event date format', 'rrze-notices'),
            'time_format' => _x('g:i a', 'event time format', 'rrze-notices'),
            'attributes' => [
                'data-timepicker' => json_encode(
                    [
                        'stepMinute' => 10,
                        'minDate' => 1,
                        'maxDate' => 730,
                        'yearRange' => 'c:c+2',
                        'firstDay' => $startOfWeek,
                        'dayNames' => Utils::getDaysOfWeek('short'),
                        'dayNamesMin' => Utils::getDaysOfWeek('min'),
                        'monthNamesShort' => Utils::getMonthNames('short')
                    ]
                ),
            ],
            'classes' => ['event-repeat'],
        ]);
        // Repeat interval
        $cmb->add_field([
            'name' => __('Repeat Interval', 'rrze-notices'),
            'id' => 'event_repeat_interval',
            'type' => 'select',
            'default' => 'week',
            'options' => [
                'week' => __('Weekly', 'rrze-notices'),
                'month' => __('Monthly', 'rrze-notices'),
            ],
            'classes' => ['event-repeat'],
        ]);
        // Repeats weekly
        $cmb->add_field([
            'name' => __('Repeats', 'rrze-notices'),
            'id' => 'event_repeat_weekly_interval',
            'type' => 'text_small',
            'default' => '1',
            'attributes' => [
                'type' => 'number',
                'min' => '1',
            ],
            'before_field' => __('every', 'rrze-notices') . ' ',
            'after_field' =>  ' ' . __('week(s)', 'rrze-notices'),
            'classes' => ['event-repeat', 'event-repeat-weekly'],
        ]);
        $cmb->add_field([
            'name' => __('Repeats On', 'rrze-notices'),
            'id' => 'event_repeat_weekly_day',
            'type' => 'multicheck_inline',
            'options' => self::getWeekdayOptions(),
            'classes' => ['event-repeat', 'event-repeat-weekly'],
        ]);
        // Repeats monthly
        $cmb->add_field([
            'name' => __('Each', 'rrze-notices'),
            'id' => 'event_repeat_monthly_type',
            'type' => 'radio',
            'options' => [
                'date' => __('Date', 'rrze-notices'),
                'dow' => __('Weekday', 'rrze-notices'),
            ],
            'classes' => ['event-repeat', 'event-repeat-monthly'],
        ]);
        $cmb->add_field([
            'name' => __('Repeats On', 'rrze-notices'),
            'id' => 'event_repeat_monthly_type_date',
            'type' => 'select',
            'options' => self::getMonthDaysOptions(),
            'before_field' => __('each', 'rrze-notices') . ' ',
            'after_field' =>  ' ' . __('of month', 'rrze-notices'),
            'classes'   => ['event-repeat', 'event-repeat-monthly', 'event-repeat-monthly-date'],
        ]);
        $cmb->add_field([
            'name' => __('Repeats On', 'rrze-notices'),
            'id' => 'event_repeat_monthly_type_dow',
            'type' => 'select_weekdayofmonth',
            'before_field' => __('each', 'rrze-notices') . ' ',
            'after_field' =>  ' ' . __('of month', 'rrze-notices'),
            'classes' => ['event-repeat', 'event-repeat-monthly', 'event-repeat-monthly-dow'],
        ]);
        $cmb->add_field([
            'name' => __('Repeats In', 'rrze-notices'),
            'id' => 'event_repeat_monthly_month',
            'type' => 'multicheck_inline',
            'options' => self::getMonthsOptions(),
            // 'default' => array_keys(self::getMonthsOptions()),
            'classes' => ['event-repeat', 'event-repeat-monthly'],
        ]);
        // Exceptions
        $cmb->add_field([
            'name' => __('Dates Exceptions', 'rrze-notices'),
            'desc' => __('Enter dates to be skipped (format YYYY-MM-DD, e.g. 2023-12-31). One date per line.', 'rrze-notices'),
            'id' => 'event_date_exceptions',
            'type' => 'textarea_small',
            'sanitization_cb' => [__CLASS__, 'sanitizeDateList'],
            'escape_cb' => [__CLASS__, 'formatDateList'],
            'classes' => ['event-repeat'],
        ]);
        // Additions
        $cmb->add_field([
            'name' => __('Dates Additions', 'rrze-notices'),
            'desc' => __('Add additional dates (format YYYY-MM-DD, e.g. 2023-12-31). One date per line.', 'rrze-notices'),
            'id' => 'event_date_additions',
            'type' => 'textarea_small',
            'sanitization_cb' => [__CLASS__, 'sanitizeDateList'],
            'escape_cb' => [__CLASS__, 'formatDateList'],
            'classes' => ['event-repeat'],
        ]);
    }

    /**
     * Get an array of display options
     * e.g. $options['hide_title'] = 'Hide Title'
     *
     * @return array
     */
    private static function getDisplayOptions(): array
    {
        $options = [
            'hide_title' => __('Hide Title', 'rrze-notices'),
        ];
        if (
            is_super_admin() &&
            Utils::isPluginActiveForNetwork()
        ) {
            $options['network_wide'] = __('Network-Wide', 'rrze-notices');
        }
        return $options;
    }

    /**
     * Get an array of visibility by user role options
     * e.g. $options['administrator'] = 'Administrator'
     *
     * @return array
     */
    private static function getRoleVisibilityOptions(): array
    {
        $availableRoles = Users::getRoles();
        $options = [];
        if (
            is_super_admin() &&
            Utils::isPluginActiveForNetwork()
        ) {
            foreach ($availableRoles as $role => $name) {
                $options[$role] = $name;
            }
        } else {
            $countUsers = count_users();
            foreach ($availableRoles as $role => $name) {
                $count = $countUsers['avail_roles'][$role] ?? 0;
                if ($count > 0) {
                    $options[$role] = sprintf('%s (%d)', $name, $count);
                } else {
                    $options[$role] = sprintf('%s (&mdash;)', $name);;
                }
            }
        }

        return $options;
    }

    /**
     * Get an array of days of the week options
     * e.g. $options['sun'] = 'Sun'
     *
     * @return array
     */
    private static function getWeekdayOptions(): array
    {
        global $wp_locale;
        $options = [];
        for ($i = 0; $i <= 6; $i++) {
            $weekday = strtolower(date('l', strtotime("Sunday +{$i} days")));
            $options[$weekday] = $wp_locale->get_weekday_abbrev($wp_locale->get_weekday($i));
        }
        return $options;
    }

    /**
     * Get an array of month days options
     * e.g. $options[1] = 1
     *
     * @return array
     */
    private static function getMonthDaysOptions(): array
    {
        return array_combine(range(1, 31), range(1, 31));
    }

    /**
     * Get an array of months options
     * e.g. $options['jan'] = 'January'
     *
     * @return array
     */
    private static function getMonthsOptions(): array
    {
        global $wp_locale;
        $options = [];
        for ($i = 1; $i <= 12; $i++) {
            $options[$i] = $wp_locale->get_month_abbrev($wp_locale->get_month(sprintf('%02d', $i)));
        }
        return $options;
    }

    /**
     * Remove stale role selections when visibility changes to all users.
     *
     * @param bool        $updated Whether CMB2 changed the stored value.
     * @param string      $action  CMB2 save action.
     * @param \CMB2_Field $field   Saved visibility field.
     * @return void
     */
    public static function saveNoticeVisibilityField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        if ($field->value === 'all') {
            delete_post_meta($postId, 'notice_visibility_roles');
        }
    }

    /**
     * Save the event start as an absolute GMT timestamp.
     *
     * @param bool        $updated Whether CMB2 changed the stored value.
     * @param string      $action  CMB2 save action.
     * @param \CMB2_Field $field   Saved event-start field.
     * @return void
     */
    public static function saveEventStartField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        $unixTimestamp = self::wallTimeTimestampToUnix((int) $field->value);
        update_post_meta($postId, 'event_start_gmt', $unixTimestamp);
    }

    /**
     * Save the event end as an absolute GMT timestamp.
     *
     * @param bool        $updated Whether CMB2 changed the stored value.
     * @param string      $action  CMB2 save action.
     * @param \CMB2_Field $field   Saved event-end field.
     * @return void
     */
    public static function saveEventEndField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        $unixTimestamp = self::wallTimeTimestampToUnix((int) $field->value);
        update_post_meta($postId, 'event_end_gmt', $unixTimestamp);
    }

    /**
     * Clamp and save the recurrence limit in local and GMT forms.
     *
     * @param bool        $updated Whether CMB2 changed the stored value.
     * @param string      $action  CMB2 save action.
     * @param \CMB2_Field $field   Saved recurrence-limit field.
     * @return void
     */
    public static function saveEventRepeatLastDateField(bool $updated, string $action, \CMB2_Field $field): void
    {
        $postId = $field->object_id;
        $timestamp = $field->value;
        if ($timestamp) {
            $timezone = wp_timezone();
            $selected = new \DateTimeImmutable(gmdate('Y-m-d H:i:s', (int) $timestamp), $timezone);
            $minDate = current_datetime()->modify('+1 day')->setTime(0, 0);
            $maxDate = current_datetime()->modify('+2 years')->setTime(23, 59, 59);
            if ($selected < $minDate) {
                $selected = $minDate;
            } elseif ($selected > $maxDate) {
                $selected = $maxDate;
            }
            $selected = $selected->setTime(0, 0);
            $localTimestamp = strtotime($selected->format('Y-m-d') . ' UTC');

            update_post_meta($postId, 'event_repeat_lastdate', $localTimestamp);
            update_post_meta($postId, 'event_repeat_lastdate_gmt', $selected->getTimestamp());
        }
    }

    /**
     * Convert CMB2's wall-time timestamp into an absolute GMT timestamp.
     *
     * @param int $timestamp CMB2 timestamp representing local wall time.
     * @return int Unix timestamp, or zero for an empty value.
     */
    private static function wallTimeTimestampToUnix(int $timestamp): int
    {
        if (!$timestamp) {
            return 0;
        }

        $local = new \DateTimeImmutable(gmdate('Y-m-d H:i:s', $timestamp), wp_timezone());

        return $local->getTimestamp();
    }

    /**
     * Convert a lead-time day count into stored seconds.
     *
     * @param mixed       $value     Submitted field value.
     * @param array       $fieldArgs CMB2 field arguments.
     * @param \CMB2_Field $field     CMB2 field instance.
     * @return int Lead time in seconds.
     */
    public static function sanitizeNoticeLeadTime($value, $fieldArgs, $field): int
    {
        $value = absint($value);
        return $value * DAY_IN_SECONDS;
    }

    /**
     * Convert stored lead-time seconds into the displayed day count.
     *
     * @param mixed       $value     Stored field value.
     * @param array       $fieldArgs CMB2 field arguments.
     * @param \CMB2_Field $field     CMB2 field instance.
     * @return int Number of calendar days.
     */
    public static function formatNoticeLeadTime($value, $fieldArgs, $field): int
    {
        $value = absint($value);
        return ceil($value / DAY_IN_SECONDS);
    }

    /**
     * Sanitize a newline-separated list of ISO dates.
     *
     * @param string $value
     * @param array $fieldArgs
     * @param \CMB2_Field $field
     * @return mixed string|array
     */
    public static function sanitizeDateList($value, $fieldArgs, $field)
    {
        if (!empty($value)) {
            $value = explode(PHP_EOL, sanitize_textarea_field($value));
            $value = array_unique(array_filter(array_map('trim', $value)));
            $value = array_filter(array_map([__CLASS__, 'normalizeDateValue'], $value));
            sort($value);
        }
        return !empty($value) ? $value : '';
    }

    /**
     * Format a stored date list for textarea display.
     *
     * @param mixed $value
     * @param array $fieldArgs
     * @param \CMB2_Field $field
     * @return string
     */
    public static function formatDateList($value, $fieldArgs, $field): string
    {
        if (!empty($value) && is_array($value)) {
            return implode(PHP_EOL, $value);
        }
        return '';
    }

    /**
     * Normalize one ISO date value.
     *
     * @param string $date
     * @return string
     */
    public static function normalizeDateValue(string $date): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date ? $d->format('Y-m-d') : '';
    }
}
