<?php

declare(strict_types=1);

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\Admin\AdminBar;
use RRZE\Notices\Admin\Dismiss;
use RRZE\Notices\Admin\Settings;
use RRZE\Notices\CPT\CPTNotice;
use RRZE\Notices\CPT\Cron;
use RRZE\Notices\CPT\Post;
use RRZE\Notices\CPT\Registration;
use RRZE\Notices\Display\Display;

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

        if (Helper::isPluginActiveForNetwork()) {
            $settings = new Settings;
            $settings->registerHooks();
            if (!$networkOptions->enabled) {
                return;
            }
        }

        Registration::registerHooks();

        $adminBar = new AdminBar;
        $adminBar->registerHooks();

        $display = new Display;
        $display->registerHooks();

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

        $assetFile = include(RRZE_NOTICES_DIR . 'build/admin.asset.php');
        wp_enqueue_style(
            'rrze-notices-admin',
            plugins_url('build/admin.style.css', RRZE_NOTICES_FILE),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? RRZE_NOTICES_VERSION
        );
        wp_enqueue_script(
            'rrze-notices-dismiss',
            plugins_url('build/dismiss.js', RRZE_NOTICES_FILE),
            [],
            $assetFile['version'] ?? RRZE_NOTICES_VERSION,
            true
        );
        wp_localize_script('rrze-notices-dismiss', 'rrzeNoticesDismiss', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rrze_notices_dismiss'),
        ]);

        $isNoticeEditor = in_array($hook, ['post.php', 'post-new.php'], true)
            && $post
            && $post->post_type === CPTNotice::POST_TYPE;

        if ($isNoticeEditor) {
            $assetFile = include(RRZE_NOTICES_DIR . 'build/cmb2.asset.php');
            wp_enqueue_script(
                'rrze-notices-cmb2',
                plugins_url('build/cmb2.js', RRZE_NOTICES_FILE),
                $assetFile['dependencies'] ?? [],
                RRZE_NOTICES_VERSION,
                true
            );
            wp_localize_script('rrze-notices-cmb2', 'rrzeNoticesCmb2', [
                'clearLabel' => __('Clear', 'rrze-notices'),
            ]);
        }

        $isNoticeScreen = $isNoticeEditor
            || (
                $hook === 'edit.php'
                && isset($_GET['post_type'])
                && $_GET['post_type'] === CPTNotice::POST_TYPE
            );

        if ($isNoticeScreen) {
            wp_enqueue_script(
                'rrze-notices-post-status',
                plugins_url('build/post-status.js', RRZE_NOTICES_FILE),
                [],
                $assetFile['version'] ?? RRZE_NOTICES_VERSION,
                true
            );
            wp_localize_script('rrze-notices-post-status',
                'rrzeNoticesPostStatus', [
                    'statusValue' => Post::EXPIRED_POST_STATUS,
                    'statusLabel' => _x('Expired', 'custom post status', 'rrze-notices'),
                    'currentStatus' => $post ? $post->post_status : '',
                ]);
        }
    }
}
