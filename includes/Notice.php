<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

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
     * An instance of the \RRZE\Notices\Dismiss object.
     *
     * @var \RRZE\Notices\Dismiss
     */
    public $dismiss;

    /**
     * Default notice options.
     *
     * @var array
     */
    private $options = [
        'screens' => [],
        'type' => 'info',
        'hide_title' => false,
        'preview' => false,
        'subscription_link' => '',
        'edit_link' => '',
        'event' => '',
        'is_dismissible' => true
    ];

    /**
     * Allowed HTML in the message.
     *
     * @var array
     */
    private $allowedHtml = [
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
        'blockquote' => []
    ];

    /**
     * An array of allowed types.
     *
     * @var array
     */
    private $allowedTypes = [
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

        $this->allowedHtml = apply_filters('rrze_notices_allowed_html', $this->allowedHtml);

        $this->dismiss = new Dismiss($this->id, $this->options['is_dismissible']);
    }

    /**
     * Prints the notice.
     */
    public function printNotice()
    {
        if (!$this->show()) {
            return;
        }

        $event = (object) $this->options['event'];
        $allDay = $event->all_day;
        $multiDay = $event->multiday;
        $tsStartUTC = strtotime($event->start_gmt);
        $tsStart = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsStartUTC)));
        $tsEndUTC = strtotime($event->end_gmt);
        $tsEnd = strtotime(get_date_from_gmt(date('Y-m-d H:i:s', $tsEndUTC)));
        $startDay = date('Y-m-d', $tsStart);
        $endDay = date('Y-m-d', $tsEnd);
        // Multiday event
        if ($multiDay == 'on' && $startDay != $endDay) {
            // All day event
            if ($allDay == 'on') {
                $eventReadableDatetime = date_i18n(get_option('date_format'), $tsStart)
                    . ' &ndash; '
                    . date_i18n(get_option('date_format'), $tsEnd);
                // Timed event
            } else {
                $eventReadableDatetime = date_i18n(get_option('date_format') . ', ' . get_option('time_format'), $tsStart)
                    . ' &ndash; '
                    . date_i18n(get_option('date_format') . ', ' . get_option('time_format'), $tsEnd);
            }
            // Single day event
        } else {
            // All day event
            if ($allDay == 'on') {
                $eventReadableDatetime = ($endDay == $startDay ? date_i18n(get_option('date_format'), $tsStart) : date_i18n(get_option('date_format'), $tsStart)
                    . ' &ndash; '
                    . date_i18n(get_option('date_format'), $tsEnd));
                // Timed event
            } else {
                $eventReadableDatetime = date_i18n(get_option('date_format'), $tsStart)
                    . ' '
                    . date_i18n(get_option('time_format'), $tsStart)
                    . ' &ndash; '
                    . date_i18n(get_option('time_format'), $tsEnd);
            }
        }

        $eventDate = sprintf(
            '<p class="notice-event-date">%s</p>',
            $eventReadableDatetime
        );

        $html  = $this->getTitle();
        $html .= $eventDate;
        $html .= $this->getMessage();

        $noticeActions[] = $this->options['subscription_link'] ? $this->options['subscription_link'] : '';
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
    public function show()
    {
        if (!$this->isScreen()) {
            return false;
        }

        if ($this->dismiss->isDismissed()) {
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

        $this->options['type'] = in_array($this->options['type'], $this->allowedTypes) ? $this->options['type'] : 'info';

        $classes[] = 'notice-' . $this->options['type'];

        $classes[] = 'notice-alt';

        return implode(' ', $classes);
    }

    /**
     * Returns the title.
     *
     * @return string
     */
    public function getTitle()
    {
        if (!$this->title || $this->options['hide_title']) {
            return '';
        }

        return sprintf(
            '<h2 class="notice-title">%s</h2>',
            esc_html($this->title)
        );
    }

    /**
     * Returns the message.
     *
     * @return string
     */
    public function getMessage()
    {
        return wpautop(wp_kses($this->message, $this->allowedHtml));
    }

    /**
     * Evaluate if we're on the right place depending on the "screens" argument.
     *
     * @return bool
     */
    private function isScreen()
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
