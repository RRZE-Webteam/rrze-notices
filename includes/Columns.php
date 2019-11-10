<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Columns
{
    protected $customPostType;

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
    }

    public function onLoaded()
    {
        add_action('manage_edit-' . $this->customPostType . '_columns', [$this, 'manageEdit']);
        add_action('manage_' . $this->customPostType . '_posts_custom_column', [$this, 'managePosts'], 10, 2);
        add_filter('manage_edit-' . $this->customPostType . '_sortable_columns', [$this, 'manageSortale']);
        add_action('load-edit.php', [$this, 'loadEdit']);
    }

    public function manageEdit($columns)
    {
        return [
            'cb' => '<input type="checkbox">',
            'title' => __('Notice', 'rrze-notices'),
            'type' => __('Type', 'rrze-notices'),
            'date' => __('Date', 'rrze-notices')
        ];
    }

    public function managePosts($column, $postId)
    {
        global $post;

        switch($column) {
            case 'type':
                $allTypes = Util::getNoticeTypes();
                $noticeType = get_post_meta($postId, 'rrze_notices_type', true);
                $type = isset($allTypes[$noticeType]) ? $allTypes[$noticeType] : $allTypes[array_key_first($allTypes)];
                echo $type;
                break;
            default:
                break;
        }
    }

    public function manageSortale($columns)
    {
        $columns['type'] = 'type';
        return $columns;
    }

    public function loadEdit()
    {
        //add_filter('request', [$this, 'request']);
    }

    public function request($vars)
    {
        if ( isset( $vars['post_type'] ) && $this->customPostType == $vars['post_type']) {

            if ( isset($vars['orderby'] ) && 'type' == $vars['orderby'] ) {

                /*
                $vars = array_merge(
                    $vars,
                    [
                        'meta_key' => 'type',
                        'orderby' => 'meta_value_num'
                    ]
                );
                */
            }
        }

        return $vars;
    }
}
