<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Bootstraps plugin components after requirements and network settings pass.
 */
class Main
{
    /**
     * @var Main|null Singleton instance.
     */
    private static $instance;

    /**
     * @var object Parsed network-wide plugin options.
     */
    private $siteOptions;

    /**
     * Return the singleton plugin bootstrap instance.
     *
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
     * Initialize all enabled plugin components and hooks.
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

        CPT::loaded();

        $adminBar = new AdminBar;
        $adminBar->loaded();

        $display = new Display;
        $display->loaded();

        new Export();

        Cron::init();

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
    }

    /**
     * Prevent cloning
     *
     * @return void
     */
    public function __clone()
    {
        trigger_error('Cloning is not allowed.', E_USER_ERROR);
    }

    /**
     * Prevent unserializing
     *
     * @return void
     */
    public function __wakeup()
    {
        trigger_error('Unserialize is forbidden.', E_USER_ERROR);
    }

    /**
     * Enqueue scripts and styles for the admin area
     *
     * @param string $hook Current admin page hook suffix.
     * @return void
     */
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
