<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Persists per-user notice dismissals and handles the AJAX dismissal request.
 */
class Dismiss
{
    /**
     * Prefix used for per-user dismissal metadata.
     *
     * @var string
     */
    public const PREFIX = 'rrze_notices_dismissed';

    /**
     * The notice-ID.
     *
     * @var string
     */
    private $id;

    /**
     * The prefix to be using for the option/user-meta.
     *
     * @var string
     */
    private $prefix;

    /**
     * Should the script be printed.
     *
     * @var boolean
     */
    private $isDismissible;

    /**
     * Initialize dismissal state and register the AJAX callback.
     * 
     * @param string $id            Site-qualified notice identifier.
     * @param bool   $isDismissible Whether the notice can be dismissed.
     */
    public function __construct(string $id, bool $isDismissible = true)
    {
        $this->id = sanitize_key($id);
        $this->prefix = self::PREFIX;
        $this->isDismissible = $isDismissible;

        add_action('wp_ajax_rrze_notices_dismiss', [$this, 'ajaxDismissNotice']);
    }

    /**
     * Print the script for dismissing the notice.
     *
     * @return void
     */
    public function printScript()
    {
        if (!$this->isDismissible) return;

        $nonce = wp_create_nonce('rrze_notices_dismiss_' . $this->id); ?>
        <script>
            window.addEventListener('load', function() {
                var dismissBtn = document.querySelector('div#rrze-notices-<?php echo esc_attr($this->id); ?>.is-dismissible');
                dismissBtn.addEventListener('click', function(event) {
                    if (event.target.tagName.toLowerCase() === 'a') {
                        return;
                    }
                    var httpRequest = new XMLHttpRequest(),
                        postData = '';
                    postData += 'id=<?php echo esc_attr(rawurlencode($this->id)); ?>';
                    postData += '&action=rrze_notices_dismiss';
                    postData += '&nonce=<?php echo esc_html($nonce); ?>';
                    httpRequest.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
                    httpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    httpRequest.send(postData);
                });
            });
        </script>
<?php
    }

    /**
     * Determine whether the current user dismissed this notice.
     *
     * @return bool
     */
    public function isDismissed()
    {
        if (!$this->isDismissible) return false;

        return (get_user_meta(get_current_user_id(), "{$this->prefix}_{$this->id}", true));
    }

    /**
     * Validate and process the AJAX dismissal request.
     *
     * @return void
     */
    public function ajaxDismissNotice()
    {
        if (!isset($_POST['action']) || 'rrze_notices_dismiss' !== $_POST['action']) {
            return;
        }

        if (!isset($_POST['id']) || $this->id !== $_POST['id']) {
            return;
        }

        check_ajax_referer('rrze_notices_dismiss_' . $this->id, 'nonce', true);

        $this->dismissNotice();
    }

    /**
     * Dismisses the notice.
     *
     * @return void
     */
    private function dismissNotice()
    {
        update_user_meta(get_current_user_id(), "{$this->prefix}_{$this->id}", true);
    }

    /**
     * Delete this dismissal key for all users.
     *
     * Used when a notice is republished or its lifecycle restarts.
     *
     * @return void
     */
    public function deleteDismissedNotice()
    {
        global $wpdb;
        $metaKey = "{$this->prefix}_{$this->id}";
        $sql = $wpdb->prepare(
            "DELETE FROM $wpdb->usermeta WHERE meta_key = %s",
            $metaKey
        );
        $wpdb->query($sql);
    }
}
