<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Query;

class ShowNotices
{
    protected $notices;

    protected $customPostType;

    protected $userMeta = 'rrze_notice_dismissed';

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
    }

    public function onLoaded() {
        add_action('admin_init', [$this, 'adminInit']);
    }
    
    public function adminInit() {
        $this->notices = new Notices();
        $this->setNotices();
        $this->showNotices();    
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
                if ($postContent) {
                    $this->addNotices($postId, $postTitle, $postContent);
                }
            }
        }
    }

    protected function addNotices($postId, $postTitle, $postContent)
    {
        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
        $roleVisibility = get_post_meta($postId, 'rrze_notices_role_visibility', true);
        
        if (!empty($roleVisibility)) { 
            $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
        } else {
            $roleVisibility = [];
        }        

        $current_user = wp_get_current_user();
        if (!Util::arrayValInArray($current_user->roles, $roleVisibility) && ! in_array('all', $roleVisibility)) {
            return;
        }
        
        $id = $postId;
        $title = get_post_meta($postId,'rrze_notices_hide_title', false) ? false : $postTitle;
        $content = $postContent;   
        $noticeType = get_post_meta($postId, 'rrze_notices_type', true);
        $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
        $altStyle = ! get_post_meta($postId, 'rrze_notices_alt_style', 0) ? false : true;

        $this->notices->add(
            $id,
            $title,
            $content,
            [
                'scope'         => 'user',
                'screens'       => ['dashboard'],
                'type'          => $type,
                'alt_style'     => $altStyle,
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