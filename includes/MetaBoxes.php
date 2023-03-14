<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;
use RRZE\Notices\Util;

class MetaBoxes
{
    protected $customPostType;

    protected $siteOptionsName;

    protected $siteOptions;

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();

        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
    }

    public function onLoaded()
    {
        add_action('cmb2_admin_init', [$this, 'metabox']);
        add_action('cmb2_save_field_rrze_notices_expiration', [$this, 'afterSaveExpirationField'], 10, 3);
    }

    public function metabox()
    {
        $cmb = new_cmb2_box([
            'id' => 'rrze_notices_metabox',
            'title' => __('Notice Settings', 'rrze-notices'),
            'object_types' => ['notice'],
            'context' => 'normal',
            'priority' => 'high',
            'show_names' => true
        ]);

        $cmb->add_field([
            'id'   => 'rrze_notices_type',
            'name' => __('Type', 'rrze-notices'),
            'type' => 'radio',
            'default' => 'info',
            'options' => [
                'info' => __('Info', 'rrze-notices'),
                'warning' => __('Warning', 'rrze-notices')
            ]
        ]);

        $cmb->add_field([
            'name' => __('Expiration', 'rrze-notices'),
            'id' => 'rrze_notices_expiration',
            'type' => 'text_datetime_timestamp',
            'date_format' => _x('Y/m/d', 'expiration date format', 'rrze-notices'),
            'time_format' => _x('g:i a', 'expiration time format', 'rrze-notices'),
            'attributes' => [
                'data-timepicker' => json_encode(
                    [
                        'timeFormat' => 'HH:mm',
                        'stepMinute' => 10
                    ]
                ),
                'required' => 'required'
            ]
        ]);

        $cmb->add_field([
            'name' => __('Display', 'rrze-notices'),
            'id' => 'rrze_notices_display',
            'type' => 'multicheck',
            'options' => $this->getDisplayOptions()
        ]);

        $cmb->add_field([
            'name' => __('Visibility', 'rrze-notices'),
            'id' => 'rrze_notices_visibility',
            'type' => 'multicheck',
            'options' => $this->getVisibilityOptions()
        ]);
    }

    public function getDisplayOptions()
    {
        $options = [
            'hide_title' => __('Hide Title', 'rrze-notices'),
            'alternative_style' => __('Use alternative style', 'rrze-notices')
        ];
        if (is_super_admin() && is_multisite() && Utils::isPluginActiveForNetwork()) {
            $options['network_wide'] = __('Network-Wide', 'rrze-notices');
        }
        return $options;
    }

    public function getVisibilityOptions()
    {
        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Utils::getRoles());
        $roleVisibility = [];
        $visibility = [];
        foreach ($roleVisibility as $k) {
            if (isset($availableRoles[$k])) {
                $visibility[] = $k;
            }
        }

        $options = [];
        $countUsers = count_users();
        foreach ($availableRoles as $role => $name) {
            $count = isset($countUsers['avail_roles'][$role]) ? $countUsers['avail_roles'][$role] : '-';
            $count = 'all' === $role ? $countUsers['total_users'] : $count;
            $options[$role] = sprintf('%s (%d)', $name, $count);
        }
        return $options;
    }

    public function afterSaveExpirationField(bool $updated, string $action, \CMB2_Field $field)
    {
        $postId = $field->object_id;
        $expiration = $field->value;
        $expirationGmt = strtotime(get_gmt_from_date(date('Y-m-d H:i:s', $expiration)));
        update_post_meta($postId, 'rrze_notices_expiration_gmt', $expirationGmt);
    }
}
