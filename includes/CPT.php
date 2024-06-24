<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class CPT
{
    /**
     * Setting up action hooks
     */
    public static function loaded()
    {
        add_action('init', [__CLASS__, 'registerCPT']);

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
            CPTNotice::nonPublic()
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
