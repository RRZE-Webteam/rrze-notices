<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class AdminBar
{
    protected $siteOptionsName;

    protected $siteOptions;

    protected $userMeta = 'rrze_notices_dismissed';

    protected $notices;

    public function __construct()
    {
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = [];
    }

    public function loaded()
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
        if (!Utils::isPluginActiveForNetwork()) {
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

            switch_to_blog($blogId);
            $currentUser = wp_get_current_user();
            restore_current_blog();

            $notice = (object) $notice;

            $title = $notice->title ?? '';
            $type = $notice->type ?? 'info';
            $visibility = $notice->visibility ?? ['all'];

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
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
            if (
                !is_super_admin() &&
                !Utils::arrayValInArray($currentUser->roles, $visibility) &&
                !in_array('all', $visibility)
            ) {
                continue;
            }

            $type = array_key_exists($type, Post::getNoticeTypes()) ? $type : 'info';

            $this->addNotices($noticeId, $title, $type);
        }
        if (!empty($this->notices)) {
            add_action('admin_bar_menu', [$this, 'addItems'], 99);
        }
    }

    protected function setNotices()
    {
        $posts = get_posts([
            'fields' => 'ids',
            'post_type' => CPTNotice::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        if (!$posts) {
            return;
        }

        foreach ($posts as $postId) {
            if ($notice = get_post_meta($postId, 'notice_data', true)) {
                $notice = (object) $notice;
            } else {
                continue;
            }

            $blogId = get_current_blog_id();
            $noticeId = sprintf('%d-%d', $blogId, $postId);

            if ($this->isDismissed($noticeId)) {
                continue;
            }

            $currentUser = wp_get_current_user();

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
            $roleVisibility = $notice->visibility;
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
            if (
                !is_super_admin() &&
                !Utils::arrayValInArray($currentUser->roles, $visibility) &&
                !in_array('all', $visibility)
            ) {
                continue;
            }

            $title = $notice->title;

            $noticeType = Post::getType($postId);
            $type = array_key_exists($noticeType, Post::getNoticeTypes()) ? $noticeType : 'info';

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
     *  Add items to the admin bar.
     * @param \WP_Admin_Bar $wpAdminBar
     */
    public function addItems(&$wpAdminBar)
    {
        $title = sprintf(
            '<span class="ab-icon dashicons"></span><span class="ab-label">%s</span> <span class="notices-count">%s</span>',
            esc_html__('Notices', 'rrze-notices'),
            count($this->notices)
        );

        $wpAdminBar->add_menu(
            [
                'id'     => 'rrze-notices',
                'parent' => 'top-secondary',
                'title'  => esc_html($title),
                'href'   => false
            ]
        );

        $i = 0;
        foreach ($this->notices as $notice) {
            $href = wp_nonce_url(add_query_arg('rrze-notices-id', $notice->id), 'rrze_notices_redirect_nonce');
            $metaClass = 'notices-' . $notice->type;

            $wpAdminBar->add_menu(
                [
                    'id'     => 'rrze-notices-item-' . $i,
                    'parent' => 'rrze-notices',
                    'title'  => esc_html($notice->title),
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
