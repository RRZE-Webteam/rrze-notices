<?php

declare(strict_types=1);

namespace RRZE\Notices\CPT;

defined('ABSPATH') || exit;

use RRZE\Notices\Helper;
use RRZE\Notices\Options;
use RRZE\Notices\Admin\Dismiss;
use RRZE\Notices\Display\NoticeId;


/**
 * Registers notice-post lifecycle, status, and capability behavior.
 */
class Post
{
    /**
     * Custom post status.
     */
    public const EXPIRED_POST_STATUS = 'expired';

    /**
     * Register notice-post lifecycle hooks.
     *
     * @return void
     */
    public static function registerHooks(): void
    {
        add_action('init', [__CLASS__, 'registerPostStatus']);
        add_action('transition_post_status', [__CLASS__, 'transitionPostStatus'], 10, 3);
        add_action('delete_post', [__CLASS__, 'handleDeletedPost'], 10, 2);
        add_filter('map_meta_cap', [__CLASS__, 'mapNoticeCapabilities'], 10, 4);
        add_action('cmb2_save_post_fields', [__CLASS__, 'handleNoticeSave']);
        add_action('admin_notices', [__CLASS__, 'showValidationNotice']);
    }

    /**
     * Register custom post status.
     *
     * @return void
     */
    public static function registerPostStatus()
    {
        if (in_array(self::EXPIRED_POST_STATUS, get_post_stati())) {
            return;
        }
        register_post_status(self::EXPIRED_POST_STATUS, [
            'label' => _x('Expired', 'custom post status', 'rrze-notices'),
            'public' => false,
            'private' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => false,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
            /* translators: %s: Number of expired posts. */
                'Expired <span class="count">(%s)</span>',
                'Expired <span class="count">(%s)</span>',
                'rrze-notices'
            )
        ]);
    }


    /**
     * Reset user dismissals when a notice is newly published.
     *
     * @param string $newStatus New post status.
     * @param string $oldStatus Previous post status.
     * @param \WP_Post $post Transitioned post.
     * @return void
     */
    public static function transitionPostStatus($newStatus, $oldStatus, $post)
    {
        if ($post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            $noticeId = NoticeId::forPost((int)$post->ID);
            $dismissal = new Dismiss((string)$noticeId, true);
            $dismissal->clearForAllUsers();
        }
    }

    /**
     * Rebuild cached notice data after CMB2 saves a notice.
     *
     * @param int $postId
     * @return void
     */
    public static function handleNoticeSave(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }
        // Clear event dates when the display option is unchecked.
        $display = (array) get_post_meta($postId, 'notice_display', true);

        if (in_array('show_event_date', $display, true)) {
            $eventStart = (int) get_post_meta($postId, 'notice_event_start',
                true);
            if (!$eventStart) {
                $display = array_diff($display, ['show_event_date']);
                update_post_meta($postId, 'notice_display', $display);
                delete_post_meta($postId, 'notice_event_start');
                delete_post_meta($postId, 'notice_event_end');

                set_transient(
                    'rrze_notices_validation_' . $postId,
                    __('Event date display was disabled because no start date was set.', 'rrze-notices'),
                    30
                );
            }
        } else {
            delete_post_meta($postId, 'notice_event_start');
            delete_post_meta($postId, 'notice_event_end');
        }
        if (
            is_super_admin() &&
            Helper::isPluginActiveForNetwork()
        ) {
            $networkOptions = Options::getNetworkOptions();
            $noticeKey = (string)NoticeId::forPost($postId);

            if (self::isNetworkWide($postId)) {
                $networkOptions->network_wide[$noticeKey] = [
                    'blog_id' => get_current_blog_id(),
                    'post_id' => $postId,
                ];
                update_post_meta($postId, 'notice_network_wide', 'on');
            } else {
                unset($networkOptions->network_wide[$noticeKey]);
                delete_post_meta($postId, 'notice_network_wide');
            }

            update_site_option(Options::getNetworkOptionName(), $networkOptions);
        }
    }

    /**
     * Delete the network-wide site option when the notice is deleted
     *
     * @param int $postId
     * @param object $post
     * @return void
     */
    public static function handleDeletedPost(int $postId, object $post): void
    {
        if ($post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $networkOptions = Options::getNetworkOptions();
        $noticeKey = (string)NoticeId::forPost($postId);

        if (!isset($networkOptions->network_wide[$noticeKey])) {
            return;
        }

        unset($networkOptions->network_wide[$noticeKey]);
        update_site_option(Options::getNetworkOptionName(), $networkOptions);
    }

    /**
     * Map notice capabilities to manage_options or manage_network_options.
     *
     * WordPress calls this filter whenever it checks a capability for a
     * notice post. Instead of relying on page capabilities (which editors
     * also have), we map to manage_options or manage_network_options
     * depending on the network setting.
     *
     * @param string[] $caps Required primitive capabilities.
     * @param string $cap Capability being checked.
     * @param int $userId User ID.
     * @param array $args Additional arguments, $args[0] is the post ID for post-specific caps.
     * @return string[]
     */
    public static function mapNoticeCapabilities(array $caps, string $cap, int $userId, array $args): array
    {
        $noticeCaps = [
            'edit_notice',
            'read_notice',
            'delete_notice',
            'edit_notices',
            'edit_others_notices',
            'publish_notices',
            'read_private_notices',
            'delete_notices',
            'delete_private_notices',
            'delete_published_notices',
            'delete_others_notices',
            'edit_private_notices',
            'edit_published_notices',
        ];

        if (!in_array($cap, $noticeCaps, true)) {
            return $caps;
        }

        //Network-wide notices are superadmin-only
        if (!empty($args[0])) {
            $post = get_post($args[0]);
            if (
                $post
                && $post->post_type === CPTNotice::POST_TYPE
                && get_post_meta($post->ID, 'notice_network_wide', true) === 'on'
                && !is_super_admin($userId)
            ) {
                return ['do_not_allow'];
            }
        }
        //Map to the configured access capability
        $networkOptions = Options::getNetworkOptions();
        return [$networkOptions->access];
    }


    /**
     * Get the available notice types
     *
     * @return array
     */
    private static function getNoticeTypes(): array
    {
        return [
            'info' => __('Info', 'rrze-notices'),
            'warning' => __('Warning', 'rrze-notices'),
            'error' => __('Critical', 'rrze-notices')
        ];
    }

    /**
     * Get the type of the notice
     *
     * @param int $postId
     * @return string
     */
    public static function getNoticeType(int $postId): string
    {
        $type = get_post_meta($postId, 'notice_type', true);
        return array_key_exists($type, self::getNoticeTypes()) ? $type : 'info';
    }

    /**
     * Check if the notice should hide the title
     *
     * @param int $postId
     * @return bool
     */
    public static function shouldHideTitle(int $postId): bool
    {
        $display = get_post_meta($postId, 'notice_display', true);
        if (!empty($display)) {
            return in_array('hide_title', (array)$display, true);
        }

        return false;
    }

    /**
     * Check if the notice is network-wide
     *
     * @param int $postId
     * @return bool
     */
    public static function isNetworkWide(int $postId): bool
    {
        $display = get_post_meta($postId, 'notice_display', true);
        if (!empty($display)) {
            return in_array('network_wide', (array)$display, true);
        }

        return false;
    }

    /**
     * Get the visibility settings for the notice
     *
     * @param int $postId
     * @return array
     */
    public static function getVisibilityRoles(int $postId): array
    {
        $visibility = get_post_meta($postId, 'notice_visibility', true);
        $roles = get_post_meta($postId, 'notice_visibility_roles', true);
        return $visibility === 'roles' && $roles ? $roles : ['all'];

    }

    /**
     * Build notice data directly from post and post meta.
     *
     * @param int $postId Notice post ID.
     * @return array<string, mixed>|null Notice data array, or null if post is invalid.
     */
    public static function buildNoticeData(int $postId): ?array
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return null;
        }
        return [
            'title' => $post->post_title,
            'content' => $post->post_content,
            'type' => self::getNoticeType($postId),
            'hide_title' => self::shouldHideTitle($postId),
            'visibility' => self::getVisibilityRoles($postId),
            'network_wide' => self::isNetworkWide($postId),
            'admin_link' => admin_url('post.php?post=' . $postId . '&action=edit'),
            'event_start'=> (int) get_post_meta($postId, 'notice_event_start', true),
            'event_end'=> (int) get_post_meta($postId, 'notice_event_end', true),
        ];
    }


    /**
     * Refresh lifecycle state for all published notices (Cron-Job)
     *
     * @return void
     */
    public static function refreshNoticeLifecycle(): void
    {
        $postIDs = get_posts([
            'fields' => 'ids',
            'post_type' => CPTNotice::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);

        if (empty($postIDs)) {
            return;
        }

        $now = time();

        foreach ($postIDs as $postID) {
            $visibleUntil = (int)get_post_meta($postID, 'notice_visible_until_gmt', true);

            if (!$visibleUntil) {
                continue;
            }

            if ($now >= $visibleUntil) {
                wp_update_post([
                    'ID' => $postID,
                    'post_status' => self::EXPIRED_POST_STATUS
                ]);

                $networkOptions = Options::getNetworkOptions();
                $noticeKey = (string)NoticeId::forPost((int)$postID);
                if (isset($networkOptions->network_wide[$noticeKey])) {
                    unset($networkOptions->network_wide[$noticeKey]);
                    update_site_option(Options::getNetworkOptionName(), $networkOptions);
                }
            }
        }
    }

    /**
     * Display a one-time validation notice after saving.
     *
     * @return void
     */
    public static function showValidationNotice(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== CPTNotice::POST_TYPE) {
            return;
        }

        global $post;
        if (!$post instanceof \WP_Post) {
            return;
        }

        $transientKey = 'rrze_notices_validation_' . $post->ID;
        $message = get_transient($transientKey);
        if (!$message) {
            return;
        }

        delete_transient($transientKey);

        printf(
            '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
            esc_html($message)
        );
    }

}
