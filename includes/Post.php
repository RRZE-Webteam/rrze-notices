<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Class Post
 *
 * A class for managing post-related functionality.
 */
class Post
{
    /**
     * Custom post status.
     */
    public const EXPIRED_POST_STATUS = 'expired';

    /**
     * Setting up hooks
     *
     * @return void
     */
    public static function loaded()
    {
        add_action('init', [__CLASS__, 'registerPostStatus']);
        add_action('admin_footer-post.php', [__CLASS__, 'appendPostStatusPostbox']);
        add_action('admin_footer-edit.php', [__CLASS__, 'appendPostStatusQuickEdit']);
        add_action('transition_post_status', [__CLASS__, 'transitionPostStatus'], 10, 3);
        add_action('delete_post', [__CLASS__, 'deletePost'], 10, 2);
        add_filter('user_has_cap', [__CLASS__, 'restrictPostEditing'], 10, 4);
        add_action('cmb2_save_post_fields', [__CLASS__, 'updatePostmeta']);
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
        if ($post->post_status == self::EXPIRED_POST_STATUS) {
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

        if ($newStatus == 'publish' && $oldStatus != 'publish') {
            $blogId = get_current_blog_id();
            $noticeId = $blogId . '-' . $post->ID;
            $dismiss = new Dismiss($noticeId, true);
            $dismiss->deleteDismissedNotice();
        }
    }

    /**
     * Update local & network post meta (if applicable) for the notice
     *
     * @param int $postId
     * @return void
     */
    public static function updatePostmeta(int $postId)
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $rruleArgs = Event::makeRRuleArgs($postId);
        if ($rruleArgs = json_encode($rruleArgs)) {
            update_post_meta($postId, 'event_rrule_args', $rruleArgs);
        }

        self::updateLocalNotice($postId);

        if (
            is_super_admin() &&
            Utils::isPluginActiveForNetwork()
        ) {
            if (self::isNetworkWide($postId)) {
                self::updateNetworkWideNotice($postId);
                update_post_meta($postId, 'notice_network_wide', 'on');
            } else {
                self::deleteNetworkWideNotice($postId);
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
    public static function deletePost($postId, $post)
    {
        self::deleteNetworkWideNotice($postId);
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
                $post->post_type == CPTNotice::POST_TYPE &&
                get_post_meta($post->ID, 'notice_network_wide', true) == 'on' &&
                !is_super_admin()
            ) {
                $allcaps[$caps[0]] = false;
            }
        }
        return $allcaps;
    }

    /**
     * Update the local notice
     *
     * @param int $postId
     * @return void
     */
    public static function updateLocalNotice($postId)
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $notice = self::makeNoticeData($postId, $post);

        update_post_meta($postId, 'notice_data', $notice);
    }

    /**
     * Update the network-wide notice
     *
     * @param int $postId
     * @return void
     */
    public static function updateNetworkWideNotice(int $postId)
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $siteOptionsName = Options::getSiteOptionName();
        $siteOptions = Options::getSiteOptions();

        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

        $siteOptions->network_wide[$noticeId] = self::makeNoticeData($postId, $post);

