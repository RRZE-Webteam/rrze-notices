<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Bootstraps plugin components after requirements and network settings pass.
 */
class Main
{
    /**
     * Prevent direct construction; use bootstrap().
     */
    private function __construct()
    {
    }

    /**
     * Initialize enabled plugin components and register their hooks.
     *
     * @return self Bootstrapped plugin coordinator.
     */
    public static function bootstrap(): self
    {
        $instance = new self();
        $instance->registerComponents();

        return $instance;
    }

    /**
     * Register all enabled plugin components.
     *
     * @return void
     */
    private function registerComponents(): void
    {
        $networkOptions = Options::getNetworkOptions();

        if (Utils::isPluginActiveForNetwork()) {
            $settings = new Settings;
            $settings->registerHooks();
            if (!$networkOptions->enabled) {
                return;
            }
        }

        CPT::registerHooks();

        $adminBar = new AdminBar;
        $adminBar->registerHooks();

        $display = new Display;
        $display->registerHooks();

        $export = new Export();
        $export->registerHooks();

        Cron::registerHooks();
        Dismiss::registerHooks();

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue scripts and styles for the admin area
     *
     * @param string $hook Current admin page hook suffix.
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void
    {
        global $post;

        $assetFile = include(plugin()->getPath('build') . 'admin.asset.php');
        wp_enqueue_style(
            'rrze-notices-admin',
            plugins_url('build/admin.style.css', plugin()->getBasename()),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? plugin()->getVersion()
        );

        if ($hook === 'post.php' && $post && $post->post_type === CPTNotice::POST_TYPE) {
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
