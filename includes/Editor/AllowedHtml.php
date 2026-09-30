<?php
declare(strict_types=1);

namespace RRZE\Notices\Editor;

defined('ABSPATH') || exit;

/**
 * Single source of truth for HTML tags allowed in notice content.
 */
class AllowedHtml
{
    /**
     * Return the allowed HTML tags and attributes for notice content.
     *
     * The result is filterable via 'rrze_notices_allowed_html' so that
     * site-specific code can extend the allowlist without touching the plugin.
     *
     * @return array<string, array<string, array<mixed>>> wp_kses-compatible
    allowlist.
     */
    public static function get(): array
    {
        $allowed = [
            'p' => [
                'class' => [],
            ],
            'a' => [
                'href' => [],
                'rel' => [],
            ],
            'em' => [],
            'strong' => [],
            'br' => [],
            'ol' => [],
            'ul' => [],
            'li' => [],
            'ins' => [],
            'blockquote' => [],
        ];

        return apply_filters('rrze_notices_allowed_html', $allowed);
    }
}
