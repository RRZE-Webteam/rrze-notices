<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;
use RRZE\Notices\Util;

class MetaBoxes
{
    /**
     * [META_TYPE_BOX description]
     * @var string
     */
    const META_TYPE_BOX = 'rrze_notices_type_box';

    /**
     * [META_DISPLAY_BOX description]
     * @var string
     */
    const META_DISPLAY_BOX = 'rrze_notices_display_box';

    /**
     * [META_ROLE_BOX description]
     * @var string
     */
    const META_ROLE_BOX = 'rrze_notices_role_visibility_box';

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
        add_action('save_post', [$this, 'saveNotice'], 10, 2);
    }

    public function addMetaBoxes()
    {
        add_meta_box(
            static::META_TYPE_BOX,
            __('Type', 'rrze-notices'),
            [$this, 'typeFields'],
            $this->customPostType,
            'side',
            'default'
        );

        add_meta_box(
            static::META_DISPLAY_BOX,
            __('Display', 'rrze-notices'),
            [$this, 'displayFields'],
            $this->customPostType,
            'side',
            'default'
        );

        add_meta_box(
            static::META_ROLE_BOX,
            __('Role visibility', 'rrze-notices'),
            [$this, 'roleFields'],
            $this->customPostType,
            'side',
            'default'
        );
    }

    public function typeFields()
    {
        global $post;

        wp_nonce_field(basename(__FILE__), 'notice_meta_fields');

        $noticeType = get_post_meta($post->ID, 'rrze_notices_type', true);
        $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
        
        echo '<ul>';
        
        echo '<li><label>';
        echo '<input name="rrze_notices_type" type="radio" value="info" ' . checked($type, 'info', false) . '>';
        echo __('Info', 'rrze-notices').'</label></li>';

        echo '<li><label>';
        echo '<input name="rrze_notices_type" type="radio" value="warning" ' . checked($type, 'warning', false) . '>';
        echo __('Warning', 'rrze-notices').'</label></li>';
        
        echo '</ul>';
    }

    public function displayFields()
    {
        global $post;

        wp_nonce_field(basename(__FILE__), 'notice_meta_fields');

        $hideTitle = get_post_meta($post->ID, 'rrze_notices_hide_title', true) ? 1 : 0;
        $altStyle = get_post_meta($post->ID, 'rrze_notices_alt_style', true) ? 1 : 0;
                
        echo '<ul>';
        
        echo '<li><label>';
        echo '<input name="rrze_notices_hide_title" type="checkbox" value="1" ' . checked($hideTitle, 1, false) . '>';
        echo __('Hide Title', 'rrze-notices').'</label></li>';

        echo '<li><label>';
        echo '<input name="rrze_notices_alt_style" type="checkbox" value="1" ' . checked($altStyle, 1, false) . '>';
        echo __('Use alternative style', 'rrze-notices').'</label></li>';
        
        if (Util::isAllowedNetworkWide()) {
            $id = sprintf('network-wide-%d', $post->ID);
            $networkWide = isset($this->siteOptions->network_wide[$id]) ? 1 : 0;            
            echo '<li><label>';
            echo '<input name="rrze_notices_network_wide" type="checkbox" value="1" ' . checked($networkWide, 1, false) . '>';
            echo __('Network-Wide', 'rrze-notices').'</label></li>';
        }
                
        echo '</ul>';
    }

    public function roleFields()
    {
        global $post;

        wp_nonce_field(basename(__FILE__), 'notice_meta_fields');

        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
        $roleVisibility = get_post_meta($post->ID, 'rrze_notices_role_visibility', true);
        $count_users = count_users();
        
        if (!empty($roleVisibility)) {
            $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
            $allRoles = array_unique(array_merge(array_keys($availableRoles), array_values($roleVisibility)));
        } else {
            $roleVisibility = [];
            $allRoles = array_keys($availableRoles);
        }
        
        echo '<ul>';
        foreach ($allRoles as $role) {
            $count = isset($count_users['avail_roles'][$role]) ? $count_users['avail_roles'][$role]: '-';
            $count = 'all' === $role ? $count_users['total_users']: $count;
            $name = isset($availableRoles[$role]) ? $availableRoles[$role]: ucfirst($role);
            echo '<li><label>';
            printf('<input name="rrze_notices_role_visibility[]" type="checkbox" value="%s" %s> %s (%d)', $role, checked($role, in_array($role, $roleVisibility) ? $role : '', false), $name, $count);
            echo '</label></li>';
        }
        echo '</ul>';
    }

    public function saveNotice($postId, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post->ID)) {
            return;
        }

        if (!isset($_POST['notice_meta_fields']) || !wp_verify_nonce($_POST['notice_meta_fields'], basename(__FILE__))) {
            return;
        }
        
        if (!isset($post->post_type) || $this->customPostType != $post->post_type) {
            return;
        }

        $postTypeObject = get_post_type_object($post->post_type);

        if (!current_user_can($postTypeObject->cap->edit_post, $postId)) {
            return;
        }

        $noticeType = $_POST['rrze_notices_type'];
        $noticeMetas['rrze_notices_type'] = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';

        $noticeMetas['rrze_notices_hide_title'] = isset($_POST['rrze_notices_hide_title']) ? 1 : 0;
        $noticeMetas['rrze_notices_alt_style'] = isset($_POST['rrze_notices_alt_style']) ? 1 : 0;
        
        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
        $roleVisibility = isset($_POST['rrze_notices_role_visibility']) ? $_POST['rrze_notices_role_visibility'] : [];
        $visibility = [];
        foreach ($roleVisibility as $k) {
            if (isset($availableRoles[$k])) {
                $visibility[] = $k;
            }
        }
        $noticeMetas['rrze_notices_role_visibility'] = $visibility;

        foreach ($noticeMetas as $key => $value) {
            if (get_post_meta($postId, $key, true)) {
                update_post_meta($postId, $key, $value);
            } else {
                add_post_meta($postId, $key, $value);
            }
            if (!$value) {
                delete_post_meta($postId, $key);
            }
        }
        
        if (Util::isAllowedNetworkWide()) {
            $id = sprintf('network-wide-%d', $postId);
            $networkWide = Util::isAllowedNetworkWide() && isset($_POST['rrze_notices_network_wide']) ? 1 : 0;
            if ($networkWide) {
                $this->siteOptions->network_wide[$id] = (object)[
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'type' => $noticeMetas['rrze_notices_type'],
                    'hide_title' => $noticeMetas['rrze_notices_hide_title'],
                    'alt_style' => $noticeMetas['rrze_notices_alt_style'],
                    'visibility' => $noticeMetas['rrze_notices_role_visibility']
                ];
            } elseif (isset($this->siteOptions->network_wide[$id])) {
                unset($this->siteOptions->network_wide[$id]);
            }
            update_site_option($this->siteOptionsName, $this->siteOptions);
        }
    }
}
