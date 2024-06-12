<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Class Cron
 *
 * A class for managing scheduled events using WP cron functionality.
 */
class Cron
{
    /**
     * The action hook name for the scheduled event
     */
    const UPDATE_STATUS = 'rrze_notices_update_status';

    /**
     * Initialize the class by setting up action hooks
     * 
     * @return void
     */
    public static function init()
    {
        // Add an action hook to run the 'updateStatus' method when scheduled.
        add_action(self::UPDATE_STATUS, [__CLASS__, 'updateStatus']);

        // Add an action hook to activate scheduled events during WP initialization.
        add_action('init', [__CLASS__, 'activateScheduledEvents']);
    }

    /**
     * Activate the scheduled event if it's not already scheduled
     * 
     * @return void
     */
    public static function activateScheduledEvents()
    {
        // Check if the scheduled event is not already in the queue.
        if (!wp_next_scheduled(self::UPDATE_STATUS)) {
            // Schedule the event to run hourly starting from the current time.
            wp_schedule_event(time(), 'hourly', self::UPDATE_STATUS);
        }
    }

    /**
     * Method to be executed when the scheduled event is triggered
     * 
     * @return void
     */
    public static function updateStatus()
    {
        Post::updateStatus();
    }

    /**
     * Clear the scheduled event hook
     * 
     * @return void
     */
    public static function clearSchedule()
    {
        // Clear the scheduled event hook for the specified action hook.
        wp_clear_scheduled_hook(self::UPDATE_STATUS);
    }
}
