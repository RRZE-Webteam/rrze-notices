<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRule\RRule;
use RRule\RSet;

/**
 * Class Event
 *
 * A class for managing events.
 */
class Event
{
    /**
     * Get event data
     *
     * @param int $postId
     * @return array
     */
    public static function getData(int $postId)
    {
        $meta = get_post_meta($postId);
        $allDay = Utils::getMeta($meta, 'event_all_day');
        $repeat = Utils::getMeta($meta, 'event_repeat');

        $data['event'] = [];

        $occurrences = $repeat ? self::getOccurrences($postId) : null;

        if (!empty($occurrences)) {
            $tsStart = strtotime($occurrences[0]);
            $tsStartUTC = strtotime(get_gmt_from_date(date('Y-m-d H:i:s', $tsStart)));
        } elseif ($tsStartUTC = (int) Utils::getMeta($meta, 'event_start_gmt')) {
            $tsStart = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsStartUTC)));
        } else {
            return $data;
        }

        if (!empty($occurrences)) {
            $eventStartGMT = (int) Utils::getMeta($meta, 'event_start_gmt');
            $eventEndGMT = (int) Utils::getMeta($meta, 'event_end_gmt');
            $tsEndUTC = $tsStartUTC + ($eventEndGMT - $eventStartGMT);
            $tsEnd = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsEndUTC)));
        } else {
            $tsEndUTC = (int) Utils::getMeta($meta, 'event_end_gmt');
            $tsEnd = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsEndUTC)));
        }

        $startDay = date('Y-m-d', $tsStart);
        $endDay = date('Y-m-d', $tsEnd);

        // Multiday event
        if ($startDay != $endDay) {
            // All day event
            if ($allDay == 'on') {
                $event = [
                    'readable_date' => date_i18n(get_option('date_format'), $tsStart)
                        . ' &ndash; '
                        . date_i18n(get_option('date_format'), $tsEnd),
                    'readable_time' => '',
                    'all_day' => 'on',
                    'multiday' => 'on',
                    'start' => $startDay,
                    'end' => $endDay,
                    'start_gmt' => date('Y-m-d', $tsStartUTC),
                    'end_gmt' => date('Y-m-d', $tsEndUTC),
                ];
                // Timed event
            } else {
                $event = [
                    'readable_date' => date_i18n(get_option('date_format') . ', ' . get_option('time_format'), $tsStart)
                        . ' &ndash; '
                        . date_i18n(get_option('date_format') . ', ' . get_option('time_format'), $tsEnd),
                    'readable_time' => '',
                    'all_day' => '',
                    'multiday' => 'on',
                    'start' => date('Y-m-d H:i:s', $tsStart),
                    'end' => date('Y-m-d H:i:s', $tsEnd),
                    'start_gmt' => date('Y-m-d H:i:s', $tsStartUTC),
                    'end_gmt' => date('Y-m-d H:i:s', $tsEndUTC),
                ];
            }
            // Single day event
        } else {
            // All day event
            if ($allDay == 'on') {
                $event = [
                    'readable_date' => ($endDay == $startDay ? date_i18n(get_option('date_format'), $tsStart) : date_i18n(get_option('date_format'), $tsStart)
                        . ' &ndash; '
                        . date_i18n(get_option('date_format'), $tsEnd)),
                    'readable_time' => '',
                    'all_day' => 'on',
                    'multiday' => '',
                    'start' => $startDay,
                    'end' => $endDay,
                    'start_gmt' => date('Y-m-d', $tsStartUTC),
                    'end_gmt' => date('Y-m-d', $tsEndUTC),
                ];
                // Timed event
            } else {
                $event = [
                    'readable_date' => date_i18n(get_option('date_format'), $tsStart),
                    'readable_time' => date_i18n(get_option('time_format'), $tsStart)
                        . ' &ndash; '
                        . date_i18n(get_option('time_format'), $tsEnd),
                    'all_day' => '',
                    'multiday' => '',
                    'start' => date('Y-m-d H:i:s', $tsStart),
                    'end' => date('Y-m-d H:i:s', $tsEnd),
                    'start_gmt' => date('Y-m-d H:i:s', $tsStartUTC),
                    'end_gmt' => date('Y-m-d H:i:s', $tsEndUTC),
                ];
            }
        }
        if ($event['all_day'] || $event['multiday']) {
            $event['readable_datetime'] = $event['readable_date'];
        } else {
            $event['readable_datetime'] = $event['readable_date'] . ' ' . $event['readable_time'];
        }

        $data['event'] = $event;

        if ($repeat) {
            $data['repeat'] = $repeat;
            $data['occurrences'] = $occurrences;
        }

        return $data;
    }

    /**
     * Make RRule arguments
     *
     * @param int $postId
     * @return array
     */
    public static function makeRRuleArgs($postId): array
    {
        $meta = get_post_meta($postId);

        if (Utils::getMeta($meta, 'event_repeat') !== 'on') {
            return [];
        }

        $repeatInterval = Utils::getMeta($meta, 'event_repeat_interval');
        $reapeatWeeklyInterval = Utils::getMeta($meta, 'event_repeat_weekly_interval');
        if ($repeatInterval == 'week' && !$reapeatWeeklyInterval) {
            return [];
        }

        $repeatMonthlyType = Utils::getMeta($meta, 'event_repeat_monthly_type');
        if ($repeatInterval == 'month' && !$repeatMonthlyType) {
            return [];
        }

        if (!$tsStart = (int) Utils::getMeta($meta, 'event_start')) {
            return [];
        }

        $rruleArgs = [
            'DTSTART' => date('Y-m-d H:i:s', $tsStart),
        ];

        $tsRepeatLastDate = Utils::getMeta($meta, 'event_repeat_lastdate');
        if ($tsRepeatLastDate > $tsStart) {
            $lastDate = '@' . $tsRepeatLastDate;
        } else {
            $lastDate = '@' . strtotime('+1 year');
        }
        $rruleArgs['UNTIL'] = $lastDate;

        $repeatInterval = Utils::getMeta($meta, 'event_repeat_interval');
        if ($repeatInterval == 'week') {
            $rruleArgs['FREQ'] = 'WEEKLY';
            $rruleArgs['INTERVAL'] = Utils::getMeta($meta, 'event_repeat_weekly_interval');
            if ($dows = Utils::getMeta($meta, 'event_repeat_weekly_day')) {
                $daysOfWeek = Utils::daysOfWeek('rrule');
                foreach ($dows as $i => $dow) {
                    $dows[$i] = $daysOfWeek[$dow];
                }
                $rruleArgs['BYDAY'] = $dows;
            }
        } elseif ($repeatInterval == 'month') {
            $rruleArgs['FREQ'] = 'MONTHLY';

            $months = (array) Utils::getMeta($meta, 'event_repeat_monthly_month');
            if (count($months) < 12) {
                $monthsRrule = Utils::getMonthNames('rrule');
                foreach ($months as $i => $month) {
                    $months[$i] = $monthsRrule[$month];
                }
                $rruleArgs['BYMONTH'] = $months;
            }

            $monthlyType = Utils::getMeta($meta, 'event_repeat_monthly_type');
            if ($monthlyType == 'date') {
                $rruleArgs['BYMONTHDAY'] = Utils::getMeta($meta, 'event_repeat_monthly_type_date');
            } elseif ($monthlyType == 'dow') {
                $monthlyDow = Utils::getMeta($meta, 'event_repeat_monthly_type_dow');
                $daysOfWeek = Utils::daysOfWeek('rrule');
                $rruleArgs['BYDAY'] = $daysOfWeek[$monthlyDow['day']];
                $rruleArgs['BYSETPOS'] = ($monthlyDow['daycount'] ?? 1);
            }
        }

        return $rruleArgs;
    }

    /**
     * Get event occurrences
     *
     * @param int $postId
     * @return array
     */
    private static function getOccurrences(int $postId): array
    {
        $meta = get_post_meta($postId);
        $allDay = Utils::getMeta($meta, 'event_all_day');

        $occurrences = [];

        if (!$tsStart = (int) Utils::getMeta($meta, 'event_start')) {
            return $occurrences;
        }

        if ($allDay == 'on') {
            $tsStart = strtotime(date('Y-m-d', $tsStart));
        }

        $rruleArgs = Utils::getMeta($meta, 'event_rrule_args');
        if ($rruleArgs = json_decode($rruleArgs, true)) {
            $rset = new RSet();
            $rset->addRRule($rruleArgs);
            $startTime = date('H:i:s', $tsStart);

            // Dates exceptions
            $exceptions = Utils::getMeta($meta, 'event_date_exceptions');
            if (!empty($exceptions) && is_array($exceptions)) {
                foreach ($exceptions as $exception) {
                    $date = $exception . ' ' . $startTime;
                    if (Utils::validateDate($date)) {
                        $rset->addExDate($date);
                    }
                }
            }
            // Dates additions
            $additions = Utils::getMeta($meta, 'event_date_additions');
            if (!empty($additions) && is_array($additions)) {
                foreach ($additions as $addition) {
                    $date = $addition . ' ' . $startTime;
                    if (Utils::validateDate($date)) {
                        $rset->addDate($date);
                    }
                }
            }

            $currentTime = current_time('timestamp');

            foreach ($rset->getOccurrences() as $occurrence) {
                $timestamp = $occurrence->getTimestamp();
                if ($timestamp < $currentTime) {
                    continue;
                }
                $occurrences[] = $occurrence->format('Y-m-d H:i:s');
            }
        }
        return $occurrences;
    }

    /**
     * Get human readable recurrence
     *
     * @param int $postId
     * @return string
     */
    public static function getHumanReadableRecurrence(int $postId): string
    {
        $meta = get_post_meta($postId);
        $readableRrule = '';

        if (Utils::getMeta($meta, 'event_repeat') == 'on') {
            $rruleArgs = Utils::getMeta($meta, 'event_rrule_args', true);
            if ($rruleArgs = json_decode($rruleArgs, true)) {
                $readableRrule = self::humanReadableRRule($rruleArgs);
            }
        }
        return $readableRrule;
    }

    /**
     * Get human readable rrule
     *
     * @param array $args
     * @return string
     */
    private static function humanReadableRRule(array $args): string
    {
        $opt = [
            'use_intl' => true,
            'locale' => substr(get_locale(), 0, 2),
            'date_formatter' => function ($date) {
                return $date->format(__('Y-m-d', 'rrze-notices'));
            },
            'fallback' => 'en',
            'explicit_infinite' => true,
            'include_start' => false,
            'include_until' => true,
            'custom_path' => plugin()->getPath('languages/rrule'),
        ];

        try {
            $rrule = new RRule($args);
        } catch (\Exception $e) {
            return '';
        }
        return $rrule->humanReadable($opt);
    }

    /**
     * Check if event is expired
     *
     * @param int $postId
     * @return bool
     */
    public static function isExpired(int $postId): bool
    {
        $currentTime = current_time('timestamp');
        $event = self::getData($postId);
        $occurrences = $event['occurrences'] ?? [];
        if ($occurrences) {
            $tsEnd = strtotime(end($occurrences));
        } else {
            $tsEnd = strtotime($event['event']['end']);
        }
        return $tsEnd < $currentTime;
    }
}
