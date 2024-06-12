<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Columns
{
    protected $customPostType;

    protected $siteOptions;

    public function __construct()
    {
        $this->customPostType = CPT::POST_TYPE;
        $this->siteOptions = Options::getSiteOptions();
    }

    public function loaded()
    {
        add_action('manage_edit-' . $this->customPostType . '_columns', [$this, 'manageColumns']);
        add_action('manage_' . $this->customPostType . '_posts_custom_column', [$this, 'managePostsCustomColumn'], 10, 2);
        add_filter('manage_edit-' . $this->customPostType . '_sortable_columns', [$this, 'manageSortableColumns']);
        add_action('load-edit.php', [$this, 'loadEdit']);
    }

    public function manageColumns($columns)
    {
        $columns = [
            'cb' => '<input type="checkbox">',
            'title' => __('Notice', 'rrze-notices'),
            'notice_type' => __('Type', 'rrze-notices'),
            'notice_start' => __('Start', 'rrze-notices'),
            'event_date' => __('Event Date', 'rrze-notices'),
            'visibility' => __('Visibility', 'rrze-notices'),
            'network_wide' => __('Network-Wide', 'rrze-notices'),
            'date' => __('Date', 'rrze-notices')
        ];
        if (!current_user_can('manage_network_options') || !Utils::isPluginActiveForNetwork()) {
            unset($columns['network_wide']);
        }
        return $columns;
    }

    public function managePostsCustomColumn($column, $postId)
    {
        switch ($column) {
            case 'notice_type':
                echo Post::getType($postId);
                break;
            case 'notice_start':
                $tsStart = Post::getNoticeStart($postId);
                echo $tsStart ? date_i18n(get_option('date_format'), $tsStart) : '&mdash;';
                echo !current_user_can('manage_network_options') && Post::isNetworkWide($postId) ? '<br>' . __('(Network-Wide)', 'rrze-notices') : '';
                break;
            case 'event_date':
                $data = Post::getEventData($postId);
                $eventDate = [
                    ($data['event']['date'] ?? '') . (isset($data['event']['time']) ? ', ' . $data['event']['time'] : '&mdash;'),
                    Post::getHumanReadableRecurrence($postId)
                ];
                $eventDate = array_filter($eventDate);
                echo implode('<br>', $eventDate);
                break;
            case 'network_wide':
                echo Post::isNetworkWide($postId) ?
                    '<span class="dashicons dashicons-yes"></span>' :
                    '&mdash;';
                break;
            case 'visibility':
                $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
                $roles = Post::getVisibility($postId);
                $visibility = [];
                foreach ($roles as $k) {
                    if (isset($availableRoles[$k])) {
                        $visibility[] = $availableRoles[$k];
                    }
                }
                echo !empty($visibility) ? implode(', ', $visibility) : '&mdash;';
                break;
            default:
                break;
        }
    }

    public function manageSortableColumns($columns)
    {
        $columns['notice_type'] = 'notice_type';
        return $columns;
    }

    public function loadEdit()
    {
        add_action('pre_get_posts', [$this, 'columnOrderby']);
    }

    public function columnOrderby($query)
    {
        if ($this->customPostType != $query->get('post_type')) {
            return;
        }
        $orderBy = $query->get('orderby');
        switch ($orderBy) {
            case 'type':
                $query->set('meta_key', 'rrze_notices_type');
                break;
            default:
                break;
        }
        $query->set('orderby', 'meta_value');
    }
}
