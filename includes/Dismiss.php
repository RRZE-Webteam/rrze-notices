<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Dismiss
{

    /**
     * The notice-ID.
     *
     * @var string
     */
    private $id;

    /**
     * The prefix we'll be using for the option/user-meta.
     *
     * @var string
     */
    private $prefix;

    /**
     * The notice's scope. Can be "user" or "global".
     *
     * @var string
     */
    private $scope;

    /**
     * Should the script be printed.
     *
     * @var boolean
     */
    private $isDismissible;

    /**
     * Constructor.
     * 
     * @param string $id A unique ID for this notice. Can contain lowercase characters and underscores.
     * @param string $prefix The prefix that will be used for the option/user-meta.
     * @param string $scope Used for the option/user-meta saving.
     * @param boolean $isDismissible Should be the notice dismissible?
     */
    public function __construct(string $id, string $prefix, string $scope = 'global', bool $isDismissible = true)
    {
        $this->id = sanitize_key($id);
        $this->prefix = sanitize_key($prefix);
        $this->scope = (in_array($scope, ['global', 'user'], true)) ? $scope : 'global';
        $this->isDismissible = $isDismissible;

        add_action('wp_ajax_rrze_notices_dismiss', [$this, 'ajaxDismissNotice']);
    }

    /**
     * Print the script for dismissing the notice.
     */
    public function printScript()
    {
        if (!$this->isDismissible) return;

        $nonce = wp_create_nonce('rrze_notices_dismiss_' . $this->id); ?>
        <script>
            window.addEventListener('load', function() {
                var dismissBtn = document.querySelector('#rrze-notices-<?php echo esc_attr($this->id); ?>.is-dismissible');
                dismissBtn.addEventListener('click', function(event) {
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
     * Check if the notice has been dismissed or not.
     *
     * @return bool
     */
    public function isDismissed()
    {
        if ('user' === $this->scope) {
            return (get_user_meta(get_current_user_id(), "{$this->prefix}_{$this->id}", true));
        }

        return (get_option("{$this->prefix}_{$this->id}"));
    }

    /**
     * Check to see if the notice needs to be dismissed.
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
     */
    private function dismissNotice()
    {
        if ('user' === $this->scope) {
            update_user_meta(get_current_user_id(), "{$this->prefix}_{$this->id}", true);
            return;
        }
        update_option("{$this->prefix}_{$this->id}", true);
    }
}
