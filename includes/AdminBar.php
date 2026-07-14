<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Collects active notices and exposes them through the WordPress admin bar.
 */
class AdminBar
{
    /** @var string Network option key used by the plugin. */
    protected $siteOptionsName;

    /** @var object Parsed network-wide plugin options. */
    protected $siteOptions;

    /** @var string Prefix used for per-user dismissal metadata. */
    protected $userMeta = 'rrze_notices_dismissed';

    /**
     * Notices available to the current user.
     *
     * @var array<int, object{id:int|string, title:string, type:string}>
     */
    protected $notices;

    /**
     * Load network options and initialize the notice collection.
     */
    public function __construct()
    {
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
        $this->notices = [];
    }

    /**
     * Register admin-bar request and rendering hooks.
     *
     * @return void
     */
    public function loaded()
    {
        add_action('admin_init', [$this, 'redirectRequest']);
        add_action('admin_head', [$this, 'adminHead']);
    }

    /**
     * Collect visible notices before WordPress renders the admin bar.
     *
     * @return void
     */
    public function adminHead()
    {
        $this->setNetworkWideNotices();
        $this->setNotices();
    }

    /**
     * Add active notices originating on other sites in the network.
     *
     * @return void
     */
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

            // Avoid duplicate notices by not displaying 
            // a network-wide notice on the current blog.
            if (get_current_blog_id() == $blogId) {
                continue;
            }

            if ($this->isDismissed($noticeId)) {
                continue;
            }

            $notice = (object) $notice;
            $event = (object) $notice->event;

            if (!Event::isNoticeActive($notice)) {
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
            $type = $notice->type;

            $this->addNotices($noticeId, $title, $type);
        }

        if (!empty($this->notices)) {
            add_action('admin_bar_menu', [$this, 'addItems'], 99);
        }
    }

    /**
     * Add active notices originating on the current site.
     *
     * @return void
     */
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
            $notice = get_post_meta($postId, 'notice_data', true);
            if (empty($notice) || empty($notice['event'])) {
                continue;
            }

            $notice = (object) $notice;
            $event = (object) $notice->event;

            if (!Event::isNoticeActive($notice)) {
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
            $type = $notice->type;

            $this->addNotices($postId, $title, $type);
        }
        if (!empty($this->notices)) {
            add_action('admin_bar_menu', [$this, 'addItems'], 99);
        }
    }

    /**
     * Append one notice to the admin-bar collection.
     *
     * @param int|string $id    Site-qualified notice ID or local post ID.
     * @param string     $title Notice title.
     * @param string     $type  Notice severity/type slug.
     * @return void
     */
    protected function addNotices($id, $title, $type)
    {
        $this->notices[] = (object)[
            'id' => $id,
            'title' => $title,
            'type' => $type
        ];
    }

    /**
     * Add collected notices to the WordPress admin bar.
     *
     * @param \WP_Admin_Bar $wpAdminBar Admin-bar instance supplied by WordPress.
     * @return void
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
                'title'  => $title,
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

    /**
     * Redirect a nonce-protected admin-bar notice link to the dashboard.
     *
     * @return void
     */
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

    /**
     * Determine whether the current user dismissed a notice.
     *
     * @param int|string $noticeId Site-qualified notice identifier.
     * @return bool
     */
    protected function isDismissed($noticeId)
    {
        return (get_user_meta(get_current_user_id(), "{$this->userMeta}_{$noticeId}", true));
    }
}
