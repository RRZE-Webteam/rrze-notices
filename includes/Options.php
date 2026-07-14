<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Provides normalized site and network option values.
 */
class Options
{
    /** @var string Per-site option key. */
    public const OPTION_NAME = 'rrze_notices';

    /** @var string Network option key. */
    public const SITE_OPTION_NAME = 'rrze_notices_site';

    /**
     * Return defaults for per-site options.
     *
     * @return array<string, mixed>
     */
    protected static function deafaultOptions()
    {
        return [];
    }

    /**
     * Return defaults for network-wide options.
     *
     * @return array{enabled:int, access:string, network_wide:array<string, array<string, mixed>>}
     */
    protected static function defaultSiteOptiones()
    {
        return [
            'enabled' => 0,
            'access' => 'manage_network_options',
            'network_wide' => []
        ];
    }

    /**
     * Get normalized options for the current site.
     *
     * @return object Per-site option object.
     */
    public static function getOptions()
    {
        $defaults = self::deafaultOptions();

        $options = (array) get_option(static::OPTION_NAME);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    /**
     * Get normalized options shared by the multisite network.
     *
     * @return object{enabled:int, access:string, network_wide:array<string, array<string, mixed>>}
     */
    public static function getSiteOptions()
    {
        $defaults = self::defaultSiteOptiones();

        $options = (array) get_site_option(static::SITE_OPTION_NAME);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    /**
     * Get the per-site option key.
     *
     * @return string
     */
    public static function getOptionName()
    {
        return static::OPTION_NAME;
    }

    /**
     * Get the network option key.
     *
     * @return string
     */
    public static function getSiteOptionName()
    {
        return static::SITE_OPTION_NAME;
    }
}
