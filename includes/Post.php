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
     * Setting up action hooks
     */
    public static function loaded()
    {
        add_action('init', [__CLASS__, 'registerPostStatus']);
        add_action('admin_footer-post.php', [__CLASS__, 'appendPostStatusPostbox']);
        add_action('admin_footer-edit.php', [__CLASS__, 'appendPostStatusQuickEdit']);
        add_action('transition_post_status', [__CLASS__, 'transitionPostStatus'], 10, 3);
        add_action('save_post', [__CLASS__, 'savePost']);
        add_action('delete_post', [__CLASS__, 'deletePost'], 10, 2);
        add_filter('user_has_cap', [__CLASS__, 'restrictPostEditing'], 10, 4);
    }

    /**
     * Register custom post status.
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
     */
    public static function savePost(int $postId)
    {
        if (get_post_type($postId) != CPTNotice::POST_TYPE) {
            return;
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
     */
    public static function updateLocalNotice($postId)
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $data = self::getEventData($postId);
        $readableDatetime = $data['event']['readable_datetime'];

        $title = $post->post_title;
        $content = $post->post_content;
        $type = self::getType($postId);
        $altStyle = self::altStyle($postId);
        $hideTitle = self::hideTitle($postId);
        $visibility = self::getVisibility($postId);
        $noticeStart = self::getNoticeStart($postId, true);
        $subscriptionLink = Export::makeIcsLink($postId);
        $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');

        $notice = [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'hide_title' => $hideTitle,
            'alt_style' => $altStyle,
            'visibility' => $visibility,
            'notice_start' => $noticeStart,
            'subscription_link' => $subscriptionLink,
            'admin_link' => $adminLink,
            'readable_datetime' => $readableDatetime,
        ];

        update_post_meta($postId, 'notice_data', $notice);
    }

    /**
     * Update the network-wide notice
     *
     * @param int $postId
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

        $data = self::getEventData($postId);
        $eventAllDay = $data['event']['all_day'];
        $eventMultiDay = $data['event']['multiday'];
        $eventStartUTC = $data['event']['start_gmt'] ?? '';
        $eventEndUTC = $data['event']['end_gmt'] ?? '';

        $title = $post->post_title;
        $content = $post->post_content;
        $type = self::getType($postId);
        $altStyle = self::altStyle($postId);
        $hideTitle = self::hideTitle($postId);
        $visibility = self::getVisibility($postId);
        $noticeStart = self::getNoticeStart($postId, true);
        $subscriptionLink = Export::makeIcsLink($postId);
        $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');

        $siteOptions->network_wide[$noticeId] = [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'hide_title' => $hideTitle,
            'alt_style' => $altStyle,
            'visibility' => $visibility,
            'notice_start' => $noticeStart,
            'subscription_link' => $subscriptionLink,
            'admin_link' => $adminLink,
            'event_all_day' => $eventAllDay,
            'event_multiday' => $eventMultiDay,
            'event_start_gmt' => $eventStartUTC,
            'event_end_gmt' => $eventEndUTC
        ];

        update_site_option($siteOptionsName, $siteOptions);
    }

    /**
     * Delete the network-wide site option for the notice
     *
     * @param int $postId
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
        return array_key_exists($type, self::getNoticeTypes()) ? $type : '&mdash;';
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
     * Check if the notice has an alternative style
     *
     * @param int $postId
     * @return bool
     */
    public static function altStyle(int $postId): bool
    {
        if ($display = (array) get_post_meta($postId, 'notice_display', true)) {
            return in_array('alt_style', $display);
        } elseif ($display = (array) get_post_meta($postId, 'rrze_notices_display', true)) {
            // Legacy meta key
            return in_array('alt_style', $display);
        } else {
            // Legacy meta key
            return get_post_meta($postId, 'rrze_notices_alt_style', true) ? true : false;
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
     * Get the timestamp when the notice should start displaying
     *
     * @param int $postId
     * @param bool $gmt
     * @return int
     */
    public static function getNoticeStart(int $postId, bool $gmt = false): int
    {
        $event = self::getEventData($postId);
        if ($gmt) {
            $noticeStart = (int) get_post_meta($postId, 'notice_start_gmt', true);
            $eventStart = $event['event']['start_gmt'] ?? current_time('mysql', true);
        } else {
            $noticeStart = (int) get_post_meta($postId, 'notice_start', true);
            $eventStart = $event['event']['start'] ?? current_time('mysql');
        }
        return strtotime($eventStart) - $noticeStart;
    }

    /**
     * Check if the notice should start displaying
     *
     * @param int $postId
     * @param bool $gmt
     * @return bool
     */
    public static function isNoticeStarted(int $postId, bool $gmt = false): bool
    {
        $noticeStart = self::getNoticeStart($postId, $gmt);
        $currentTimestamp = $gmt ? time() : current_time('timestamp');
        return $noticeStart < $currentTimestamp;
    }

    /**
     * Get the event data for the notice
     *
     * @param int $postId
     * @return array
     */
    public static function getEventData($postId)
    {
        return Event::getData($postId);
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
            $expired = Event::isExpired($postId);
            $noticeStarted = Post::isNoticeStarted($postId);
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
