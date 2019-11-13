<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;
use RRZE\Notices\Util;

class MetaBoxes
{
    const META_TYPE_BOX = 'rrze_notices_type_box';

    const META_EXPIRATION_BOX = 'rrze_notices_expiration_box';
    
    const META_DISPLAY_BOX = 'rrze_notices_display_box';

    const META_VISIBILITY_BOX = 'rrze_notices_visibility_box';

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
        add_action('save_post', [$this, 'savePost'], 10, 2);
        add_action('post_updated', [$this, 'postUpdated'], 10, 3);
        add_action('delete_post', [$this, 'deletePost']);
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
            static::META_EXPIRATION_BOX,
            __('Expiration', 'rrze-notices'),
            [$this, 'expirationFields'],
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
            static::META_VISIBILITY_BOX,
            __('Visibility', 'rrze-notices'),
            [$this, 'visibilityFields'],
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

    public function expirationFields()
    {
        global $post;

        wp_nonce_field(basename(__FILE__), 'notice_meta_fields');
        
        $allIntervals = Util::getExpirationIntervals();
        $noticeExpiration = get_post_meta($post->ID, 'rrze_notices_expiration', true);
        $expiration = array_key_exists($noticeExpiration, $allIntervals) ? $noticeExpiration : 'week';
        $expired = Util::isExpired($expiration, $post->post_date_gmt);
        
        echo '<ul>';
        
        echo '<li>';
        echo '<select name="rrze_notices_expiration">';
        foreach ($allIntervals as $key => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                $key,
                selected($expiration, $key, false),
                $label
            );
        }
        echo '</select>';
        echo $expired ? '<span class="expired">' . __('Expired!', 'rrze_notices') . '</span>' : '';
        echo '</li>';
        
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
            global $wpdb;
            $blogId = $wpdb->blogid;
            $id = sprintf('%d-%d', $blogId, $post->ID);
            $networkWide = isset($this->siteOptions->network_wide[$id]) ? 1 : 0;
            echo '<li><label>';
            echo '<input name="rrze_notices_network_wide" type="checkbox" value="1" ' . checked($networkWide, 1, false) . '>';
            echo __('Network-Wide', 'rrze-notices').'</label></li>';
        }
                
        echo '</ul>';
    }

    public function visibilityFields()
    {
        global $post;

        wp_nonce_field(basename(__FILE__), 'notice_meta_fields');

        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
        $roleVisibility = get_post_meta($post->ID, 'rrze_notices_visibility', true);
        if (!empty($roleVisibility)) {
            $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
        } else {
            $roleVisibility = [];
        }
        $visibility = [];
        foreach ($roleVisibility as $k) {
            if (isset($availableRoles[$k])) {
                $visibility[] = $k;
            }
        }
        
        $count_users = count_users();
        
        echo '<ul>';
        foreach ($availableRoles as $role => $name) {
            $count = isset($count_users['avail_roles'][$role]) ? $count_users['avail_roles'][$role]: '-';
            $count = 'all' === $role ? $count_users['total_users']: $count;
            echo '<li><label>';
            printf('<input name="rrze_notices_visibility[]" type="checkbox" value="%s" %s> %s (%d)', $role, checked($role, in_array($role, $visibility) ? $role : '', false), $name, $count);
            echo '</label></li>';
        }
        echo '</ul>';
    }

    public function savePost($postId, $post)
    {
        if (is_int(wp_is_post_revision($postId))) {
            return;
        }
        if (is_int(wp_is_post_autosave($postId))) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
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

        $noticeExpiration = $_POST['rrze_notices_expiration'];
        $noticeMetas['rrze_notices_expiration'] = array_key_exists($noticeExpiration, Util::getExpirationIntervals()) ? $noticeExpiration : 'week';

        $noticeMetas['rrze_notices_hide_title'] = isset($_POST['rrze_notices_hide_title']) ? 1 : 0;
        $noticeMetas['rrze_notices_alt_style'] = isset($_POST['rrze_notices_alt_style']) ? 1 : 0;
        
        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
        $roleVisibility = isset($_POST['rrze_notices_visibility']) ? $_POST['rrze_notices_visibility'] : [];
        if (!empty($roleVisibility)) {
            $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
        } else {
            $roleVisibility = [];
        }
        $visibility = [];
        foreach ($roleVisibility as $k) {
            if (isset($availableRoles[$k])) {
                $visibility[] = $k;
            }
        }
        $noticeMetas['rrze_notices_visibility'] = $visibility;

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
            global $wpdb;
            $blogId = $wpdb->blogid;
            $id = sprintf('%d-%d', $blogId, $postId);
            $networkWide = Util::isAllowedNetworkWide() && isset($_POST['rrze_notices_network_wide']) ? 1 : 0;
            if ($networkWide && $post->post_status == 'publish') {
                $this->siteOptions->network_wide[$id] = (object)[
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'date_gmt' => $post->post_date_gmt,
                    'type' => $noticeMetas['rrze_notices_type'],
                    'expiration' => $noticeMetas['rrze_notices_expiration'],
                    'hide_title' => $noticeMetas['rrze_notices_hide_title'],
                    'alt_style' => $noticeMetas['rrze_notices_alt_style'],
                    'visibility' => $noticeMetas['rrze_notices_visibility']
                ];
            } elseif (isset($this->siteOptions->network_wide[$id])) {
                unset($this->siteOptions->network_wide[$id]);
            }
            update_site_option($this->siteOptionsName, $this->siteOptions);
        }
    }
    
    public function postUpdated($postId, $postAfter, $postBefore)
    {    
        if (is_int(wp_is_post_revision($postId))) {
            return;
        }
        if (is_int(wp_is_post_autosave($postId))) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($postAfter->post_type) || $this->customPostType != $postAfter->post_type) {
            return;
        }
        $postTypeObject = get_post_type_object($postAfter->post_type);
        if (!current_user_can($postTypeObject->cap->edit_post, $postId)) {
            return;
        }

        if (Util::isAllowedNetworkWide() && $postAfter->post_status != 'publish') {
            global $wpdb;
            $blogId = $wpdb->blogid;
            $id = sprintf('%d-%d', $blogId, $postId);
            if (isset($this->siteOptions->network_wide[$id])) {
                unset($this->siteOptions->network_wide[$id]);
                update_site_option($this->siteOptionsName, $this->siteOptions);
            }
        }
    }
        
    public function deletePost($postId)
    {
        if (is_int(wp_is_post_revision($postId))) {
            return;
        }
        if (is_int(wp_is_post_autosave($postId))) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $post = get_post($postId);
        if (!isset($post->post_type) || $this->customPostType != $post->post_type) {
            return;
        }
        $postTypeObject = get_post_type_object($post->post_type);
        if (!current_user_can($postTypeObject->cap->edit_post, $postId)) {
            return;
        }
        
        if (Util::isAllowedNetworkWide()) {
            global $wpdb;
            $blogId = $wpdb->blogid;
            $id = sprintf('%d-%d', $blogId, $postId);
            if (isset($this->siteOptions->network_wide[$id])) {
                unset($this->siteOptions->network_wide[$id]);
                update_site_option($this->siteOptionsName, $this->siteOptions);
            }
        }
    }
}
