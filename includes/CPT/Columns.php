<?php

declare(strict_types=1);

namespace RRZE\Notices\CPT;

defined('ABSPATH') || exit;

use RRZE\Notices\Helper;
use RRZE\Notices\Admin\Users;

/**
 * Custom columns for the notice list table in wp-admin.
 *
 * Adds Type, Visibility, Network-Wide, and Visible Until columns.
 * Hides network-wide notices from non-superadmins.
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

    /** @var array<int, array|null> Cached notice data per post ID. */
    private static array $noticeDataCache = [];

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
        add_action('load-edit.php', [$this, 'registerNetworkFilter']);
        add_filter('views_edit-' . $this->customPostType, [$this, 'addListDescription']);

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
     * @param int $postId Notice post ID.
     * @return void
     */
    public function renderColumn(string $column, int $postId): void
    {
        if (!array_key_exists($postId, self::$noticeDataCache)) {
            self::$noticeDataCache[$postId] = Post::buildNoticeData($postId);
        }
        $noticeData = self::$noticeDataCache[$postId];
        if ($noticeData !== null) {
            $notice = (object)$noticeData;
            $types = Post::getNoticeTypes();
            $noticeType = isset($types[$notice->type]) ? $types[$notice->type] : $types['info'];
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
                $visibleUntil = (int)get_post_meta($postId, 'notice_visible_until_gmt', true);
                if ($visibleUntil) {
                    echo esc_html(
                        wp_date(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            $visibleUntil,
                        )
                    );
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
                $query->set('meta_query', [
                    'relation' => 'OR',
                    'notice_type_clause' => [
                        'key' => 'notice_type',
                        'compare' => 'EXISTS',
                    ],
                    [
                        'key' => 'notice_type',
                        'compare' => 'NOT EXISTS',
                    ],
                ]);
                $query->set('orderby', 'notice_type_clause');
                break;
            case 'visible_until':
                $query->set('meta_query', [
                    'relation' => 'OR',
                    'visible_until_clause' => [
                        'key' => 'notice_visible_until_gmt',
                        'compare' => 'EXISTS',
                        'type' => 'NUMERIC',
                    ],
                    [
                        'key' => 'notice_visible_until_gmt',
                        'compare' => 'NOT EXISTS',
                    ],
                ]);
                $query->set('orderby', 'visible_until_clause');
                break;
        }
    }

    /**
     * Hide network-wide notices from the post list for non-superadmins.
     *
     * @return void
     */
    public function registerNetworkFilter(): void
    {
        if (!is_super_admin()) {
            add_action('pre_get_posts', [$this, 'excludeNetworkWideNotices']);
        }
    }

    /**
     * Exclude posts with notice_network_wide meta from the query.
     *
     * @param \WP_Query $query Current admin list query.
     * @return void
     */
    public function excludeNetworkWideNotices($query): void
    {
        if ($this->customPostType !== $query->get('post_type')) {
            return;
        }

        $metaQuery = $query->get('meta_query') ?: [];
        $metaQuery[] = [
            'relation' => 'OR',
            [
                'key' => 'notice_network_wide',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => 'notice_network_wide',
                'value' => 'on',
                'compare' => '!=',
            ],
        ];
        $query->set('meta_query', $metaQuery);
    }

    /**
     * Add a description text above the views bar on the notice list screen.
     *
     * @param array $views Existing view links.
     * @return array Unmodified view links.
     */
    public function addListDescription(array $views): array
    {
        printf(
            '<p class="description">%s</p>',
            esc_html__('Create and manage dashboard notices for your website.', 'rrze-notices')
        );
        return $views;
    }

}