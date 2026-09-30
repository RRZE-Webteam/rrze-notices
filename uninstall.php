<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

function rrze_notices_cleanup_site(): void
{
    global $wpdb;

    // Delete notice posts including meta
    $postIds = get_posts([
        'post_type'   => 'notice',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
    ]);
    foreach ($postIds as $postId) {
        wp_delete_post($postId, true);
    }

    // Delete dismissal user meta
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'rrze_notices_dismissed_%'"
    );

    // Delete cron event
    wp_clear_scheduled_hook('rrze_notices_update_status');
}

if (is_multisite()) {
      delete_site_option('rrze_notices_site');

      $siteIds = get_sites(['fields' => 'ids', 'number' => 0]);
      foreach ($siteIds as $siteId) {
          switch_to_blog($siteId);
          rrze_notices_cleanup_site();
          restore_current_blog();
      }
  } else {
      delete_option('rrze_notices_site');
      rrze_notices_cleanup_site();
  }
