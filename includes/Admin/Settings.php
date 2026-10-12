<?php

declare(strict_types=1);

namespace RRZE\Notices\Admin;

defined('ABSPATH') || exit;

use RRZE\Notices\Helper;
use RRZE\Notices\Options;

/**
 * Registers and renders the multisite network settings page.
 */
class Settings
{
    /** @var string Network option key used by the plugin. */
    protected string $networkOptionName;

    /** @var array Parsed network-wide plugin options. */
    protected array $networkOptions;

    /**
     * Load the current network settings.
     */
    public function __construct()
    {
        $this->networkOptionName = Options::getNetworkOptionName();
        $this->networkOptions = Options::getNetworkOptions();
    }

    /**
     * Register network settings hooks and plugin action links.
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('network_admin_menu', [$this, 'registerNetworkAdminMenu']);
        add_action('admin_init', [$this, 'handleSettingsSubmission']);
        add_action('admin_init', [$this, 'registerNetworkSettings']);
        add_filter('network_admin_plugin_action_links_' . plugin_basename(RRZE_NOTICES_FILE), [$this, 'addNetworkSettingsLink']);
    }

    /**
     * Add a settings link to the network plugins screen.
     *
     * @param string[] $links Existing plugin action links.
     * @return string[] Updated plugin action links.
     */
    public function addNetworkSettingsLink(array $links): array
    {
        if (!is_super_admin()) {
            return $links;
        }
        return array_merge(
            $links,
            [
                sprintf(
                    '<a href="%s">%s</a>',
                    add_query_arg(
                        ['page' => 'rrze-notices'],
                        network_admin_url('settings.php')
                    ),
                    __('Settings', 'rrze-notices')
                )
            ]
        );
    }

    /**
     * Register the network settings submenu page.
     *
     * @return void
     */
    public function registerNetworkAdminMenu(): void
    {
        add_submenu_page(
            'settings.php',
            __('RRZE Notices', 'rrze-notices'),
            __('RRZE Notices', 'rrze-notices'),
            'manage_network_options',
            'rrze-notices',
            [$this, 'renderNetworkSettingsPage']
        );
    }

    /**
     * Validate and persist a submitted network-settings form.
     *
     * @return void
     */
    public function handleSettingsSubmission(): void
    {
        if (!isset($_POST['rrze_notices_settings_submit']) || !is_super_admin()) {
            return;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'rrze_notices_network-options')) {
            return;
        }

        $settings = isset($_POST['rrze_notices_settings']) && is_array($_POST['rrze_notices_settings'])
            ? wp_unslash($_POST['rrze_notices_settings'])
            : [];
        $this->networkOptions['enabled'] = !empty($settings['enabled']) ? 1 : 0;
        $this->networkOptions['access'] = ($settings['access'] ?? '') === 'manage_options'
            ? 'manage_options'
            : 'manage_network_options';
        update_site_option($this->networkOptionName, $this->networkOptions);

        wp_safe_redirect(add_query_arg(
            ['page' => 'rrze-notices', 'update' => 'updated'],
            network_admin_url('settings.php')
        ));
        exit;
    }

    /**
     * Register fields used by the network settings page.
     *
     * @return void
     */
    public function registerNetworkSettings(): void
    {
        add_settings_section('rrze_notices_section', __('General', 'rrze-notices'), '__return_false', 'rrze_notices_network');

        add_settings_field('rrze_notices_enable', __('Enable Notices', 'rrze-notices'), [$this, 'renderEnabledField'], 'rrze_notices_network', 'rrze_notices_section');
        add_settings_field('rrze_notices_access', __('Access', 'rrze-notices'), [$this, 'renderAccessField'], 'rrze_notices_network', 'rrze_notices_section');
    }

    /**
     * Render the checkbox that enables notices network-wide.
     *
     * @return void
     */
    public function renderEnabledField(): void
    {
        echo '<fieldset>';
        echo '<label>';
        echo '<input type="radio" name="rrze_notices_settings[enabled]" value="1" ', checked('1', $this->networkOptions['enabled']), '>', esc_html_e('Notices is enabled.', 'rrze-notices');
        echo '</label><br>';
        echo '<label>';
        echo '<input type="radio" name="rrze_notices_settings[enabled]" value="0" ', checked('0', $this->networkOptions['enabled']), '>', esc_html_e('Notices is disabled.', 'rrze-notices');
        echo '</label>';
        echo '</fieldset>';
    }

    /**
     * Render the capability selection for notice management.
     *
     * @return void
     */
    public function renderAccessField(): void
    {
        echo '<fieldset>';
        echo '<label>';
        echo '<input name="readonly" type="checkbox" value="manage_network_options" disabled checked="checked">';
        echo __('Superadmins (only networkwide notices)', 'rrze-notices');
        echo '</label><br>';
        echo '<label>';
        echo '<input name="rrze_notices_settings[access]" type="checkbox" value="manage_options" ', checked($this->networkOptions['access'], 'manage_options', false), '>';
        echo __('Administrators (Webmaster can publish notices on their own website)', 'rrze-notices');
        echo '</label>';
        echo '</fieldset>';
    }

    /**
     * Render the complete network settings page.
     *
     * @return void
     */
    public function renderNetworkSettingsPage(): void
    {
        echo '<div class="wrap">';
        echo '<h2>', esc_html(__('RRZE Notices Network Settings', 'rrze-notices')), '</h2>';
        echo '<form method="post">';
        settings_fields('rrze_notices_network');
        do_settings_sections('rrze_notices_network');
        submit_button(esc_html__('Save Changes', 'rrze-notices'), 'primary', 'rrze_notices_settings_submit');
        echo '</form>';
        echo '</div>';
    }
}
