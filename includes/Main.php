<?php

declare(strict_types=1);

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\Admin\AdminBar;
use RRZE\Notices\Admin\Dismiss;
use RRZE\Notices\Admin\Settings;
use RRZE\Notices\Admin\NetworkNotices;
use RRZE\Notices\CPT\CPTNotice;
use RRZE\Notices\CPT\Cron;
use RRZE\Notices\CPT\Post;
use RRZE\Notices\CPT\Registration;
use RRZE\Notices\Display\Display;
use RRZE\Notices\Display\NoticeQuery;


/**
 * Central entry point that wires up all plugin components.
 *
 * This class decides which parts of the plugin are active based on
 * network settings (enabled, access level) and registers their
 * WordPress hooks. No other class should instantiate components
 * directly — Main is the single place where everything connects.
 *
 * Called from rrze-notices.php via Main::bootstrap() on the
 * 'plugins_loaded' hook after system requirements have passed.
 */
class Main
{
    /**
     * Private constructor — prevents creating instances with 'new Main()'.
     *
     * Use Main::bootstrap() instead. This pattern ensures the plugin
     * is only initialized once and always through the same entry point.
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
     * Wire up all plugin components based on network settings.
     *
     * Stops early if the plugin is network-activated but not enabled.
     *
     * @return void
     */
    private function registerComponents(): void
    {
        $networkOptions = Options::getNetworkOptions();

        if (Helper::isPluginActiveForNetwork()) {
            $settings = new Settings;
            $settings->registerHooks();
            if (!$networkOptions['enabled']) {
                return;
            }
        }

        if (Helper::isPluginActiveForNetwork()) {
            NetworkNotices::registerHooks();
        }

        Registration::registerHooks();

        $noticeQuery = new NoticeQuery($networkOptions);

        $adminBar = new AdminBar($noticeQuery);
        $adminBar->registerHooks();

        $display = new Display($noticeQuery);
        $display->registerHooks();

        Cron::registerHooks();
        Dismiss::registerHooks();

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue admin scripts and styles, scoped to relevant screens only.
     *
     * @param string $hook Admin page hook suffix (e.g. 'index.php', 'post.php').
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void
    {
        global $post;

        $assetPath = RRZE_NOTICES_DIR . 'build/admin.asset.php';
        $assetFile = file_exists($assetPath) ? include $assetPath : [];
        wp_enqueue_style(
            'rrze-notices-admin',
            plugins_url('build/admin.style.css', RRZE_NOTICES_FILE),
            [],
            $assetFile['version'] ?? RRZE_NOTICES_VERSION
        );

        $isNoticeEditor = in_array($hook, ['post.php', 'post-new.php'], true)
            && $post
            && $post->post_type === CPTNotice::POST_TYPE;

        $dismissAssetPath = RRZE_NOTICES_DIR . 'build/dismiss.asset.php';
        $dismissAsset = file_exists($dismissAssetPath)
            ? include $dismissAssetPath
            : [];

        wp_enqueue_script(
            'rrze-notices-dismiss',
            plugins_url('build/dismiss.js', RRZE_NOTICES_FILE),
            $dismissAsset['dependencies'] ?? [],
            $dismissAsset['version'] ?? RRZE_NOTICES_VERSION,
            true
        );

        wp_localize_script('rrze-notices-dismiss', 'rrzeNoticesDismiss', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rrze_notices_dismiss'),
        ]);


        if ($isNoticeEditor) {
            $assetFile = include(RRZE_NOTICES_DIR . 'build/cmb2.asset.php');
            wp_enqueue_script(
                'rrze-notices-cmb2',
                plugins_url('build/cmb2.js', RRZE_NOTICES_FILE),
                $assetFile['dependencies'] ?? [],
                $assetFile['version'] ?? RRZE_NOTICES_VERSION,
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
                && sanitize_key($_GET['post_type']) === CPTNotice::POST_TYPE
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
