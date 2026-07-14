<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Builds and synchronizes denormalized notice payloads.
 */
final class NoticeCache
{
    /**
     * Rebuild the cached payload stored with a local notice.
     *
     * @param int $postId Notice post ID.
     * @return void
     */
    public static function rebuildLocal(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        update_post_meta($postId, 'notice_data', self::buildPayload($postId, $post));
    }

    /**
     * Synchronize a notice into the network-wide cache.
     *
     * @param int $postId Notice post ID.
     * @return void
     */
    public static function syncNetwork(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== CPTNotice::POST_TYPE) {
            return;
        }

        $networkOptions = Options::getNetworkOptions();
        $noticeId = (string) NoticeId::forPost($postId);
        $networkOptions->network_wide[$noticeId] = self::buildPayload($postId, $post);

        update_site_option(Options::getNetworkOptionName(), $networkOptions);
    }

    /**
     * Remove a notice from the network-wide cache.
     *
     * @param int $postId Notice post ID.
     * @return void
     */
    public static function removeNetwork(int $postId): void
    {
        $networkOptions = Options::getNetworkOptions();
        $noticeId = (string) NoticeId::forPost($postId);
        if (!isset($networkOptions->network_wide[$noticeId])) {
            return;
        }

        unset($networkOptions->network_wide[$noticeId]);
        update_site_option(Options::getNetworkOptionName(), $networkOptions);
    }

    /**
     * Build the payload consumed by display, admin-bar, and export code.
     *
     * @param int      $postId Notice post ID.
     * @param \WP_Post $post   Notice post object.
     * @return array<string, mixed>
     */
    private static function buildPayload(int $postId, object $post): array
    {
        return [
            'title' => $post->post_title,
            'content' => $post->post_content,
            'type' => Post::getNoticeType($postId),
            'hide_title' => Post::shouldHideTitle($postId),
            'visibility' => Post::getVisibilityRoles($postId),
            'network_wide' => Post::isNetworkWide($postId),
            'notice_start' => get_post_meta($postId, 'notice_start', true),
            'subscription_link' => Export::makeIcsLink($postId),
            'admin_link' => admin_url('post.php?post=' . esc_attr($postId) . '&action=edit'),
            'event' => Event::buildEventData($postId),
        ];
    }
}
