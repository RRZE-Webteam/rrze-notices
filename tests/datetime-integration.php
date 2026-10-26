<?php

declare(strict_types=1);

/**
 * Exercise real CMB2 save/render cycles using in-memory post metadata.
 *
 * Run from the plugin directory:
 * wp --skip-plugins --skip-themes eval-file tests/datetime-integration.php
 *
 * No posts or site options are written to the database.
 */

use RRZE\Notices\Editor\Metaboxes;

if (!defined('ABSPATH')) {
    throw new RuntimeException('Run this integration test through wp eval-file.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
if (!class_exists('CMB2', false)) {
    foreach (get_declared_classes() as $class) {
        if (str_starts_with($class, 'CMB2_Bootstrap_')) {
            $class::initiate()->include_cmb();
            break;
        }
    }
}

const NOTICE_DATE_TEST_ID = 2147483647;
global $noticeDateTestMeta, $noticeDateTestTimezone, $noticeDateForeignFilterCalls;
$noticeDateTestMeta = [];
$noticeDateTestTimezone = 'Europe/Berlin';
$noticeDateForeignFilterCalls = 0;

/**
 * Read fixture metadata without consulting a real post.
 *
 * @param mixed $override Previous filter result.
 * @param int $objectId Requested post.
 * @param string $key Metadata key.
 * @param bool $single Whether one value was requested.
 * @return mixed Fixture value, or the previous filter result for other posts.
 */
function noticeDateTestReadMeta(mixed $override, int $objectId, string $key, bool $single): mixed
{
    global $noticeDateTestMeta;
    if ($objectId !== NOTICE_DATE_TEST_ID) {
        return $override;
    }
    return $single ? ($noticeDateTestMeta[$key] ?? '') : (isset($noticeDateTestMeta[$key]) ? [$noticeDateTestMeta[$key]] : []);
}

/**
 * Capture fixture metadata writes in memory.
 *
 * @param mixed $override Previous filter result.
 * @param int $objectId Target post.
 * @param string $key Metadata key.
 * @param mixed $value New value.
 * @return mixed True for the fixture, otherwise the previous filter result.
 */
function noticeDateTestWriteMeta(mixed $override, int $objectId, string $key, mixed $value): mixed
{
    global $noticeDateTestMeta;
    if ($objectId !== NOTICE_DATE_TEST_ID) {
        return $override;
    }
    $noticeDateTestMeta[$key] = $value;
    return true;
}

/**
 * Capture fixture metadata deletion in memory.
 *
 * @param mixed $override Previous filter result.
 * @param int $objectId Target post.
 * @param string $key Metadata key.
 * @return mixed True for the fixture, otherwise the previous filter result.
 */
function noticeDateTestDeleteMeta(mixed $override, int $objectId, string $key): mixed
{
    global $noticeDateTestMeta;
    if ($objectId !== NOTICE_DATE_TEST_ID) {
        return $override;
    }
    unset($noticeDateTestMeta[$key]);
    return true;
}

/**
 * Supply a test timezone without changing the site's saved configuration.
 *
 * @return string Current fixture timezone.
 */
function noticeDateTestTimezone(): string
{
    global $noticeDateTestTimezone;
    return $noticeDateTestTimezone;
}

/**
 * Simulate another plugin overriding all standard CMB2 datetime fields.
 *
 * @return int Deliberately incorrect timestamp to reveal filter interference.
 */
function noticeDateTestForeignFilter(): int
{
    global $noticeDateForeignFilterCalls;
    ++$noticeDateForeignFilterCalls;
    return 123;
}

/**
 * Fail an integration check with its expected and actual values.
 *
 * @param mixed $expected Required value.
 * @param mixed $actual Observed value.
 * @param string $message Description of the checked behavior.
 * @return void
 */
function noticeDateAssert(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': ' . var_export([$expected, $actual], true));
    }
}

/**
 * Render a field through its actual CMB2 hook and read the browser input values.
 *
 * @param CMB2_Field $field Field to render.
 * @return array{date: string, time: string} Values that a browser would submit.
 */
function noticeDateTestRender(CMB2_Field $field): array
{
    // CMB2 caches row markup within a request; a browser reload starts fresh.
    $field->unset_param_callback_cache('render_row_cb');
    ob_start();
    $field->render_field();
    $html = ob_get_clean();
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $input = [];
    foreach (['date', 'time'] as $part) {
        $element = $xpath->query('//input[@id="' . $field->id() . '_' . $part . '"]')->item(0);
        noticeDateAssert(true, $element instanceof DOMElement, 'Both date inputs must render');
        $input[$part] = $element->getAttribute('value');
        noticeDateAssert($field->id() === 'notice_visible_until', $element->hasAttribute('required'), 'Only expiry inputs are mandatory');
        if ($part === 'time') {
            noticeDateAssert('time', $element->getAttribute('type'), 'Time input must use native browser behavior');
            noticeDateAssert(false, str_contains($element->getAttribute('class'), 'cmb2-timepicker'), 'CMB2 must not initialize its auto-filling time picker');
        }
    }
    return $input;
}

