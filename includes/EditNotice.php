<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class EditNotice
{
    protected $notices;

    protected $customPostType;

    protected $userMeta = 'rrze_notice_edit_dismissed';

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
    }

    public function onLoaded()
    {
        add_action('admin_head', [$this, 'adminHead'], 99);
        //add_filter('user_can_richedit', [$this, 'disableRichedit']);
    }

    public function adminHead()
    {
        $this->notices = new Notices();
        $this->previewNotice();
    }

    protected function previewNotice()
    {
        $screen = get_current_screen();
        if ($screen->id != $this->customPostType) {
            return;
        }
        
        global $pagenow;
        if ($pagenow == 'post-new.php') {
            return;
        }

        global $post;
        $notice = $post;
        $noticeId = $post->ID;

        $id = $noticeId;
        $title = get_post_meta($noticeId, 'rrze_notices_hide_title', false) ? false : $notice->post_title;
        $content = $notice->post_content;
        $noticeType = get_post_meta($noticeId, 'rrze_notices_type', 'info');
        $type = array_key_exists($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
        $altStyle = ! get_post_meta($noticeId, 'rrze_notices_alt_style', 0) ? false : true;

        $currentUserId = get_current_user_id();
        delete_user_meta($currentUserId, $this->userMeta);
        
        if (! $content) {
            return;
        }
        $this->notices->add(
            $id,
            $title,
            $content,
            [
                'scope'         => 'user',
                'screens'       => [$this->customPostType],
                'type'          => $type,
                'alt_style'     => $altStyle,
                'capability'    => 'read',
                'option_prefix' => $this->userMeta
            ]
        );

        $this->notices->boot();
    }

    public function disableRichedit($can)
    {
        global $post;

        if ($this->customPostType == $post->post_type) {
            return false;
        }
        return $can;
    }
}
