<?php

declare(strict_types=1);

namespace RRZE\Notices\Display;

defined('ABSPATH') || exit;

use RRZE\Notices\Helper;
use RRZE\Notices\Options;
use RRZE\Notices\CPT\CPTNotice;
use RRZE\Notices\CPT\Post;

/**
 * Registers active notices for display in the WordPress dashboard.
 */
class Display
{
    private Notices $notices;

    private NoticeQuery $noticeQuery;

    /**
     * Initialize the notice collection and query service.
     */
    public function __construct()
    {
        $this->notices = new Notices();
        $this->noticeQuery = new NoticeQuery(Options::getNetworkOptions());
    }

    /**
     * Register dashboard-notice and preview hooks.
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('admin_init', [$this, 'registerDashboardNotices']);
        add_action('admin_head', [$this, 'registerPreviewNotice'], 99);
    }

    /**
     * Add active notices that target the current user.
     *
     * @return void
     */
    public function registerDashboardNotices(): void
    {
        foreach ($this->noticeQuery->findActiveForCurrentUser() as $match) {
            $this->addDashboardNotice($match['id'], $match['notice']);
        }

        $this->notices->registerHooks();
    }

    /**
     * Add one cached notice to the dashboard collection.
     *
     * @param NoticeId $noticeId Canonical network notice identifier.
     * @param object   $notice   Cached notice data.
     * @return void
     */
    private function addDashboardNotice(NoticeId $noticeId, object $notice): void
    {
        if (empty($notice->content)) {
            return;
        }

        $editLink = '';
        if (current_user_can('manage_options')) {
            $editText = is_super_admin()
                && Helper::isPluginActiveForNetwork()
                && !empty($notice->network_wide)
                    ? __('Edit (Network-Wide)', 'rrze-notices')
                    : __('Edit', 'rrze-notices');
            $editLink = sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url($notice->admin_link),
                esc_html($editText)
            );
        }

        $this->notices->add(
            (string) $noticeId,
            (string) $notice->title,
            (string) $notice->content,
            [
                'screens' => ['dashboard'],
                'type' => (string) $notice->type,
                'hide_title' => (bool) $notice->hide_title,
                'edit_link' => $editLink,
            ]
        );
    }

    /**
     * Register the notice preview shown on the notice editing screen.
     *
     * @return void
     */
    public function registerPreviewNotice(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== CPTNotice::POST_TYPE) {
            return;
        }

        global $pagenow;
        if ($pagenow === 'post-new.php') {
            return;
        }

        global $post;
        if (!$post instanceof \WP_Post) {
            return;
        }

        $noticeData = Post::buildNoticeData((int) $post -> ID);
        if ($noticeData === null || empty($noticeData['content'])) {
            return;
        }

        $notice = (object) $noticeData;

        $editLink = sprintf(
            '<span class="notice-preview">%s</span>',
            esc_html__('Preview', 'rrze-notices')
        );

        $this->notices->add(
            (string) NoticeId::forPost((int) $post->ID),
            (string) $notice->title,
            (string) $notice->content,
            [
                'screens' => [CPTNotice::POST_TYPE],
                'type' => (string) $notice->type,
                'hide_title' => (bool) $notice->hide_title,
                'edit_link' => $editLink,
                'preview' => true,
                'is_dismissible' => false,
            ]
        );

        $this->notices->registerHooks();
    }
}
