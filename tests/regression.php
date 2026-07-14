<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);

$testTimezone = 'Europe/Berlin';

function get_locale(): string
{
    return 'de_DE';
}

function site_url(): string
{
    return 'https://example.test';
}

function get_current_blog_id(): int
{
    return 7;
}

function get_option(string $name): mixed
{
    return match ($name) {
        'blog_charset' => 'UTF-8',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        default => '',
    };
}

function wp_timezone_string(): string
{
    global $testTimezone;
    return $testTimezone;
}

function wp_timezone(): DateTimeZone
{
    return new DateTimeZone(wp_timezone_string());
}

function wp_strip_all_tags(string $value, bool $removeBreaks = false): string
{
    $value = strip_tags($value);
    return $removeBreaks ? preg_replace('/[\r\n\t ]+/', ' ', $value) : $value;
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
    $date = new DateTimeImmutable('@' . ($timestamp ?? time()));
    return $date->setTimezone($timezone ?? wp_timezone())->format($format);
}

function absint(mixed $value): int
{
    return abs((int) $value);
}

function add_action(string $hook, callable|array $callback): void
{
}

function translate_user_role(string $role): string
{
    return $role;
}

class WP_Roles
{
    public function get_names(): array
    {
        return [
            'subscriber' => 'Subscriber',
            'editor' => 'Editor',
            'administrator' => 'Administrator',
        ];
    }
}

function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
{
    global $testPostMeta;
    return $testPostMeta[$postId][$key] ?? ($single ? '' : []);
}

require_once dirname(__DIR__) . '/includes/Utils.php';
require_once dirname(__DIR__) . '/includes/Users.php';
require_once dirname(__DIR__) . '/includes/NoticeId.php';
require_once dirname(__DIR__) . '/includes/NoticeVisibility.php';
require_once dirname(__DIR__) . '/includes/Event.php';
require_once dirname(__DIR__) . '/includes/ICal.php';
require_once dirname(__DIR__) . '/includes/Export.php';
require_once dirname(__DIR__) . '/includes/Post.php';

use RRZE\Notices\Event;
use RRZE\Notices\Export;
use RRZE\Notices\ICal;
use RRZE\Notices\NoticeId;
use RRZE\Notices\NoticeVisibility;
use RRZE\Notices\Post;
use RRZE\Notices\Utils;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf("%s\nExpected: %s\nActual:   %s", $message, var_export($expected, true), var_export($actual, true))
        );
    }
}

function assertContainsValue(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(sprintf("%s\nMissing: %s", $message, $needle));
    }
}

$timed = new ICal([
    [
        'uid' => 'notice@example.test',
        'summary' => 'Maintenance, phase; one',
        'description' => '<p>First line</p><p>Second line</p>',
        'dtstart' => '2026-07-14 10:00:00',
        'dtend' => '2026-07-14 11:00:00',
        'timezone' => 'Europe/Berlin',
    ],
]);
$timedOutput = $timed->build();

assertContainsValue('DTSTART:20260714T080000Z', $timedOutput, 'Timed starts must be serialized in UTC.');
assertContainsValue('DTEND:20260714T090000Z', $timedOutput, 'Timed ends must be serialized in UTC.');
assertContainsValue('SUMMARY:Maintenance\, phase\; one', $timedOutput, 'ICS text must be escaped.');
assertSameValue(false, str_contains($timedOutput, 'VTIMEZONE'), 'UTC output must not contain a hand-built VTIMEZONE.');

$allDayEvent = [
    'start' => '2026-07-14',
    'end' => '2026-07-14',
    'all_day' => 'on',
    'timezone' => 'Europe/Berlin',
];
$allDayEnd = Event::occurrenceEnd($allDayEvent);
$allDay = new ICal([
    [
        'uid' => 'all-day@example.test',
        'summary' => 'All day',
        'dtstart' => '2026-07-14',
        'dtend' => $allDayEnd->format('Y-m-d'),
        'all_day' => true,
        'timezone' => 'Europe/Berlin',
    ],
]);
$allDayOutput = $allDay->build();

assertContainsValue('DTSTART;VALUE=DATE:20260714', $allDayOutput, 'All-day starts must use VALUE=DATE.');
assertContainsValue('DTEND;VALUE=DATE:20260715', $allDayOutput, 'All-day ends must be exclusive.');

$uid = Utils::createUid(42, '2026-07-14 10:00:00');
assertSameValue($uid, Utils::createUid(42, '2026-07-14 10:00:00'), 'UIDs must remain stable across exports.');
assertSameValue(false, $uid === Utils::createUid(42, '2026-07-21 10:00:00'), 'Occurrence UIDs must be distinct.');

