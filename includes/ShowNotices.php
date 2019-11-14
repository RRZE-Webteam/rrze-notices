<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Query;

class ShowNotices
{
    protected $notices;

    protected $customPostType;
    
    protected $siteOptions;

    protected $userMeta = 'rrze_notice_dismissed';

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = new Notices;
    }

    public function onLoaded()
    {
        add_action('admin_init', [$this, 'adminInit']);
    }
    
    public function adminInit()
    {
        $this->setNetworkWideNotices();
        $this->setNotices();
        $this->showNotices();
    }
    
    protected function setNetworkWideNotices()
    {
        if (!is_multisite()) {
            return;
        }
                
        $notices = $this->siteOptions->network_wide;
        if (empty($notices)) {
            return;
        }

        foreach ($notices as $id => $notice) {
            global $wpdb;
            $idArray = explode('-', $id);
            $blogId = isset($idArray[0]) ? absint($idArray[0]) : 0;
            if ($wpdb->blogid == $blogId) {
                continue;
            }
                        
            $allIntervals = Util::getExpirationIntervals();
            $expiration = array_key_exists($notice->expiration, $allIntervals) ? $notice->expiration : 'week';
            $expired = Util::isExpired($expiration, $notice->date_gmt);
            if ($expired) {
                if (isset($this->siteOptions->network_wide[$id])) {
                    unset($this->siteOptions->network_wide[$id]);
                    update_site_option($this->siteOptionsName, $this->siteOptions);
                }
                continue;
            }
                                    
            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
            $roleVisibility = $notice->visibility;
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
            if (!empty($visibility)) {
                $visibility = !is_array($visibility) ? [$visibility] : $visibility;
            } else {
                $visibility = [];
            }
            $current_user = wp_get_current_user();
            if (!Util::arrayValInArray($current_user->roles, $visibility) && !in_array('all', $visibility)) {
                continue;
            }
                
            $content = $notice->content;
            if (!$content) {
                continue;
            }
            
            $title = $notice->title;
            $type = array_key_exists($notice->type, Util::getNoticeTypes()) ? $notice->type : 'info';
            $altStyle = $notice->alt_style ? true : false;
            
            $editLink = '';
            if (Util::isAllowedNetworkWide()) {
                $blogId = isset($idArray[0]) ? absint($idArray[0]) : 0;
                $postId = isset($idArray[1]) ? absint($idArray[1]) : 0;
                switch_to_blog($blogId);
                $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');
                restore_current_blog();
                $editText = __('Edit (Network-Wide)', 'rrze-notices');
                $editLink = sprintf('<a style="float: right; position: relative; bottom: 26px;" href="%1$s">%2$s</a>', $adminLink, $editText);
            }
                        
            $this->addNotices($id, $title, $content, $type, $altStyle, $editLink);
        }
    }
    
    protected function setNotices()
    {
        $posts = get_posts([
            'post_type' => $this->customPostType,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        if (!$posts) {
            return;
        }
        foreach ($posts as $post) {
            $id = $post->ID;
            
            $allIntervals = Util::getExpirationIntervals();
            $noticeExpiration = get_post_meta($id, 'rrze_notices_expiration', true);
            $expiration = array_key_exists($noticeExpiration, $allIntervals) ? $noticeExpiration : 'week';
            $expired = Util::isExpired($expiration, $post->post_date_gmt);
            if ($expired) {
                continue;
            }
            
            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
            $roleVisibility = get_post_meta($id, 'rrze_notices_visibility', true);
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
            $current_user = wp_get_current_user();
            if (!Util::arrayValInArray($current_user->roles, $visibility) && !in_array('all', $visibility)) {
                continue;
            }
                            
            $content = $post->post_content;
            if (!$content) {
                continue;
            }

            $title = $post->post_title;
                            
            $noticeType = get_post_meta($id, 'rrze_notices_type', true);
            $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
            $altStyle = get_post_meta($id, 'rrze_notices_alt_style', true) ? true : false;
            
            $editLink = '';
            $adminLink = admin_url('post.php?post=' . esc_attr($id) . '&action=edit');
            $editText = __('Edit', 'rrze-notices');
            
            if (Util::isAllowedNetworkWide()) {
                global $wpdb;
                $blogId = $wpdb->blogid;
                $networkWideid = sprintf('%d-%d', $blogId, $id);
                if (isset($this->siteOptions->network_wide[$networkWideid])) {
                    $editText = isset($this->siteOptions->network_wide[$networkWideid]) ? __('Edit (Network-Wide)', 'rrze-notices') : '';
                }
            }
                   
            if (current_user_can('manage_options')) {
                $editLink = sprintf('<a style="float: right; position: relative; bottom: 26px;" href="%1$s">%2$s</a>', $adminLink, $editText);
            }
            
            $this->addNotices($id, $title, $content, $type, $altStyle, $editLink);
        }
    }

    protected function addNotices($id, $title, $content, $type, $altStyle, $editLink)
    {
        $this->notices->add(
            $id,
            $title,
            $content,
            [
                'scope'         => 'user',
                'screens'       => ['dashboard'],
                'type'          => $type,
                'alt_style'     => $altStyle,
                'capability'    => 'read',
                'edit_link'     => $editLink,
                'option_prefix' => $this->userMeta
            ]
        );
    }

    protected function showNotices()
    {
        $this->notices->boot();
    }
}
