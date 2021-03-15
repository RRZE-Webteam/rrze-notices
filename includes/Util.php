<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use WP_Roles;

use function PHPSTORM_META\expectedArguments;

class Util
{
    const BUILT_IN_ROLES = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];

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

    public static function getNoticeTypes()
    {
        return [
            'info' => __('Info', 'rrze-notices'),
            'warning' => __('Warning', 'rrze-notices')
        ];
    }

    public static function getType(int $postId): string
    {
        $type = get_post_meta($postId, 'rrze_notices_type', true);
        return array_key_exists($type, Util::getNoticeTypes()) ? $type : 'info';
    }

    public static function hideTitle(int $postId): bool
    {
        if ($display = (array) get_post_meta($postId, 'rrze_notices_display', true)) {
            return in_array('hide_title', $display);
        }
        // Legacy value
        return get_post_meta($postId, 'rrze_notices_hide_title', true) ? true : false;
    }

    public static function altStyle(int $postId): bool
    {
        if ($display = (array) get_post_meta($postId, 'rrze_notices_display', true)) {
            return in_array('alt_style', $display);
        }
        // Legacy value        
        return get_post_meta($postId, 'rrze_notices_alt_style', true) ? true : false;
    }

    public static function networkWide(int $postId): int
    {
        if ($display = (array) get_post_meta($postId, 'rrze_notices_display', true)) {
            return in_array('network_wide', $display);
        }
        // Legacy value           
        return get_post_meta($postId, 'rrze_notices_network_wide', true) ? true : false;
    }

    public static function getVisibility(int $postId): array
    {
        return (array) get_post_meta($postId, 'rrze_notices_visibility', true);
    }

    public static function getExpiration(int $postId): int
    {
        $expiration = absint(get_post_meta($postId, 'rrze_notices_expiration', true));
        if ($expiration) {
            return $expiration;
        }
        // Maybe convert from legacy values
        $post = get_post($postId);
        $interval = get_post_meta($postId, 'rrze_notices_expiration', true);
        $legacyExpirationIntervals = [
            'day' => __('1 Day', 'rrze-notices'),
            'week' => __('1 Week', 'rrze-notices'),
            'month' => __('1 Month', 'rrze-notices')
        ];
        if (isset($legacyExpirationIntervals[$interval])) {
            $expiration = strtotime('+1 ' . $interval, strtotime($post->post_date));
            $expirationGmt = strtotime('+1 ' . $interval, strtotime($post->post_date_gmt));
            update_post_meta($postId, 'rrze_notices_expiration', $expiration);
            update_post_meta($postId, 'rrze_notices_expiration_gmt', $expirationGmt);
        }
        return $expiration ? $expiration : current_time('timestamp');
    }

    public static function getExpirationGmt(int $postId): int
    {
        $expirationGmt = absint(get_post_meta($postId, 'rrze_notices_expiration_gmt', true));
        return $expirationGmt ? $expirationGmt : time();
    }

    public static function dateTimeFormat(int $timestamp): string
    {
        return sprintf(
            /* translators: Expiration date string. 1: Date, 2: Time. */
            __('%1$s at %2$s'),
            /* translators: Expiration date format, see https://www.php.net/manual/datetime.format.php */
            date_i18n(_x('Y/m/d', 'expiration date format', 'rrze-notices'), $timestamp),
            /* translators: Expiration time format, see https://www.php.net/manual/datetime.format.php */
            date_i18n(_x('g:i a', 'expiration time format', 'rrze-notices'), $timestamp)
        );
    }

    public static function isExpired(int $expiration): bool
    {
        return (time() - $expiration > 0);
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
