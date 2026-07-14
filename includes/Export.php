<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use function RRZE\Notices\plugin;

/**
 * Handles public ICS download requests for published notices.
 */
class Export
{
    /**
     * Serialized iCalendar document.
     *
     * @var string
     */
    private string $calendar = '';

    /**
     * Register the export request handler.
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('init', [$this, 'handleExportRequest']);
    }

    /**
     * Detect and serve a valid notice export request during WordPress init.
     *
     * @return void
     */
    public function handleExportRequest(): void
    {
        $pluginSlug = isset($_GET['ical-source'])
            ? sanitize_key(wp_unslash($_GET['ical-source']))
            : '';
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $postId = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($action !== 'export' || $pluginSlug !== plugin()->getSlug() || !$postId) {
            return;
        }

        $post = get_post($postId);
        if ($post && $this->buildCalendarForPost($post)) {
            $this->sendCalendarDownload();
        }
    }

    /**
     * Build calendar content for a published notice post.
     *
     * @param object $post Candidate WordPress post object.
     * @return bool Whether calendar content was created.
     */
    private function buildCalendarForPost(object $post): bool
    {
        if (
            is_a($post, 'WP_Post') &&
            $post->post_type === CPTNotice::POST_TYPE &&
            $post->post_status === 'publish'
        ) {
            $postId = $post->ID;
            $events = $this->buildCalendarEvents($postId);
            if ($events !== []) {
                $this->serializeCalendar($events);
                return true;
            }
        }

        return false;
    }

    /**
     * Convert cached notice data into one or more calendar events.
     *
     * Recurrences are expanded into independent events with stable per-date
     * UIDs because the stored recurrence set may contain exceptions/additions.
     *
     * @param int $postId Notice post ID.
     * @return array<int, array<string, mixed>> Calendar event records.
     */
    private function buildCalendarEvents(int $postId): array
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
     * Create one normalized calendar event record.
     *
     * @param string             $eventUid Stable event UID.
     * @param \DateTimeImmutable $start    Start in the event timezone.
     * @param \DateTimeImmutable $end      Exclusive event end.
     * @param object             $notice   Cached notice data.
     * @param object             $event    Cached event data.
     * @return array<string, mixed> Calendar event properties.
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
     * Serialize normalized calendar records.
     *
     * @param array<int, array<string, mixed>> $data Calendar event records.
     * @return void
     */
    private function serializeCalendar(array $data): void
    {
        // Setup calendar
        $ics = new ICal($data);

        // Output .ics formatted text
        $this->calendar = $ics->build();
    }

    /**
     * Send calendar download headers and terminate the request.
     *
     * @return never
     */
    private function sendCalendarDownload(): never
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
        echo $this->calendar;
        exit;
    }

    /**
     * Build the public ICS download URL for a notice.
     *
     * @param int $postId Notice post ID.
     * @return string Export URL on the current site.
     */
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
