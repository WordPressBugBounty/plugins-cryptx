<?php

namespace CryptX;

final class Config {
    /**
     * Default configuration options for the application.
     *
     * This array provides the default settings and values for various
     * features and behaviors of the application. These options can be
     * customized to suit specific implementation requirements.
     *
     * Keys and their purposes:
     * - 'version': The version of the application (default: null).
     * - 'at': Replacement string for the "@" symbol (default: ' [at] ').
     * - 'dot': Replacement string for the "." symbol (default: ' [dot] ').
     * - 'css_id': CSS ID to use for specific elements (default: '').
     * - 'css_class': CSS class to use for specific elements (default: '').
     * - 'the_content': Flag to enable processing on content (default: 1).
     * - 'render_block': Flag to control rendering of blocks (default: 1).
     * - 'the_meta_key': Flag to enable processing on meta keys (default: 1).
     * - 'the_excerpt': Flag to enable processing on excerpts (default: 1).
     * - 'comment_text': Flag to enable processing on comments (default: 1).
     * - 'widget_text': Flag to enable processing in widgets (default: 1).
     * - 'java': Flag indicating JavaScript-related configurations (default: 1).
     * - 'load_java': Flag to enable JavaScript loading (default: 1).
     * - 'opt_linktext': Option for link text settings (default: 0).
     * - 'autolink': Flag to enable auto-linking of content (default: 1).
     * - 'alt_linktext': Alternative text for links (default: '').
     * - 'alt_linkimage': Alternative image for links (default: '').
     * - 'http_linkimage_title': Link image title with HTTP reference (default: '').
     * - 'alt_linkimage_title': Alternative title for the link image (default: '').
     * - 'excludedIDs': IDs to exclude from processing (default: '').
     * - 'metaBox': Flag to enable or disable meta box features (default: 1).
     * - 'alt_uploadedimage': Alternative uploaded image setting (default: '0').
     * - 'c2i_font': Custom font setting (default: null).
     * - 'c2i_fontSize': Font size for configuration (default: 10).
     * - 'c2i_fontRGB': Font color in RGB format (default: '#000000').
     * - 'echo': Flag to enable output directly to the browser (default: 1).
     * - 'whiteList': Comma-separated string of allowed file extensions (default: 'jpeg,jpg,png,gif').
     * - 'disable_rss': Flag to disable CryptX in RSS feeds by default (default: 1).
     * - 'encryption_mode': Encryption mode setting (default: 'secure').
     * - 'encryption_password': Password for encryption; auto-generated if null (default: null).
     * - 'use_secure_encryption': Flag to enable secure encryption by default (default: 1).
     */
    private const DEFAULT_OPTIONS = [
        'version' => null,
        'at' => ' [at] ',
        'dot' => ' [dot] ',
        'css_id' => '',
        'css_class' => '',
        'the_content' => 1,
        'the_meta_key' => 1,
        'the_excerpt' => 1,
        'comment_text' => 1,
        'widget_text' => 1,
        'java' => 1,
        'load_java' => 1,
        'opt_linktext' => 0,
        'autolink' => 1,
        'alt_linktext' => '',
        'alt_linkimage' => '',
        'http_linkimage_title' => '',
        'alt_linkimage_title' => '',
        'excludedIDs' => '',
        'metaBox' => 1,
        'alt_uploadedimage' => '0',
        'c2i_font' => null,
        'c2i_fontSize' => 10,
        'c2i_fontRGB' => '#000000',
        'echo' => 1,
        'whiteList' => 'jpeg,jpg,png,gif',
        'disable_rss' => 1,
        'encryption_mode' => 'secure',
        'encryption_password' => null,
        'use_secure_encryption' => 1,
    ];

    /**
     * An array of filter names used within the application.
     * These filters are commonly applied to various types of content,
     * including posts, comments, and widgets.
     */
    private const FILTERS = ['the_content', 'the_meta_key', 'the_excerpt', 'comment_text', 'widget_text'];

    // Define the actual widget filters that will be used when widget_text is enabled
    private const WIDGET_FILTERS = [
        'widget_text',                    // Legacy text widget (pre-4.9)
        'widget_text_content',            // Modern text widget (4.9+)
        'widget_custom_html_content'      // Custom HTML widget (4.8.1+)
    ];

    private array $options;
    private array $originalOptions;

