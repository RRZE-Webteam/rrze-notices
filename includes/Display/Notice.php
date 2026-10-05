<?php

declare(strict_types=1);

namespace RRZE\Notices\Display;

defined('ABSPATH') || exit;

use RRZE\Notices\Admin\Dismiss;
use RRZE\Notices\Editor\AllowedHtml;

/**
 * Represents and renders one dashboard notice for the current request.
 */
class Notice
{
    /**
     * The notice-ID.
     *
     * @var string
     */
    private $id;

    /**
     * The notice title.
     *
     * @var string
     */
    private $title;

    /**
     * The notice message.
     *
     * @var string
     */
    private $message;

    /**
     * Dismissal state for this notice.
     *
     * @var Dismiss|null
     */
    public ?Dismiss $dismissal = null;

    /**
     * Default notice options.
     *
     * @var array<string, mixed>
     */
    private $options = [
        'screens' => [],
        'type' => 'info',
        'preview' => false,
        'edit_link' => '',
        'is_dismissible' => true,
        'event_start' => 0,
        'event_end' => 0,
    ];


    /**
     * Allowed WordPress notice types.
     *
     * @var string[]
     */
    private const ALLOWED_TYPES = [
        'info',
        'success',
        'error',
        'warning'
    ];

    /**
     * Constructor.
     *
     * @param string $id      A unique ID for this notice. Can contain lowercase characters and underscores.
     * @param string $title   The title of the notice.
     * @param string $message The message of the notice.
     * @param array  $options An array of additional options to change the defaults for the notice.
     */
    public function __construct($id, $title, $message, $options = [])
    {
        $this->id = $id;
        $this->title = $title;
        $this->message = $message;
        $this->options = wp_parse_args($options, $this->options);

        if (!$this->id || !$this->message) {
            return;
        }

        $this->dismissal = new Dismiss($this->id, $this->options['is_dismissible']);
    }

    /**
     * Render the notice markup.
     *
     * @return void
     */
    public function render(): void
    {
        if (!$this->shouldDisplay()) {
            return;
        }

        $html  = $this->renderTitle();
        $html .= $this->renderEventDate();
        $html .= $this->renderMessage();

        $noticeActions[] = $this->options['edit_link'] ? $this->options['edit_link'] : '';
        $noticeActions = array_filter($noticeActions);
        $html .= sprintf(
            '<div class="notice-actions">%s</div>',
            implode('&nbsp;&nbsp;|&nbsp;&nbsp;', $noticeActions)
        );

        printf(
            '<div id="%1$s" class="%2$s">%3$s</div>',
            'rrze-notices-' . esc_attr($this->id),
            esc_attr($this->getClasses()),
            $html
        );
    }

    /**
     * Determine if the notice should be shown or not.
     *
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        if (!$this->isAllowedScreen()) {
            return false;
        }

        if (!$this->dismissal || $this->dismissal->isDismissed()) {
            return false;
        }

        return true;
    }

    /**
     * Get the notice classes.
     *
     * @return string
     */
    public function getClasses()
    {
        $classes = [
            'rrze-notices',
            'notice'
        ];

        if ($this->options['preview']) {
            $classes[] = 'notice-preview';
        }

        if ($this->options['is_dismissible']) {
            $classes[] = 'is-dismissible';
        }

        $type = in_array($this->options['type'], self::ALLOWED_TYPES, true)
            ? $this->options['type']
            : 'info';
        $classes[] = 'notice-' . $type;

        $classes[] = 'notice-alt';

        return implode(' ', $classes);
    }

    /**
     * Return the escaped title markup.
     *
     * @return string
     */
    public function renderTitle(): string
    {
        if (!$this->title) {
            return '';
        }

        return sprintf(
            '<h2 class="notice-title">%s</h2>',
            esc_html($this->title)
        );
    }

    /**
     * Return the event date markup, or an empty string if no
    event date is set.
     *
     * @return string
     */
    public function renderEventDate(): string
    {
        $start = (int) $this->options['event_start'];
        if (!$start) {
            return '';
        }

        $end = (int) $this->options['event_end'];

        $startDate = wp_date(_x('d.m.Y', 'date format', 'rrze-notices'), $start);
        $startTime = wp_date(_x('H:i', 'time format', 'rrze-notices'), $start);
        $startHasTime = wp_date('H:i', $start) !== '00:00';

        if (!$end || $end <= $start) {
            // Single date, optionally with time
            $output = $startHasTime
                ? sprintf('%s, %s Uhr', $startDate, $startTime)
                : $startDate;
        } else {
            $endDate = wp_date(_x('d.m.Y', 'date format', 'rrze-notices'), $end);
            $endTime = wp_date(_x('H:i', 'time format', 'rrze-notices'), $end);
            $endHasTime = wp_date('H:i', $end) !== '00:00';
            $sameDay = wp_date('Y-m-d', $start) === wp_date('Y-m-d', $end);

            if ($sameDay) {
                // Same day: show date once, time range
                if ($startHasTime && $endHasTime) {
                    $output = sprintf('%s, %s – %s Uhr', $startDate, $startTime, $endTime);
                } elseif ($startHasTime) {
                    $output = sprintf('%s, %s Uhr', $startDate, $startTime);
                } else {
                    $output = $startDate;
                }
            } else {
                // Different days
                $startPart = $startHasTime
                    ? sprintf('%s, %s Uhr', $startDate, $startTime)
                    : $startDate;
                $endPart = $endHasTime
                    ? sprintf('%s, %s Uhr', $endDate, $endTime)
                    : $endDate;
                $output = sprintf('%s – %s', $startPart, $endPart);
            }
        }

        return sprintf('<p class="notice-event-date">%s</p>', esc_html($output));
    }


    /**
     * Return the sanitized message markup.
     *
     * @return string
     */
    public function renderMessage(): string
    {
        return wpautop(wp_kses($this->message, AllowedHtml::get()));
    }

    /**
     * Evaluate if we're on the right place depending on the "screens" argument.
     *
     * @return bool
     */
    private function isAllowedScreen(): bool
    {
        if (!$this->options['screens'] || empty($this->options['screens'])) {
            return true;
        }

        if (!function_exists('get_current_screen')) {
            require_once ABSPATH . 'wp-admin/includes/screen.php';
        }

        return (in_array(get_current_screen()->id, $this->options['screens'], true));
    }
}
