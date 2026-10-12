<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

// Network option (shared across all sites)
delete_site_option('rrze_notices_site');

// Clean up the current site only
$postIds = get_posts([
    'post_type'   => 'notice',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields'      => 'ids',
]);
foreach ($postIds as $postId) {
    wp_delete_post($postId, true);
}

global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('rrze_notices_dismissed_') . '%'
    )
);


