<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class ICal
{
    // Carriage return and line feed
    const CRLF = "\r\n";

    // Length of line in bytes
    const LINE_LENGTH = 70;

    /**
     * iCalendar data array.
     *
     * @var array
     */
    private $data = [];

    /**
     * Available iCalendar properties.
     *
     * @var array
     */
    private $availableProperties = [
        'uid',
        'description',
        'dtend',
        'dtstart',
        'location',
        'summary'
    ];

    /**
     * Construct method.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->setData($data);
    }

    /**
     * Create an array with iCalendar data.
     *
     * @param array $data
     * @return void
     */
    public function setData(array $data)
    {
        foreach ($data as $key => $value) {
            $this->data[$key] = $this->setProperties($value);
        }
    }

    /**
     * Set the iCalendar properties.
     *
     * @param array $props
     * @return array
     */
    private function setProperties(array $props): array
    {
        $properties = [];
        foreach ($props as $key => $value) {
            if (in_array($key, $this->availableProperties)) {
                if (empty($value)) {
                    continue;
                }
                $properties[$key] = $this->sanitizeValue($value, $key);
            }
        }
        return $properties;
    }

    /**
     * Build the ICS file content.
     *
     * @return string
     */
    public function build(): string
    {
        $rows = array_map(
            fn ($row) => $this->split($row),
            $this->render()
        );
        return implode(self::CRLF, $rows);
    }

    /**
     * Create an array with iCalendar properties.
     *
     * @return array
     */
    private function render(): array
    {
        $locale = strtoupper(mb_substr(get_locale(), 0, 2));
        $prodid = str_replace('.', '-', parse_url(site_url(), PHP_URL_HOST)) . '//Events//' . $locale;
        $icalProps = [
            'BEGIN:VCALENDAR',
            'METHOD:PUBLISH',
            'PRODID:' . $this->split($prodid),
            'VERSION:2.0'
        ];
        $icalProps = array_merge($icalProps, $this->vtimezone());
        if (!empty($this->data)) {
            foreach ($this->data as $properties) {
                $icalProps[] = 'BEGIN:VEVENT';
                $props = [];
                foreach ($properties as $key => $value) {
                    $props[strtoupper($key)] = $value;
                }
                $props['DTSTAMP'] = $this->formatTimestamp('now');
                foreach ($props as $key => $value) {
                    $icalProps[] = "$key$value";
                }
                $icalProps[] = 'END:VEVENT';
            }
        }

        $icalProps[] = 'END:VCALENDAR';
        return $icalProps;
    }

    /**
     * Sanitize a string value.
     *
     * @param string $value
     * @param string $key
     * @return string
     */
    private function sanitizeValue(string $value, string $key = ''): string
    {
        switch ($key) {
            case 'dtend':
            case 'dtstamp':
            case 'dtstart':
                $value = $this->formatTimestamp($value);
                break;
            case 'description':
            case 'location':
                $value = ':' . $this->escStr($value, true);
                break;
            default:
                $value = ':' . $value;
        }

        return $value;
    }

    /**
     * Formats a timestamp for iCalendar.
     *
     * @param string $value The date/time to format, or 'now' for the current time.
     * @return string The formatted timestamp.
     */
    private function formatTimestamp($value): string
    {
        $timezoneString = wp_timezone_string() ?: 'UTC';
        $date = new \DateTime($value, new \DateTimeZone($timezoneString));
        return sprintf(
            ';TZID=%1$s:%2$s',
            $timezoneString,
            $date->format('Ymd\THis')
        );
    }

    /**
     * Escapes , and ; characters in text type fields.
     *
     * @param string $value The string to escape
     * @return string
     */
    private function escStr(string $value): string
    {
        $value = preg_replace('/((?<!\\\),|(?<!\\\);)/', '\\\$1', $value);
        $value = preg_replace('/((?<!\\\)\\\(?!,|;|n|N|\\\))/', '\\\\$1', $value);
        return $value;
    }

    /**
     * Splits a string into new lines if necessary.
     * RFC-5545 (3.1. Content Lines)
     *
     * @param string $value
     * @return string
     */
    private function split(string $value): string
    {
        // Newlines need to be converted to literal \n
        $value = str_replace("\n", "\\n", str_replace("\r\n", "\n", $value));

        // Get number of bytes
        $length = strlen($value);

        $output = '';

        if ($length > 75) {
            $start = 0;

            while ($start < $length) {
                $cut = mb_strcut($value, $start, self::LINE_LENGTH, 'UTF-8');
                $output .= $cut;
                $start = $start + strlen($cut);

                // Add space if not last line
                if ($start < $length) {
                    $output .= self::CRLF . ' ';
                }
            }
        } else {
            $output = $value;
        }

        return $output;
    }

    private function vtimezone()
    {
        // Default to 'UTC' if not set
        $timezoneString = get_option('timezone_string', 'UTC');

        $timezone = new \DateTimeZone($timezoneString);

        // Get transitions for the next year
        $transitions = $timezone->getTransitions(time(), time() + YEAR_IN_SECONDS);

        $props[] = "BEGIN:VTIMEZONE";
        $props[] = "TZID:" . $timezoneString;

        foreach ($transitions as $transition) {
            if ($transition['isdst']) {
                $start = new \DateTime($transition['time']);
                $props[] = "BEGIN:DAYLIGHT";
                $props[] = "DTSTART:" . $start->format('Ymd\THis');
                $props[] = "TZOFFSETFROM:" . $this->formatOffset($transition['offset'] - 3600);
                $props[] = "TZOFFSETTO:" . $this->formatOffset($transition['offset']);
                $props[] = "RRULE:FREQ=YEARLY;BYMONTH=" . $start->format('n') . ";BYDAY=-1SU";
                $props[] = "END:DAYLIGHT";
            } else {
                $start = new \DateTime($transition['time']);
                $props[] = "BEGIN:STANDARD";
                $props[] = "DTSTART:" . $start->format('Ymd\THis');
                $props[] = "TZOFFSETFROM:" . $this->formatOffset($transition['offset'] + 3600);
                $props[] = "TZOFFSETTO:" . $this->formatOffset($transition['offset']);
                $props[] = "RRULE:FREQ=YEARLY;BYMONTH=" . $start->format('n') . ";BYDAY=-1SU";
                $props[] = "END:STANDARD";
            }
        }

        $props[] = "END:VTIMEZONE";

        return $props;
    }

    private function formatOffset($offset)
    {
        $hours = intval($offset / 3600);
        $minutes = abs(intval(($offset % 3600) / 60));
        return sprintf("%+03d:%02d", $hours, $minutes);
    }
}
