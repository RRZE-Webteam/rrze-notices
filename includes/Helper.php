<?php

declare(strict_types=1);

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Checks whether this plugin is network-activated.
 *
 * Used throughout the plugin to decide if network-wide features
 * (cross-site notices, network settings) are available.
 */
class Helper
{
    /**
     * Determine whether this plugin is network-activated.
     *
     * @return bool
     */
    public static function isPluginActiveForNetwork()
    {
        if (!is_multisite()) {
            return false;
        }
        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[plugin_basename(RRZE_NOTICES_FILE)])) {
            return true;
        }
        return false;
    }
}