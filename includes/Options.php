<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Options
{
    const OPTION_NAME = 'rrze_notices';

    const SITE_OPTION_NAME = 'rrze_notices_site';

    protected static function deafaultOptions()
    {
        return [
            'notices_per_page' => 10,
            'notices' => []
        ];
    }

    protected static function defaultSiteOptiones()
    {
        return [
            'active' => 1
        ];
    }

    public static function getOptions()
    {
        $defaults = self::deafaultOptions();

        $options = (array) get_option(static::OPTION_NAME);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    public static function getSiteOptions()
    {
        $defaults = self::defaultSiteOptiones();

        $options = (array) get_site_option(static::SITE_OPTION_NAME);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    public static function getOptionName()
    {
        return static::OPTION_NAME;
    }

    public static function getSiteOptionName()
    {
        return static::SITE_OPTION_NAME;
    }
}
