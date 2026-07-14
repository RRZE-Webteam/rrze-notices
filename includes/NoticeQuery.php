<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Finds active notices that target the current user.
 */
final class NoticeQuery
{
    /**
     * @param object $networkOptions Parsed network-wide plugin options.
     */
    public function __construct(private readonly object $networkOptions)
    {
    }

    /**
     * Return active local and network notices for the current user.
     *
     * @return array<int, array{id:NoticeId, notice:object, from_network:bool}>
     */
    public function findActiveForCurrentUser(): array
    {
        return array_merge($this->findNetworkNotices(), $this->findLocalNotices());
    }

    /**
     * Return notices published by other public sites in the network.
     *
     * @return array<int, array{id:NoticeId, notice:object, from_network:bool}>
     */
    private function findNetworkNotices(): array
    {
        if (!Utils::isPluginActiveForNetwork() || empty($this->networkOptions->network_wide)) {
            return [];
        }

        $matches = [];
        foreach ($this->networkOptions->network_wide as $storedId => $noticeData) {
            $noticeId = NoticeId::fromString($storedId);
            if (
                !$noticeId
                || $noticeId->blogId() === get_current_blog_id()
                || !get_blog_status($noticeId->blogId(), 'public')
                || !is_array($noticeData)
            ) {
                continue;
            }

            $notice = (object) $noticeData;
            if ($this->isActiveAndVisible($notice)) {
                $matches[] = [
                    'id' => $noticeId,
                    'notice' => $notice,
                    'from_network' => true,
                ];
            }
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
            $noticeData = get_post_meta($postId, 'notice_data', true);
            if (!is_array($noticeData)) {
                continue;
            }

            $notice = (object) $noticeData;
            if ($this->isActiveAndVisible($notice)) {
                $matches[] = [
                    'id' => NoticeId::forPost((int) $postId),
                    'notice' => $notice,
                    'from_network' => false,
                ];
            }
        }

        return $matches;
    }

    /**
     * Apply the common event-boundary and role-visibility policies.
     *
     * @param object $notice Cached notice data.
     * @return bool
     */
    private function isActiveAndVisible(object $notice): bool
    {
        return !empty($notice->event)
            && Event::isNoticeActive($notice)
            && NoticeVisibility::isVisibleToCurrentUser($notice);
    }
}
