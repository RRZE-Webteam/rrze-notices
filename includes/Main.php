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

    /**
     * Variablen Werte zuweisen.
     * @param string $pluginFile Pfad- und Dateiname der Plugin-Datei
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Es wird ausgeführt, sobald die Klasse instanziiert wird.
     */
    public function onLoaded()
    {
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
                
        $cpt = new CPT;
        $cpt->onLoaded();

        $metaBoxes = new MetaBoxes();
        $metaBoxes->onLoaded();

        $columns = new Columns();
        $columns->onLoaded();        

        $editNotice = new EditNotice;
        $editNotice->onLoaded();

        $showNotices = new ShowNotices;
        $showNotices->onLoaded();     

        $adminBar = new AdminBar;
        $adminBar->onLoaded();

        //$dashboard = new Dashboard;
        //$dashboard->onLoaded();
    }

    public function adminEnqueueScripts() {
        wp_enqueue_style('rrze-notices', plugins_url('css/plugin.css', plugin_basename($this->pluginFile)));        
    }
}
