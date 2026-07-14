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
     * Make event data
     *
     * @param int $postId
     * @return array
     */
    public static function makeData(int $postId)
    {
        $meta = get_post_meta($postId);
        $allDay = Utils::getMeta($meta, 'event_all_day');
        $repeat = Utils::getMeta($meta, 'event_repeat');
        $occurrences = $repeat == 'on' ? self::getOccurrences($postId) : [];
        $timezone = wp_timezone();

        if (!empty($occurrences)) {
            $start = new \DateTimeImmutable($occurrences[0], $timezone);

            $originalStart = self::metaTimestampToDateTime(
                (int) Utils::getMeta($meta, 'event_start'),
                $timezone
            );
            $originalEnd = self::metaTimestampToDateTime(
                (int) Utils::getMeta($meta, 'event_end'),
                $timezone
            );

            if (!$originalStart || !$originalEnd) {
                return [];
            }

            $end = $start->add($originalStart->diff($originalEnd));
        } elseif ($tsStartUTC = (int) Utils::getMeta($meta, 'event_start_gmt')) {
            $start = (new \DateTimeImmutable('@' . $tsStartUTC))->setTimezone($timezone);
            $tsEndUTC = (int) Utils::getMeta($meta, 'event_end_gmt');
            $end = (new \DateTimeImmutable('@' . $tsEndUTC))->setTimezone($timezone);
        } else {
            return [];
        }

        if ($allDay === 'on') {
            $start = $start->setTime(0, 0);
            $end = $end->setTime(0, 0);
        }

        $startDay = $start->format('Y-m-d');
        $endDay = $end->format('Y-m-d');
        $multiDay = $startDay !== $endDay;
        $dateFormat = get_option('date_format');
        $timeFormat = get_option('time_format');

        if ($allDay === 'on') {
            $readableDate = wp_date($dateFormat, $start->getTimestamp(), $timezone);
            if ($multiDay) {
                $readableDate .= ' &ndash; ' . wp_date($dateFormat, $end->getTimestamp(), $timezone);
            }

            $data = [
                'readable_date' => $readableDate,
                'readable_time' => '',
                'all_day' => 'on',
                'multiday' => $multiDay ? 'on' : '',
                'start' => $startDay,
                'end' => $endDay,
            ];
        } elseif ($multiDay) {
            $data = [
                'readable_date' => wp_date($dateFormat . ', ' . $timeFormat, $start->getTimestamp(), $timezone)
                    . ' &ndash; '
                    . wp_date($dateFormat . ', ' . $timeFormat, $end->getTimestamp(), $timezone),
                'readable_time' => '',
                'all_day' => '',
                'multiday' => 'on',
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ];
        } else {
            $data = [
                'readable_date' => wp_date($dateFormat, $start->getTimestamp(), $timezone),
                'readable_time' => wp_date($timeFormat, $start->getTimestamp(), $timezone)
                    . ' &ndash; '
                    . wp_date($timeFormat, $end->getTimestamp(), $timezone),
                'all_day' => '',
                'multiday' => '',
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ];
        }

        $utc = new \DateTimeZone('UTC');
        $data['start_gmt'] = $start->setTimezone($utc)->format('Y-m-d H:i:s');
        $data['end_gmt'] = $end->setTimezone($utc)->format('Y-m-d H:i:s');
        $data['timezone'] = $timezone->getName();

        if ($data['all_day'] || $data['multiday']) {
            $data['readable_datetime'] = $data['readable_date'];
        } else {
            $data['readable_datetime'] = $data['readable_date'] . ' ' . $data['readable_time'];
        }

        $data['repeat'] = $repeat;
        $data['occurrences'] = $occurrences;

        return $data;
    }

    private static function metaTimestampToDateTime(
        int $timestamp,
        \DateTimeZone $timezone
    ): ?\DateTimeImmutable {
        if (!$timestamp) {
            return null;
        }

        // CMB2's timestamp field stores the selected wall time in PHP's UTC
        // timezone. Re-interpret that formatted value in the site timezone.
        return new \DateTimeImmutable(gmdate('Y-m-d H:i:s', $timestamp), $timezone);
    }

    /**
     * Return the timezone in which an event's wall-time values were created.
     */
    public static function timezone(array|object $event): \DateTimeZone
    {
        $event = (object) $event;
        $timezoneName = isset($event->timezone) ? (string) $event->timezone : '';

        try {
            return new \DateTimeZone($timezoneName ?: wp_timezone_string());
        } catch (\Exception $exception) {
            return wp_timezone();
        }
    }

    /**
     * Get the local start of an event or recurrence instance.
     */
    public static function occurrenceStart(
        array|object $event,
        ?string $occurrence = null
    ): \DateTimeImmutable {
        $event = (object) $event;
        $value = $occurrence ?: (string) $event->start;

        return new \DateTimeImmutable($value, self::timezone($event));
    }

    /**
     * Get the exclusive end of an event or recurrence instance.
     *
     * The editor treats an all-day end date as inclusive, while iCalendar and
     * activity checks need an exclusive boundary.
     */
    public static function occurrenceEnd(
        array|object $event,
        ?string $occurrence = null
    ): \DateTimeImmutable {
        $event = (object) $event;
        $timezone = self::timezone($event);
        $eventStart = new \DateTimeImmutable((string) $event->start, $timezone);
        $eventEnd = new \DateTimeImmutable((string) $event->end, $timezone);
        $start = self::occurrenceStart($event, $occurrence);
        $end = $start->add($eventStart->diff($eventEnd));

        if (($event->all_day ?? '') === 'on') {
            $end = $end->modify('+1 day');
        }

        return $end;
    }

    /**
     * Return whether the notice lead time has begun.
     */
    public static function hasNoticeStarted(array|object $notice, ?int $now = null): bool
    {
        $starts = self::noticeStartsAt($notice);

        return $starts && ($now ?? time()) >= $starts->getTimestamp();
    }

    public static function noticeStartsAt(array|object $notice): ?\DateTimeImmutable
    {
        $notice = (object) $notice;
        if (empty($notice->event)) {
            return null;
        }

        $event = (object) $notice->event;
        $days = max(0, (int) round(absint($notice->notice_start ?? 0) / DAY_IN_SECONDS));

        return self::occurrenceStart($event)->modify(sprintf('-%d days', $days));
    }

    /**
     * Return the exclusive end of the final recurrence instance.
     */
    public static function noticeEnd(array|object $notice): ?\DateTimeImmutable
    {
        $notice = (object) $notice;
        if (empty($notice->event)) {
            return null;
        }

        $event = (object) $notice->event;
        $occurrences = !empty($event->occurrences) && is_array($event->occurrences)
            ? $event->occurrences
            : [];
        $lastOccurrence = $occurrences ? (string) end($occurrences) : null;

        return self::occurrenceEnd($event, $lastOccurrence);
    }

    public static function isNoticeExpired(array|object $notice, ?int $now = null): bool
    {
        $end = self::noticeEnd($notice);

        return !$end || ($now ?? time()) >= $end->getTimestamp();
    }

    public static function isNoticeActive(array|object $notice, ?int $now = null): bool
    {
        $now = $now ?? time();

        return self::hasNoticeStarted($notice, $now)
            && !self::isNoticeExpired($notice, $now);
    }

    /**
     * Return the ongoing occurrence, or the next one when none is in progress.
     */
    public static function currentOrNextOccurrence(
        array|object $event,
        ?int $now = null
    ): ?string {
        $event = (object) $event;
        if (empty($event->occurrences) || !is_array($event->occurrences)) {
            return null;
        }

        $now = $now ?? time();
        foreach ($event->occurrences as $occurrence) {
            if (self::occurrenceEnd($event, (string) $occurrence)->getTimestamp() > $now) {
                return (string) $occurrence;
            }
        }

        return null;
    }

    /**
     * Format an event for the current user's locale without changing its zone.
     */
    public static function readableDatetime(array|object $event): string
    {
        $event = (object) $event;
        $timezone = self::timezone($event);
        $occurrence = self::currentOrNextOccurrence($event);
        $start = self::occurrenceStart($event, $occurrence);
        $end = $occurrence
            ? self::occurrenceEnd($event, $occurrence)
            : new \DateTimeImmutable((string) $event->end, $timezone);
        $dateFormat = get_option('date_format');
        $timeFormat = get_option('time_format');
        $allDay = ($event->all_day ?? '') === 'on';
        if ($allDay && $occurrence) {
            // occurrenceEnd() is exclusive, while the editor's display date is inclusive.
            $end = $end->modify('-1 day');
        }
        $multiDay = $start->format('Y-m-d') !== $end->format('Y-m-d');

        if ($allDay) {
            $value = wp_date($dateFormat, $start->getTimestamp(), $timezone);
            return $multiDay
                ? $value . ' &ndash; ' . wp_date($dateFormat, $end->getTimestamp(), $timezone)
                : $value;
        }

        if ($multiDay) {
            return wp_date($dateFormat . ', ' . $timeFormat, $start->getTimestamp(), $timezone)
                . ' &ndash; '
                . wp_date($dateFormat . ', ' . $timeFormat, $end->getTimestamp(), $timezone);
        }

        return wp_date($dateFormat, $start->getTimestamp(), $timezone)
            . ' '
            . wp_date($timeFormat, $start->getTimestamp(), $timezone)
            . ' &ndash; '
            . wp_date($timeFormat, $end->getTimestamp(), $timezone);
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
            'DTSTART' => gmdate('Y-m-d H:i:s', $tsStart),
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
            if (!empty($months) && is_array($months)) {
                $rruleArgs['BYMONTH'] = implode(',', $months);
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

        $tsEnd = (int) Utils::getMeta($meta, 'event_end');
        $duration = max(0, $tsEnd - $tsStart);

        if ($allDay == 'on') {
            $tsStart = strtotime(gmdate('Y-m-d', $tsStart) . ' UTC');
            $tsEnd = strtotime(gmdate('Y-m-d', $tsEnd) . ' UTC');
            // The end date selected in the editor is inclusive.
            $duration = max(0, $tsEnd - $tsStart) + DAY_IN_SECONDS;
        }

        $rruleArgs = Utils::getMeta($meta, 'event_rrule_args');
        if ($rruleArgs = json_decode($rruleArgs, true)) {
            try {
                $rset = new RSet();
                $rset->addRRule($rruleArgs);
            } catch (\Exception $e) {
                return $occurrences;
            }

            $startTime = gmdate('H:i:s', $tsStart);

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
                if (($timestamp + $duration) <= $currentTime) {
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
}
