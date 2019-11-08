<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class EditNotice
{
    protected $customPostType;

    protected $userMeta = 'rrze_notice_edit_dismissed';

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
    }

    public function onLoaded() {
        add_action('admin_head', [$this, 'adminHead']);
        add_filter('user_can_richedit', [$this, 'disableRichedit']);
    }

    public function adminHead() {
        $this->previewNotice();
    }

    protected function previewNotice() {
        $screen = get_current_screen();
        if ($screen->id != $this->customPostType) {
            return;
        }        
        
        global $post;
        
        $notice = $post;
        $noticeId = $post->ID;

        $id = $noticeId;
        $title = get_post_meta($noticeId,'rrze_notices_hide_title', false) ? false : $notice->post_title;
        $content = $notice->post_content;        
        $noticeType = get_post_meta($noticeId,'rrze_notices_type', 'info');
        $type = in_array($noticeType, Util::getNoticeTypes()) ? $noticeType : 'info';
        $altStyle = ! get_post_meta($noticeId,'rrze_notices_alt_style', 0) ? false : true;

        $currentUserId = get_current_user_id();
        delete_user_meta($currentUserId, $this->userMeta);
        
        $notices = new Notices();
        $notices->add(
            $id,
            $title,
            $content,
            [
                'scope'         => 'user',
                'screens'       => [$this->customPostType],
                'type'          => $type,
                'alt_style'     => $altStyle,
                'option_prefix' => $this->userMeta
            ]
        );
        $notices->boot();
    }

    public function disableRichedit($can) {
        global $post;

        if ($this->customPostType == $post->post_type) {
            return false;
        }
        return $can;
    }   
}