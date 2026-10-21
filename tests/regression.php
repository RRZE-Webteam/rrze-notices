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

/**
 * Remove request slashes for datetime parsing tests.
 *
 * @param string $value Submitted string.
 * @return string Unslashed input.
 */
function wp_unslash(string $value): string
{
    return stripslashes($value);
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

function wp_roles(): WP_Roles
{
    return new WP_Roles();
}

require_once dirname(__DIR__) . '/includes/Helper.php';
require_once dirname(__DIR__) . '/includes/Admin/Users.php';
require_once dirname(__DIR__) . '/includes/Display/NoticeId.php';
require_once dirname(__DIR__) . '/includes/Display/NoticeVisibility.php';
require_once dirname(__DIR__) . '/includes/Editor/DateTimeField.php';

use RRZE\Notices\Display\NoticeId;
use RRZE\Notices\Display\NoticeVisibility;
use RRZE\Notices\Editor\DateTimeField;

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


$fieldArgs = ['id' => 'notice_event_start', 'date_format' => 'd.m.Y'];
$originalPhpTimezone = date_default_timezone_get();
foreach (['UTC', 'America/New_York', 'Europe/Berlin'] as $phpTimezone) {
    date_default_timezone_set($phpTimezone);
    foreach ([
        ['21.10.2026', '12:00', '2026-10-21T10:00:00Z'],
        ['21.01.2026', '12:00', '2026-01-21T11:00:00Z'],
        ['29.03.2026', '01:30', '2026-03-29T00:30:00Z'],
        ['29.03.2026', '03:30', '2026-03-29T01:30:00Z'],
        ['25.10.2026', '01:30', '2026-10-24T23:30:00Z'],
        ['25.10.2026', '03:30', '2026-10-25T02:30:00Z'],
        ['21.10.2026', '', '2026-10-20T22:00:00Z'],
    ] as [$date, $time, $expected]) {
        $actual = DateTimeField::sanitize(['date' => $date, 'time' => $time], $fieldArgs);
        assertSameValue(
            (new DateTimeImmutable($expected))->getTimestamp(),
            $actual,
            'Date input must use the site timezone and reset seconds, independently of PHP timezone.'
        );
    }
}
date_default_timezone_set($originalPhpTimezone);

foreach ([
    ['date' => '', 'time' => ''],
    ['date' => '', 'time' => '12:00'],
    ['date' => '31.02.2026', 'time' => '12:00'],
    ['date' => '21.10.2026', 'time' => '24:00'],
    ['date' => '29.03.2026', 'time' => '02:30'],
    ['date' => ['21.10.2026'], 'time' => '12:00'],
    ['date' => '21.10.2026', 'time' => ['12:00']],
    ['date' => "21.10.2026\0", 'time' => '12:00'],
    null,
    '',
] as $input) {
    assertSameValue('', DateTimeField::sanitize($input, $fieldArgs), 'Empty or invalid input must not invent a timestamp.');
}

$expiryArgs = ['id' => 'notice_visible_until', 'date_format' => 'd.m.Y'];
assertSameValue('', DateTimeField::sanitize(['date' => '21.10.2026', 'time' => ''], $expiryArgs), 'Expiry requires an explicit time.');
assertSameValue(
    1792533600,
    DateTimeField::sanitize(['date' => '21.10.2026', 'time' => '00:00'], $expiryArgs),
    'Explicit midnight remains a valid expiry.'
);
assertSameValue(1792576800, DateTimeField::sanitize('1792576800', $fieldArgs), 'Existing timestamps must not receive another offset.');

$testTimezone = 'UTC';
assertSameValue(1792584000, DateTimeField::sanitize(['date' => '21.10.2026', 'time' => '12:00'], $fieldArgs), 'UTC sites must retain their own timezone.');
$testTimezone = 'Asia/Kolkata';
assertSameValue(1792564200, DateTimeField::sanitize(['date' => '21.10.2026', 'time' => '12:00'], $fieldArgs), 'Fractional-hour timezones must be supported.');
$testTimezone = 'Europe/Berlin';

echo "All regression checks passed.\n";
