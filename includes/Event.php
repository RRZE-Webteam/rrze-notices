<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Builds normalized event data and evaluates timezone-aware event boundaries.
 *
 * Event wall times are interpreted in the timezone stored with the event. Timed
 * boundaries are converted to absolute timestamps only for comparison/export;
 * all-day end dates remain inclusive in the editor and exclusive at boundaries.
 */
class Event
{
    /**
     * Reinterpret CMB2's UTC-based timestamp as wall time in the site timezone.
     *
     * @param int           $timestamp CMB2 timestamp representing selected wall time.
     * @param \DateTimeZone $timezone  Timezone in which the value was selected.
     * @return \DateTimeImmutable|null Local date-time, or null for an empty timestamp.
     */
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
     *
     * @param array<string, mixed>|object $event Normalized event data.
     * @return \DateTimeZone Stored event timezone or the current site timezone.
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


}
