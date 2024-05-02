<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Main
{
    /**
     * @var Main
     */
    private static $instance;

    /**
     * @var Options
     */
    private $siteOptions;

    /**
     * @return Main
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Main constructor.
     */
    public function __construct()
    {
        $this->siteOptions = Options::getSiteOptions();

        if (Utils::isPluginActiveForNetwork()) {
            $settings = new Settings;
            $settings->loaded();
            if (!$this->siteOptions->enabled) {
                return;
            }
        }

        $cpt = new CPT;
        $cpt->loaded();

        $display = new Display;
        $display->loaded();

        $adminBar = new AdminBar;
        $adminBar->loaded();

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
    }

    public function __clone()
    {
        trigger_error('Cloning is not allowed.', E_USER_ERROR);
    }

    public function __wakeup()
    {
        trigger_error('Unserialize is forbidden.', E_USER_ERROR);
    }

    public function adminEnqueueScripts($hook)
    {
        global $post;

        $assetFile = include(plugin()->getPath('build') . 'admin.asset.php');
        wp_enqueue_style(
            'rrze-notices-admin',
            plugins_url('build/admin.style.css', plugin()->getBasename()),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? plugin()->getVersion()
        );

        if ('post.php' == $hook && $post && 'notice' == $post->post_type) {
            $assetFile = include(plugin()->getPath('build') . 'cmb2.asset.php');
            wp_enqueue_script(
                'rrze-notices-cmb2',
                plugins_url('build/cmb2.js', plugin()->getBasename()),
                $assetFile['dependencies'] ?? [],
                $assetFile['version'] ?? plugin()->getVersion(),
                true
            );
        }
    }
}
