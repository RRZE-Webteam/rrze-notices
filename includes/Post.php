<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

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
        add_action('admin_footer-post.php', [__CLASS__, 'appendPostStatusPostbox']);
        add_action('admin_footer-edit.php', [__CLASS__, 'appendPostStatusQuickEdit']);
        add_action('transition_post_status', [__CLASS__, 'transitionPostStatus'], 10, 3);
        add_action('delete_post', [__CLASS__, 'handleDeletedPost'], 10, 2);
        add_filter('user_has_cap', [__CLASS__, 'restrictPostEditing'], 10, 4);
        add_action('cmb2_save_post_fields', [__CLASS__, 'handleNoticeSave']);
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
     * Append custom post status to the postbox.
     *
     * @return void
     */
    public static function appendPostStatusPostbox()
    {
        $post = get_post();
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $selected = '';
        $postStatusDisplay = '';
        $title = _x('Expired', 'custom post status', 'rrze-notices');
        if ($post->post_status === self::EXPIRED_POST_STATUS) {
            $postStatusDisplay = '$(".misc-pub-post-status span#post-status-display").text("' . $title . '");';
            $selected = ' selected=\"selected\"';
        }
        $script = '
        <script>
        jQuery(document).ready(function($){
        $("select#post_status").append("<option value=\"expired\"' . $selected . '>' . $title . '</option>");
        ' . $postStatusDisplay . '
        });
        </script>
        ';
        echo str_replace(PHP_EOL, '', trim(preg_replace('/\s+/', ' ', $script)));
    }

    /**
     * Append custom post status to the quick edit.
     *
     * @return void
     */
    public static function appendPostStatusQuickEdit()
    {
        $post = get_post();
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $title = _x('Expired', 'custom post status', 'rrze-notices');
        $script = "<script>
        jQuery(document).ready(function($) {
        $('select[name=\"_status\"]').append('<option value=\"expired\">" . $title . "</option>');
        });
        </script>";
        echo str_replace(PHP_EOL, '', trim(preg_replace('/\s+/', ' ', $script)));
    }

    /**
     * Reset user dismissals when a notice is newly published.
     *
     * @param string   $newStatus New post status.
     * @param string   $oldStatus Previous post status.
     * @param \WP_Post $post      Transitioned post.
     * @return void
     */
    public static function transitionPostStatus($newStatus, $oldStatus, $post)
    {
        if ($post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            $noticeId = NoticeId::forPost((int) $post->ID);
            $dismissal = new Dismiss((string) $noticeId, true);
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

        NoticeCache::rebuildLocal($postId);

        if (
            is_super_admin() &&
            Utils::isPluginActiveForNetwork()
        ) {
            if (self::isNetworkWide($postId)) {
                NoticeCache::syncNetwork($postId);
                update_post_meta($postId, 'notice_network_wide', 'on');
            } else {
                NoticeCache::removeNetwork($postId);
                delete_post_meta($postId, 'notice_network_wide');
            }
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
        if ($post->post_type === CPTNotice::POST_TYPE) {
            NoticeCache::removeNetwork($postId);
        }
    }

    /**
     * Restrict editing of network-wide notices
     *
     * @param array $allcaps
     * @param array $caps
     * @param array $args
     * @param object $user
     * @return array
     */
    public static function restrictPostEditing($allcaps, $caps, $args, $user)
    {
        if (isset($caps[0], $args[2])) {
            $post = get_post($args[2]);
            if (
                $post &&
                $post->post_type === CPTNotice::POST_TYPE &&
                get_post_meta($post->ID, 'notice_network_wide', true) === 'on' &&
                !is_super_admin()
            ) {
                $allcaps[$caps[0]] = false;
            }
        }
        return $allcaps;
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
            'warning' => __('Warning', 'rrze-notices')
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
            return in_array('hide_title', (array) $display, true);
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
            return in_array('network_wide', (array) $display, true);
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
     * Refresh lifecycle state for all published notices.
     *
     * @return void
     */
    public static function refreshNoticeLifecycle(): void
    {
        // TODO: Reimplementierung mit Schedule-Logik (Issue 3).

    }
}
