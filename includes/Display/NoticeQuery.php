<?php

declare(strict_types=1);

namespace RRZE\Notices\Display;

defined('ABSPATH') || exit;

use RRZE\Notices\Helper;
use RRZE\Notices\CPT\CPTNotice;
use RRZE\Notices\CPT\Post;

/**
 * Finds active notices that target the current user.
 */
final class NoticeQuery
{
    /** @var array|null Cached result for the current request. */
    private ?array $cachedResult = null;

    /**
     * @param object $networkOptions Parsed network-wide plugin options.
     */
    public function __construct(private readonly array $networkOptions)
    {
    }

    /**
     * Return active local and network notices for the current user.
     *
     * @return array<int, array{id:NoticeId, notice:object, from_network:bool}>
     */
    public function findActiveForCurrentUser(): array
    {
        if ($this->cachedResult === null) {
            $this->cachedResult = array_merge(
                $this->findNetworkNotices(),
                $this->findLocalNotices()
            );
        }

        return $this->cachedResult;
    }

    /**
     * Return notices published by other public sites in the network.
     *
     * @return array<int, array{id:NoticeId, notice:object,
     * from_network:bool}>
     */
    private function findNetworkNotices(): array
    {
        if (!Helper::isPluginActiveForNetwork() ||
            empty($this->networkOptions['network_wide'])) {
            return [];
        }

        $currentBlogId = get_current_blog_id();
        $matches = [];

        foreach ($this->networkOptions['network_wide'] as $storedId => $entry) {
            $noticeId = NoticeId::fromString($storedId);
            if (
                !$noticeId
                || $noticeId->blogId() === $currentBlogId
                || !get_blog_status($noticeId->blogId(), 'public')
                || !is_array($entry)
                || empty($entry['post_id'])
            ) {
                continue;
            }

            switch_to_blog($noticeId->blogId());

            $noticeData = Post::buildNoticeData((int)$entry['post_id']);
            if ($noticeData !== null) {
                $notice = (object)$noticeData;
                if ($this->isActiveAndVisible($notice)) {
                    $matches[] = [
                        'id' => $noticeId,
                        'notice' => $notice,
                        'from_network' => true,
                    ];
                }
            }

            restore_current_blog();
        }

        return $matches;
    }

    /**
     * Return notices published on the current site.
     *
     * @return array<int, array{id:NoticeId, notice:object, from_network:bool}>
     */
    private function findLocalNotices(): array
    {
        $postIds = get_posts([
            'fields' => 'ids',
            'post_type' => CPTNotice::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);
        if (!$postIds) {
            return [];
        }

        $matches = [];
        foreach ($postIds as $postId) {
            $noticeData = Post::buildNoticeData($postId);
            if ($noticeData === null) {
                continue;
            }

            $notice = (object)$noticeData;

            if ($this->isActiveAndVisible($notice)) {
                $matches[] = [
                    'id' => NoticeId::forPost((int)$postId),
                    'notice' => $notice,
                    'from_network' => false,
                ];
            }
        }

        return $matches;
    }

    /**
     * Apply role-visibility policy.
     *
     * @param object $notice Cached notice data.
     * @return bool
     */
    private function isActiveAndVisible(object $notice): bool
    {
        return NoticeVisibility::isVisibleToCurrentUser($notice);
    }
}
