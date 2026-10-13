<?php

declare(strict_types=1);

namespace RRZE\Notices\CPT;

defined('ABSPATH') || exit;

use RRZE\Notices\Editor\Metaboxes;
use RRZE\Notices\Editor\EditorConfig;

/**
 * Registers the notice post type and wires up its sub-components
 * (Post lifecycle, Metaboxes, EditorConfig, Columns).
 */
class Registration
{
    /**
     * Register post-type, metadata, and list-table hooks.
     *
     * @return void
     */
    public static function registerHooks(): void
    {
        add_action('init', [__CLASS__, 'registerNoticePostType']);

        Post::registerHooks();

        Metaboxes::registerHooks();

        EditorConfig::registerHooks();

        $columns = new Columns;
        $columns->registerHooks();
    }

    /**
     * Register custom post type.
     *
     * @return void
     */
    public static function registerNoticePostType(): void
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
