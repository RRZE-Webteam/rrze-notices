<?php
declare(strict_types=1);

namespace RRZE\Notices\Admin;

defined('ABSPATH') || exit;

use RRZE\Notices\Options;
use RRZE\Notices\CPT\CPTNotice;
use RRZE\Notices\CPT\Post;
use RRZE\Notices\Display\NoticeId;
use RRZE\Notices\Editor\AllowedHtml;

/**
 * Read-only overview of active network-wide notices for site admins.
 *
 * Loads content from originating sites via switch_to_blog().
 * Super admins see an edit link; site admins can only read.
 */
class NetworkNotices
{
    /**
     * Register the submenu page hook.
     *
     * @return void
     */
    public static function registerHooks(): void
    {
        add_action('admin_menu', [__CLASS__, 'addSubmenuPage']);
    }

    /**
     * Add the submenu page below the notice CPT menu.
     *
     * @return void
     */
    public static function addSubmenuPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . CPTNotice::POST_TYPE,
            __('Network Notices', 'rrze-notices'),
            __('Network Notices', 'rrze-notices'),
            'manage_options',
            'rrze-notices-network',
            [__CLASS__, 'renderPage']
        );
    }

    /**
     * Render the read-only network notices page.
     *
     * @return void
     */
    public static function renderPage(): void
    {
        $networkOptions = Options::getNetworkOptions();
        $notices = self::loadActiveNotices($networkOptions['network_wide'] ??
            []);

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html__('Latest Network Notices', 'rrze-notices'));

        if (empty($notices)) {
            printf(
                '<p>%s</p>',
                esc_html__('There are no active network-wide notices.',
                    'rrze-notices')
            );
            echo '</div>';
            return;
        }

        echo '<div class="rrze-notices-network-list">';
        foreach ($notices as $notice) {
            self::renderNoticeCard($notice);
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Load all active network-wide notices via switch_to_blog().
     *
     * @param array $networkWide Network-wide entries from the network
     * option.
     * @return array<int, array<string, mixed>>
     */
    private static function loadActiveNotices(array $networkWide): array
    {
        if (empty($networkWide)) {
            return [];
        }

        $notices = [];

        foreach ($networkWide as $storedId => $entry) {
            $noticeId = NoticeId::fromString($storedId);
            if (
                !$noticeId
                || !is_array($entry)
                || empty($entry['post_id'])
                || !get_blog_status($noticeId->blogId(), 'public')
            ) {
                continue;
            }

            switch_to_blog($noticeId->blogId());

            $post = get_post((int)$entry['post_id']);
            if ($post && $post->post_status === 'publish') {
                $types = Post::getNoticeTypes();
                $type = Post::getNoticeType((int)$entry['post_id']);
                $publishedDate = strtotime($post->post_date_gmt);
                $blogName = get_bloginfo('name');
                $editLink = admin_url('post.php?post=' . $entry['post_id'] .
                    '&action=edit');

                $notices[] = [
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'type' => $type,
                    'type_label' => $types[$type] ?? $types['info'],
                    'published' => $publishedDate,
                    'blog_name' => $blogName,
                    'blog_id' => $noticeId->blogId(),
                    'edit_link' => $editLink,
                ];
            }

            restore_current_blog();
        }

        return $notices;
    }

    /**
     * Render a single notice card.
     *
     * @param array<string, mixed> $notice Notice data.
     * @return void
     */
    private static function renderNoticeCard(array $notice): void
    {
        $typeClass = 'notice-' . esc_attr($notice['type']);

        echo '<div class="rrze-notices-network-card notice ' . $typeClass . ' notice-alt">';

        // Title
        if (!empty($notice['title'])) {
            printf('<h2 class="notice-title">%s</h2>',
                esc_html($notice['title']));
        }

        // Meta line: type, source site, expiry
        echo '<p class="notice-meta">';
        if ($notice['published']) {
            printf(
                '%s: %s',
                esc_html__('Published', 'rrze-notices'),
                esc_html(wp_date(
                    get_option('date_format'),
                    $notice['published']
                ))
            );
        }
        echo '</p>';

        // Content
        echo '<div class="notice-content">';
        echo wpautop(wp_kses($notice['content'], AllowedHtml::get()));
        echo '</div>';

        // Edit link for superadmins
        if (is_super_admin()) {
            printf(
                '<p class="notice-actions"><a href="%s">%s</a></p>',
                esc_url($notice['edit_link']),
                esc_html__('Edit', 'rrze-notices')
            );
        }

        echo '</div>';
    }
}