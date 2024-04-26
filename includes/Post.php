<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;

class Post
{
    protected $customPostType;

    protected $siteOptionsName;

    protected $siteOptions;

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();

        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
    }

    public function loaded()
    {
        add_action('updated_post_meta', [$this, 'updatePostMeta'], 10, 4);
        add_action('delete_post', [$this, 'deletePost'], 10, 2);
    }

    public function updatePostMeta($metaId, $postId, $metaKey, $metaValue)
    {
        if (get_post_type($postId) != $this->customPostType) {
            return;
        }
        $repeat = get_post_meta($postId, 'event_repeat', true);
        if ($repeat == 'on') {
            $rruleArgs = Event::makeRRuleArgs($postId);
            update_post_meta($postId, 'event_rrule_args', json_encode($rruleArgs));
        }
    }

    public function deletePost($postId, $post)
    {
        $this->deleteNetworkWideSiteOption($postId);
    }

    protected function updateNetworkWideSiteOption(int $postId, \WP_Post $post)
    {
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

        $title = $post->post_title;
        $content = $post->post_content;
        $date = $post->post_date;
        $dateGmt = $post->post_date_gmt;
        $type = Utils::getType($postId);
        $expiration = Utils::getExpiration($postId);
        $expirationGmt = Utils::getExpirationGmt($postId);
        $altStyle = Utils::altStyle($postId);
        $hideTitle = Utils::hideTitle($postId);
        $visibility = Utils::getVisibility($postId);
        $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');

        $this->siteOptions->network_wide[$noticeId] = [
            'title' => $title,
            'content' => $content,
            'date' => $date,
            'date_gmt' => $dateGmt,
            'type' => $type,
            'expiration' => $expiration,
            'expiration_gmt' => $expirationGmt,
            'hide_title' => $hideTitle,
            'alt_style' => $altStyle,
            'visibility' => $visibility,
            'admin_link' => $adminLink
        ];

        update_site_option($this->siteOptionsName, $this->siteOptions);
    }

    protected function deleteNetworkWideSiteOption(int $postId)
    {
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);
        if (isset($this->siteOptions->network_wide[$noticeId])) {
            unset($this->siteOptions->network_wide[$noticeId]);
            update_site_option($this->siteOptionsName, $this->siteOptions);
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
        if ($display = (array) get_post_meta($postId, 'rrze_notices_display', true)) {
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

    public static function getNoticeStart(int $postId): array
    {
        $startAry = [];
        if ($start = (int) get_post_meta($postId, 'notice_start', true)) {
            $startAry = [
                'date' => date_i18n(get_option('date_format'), $start),
                'time' => date_i18n(get_option('time_format'), $start) . ' &ndash; ' . date_i18n(get_option('time_format'), $start),
            ];
        }
        return $startAry;
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
        return Event::getReccurence($postId);
    }
}
