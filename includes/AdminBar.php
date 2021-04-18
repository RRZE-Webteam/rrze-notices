<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Query;

class AdminBar
{
    protected $customPostType;

    protected $siteOptionsName;

    protected $siteOptions;

    protected $userMeta = 'rrze_notices_dismissed';

    protected $notices;

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = [];
    }

    public function onLoaded()
    {
        add_action('admin_head', [$this, 'adminHead']);
        add_action('init', [$this, 'redirectRequest']);
    }

    public function adminHead()
    {
        $this->setNetworkWideNotices();
        $this->setNotices();
    }

    protected function setNetworkWideNotices()
    {
        if (!is_multisite() || !Util::isPluginActiveForNetwork()) {
            return;
        }

        $notices = $this->siteOptions->network_wide;
        if (empty($notices)) {
            return;
        }

        foreach ($notices as $noticeId => $notice) {
            $idArray = explode('-', $noticeId);
            $blogId = isset($idArray[0]) ? absint($idArray[0]) : 0;
            $postId = isset($idArray[1]) ? absint($idArray[1]) : 0;
            if (!$blogId || !$postId) {
                continue;
            }

            if (get_current_blog_id() == $blogId) {
                continue;
            }

            if ($this->isDismissed($noticeId)) {
                continue;
            }

            $notice = (object) $notice;

            $title = isset($notice->title) ? $notice->title : '';
            $content = isset($notice->content) ? $notice->content : '';
            $type = isset($notice->type) ? $notice->type : 'info';
            $expiration = isset($notice->expiration) ? $notice->expiration : current_time('timestamp');
            $expirationGmt = isset($notice->expiration_gmt) ? $notice->expiration_gmt : time();
            $altStyle = isset($notice->alt_style) ? $notice->alt_style : false;
            $hideTitle = isset($notice->hide_title) ? $notice->hide_title : false;
            $visibility = isset($notice->visibility) ? $notice->visibility : true;
            $adminLink = isset($notice->admin_link) ? $notice->admin_link : '';

            $expired = Util::isExpired($expirationGmt);
            if ($expired) {
                continue;
            }

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
            $roleVisibility = $visibility;
            if (!empty($roleVisibility)) {
                $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
            } else {
                $roleVisibility = [];
            }
            $visibility = [];
            foreach ($roleVisibility as $k) {
                if (isset($availableRoles[$k])) {
                    $visibility[] = $k;
                }
            }
            if (!empty($visibility)) {
                $visibility = !is_array($visibility) ? [$visibility] : $visibility;
            } else {
                $visibility = [];
            }
            $current_user = wp_get_current_user();
            if (!Util::arrayValInArray($current_user->roles, $visibility) && !in_array('all', $visibility)) {
                continue;
            }

            if (!$content) {
                continue;
            }

            $type = array_key_exists($type, Util::getNoticeTypes()) ? $type : 'info';

            $this->addNotices($noticeId, $title, $type);
        }
        if (!empty($this->notices)) {
            add_action('admin_bar_menu', [$this, 'addItems'], 99);
        }
    }

    protected function setNotices()
    {
        $posts = get_posts([
            'post_type' => $this->customPostType,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        if (!$posts) {
            return;
        }
        foreach ($posts as $post) {
            $blogId = get_current_blog_id();
            $postId = $post->ID;
            $noticeId = sprintf('%d-%d', $blogId, $postId);

            if ($this->isDismissed($noticeId)) {
                continue;
            }

            $expiration = Util::getExpirationGmt($postId);
            $expired = Util::isExpired($expiration);
            if ($expired) {
                continue;
            }

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
            $roleVisibility = get_post_meta($postId, 'rrze_notices_visibility', true);
            if (!empty($roleVisibility)) {
                $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
            } else {
                $roleVisibility = [];
            }
            $visibility = [];
            foreach ($roleVisibility as $k) {
                if (isset($availableRoles[$k])) {
                    $visibility[] = $k;
                }
            }
            $current_user = wp_get_current_user();
            if (!Util::arrayValInArray($current_user->roles, $visibility) && !in_array('all', $visibility)) {
                continue;
            }

            $content = $post->post_content;
            if (!$content) {
                continue;
            }

            $title = $post->post_title;

            $noticeType = Util::getType($postId);
            $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';

            $this->addNotices($postId, $title, $type);
        }
        if (!empty($this->notices)) {
            add_action('admin_bar_menu', [$this, 'addItems'], 99);
        }
    }

    protected function addNotices($id, $title, $type)
    {
        $this->notices[] = (object)[
            'id' => $id,
            'title' => $title,
            'type' => $type
        ];
    }

    /**
     * Undocumented function
     *
     * @param object \WP_Admin_Bar $wp_admin_bar
     * @return void
     */
    public function addItems(&$wp_admin_bar)
    {
        $title = sprintf(
            '<span class="ab-icon dashicons"></span><span class="ab-label">%s</span> <span class="notices-count">%s</span>',
            esc_html__('Notices', 'rrze-notices'),
            count($this->notices)
        );

        $wp_admin_bar->add_menu(
            [
                'id'     => 'rrze-notices',
                'parent' => 'top-secondary',
                'title'  => $title,
                'href'   => false
            ]
        );

        $i = 0;
        foreach ($this->notices as $notice) {
            $href = wp_nonce_url(add_query_arg('rrze-notices-id', $notice->id), 'rrze_notices_redirect_nonce');
            $metaClass = 'notices-' . $notice->type;

            $wp_admin_bar->add_menu(
                [
                    'id'     => 'rrze-notices-item-' . $i,
                    'parent' => 'rrze-notices',
                    'title'  => $notice->title,
                    'href'   => $href,
                    'meta'   => [
                        'class' => $metaClass
                    ]
                ]
            );

            $i++;
        }
    }

    public function redirectRequest()
    {
        if (empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'rrze_notices_redirect_nonce')) {
            return;
        }
        if (empty($_GET['rrze-notices-id'])) {
            return;
        }

        $noticeId = absint($_GET['rrze-notices-id']);
        $href = add_query_arg('rrze-notices-id', $noticeId, get_dashboard_url());

        wp_safe_redirect($href);
        exit();
    }

    protected function isDismissed($noticeId)
    {
        return (get_user_meta(get_current_user_id(), "{$this->userMeta}_{$noticeId}", true));
    }
}
