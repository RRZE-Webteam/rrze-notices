<?php

declare(strict_types=1);

namespace RRZE\Notices;

defined('ABSPATH') || exit;

/**
 * Stores plugin file metadata and resolves plugin-relative paths and URLs.
 */
class Plugin
{
    /**
     * The full path and filename of the plugin.
     * @var string
     */
    protected $pluginFile;

    /**
     * The basename of the plugin.
     * @var string
     */
    protected $basename;

    /**
     * The filesystem directory path (with trailing slash) for the plugin.
     * @var string
     */
    protected $directory;

    /**
     * The URL directory path (with trailing slash) for the plugin.
     * @var string
     */
    protected $url;

    /**
     * The version of the plugin.
     * @var string
     */
    protected $version;

    /**
     * __construct method
     * @param string $pluginFile The full path and filename of the plugin.
     */
    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Initialize derived plugin metadata.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->setBasename()
            ->setDirectory()
            ->setUrl()
            ->setVersion();
    }

    /**
     * getFile method
     * Get the full path and filename of the plugin.
     * @return string The full path and filename.
     */
    public function getFile(): string
    {
        return $this->pluginFile;
    }

    /**
     * getBasename method
     * Get the basename of the plugin.
     * @return string The basename.
     */
    public function getBasename(): string
    {
        return $this->basename;
    }

    /**
     * Set the plugin basename.
     * @return self This plugin metadata object.
     */
    private function setBasename(): self
    {
        $this->basename = plugin_basename($this->pluginFile);
        return $this;
    }

    /**
     * getDirectory method
     * Get the filesystem directory path (with trailing slash) for the plugin.
     * @return string The filesystem directory path.
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Set the filesystem directory path with a trailing slash.
     * @return self This plugin metadata object.
     */
    private function setDirectory(): self
    {
        $this->directory = rtrim(plugin_dir_path($this->pluginFile), '/') . '/';
        return $this;
    }

    /**
     * getPath method
     * Get the filesystem directory path (with trailing slash) for the plugin.
     * @param string $path The path name.
     * @return string The filesystem directory path.
     */
    public function getPath(string $path = ''): string
    {
        return $this->directory . ($path ? trim($path, '/') . '/' : '');
    }

    /**
     * getUrl method
     * Get the URL directory path (with trailing slash) for the plugin.
     * @param string $path The path name.
     * @return string The URL directory path.
     */
    public function getUrl(string $path = ''): string
    {
        return $this->url . ($path ? trim($path, '/') . '/' : '');
    }

    /**
     * Set the URL directory path with a trailing slash.
     * @return self This plugin metadata object.
     */
    private function setUrl(): self
    {
        $this->url = rtrim(plugin_dir_url($this->pluginFile), '/') . '/';
        return $this;
    }

    /**
     * getSlug method
     * Get the slug of the plugin.
     * @return string The slug.
     */
    public function getSlug(): string
    {
        return sanitize_title(dirname($this->basename));
    }

    /**
     * getVersion method
     * Get the version of the plugin.
     * @return string The version.
     */
    public function getVersion(): string
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return bin2hex(random_bytes(4));
        }
        return $this->version;
    }

    /**
     * Read and store the plugin version header.
     * @return self This plugin metadata object.
     */
    private function setVersion(): self
    {
        $headers = ['Version' => 'Version'];
        $fileData = get_file_data($this->pluginFile, $headers, 'plugin');
        if (isset($fileData['Version'])) {
            $this->version = $fileData['Version'];
        };
        return $this;
    }

}