$networkNoticeId = NoticeId::fromString('2-42');
assertSameValue('2-42', (string) $networkNoticeId, 'Network notice IDs must survive URL parsing unchanged.');
assertSameValue(2, $networkNoticeId?->blogId(), 'Network notice IDs must retain the originating site.');
assertSameValue(42, $networkNoticeId?->postId(), 'Network notice IDs must retain the post ID.');
assertSameValue(null, NoticeId::fromString('42'), 'Unqualified post IDs must not pass as network notice IDs.');

assertSameValue(
    true,
    NoticeVisibility::allowsRoles(['visibility' => ['editor']], ['editor']),
    'A notice must be visible when one configured role matches.'
);
assertSameValue(
    false,
    NoticeVisibility::allowsRoles(['visibility' => ['editor']], ['subscriber']),
    'A notice must remain hidden when configured roles do not match.'
);
assertSameValue(
    true,
    NoticeVisibility::allowsRoles(['visibility' => ['all']], []),
    'The all-users visibility option must not require a role.'
);

$testPostMeta = [
    99 => [
        'notice_display' => '',
        'rrze_notices_display' => ['hide_title', 'network_wide'],
    ],
];
assertSameValue(true, Post::shouldHideTitle(99), 'Legacy display metadata must remain readable.');
assertSameValue(true, Post::isNetworkWide(99), 'Legacy network-wide metadata must remain readable.');

$testPostMeta = [
    42 => [
        'notice_data' => [
            'title' => 'Non-recurring event',
            'content' => 'Description',
            'event' => [
                'start' => '2026-07-14 10:00:00',
                'end' => '2026-07-14 11:00:00',
                'all_day' => '',
                'timezone' => 'Europe/Berlin',
                'repeat' => '',
                'occurrences' => [],
            ],
        ],
    ],
];
$export = new Export();
$buildCalendarEvents = new ReflectionMethod(Export::class, 'buildCalendarEvents');
$exportData = $buildCalendarEvents->invoke($export, 42);

assertSameValue('Non-recurring event', $exportData[0]['summary'] ?? null, 'Non-recurring export data must not have an extra array level.');
assertSameValue('2026-07-14 10:00:00', $exportData[0]['dtstart'] ?? null, 'Non-recurring export must retain local wall time until serialization.');

$recurringNotice = [
    'notice_start' => 0,
    'event' => [
        'start' => '2026-07-14 10:00:00',
        'end' => '2026-07-14 11:00:00',
        'all_day' => '',
        'timezone' => 'Europe/Berlin',
        'occurrences' => ['2026-07-14 10:00:00', '2026-07-21 10:00:00'],
    ],
];
$duringFirstOccurrence = (new DateTimeImmutable('2026-07-14 10:30:00', wp_timezone()))->getTimestamp();
$finalEnd = Event::noticeEnd($recurringNotice);

assertSameValue(true, Event::isNoticeActive($recurringNotice, $duringFirstOccurrence), 'An ongoing occurrence must remain active.');
assertSameValue('2026-07-21 11:00:00', $finalEnd?->format('Y-m-d H:i:s'), 'Expiration must include the final occurrence duration.');
assertSameValue(
    '2026-07-14 10:00:00',
    Event::currentOrNextOccurrence($recurringNotice['event'], $duringFirstOccurrence),
    'The displayed occurrence must not advance while the current one is ongoing.'
);
$afterFirstOccurrence = (new DateTimeImmutable('2026-07-14 11:30:00', wp_timezone()))->getTimestamp();
assertSameValue(
    '2026-07-21 10:00:00',
    Event::currentOrNextOccurrence($recurringNotice['event'], $afterFirstOccurrence),
    'The displayed occurrence should advance immediately after the current one ends.'
);

$dstNotice = [
    'notice_start' => DAY_IN_SECONDS,
    'event' => [
        'start' => '2026-03-29 10:00:00',
        'end' => '2026-03-29 11:00:00',
        'all_day' => '',
        'timezone' => 'Europe/Berlin',
        'occurrences' => [],
    ],
];
$dstEventStart = Event::occurrenceStart($dstNotice['event']);
$dstNoticeStart = Event::noticeStartsAt($dstNotice);

assertSameValue('2026-03-28 10:00:00', $dstNoticeStart?->format('Y-m-d H:i:s'), 'Lead days must preserve local clock time across DST.');
assertSameValue(23 * 3600, $dstEventStart->getTimestamp() - $dstNoticeStart->getTimestamp(), 'The DST regression must cross a 23-hour local day.');

echo "All regression checks passed.\n";
