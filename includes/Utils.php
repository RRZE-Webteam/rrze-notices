<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Collection of shared WordPress, date, and identifier helpers.
 */
class Utils
{
    /**
     * Read a value from the array returned by get_post_meta().
     *
     * @param array<string, array<int, mixed>> $meta Post metadata array.
     * @param string                           $key  Metadata key.
     * @return mixed Unserialized metadata value, or an empty string when absent.
     */
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

    /**
     * Determine whether any needle value occurs in the haystack.
     *
     * @param array<int, mixed> $needle   Values to find.
     * @param array<int, mixed> $haystack Values to search.
     * @return bool
     */
    public static function arrayValInArray($needle, $haystack)
    {
        foreach ($needle as $stack) {
            if (in_array($stack, $haystack)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine whether this plugin is network-activated.
     *
     * @return bool
     */
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

    /**
     * Return localized weekday labels ordered by the site's week start.
     *
     * @param string|null $format Label format: full, short, min, or rrule.
     * @return array<int|string, string> Ordered weekday labels.
     */
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

    /**
     * Return localized weekday labels in their canonical order.
     *
     * @param string|null $format Label format: full, short, min, or rrule.
     * @return array<int|string, string> Weekday labels keyed by day index or name.
     */
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

    /**
     * Return localized month labels or recurrence month numbers.
     *
     * @param string|null $format Label format: full, short, or rrule.
     * @return array<int|string, int|string> Month values keyed by index or abbreviation.
     */
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
     * Generate a cryptographically random hexadecimal identifier.
     *
     * @param int $length Number of random bytes; output is twice this length.
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
     *
     * @param int    $postId    Notice post ID.
     * @param string $occurrence Stable occurrence discriminator, usually a date.
     * @return string RFC 5545-compatible globally unique identifier.
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
     * Determine whether a value can represent a valid date and time.
     *
     * @param \DateTime|string|int $date Date object, parseable string, or timestamp.
     * @return bool
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

    /**
     * Render or return a human-readable difference from the current site time.
     *
     * When HTML output is enabled, this method echoes the formatted value and
     * returns null. Otherwise it returns only the relative-time text.
     *
     * @param int|string $timestamp MySQL date string or timestamp understood by WordPress.
     * @param bool       $html      Whether to echo annotated HTML.
     * @return string|null Relative-time text when HTML is disabled.
     */
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