    public function __construct(array $options = []) {
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);
        $this->originalOptions = $this->options;
    }

    public function getActiveFilters(): array {
        return array_filter(self::FILTERS, fn($filter) =>
            isset($this->options[$filter]) && $this->options[$filter]
        );
    }

    public function isMetaBoxEnabled(): bool {
        return (bool) ($this->options['metaBox'] ?? false);
    }

    public function isAutolinkEnabled(): bool {
        return (bool) ($this->options['autolink'] ?? false);
    }

    public function getLinkTextOption(): int {
        return (int) ($this->options['opt_linktext'] ?? 0);
    }

    public function getFontSettings(): array {
        return [
            'font' => $this->options['c2i_font'],
            'size' => (int) $this->options['c2i_fontSize'],
            'color' => $this->options['c2i_fontRGB']
        ];
    }

    public function getCssSettings(): array {
        return [
            'id' => $this->options['css_id'],
            'class' => $this->options['css_class']
        ];
    }

    public function getEmailReplacements(): array {
        return [
            'at' => $this->options['at'],
            'dot' => $this->options['dot']
        ];
    }

    public function getImageSettings(): array {
        return [
            'url' => $this->options['alt_linkimage'],
            'title' => $this->options['http_linkimage_title'],
            'uploaded_id' => $this->options['alt_uploadedimage'],
            'uploaded_title' => $this->options['alt_linkimage_title']
        ];
    }

    public function getExcludedIds(): array {
        $ids = $this->options['excludedIDs'];
        return $ids ? array_map('trim', explode(',', $ids)) : [];
    }

    public function getVersion(): ?string {
        return $this->options['version'];
    }

    /**
     * Get the actual widget filters to be applied
     *
     * @return array
     */
    public function getWidgetFilters(): array {
        return self::WIDGET_FILTERS;
    }

    public function updateFromShortcode(array $attributes, string $tag): void {
        $this->originalOptions = $this->options;
        $shortcodeOptions = shortcode_atts(
            $this->options,
            array_change_key_case($attributes, CASE_LOWER),
            $tag
        );
        $this->options = array_merge($this->options, $shortcodeOptions);
    }

    public function restoreOriginalOptions(): void {
        $this->options = $this->originalOptions;
    }

    public function save(): void {
        update_option('cryptX', $this->options);
    }

    public function update(array $newOptions): void
    {
        // Convert checkbox values to integers
        foreach (['the_content', 'the_meta_key', 'the_excerpt', 'comment_text',
                     'widget_text', 'autolink', 'metaBox', 'disable_rss', 'use_secure_encryption'] as $key) {
            if (isset($newOptions[$key])) {
                $newOptions[$key] = (int)$newOptions[$key];
            }
        }

        $this->options = array_merge($this->options, $newOptions);
        $this->save(); // Save immediately after update
    }

    public function get(string $key, $default = null) {
        return $this->options[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->options[$key]);
    }

    public function getAll(): array {
        return $this->options;
    }

    /**
     * Resets all options to their default values
     *
     * @return void
     */
    public function reset(): void
    {
        $this->options = self::DEFAULT_OPTIONS;

        // Set version and default font
        $this->options['version'] = CRYPTX_VERSION;

        // Set default font if available
        $fontFiles = glob(CRYPTX_DIR_PATH . 'fonts/*.ttf');
        if (!empty($fontFiles)) {
            $this->options['c2i_font'] = basename($fontFiles[0]);
        }

        $this->save();
    }

    /**
     * Retrieves the encryption mode configured in the options.
     *
     * @return string Returns the encryption mode as a string. Defaults to 'secure' if not set.
     */
    public function getEncryptionMode(): string
    {
        return $this->options['encryption_mode'] ?? 'secure';
    }

    /**
     * Checks if secure encryption is enabled in the options.
     *
     * @return bool Returns true if secure encryption is enabled, false otherwise.
     */
    public function isSecureEncryptionEnabled(): bool
    {
        return (bool) ($this->options['use_secure_encryption'] ?? true);
    }

    /**
     * Retrieves the encryption password configured in the options or generates a secure password if not set.
     *
     * @return string Returns the encryption password as a string.
     */
    public function getEncryptionPassword(): string
    {
        if (empty($this->options['encryption_password'])) {
            // Generate a secure password based on WordPress keys
            $this->options['encryption_password'] = hash('sha256',
                (defined('AUTH_KEY') ? AUTH_KEY : '') .
                (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '') .
                get_site_url()
            );
            $this->save();
        }
        return $this->options['encryption_password'];
    }
}