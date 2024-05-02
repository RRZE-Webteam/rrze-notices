<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;

class Post
{
    protected $siteOptionsName;

    protected $siteOptions;

    public function __construct()
    {
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
    }

    public function loaded()
    {
        add_action('updated_post_meta', [$this, 'updatePostMeta'], 10, 4);
        add_action('delete_post', [$this, 'deletePost'], 10, 2);
        add_action('pre_get_posts', [$this, 'excludeNetworkWidePosts']);
        add_filter('user_has_cap', [$this, 'restrictPostEditing'], 10, 4);
    }

    public function updatePostMeta($metaId, $postId, $metaKey, $metaValue)
    {
        if (get_post_type($postId) != CPT::POST_TYPE) {
            return;
        }
        $repeat = get_post_meta($postId, 'event_repeat', true);
        $rruleArgs = Event::makeRRuleArgs($postId);
        if (
            $repeat == 'on' &&
            $rruleArgs = json_encode($rruleArgs)
        ) {
            update_post_meta($postId, 'event_rrule_args', $rruleArgs);
        }

        if (
            Utils::isPluginActiveForNetwork() &&
            current_user_can('manage_network_options')
        ) {
            if (self::isNetworkWide($postId)) {
                $this->updateNetworkWideSiteOption($postId);
                update_post_meta($postId, 'notice_network_wide', 'on');
            } else {
                $this->deleteNetworkWideSiteOption($postId);
                delete_post_meta($postId, 'notice_network_wide');
            }
        }
    }

    public function deletePost($postId, $post)
    {
        $this->deleteNetworkWideSiteOption($postId);
    }

    public function excludeNetworkWidePosts($query)
    {
        if (
            !current_user_can('manage_network_options') &&
            $query->is_main_query() &&
            $query->get('post_type') == CPT::POST_TYPE
        ) {
            $meta_query = [
                'relation' => 'OR',
                [
                    'key' => 'notice_network_wide',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => 'notice_network_wide',
                    'value' => 'on',
                    'compare' => '!=',
                ],
            ];
            $query->set('meta_query', $meta_query);
        }
    }

    public function restrictPostEditing($allcaps, $caps, $args, $user)
    {
        if (isset($caps[0], $args[2]) && $caps[0] == 'edit_post') {
            $post = get_post($args[2]);
            if (
                $post->post_type == CPT::POST_TYPE &&
                get_post_meta($post->ID, 'notice_network_wide', true) == 'on' &&
                !current_user_can('manage_network_options')
            ) {
                $allcaps[$caps[0]] = false;
            }
        }
        return $allcaps;
    }

    protected function updateNetworkWideSiteOption(int $postId)
    {
        $post = get_post($postId);
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

        $title = $post->post_title;
        $content = $post->post_content;
        $date = $post->post_date;
        $dateGmt = $post->post_date_gmt;
        $type = self::getType($postId);
        $altStyle = self::altStyle($postId);
        $hideTitle = self::hideTitle($postId);
        $visibility = self::getVisibility($postId);
        $noticeStart = self::getNoticeStart($postId);
        $adminLink = admin_url('post.php?post=' . esc_attr($postId) . '&action=edit');

        $this->siteOptions->network_wide[$noticeId] = [
            'title' => $title,
            'content' => $content,
            'date' => $date,
            'date_gmt' => $dateGmt,
            'type' => $type,
            'hide_title' => $hideTitle,
            'alt_style' => $altStyle,
            'visibility' => $visibility,
            'notice_start' => $noticeStart,
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

    public static function getNoticeStart(int $postId): string
    {
        $start = '';
        if ($start = (int) get_post_meta($postId, 'notice_start', true)) {
            $start = date_i18n(get_option('date_format'), $start);
        }
        return $start;
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

    public static function isExpired($postId)
    {
        //
    }

    public static function updateStatus()
    {
        $args = array(
            'post_type' => CPT::POST_TYPE,
            'posts_per_page' => -1,
        );
        $posts = get_posts($args);
        
        foreach ($posts as $post) {
            $postId = $post->ID;
            $repeat = get_post_meta($postId, 'event_repeat', true);
            $rruleArgs = Event::makeRRuleArgs($postId);
            if (
                $repeat == 'on' &&
                $rruleArgs = json_encode($rruleArgs)
            ) {
                // update_post_meta($postId, 'event_rrule_args', $rruleArgs);
            }
    
            if (Utils::isPluginActiveForNetwork()) {
                if (self::isNetworkWide($postId)) {
                    $this->updateNetworkWideSiteOption($postId);
                    update_post_meta($postId, 'notice_network_wide', 'on');
                } else {
                    $this->deleteNetworkWideSiteOption($postId);
                    delete_post_meta($postId, 'notice_network_wide');
                }
        }
    }
}
