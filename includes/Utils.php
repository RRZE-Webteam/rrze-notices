<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Utils
{
    public static function getMeta($meta, $key)
    {
        if (!isset($meta[$key][0])) {
            return '';
        } elseif (is_serialized($meta[$key][0])) {
            return unserialize($meta[$key][0]);
        } else {
            return $meta[$key][0];
        }
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

    public static function isPluginActiveForNetwork()
    {
        if (!is_multisite()) {
            return false;
        }
        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[plugin()->getBaseName()])) {
            return true;
        }
        return false;
    }

    public static function getDaysOfWeek($format = null)
    {
        $days = [];
        $daysOfWeek = self::daysOfWeek($format);
        $startOfWeek = get_option('start_of_week', 0);
        for ($i = 0; $i < 7; $i++) {
            $weekDay = ($i + $startOfWeek) % 7;
            $days[$weekDay] = $daysOfWeek[$weekDay];
        }
        return $days;
    }

    public static function daysOfWeek($format = null)
    {
        global $wp_locale;
        $daysOfWeek = [];
        switch ($format) {
            case 'rrule':
                $daysOfWeek = [
                    'monday' => 'MO',
                    'tuesday' => 'TU',
                    'wednesday' => 'WE',
                    'thursday' => 'TH',
                    'friday' => 'FR',
                    'saturday' => 'SA',
                    'sunday' => 'SU',
                ];
                break;
            case 'min':
                $daysOfWeek = [
                    0 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(0)),
                    1 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(1)),
                    2 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(2)),
                    3 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(3)),
                    4 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(4)),
                    5 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(5)),
                    6 => $wp_locale->get_weekday_initial($wp_locale->get_weekday(6)),
                ];
                break;
            case 'short':
                $daysOfWeek = [
                    0 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(0)),
                    1 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(1)),
                    2 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(2)),
                    3 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(3)),
                    4 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(4)),
                    5 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(5)),
                    6 => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(6)),
                ];
                break;
            case 'full':
            default:
                $daysOfWeek = [
                    0 => $wp_locale->get_weekday(0),
                    1 => $wp_locale->get_weekday(1),
                    2 => $wp_locale->get_weekday(2),
                    3 => $wp_locale->get_weekday(3),
                    4 => $wp_locale->get_weekday(4),
                    5 => $wp_locale->get_weekday(5),
                    6 => $wp_locale->get_weekday(6),
                ];
                break;
        }
        return $daysOfWeek;
    }

    public static function getMonthNames($format = null)
    {
        global $wp_locale;
        $monthNames = [];
        switch ($format) {
            case 'rrule':
                $monthNames = [
                    'jan' => 1,
                    'feb' => 2,
                    'mar' => 3,
                    'apr' => 4,
                    'may' => 5,
                    'jun' => 6,
                    'jul' => 7,
                    'aug' => 8,
                    'sep' => 9,
                    'oct' => 10,
                    'nov' => 11,
                    'dec' => 12,
                ];
                break;
            case 'short':
                $monthNames = [
                    0 => $wp_locale->get_month_abbrev($wp_locale->get_month('01')),
                    1 => $wp_locale->get_month_abbrev($wp_locale->get_month('02')),
                    2 => $wp_locale->get_month_abbrev($wp_locale->get_month('03')),
                    3 => $wp_locale->get_month_abbrev($wp_locale->get_month('04')),
                    4 => $wp_locale->get_month_abbrev($wp_locale->get_month('05')),
                    5 => $wp_locale->get_month_abbrev($wp_locale->get_month('06')),
                    6 => $wp_locale->get_month_abbrev($wp_locale->get_month('07')),
                    7 => $wp_locale->get_month_abbrev($wp_locale->get_month('08')),
                    8 => $wp_locale->get_month_abbrev($wp_locale->get_month('09')),
                    9 => $wp_locale->get_month_abbrev($wp_locale->get_month('10')),
                    10 => $wp_locale->get_month_abbrev($wp_locale->get_month('11')),
                    11 => $wp_locale->get_month_abbrev($wp_locale->get_month('12')),
                    12 => $wp_locale->get_month_abbrev($wp_locale->get_month('12')),
                ];
                break;
            case 'full':
            default:
                $monthNames = [
                    0 => $wp_locale->get_month('01'),
                    1 => $wp_locale->get_month('02'),
                    2 => $wp_locale->get_month('03'),
                    3 => $wp_locale->get_month('04'),
                    4 => $wp_locale->get_month('05'),
                    5 => $wp_locale->get_month('06'),
                    6 => $wp_locale->get_month('07'),
                    7 => $wp_locale->get_month('08'),
                    8 => $wp_locale->get_month('09'),
                    9 => $wp_locale->get_month('10'),
                    10 => $wp_locale->get_month('11'),
                    11 => $wp_locale->get_month('12'),
                    12 => $wp_locale->get_month('12'),
                ];
                break;
        }
        return $monthNames;
    }


    /**
     * Generates a random id.
     *
     * @param integer $length
     * @return string
     */
    public static function createId(int $length = 8): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generates a random UUID based on the post ID and website host.
     *
     * @param integer $postId
     * @return string
     */
    public static function createUid(int $postId = 0)
    {
        $host = parse_url(site_url(), PHP_URL_HOST);
        $uid = vsprintf('%s-%s-%s', str_split(self::createId(), 4));
        return sprintf('%s-%s@%s', $uid, $postId, $host);
    }

    /**
     * This function will now return true if $date is a valid date 
     * (a \DateTime object, a string representation of a date, or a timestamp), 
     * and false otherwise.
     * @param mixed $date (\DateTime|string|int)
     * @return boolean
     */
    public static function validateDate($date)
    {
        if ($date instanceof \DateTime) {
            return true;
        }

        if (is_numeric($date)) {
            try {
                new \DateTime('@' . $date);
                return true;
            } catch (\Exception $e) {
                // continue to the next check
            }
        }

        try {
            new \DateTime($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
