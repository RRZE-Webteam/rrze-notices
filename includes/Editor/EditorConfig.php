<?php

declare(strict_types=1);

namespace RRZE\Notices\Editor;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT\CPTNotice;

/**
 * Configures the Classic Editor for the notice post type.
 */
class EditorConfig
{
    /**
     * Register editor-configuration hooks.
     *
     * @return void
     */
    public static function registerHooks(): void
    {
        add_filter('wp_editor_settings', [__CLASS__, 'filterEditorSettings'], 10, 2);
        add_filter('tiny_mce_before_init', [__CLASS__, 'filterTinyMceSettings']);
        add_filter('quicktags_settings', [__CLASS__, 'filterQuicktagsSettings'], 10, 2);
        add_filter('wp_editor_expand', [__CLASS__, 'disableEditorExpand']);
    }

    /**
     * Check whether the current screen is the notice editor.
     *
     * @return bool
     */
    private static function isNoticeEditor(): bool
    {
        $screen = get_current_screen();
        return $screen && $screen->post_type === CPTNotice::POST_TYPE;
    }

    /**
     * Set editor height, disable media buttons and auto-expand.
     *
     * @param array $settings Editor settings.
     * @param string $editorId Editor instance ID.
     * @return array Modified settings.
     */
    public static function filterEditorSettings(array $settings, string $editorId): array
    {
        if (!self::isNoticeEditor()) {
            return $settings;
        }

        $settings['editor_height'] = 300;
        $settings['media_buttons'] = false;

        return $settings;
    }

    /**
     * Restrict the TinyMCE toolbar and valid elements to the notice allowlist.
     *
     * @param array $settings TinyMCE init settings.
     * @return array Modified settings.
     */
    public static function filterTinyMceSettings(array $settings): array
    {
        if (!self::isNoticeEditor()) {
            return $settings;
        }

        $settings['toolbar1'] = 'bold,italic,bullist,numlist,blockquote,link';
        $settings['toolbar2'] = '';
        $settings['valid_elements'] = self::buildValidElements();

        return $settings;
    }

    /**
     * Restrict quicktag buttons in the Text tab to allowed elements.
     *
     * @param array $settings Quicktags settings.
     * @param string $editorId Editor instance ID.
     * @return array Modified settings.
     */
    public static function filterQuicktagsSettings(array $settings, string $editorId): array
    {
        if (!self::isNoticeEditor()) {
            return $settings;
        }

        $settings['buttons'] = 'strong,em,link,block,ins,ul,ol,li';

        return $settings;
    }

    /**
     * Disable the editor auto-expand that causes layout jumping.
     *
     * @param bool $expand Whether to expand the editor.
     * @return bool
     */
    public static function disableEditorExpand(bool $expand): bool
    {
        if (!self::isNoticeEditor()) {
            return $expand;
        }

        return false;
    }

    /**
     * Build a TinyMCE valid_elements string from the kses allowlist.
     *
     * @return string Comma-separated valid_elements value.
     */
    private static function buildValidElements(): string
    {
        $allowed = AllowedHtml::get();
        $elements = [];

        foreach ($allowed as $tag => $attributes) {
            if (empty($attributes)) {
                $elements[] = $tag;
            } else {
                $attrs = implode('|', array_keys($attributes));
                $elements[] = $tag . '[' . $attrs . ']';
            }
        }

        return implode(',', $elements);
    }
}