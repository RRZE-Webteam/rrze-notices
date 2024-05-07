<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class CPT
{
    const POST_TYPE = 'notice';

    const EXPIRED_POST_STATUS = 'expired';

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

    public static function registerCPT()
    {
        $labels = [
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
        ];

        $siteOptions = Options::getSiteOptions();
        $capability = Utils::isPluginActiveForNetwork() ? $siteOptions->access : 'manage_options';

        $capabilityType = $capability == 'manage_options' ? 'page' : self::POST_TYPE;
        $capabilities = [
            'edit_post'              => 'edit_' . $capabilityType,
            'read_post'              => 'read_' . $capabilityType,
            'delete_post'            => 'delete_' . $capabilityType,
            'edit_posts'             => 'edit_' . $capabilityType . 's',
            'edit_others_posts'      => 'edit_others_' . $capabilityType . 's',
            'delete_posts'           => 'delete_' . $capabilityType . 's',
            'publish_posts'          => 'publish_' . $capabilityType . 's',
            'read_private_posts'     => 'read_private_' . $capabilityType . 's',
        ];

        $args = [
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
            'capability_type'       => $capabilityType,
            'capabilities'          => $capabilities,
            'map_meta_cap'          => true,
            'hierarchical'          => false,
            'supports'              => ['title', 'editor'],
            'has_archive'           => false,
            'rewrite'               => false,
            'query_var'             => true,
            'can_export'            => true,
            'delete_with_user'      => null,
            'show_in_rest'          => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public static function registerPostStatus()
    {
        if (in_array(self::EXPIRED_POST_STATUS, get_post_stati())) {
            return;
        }
        register_post_status(self::EXPIRED_POST_STATUS, [
            'label'                     => _x('Expired', 'custom post status', 'rrze-notices'),
            'public'                    => false,
            'private'                   => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => false,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                /* translators: %s: Number of expired posts. */
                'Expired <span class="count">(%s)</span>',
                'Expired <span class="count">(%s)</span>',
                'rrze-notices'
            )
        ]);
    }

    public static function appendPostStatusPostbox()
    {
        $post = get_post();
        if (!$post || $post->post_type !== self::POST_TYPE) {
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

    public static function appendPostStatusQuickEdit()
    {
        $post = get_post();
        if (!$post || $post->post_type !== self::POST_TYPE) {
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
