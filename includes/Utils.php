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
     * Generate a stable iCalendar UID for a notice or one of its occurrences.
     *
     * Calendar clients use the UID to update an imported event. A random value
     * would create a duplicate whenever the same notice is exported again.
     */
    public static function createUid(int $postId = 0, string $occurrence = ''): string
    {
        $host = (string) parse_url(site_url(), PHP_URL_HOST);
        $blogId = function_exists('get_current_blog_id') ? get_current_blog_id() : 0;
        $instance = $occurrence !== '' ? '-' . substr(hash('sha256', $occurrence), 0, 16) : '';

        return sprintf(
            'rrze-notices-%d-%d%s@%s',
            $blogId,
            $postId,
            $instance,
            $host ?: 'localhost'
        );
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
        if (empty($date)) {
            return false;
        }

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

    public static function humanTimeDiff($timestamp, $html = true)
    {
        $datetime = date(__('Y/m/d g:i:s a', 'rrze-notices'), mysql2date('U', $timestamp));
        $timeDiff = $timestamp - current_time('timestamp');

        if ($timeDiff > 0 && $timeDiff < DAY_IN_SECONDS) {
            $humanTime = sprintf(__('in %s'), human_time_diff($timestamp));
        } elseif ($timeDiff < 0) {
            $humanTime = sprintf(__('%s ago'), human_time_diff($timestamp));
        } else {
            $humanTime = '';
        }

        if (!$html) {
            return $humanTime;
        }

        if ($timeDiff < 0) {
            echo '<span class="expired">' . __('Expired', 'rrze-notices') . '</span>';
            $humanTimeSpan = '<abbr class="expired" title="' . $datetime . '">' . $humanTime . '</abbr>';
        } else {
            echo '<abbr title="' . $datetime . '">' . mysql2date(__('Y/m/d'), $timestamp) . '</abbr>';
            $humanTimeSpan = $humanTime ? '(' . $humanTime . ')' : '';
        }

        echo $humanTimeSpan;
    }
}
