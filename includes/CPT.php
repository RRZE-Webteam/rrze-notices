<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class CPT
{
    /**
     * [CPT_NAME description]
     * @var string
     */
    const CPT_NAME = 'notice';

    public function onLoaded()
    {
        add_action('init', [$this, 'registerCPT']);
    }

    public static function getPostTypeName()
    {
        return static::CPT_NAME;
    }

    public function registerCPT()
    {
        $labels = array(
            'name'               => _x('Notices', 'post type general name', 'rrze-notices'),
            'singular_name'      => _x('Notice', 'post type singular name', 'rrze-notices'),
            'menu_name'          => _x('Notices', 'admin menu', 'rrze-notices'),
            'name_admin_bar'     => _x('Notice', 'add new on admin bar', 'rrze-notices'),
            'add_new'            => _x('Add New', 'notice', 'rrze-notices'),
            'add_new_item'       => __('Add New Notice', 'rrze-notices'),
            'new_item'           => __('New Notice', 'rrze-notices'),
            'edit_item'          => __('Edit Notice', 'rrze-notices'),
            'view_item'          => __('View Notice', 'rrze-notices'),
            'all_items'          => __('All Notices', 'rrze-notices'),
            'search_items'       => __('Search Notices', 'rrze-notices'),
            'parent_item_colon'  => __('Parent Notices:', 'rrze-notices'),
            'not_found'          => __('No notices found.', 'rrze-notices'),
            'not_found_in_trash' => __('No notices found in Trash.', 'rrze-notices')
        );

        $args = array(
            'labels'                => $labels,
            'description'           => '',
            'public'                => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_nav_menus'     => false,
            'show_in_menu'          => true,
            'show_in_admin_bar'     => false,
            'menu_position'         => null,
            'menu_icon'             => 'dashicons-warning',
            'capability_type'       => 'page',
            'capabilities'          => [
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_posts'       => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
                'create_posts'       => 'manage_options'
            ],
            //'map_meta_cap'          => null,
            'hierarchical'          => false,
            'supports'              => ['title', 'editor'],
            'register_meta_box_cb'  => [$this, 'addMetaBoxes'],
            //'taxonomies'            => [],
            'has_archive'           => false,
            'rewrite'               => false,
            //'permalink_epmask'    => EP_PERMALINK,
            'query_var'             => true,
            'can_export'            => true,
            'delete_with_user'      => null,
            'show_in_rest'          => false,
            //'rest_base'             => static::CPT_NAME,
            //'rest_controller_class' => WP_REST_Posts_Controller,
        );

        register_post_type(static::CPT_NAME, $args);
    }

    public function addMetaBoxes()
    {
        $metaBoxes = new MetaBoxes();
        $metaBoxes->addMetaBoxes();
    }
}
