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
        add_filter('manage_edit-' . $this->customPostType . '_sortable_columns', [$this, 'manageSortable']);
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
        if (!is_super_admin() || !is_multisite() || !Utils::isPluginActiveForNetwork()) {
            unset($columns['network_wide']);
        }
        return $columns;
    }

    public function managePosts($column, $postId)
    {
        switch ($column) {
            case 'type':
                $allTypes = Utils::getNoticeTypes();
                $noticeType = Utils::getType($postId);
                $type = isset($allTypes[$noticeType]) ? $allTypes[$noticeType] : $allTypes[array_key_first($allTypes)];
                echo $type;
                break;
            case 'expiration':
                $expiration = Utils::getExpiration($postId);
                $expirationGmt = Utils::getExpirationGmt($postId);
                $expired = Utils::isExpired($expirationGmt);
                $expireDateTime = Utils::dateTimeFormat($expiration);
                if ($expired) {
                    printf('<span class="expired">%s</span><br><span class="expired">%s</span>', __('Expired', 'rrze-notices'), $expireDateTime);
                } else {
                    printf('<span>%s</span>', $expireDateTime);
                }
                break;
            case 'network_wide':
                if (Utils::networkWide($postId)) {
                    echo '<span class="dashicons dashicons-yes"></span>';
                }
                break;
            case 'visibility':
                $availableRoles = array_merge(['all' => __('All', 'rrze-notices')], Utils::getRoles());
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

    public function manageSortable($columns)
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
