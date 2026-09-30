<?php

declare(strict_types=1);

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Collection of shared WordPress, date, and identifier helpers.
 */
class Helper
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
        }
        return maybe_unserialize($meta[$key][0]);
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
        if (isset($plugins[plugin_basename(RRZE_NOTICES_FILE)])) {
            return true;
        }
        return false;
    }

    /**
     * Return localized weekday labels ordered by the site's week start.
     *
     * @param string|null $format Label format: full, short, min.
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
     * @param string|null $format Label format: full, short, min.
     * @return array<int|string, string> Weekday labels keyed by day index or name.
     */
    public static function daysOfWeek($format = null)
    {
        global $wp_locale;
        switch ($format) {
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
     * @param string|null $format Label format: full, short.
     * @return array<int|string, int|string> Month values keyed by index or abbreviation.
     */
    public static function getMonthNames($format = null)
    {
        global $wp_locale;
        $monthNames = [];
        switch ($format) {
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
                ];
                break;
        }
        return $monthNames;
    }

}
