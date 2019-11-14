<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Query;

class AdminBar
{
    protected $customPostType;

    protected $notices;
    
    protected $pluginFile;
    
    protected $pluginBaseName;
    
    protected $siteOptionsName;
    
    protected $siteOptions;

    protected $userMeta = 'rrze_notice_dismissed';

    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
        $this->pluginBaseName = plugin_basename($this->pluginFile);
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
        if (!is_multisite() || !is_plugin_active_for_network($this->pluginBaseName)) {
            return;
        }
                
        $notices = $this->siteOptions->network_wide;
        if (empty($notices)) {
            return;
        }
        
        foreach ($notices as $id => $notice) {
            global $wpdb;
            $idArray = explode('-', $id);
            $blogId = isset($idArray[0]) ? absint($idArray[0]) : 0;
            if ($wpdb->blogid == $blogId) {
                continue;
            }
            
            if ($this->isDismissed($id)) {
                continue;
            }
                        
            $allIntervals = Util::getExpirationIntervals();
            $expiration = array_key_exists($notice->expiration, $allIntervals) ? $notice->expiration : 'week';
            $expired = Util::isExpired($expiration, $notice->date_gmt);
            if ($expired) {
                if (isset($this->siteOptions->network_wide[$id])) {
                    unset($this->siteOptions->network_wide[$id]);
                    update_site_option($this->siteOptionsName, $this->siteOptions);
                }
                continue;
            }
                                    
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
            $current_user = wp_get_current_user();
            if (!Util::arrayValInArray($current_user->roles, $visibility) && !in_array('all', $visibility)) {
                continue;
            }
                
            $content = $notice->content;
            if (!$content) {
                continue;
            }
            
            $title = $notice->title;
            $type = array_key_exists($notice->type, Util::getNoticeTypes()) ? $notice->type : 'info';
                        
            $this->addNotices($id, $title, $type);
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
        if (!$posts) {
            return;
        }
        foreach ($posts as $post) {
            $id = $post->ID;
            
            if ($this->isDismissed($id)) {
                continue;
            }
                          
            $allIntervals = Util::getExpirationIntervals();
            $noticeExpiration = get_post_meta($id, 'rrze_notices_expiration', true);
            $expiration = array_key_exists($noticeExpiration, $allIntervals) ? $noticeExpiration : 'week';
            $expired = Util::isExpired($expiration, $post->post_date_gmt);
            if ($expired) {
                continue;
            }
            
            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
            $roleVisibility = get_post_meta($id, 'rrze_notices_visibility', true);
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
                            
            $noticeType = get_post_meta($id, 'rrze_notices_type', true);
            $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
                            
            $this->addNotices($id, $title, $type);
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
