<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Roles;

class Users
{
    /**
     * Built-in roles
     */
    const BUILT_IN_ROLES = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];

    /**
     * Get translated user roles names
     *
     * @param boolean $translated
     * @param boolean $buildIn
     * @return array
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
