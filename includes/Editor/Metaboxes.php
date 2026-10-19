<?php

declare(strict_types=1);

namespace RRZE\Notices\Editor;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT\CPTNotice;
use RRZE\Notices\Helper;
use RRZE\Notices\Admin\Users;

/**
 * Defines the notice settings fields below the editor (CMB2 metabox).
 *
 * CMB2 is a library that creates settings panels on the post-editing
 * screen. This class registers fields for notice type, visibility,
 * network-wide publishing, and the visible-until date.
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
        add_action('cmb2_save_field_notice_visible_until', [__CLASS__, 'saveVisibleUntilField'], 10, 3);
    }


    /**
     * Register the complete notice-settings metabox and its fields.
     *
     * @return void
     */
    public static function registerNoticeMetabox(): void
    {
        $objectTypes = [CPTNotice::POST_TYPE];

        $cmb = new_cmb2_box([
            'id' => 'rrze_notices_metabox',
            'title' => __('Notice Settings', 'rrze-notices'),
            'object_types' => $objectTypes,
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true
        ]);

        self::addNoticeFields($cmb);
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
            'id' => 'notice_type',
            'name' => __('Type', 'rrze-notices'),
            'type' => 'radio',
            'default' => 'info',
            'options' => [
                'info' => __('Info', 'rrze-notices'),
                'warning' => __('Warning', 'rrze-notices'),
                'error' => __('Critical', 'rrze-notices')
            ],
        ]);
        // Notice Display options
        $cmb->add_field([
            'name' => __('Display', 'rrze-notices'),
            'id' => 'notice_display',
            'type' => 'multicheck',
            'select_all_button' => false,
            'options' => self::getDisplayOptions()
        ]);
        // Notice Visibility
        $cmb->add_field([
            'name' => __('Visibility', 'rrze-notices'),
            'id' => 'notice_visibility',
            'type' => 'radio',
            'options' => [
                'all' => __('All Users', 'rrze-notices'),
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
        // Event start date and time
        $cmb->add_field([
            'id' => 'notice_event_start',
            'name' => __('Event Start', 'rrze-notices')
                . '<br><span style="font-weight: normal;color: #666;font-size: 13px;">'
                . __('(required)', 'rrze-notices') . '</span>',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('d.m.Y', 'date format', 'rrze-notices'),
            'time_format' => _x('H:i', 'time format', 'rrze-notices'),
        ]);
        // Event end date and time
        $cmb->add_field([
            'id' => 'notice_event_end',
            'name' => __('Event End', 'rrze-notices')
                . '<br><span style="font-weight: normal;color: #666;font-size: 13px;">'
                . __('(optional)', 'rrze-notices') . '</span>',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('d.m.Y', 'date format', 'rrze-notices'),
            'time_format' => _x('H:i', 'time format', 'rrze-notices'),
        ]);
        $cmb->add_field([
            'name' => __('Visible until', 'rrze-notices')
                . '<br><span style="font-weight: normal;color: #666;font-size: 13px;">'
                . __('(Date and time)', 'rrze-notices') . '</span>',
            'id' => 'notice_visible_until',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('d.m.Y', 'date format', 'rrze-notices'),
            'time_format' => _x('H:i', 'time format', 'rrze-notices'),
            'attributes' => [
                'required' => 'required'
            ],
        ]);

    }


    /**
     * Get an array of display options for the notice metabox.
     *
     * @return array
     */
    private static function getDisplayOptions(): array
    {
        $options = [
            'hide_title' => __('Hide Title', 'rrze-notices'),
            'show_event_date' => __('Show Event Date', 'rrze-notices'),
        ];
        if (
            is_super_admin() &&
            Helper::isPluginActiveForNetwork()
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
        if (is_super_admin() && Helper::isPluginActiveForNetwork()) {
            $availableRoles = Users::getRoles(builtInOnly: true);
        } else {
            $availableRoles = Users::getRoles();
        }

        $options = [];
        foreach ($availableRoles as $role => $name) {
            $options[$role] = $name;
        }

        return $options;

    }


    /**
     * Remove stale role selections when visibility changes to all users.
     *
     * @param bool $updated Whether CMB2 changed the stored value.
     * @param string $action CMB2 save action.
     * @param \CMB2_Field $field Saved visibility field.
     * @return void
     */
    public static function saveNoticeVisibilityField(bool $updated, string $action, \CMB2_Field $field): void
    {
        $postId = $field->object_id;
        if ($field->value === 'all') {
            delete_post_meta($postId, 'notice_visibility_roles');
        }
    }

    /**
     * Save the display-until value as an absolute GMT timestamp.
     *
     * @param bool $updated Whether CMB2 changed the stored value.
     * @param string $action CMB2 save action.
     * @param \CMB2_Field $field Saved display-until field.
     * @return void
     */
    public static function saveVisibleUntilField(bool $updated, string $action, \CMB2_Field $field): void
    {
        $postId = $field->object_id;
        if (!empty($field->value)) {
            // CMB2 text_datetime_timestamp already returns a UTC Unix timestamp.
            update_post_meta($postId, 'notice_visible_until_gmt',
                (int)$field->value);
        } else {
            delete_post_meta($postId, 'notice_visible_until_gmt');
        }
    }

}
