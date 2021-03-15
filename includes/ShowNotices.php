<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class ShowNotices
{
    protected $customPostType;

    protected $siteOptions;

    protected $siteOptionsName;

    protected $userMeta = 'rrze_notices_dismissed';

    protected $notices;

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = new Notices;
    }

    public function onLoaded()
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
        if (!is_multisite() || !is_plugin_active_for_network(plugin()->getBaseName())) {
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

            $expired = Util::isExpired($notice->expiration_gmt);
            if ($expired) {
                unset($this->siteOptions->network_wide[$noticeId]);
                update_site_option($this->siteOptionsName, $this->siteOptions);
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

            $title = !$notice->hide_title ? $notice->title : '';
            $type = array_key_exists($notice->type, Util::getNoticeTypes()) ? $notice->type : 'info';
            $altStyle = $notice->alt_style;

            $editLink = '';
            if (
                is_super_admin()
                && is_multisite()
                && is_plugin_active_for_network(plugin()->getBaseName())
            ) {
                switch_to_blog($blogId);
                $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');
                restore_current_blog();
                $editText = __('Edit (Network-Wide)', 'rrze-notices');
                $editLink = sprintf('<a class="admin-edit-link" href="%1$s">%2$s</a>', $adminLink, $editText);
            }

            $this->notices->add(
                $noticeId,
                $title,
                $content,
                [
                    'scope'         => 'user',
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

            $expirationGmt = Util::getExpirationGmt($postId);
            $expired = Util::isExpired($expirationGmt);
            if ($expired) {
                continue;
            }

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
            $roleVisibility = Util::getVisibility($postId);
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

            $title = !Util::hideTitle($postId) ? $post->post_title : false;

            $noticeType = Util::getType($postId);
            $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
            $altStyle = Util::altStyle($postId);

            $editLink = '';
            $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');
            $editText = __('Edit', 'rrze-notices');
            $capability = 'manage_options';
            $blogId = get_current_blog_id();
            $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

            if (
                is_super_admin()
                && is_multisite()
                && is_plugin_active_for_network(plugin()->getBaseName())
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
                    'scope'         => 'user',
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

        $title = !Util::hideTitle($postId) ? $post->post_title : false;

        $noticeType = Util::getType($postId);
        $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
        $altStyle = Util::altStyle($postId);

        $editLink = sprintf('<span class="preview">%s</span>', __('Preview', 'rrze-notices'));;
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%d-%d', $blogId, $postId);

        $this->notices->add(
            $noticeId,
            $title,
            $content,
            [
                'scope'          => 'user',
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