        update_site_option($siteOptionsName, $siteOptions);
    }

    /**
     * Build the denormalized notice payload used for display and export.
     *
     * @param int      $postId Notice post ID.
     * @param \WP_Post $post   Notice post object.
     * @return array<string, mixed> Cached notice data.
     */
    private static function makeNoticeData(int $postId, object $post)
    {
        $event = self::getEventData($postId);
        $noticeStart = get_post_meta($postId, 'notice_start', true);

        $title = $post->post_title;
        $content = $post->post_content;
        $type = self::getType($postId);
        $hideTitle = self::hideTitle($postId);
        $visibility = self::getVisibility($postId);
        $networkWide = Post::isNetworkWide($postId);
        $noticeStart = $noticeStart;
        $subscriptionLink = Export::makeIcsLink($postId);
        $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');

        return [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'hide_title' => $hideTitle,
            'visibility' => $visibility,
            'network_wide' => $networkWide,
            'notice_start' => $noticeStart,
            'subscription_link' => $subscriptionLink,
            'admin_link' => $adminLink,
            'event' => $event
        ];
    }

    /**
     * Delete the network-wide site option for the notice
     *
     * @param int $postId
     * @return void
     */
    public static function deleteNetworkWideNotice(int $postId)
    {
        $siteOptionsName = Options::getSiteOptionName();
        $siteOptions = Options::getSiteOptions();

        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);
        if (isset($siteOptions->network_wide[$noticeId])) {
            unset($siteOptions->network_wide[$noticeId]);
            update_site_option($siteOptionsName, $siteOptions);
        }
    }

    /**
     * Get the available notice types
     *
     * @return array
     */
    public static function getNoticeTypes()
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
    public static function getType(int $postId): string
    {
        if (!$type = get_post_meta($postId, 'notice_type', true)) {
            // Legacy meta key
            $type = get_post_meta($postId, 'rrze_notices_type', true);
        }
        return array_key_exists($type, self::getNoticeTypes()) ? $type : 'info';
    }

    /**
     * Check if the notice should hide the title
     *
     * @param int $postId
     * @return bool
     */
    public static function hideTitle(int $postId): bool
    {
        if ($display = (array) get_post_meta($postId, 'notice_display', true)) {
            return in_array('hide_title', $display);
        } elseif ($display = (array) get_post_meta($postId, 'rrze_notices_display', true)) {
            // Legacy meta key
            return in_array('hide_title', $display);
        } else {
            // Legacy meta key
            return get_post_meta($postId, 'rrze_notices_hide_title', true) ? true : false;
        }
    }

    /**
     * Check if the notice is network-wide
     *
     * @param int $postId
     * @return int
     */
    public static function isNetworkWide(int $postId): int
    {
        if ($display = (array) get_post_meta($postId, 'notice_display', true)) {
            return in_array('network_wide', $display);
        } elseif ($display = (array) get_post_meta($postId, 'rrze_notices_display', true)) {
            // Legacy meta key
            return in_array('network_wide', $display);
        } else {
            // Legacy meta key
            return get_post_meta($postId, 'rrze_notices_network_wide', true) ? true : false;
        }
    }

    /**
     * Get the visibility settings for the notice
     *
     * @param int $postId
     * @return array
     */
    public static function getVisibility(int $postId): array
    {
        if ($visibility = get_post_meta($postId, 'notice_visibility', true)) {
            $roles = get_post_meta($postId, 'notice_visibility_roles', true);
            return $visibility == 'roles' && $roles ? $roles : ['all'];
        } else {
            // Legacy meta key
            $roles = (array) get_post_meta($postId, 'rrze_notices_visibility', true);
            return in_array('all', $roles) ? ['all'] : $roles;
        }
    }

    /**
     * Get the event data for the notice
     *
     * @param int $postId
     * @return array
     */
    public static function getEventData($postId)
    {
        return Event::makeData($postId);
    }

    /**
     * Get the human-readable event recurrence
     *
     * @param int $postId
     * @return string
     */
    public static function getHumanReadableRecurrence($postId)
    {
        return Event::getHumanReadableRecurrence($postId);
    }

    /**
     * Update the status of the notice
     *
     * @return void
     */
    public static function updateStatus()
    {
        $args = [
            'fields' => 'ids',
            'post_type' => CPTNotice::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $posts = get_posts($args);

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

            $noticeStarted = Event::hasNoticeStarted($notice);
            $expired = Event::isNoticeExpired($notice);

            if ($expired) {
                wp_update_post([
                    'ID' => $postId,
                    'post_status' => self::EXPIRED_POST_STATUS
                ]);
            }

            if ($expired || !$noticeStarted) {
                $blogId = get_current_blog_id();
                $noticeId = $blogId . '-' . $postId;
                $dismiss = new Dismiss($noticeId, true);
                $dismiss->deleteDismissedNotice();
            }

            if (!$expired) {
                self::updateLocalNotice($postId);
            } else {
                delete_post_meta($postId, 'notice_data');
            }

            if (
                Utils::isPluginActiveForNetwork()
                && self::isNetworkWide($postId)
            ) {
                if (!$expired) {
                    self::updateNetworkWideNotice($postId);
                } else {
                    self::deleteNetworkWideNotice($postId);
                }
            }
        }
    }
}
