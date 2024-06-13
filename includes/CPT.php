<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class CPT
{
    /**
     * Custom post status.
     */
    public const EXPIRED_POST_STATUS = 'expired';

    /**
     * Initialize the class by setting up action hooks
     */
    public static function loaded()
    {
        add_action('init', [__CLASS__, 'registerCPT']);
        add_action('init', [__CLASS__, 'registerPostStatus']);
        add_action('admin_footer-post.php', [__CLASS__, 'appendPostStatusPostbox']);
        add_action('admin_footer-edit.php', [__CLASS__, 'appendPostStatusQuickEdit']);

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

    /**
     * Register custom post status.
     */
    public static function registerPostStatus()
    {
        if (in_array(self::EXPIRED_POST_STATUS, get_post_stati())) {
            return;
        }
        register_post_status(self::EXPIRED_POST_STATUS, [
            'label' => _x('Expired', 'custom post status', 'rrze-notices'),
            'public' => false,
            'private' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => false,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                /* translators: %s: Number of expired posts. */
                'Expired <span class="count">(%s)</span>',
                'Expired <span class="count">(%s)</span>',
                'rrze-notices'
            )
        ]);
    }

    /**
     * Append custom post status to the postbox.
     */
    public static function appendPostStatusPostbox()
    {
        $post = get_post();
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $selected = '';
        $postStatusDisplay = '';
        $title = _x('Expired', 'custom post status', 'rrze-notices');
        if ($post->post_status == self::EXPIRED_POST_STATUS) {
            $postStatusDisplay = '$(".misc-pub-post-status span#post-status-display").text("' . $title . '");';
            $selected = ' selected=\"selected\"';
        }
        $script = '
        <script>
        jQuery(document).ready(function($){
        $("select#post_status").append("<option value=\"expired\"' . $selected . '>' . $title . '</option>");
        ' . $postStatusDisplay . '
        });
        </script>
        ';
        echo str_replace(PHP_EOL, '', trim(preg_replace('/\s+/', ' ', $script)));
    }

    /**
     * Append custom post status to the quick edit.
     */
    public static function appendPostStatusQuickEdit()
    {
        $post = get_post();
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $title = _x('Expired', 'custom post status', 'rrze-notices');
        $script = "<script>
        jQuery(document).ready(function($) {
        $('select[name=\"_status\"]').append('<option value=\"expired\">" . $title . "</option>');
        });
        </script>";
        echo str_replace(PHP_EOL, '', trim(preg_replace('/\s+/', ' ', $script)));
    }
}
