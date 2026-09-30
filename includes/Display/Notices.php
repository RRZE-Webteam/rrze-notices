<?php

declare(strict_types=1);

namespace RRZE\Notices\Display;

defined('ABSPATH') || exit;

/**
 * Request-scoped collection of notices to render in the WordPress admin.
 */
class Notices
{
    /**
     * An array of notices.
     *
     * @var array<string, Notice>
     */
    private $notices = [];

    /** @var bool Whether rendering hooks have already been registered. */
    private bool $hooksRegistered = false;

    /**
     * Adds actions for the notices.
     *
     * @return void
     */
    public function registerHooks(): void
    {
        if ($this->hooksRegistered) {
            return;
        }

        add_action('admin_notices', [$this, 'renderNotices']);
        $this->hooksRegistered = true;
    }

    /**
     * Add a notice.
     *
     * @param string $id A unique ID for this notice. Can contain lowercase characters and underscores.
     * @param string $title The title for our notice.
     * @param string $message The message for our notice.
     * @param array $options An array of additional options to change the defaults for this notice.
     *                        See Notice::__construct() for details.
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
     * Render all registered notices.
     *
     * @return void
     */
    public function renderNotices(): void
    {
        $notices = $this->getAll();

        foreach ($notices as $notice) {
            $notice->render();
        }
    }

}