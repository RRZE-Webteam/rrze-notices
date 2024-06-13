<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class CPT
{
    /**
     * Initialize the class by setting up action hooks
     */
    public static function loaded()
    {
        add_action('init', [__CLASS__, 'registerCPT']);
        add_action('init', [__CLASS__, 'registerPostStatus']);

        Post::loaded();

        Metaboxes::loaded();

        $columns = new Columns;
        $columns->loaded();
    }

    /**
     * Register custom post type.
     */
    public static function registerCPT()
    {
        register_post_type(
            CPTNotice::POST_TYPE,
            CPTNotice::hidden()
                ->merge([
                    'menu_icon' => 'dashicons-warning',
                    'supports' => [
                        'title',
                        'editor',
                    ],
                    'show_ui' => true,
                    'show_in_nav_menus' => false,
                    'show_in_admin_bar' => false,
                ])
                ->args()
        );
    }
}
