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

}
