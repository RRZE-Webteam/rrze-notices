<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class ICal
{
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
        return implode("\r\n", $rows);
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
            case 'summary':
            case 'description':
            case 'location':
                $value = ':' . $this->escStr($value, true);
                break;
            default:
                $value = ':' . $this->escStr($value);
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
     * Escape special characters.
     *
     * @param string $input
     * @param boolean $specialChars
     * @return string
     */
    private function escStr(string $input, $specialChars = false): string
    {
        if ($specialChars) {
            $input = wp_strip_all_tags($input);
            $input = preg_replace('/([\,;])/', '\\\$1', $input);
        }
        $input = str_replace("\n", "\\n", $input);
        $input = str_replace("\r", "\\r", $input);
        return $input;
    }

    /**
     * Split content lines.
     * RFC-5545 (3.1. Content Lines)
     *
     * @param string $input
     * @param integer $lineLimit
     * @return string
     */
    private function split(string $input, int $lineLimit = 70): string
    {
        $output = '';
        $line = '';
        $pos = 0;

        while ($pos < mb_strlen($input)) {
            // Find newlines
            $newLinepos = mb_strpos($input, "\n", $pos + 1);
            if (!$newLinepos) {
                $newLinepos = mb_strlen($input);
            }
            $line = mb_substr($input, $pos, $newLinepos - $pos);

            if (mb_strlen($line) <= $lineLimit) {
                $output .= $line;
            } else {
                // The line break limit of the first line is $lineLimit
                $output .= mb_substr($line, 0, $lineLimit);
                $line = mb_substr($line, $lineLimit);

                // Subsequent line break limit is $lineLimit - 1 due to leading whitespace
                $output .= "\n " . mb_substr($line, 0, $lineLimit - 1);

                while (mb_strlen($line) > $lineLimit - 1) {
                    $line = mb_substr($line, $lineLimit - 1);
                    $output .= "\n " . mb_substr($line, 0, $lineLimit - 1);
                }
            }
            $pos = $newLinepos;
        }

        return $output;
    }

    private function vtimezone()
    {
        $timezoneString = get_option('timezone_string', 'UTC'); // Default to 'UTC' if not set
        $timezone = new \DateTimeZone($timezoneString);
        $transitions = $timezone->getTransitions(time(), time() + 365 * 86400); // Get transitions for the next year

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
