<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Configures and renders columns on the notice post list screen.
 */
class Columns
{
    /** @var string Notice custom post type slug. */
    protected $customPostType;

    /** @var object Parsed network-wide plugin options. */
    protected $siteOptions;

    /**
     * Initialize column dependencies.
     */
    public function __construct()
    {
        $this->customPostType = CPTNotice::POST_TYPE;
        $this->siteOptions = Options::getSiteOptions();
    }

    /**
     * Register notice-list column hooks.
     *
     * @return void
     */
    public function loaded()
    {
        add_action('manage_edit-' . $this->customPostType . '_columns', [$this, 'manageColumns']);
        add_action('manage_' . $this->customPostType . '_posts_custom_column', [$this, 'managePostsCustomColumn'], 10, 2);
        add_filter('manage_edit-' . $this->customPostType . '_sortable_columns', [$this, 'manageSortableColumns']);
        add_action('load-edit.php', [$this, 'loadEdit']);
    }

    /**
     * Replace the default notice-list columns.
     *
     * @param array<string, string> $columns Existing column labels.
     * @return array<string, string> Notice column labels.
     */
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
            // 'date' => __('Date', 'rrze-notices')
        ];
        if (!is_super_admin() || !Utils::isPluginActiveForNetwork()) {
            unset($columns['network_wide']);
        }
        return $columns;
    }

    /**
     * Render the value for a custom notice-list column.
     *
     * @param string $column Column identifier.
     * @param int    $postId Notice post ID.
     * @return void
     */
    public function managePostsCustomColumn($column, $postId)
    {
        $notice = get_post_meta($postId, 'notice_data', true);
        if (!empty($notice) && !empty($notice['event'])) {
            $notice = (object) $notice;
            $event = (object) $notice->event;
            $types = [
                'info' => __('Info', 'rrze-notices'),
                'warning' => __('Warning', 'rrze-notices')
            ];
            $noticeType = in_array($notice->type, array_keys($types)) ? $types[$notice->type] : __('Info', 'rrze-notices');
            $noticeStartsAt = Event::noticeStartsAt($notice);
            $noticeStartDate = $noticeStartsAt
                ? wp_date(
                    get_option('date_format'),
                    $noticeStartsAt->getTimestamp(),
                    Event::timezone($event)
                )
                : '&mdash;';
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
            $noticeStartDate = '&mdash;';
            $readableDate = '&mdash;';
            $visibility = '&mdash;';
            $networkWide = '&mdash;';
        }

        switch ($column) {
            case 'notice_type':
                echo $noticeType;
                break;
            case 'notice_start':
                echo $noticeStartDate;
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

    /**
     * Register sortable notice-list columns.
     *
     * @param array<string, string> $columns Existing sortable columns.
     * @return array<string, string> Updated sortable columns.
     */
    public function manageSortableColumns($columns)
    {
        $columns['notice_type'] = 'notice_type';
        return $columns;
    }

    /**
     * Register query customization on the notice list screen.
     *
     * @return void
     */
    public function loadEdit()
    {
        add_action('pre_get_posts', [$this, 'columnOrderby']);
    }

    /**
     * Apply metadata ordering requested by a sortable notice column.
     *
     * @param \WP_Query $query Current admin list query.
     * @return void
     */
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
