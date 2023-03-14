<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Main
{
    private static $instance;

    private $siteOptions;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->siteOptions = Options::getSiteOptions();

        if (Utils::isPluginActiveForNetwork()) {
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
        wp_enqueue_style(
            'rrze-notices-admin',
            plugins_url('build/admin.css', plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );
        if ('post.php' == $hook && $post && 'notice' == $post->post_type) {
            wp_enqueue_style(
                'rrze-notices-cmb2',
                plugins_url('build/cmb2.css', plugin()->getBasename()),
                [],
                plugin()->getVersion()
            );
            wp_enqueue_script(
                'rrze-notices-cmb2',
                plugins_url('build/cmb2.js', plugin()->getBasename()),
                ['jquery'],
                plugin()->getVersion(),
                true
            );
        }
    }
}
