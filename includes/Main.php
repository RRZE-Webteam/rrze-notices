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

        if (Util::isPluginActiveForNetwork()) {
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
}
