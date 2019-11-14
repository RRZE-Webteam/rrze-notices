<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Roles;

class Util
{
    const BUILT_IN_ROLES = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];
    
    public static function getRoles($translated = true, $builIn = true)
    {
        $wpRoles = new WP_Roles();
        $roles = $wpRoles->get_names();
        if (true === $translated) {
            $roles = array_map('translate_user_role', $roles);
        }
        if (true === $builIn) {
            foreach (array_flip($roles) as $k) {
                if (!in_array($k, static::BUILT_IN_ROLES)) {
                    unset($roles[$k]);
                }
            }
        }
        return $roles;
    }

    public static function getNoticeTypes()
    {
        return [
            'info' 	  => __('Info', 'rrze-notices'),
            'warning' => __('Warning', 'rrze-notices')
        ];
    }

    public static function getExpirationIntervals()
    {
        return [
            'day' => __('1 Day', 'rrze-notices'),
            'week' => __('1 Week', 'rrze-notices'),
            'month' => __('1 Month', 'rrze-notices')
        ];
    }
    
    public static function isExpired($interval, $relative)
    {
        return (time() - strtotime('+1 ' . $interval, strtotime($relative))) > 0 ? true : false;
    }
    
    public static function arrayValInArray($needle, $haystack)
    {
        foreach ($needle as $stack) {
            if (in_array($stack, $haystack)) {
                return true;
            }
        }
        return false;
    }   
}
