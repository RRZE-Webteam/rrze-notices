<?php

declare(strict_types=1);

namespace RRZE\Notices\CPT;

defined('ABSPATH') || exit;

use RRZE\Notices\Helper;
use RRZE\Notices\Admin\Users;

/**
 * Configures and renders columns on the notice post list screen.
 */
class Columns
{
    /** @var string Notice custom post type slug. */
    protected $customPostType;

    /**
     * Initialize column dependencies.
     */
    public function __construct()
    {
        $this->customPostType = CPTNotice::POST_TYPE;
    }

    /**
     * Register notice-list column hooks.
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('manage_edit-' . $this->customPostType . '_columns', [$this, 'defineColumns']);
        add_action('manage_' . $this->customPostType . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_filter('manage_edit-' . $this->customPostType . '_sortable_columns', [$this, 'defineSortableColumns']);
        add_action('load-edit.php', [$this, 'registerSortingHook']);
    }

    /**
     * Replace the default notice-list columns.
     *
     * @param array<string, string> $columns Existing column labels.
     * @return array<string, string> Notice column labels.
     */
    public function defineColumns(array $columns): array
    {
        $columns = [
            'cb' => '<input type="checkbox">',
            'title' => __('Notice', 'rrze-notices'),
            'notice_type' => __('Type', 'rrze-notices'),
            'visibility' => __('Visibility', 'rrze-notices'),
            'network_wide' => __('Network-Wide', 'rrze-notices'),
            'visible_until' => __('Visible Until', 'rrze-notices'),
            // 'date' => __('Date', 'rrze-notices')
        ];
        if (!is_super_admin() || !Helper::isPluginActiveForNetwork()) {
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
    public function renderColumn(string $column, int $postId): void
    {
        $noticeData = Post::buildNoticeData($postId);
        if ($noticeData !== null) {
            $notice = (object) $noticeData;
            $types = [
                'info' => __('Info', 'rrze-notices'),
                'warning' => __('Warning', 'rrze-notices')
            ];
            $noticeType = in_array($notice->type, array_keys($types)) ? $types[$notice->type] : __('Info', 'rrze-notices');
            $networkWide = $notice->network_wide ?
                '<span class="dashicons dashicons-yes"></span>' :
                '-';

            $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Users::getRoles());
            $roles = $notice->visibility;
            $visibility = [];
            foreach ($roles as $roleSlug) {
                if (isset($availableRoles[$roleSlug])) {
                    $visibility[] = esc_html($availableRoles[$roleSlug]);
                }
            }
            $visibility = !empty($visibility) ? implode(', ', $visibility) : '-';
        } else {
            $noticeType = '-';
            $visibility = '-';
            $networkWide = '-';
        }

        switch ($column) {
            case 'notice_type':
                echo esc_html($noticeType);
                break;
            case 'network_wide':
                echo wp_kses($networkWide, ['span' => ['class' => []]]);
                break;
            case 'visibility':
                echo esc_html($visibility);
                break;
            case 'visible_until':
                $visibleUntil = (int) get_post_meta($postId, 'notice_visible_until_gmt', true);
                if ($visibleUntil) {
                    echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $visibleUntil));
                } else {
                    echo esc_html('-');
                }
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
    public function defineSortableColumns(array $columns): array
    {
        $columns['notice_type'] = 'notice_type';
        $columns['visible_until'] = 'visible_until';
        return $columns;
    }

    /**
     * Register query customization on the notice list screen.
     *
     * @return void
     */
    public function registerSortingHook(): void
    {
        add_action('pre_get_posts', [$this, 'applyColumnOrdering']);
    }

    /**
     * Apply metadata ordering requested by a sortable notice column.
     *
     * @param \WP_Query $query Current admin list query.
     * @return void
     */
    public function applyColumnOrdering($query): void
    {
        if ($this->customPostType !== $query->get('post_type')) {
            return;
        }
        $orderBy = $query->get('orderby');
        switch ($orderBy) {
            case 'notice_type':
                $query->set('meta_key', 'notice_type');
                $query->set('orderby', 'meta_value');
                break;
            case 'visible_until':
                $query->set('meta_key', 'notice_visible_until_gmt');
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }
}
