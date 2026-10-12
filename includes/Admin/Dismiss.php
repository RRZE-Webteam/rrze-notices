<?php

declare(strict_types=1);

namespace RRZE\Notices\Admin;

defined('ABSPATH') || exit;

use RRZE\Notices\Display\NoticeId;

/**
 * Persists per-user notice dismissals and handles the AJAX dismissal request.
 */
class Dismiss
{
    /**
     * Prefix used for per-user dismissal metadata.
     *
     * @var string
     */
    public const PREFIX = 'rrze_notices_dismissed';

    /** @var bool Whether the shared AJAX callback has been registered. */
    private static bool $hooksRegistered = false;

    /**
     * The notice-ID.
     *
     * @var string
     */
    private $id;

    /**
     * Should the script be printed.
     *
     * @var boolean
     */
    private $isDismissible;

    /**
     * Initialize dismissal state.
     * 
     * @param string $id            Site-qualified notice identifier.
     * @param bool   $isDismissible Whether the notice can be dismissed.
     */
    public function __construct(string $id, bool $isDismissible = true)
    {
        $this->id = sanitize_key($id);
        $this->isDismissible = $isDismissible;

    }

    /**
     * Register the shared AJAX dismissal callback once per request.
     *
     * @return void
     */
    public static function registerHooks(): void
    {
        if (self::$hooksRegistered) {
            return;
        }

        add_action('wp_ajax_rrze_notices_dismiss', [self::class, 'handleAjaxDismissal']);
        self::$hooksRegistered = true;
    }


    /**
     * Determine whether the current user dismissed this notice.
     *
     * @return bool
     */
    public function isDismissed()
    {
        if (!$this->isDismissible) return false;

        return (bool) get_user_meta(get_current_user_id(), self::PREFIX . "_{$this->id}", true);
    }

    /**
     * Validate and process the AJAX dismissal request.
     *
     * @return void
     */
    public static function handleAjaxDismissal(): void
    {
        $noticeId = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
        if (!$noticeId || !NoticeId::fromString($noticeId)) {
            wp_send_json_error(null, 400);
        }

        check_ajax_referer('rrze_notices_dismiss', 'nonce', true);
        update_user_meta(get_current_user_id(), self::PREFIX . '_' . $noticeId,
            true);
        wp_send_json_success();
    }

    /**
     * Delete this dismissal key for all users.
     *
     * Used when a notice is republished or its lifecycle restarts.
     *
     * @return void
     */
    public function clearForAllUsers(): void
    {
        global $wpdb;
        $metaKey = self::PREFIX . "_{$this->id}";
        $userIds = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s",
            $metaKey
        ));

        if (empty($userIds)) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->usermeta WHERE meta_key = %s",
            $metaKey
        ));

        foreach ($userIds as $userId) {
            wp_cache_delete($userId, 'user_meta');
        }
    }
}