add_filter('get_post_metadata', 'noticeDateTestReadMeta', 10, 4);
add_filter('update_post_metadata', 'noticeDateTestWriteMeta', 10, 4);
add_filter('delete_post_metadata', 'noticeDateTestDeleteMeta', 10, 3);
add_filter('pre_option_timezone_string', 'noticeDateTestTimezone');
Metaboxes::registerHooks();
Metaboxes::registerNoticeMetabox();
$box = cmb2_get_metabox('rrze_notices_metabox');
$box->object_id(NOTICE_DATE_TEST_ID);

foreach ([false, true] as $foreignFilter) {
    if ($foreignFilter) {
        add_filter('cmb2_sanitize_text_datetime_timestamp', 'noticeDateTestForeignFilter');
    }
    foreach (['Europe/Berlin', 'UTC', 'Asia/Kolkata'] as $noticeDateTestTimezone) {
        foreach (['21.10.2026', '21.01.2026'] as $date) {
            foreach (['notice_event_start', 'notice_event_end', 'notice_visible_until'] as $id) {
                $field = $box->get_field($id, null, true);
                $input = ['date' => $date, 'time' => '12:00'];
                $expected = DateTimeImmutable::createFromFormat('!d.m.Y H:i', $date . ' 12:00', wp_timezone())->getTimestamp();
                for ($cycle = 0; $cycle < 3; ++$cycle) {
                    $field->save_field($input);
                    noticeDateAssert($expected, $noticeDateTestMeta[$id], 'Repeated saving must preserve the absolute timestamp');
                    noticeDateAssert($input, noticeDateTestRender($field), 'Editor values must survive a save unchanged');
                    noticeDateAssert($date . ' 12:00', wp_date('d.m.Y H:i', $noticeDateTestMeta[$id]), 'Notice display must match the input');
                    if ($id === 'notice_visible_until') {
                        noticeDateAssert($expected, $noticeDateTestMeta['notice_visible_until_gmt'], 'Expiry must use the same instant as the editor');
                    }
                    $input = noticeDateTestRender($field);
                }
                $field->save_field(['date' => '', 'time' => '']);
                noticeDateAssert(['date' => '', 'time' => ''], noticeDateTestRender($field), 'Cleared fields must remain empty');
                if ($id === 'notice_visible_until') {
                    noticeDateAssert(false, isset($noticeDateTestMeta['notice_visible_until_gmt']), 'Clearing expiry must remove both stored values');
                } else {
                    $field->save_field(['date' => $date, 'time' => '']);
                    noticeDateAssert(['date' => $date, 'time' => ''], noticeDateTestRender($field), 'Date-only events must retain a blank time');
                }
            }
        }
    }
}
noticeDateAssert(0, $noticeDateForeignFilterCalls, 'Foreign standard-datetime filters must not process notice fields');
noticeDateAssert(
    123,
    apply_filters('cmb2_sanitize_text_datetime_timestamp', null, ['date' => '', 'time' => ''], NOTICE_DATE_TEST_ID, $field->args(), new CMB2_Sanitize($field, '')),
    'The foreign filter must remain active for other plugins'
);

$noticeDateTestTimezone = 'Europe/Berlin';
$noticeDateTestMeta['notice_visible_until'] = 1792584000;
$noticeDateTestMeta['notice_visible_until_gmt'] = 1792576800;
$field = $box->get_field('notice_visible_until', null, true);
$input = noticeDateTestRender($field);
noticeDateAssert(['date' => '21.10.2026', 'time' => '12:00'], $input, 'Legacy expiry must display the authoritative GMT value');
$field->save_field($input);
noticeDateAssert(1792576800, $noticeDateTestMeta['notice_visible_until_gmt'], 'Saving legacy expiry must not move its deadline');
noticeDateAssert(1792576800, $noticeDateTestMeta['notice_visible_until'], 'Saving legacy expiry must normalize its editor value');

foreach (['notice_event_start', 'notice_event_end', 'notice_visible_until'] as $id) {
    foreach (['2026-10-25T00:30:00Z', '2026-10-25T01:30:00Z'] as $utcTime) {
        $timestamp = (new DateTimeImmutable($utcTime))->getTimestamp();
        $noticeDateTestMeta[$id] = $timestamp;
        if ($id === 'notice_visible_until') {
            $noticeDateTestMeta['notice_visible_until_gmt'] = $timestamp;
        }
        $field = $box->get_field($id, null, true);
        $input = noticeDateTestRender($field);
        noticeDateAssert(['date' => '25.10.2026', 'time' => '02:30'], $input, 'Both autumn DST occurrences must display correctly');
        $field->save_field($input);
        noticeDateAssert($timestamp, $noticeDateTestMeta[$id], 'Unchanged ambiguous local times must preserve their original instant');
    }
}

echo "All datetime integration checks passed.\n";
