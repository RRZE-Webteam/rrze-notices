<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class ICal
{
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
     * @param array<int, array<string, mixed>> $data
     */
    public function __construct(array $data)
    {
        $this->setData($data);
    }

    /**
     * @param array<int, array<string, mixed>> $data
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

    public function build(): string
    {
        $rows = array_map([$this, 'split'], $this->render());

        return implode(self::CRLF, $rows) . self::CRLF;
    }

    /**
     * @return string[]
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

    private function timezone(string $timezone): \DateTimeZone
    {
        try {
            return new \DateTimeZone($timezone ?: wp_timezone_string());
        } catch (\Exception $exception) {
            return wp_timezone();
        }
    }

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
