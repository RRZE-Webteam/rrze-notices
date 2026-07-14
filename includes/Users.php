<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Roles;

/**
 * Provides user-role choices used by notice visibility controls.
 */
class Users
{
    /**
     * WordPress roles supported by the visibility UI.
     *
     * @var string[]
     */
    public const BUILT_IN_ROLES = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];

    /**
     * Get roles that may be targeted by a notice.
     *
     * @param bool $translated Return translated role labels when true.
     * @param bool $buildIn    Restrict results to WordPress built-in roles.
     * @return array<string, string> Role slug to display-label map.
     */
    public static function getRoles($translated = true, $buildIn = true)
    {
        $wpRoles = new WP_Roles();
        $roles = $wpRoles->get_names();
        if (true === $translated) {
            $roles = array_map('translate_user_role', $roles);
        }
        if (true === $buildIn) {
            foreach (array_flip($roles) as $k) {
                if (!in_array($k, static::BUILT_IN_ROLES)) {
                    unset($roles[$k]);
                }
            }
        }
        return $roles;
    }
}
