<?php

declare(strict_types=1);

namespace RRZE\Notices\Display;

defined('ABSPATH') || exit;

use RRZE\Notices\Admin\Users;

/**
 * Evaluates whether a notice targets the current user.
 */
final class NoticeVisibility
{
    /**
     * Determine whether the current user may see a notice.
     *
     * Network administrators always see notices so they can administer them.
     *
     * @param array<string, mixed>|object $notice Cached notice data.
     * @return bool
     */
    public static function isVisibleToCurrentUser(array|object $notice): bool
    {
        if (is_super_admin()) {
            return true;
        }

        return self::allowsRoles($notice, wp_get_current_user()->roles);
    }

    /**
     * Determine whether any supplied role is targeted by a notice.
     *
     * @param array<string, mixed>|object $notice    Cached notice data.
     * @param string[]                   $userRoles User role slugs.
     * @return bool
     */
    public static function allowsRoles(array|object $notice, array $userRoles): bool
    {
        $notice = (object) $notice;
        $configuredRoles = $notice->visibility ?? [];
        $configuredRoles = is_array($configuredRoles) ? $configuredRoles : [$configuredRoles];

        $knownRoles = array_fill_keys(array_keys(Users::getRoles()), true);
        $knownRoles['all'] = true;
        $targetRoles = array_values(array_filter(
            $configuredRoles,
            static fn ($role): bool => is_string($role) && isset($knownRoles[$role])
        ));

        return in_array('all', $targetRoles, true)
            || array_intersect($userRoles, $targetRoles) !== [];
    }
}
