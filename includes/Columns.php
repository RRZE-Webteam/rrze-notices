<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Columns
{
    protected $customPostType;

    protected $siteOptions;

    public function __construct()
    {
        $this->customPostType = CPTNotice::POST_TYPE;
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
        if (!is_super_admin() || !Utils::isPluginActiveForNetwork()) {
            unset($columns['network_wide']);
        }
        return $columns;
    }

    public function managePostsCustomColumn($column, $postId)
    {
        $notice = get_post_meta($postId, 'notice_data', true);
        if (!empty($notice) && !empty($notice['event'])) {
            $notice = (object) $notice;
            $event = (object) $notice->event;
            $noticeType = $notice->type;
            $noticeStart = $notice->notice_start;
            $eventStart = $event->start_gmt;
            $tsNoticeStart = strtotime($eventStart) - absint($noticeStart);
            $readableDate = $event->readable_datetime;
            $networkWide = $notice->network_wide ?
                '<span class="dashicons dashicons-yes"></span>' :
                '&mdash;';

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
            $roles = $notice->visibility;
            $visibility = [];
            foreach ($roles as $k) {
                if (isset($availableRoles[$k])) {
                    $visibility[] = $availableRoles[$k];
                }
            }
            $visibility = !empty($visibility) ? implode(', ', $visibility) : '&mdash;';
        } else {
            $noticeType = '&mdash;';
            $noticeStart = '&mdash;';
            $readableDate = '&mdash;';
            $visibility = '&mdash;';
            $networkWide = '&mdash;';
        }

        switch ($column) {
            case 'notice_type':
                echo $noticeType;
                break;
            case 'notice_start':
                echo $tsNoticeStart > 0 ? date_i18n(get_option('date_format'), $tsNoticeStart) : '&mdash;';
                echo !is_super_admin() && Post::isNetworkWide($postId) ? '<br>' . __('(Network-Wide)', 'rrze-notices') : '';
                break;
            case 'event_date':
                echo $readableDate;
                break;
            case 'network_wide':
                echo $networkWide;
                break;
            case 'visibility':
                echo $visibility;
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
