<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Provides normalized network option values.
 */
class Options
{
    /** @var string Network option key. */
    public const NETWORK_OPTION_NAME = 'rrze_notices_site';

    /**
     * Return defaults for network-wide options.
     *
     * @return array{enabled:int, access:string, network_wide:array<string, array<string, mixed>>}
     */
    private static function defaultNetworkOptions(): array
    {
        return [
            'enabled' => 0,
            'access' => 'manage_network_options',
            'network_wide' => []
        ];
    }

    /**
     * Get normalized options shared by the multisite network.
     *
     * @return object{enabled:int, access:string, network_wide:array<string, array<string, mixed>>}
     */
    public static function getNetworkOptions(): object
    {
        $defaults = self::defaultNetworkOptions();

        $options = (array) get_site_option(static::NETWORK_OPTION_NAME);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    /**
     * Get the network option key.
     *
     * @return string
     */
    public static function getNetworkOptionName(): string
    {
        return static::NETWORK_OPTION_NAME;
    }
}
