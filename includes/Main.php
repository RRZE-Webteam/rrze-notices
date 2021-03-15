<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;
use RRZE\Notices\EditNotice;

/**
 * Hauptklasse (Main)
 */
class Main
{
    protected $siteOptions;

    public function __construct()
    {
        $this->siteOptions = Options::getSiteOptions();
    }

    public function onLoaded()
    {
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        if ($this->isPluginActiveForNetwork()) {
            $settings = new Settings;
            $settings->onLoaded();
            if (!$this->siteOptions->enabled) {
                return;
            }
        }

        $cpt = new CPT;
        $cpt->onLoaded();

        $showNotices = new ShowNotices;
        $showNotices->onLoaded();

        $adminBar = new AdminBar;
        $adminBar->onLoaded();
    }

    public function adminEnqueueScripts($hook)
    {
        wp_enqueue_style(
            'rrze-notices-admin', 
            plugins_url('assets/css/admin.css', plugin()->getBasename()),
        );
    }

    protected function isPluginActiveForNetwork()
    {
        if (!is_multisite()) {
            return false;
        }
        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[plugin()->getBaseName()])) {
            return true;
        }
        return false;
    }
}
