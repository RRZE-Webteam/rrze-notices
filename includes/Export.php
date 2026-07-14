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
            $this->build($data);
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
                $start = Event::occurrenceStart($event, (string) $occurrence);
                $end = Event::occurrenceEnd($event, (string) $occurrence);
                $uid = Utils::createUid($postId, $start->format('Y-m-d'));
                $data[] = self::makeIcalData($uid, $start, $end, $notice, $event);
            }
        } else {
            $uid = Utils::createUid($postId);
            $start = Event::occurrenceStart($event);
            $end = Event::occurrenceEnd($event);
            $data[] = self::makeIcalData($uid, $start, $end, $notice, $event);
        }

        return $data;
    }

    /**
     * Make the iCal data for the notice
     *
     * @param string $eventUid
     * @param \DateTimeImmutable $start
     * @param \DateTimeImmutable $end
     * @param object $notice
     * @param object $event
     * @return array
     */
    private static function makeIcalData(
        string $eventUid,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        object $notice,
        object $event
    ): array {
        $allDay = ($event->all_day ?? '') === 'on';

        return [
            'summary' => $notice->title,
            'uid' => $eventUid ?: '',
            'description' => $notice->content ?: '',
            'dtstart' => $start->format($allDay ? 'Y-m-d' : 'Y-m-d H:i:s'),
            'dtend' => $end->format($allDay ? 'Y-m-d' : 'Y-m-d H:i:s'),
            'location' => '',
            'all_day' => $allDay,
            'timezone' => Event::timezone($event)->getName(),
        ];
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
