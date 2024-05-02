<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRule\RRule;
use RRule\RSet;

class Event
{
    public static function getData($postId)
    {
        $meta = get_post_meta($postId);
        $allDay = Utils::getMeta($meta, 'event_all_day');
        $repeat = Utils::getMeta($meta, 'event_repeat');

        $data['event'] = [];

        if (!$tsStartUTC = (int) Utils::getMeta($meta, 'event_start_gmt')) {
            return null;
        }
        $tsStart = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsStartUTC)));
        $startDay = date('Y-m-d', $tsStart);

        $tsEndUTC = (int) Utils::getMeta($meta, 'event_end_gmt');
        $tsEnd = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsEndUTC)));
        $endDay = date('Y-m-d', $tsEnd);

        // Multiday event
        if ($startDay != $endDay) {
            // All day event
            if ($allDay == 'on') {
                $event = [
                    'date' => date_i18n(get_option('date_format'), $tsStart)
                        . ' &ndash; '
                        . date_i18n(get_option('date_format'), $tsEnd),
                    'time' => __('All Day', 'rrze-notices'),
                    'startISO' => $startDay,
                    'endISO' => $endDay,
                ];
                // Timed event
            } else {
                $event = [
                    'date' => date_i18n(get_option('date_format') . ', ' . get_option('time_format'), $tsStart)
                        . ' &ndash; '
                        . date_i18n(get_option('date_format') . ', ' . get_option('time_format'), $tsEnd),
                    'time' => '',
                    'startISO' => date_i18n('c', $tsStartUTC, true),
                    'endISO' => date_i18n('c', $tsEndUTC),
                ];
            }
            // Single day event
        } else {
            // All day event
            if ($allDay == 'on') {
                $event = [
                    'date' => ($endDay == $startDay ? date_i18n(get_option('date_format'), $tsStart) : date_i18n(get_option('date_format'), $tsStart)
                        . ' &ndash; '
                        . date_i18n(get_option('date_format'), $tsEnd)),
                    'time' => __('All Day', 'rrze-notices'),
                    'startISO' => $startDay,
                    'endISO' => $endDay,
                ];
                // Timed event
            } else {
                $event = [
                    'date' => date_i18n(get_option('date_format'), $tsStart),
                    'time' => date_i18n(get_option('time_format'), $tsStart) . ' &ndash; ' . date_i18n(get_option('time_format'), $tsEnd),
                    'startISO' => date_i18n('c', $tsStartUTC, true),
                    'endISO' => date_i18n('c', $tsEndUTC),
                ];
            }
        }
        $data['event'] = $event;

        $data['all_day'] = $allDay;

        if ($repeat) {
            $data['repeat'] = $repeat;
            $data['ocurrences'] = self::getOcurrences($postId);
        }

        return $data;
    }

    public static function makeRRuleArgs($postId): array
    {
        $meta = get_post_meta($postId);

        $repeat = Utils::getMeta($meta, 'event_repeat');
        if ($repeat !== 'on') {
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

        if (!$tsStartUTC = (int) Utils::getMeta($meta, 'event_start_gmt')) {
            return [];
        }
        $startTS = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsStartUTC)));
        $dtstart = date('Y-m-d H:i:s', $startTS);

        $rruleArgs = [
            'DTSTART' => $dtstart,
        ];

        $lastDateTS = Utils::getMeta($meta, 'event_repeat_lastdate');
        if ($lastDateTS) {
            $lastDate = '@' . $lastDateTS;
        } else {
            $lastDate = '@' . strtotime('+1 year');
        }
        $rruleArgs['UNTIL'] = $lastDate;

        $repeatInterval = Utils::getMeta($meta, 'event_repeat_interval');
        if ($repeatInterval == 'week') {
            $rruleArgs['FREQ'] = 'weekly';
            $rruleArgs['INTERVAL'] = Utils::getMeta($meta, 'event_repeat_weekly_interval');
            if ($dows = Utils::getMeta($meta, 'event_repeat_weekly_day')) {
                $daysOfWeek = Utils::daysOfWeek('rrule');
                foreach ($dows as $i => $dow) {
                    $dows[$i] = $daysOfWeek[$dow];
                }
                $rruleArgs['BYDAY'] = $dows;
            }
        } elseif ($repeatInterval == 'month') {
            $rruleArgs['FREQ'] = 'monthly';

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

    public static function getOcurrences($postId)
    {
        $ocurrences = [];
        $repeat = get_post_meta($postId, 'event_repeat', true);
        $rruleArgs = get_post_meta($postId, 'event_rrule_args', true);
        if (
            $repeat == 'on' &&
            $rruleArgs = json_decode($rruleArgs, true)
        ) {
            $rrule = new RRule($rruleArgs);
            foreach ($rrule as $occurrence) {
                $ocurrences[$occurrence->format('Y-m-d H:i:s')] = $occurrence->format('Y-m-d H:i:s');
            }
        }
        return $ocurrences;
    }

    public static function makeRRuleSet(int $postId, int $start, int $end): array
    {
        $meta = get_post_meta($postId);
        $start = date('Y-m-d', $start);
        $end = date('Y-m-d', $end);

        $rruleArgs = Utils::getMeta($meta, 'event_rrule_args');
        if ($rruleArgs = json_decode($rruleArgs, true)) {
            $rset = new RSet();
            $rset->addRRule($rruleArgs);
            $startTS = absint(Utils::getMeta($meta, 'start'));
            $startTime = date('H:i', $startTS);

            // Exceptions
            $exceptionsRaw = Utils::getMeta($meta, 'event_exceptions');
            if (!empty($exceptionsRaw)) {
                $exceptions = explode("\n", str_replace("\r", '', $exceptionsRaw));
                foreach ($exceptions as $exception) {
                    $date = $exception . ' ' . $startTime;
                    if (Utils::validateDate($date)) {
                        $rset->addExDate($date);
                    }
                }
            }
            // Additions
            $additionsRaw = Utils::getMeta($meta, 'event_additions');
            if (!empty($additionsRaw)) {
                $additions = explode("\n", str_replace("\r", '', $additionsRaw));
                foreach ($additions as $addition) {
                    $date = $addition . ' ' . $startTime;
                    if (Utils::validateDate($date)) {
                        $rset->addDate($date);
                    }
                }
            }

            $start = Utils::validateDate($start) ? $start : NULL;
            $end = Utils::validateDate($end) ? $end : NULL;
            if ($start != NULL || $end != NULL) {
                return $rset->getOccurrencesBetween($start, $end);
            } else {
                return $rset->getOccurrences();
            }
        }
        return [];
    }

    public static function getHumanReadableRecurrence($postId)
    {
        $repeat = get_post_meta($postId, 'event_repeat', true);
        $readableRrule = '';
        if ($repeat) {
            $rruleArgs = get_post_meta($postId, 'event_rrule_args', true);
            if ($rruleArgs = json_decode($rruleArgs, true)) {
                $readableRrule = self::humanReadableRecurrence($rruleArgs);
            }
        }
        return $readableRrule;
    }

    public static function humanReadableRecurrence(array $args)
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

        $rrule = new RRule($args);
        return $rrule->humanReadable($opt);
    }
}
