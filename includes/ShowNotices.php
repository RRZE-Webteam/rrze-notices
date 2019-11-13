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
            if ($wpdb->blogid == $idArray[array_key_first($idArray)]) {
                continue;
            }            
            $current_user = wp_get_current_user();
            if (!Util::arrayValInArray($current_user->roles, $notice->visibility) && !in_array('all', $notice->visibility)) {
                return;
            }

            $title = $notice->hide_title ? false : $notice->title;
            $content = $notice->content;
            if (!$content) {
                continue;
            }
            
            $type = array_key_exists($notice->type, Util::getNoticeTypes()) ? $notice->type : 'info';

            $allIntervals = Util::getExpirationIntervals();
            $expiration = array_key_exists($notice->expiration, $allIntervals) ? $notice->expiration : 'week';
            $expired = Util::isExpired($expiration, $notice->date_gmt);
            if ($expired) {
                continue;
            }
            
            $altStyle = $notice->alt_style ? true : false;
            
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
                    'edit_link'     => false,
                    'option_prefix' => $this->userMeta
                ]
            );
        }
    }
    
    protected function setNotices()
    {
        $posts = get_posts([
            'post_type' => $this->customPostType,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        if ($posts) {
            foreach ($posts as $post) {
                $postId = $post->ID;
                $postTitle = $post->post_title;
                $postContent = $post->post_content;
                
                $allIntervals = Util::getExpirationIntervals();
                $noticeExpiration = get_post_meta($postId, 'rrze_notices_expiration', true);
                $expiration = array_key_exists($noticeExpiration, $allIntervals) ? $noticeExpiration : 'week';
                $expired = Util::isExpired($expiration, $post->post_date_gmt);
                
                if ($postContent && !$expired) {
                    $this->addNotices($postId, $postTitle, $postContent);
                }
            }
        }
    }

    protected function addNotices($postId, $postTitle, $postContent)
    {
        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
        $roleVisibility = get_post_meta($postId, 'rrze_notices_visibility', true);
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
            return;
        }
        
        $id = $postId;
        $title = get_post_meta($postId, 'rrze_notices_hide_title', true) ? false : $postTitle;
        $content = $postContent;
        $noticeType = get_post_meta($postId, 'rrze_notices_type', true);
        $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
        $altStyle = get_post_meta($postId, 'rrze_notices_alt_style', true) ? true : false;
        
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
                'edit_link'     => current_user_can('manage_options') ? true : false,
                'option_prefix' => $this->userMeta
            ]
        );
    }

    protected function showNotices()
    {
        $this->notices->boot();
    }
}
