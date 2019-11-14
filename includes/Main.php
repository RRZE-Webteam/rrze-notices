<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;
use RRZE\Notices\MetaBoxes;
use RRZE\Notices\EditNotice;

/**
 * Hauptklasse (Main)
 */
class Main
{
    /**
     * Der vollständige Pfad- und Dateiname der Plugin-Datei.
     * @var string
     */
    protected $pluginFile;
    
    protected $siteOptions;
    
    /**
     * Variablen Werte zuweisen.
     * @param string $pluginFile Pfad- und Dateiname der Plugin-Datei
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
        $this->siteOptions = Options::getSiteOptions();
    }

    /**
     * Es wird ausgeführt, sobald die Klasse instanziiert wird.
     */
    public function onLoaded()
    {
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
        
        $settings = new Settings($this->pluginFile);
        $settings->onLoaded();
        if (!$this->siteOptions->enabled) {
            return;
        }
        
        $cpt = new CPT($this->pluginFile);
        $cpt->onLoaded();

        $editNotice = new EditNotice;
        $editNotice->onLoaded();

        $showNotices = new ShowNotices($this->pluginFile);
        $showNotices->onLoaded();

        $adminBar = new AdminBar($this->pluginFile);
        $adminBar->onLoaded();
    }

    public function adminEnqueueScripts()
    {
        wp_enqueue_style('rrze-notices', plugins_url('css/plugin.css', plugin_basename($this->pluginFile)));
    }
}
