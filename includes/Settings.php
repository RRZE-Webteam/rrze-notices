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

    public function onLoaded()
    {
        add_action('network_admin_menu', [$this, 'networkAdminMenu']);
        add_action('admin_init', [$this, 'networkAdminSettings']);
        add_filter('network_admin_plugin_action_links_' . plugin()->getBasename(), [$this, 'networkAdminPluginActionLink']);
    }

    public function networkAdminPluginActionLink($links)
    {
        if (!current_user_can('manage_network_options')) {
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
        if (isset($_POST['_wpnonce']) &&  wp_verify_nonce($_POST['_wpnonce'], 'rrze_notices_network-options') && current_user_can('manage_network_options')) {
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
            [$this, 'network_page_settings']
        );
    }

    public function networkAdminSettings()
    {
        add_settings_section('rrze_notices_section', __('Notices', 'rrze-notices'), '__return_false', 'rrze_notices_network');

        add_settings_field('rrze_notices_enable', __('Enable Notices', 'rrze-notices'), array($this, 'enabledField'), 'rrze_notices_network', 'rrze_notices_section');
        add_settings_field('rrze_notices_access', __('Access', 'rrze-notices'), array($this, 'accessField'), 'rrze_notices_network', 'rrze_notices_section');
    }

    public function enabledField()
    {
    ?>
        <label>
            <input type="radio" name="rrze_notices_settings[enabled]" value="1" <?php checked('1', $this->siteOptions->enabled); ?>><?php esc_html_e('Notices is enabled.', 'rrze-notices') ?>
        </label><br>
        <label>
            <input type="radio" name="rrze_notices_settings[enabled]" value="0" <?php checked('0', $this->siteOptions->enabled); ?>><?php esc_html_e('Notices is disabled.', 'rrze-notices') ?>
        </label>
    <?php
    }

    public function accessField()
    {
        echo '<label>';
        echo '<input name="readonly" type="checkbox" value="manage_site_options" disabled checked="checked">';
        echo __('Superadmins', 'rrze-notices') . '</label>';
        echo '<br>';
        echo '<label>';
        echo '<input name="rrze_notices_settings[access]" type="checkbox" value="manage_options" ' . checked($this->siteOptions->access, 'manage_options', false) . '>';
        echo __('Administrators', 'rrze-notices') . '</label>';
    }

    public function network_page_settings()
    {
    ?>
        <div class="wrap">
            <h2><?php echo esc_html(__('Notices', 'rrze-notices')); ?></h2>

            <form method="post">
                <?php
                settings_fields('rrze_notices_network');
                do_settings_sections('rrze_notices_network');
                submit_button(esc_html__('Save Changes', 'rrze-notices'), 'primary', 'rrze_notices_settings_submit');
                ?>
            </form>

        </div>
    <?php
    }
}
