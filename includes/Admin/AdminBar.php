<?php

declare(strict_types=1);

namespace RRZE\Notices\Admin;

defined('ABSPATH') || exit;

use RRZE\Notices\Display\NoticeId;
use RRZE\Notices\Display\NoticeQuery;

/**
 * Collects active notices and exposes them through the WordPress admin bar.
 */
class AdminBar
{
    /**
     * Notices available to the current user.
     *
     * @var array<int, object{id:string, title:string, type:string}>
     */
    protected array $notices = [];

    /** @var NoticeQuery Active-notice query service. */
    private NoticeQuery $noticeQuery;

    /**
     * Initialize the active-notice query.
     */
    public function __construct(NoticeQuery $noticeQuery)
    {
        $this->noticeQuery = $noticeQuery;
    }

    /**
     * Register admin-bar request and rendering hooks.
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('admin_init', [$this, 'redirectNoticeRequest']);
        add_action('admin_head', [$this, 'collectNotices']);
    }

    /**
     * Collect active, visible, non-dismissed notices.
     *
     * @return void
     */
    public function collectNotices(): void
    {
        foreach ($this->noticeQuery->findActiveForCurrentUser() as $match) {
            $noticeId = (string) $match['id'];
            if ($this->isDismissed($noticeId)) {
                continue;
            }

            $notice = $match['notice'];
            $this->addNotice($noticeId, (string) $notice->title, (string) $notice->type);
        }

        if ($this->notices !== []) {
            add_action('admin_bar_menu', [$this, 'addItems'], 99);
        }
    }

    /**
     * Append one notice to the admin-bar collection.
     *
     * @param string $noticeId Canonical site-qualified notice ID.
     * @param string $title    Notice title.
     * @param string $type     Notice severity/type slug.
     * @return void
     */
    protected function addNotice(string $noticeId, string $title, string $type): void
    {
        $this->notices[] = (object) [
            'id' => $noticeId,
            'title' => $title,
            'type' => $type,
        ];
    }

    /**
     * Add collected notices to the WordPress admin bar.
     *
     * @param \WP_Admin_Bar $wpAdminBar Admin-bar instance supplied by WordPress.
     * @return void
     */
    public function addItems($wpAdminBar): void
    {
        $title = sprintf(
            '<span class="ab-icon dashicons"></span><span class="ab-label">%s</span> <span class="notices-count">%s</span>',
            esc_html__('Notices', 'rrze-notices'),
            count($this->notices)
        );

        $wpAdminBar->add_menu([
            'id' => 'rrze-notices',
            'parent' => 'top-secondary',
            'title' => $title,
            'href' => false,
        ]);

        foreach ($this->notices as $index => $notice) {
            $href = wp_nonce_url(
                add_query_arg('rrze-notices-id', $notice->id),
                'rrze_notices_redirect_nonce'
            );
            $wpAdminBar->add_menu([
                'id' => 'rrze-notices-item-' . $index,
                'parent' => 'rrze-notices',
                'title' => esc_html($notice->title),
                'href' => $href,
                'meta' => ['class' => 'notices-' . $notice->type],
            ]);
        }
    }

    /**
     * Redirect a nonce-protected admin-bar notice link to the dashboard.
     *
     * @return void
     */
    public function redirectNoticeRequest(): void
    {
        if (empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'rrze_notices_redirect_nonce')) {
            return;
        }

        $noticeId = NoticeId::fromString($_GET['rrze-notices-id'] ?? null);
        if (!$noticeId) {
            return;
        }

        $href = add_query_arg('rrze-notices-id', (string) $noticeId, get_dashboard_url());
        wp_safe_redirect($href);
        exit;
    }

    /**
     * Determine whether the current user dismissed a notice.
     *
     * @param string $noticeId Canonical site-qualified notice identifier.
     * @return bool
     */
    protected function isDismissed(string $noticeId): bool
    {
        return (bool) get_user_meta(
            get_current_user_id(),
            Dismiss::PREFIX . '_' . $noticeId,
            true
        );
    }
}
