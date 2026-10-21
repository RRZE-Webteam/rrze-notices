<?php

declare(strict_types=1);

namespace RRZE\Notices\Editor;

defined('ABSPATH') || exit;

/**
 * Stores notice dates as Unix timestamps and renders them in the site timezone.
 *
 * A plugin-specific CMB2 type isolates these fields from other plugins' datetime
 * filters. CMB2 still provides the date picker and escaped input markup.
 */
class DateTimeField
{
    /**
     * Parse a local date and time without relying on PHP's default timezone.
     *
     * Date-only events use local midnight, which the notice output shows without
     * a time. Expiration requires an explicit time. Invalid or empty input clears
     * the field; impossible dates and times in the spring DST gap are rejected.
     *
     * @param mixed $value Submitted CMB2 value, or an existing Unix timestamp.
     * @param array $args Field configuration, including its ID and date format.
     * @param \CMB2_Field|null $field Field containing the previously stored instant.
     * @return int|string Absolute timestamp, or an empty string for invalid input.
     */
    public static function sanitize(mixed $value, array $args, ?\CMB2_Field $field = null): int|string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int)$value > 0 ? (int)$value : '';
        }

        if (!is_array($value)) {
            return '';
        }

        $date = $value['date'] ?? '';
        $time = $value['time'] ?? '';
        if (!is_string($date) || !is_string($time)
            || str_contains($date, "\0") || str_contains($time, "\0")) {
            return '';
        }

        $date = trim(wp_unslash($date));
        $time = trim(wp_unslash($time));
        if ($date === '' || ($time === '' && $args['id'] === 'notice_visible_until')) {
            return '';
        }

        $format = $args['date_format'] . ' H:i';
        $input = $date . ' ' . ($time !== '' ? $time : '00:00');
        $local = \DateTimeImmutable::createFromFormat('!' . $format, $input, wp_timezone());
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$local || ($errors && ($errors['warning_count'] || $errors['error_count']))
            || $local->format($format) !== $input) {
            return '';
        }

        // During the autumn DST change the same wall time occurs twice. Keep the
        // original instant when its displayed date/time has not been edited.
        $previous = $field ? self::getTimestamp($field) : 0;
        if ($previous && wp_date($format, $previous) === $input) {
            return $previous;
        }

        return $local->getTimestamp();
    }

    /**
     * Read the stored instant, preferring legacy expiry's authoritative GMT key.
     *
     * @param \CMB2_Field $field Current date field.
     * @return int Stored Unix timestamp, or zero for an empty field.
     */
    private static function getTimestamp(\CMB2_Field $field): int
    {
        $timestamp = (int)$field->value();
        if ($field->id() === 'notice_visible_until') {
            $expiry = (int)get_post_meta($field->object_id, 'notice_visible_until_gmt', true);
            return $expiry ?: $timestamp;
        }
        return $timestamp;
    }

    /**
     * Render local dates with CMB2's date picker and a native, optional time input.
     *
     * Explicit display values bypass CMB2's date(), which uses PHP's timezone.
     * The native time input avoids CMB2's automatic value on closing an empty
     * time picker. Existing expiration values use the authoritative GMT metadata.
     *
     * @param \CMB2_Field $field Current date field.
     * @param mixed $escapedValue CMB2's escaped stored value.
     * @param int $objectId Notice post ID.
     * @param string $objectType CMB2 object type.
     * @param \CMB2_Types $types CMB2 input renderer.
     * @return void
     */
    public static function render(
        \CMB2_Field $field,
        mixed $escapedValue,
        int $objectId,
        string $objectType,
        \CMB2_Types $types
    ): void {
        $timestamp = self::getTimestamp($field);
        $isExpiry = $field->id() === 'notice_visible_until';

        $date = $timestamp ? wp_date($field->args('date_format'), $timestamp) : '';
        $time = $timestamp ? wp_date('H:i', $timestamp) : '';
        if (!$isExpiry && $time === '00:00') {
            $time = '';
        }

        echo $types->text_datetime_timestamp([
            'datepicker' => [
                'value' => $date,
                'js_dependencies' => ['jquery-ui-core', 'jquery-ui-datepicker'],
            ],
            'timepicker' => [
                'type' => 'time',
                'class' => 'text-time',
                'value' => $time,
                'aria-label' => $field->id() === 'notice_event_start'
                    ? __('Event Start', 'rrze-notices')
                    : ($isExpiry ? __('Visible until', 'rrze-notices') : __('Event End', 'rrze-notices')),
                'js_dependencies' => [],
            ],
        ]);
    }
}
