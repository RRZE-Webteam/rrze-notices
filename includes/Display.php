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
     *
     * @return void
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
     *
     * @return void
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

            if (empty($notice['event'])) {
                continue;
            }

            $notice = (object) $notice;
            $event = (object) $notice->event;

            if (!Event::isNoticeActive($notice)) {
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
                !is_super_admin() &&
                !Utils::arrayValInArray($currentUser->roles, $visibility) &&
                !in_array('all', $visibility)
            ) {
                continue;
            }

            if (!$content = $notice->content) {
                continue;
            }

            $title = $notice->title;

            $type = $notice->type;

            $hideTitle = $notice->hide_title;

            $subscriptionLink = sprintf(
                '<a href="%1$s">%2$s</a>',
                $notice->subscription_link,
                __('Add to calendar', 'rrze-notices')
            );

            $editLink = '';
            $editText = __('Edit', 'rrze-notices');

            if (
                is_super_admin()
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
                    'hide_title'        => $hideTitle,
                    'subscription_link' => $subscriptionLink,
                    'edit_link'         => $editLink,
                    'event'             => $event
                ]
            );
        }
    }

    /**
     * Set notices
     *
     * @return void
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
            $notice = get_post_meta($postId, 'notice_data', true);
            if (empty($notice) || empty($notice['event'])) {
                continue;
            }

            $notice = (object) $notice;
            $event = (object) $notice->event;

            if (!Event::isNoticeActive($notice)) {
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
                !is_super_admin() &&
                !Utils::arrayValInArray($currentUser->roles, $visibility) &&
                !in_array('all', $visibility)
            ) {
                continue;
            }

            if (!$content = $notice->content) {
                continue;
            }

            $blogId = get_current_blog_id();
            $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

            $title = $notice->title;

            $type = $notice->type;

            $hideTitle = $notice->hide_title;

            $subscriptionLink = sprintf(
                '<a href="%1$s">%2$s</a>',
                $notice->subscription_link,
                __('Add to calendar', 'rrze-notices')
            );

            $editLink = '';
            $editText = __('Edit', 'rrze-notices');

            if (
                is_super_admin()
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
                    'hide_title'        => $hideTitle,
                    'subscription_link' => $subscriptionLink,
                    'edit_link'         => $editLink,
                    'event'             => $event
                ]
            );
        }
    }

    /**
     * Set the preview notice
     *
     * @return void
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

        $notice = get_post_meta($postId, 'notice_data', true);
        if (empty($notice) || empty($notice['event'])) {
            return;
        }

        $notice = (object) $notice;
        $event = (object) $notice->event;

        if (!$content = $notice->content) {
            return;
        }

        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

        $title = $notice->title;

        $type = $notice->type;

        $hideTitle = $notice->hide_title;

        $subscriptionLink = sprintf(
            '<a href="%1$s">%2$s</a>',
            Export::makeIcsLink($postId),
            __('Add to calendar', 'rrze-notices')
        );

        $editLink = sprintf(
            '<span class="notice-preview">%s</span>',
            __('Preview', 'rrze-notices')
        );

        $this->notices->add(
            $noticeId,
            $title,
            $content,
            [
                'screens' => [CPTNotice::POST_TYPE],
                'type' => $type,
                'hide_title' => $hideTitle,
                'subscription_link' => $subscriptionLink,
                'edit_link' => $editLink,
                'event' => $event,
                'preview' => true,
                'is_dismissible' => false
            ]
        );
    }
}
