<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Query;

class AdminBar
{
    protected $customPostType;

    protected $notices;
    
    protected $siteOptions;

    protected $userMeta = 'rrze_notice_dismissed';

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
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
        $notices = $this->siteOptions->network_wide;
        if (Util::isMainWebsite() || empty($notices)) {
            return;
        }
        
        foreach ($notices as $id => $notice) {
            $title = $notice->title;
            $content = $notice->content;
            if (!$content) {
                continue;
            }
            $type = $notice->type;
            
            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
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
            $this->addNotices($id, $title, $content, $type, $visibility);
        }
        if (! empty($this->notices)) {
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
        
        if ($posts) {
            foreach ($posts as $post) {
                $id = $post->ID;
                $title = $post->post_title;
                $content = $post->post_content;
                if (!$content) {
                    continue;
                }
                $noticeType = get_post_meta($id, 'rrze_notices_type', true);
                $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
                
                $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
                $roleVisibility = get_post_meta($id, 'rrze_notices_role_visibility', true);
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
                $this->addNotices($id, $title, $content, $type, $visibility);
            }
            if (!empty($this->notices)) {
                add_action('admin_bar_menu', [$this, 'addItems'], 99);
            }
        }
    }

    protected function addNotices($id, $title, $content, $type, $visibility)
    {
        if (!$content || $this->isDismissed($id)) {
            return;
        }
                
        $current_user = wp_get_current_user();
        if (!Util::arrayValInArray($current_user->roles, $visibility) && ! in_array('all', $visibility)) {
            return;
        }

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

            $i ++;
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

        $id = absint($_GET['rrze-notices-id']);
        $href = add_query_arg('rrze-notices-id', $id, get_dashboard_url());

        wp_safe_redirect($href);
        exit();
    }

    protected function isDismissed($id)
    {
        return (get_user_meta(get_current_user_id(), "{$this->userMeta}_{$id}", true));
    }
}
