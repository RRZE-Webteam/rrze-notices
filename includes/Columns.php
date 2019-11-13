<?php

namespace RRZE\Notices;

defined('ABSPATH') || exit;

class Columns
{
    protected $customPostType;
    
    protected $siteOptions;
    
    public function __construct()
    {
        $this->customPostType = CPT::getPostTypeName();
        $this->siteOptions = Options::getSiteOptions();
    }

    public function onLoaded()
    {
        add_action('manage_edit-' . $this->customPostType . '_columns', [$this, 'manageEdit']);
        add_action('manage_' . $this->customPostType . '_posts_custom_column', [$this, 'managePosts'], 10, 2);
        add_filter('manage_edit-' . $this->customPostType . '_sortable_columns', [$this, 'manageSortale']);
        add_action('load-edit.php', [$this, 'loadEdit']);
    }

    public function manageEdit($columns)
    {
        $columns = [
            'cb' => '<input type="checkbox">',
            'title' => __('Notice', 'rrze-notices'),
            'type' => __('Type', 'rrze-notices'),
            'expiration' => __('Expiration', 'rrze-notices'),
            'visibility' => __('Visibility', 'rrze-notices'),
            'network_wide' => __('Network-Wide', 'rrze-notices'),
            'date' => __('Date', 'rrze-notices')
        ];
        if (!Util::isAllowedNetworkWide()) {
            unset($columns['network_wide']);
        }
        return $columns;
    }

    public function managePosts($column, $postId)
    {
        switch ($column) {
            case 'type':
                $allTypes = Util::getNoticeTypes();
                $noticeType = get_post_meta($postId, 'rrze_notices_type', true);
                $type = isset($allTypes[$noticeType]) ? $allTypes[$noticeType] : $allTypes[array_key_first($allTypes)];
                echo $type;
                break;
            case 'expiration':
                    $post = get_post($postId);
                    $allIntervals = Util::getExpirationIntervals();
                    $expiration = get_post_meta($postId, 'rrze_notices_expiration', true);
                    $expiration = isset($allIntervals[$expiration]) ? $allIntervals[$expiration] : $allIntervals[array_key_first($allIntervals)];
                    $expired = Util::isExpired($expiration, $post->post_date_gmt);
                    echo !$expired ? $expiration : __('Expired!', 'rrze_notices');
                    break;
            case 'network_wide':
                global $wpdb;
                $blogId = $wpdb->blogid;
                $id = sprintf('%d-%d', $blogId, $postId);
                if (isset($this->siteOptions->network_wide[$id])) {
                    echo '<span class="dashicons dashicons-yes"></span>';
                }
                break;
            case 'visibility':
                $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Util::getRoles());
                $roleVisibility = get_post_meta($postId, 'rrze_notices_visibility', true);
                if (!empty($roleVisibility)) {
                    $roleVisibility = !is_array($roleVisibility) ? [$roleVisibility] : $roleVisibility;
                } else {
                    $roleVisibility = [];
                }
                $roles = [];
                foreach ($roleVisibility as $k) {
                    if (isset($availableRoles[$k])) {
                        $roles[] = $availableRoles[$k];
                    }
                }
                echo !empty($roles) ? implode(', ', $roles) : '&#65293;';
                break;
            default:
                break;
        }
    }

    public function manageSortale($columns)
    {
        $columns['type'] = 'type';
        $columns['expiration'] = 'expiration';
        return $columns;
    }

    public function loadEdit()
    {
        add_action('pre_get_posts', [$this, 'columnOrderby']);
    }

    public function columnOrderby($query)
    {
        if ($this->customPostType != $query->get('post_type')) {
            return;
        }
        $orderBy = $query->get('orderby');
        switch ($orderBy) {
            case 'type':
                $query->set('meta_key', 'rrze_notices_type');
                break;
            case 'expiration':
                $query->set('meta_key', 'rrze_notices_expiration');
                break;
            default:
                break;
        }
        $query->set('orderby', 'meta_value');
    }
}
