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

        if (
            $plugin === plugin()->getSlug() &&
            $postId &&
            $action === 'export'
        ) {
            $postId = absint($postId);

            $this->set($postId);
            $this->stream();
        }
    }

    private function set(int $postId)
    {
        $post = get_post($postId);
        if (
            !$post ||
            $post->post_type !== CPT::POST_TYPE ||
            $post->post_status !== 'publish'
        ) {
            return;
        }

        $data = $this->getEvent($post);
        self::build(@$data);
    }

    private function getEvent(object $post)
    {
        $meta = get_post_meta($post->ID, '', true);
        $data = [
            'summary' => $post->post_title,
            'uid' => $meta['event_uid'][0] ?? '',
            'description' => $post->post_content ?? '',
            'dtstart' => get_gmt_from_date(date('Y-m-d H:i', $meta['event_start'][0]), 'Y-m-d H:i:s'),
            'dtend' => !empty($meta['end'][0]) ? get_gmt_from_date(date('Y-m-d H:i', $meta['end'][0]), 'Y-m-d H:i:s') : '',
            'location' => $meta['location'][0] ?? '',
        ];
        $icsMeta = !empty($meta['ics_event_meta'][0]) ? maybe_unserialize($meta['ics_event_meta'][0]) : null;
        $rrule = !empty($icsMeta['rrule']) ? maybe_unserialize($icsMeta['rrule']) : null;
        if (empty($rrule)) {
            $rrule = !empty($meta['event-rrule-args'][0]) ? json_decode($meta['event-rrule-args'][0], true) : null;
            if (!empty($rrule) && is_array($rrule)) {
                $rruleObj = new RRule($rrule);
                $rrule = $rruleObj->rfcString();
                $rruleStartPos = strpos($rrule, 'RRULE:');
                if ($rruleStartPos !== false) {
                    $rrule = substr($rrule, $rruleStartPos + strlen('RRULE:'));
                }
            }
        }
        $data['rrule'] = !empty($rrule) ? $rrule : '';

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
