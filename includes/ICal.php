<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Serializes normalized notice events as RFC 5545 iCalendar content.
 *
 * Timed values are emitted in UTC. All-day values use VALUE=DATE, which avoids
 * custom VTIMEZONE definitions and remains portable across calendar clients.
 */
class ICal
{
    /** @var string RFC 5545 content-line separator. */
    private const CRLF = "\r\n";

    /**
     * Keep folded content lines comfortably below RFC 5545's 75-octet limit.
     */
    private const LINE_LENGTH = 70;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $data = [];

    /**
     * Initialize the calendar with normalized event properties.
     *
     * @param array<int, array<string, mixed>> $data Calendar event records.
     */
    public function __construct(array $data)
    {
        $this->setData($data);
    }

    /**
     * Replace the calendar event collection, discarding invalid records.
     *
     * A valid record requires both a stable UID and a start value.
     *
     * @param array<int, array<string, mixed>> $data Calendar event records.
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = [];

        foreach ($data as $event) {
            if (!is_array($event)) {
                continue;
            }

            $properties = array_intersect_key(
                $event,
                array_flip([
                    'uid',
                    'description',
                    'dtend',
                    'dtstart',
                    'location',
                    'summary',
                    'all_day',
                    'timezone',
                ])
            );

            if (!empty($properties['uid']) && !empty($properties['dtstart'])) {
                $this->data[] = $properties;
            }
        }
    }

    /**
     * Build a complete UTF-8 iCalendar document.
     *
     * @return string CRLF-terminated iCalendar content.
     */
    public function build(): string
    {
        $rows = array_map([$this, 'split'], $this->render());

        return implode(self::CRLF, $rows) . self::CRLF;
    }

    /**
     * Render logical, unfolded iCalendar content lines.
     *
     * @return string[] Calendar content lines.
     */
    private function render(): array
    {
        $locale = strtoupper(substr(get_locale(), 0, 2));
        $host = (string) parse_url(site_url(), PHP_URL_HOST);
        $product = sprintf('-//%s//RRZE Notices//%s', $host ?: 'WordPress', $locale ?: 'EN');

        $rows = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . $product,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($this->data as $event) {
            $allDay = !empty($event['all_day']);
            $timezone = $this->timezone($event['timezone'] ?? '');

            $rows[] = 'BEGIN:VEVENT';
            $rows[] = 'UID:' . $this->escapeText((string) $event['uid']);
            $rows[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');

            if (!empty($event['summary'])) {
                $rows[] = 'SUMMARY:' . $this->escapeText((string) $event['summary']);
            }
            if (!empty($event['description'])) {
                $rows[] = 'DESCRIPTION:' . $this->escapeText((string) $event['description'], true);
            }
            if (!empty($event['location'])) {
                $rows[] = 'LOCATION:' . $this->escapeText((string) $event['location'], true);
            }

            $rows[] = $this->dateProperty('DTSTART', (string) $event['dtstart'], $allDay, $timezone);

            if (!empty($event['dtend'])) {
                $rows[] = $this->dateProperty('DTEND', (string) $event['dtend'], $allDay, $timezone);
            }

            $rows[] = 'END:VEVENT';
        }

        $rows[] = 'END:VCALENDAR';

        return $rows;
    }

    /**
     * Serialize a DTSTART or DTEND property.
     *
     * @param string        $name     Property name.
     * @param string        $value    Local wall time or all-day date.
     * @param bool          $allDay   Whether to emit a VALUE=DATE property.
     * @param \DateTimeZone $timezone Timezone used to interpret wall time.
     * @return string Serialized content line.
     */
    private function dateProperty(
        string $name,
        string $value,
        bool $allDay,
        \DateTimeZone $timezone
    ): string {
        if ($allDay) {
            $date = new \DateTimeImmutable($value, $timezone);
            return sprintf('%s;VALUE=DATE:%s', $name, $date->format('Ymd'));
        }

        $date = new \DateTimeImmutable($value, $timezone);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));

        return sprintf('%s:%s', $name, $date->format('Ymd\THis\Z'));
    }

    /**
     * Resolve a supplied timezone with a WordPress-site fallback.
     *
     * @param string $timezone IANA timezone name or numeric offset.
     * @return \DateTimeZone
     */
    private function timezone(string $timezone): \DateTimeZone
    {
        try {
            return new \DateTimeZone($timezone ?: wp_timezone_string());
        } catch (\Exception $exception) {
            return wp_timezone();
        }
    }

    /**
     * Escape an iCalendar TEXT value.
     *
     * @param string $value     Raw property value.
     * @param bool   $stripTags Remove HTML before escaping when true.
     * @return string RFC 5545-escaped text.
     */
    private function escapeText(string $value, bool $stripTags = false): string
    {
        if ($stripTags) {
            $value = wp_strip_all_tags($value, true);
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_option('blog_charset') ?: 'UTF-8');
        }

        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);

        return str_replace([',', ';'], ['\\,', '\\;'], $value);
    }

    /**
     * Fold a content line without splitting a UTF-8 sequence.
     *
     * @param string $value Unfolded content line.
     * @return string Folded content line.
     */
    private function split(string $value): string
    {
        $length = strlen($value);
        if ($length <= 75) {
            return $value;
        }

        $output = '';
        $start = 0;

        while ($start < $length) {
            $cut = mb_strcut($value, $start, self::LINE_LENGTH, 'UTF-8');
            $output .= $cut;
            $start += strlen($cut);

            if ($start < $length) {
                $output .= self::CRLF . ' ';
            }
        }

        return $output;
    }
}
