<?php

declare(strict_types=1);

namespace RRZE\Notices\CPT;

defined('ABSPATH') || exit;

/**
 * Argument builder for the notice custom post type.
 *
 * Provides preset configurations (public/nonPublic) that can be
 * extended with merge() before passing to register_post_type().
 */
class CPTNotice
{
    /**
     * Post type.
     */
    public const POST_TYPE = 'notice';

    /**
     * CPTNotice Constructor.
     *
     * @param array $args
     */
    private function __construct(private array $args)
    {
    }

    /**
     * Set default arguments.
     *
     * @return CPTNotice
     */
    private static function default(): self
    {
        return new self(
            [
                'label' => _x(
                    'Notices',
                    'backend: post type label',
                    'rrze-notices'
                ),
                'labels' => self::labels(),
                'hierarchical' => false,
                'supports' => [
                    'title',
                ],
                'capability_type' => ['notice', 'notices'],
                'map_meta_cap' => true,
            ]
        );
    }

    /**
     * Merge the default arguments with the public arguments.
     *
     * @return CPTNotice
     */
    public static function public(): self
    {
        return self::default()->merge(
            [
                'public' => true,
                'show_in_rest' => true,
            ]
        );
    }

    /**
     * Merge the default arguments with the non-public arguments.
     *
     * @return CPTNotice
     */
    public static function nonPublic(): self
    {
        return self::default()->merge(
            [
                'public' => false,
                'show_in_rest' => false,
            ]
        );
    }


    /**
     * Get the labels.
     *
     * @return array
     */
    private static function labels(): array
    {
        return [
            'name' => _x(
                'Notices',
                'backend: post type label (general name)',
                'rrze-notices'
            ),
            'singular_name' => _x(
                'Notice',
                'backend: post type label (singular name)',
                'rrze-notices'
            ),
            'add_new' => _x(
                'Add New',
                'backend: post type label',
                'rrze-notices'
            ),
            'add_new_item' => _x(
                'Add New Notice',
                'backend: post type label',
                'rrze-notices'
            ),
            'new_item' => _x(
                'New Notice',
                'backend: post type label',
                'rrze-notices'
            ),
            'edit_item' => _x(
                'Edit Notice',
                'backend: post type label',
                'rrze-notices'
            ),
            'view_item' => _x(
                'View Notice',
                'backend: post type label',
                'rrze-notices'
            ),
            'view_items' => _x(
                'View Notices',
                'backend: post type label',
                'rrze-notices'
            ),
            'search_items' => _x(
                'Search Notices',
                'backend: post type label',
                'rrze-notices'
            ),
            'not_found' => _x(
                'No notices found.',
                'backend: post type label',
                'rrze-notices'
            ),
            'not_found_in_trash' => _x(
                'No notices found in Trash.',
                'backend: post type label',
                'rrze-notices'
            ),
            'all_items' => _x(
                'All Notices',
                'backend: post type label',
                'rrze-notices'
            ),
            'archives' => _x(
                'Notice Archives',
                'backend: post type label',
                'rrze-notices'
            ),
            'attributes' => _x(
                'Notice Attributes',
                'backend: post type label',
                'rrze-notices'
            ),
            'insert_into_item' => _x(
                'Insert into notice',
                'backend: post type label',
                'rrze-notices'
            ),
            'uploaded_to_this_item' => _x(
                'Uploaded to this notice',
                'backend: post type label',
                'rrze-notices'
            ),
            'filter_items_list' => _x(
                'Filter notice list',
                'backend: post type label',
                'rrze-notices'
            ),
            'items_list_navigation' => _x(
                'Notices list navigation',
                'backend: post type label',
                'rrze-notices'
            ),
            'items_list' => _x(
                'Notices list',
                'backend: post type label',
                'rrze-notices'
            ),
            'item_published' => _x(
                'Notice published.',
                'backend: post type label',
                'rrze-notices'
            ),
            'item_published_privately' => _x(
                'Notice published privately.',
                'backend: post type label',
                'rrze-notices'
            ),
            'item_reverted_to_draft' => _x(
                'Notice reverted to draft.',
                'backend: post type label',
                'rrze-notices'
            ),
            'item_scheduled' => _x(
                'Notice scheduled.',
                'backend: post type label',
                'rrze-notices'
            ),
            'item_updated' => _x(
                'Notice updated.',
                'backend: post type label',
                'rrze-notices'
            ),
        ];
    }

    /**
     * Get the arguments.
     *
     * @return array
     */
    public function args(): array
    {
        return $this->args;
    }

    /**
     * Merge the arguments.
     *
     * @param array $args
     * @return CPTNotice
     */
    public function merge(array $args): self
    {
        return new self(array_merge($this->args, $args));
    }
}
