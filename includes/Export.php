<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRule\RRule;
use function RRZE\notices\plugin;


class Export
{
    private $vcalendar = '';

    public function __construct()
    {
        add_action('init', [$this, 'request']);
    }

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

    private function set(object $post)
    {
        if (
            is_a($post, 'WP_Post') &&
            $post->post_type === CPTNotice::POST_TYPE &&
            $post->post_status === 'publish'
        ) {
            $postId = $post->ID;
            $data = $this->getEvent($postId, $post);
            self::build(@$data);
        }
    }

    private function getEvent(int $postId, object $post)
    {
        $meta = get_post_meta($postId);
        $repeat = Utils::getMeta($meta, 'event_repeat');

        $tsStartUTC = (int) Utils::getMeta($meta, 'event_start_gmt');
        $startDay = date('Y-m-d H:i:s', $tsStartUTC);

        $tsEndUTC = (int) Utils::getMeta($meta, 'event_end_gmt');
        $endDay = date('Y-m-d H:i:s', $tsEndUTC);

        $meta = get_post_meta($post->ID);
        $eventUid = Utils::getMeta($meta, 'event_uid');
        $repeat = Utils::getMeta($meta, 'event_repeat');

        $rrule = null;
        if ($repeat) {
            $rruleArgs = Utils::getMeta($meta, 'event_rrule_args');
            if ($rrule = json_decode($rruleArgs, true)) {
                $rruleObj = new RRule($rrule);
                $rrule = $rruleObj->rfcString();
                $rruleStartPos = strpos($rrule, 'RRULE:');
                if ($rruleStartPos !== false) {
                    $rrule = substr($rrule, $rruleStartPos + strlen('RRULE:'));
                }
            }
        }

        $data[$postId] = [
            'summary' => $post->post_title,
            'uid' => $eventUid ?: '',
            'description' => $post->post_content ?: '',
            'dtstart' => $startDay,
            'dtend' => $endDay,
            'location' => '',
            'rrule' => $rrule ?: ''
        ];

        return $data;
    }

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
