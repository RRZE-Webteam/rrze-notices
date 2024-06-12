<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Class Display
 *
 * A class for managing the display of notices.
 */
class Display
{
    /**
     * @var object
     */
    protected $siteOptions;

    /**
     * @var string
     */
    protected $siteOptionsName;

    /**
     * @var string
     */
    protected $userMeta = 'rrze_notices_dismissed';

    /**
     * @var object
     */
    protected $notices;

    /**
     * Display constructor
     */
    public function __construct()
    {
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = new Notices;
    }

    /**
     * Initialize the class by setting up action hooks
     */
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
    }

    /**
     * Set network-wide notices
     */
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

            if (!get_blog_status($blogId, 'public')) {
                continue;
            }

            if (!isset($this->siteOptions->network_wide[$noticeId])) {
                continue;
            }

            $notice = (object) $notice;

            $title = $notice->title ?? '';
            $content = $notice->content ?? '';
            $type = $notice->type ?? 'info';
            $altStyle = $notice->alt_style ?? false;
            $hideTitle = $notice->hide_title ?? false;
            $visibility = $notice->visibility ?? true;
            $subscriptionLink = $notice->subscription_link ?? '';
            $adminLink = $notice->admin_link ?? '';

            switch_to_blog($blogId);
            if (Event::isExpired($postId) || !Post::isNoticeStarted($postId)) {
                continue;
            }
            restore_current_blog();

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

            $subscriptionLink = sprintf(
                '<a href="%1$s">%2$s</a>',
                $subscriptionLink,
                __('Add to calendar', 'rrze-notices')
            );

            $editLink = '';
            if (
                current_user_can('manage_network_options')
                && Utils::isPluginActiveForNetwork()
            ) {
                $editLink = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    $adminLink,
                    __('Edit (Network-Wide)', 'rrze-notices')
                );
            }

            $this->notices->add(
                $noticeId,
                $title,
                $content,
                [
                    'screens'           => ['dashboard'],
                    'type'              => $type,
                    'alt_style'         => $altStyle,
                    'capability'        => 'read',
                    'subscription_link' => $subscriptionLink,
                    'edit_link'         => $editLink
                ]
            );
        }
    }

    /**
     * Set notices
     */
    protected function setNotices()
    {
        $posts = get_posts([
            'post_type' => CPT::POST_TYPE,
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
            $subscriptionLink = Export::makeIcsLink($postId);
            $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');

            if (Event::isExpired($postId) || !Post::isNoticeStarted($postId)) {
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

            $subscriptionLink = sprintf(
                '<a href="%1$s">%2$s</a>',
                Export::makeIcsLink($postId),
                __('Add to calendar', 'rrze-notices')
            );

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
                $editLink = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    $adminLink,
                    $editText
                );
            }

            $this->notices->add(
                $noticeId,
                $title,
                $content,
                [
                    'screens'           => ['dashboard'],
                    'type'              => $type,
                    'alt_style'         => $altStyle,
                    'capability'        => 'read',
                    'subscription_link' => $subscriptionLink,
                    'edit_link'         => $editLink
                ]
            );
        }
    }

    /**
     * Set the preview notice
     */
    protected function previewNotice()
    {
        $screen = get_current_screen();
        if ($screen->id != CPT::POST_TYPE) {
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

        $subscriptionLink = sprintf(
            '<a href="%1$s">%2$s</a>',
            Export::makeIcsLink($postId),
            __('Add to calendar', 'rrze-notices')
        );

        $editLink = sprintf(
            '<span class="notice-preview">%s</span>',
            __('Preview', 'rrze-notices')
        );

        $blogId = get_current_blog_id();
        $noticeId = sprintf('%d-%d', $blogId, $postId);

        $this->notices->add(
            $noticeId,
            $title,
            $content,
            [
                'screens'           => [CPT::POST_TYPE],
                'type'              => $type,
                'alt_style'         => $altStyle,
                'capability'        => 'read',
                'subscription_link' => $subscriptionLink,
                'edit_link'         => $editLink,
                'preview'           => true,
                'is_dismissible'    => false
            ]
        );
    }
}
