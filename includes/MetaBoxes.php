<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;

class MetaBoxes
{
    /**
     * Custom post type name
     *
     * @var string
     */
    protected $customPostType;

    /**
     * Site options name
     *
     * @var string
     */
    protected $siteOptionsName;

    /**
     * Site options
     *
     * @var array
     */
    protected $siteOptions;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->customPostType = CPT::POST_TYPE;

        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
    }

    /**
     * Load hooks
     *
     * @return void
     */
    public function loaded()
    {
        add_action('cmb2_admin_init', [$this, 'metabox']);
        add_action('cmb2_save_field_notice_start', [$this, 'saveNoticeStartField'], 10, 3);
        add_action('cmb2_save_field_event_start', [$this, 'saveEventStartField'], 10, 3);
        add_action('cmb2_save_field_event_end', [$this, 'saveEventEndField'], 10, 3);
        add_action('cmb2_save_field_event_repeat_lastdate', [$this, 'saveEventRepeatLastdateField'], 10, 3);
    }

    public function metabox()
    {
        $startOfWeek = get_option('start_of_week');

        $cmb = new_cmb2_box([
            'id' => 'rrze_notices_metabox',
            'title' => __('Notice Settings', 'rrze-notices'),
            'object_types' => [$this->customPostType],
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true
        ]);
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
            'default' => 'info'
        ]);
        // Notice Display options
        $cmb->add_field([
            'name' => __('Display', 'rrze-notices'),
            'id' => 'notice_display',
            'type' => 'multicheck',
            'options' => $this->getDisplayOptions()
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
            'name' => __('User Roles', 'rrze-notices'),
            'id' => 'notice_visibility_roles',
            'type' => 'multicheck',
            'options' => $this->getVisibilityByUserRoleOptions(),
            'classes' => ['notice_visibility_roles'],
        ]);
        // Notice Start date-time->timestamp
        $cmb->add_field([
            'name' => __('Notice Start', 'rrze-notices'),
            'desc' => __('days before the start of the event', 'rrze-notices'),
            'id' => 'notice_start',
            'type' => 'text_small',
            'attributes' => [
                'type' => 'number',
                'min' => '1',
                'max' => '30',
                'required' => 'required'
            ],
            'sanitization_cb' => 'absint',
            'escape_cb' => 'absint',
        ]);
        // Event Start date-time->timestamp
        $cmb->add_field([
            'name' => __('Event Start', 'rrze-notices'),
            'id' => 'event_start',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('Y/m/d', 'expiration date format', 'rrze-notices'),
            'time_format' => _x('g:i a', 'expiration time format', 'rrze-notices'),
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
            'date_format' => _x('Y/m/d', 'expiration date format', 'rrze-notices'),
            'time_format' => _x('g:i a', 'expiration time format', 'rrze-notices'),
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
        // Event reapeat
        $cmb->add_field([
            'name' => __('Event Repeat', 'rrze-notices'),
            'id' => 'event_repeat',
            'type' => 'checkbox',
        ]);
        // Reapeat until
        $cmb->add_field([
            'name' => __('Repeat Until', 'rrze-notices'),
            'desc' => __('(optional)', 'rrze-notices'),
            'id' => 'event_repeat_lastdate',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('Y/m/d', 'expiration date format', 'rrze-notices'),
            'time_format' => _x('g:i a', 'expiration time format', 'rrze-notices'),
            'attributes' => [
                'data-timepicker' => json_encode(
                    [
                        'stepMinute' => 10,
                        'minDate' => 1,
                        'yearRange' => 'c:c+10',
                        'firstDay' => $startOfWeek,
                        'dayNames' => Utils::getDaysOfWeek('short'),
                        'dayNamesMin' => Utils::getDaysOfWeek('min'),
                        'monthNamesShort' => Utils::getMonthNames('short')
                    ]
                ),
                'required' => 'required'
            ],
            'classes'   => ['event-repeat'],
        ]);
        // Reapeat Interval
        $cmb->add_field([
            'name' => __('Repeat Interval', 'rrze-notices'),
            'id' => 'event_repeat_interval',
            'type' => 'select',
            'default' => 'week',
            'options' => [
                'week' => __('Weekly', 'rrze-notices'),
                'month' => __('Monthly', 'rrze-notices'),
            ],
            'classes'   => ['event-repeat'],
        ]);
        // Reapeats weekly
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
            'classes'   => ['event-repeat', 'event-repeat-weekly'],
        ]);
        $cmb->add_field([
            'name' => __('Repeats On', 'rrze-notices'),
            'id' => 'event_repeat_weekly_day',
            'type' => 'multicheck_inline',
            'options' => $this->getDowOptions(),
            'classes'   => ['event-repeat', 'event-repeat-weekly'],
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
            'classes'   => ['event-repeat', 'event-repeat-monthly'],
        ]);
        $cmb->add_field([
            'name' => __('Repeats On', 'rrze-notices'),
            'id' => 'event_repeat_monthly_type_date',
            'type' => 'select',
            'options' => $this->getMonthDaysOptions(),
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
            'classes'   => ['event-repeat', 'event-repeat-monthly', 'event-repeat-monthly-dow'],
        ]);
        $cmb->add_field([
            'name' => __('Repeats In', 'rrze-notices'),
            'id' => 'event_repeat_monthly_month',
            'type' => 'multicheck_inline',
            'options' => $this->getMonthsOptions(),
            'default' => array_keys($this->getMonthsOptions()),
            'classes'   => ['event-repeat', 'event-repeat-monthly'],
        ]);
        // Exceptions
        $cmb->add_field([
            'name' => __('Dates Exceptions', 'rrze-notices'),
            'desc' => __('Enter dates to be skipped (format YYYY-MM-DD, e.g. 2023-12-31). One date per line.', 'rrze-notices'),
            'id' => 'event_date_exceptions',
            'type' => 'textarea_small',
            'sanitization_cb' => [$this, 'sanitizeTextarea'],
            'escape_cb' => [$this, 'escapeTextareaValue'],
            'classes'   => ['event-repeat'],
        ]);
        // Additions
        $cmb->add_field([
            'name' => __('Dates Additions', 'rrze-notices'),
            'desc' => __('Add additional dates (format YYYY-MM-DD, e.g. 2023-12-31). One date per line.', 'rrze-notices'),
            'id' => 'event_date_additions',
            'type' => 'textarea_small',
            'sanitization_cb' => [$this, 'sanitizeTextarea'],
            'escape_cb' => [$this, 'escapeTextareaValue'],
            'classes'   => ['event-repeat'],
        ]);
    }

    /**
     * Get an array of display options
     * e.g. $options['hide_title'] = 'Hide Title'
     *
     * @return array
     */
    public function getDisplayOptions()
    {
        $options = [
            'hide_title' => __('Hide Title', 'rrze-notices'),
            'alt_style' => __('Use alternative style', 'rrze-notices')
        ];
        if (
            current_user_can('manage_network_options') &&
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
    public function getVisibilityByUserRoleOptions()
    {
        $availableRoles = Users::getRoles();
        $roleVisibility = [];
        $visibility = [];
        foreach ($roleVisibility as $k) {
            if (isset($availableRoles[$k])) {
                $visibility[] = $k;
            }
        }

        $options = [];
        if (
            current_user_can('manage_network_options') &&
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
    public function getDowOptions()
    {
        global $wp_locale;
        $options = [];
        for ($i = 0; $i <= 6; $i++) {
            $dow = strtolower(date('l', strtotime("Sunday +{$i} days")));
            $options[$dow] = $wp_locale->get_weekday_abbrev($wp_locale->get_weekday($i));
        }
        return $options;
    }

    /**
     * Get an array of month days options
     * e.g. $options[1] = 1
     *
     * @return array
     */
    public function getMonthDaysOptions()
    {
        return array_combine(range(1, 31), range(1, 31));
    }

    /**
     * Get an array of months options
     * e.g. $options['jan'] = 'January'
     *
     * @return array
     */
    public function getMonthsOptions()
    {
        global $wp_locale;
        $options = [];
        for ($i = 1; $i <= 12; $i++) {
            $month = strtolower(substr(date('M', mktime(0, 0, 0, $i, 1)), 0, 3));
            $options[$month] = $wp_locale->get_month_abbrev($wp_locale->get_month(sprintf('%02d', $i)));
        }
        return $options;
    }

    /**
     * Saves the event_uid field if it has not already been saved
     *
     * @param bool $updated
     * @param string $action
     * @param \CMB2_Field $field
     * @return void
     */
    public function saveNoticeStartField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        if (!get_post_meta($postId, 'event_uid', true)) {
            update_post_meta($postId, 'event_uid', Utils::createUid($postId));
        }
    }

    /**
     * Save event_start date as GMT timestamp
     *
     * @param bool $updated
     * @param string $action
     * @param \CMB2_Field $field
     * @return void
     */
    public function saveEventStartField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        $timestamp = $field->value;
        $timestampGmt = strtotime(get_gmt_from_date(date('Y-m-d H:i:s', $timestamp)));
        update_post_meta($postId, 'event_start_gmt', $timestampGmt);
    }

    /**
     * Save event_start date as GMT timestamp
     *
     * @param bool $updated
     * @param string $action
     * @param \CMB2_Field $field
     * @return void
     */
    public function saveEventEndField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        $timestamp = $field->value;
        $timestampGmt = strtotime(get_gmt_from_date(date('Y-m-d H:i:s', $timestamp)));
        update_post_meta($postId, 'event_end_gmt', $timestampGmt);
    }

    /**
     * Save event_repeat_lastdate date as GMT timestamp
     *
     * @param bool $updated
     * @param string $action
     * @param \CMB2_Field $field
     * @return void
     */
    public function saveEventRepeatLastdateField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        $timestamp = $field->value;
        $timestampGmt = strtotime(get_gmt_from_date(date('Y-m-d H:i:s', $timestamp)));
        update_post_meta($postId, 'event_repeat_lastdate_gmt', $timestampGmt);
    }

    /**
     * Sanitize textarea field
     *
     * @param string $value
     * @param array $field_args
     * @param \CMB2_Field $field
     * @return mixed string|array
     */
    public function sanitizeTextarea($value, $field_args, $field)
    {
        if (!empty($value)) {
            $value = explode(PHP_EOL, sanitize_textarea_field($value));
            $value = array_unique(array_filter(array_map('trim', $value)));
            $value = array_filter(array_map([$this, 'validateDate'], $value));
            sort($value);
        }
        return !empty($value) ? $value : '';
    }

    /**
     * Escape textarea field value
     *
     * @param mixed $value
     * @param array $field_args
     * @param \CMB2_Field $field
     * @return string
     */
    public function escapeTextareaValue($value, $field_args, $field)
    {
        if (!empty($value) && is_array($value)) {
            return implode(PHP_EOL, $value);
        }
        return '';
    }

    /**
     * Validate date
     *
     * @param string $date
     * @return string
     */
    public function validateDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date ? $d->format('Y-m-d') : '';
    }
}
