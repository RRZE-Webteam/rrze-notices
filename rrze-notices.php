<?php

/*
Plugin Name:     RRZE Notices
Plugin URI:      https://gitlab.rrze.fau.de/rrze-webteam/rrze-notices
Description:     Management and listing of admin notices.
Version:         3.0.3
Author:          RRZE Webteam
Author URI:      https://blogs.fau.de/webworking/
License:         GNU General Public License v2
License URI:     http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:     /languages
Text Domain:     rrze-notices
*/

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/** @var string Minimum supported PHP version. */
const RRZE_PHP_VERSION = '8.2';

/** @var string Minimum supported WordPress version. */
const RRZE_WP_VERSION = '6.5';

/**
 * Load Composer dependencies and the plugin PSR-4 autoloader.
 */
require_once __DIR__ . '/vendor/autoload.php';

// Register plugin hooks.
register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');

add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * Load translated plugin strings.
 *
 * @return void
 */
function loadTextdomain()
{
    load_plugin_textdomain('rrze-notices', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Verify the active PHP and WordPress versions.
 *
 * @return string Localized error message, or an empty string when compatible.
 */
function systemRequirements(): string
{
    global $wp_version;
    // Strip off any -alpha, -RC, -beta, -src suffixes.
    list($wpVersion) = explode('-', $wp_version);
    $phpVersion = phpversion();
    $error = '';
    if (!is_php_version_compatible(RRZE_PHP_VERSION)) {
        $error = sprintf(
            /* translators: 1: Server PHP version number, 2: Required PHP version number. */
            __('The server is running PHP version %1$s. The Plugin requires at least PHP version %2$s.', 'rrze-notices'),
            $phpVersion,
            RRZE_PHP_VERSION
        );
    } elseif (!is_wp_version_compatible(RRZE_WP_VERSION)) {
        $error = sprintf(
            /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
            __('The server is running WordPress version %1$s. The Plugin requires at least WordPress version %2$s.', 'rrze-notices'),
            $wpVersion,
            RRZE_WP_VERSION
        );
    }
    return $error;
}

/**
 * Verify requirements during plugin activation.
 *
 * @return void
 */
function activation()
{
    loadTextdomain();
    if ($error = systemRequirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                /* translators: 1: The plugin name, 2: The error string. */
                __('Plugins: %1$s: %2$s', 'rrze-notices'),
                plugin_basename(__FILE__),
                $error
            )
        );
    }
}

/**
 * Clear scheduled status jobs when the plugin is deactivated.
 *
 * @param bool $networkWide Whether WordPress is deactivating the plugin network-wide.
 * @return void
 */
function deactivation(bool $networkWide = false)
{
    if (is_multisite() && $networkWide) {
        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
            switch_to_blog((int) $siteId);
            Cron::clearSchedule();
            restore_current_blog();
        }
        return;
    }

    Cron::clearSchedule();
}

/**
 * Return the request-scoped plugin metadata instance.
 *
 * @return Plugin
 */
function plugin()
{
    static $instance;
    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }
    return $instance;
}

/**
 * Initialize the plugin after WordPress loads all active plugins.
 *
 * @return void
 */
function loaded()
{
    loadTextdomain();
    plugin()->initialize();
    if ($error = systemRequirements()) {
        add_action('admin_init', function () use ($error) {
            if (current_user_can('activate_plugins')) {
                $pluginData = get_plugin_data(plugin()->getFile());
                $pluginName = $pluginData['Name'];
                $tag = is_plugin_active_for_network(plugin()->getBasename()) ? 'network_admin_notices' : 'admin_notices';
                add_action($tag, function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' .
                            /* translators: 1: The plugin name, 2: The error string. */
                            __('Plugins: %1$s: %2$s', 'rrze-notices') .
                            '</p></div>',
                        esc_html($pluginName),
                        esc_html($error)
                    );
                });
            }
        });
        return;
    }
    Main::bootstrap();
}
