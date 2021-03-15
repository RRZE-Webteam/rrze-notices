<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

use RRZE\Notices\CPT;
use RRZE\Notices\Util;

class Post
{
    protected $customPostType;

    protected $siteOptionsName;

    protected $siteOptions;

    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();

        $this->siteOptionsName = Options::getSiteOptionName();
        $this->siteOptions = Options::getSiteOptions();
    }

    public function onLoaded()
    {
        add_action('save_post', [$this, 'savePost'], 99, 2);
        add_action('delete_post', [$this, 'deletePost'], 10, 2);
    }

    public function savePost($postId, $post)
    {
        $postId = $post->ID;

        if (
            !is_super_admin()
            || !is_multisite()
            || $post->post_type != $this->customPostType
        ) {
            return;
        }

        $postTypeObject = get_post_type_object($post->post_type);
        if (!current_user_can($postTypeObject->cap->edit_post, $postId)) {
            return;
        }

        $this->deleteNetworkWideSiteOption($postId);
        if (Util::networkWide($postId)) {
            $this->updateNetworkWideSiteOption($postId, $post);
        }
    }

    public function deletePost($postId, $post)
    {
        $postId = $post->ID;

        if (
            !is_super_admin()
            || !is_multisite()
            || $post->post_type != $this->customPostType
        ) {
            return;
        }

        $postTypeObject = get_post_type_object($post->post_type);
        if (!current_user_can($postTypeObject->cap->edit_post, $postId)) {
            return;
        }

        $this->deleteNetworkWideSiteOption($postId);
    }

    protected function updateNetworkWideSiteOption(int $postId, \WP_Post $post)
    {
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);

        $type = Util::getType($postId);
        $expiration = Util::getExpiration($postId);
        $expirationGmt = Util::getExpirationGmt($postId);
        $altStyle = Util::altStyle($postId);
        $hideTitle = Util::hideTitle($postId);
        $visibility = Util::getVisibility($postId);

        $this->siteOptions->network_wide[$noticeId] = (object)[
            'title' => $post->post_title,
            'content' => $post->post_content,
            'date' => $post->post_date,
            'date_gmt' => $post->post_date_gmt,
            'type' => $type,
            'expiration' => $expiration,
            'expiration_gmt' => $expirationGmt,
            'hide_title' => $hideTitle,
            'alt_style' => $altStyle,
            'visibility' => $visibility
        ];
        update_site_option($this->siteOptionsName, $this->siteOptions);
    }

    protected function deleteNetworkWideSiteOption(int $postId)
    {
        $blogId = get_current_blog_id();
        $noticeId = sprintf('%1$d-%2$d', $blogId, $postId);
        if (isset($this->siteOptions->network_wide[$noticeId])) {
            unset($this->siteOptions->network_wide[$noticeId]);
            update_site_option($this->siteOptionsName, $this->siteOptions);
        }
    }
}
