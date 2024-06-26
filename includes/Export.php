<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use function RRZE\notices\plugin;


class Export
{
    /**
     * The iCal formatted calendar data
     *
     * @var string
     */
    private $vcalendar = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'request']);
    }

    /**
     * Handle the request
     */
    public function request()
    {
        $plugin = $_GET['ical-source'] ?? null;
        $postId = $_GET['id'] ?? null;
        $action = $_GET['action'] ?? null;
        $post = get_post(absint($postId));

        if (
            $post &&
            $action === 'export' &&
            $plugin === plugin()->getSlug()

        ) {
            $this->set($post);
            $this->stream();
        }
    }

    /**
     * Set the notice data
     *
     * @param object $post
     */
    private function set(object $post)
    {
        if (
            is_a($post, 'WP_Post') &&
            $post->post_type === CPTNotice::POST_TYPE &&
            $post->post_status === 'publish'
        ) {
            $postId = $post->ID;
            $data = $this->getEvent($postId);
            self::build(@$data);
        }
    }

    /**
     * Get the event data for the notice
     *
     * @param int $postId
     * @return array
     */
    private function getEvent(int $postId)
    {
        $data = [];
        $notice = get_post_meta($postId, 'notice_data', true);
        if (empty($notice) || empty($notice['event'])) {
            return $data;
        }

        $notice = (object) $notice;
        $event = (object) $notice->event;

        $repeat = $event->repeat ?? null;
        $occurrences = $event->occurrences ?? null;
        if ($repeat && $occurrences) {
            foreach ($occurrences as $occurrence) {
                $tsOccurrence = strtotime($occurrence);
                $startDate = date('Y-m-d H:i:s', $tsOccurrence);
                $tsStart = strtotime($event->start);
                $tsEnd = strtotime($event->end);
                $endDate = date('Y-m-d H:i:s', $tsOccurrence + ($tsEnd - $tsStart));
                $data[] = self::getIcalData($event->uid, $startDate, $endDate, $notice, $event);
            }
        } else {
            $startDate = $event->start;
            $endDate = $event->end;
            $data[] = [self::getIcalData($event->uid, $startDate, $endDate, $notice, $event)];
        }

        return $data;
    }

    /**
     * Get the iCal data for the notice
     *
     * @param string $eventUid
     * @param string $startDate
     * @param string $endDate
     * @param object $notice
     * @param object $event
     * @return array
     */
    private static function getIcalData(
        string $eventUid,
        string $startDate,
        string $endDate,
        object $notice,
        object $event
    ): array {
        return self::makeIcalData(
            $eventUid,
            $startDate,
            $endDate,
            $notice,
            $event
        );
    }

    /**
     * Make the iCal data for the notice
     *
     * @param string $eventUid
     * @param string $startDate
     * @param string $endDate
     * @param object $notice
     * @param object $event
     * @return array
     */
    private static function makeIcalData(
        string $eventUid,
        string $startDate,
        string $endDate,
        object $notice,
        object $event
    ): array {
        $allDay = $event->all_day;
        $multiDay = $event->multiday;
        $tsStart = strtotime($startDate);
        $tsEnd = strtotime($endDate);
        $startDay = date('Y-m-d', $tsStart);
        $endDay = date('Y-m-d', $tsEnd);
        // Multiday event
        if ($multiDay == 'on' && $startDay != $endDay) {
            // All day event
            if ($allDay == 'on') {
                $startDate = date('Y-m-d', $tsStart);
                $endDate = date('Y-m-d', $tsEnd);
                // Timed event
            } else {
                $startDate = date('Y-m-d H:i:s', $tsStart);
                $endDate = date('Y-m-d H:i:s', $tsEnd);
            }
            // Single day event
        } else {
            // All day event
            if ($allDay == 'on') {
                $startDate = date('Y-m-d', $tsStart);
                $endDate = $endDay == $startDay ? date('Y-m-d', $tsStart) : date('Y-m-d', $tsEnd);
                // Timed event
            } else {
                $startDate = date('Y-m-d H:i:s', $tsStart);
                $endDate = date('Y-m-d H:i:s', $tsEnd);
            }
        }

        return [
            'summary' => $notice->title,
            'uid' => $eventUid ?: '',
            'description' => $notice->content ?: '',
            'dtstart' => $startDate,
            'dtend' => $endDate,
            'location' => ''
        ];
    }

    /**
     * Get the event data for the notice
     *
     * @param int $postId
     * @return array
     */
    public static function getEventData($postId)
    {
        return Event::makeData($postId);
    }

    /**
     * Build the iCal formatted calendar data
     *
     * @param array $data
     */
    private function build(array $data)
    {
        // Setup calendar
        $ics = new ICal($data);

        // Output .ics formatted text
        $this->vcalendar = $ics->build();
    }

    private function stream()
    {
        $urlHost = parse_url(site_url(), PHP_URL_HOST);
        $urlPath = parse_url(site_url(), PHP_URL_PATH);
        $filename = sprintf(
            '%1$s%2$s.ics',
            sanitize_title($urlHost),
            $urlPath ? '-' . sanitize_title($urlPath) : ''
        );

        header('Content-Type: text/calendar; charset=' . get_option('blog_charset'), true);
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $this->vcalendar;
        exit;
    }

    public static function makeIcsLink(int $postId)
    {
        $args = [
            'ical-source' => plugin()->getSlug(),
            'id' => $postId,
            'action' => 'export'
        ];

        return add_query_arg($args, site_url());
    }
}
