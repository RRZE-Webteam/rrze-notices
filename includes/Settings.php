<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Settings
{
    protected $siteOptionsName;

    protected $siteOptions;

    public function __construct()
    {
        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
    }

    public function loaded()
    {
        add_action('network_admin_menu', [$this, 'networkAdminMenu']);
        add_action('admin_init', [$this, 'networkAdminSettings']);
        add_filter('network_admin_plugin_action_links_' . plugin()->getBasename(), [$this, 'networkAdminPluginActionLink']);
    }

    public function networkAdminPluginActionLink($links)
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

    public function networkAdminMenu()
    {
        if (isset($_POST['_wpnonce']) &&  wp_verify_nonce($_POST['_wpnonce'], 'rrze_notices_network-options') && is_super_admin()) {
            if (isset($_POST['rrze_notices_settings_submit'])) {
                $settings = isset($_POST['rrze_notices_settings']) ? $_POST['rrze_notices_settings'] : [];

                $this->siteOptions->enabled = !empty($settings['enabled']) ? 1 : 0;
                $this->siteOptions->access = isset($settings['access']) && $settings['access'] == 'manage_options' ? $settings['access'] : 'manage_site_options';
                update_site_option($this->siteOptionsName, $this->siteOptions);
            }

            wp_redirect(
                add_query_arg(
                    ['page' => 'rrze-notices', 'update' => 'updated'],
                    network_admin_url('settings.php')
                )
            );
            exit;
        }

        add_submenu_page(
            'settings.php',
            __('Notices', 'rrze-notices'),
            __('Notices', 'rrze-notices'),
            'manage_network_options',
            'rrze-notices',
            [$this, 'networkPageSettings']
        );
    }

    public function networkAdminSettings()
    {
        add_settings_section('rrze_notices_section', __('General', 'rrze-notices'), '__return_false', 'rrze_notices_network');

        add_settings_field('rrze_notices_enable', __('Enable Notices', 'rrze-notices'), array($this, 'enabledField'), 'rrze_notices_network', 'rrze_notices_section');
        add_settings_field('rrze_notices_access', __('Access', 'rrze-notices'), array($this, 'accessField'), 'rrze_notices_network', 'rrze_notices_section');
    }

    public function enabledField()
    {
        echo '<fieldset>';
        echo '<label>';
        echo '<input type="radio" name="rrze_notices_settings[enabled]" value="1" ', checked('1', $this->siteOptions->enabled), '>', esc_html_e('Notices is enabled.', 'rrze-notices');
        echo '</label><br>';
        echo '<label>';
        echo '<input type="radio" name="rrze_notices_settings[enabled]" value="0" ', checked('0', $this->siteOptions->enabled), '>', esc_html_e('Notices is disabled.', 'rrze-notices');
        echo '</label>';
        echo '</fieldset>';
    }

    public function accessField()
    {
        echo '<fieldset>';
        echo '<label>';
        echo '<input name="readonly" type="checkbox" value="manage_site_options" disabled checked="checked">';
        echo __('Superadmins', 'rrze-notices');
        echo '</label><br>';
        echo '<label>';
        echo '<input name="rrze_notices_settings[access]" type="checkbox" value="manage_options" ', checked($this->siteOptions->access, 'manage_options', false), '>';
        echo __('Administrators', 'rrze-notices');
        echo '</label>';
        echo '</fieldset>';
    }

    public function networkPageSettings()
    {
        echo '<div class="wrap">';
        echo '<h2>', esc_html(__('Notices Network Settings', 'rrze-notices')), '</h2>';
        echo '<form method="post">';
        settings_fields('rrze_notices_network');
        do_settings_sections('rrze_notices_network');
        submit_button(esc_html__('Save Changes', 'rrze-notices'), 'primary', 'rrze_notices_settings_submit');
        echo '</form>';
        echo '</div>';
    }
}
