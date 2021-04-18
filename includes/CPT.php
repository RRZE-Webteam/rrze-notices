<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class CPT
{
    const CPT_NAME = 'notice';

    protected $siteOptions;

    protected $metaBoxes;

    public function __construct()
    {
        $this->siteOptions = Options::getSiteOptions();
    }

    public function onLoaded()
    {
        add_action('init', [$this, 'registerCPT']);

        $post = new Post;
        $post->onLoaded();

        $columns = new Columns;
        $columns->onLoaded();

        $metaBoxes = new MetaBoxes;
        $metaBoxes->onLoaded();
    }

    public static function getPostTypeName()
    {
        return static::CPT_NAME;
    }

    public function registerCPT()
    {
        $capability = Util::isPluginActiveForNetwork() ? $this->siteOptions->access : 'manage_options';

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
                'edit_post'          => $capability,
                'read_post'          => $capability,
                'delete_posts'       => $capability,
                'edit_posts'         => $capability,
                'edit_others_posts'  => $capability,
                'publish_posts'      => $capability,
                'read_private_posts' => $capability,
                'create_posts'       => $capability
            ],
            //'map_meta_cap'          => null,
            'hierarchical'          => false,
            'supports'              => ['title', 'editor'],
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
}
