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
        'screens'        => [],
        'type'           => 'info',
        'alt_style'      => false,
        'edit_link'      => '',
        'capability'     => 'edit_theme_options',
        'option_prefix'  => 'rrze_notices_dismissed',
        'is_dismissible' => true
    ];

    /**
     * Allowed HTML in the message.
     *
     * @var array
     */
    private $allowed_html = [
        'p' => [],
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
    private $allowed_types = [
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
        $this->id         = $id;
        $this->title      = $title;
        $this->message    = $message;
        $this->options    = wp_parse_args($options, $this->options);

        if (!$this->id || !$this->message) {
            return;
        }

        $this->allowed_html = apply_filters('rrze_notices_allowed_html', $this->allowed_html);

        $this->dismiss = new Dismiss($this->id, $this->options['option_prefix'], $this->options['is_dismissible']);
    }

    /**
     * Prints the notice.
     */
    public function theNotice()
    {
        if (!$this->show()) {
            return;
        }

        $html  = $this->getTitle();
        $html .= $this->getMessage();
        $html .= $this->options['edit_link'] ? $this->options['edit_link'] : '';

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
        if (!current_user_can($this->options['capability'])) {
            return false;
        }

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

        if ($this->options['is_dismissible']) {
            $classes[] = 'is-dismissible';
        }

        $this->options['type'] = in_array($this->options['type'], $this->allowed_types) ? $this->options['type'] : 'info';

        $classes[] = 'notice-' . $this->options['type'];

        if ($this->options['alt_style']) {
            $classes[] = 'notice-alt';
        }

        return implode(' ', $classes);
    }

    /**
     * Returns the title.
     *
     * @return string
     */
    public function getTitle()
    {
        if (!$this->title) {
            return '';
        }

        return sprintf(
            '<h2 class="notice-title">%s</h2>',
            wp_strip_all_tags($this->title)
        );
    }

    /**
     * Returns the message.
     *
     * @return string
     */
    public function getMessage()
    {
        return wpautop(wp_kses($this->message, $this->allowed_html));
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
