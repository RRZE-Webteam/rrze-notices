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

require_once dirname(__DIR__) . '/includes/Helper.php';
require_once dirname(__DIR__) . '/includes/Users.php';
require_once dirname(__DIR__) . '/includes/NoticeId.php';
require_once dirname(__DIR__) . '/includes/NoticeVisibility.php';

use RRZE\Notices\NoticeId;
use RRZE\Notices\NoticeVisibility;

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


echo "All regression checks passed.\n";
