<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Display
{
    protected $customPostType;

    protected $siteOptions;

    protected $siteOptionsName;

    protected $userMeta = 'rrze_notices_dismissed';

    protected $notices;

    public function __construct()
    {
        $this->customPostType = CPT::POST_TYPE;
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = new Notices;
    }

    public function loaded()
    {
        add_action('admin_init', function () {
            $this->setNetworkWideNotices();
            $this->setNotices();
            $this->notices->boot();
        });

        add_action('admin_head', function () {
            $this->previewNotice();
            $this->notices->boot();
        }, 99);

        //add_filter('user_can_richedit', [$this, 'disableRichedit']);        
    }

    protected function setNetworkWideNotices()
    {
        if (!Utils::isPluginActiveForNetwork()) {
            return;
        }

        $notices = $this->siteOptions->network_wide;
        if (empty($notices)) {
            return;
        }

        foreach ($notices as $noticeId => $notice) {
            $idArray = explode('-', $noticeId);
            $blogId = isset($idArray[0]) ? absint($idArray[0]) : 0;
            $postId = isset($idArray[1]) ? absint($idArray[1]) : 0;
            if (!$blogId || !$postId) {
                continue;
            }

            if (get_current_blog_id() == $blogId) {
                continue;
            }

            if (!isset($this->siteOptions->network_wide[$noticeId])) {
                continue;
            }

            $notice = (object) $notice;

            $title = isset($notice->title) ? $notice->title : '';
            $content = isset($notice->content) ? $notice->content : '';
            $type = isset($notice->type) ? $notice->type : 'info';
            // $expiration = isset($notice->expiration) ? $notice->expiration : current_time('timestamp');
            // $expirationGmt = isset($notice->expiration_gmt) ? $notice->expiration_gmt : time();
            $altStyle = isset($notice->alt_style) ? $notice->alt_style : false;
            $hideTitle = isset($notice->hide_title) ? $notice->hide_title : false;
            $visibility = isset($notice->visibility) ? $notice->visibility : true;
            $adminLink = isset($notice->admin_link) ? $notice->admin_link : '';

            // $expired = Utils::isExpired($expirationGmt);
            // if ($expired) {
            //     unset($this->siteOptions->network_wide[$noticeId]);
            //     update_site_option($this->siteOptionsName, $this->siteOptions);
            //     continue;
            // }

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
            $roleVisibility = $visibility;
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
            if (!Utils::arrayValInArray($current_user->roles, $visibility) && !in_array('all', $visibility)) {
                continue;
            }

            if (!$content) {
                continue;
            }

            $title = !$hideTitle ? $title : '';
            $type = array_key_exists($type, Post::getNoticeTypes()) ? $type : 'info';

            $editLink = '';
            if (
                current_user_can('manage_network_options')
                && Utils::isPluginActiveForNetwork()
            ) {
                $editText = __('Edit (Network-Wide)', 'rrze-notices');
                $editLink = sprintf('<a class="admin-edit-link" href="%1$s">%2$s</a>', $adminLink, $editText);
            }

            $this->notices->add(
                $noticeId,
                $title,
                $content,
                [
                    'screens'       => ['dashboard'],
                    'type'          => $type,
                    'alt_style'     => $altStyle,
                    'capability'    => 'read',
                    'edit_link'     => $editLink
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
        if (!$posts) {
            return;
        }
        foreach ($posts as $post) {
            $postId = $post->ID;
            $blogId = get_current_blog_id();
            $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);
            $title = $post->post_title;
            $content = $post->post_content;
            $type = Post::getType($postId);
            $altStyle = Post::altStyle($postId);
            $hideTitle = Post::hideTitle($postId);
            $visibility = Post::getVisibility($postId);
            $noticeStart = Post::getNoticeStart($postId);
            $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');

            $expired = Post::isExpired($postId);
            if ($expired) {
                continue;
            }

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
            $roleVisibility = $visibility;
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
            if (!Utils::arrayValInArray($current_user->roles, $visibility) && !in_array('all', $visibility)) {
                continue;
            }

            if (!$content) {
                continue;
            }

            $title = !$hideTitle ? $title : '';

            $title = !$hideTitle ? $title : '';
            $type = array_key_exists($type, Post::getNoticeTypes()) ? $type : 'info';

            $editLink = '';
            $editText = __('Edit', 'rrze-notices');
            $capability = 'manage_options';

            if (
                current_user_can('manage_network_options')
                && Utils::isPluginActiveForNetwork()
                && isset($this->siteOptions->network_wide[$noticeId])
            ) {
                $capability = $this->siteOptions->access;
                $editText = isset($this->siteOptions->network_wide[$noticeId]) ? __('Edit (Network-Wide)', 'rrze-notices') : '';
            }

            if (current_user_can($capability)) {
                $editLink = sprintf('<a class="admin-edit-link" href="%1$s">%2$s</a>', $adminLink, $editText);
            }

            $this->notices->add(
                $noticeId,
                $title,
                $content,
                [
                    'screens'       => ['dashboard'],
                    'type'          => $type,
                    'alt_style'     => $altStyle,
                    'capability'    => 'read',
                    'edit_link'     => $editLink
                ]
            );
        }
    }

    protected function previewNotice()
    {
        $screen = get_current_screen();
        if ($screen->id != $this->customPostType) {
            return;
        }

        global $pagenow;
        if ($pagenow == 'post-new.php') {
            return;
        }

        global $post;
        $postId = $post->ID;

        $content = $post->post_content;
        if (!$content) {
            return;
        }

        $title = !Post::hideTitle($postId) ? $post->post_title : false;

        $noticeType = Post::getType($postId);
        $type = array_key_exists($noticeType, Post::getNoticeTypes()) ? $noticeType : 'info';
        $altStyle = Post::altStyle($postId);

        $editLink = sprintf('<span class="preview">%s</span>', __('Preview', 'rrze-notices'));;
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%d-%d', $blogId, $postId);

        $this->notices->add(
            $noticeId,
            $title,
            $content,
            [
                'screens'        => [$this->customPostType],
                'type'           => $type,
                'alt_style'      => $altStyle,
                'capability'     => 'read',
                'edit_link'      => $editLink,
                'is_dismissible' => false
            ]
        );
    }

    public function disableRichedit($can)
    {
        global $post;

        if ($this->customPostType == $post->post_type) {
            return false;
        }
        return $can;
    }
}
