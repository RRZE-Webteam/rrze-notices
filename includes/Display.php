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
     * Constructor
     */
    public function __construct()
    {
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = new Notices;
    }

    /**
     * Setting up action hooks
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

            switch_to_blog($blogId);
            $currentUser = wp_get_current_user();
            restore_current_blog();

            $notice = (object) $notice;

            $title = $notice->title ?? '';
            $content = $notice->content ?? '';
            $type = $notice->type ?? 'info';
            $altStyle = $notice->alt_style ?? false;
            $hideTitle = $notice->hide_title ?? false;
            $roleVisibility = $notice->visibility ?? ['all'];
            $subscriptionLink = $notice->subscription_link ?? '';
            $adminLink = $notice->admin_link ?? '';

            if (!$content) {
                continue;
            }

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
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
            
            if (
                !current_user_can('manage_network_options') &&
                !Utils::arrayValInArray($currentUser->roles, $visibility) &&
                !in_array('all', $visibility)
            ) {
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
                    'edit_link'         => $editLink,
                    'readable_datetime' => true
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
            'fields' => 'ids',
            'post_type' => CPTNotice::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        if (!$posts) {
            return;
        }

        foreach ($posts as $postId) {
            if ($notice = get_post_meta($postId, 'notice_data', true)) {
                $notice = (object) $notice;
            } else {
                continue;
            }

            $currentUser = wp_get_current_user();

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
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
            if (
                !current_user_can('manage_network_options') &&
                !Utils::arrayValInArray($currentUser->roles, $visibility) &&
                !in_array('all', $visibility)
            ) {
                continue;
            }

            if (!$content = $notice->content) {
                continue;
            }

            $title = $notice->hide_title;

            $type = array_key_exists($notice->type, Post::getNoticeTypes()) ? $notice->type : 'info';

            $altStyle = $notice->alt_style;

            $subscriptionLink = sprintf(
                '<a href="%1$s">%2$s</a>',
                $notice->subscription_link,
                __('Add to calendar', 'rrze-notices')
            );

            $blogId = get_current_blog_id();
            $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

            $editLink = '';
            $editText = __('Edit', 'rrze-notices');

            if (
                current_user_can('manage_network_options')
                && Utils::isPluginActiveForNetwork()
                && isset($this->siteOptions->network_wide[$noticeId])
            ) {
                $editText = isset($this->siteOptions->network_wide[$noticeId]) ? __('Edit (Network-Wide)', 'rrze-notices') : '';
            }

            if (current_user_can('manage_options')) {
                $editLink = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    $notice->admin_link,
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
        if ($screen->id != CPTNotice::POST_TYPE) {
            return;
        }

        global $pagenow;
        if ($pagenow == 'post-new.php') {
            return;
        }

        global $post;
        $postId = $post->ID;

        if (!$content = $post->post_content) {
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
                'screens'           => [CPTNotice::POST_TYPE],
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
