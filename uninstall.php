<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Delete notices of every status and the cron event on the current site.
 *
 * Read IDs directly because custom statuses may be unregistered on uninstall.
 * Delete through WordPress so associated post metadata is removed as well.
 *
 * @return void
 */
function rrze_notices_uninstall_current_site(): void
{
    global $wpdb;
    $lastId = 0;
    do {
        $postIds = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND ID > %d
             ORDER BY ID ASC LIMIT 100",
            'notice',
            $lastId
        ));
        foreach ($postIds as $postId) {
            wp_delete_post((int)$postId, true);
            $lastId = (int)$postId;
        }
    } while (count($postIds) === 100);

    wp_clear_scheduled_hook('rrze_notices_update_status');
}

if (is_multisite()) {
    $offset = 0;
    do {
        $siteIds = get_sites([
            'fields'  => 'ids',
            'number'  => 100,
            'offset'  => $offset,
            'orderby' => 'id',
            'order'   => 'ASC',
        ]);
        foreach ($siteIds as $siteId) {
            switch_to_blog((int)$siteId);
            try {
                rrze_notices_uninstall_current_site();
            } finally {
                restore_current_blog();
            }
        }
        $offset += count($siteIds);
    } while (count($siteIds) === 100);
} else {
    rrze_notices_uninstall_current_site();
}

// Network option (shared across all sites)
delete_site_option('rrze_notices_site');

// User dismissal metadata (stored in the global usermeta table)
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('rrze_notices_dismissed_') . '%'
    )
);