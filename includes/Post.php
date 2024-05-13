<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;

class Post
{
    public static function loaded()
    {
        add_action('updated_post_meta', [__CLASS__, 'updatePostMeta'], 10, 4);
        add_action('delete_post', [__CLASS__, 'deletePost'], 10, 2);
        add_filter('user_has_cap', [__CLASS__, 'restrictPostEditing'], 10, 4);
    }

    public static function updatePostMeta($metaId, $postId, $metaKey, $metaValue)
    {
        if (get_post_type($postId) != CPT::POST_TYPE) {
            return;
        }

        if (
            $metaKey == 'event_repeat'
            && $metaValue == 'on'
        ) {
            $rruleArgs = Event::makeRRuleArgs($postId);
        }

        if (
            $metaKey == 'event_repeat'
            && $metaValue == 'on'
            && $rruleArgs = json_encode($rruleArgs)
        ) {
            update_post_meta($postId, 'event_rrule_args', $rruleArgs);
        }

        if (
            Utils::isPluginActiveForNetwork()
            && current_user_can('manage_network_options')
        ) {
            if (self::isNetworkWide($postId)) {
                self::updateNetworkWideSiteOption($postId);
                update_post_meta($postId, 'notice_network_wide', 'on');
            } else {
                self::deleteNetworkWideSiteOption($postId);
                delete_post_meta($postId, 'notice_network_wide');
            }
        }
    }

    public static function deletePost($postId, $post)
    {
        self::deleteNetworkWideSiteOption($postId);
    }

    public static function restrictPostEditing($allcaps, $caps, $args, $user)
    {
        if (isset($caps[0], $args[2])) {
            $post = get_post($args[2]);
            if (
                $post &&
                $post->post_type == CPT::POST_TYPE &&
                get_post_meta($post->ID, 'notice_network_wide', true) == 'on' &&
                !current_user_can('manage_network_options')
            ) {
                $allcaps[$caps[0]] = false;
            }
        }
        return $allcaps;
    }

    public static function updateNetworkWideSiteOption(int $postId)
    {
        $siteOptionsName = Options::getSiteOptionName();
        $siteOptions = Options::getSiteOptions();

        $post = get_post($postId);
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

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
            'admin_link' => $adminLink
        ];

        update_site_option($siteOptionsName, $siteOptions);
    }

    public static function deleteNetworkWideSiteOption(int $postId)
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

    public static function getNoticeTypes()
    {
        return [
            'info' => __('Info', 'rrze-notices'),
            'warning' => __('Warning', 'rrze-notices')
        ];
    }

    public static function getType(int $postId): string
    {
        if (!$type = get_post_meta($postId, 'notice_type', true)) {
            // Legacy meta key
            $type = get_post_meta($postId, 'rrze_notices_type', true);
        }
        return array_key_exists($type, self::getNoticeTypes()) ? $type : '&mdash;';
    }

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

    public static function getNoticeStart(int $postId, bool $gmt = false): mixed
    {
        $noticeStart = get_post_meta($postId, 'notice_start', true);
        $event = self::getEventData($postId);
        if ($gmt) {
            $eventStart = $event['event']['start_gmt'] ?? null;
        } else {
            $eventStart = $event['event']['start'] ?? null;
        }
        if (!$noticeStart || !$eventStart) {
            return null;
        }
        $noticeStart = absint($noticeStart);
        $eventStart = strtotime($eventStart);
        return $eventStart - $noticeStart;
    }

    public static function isEventRepeat(int $postId): int
    {
        return (bool) get_post_meta($postId, 'event_repeat', true);
    }

    public static function getEventData($postId)
    {
        return Event::getData($postId);
    }

    public static function getEventReccurence($postId)
    {
        return Event::getHumanReadableRecurrence($postId);
    }

    public static function updateStatus()
    {
        $args = [
            'post_type' => CPT::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $posts = get_posts($args);

        foreach ($posts as $post) {
            $postId = $post->ID;
            $expired = Event::isExpired($postId);
            if ($expired) {
                wp_update_post([
                    'ID' => $postId,
                    'post_status' => CPT::EXPIRED_POST_STATUS
                ]);
            }

            if (
                Utils::isPluginActiveForNetwork()
                && self::isNetworkWide($postId)
            ) {
                if (!$expired) {
                    self::updateNetworkWideSiteOption($postId);
                    update_post_meta($postId, 'notice_network_wide', 'on');
                } else {
                    self::deleteNetworkWideSiteOption($postId);
                    delete_post_meta($postId, 'notice_network_wide');
                }
            }
        }
    }
}
