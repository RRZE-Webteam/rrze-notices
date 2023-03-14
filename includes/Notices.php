<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Notices
{
    /**
     * An array of notices.
     *
     * @var array
     */
    private $notices = [];

    /**
     * Adds actions for the notices.
     */
    public function boot()
    {
        add_action('admin_notices', [$this, 'theNotices']);
        add_action('admin_footer', [$this, 'printScripts']);
    }

    /**
     * Add a notice.
     *
     * @param string $id      A unique ID for this notice. Can contain lowercase characters and underscores.
     * @param string $title   The title for our notice.
     * @param string $message The message for our notice.
     * @param array  $options An array of additional options to change the defaults for this notice.
     *                        See Notice::__constructor() for details.
     * @return void
     */
    public function add($id, $title, $message, $options = [])
    {
        $this->notices[$id] = new Notice($id, $title, $message, $options);
    }

    /**
     * Remove a notice.
     *
     * @param string $id The ID of the notice.
     * @return void
     */
    public function remove($id)
    {
        unset($this->notices[$id]);
    }

    /**
     * Get a single notice.
     *
     * @param string $id The ID of the notice.
     * @return Notice|null
     */
    public function get($id)
    {
        if (isset($this->notices[$id])) {
            return $this->notices[$id];
        }
        return null;
    }

    /**
     * Get all notices.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->notices;
    }

    /**
     * Prints the notice.
     *
     * @return void
     */
    public function theNotices()
    {
        $notices = $this->getAll();

        foreach ($notices as $notice) {
            $notice->theNotice();
        }
    }

    /**
     * Prints scripts for the notices.
     */
    public function printScripts()
    {
        $notices = $this->getAll();

        foreach ($notices as $notice) {
            if ($notice->show()) {
                $notice->dismiss->printScript();
            }
        }
    }
}
