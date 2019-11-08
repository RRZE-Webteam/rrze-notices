<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Query;

class AdminBar 
{
    protected $customPostType;

    protected $notices;

    protected $userMeta = 'rrze_notice_dismissed';

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
        $this->notices = [];
    }

	public function onLoaded() {
        add_action('admin_head', [$this, 'setNotices']);
        add_action('init', [$this, 'redirectRequest']);
	}

    public function setNotices()
    {
        $args = [
            'post_type' => $this->customPostType,
            'post_status' => 'publish',
            'posts_per_page' => -1
        ];
        $posts = new WP_Query($args);
        
        if ($posts->have_posts()) {
            while ($posts->have_posts()) {
                $posts->the_post(); 
                $postId = get_the_ID();
                $postTitle = get_the_title();
                $postContent = get_the_content();
                if (! $this->isDismissed($postId)) {
                    $this->addNotices($postId, $postTitle, $postContent);
                }
            }
            if (! empty($this->notices)) {
                add_action('admin_bar_menu', [$this, 'addItems'], 99);
            }
        }
        wp_reset_postdata();
    }

    protected function addNotices($postId, $postTitle, $postContent)
    {
        $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
        $roleVisibility = get_post_meta($postId, 'rrze_notices_role_visibility', true);
        
        if (!empty($roleVisibility)) { 
            $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
        } else {
            $roleVisibility = [];
        }        

        $current_user = wp_get_current_user();
        if (!Util::arrayValInArray($current_user->roles, $roleVisibility) && ! in_array('all', $roleVisibility)) {
            return;
        }

        $this->notices[] = [
            'id' => $postId,
            'title' => $postTitle,
        ];
    }

    /**
     * Undocumented function
     *
     * @param object \WP_Admin_Bar $wp_admin_bar
     * @return void
     */
    public function addItems( &$wp_admin_bar ) 
    {
		$title = sprintf(
            '<span class="ab-icon dashicons"></span><span class="ab-label">%s</span> <span class="notices-count">%s</span>',
            esc_html__('Notices', 'rrze-notices'), 
            count($this->notices)
        );

		$wp_admin_bar->add_menu([
                'id'     => 'rrze-notices',
                'parent' => 'top-secondary',
                'title'  => $title,
                'href'   => false
            ]
        );

        $i = 0;
        foreach ($this->notices as $notice) {
            $href = wp_nonce_url(add_query_arg('rrze-notices-id', $notice['id']), 'rrze_notices_redirect_nonce');
            $metaClass = implode( ' ', array_map( function ( $type ) {
                return 'wpnc-' . $type;
            }, ['info', 'warning'] ) );

            $wp_admin_bar->add_menu([
                    'id'     => 'rrze-notices-item-' . $i,
                    'parent' => 'rrze-notices',
                    'title'  => $notice['title'],
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

        $postId = absint($_GET['rrze-notices-id']);
        $postType = get_post_type($postId);
        if (!$postType || $postType != $this->customPostType) {
            return;
        }

        $href = add_query_arg('rrze-notices-id', $postId, get_dashboard_url());

        wp_safe_redirect($href);
        exit();
    }    

	protected function isDismissed($id) {
		return ( get_user_meta( get_current_user_id(), "{$this->userMeta}_{$id}", true ) );
	}    
}