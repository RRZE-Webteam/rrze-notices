<?php

declare(strict_types=1);

namespace RRZE\Notices\Display;

defined('ABSPATH') || exit;

/**
 * Immutable identifier for a notice within a multisite network.
 *
 * Format: "blogId-postId" (e.g. "3-42"). Used as array key in
 * network options, as user-meta key for dismissals, and in URLs.
 */
final class NoticeId
{
    private function __construct(private readonly int $blogId, private readonly int $postId)
    {
    }

    /**
     * Create an identifier for a notice post.
     *
     * @param int $postId Notice post ID.
     * @param int|null $blogId Site ID, or the current site when omitted.
     * @return self
     */
    public static function forPost(int $postId, ?int $blogId = null): self
    {
        $blogId = $blogId ?? get_current_blog_id();
        if ($blogId < 1 || $postId < 1) {
            throw new \InvalidArgumentException('Notice IDs require positive site and post IDs.');
        }

        return new self($blogId, $postId);
    }

    /**
     * Parse a stored identifier in the canonical "blog-post" form.
     *
     * @param mixed $value Candidate identifier.
     * @return self|null Parsed identifier, or null when invalid.
     */
    public static function fromString(mixed $value): ?self
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = (string)$value;
        if (!preg_match('/^([1-9]\d*)-([1-9]\d*)$/', $value, $matches)) {
            return null;
        }

        return new self((int)$matches[1], (int)$matches[2]);
    }

    /**
     * Return the originating site ID.
     *
     * @return int
     */
    public function blogId(): int
    {
        return $this->blogId;
    }

    /**
     * Return the notice post ID.
     *
     * @return int
     */
    public function postId(): int
    {
        return $this->postId;
    }

    /**
     * Serialize the identifier for URLs, array keys, and user metadata.
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('%d-%d', $this->blogId, $this->postId);
    }
}
