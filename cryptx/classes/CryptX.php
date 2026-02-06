<?php

namespace CryptX;

final class CryptX
{
    const NOT_FOUND = false;
    const SUBJECT_IDENTIFIER = "?subject=";
    const ASCII_VALUES_BLACKLIST = ['32', '34', '39', '60', '62', '63', '92', '94', '96', '127'];
    private static ?self $instance = null;
    private static array $cryptXOptions = [];
    private static int $imageCounter = 0;
    private const FONT_EXTENSION = 'ttf';
    private const PAYPAL_DONATION_URL = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4026696';
    private CryptXSettingsTabs $settingsTabs;
    private Config $config;

    private function __construct()
    {
        $this->settingsTabs = new CryptXSettingsTabs($this);
        $this->config = new Config(get_option('cryptX', []));
        self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults();
    }

    /**
     * Retrieves the singleton instance of the class.
     *
     * @return self The singleton instance of the class.
     */
    public static function get_instance(): self
    {
        $needs_initialization = !(self::$instance instanceof self);

        if ($needs_initialization) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Initializes the CryptX plugin by setting up version checks, applying filters, registering core hooks, initializing meta boxes (if enabled), and adding additional hooks.
     *
     * @return void
     */
    public function startCryptX(): void
    {
        $this->checkAndUpdateVersion();
        $this->addUniversalWidgetFilters(); // Add this line
        $this->initializePluginFilters();
        $this->registerCoreHooks();
        $this->initializeMetaBoxIfEnabled();
        $this->registerAdditionalHooks();
    }

    /**
     * Checks the current version of the application against the stored version and updates settings if the application version is newer.
     *
     * @return void
     */
    private function checkAndUpdateVersion(): void
    {
        $currentVersion = self::$cryptXOptions['version'] ?? null;
        if ($currentVersion && version_compare(CRYPTX_VERSION, $currentVersion) > 0) {
            $this->updateCryptXSettings();
        }
    }

    /**
     * Initializes and registers plugin filters based on the configuration settings.
     *
     * This method retrieves the active filters from the configuration and applies
     * each filter by either adding widget-specific filters or other plugin-related filters.
     * If the theme is a block theme, it transforms certain filters to an appropriate block-based equivalent.
     * It also checks if autolink functionality is enabled and adds the respective filters when applicable.
     *
     * @return void
     */
    public function initializePluginFilters(): void
    {
        if (empty($this->config)) {
            return;
        }

        $activeFilters = $this->config->getActiveFilters();

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            $activeFilters = array_map(
                    fn($value) => $value === 'the_content' ? 'render_block' : $value,
                    $activeFilters
            );
        }

        foreach ($activeFilters as $filter) {
            if ($filter === 'widget_text') {
                $this->addWidgetFilters();
            } else {
                // Add autolink filters for non-widget filters if autolink is enabled
                if ($this->config->isAutolinkEnabled()) {
                    $this->addAutoLinkFilters($filter, 11);
                }
                $this->addOtherFilters($filter);
            }
        }
    }

    /**
     * Registers core hooks for the plugin's functionality.
     *
     * @return void
     */
    private function registerCoreHooks(): void
    {
        add_action('activate_' . CRYPTX_BASENAME, [$this, 'installCryptX']);
        add_action('wp_enqueue_scripts', [$this, 'loadJavascriptFiles']);
    }

    /**
     * Initializes the meta box functionality if enabled in the configuration.
     *
     * This method checks whether the meta box feature is enabled in the cryptX options.
     * If enabled, it adds the necessary actions for administering the meta box and managing the posts' exclusion list.
     *
     * @return void
     */
    private function initializeMetaBoxIfEnabled(): void
    {
        if (!isset(self::$cryptXOptions['metaBox']) || !self::$cryptXOptions['metaBox']) {
            return;
        }

        add_action('admin_menu', [$this, 'metaBox']);
        add_action('wp_insert_post', [$this, 'addPostIdToExcludedList']);
        add_action('wp_update_post', [$this, 'addPostIdToExcludedList']);
    }

    /**
     * Registers additional WordPress hooks and shortcodes.
     *
     * @return void
     */
    private function registerAdditionalHooks(): void
    {
        add_filter('plugin_row_meta', [$this, 'add_plugin_action_links'], 10, 2);
        add_filter('init', [$this, 'cryptXtinyUrl']);
        add_shortcode('cryptx', [$this, 'cryptXShortcode']);
    }

    /**
     * Retrieves the default options for CryptX configuration.
     *
     * @return array The default CryptX options, including version and font settings.
     */
    public function getCryptXOptionsDefaults(): array
    {
        return array_merge(
                $this->config->getAll(),
                [
                        'version' => CRYPTX_VERSION,
                        'c2i_font' => $this->getDefaultFont()
                ]
        );
    }

    /**
     * Retrieves the default font from the available fonts directory.
     *
     * @return string|null Returns the name of the default font found, or null if no fonts are available.
     */
    private function getDefaultFont(): ?string
    {
        $availableFonts = $this->getFilesInDirectory(
                CRYPTX_DIR_PATH . 'fonts',
                [self::FONT_EXTENSION]
        );

        return $availableFonts[0] ?? null;
    }

    /**
     * Loads the cryptX options with default values.
     *
     * @return array The cryptX options array with default values.
     */
    public function loadCryptXOptionsWithDefaults(): array
    {
        $defaultValues = $this->getCryptXOptionsDefaults();
        $currentOptions = get_option('cryptX');

        return wp_parse_args($currentOptions, $defaultValues);
    }

    /**
     * Saves the cryptX options by updating the 'cryptX' option with the saved options merged with the default options.
     *
     * @param array $saveOptions The options to be saved.
     *
     * @return void
     */
    public function saveCryptXOptions(array $saveOptions): void
    {
        update_option('cryptX', wp_parse_args($saveOptions, $this->loadCryptXOptionsWithDefaults()));
    }

    /**
     * Decodes attributes from their encoded state and returns the decoded array.
     *
     * @param array $attributes The array of attributes, potentially encoded.
     * @return array The array of decoded attributes with the 'encoded' key removed if present.
     */
    private function decodeAttributes(array $attributes): array
    {
        if (($attributes['encoded'] ?? '') !== 'true') {
            return $attributes;
        }

        $decodedAttributes = array_map(
                fn($value) => $this->decodeString($value),
                $attributes
        );
        unset($decodedAttributes['encoded']);

        return $decodedAttributes;
    }

    /**
     * Processes the provided shortcode attributes and content, encrypts content, and optionally creates links for email addresses.
     *
     * @param array $atts Attributes passed to the shortcode. Defaults to an empty array.
     * @param string $content The content enclosed within the shortcode. Defaults to an empty string.
     * @param string $tag The name of the shortcode tag. Defaults to an empty string.
     * @return string The processed and encrypted content, optionally including links for email addresses.
     */
    public function cryptXShortcode(array $atts = [], string $content = '', string $tag = ''): string
    {
        // Decode attributes if needed
        $attributes = $this->decodeAttributes($atts);

        // Update options if attributes provided
        if (!empty($attributes)) {
            self::$cryptXOptions = shortcode_atts(
                    $this->loadCryptXOptionsWithDefaults(),
                    array_change_key_case($attributes, CASE_LOWER),
                    $tag
            );
        }

        // Process content (inline the encryptAndLinkContent logic)
        if (self::$cryptXOptions['autolink'] ?? false) {
            $content = $this->addLinkToEmailAddresses($content, true);
        }
        $content = $this->findEmailAddressesInContent($content, true);
        $processedContent = $this->replaceEmailInContent($content, true);

        // Reset options to defaults
        self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults();

        return $processedContent;
    }

    /**
     * Encrypts and links content.
     *
     * @param string $content The content to be encrypted and linked.
     *
     * @return string The encrypted and linked content.
     */
    private function encryptAndLinkContent(string $content, bool $shortcode = false): string
    {
        $content = $this->findEmailAddressesInContent($content, $shortcode);

        return $this->replaceEmailInContent($content, $shortcode);
    }

    /**
     * Retrieves the ID of the current post.
     *
     * @return int The current post ID if available, or -1 if no post object is present.
     */
    private function getCurrentPostId(): int
    {
        global $post;
        return (is_object($post)) ? $post->ID : -1;
    }


    /**
     * Generates and returns a tiny URL image.
     *
     * @return void
     */
    public function cryptXtinyUrl(): void
    {
        $url = (!empty($_SERVER['REQUEST_URI'])) ? esc_url(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $params = explode('/', $url);
        if (count($params) > 1) {
            $tiny_url = $params[count($params) - 2];
            if ($tiny_url == md5(get_bloginfo('url'))) {
                $font = CRYPTX_DIR_PATH . 'fonts/' . str_replace(' ', '_', self::$cryptXOptions['c2i_font']);
                $msg = $params[count($params) - 1];
                $size = self::$cryptXOptions['c2i_fontSize'];
                $pad = 1;
                $rgb = str_replace("#", "", self::$cryptXOptions['c2i_fontRGB']);
                $red = hexdec(substr($rgb, 0, 2));
                $grn = hexdec(substr($rgb, 2, 2));
                $blu = hexdec(substr($rgb, 4, 2));
                $bounds = ImageTTFBBox($size, 0, $font, "W");
                $font_height = abs($bounds[7] - $bounds[1]);
                $bounds = ImageTTFBBox($size, 0, $font, $msg);
                $width = abs($bounds[4] - $bounds[6]);
                $height = abs($bounds[7] - $bounds[1]);
                $offset_y = $font_height + abs(($height - $font_height) / 2) - 1;
                $offset_x = 0;
                $image = imagecreatetruecolor($width + ($pad * 2), $height + ($pad * 2));
                imagesavealpha($image, true);
                $foreground = ImageColorAllocate($image, $red, $grn, $blu);
                $background = imagecolorallocatealpha($image, 0, 0, 0, 127);
                imagefill($image, 0, 0, $background);
                ImageTTFText($image, $size, 0, round($offset_x + $pad, 0), round($offset_y + $pad, 0), $foreground, $font, esc_html($msg));
                Header("Content-type: image/png");
                imagePNG($image);
                die;
            }
        }
    }

    /**
     * Adds common filters to a given filter name.
     *
     * This function adds the common filter 'autolink' to the provided $filterName.
     *
     * @param string $filterName The name of the filter to add common filters to.
     *
     * @return void
     */
    private function addAutoLinkFilters(string $filterName, $prio = 5): void
    {
        add_filter($filterName, [$this, 'addLinkToEmailAddresses'], $prio);
    }

    /**
     * Adds additional filters to a given filter name.
     *
     * This function adds two additional filters, 'encryptx' and 'replaceEmailInContent',
     * to the specified filter name. The 'encryptx' filter is added with a priority of 12,
     * and the 'replaceEmailInContent' filter is added with a priority of 13.
     *
     * @param string $filterName The name of the filter to add the additional filters to.
     *
     * @return void
     */
    private function addOtherFilters(string $filterName): void
    {
        // Check if this is a widget filter
        $widgetFilters = $this->config->getWidgetFilters();
        $isWidgetFilter = in_array($filterName, $widgetFilters);

        if ($isWidgetFilter) {
            // Use higher priority for widget filters (after autolink at priority 10)
            add_filter($filterName, [$this, 'findEmailAddressesInContent'], 15);
            add_filter($filterName, [$this, 'replaceEmailInContent'], 16);
        } else {
            // Standard priorities for other filters
            add_filter($filterName, [$this, 'findEmailAddressesInContent'], 12);
            add_filter($filterName, [$this, 'replaceEmailInContent'], 13);
        }
    }


    /**
     * Adds and applies widget filters from the configuration.
     *
     * @return void
     */
    private function addWidgetFilters(): void
    {
        $widgetFilters = $this->config->getWidgetFilters();

        foreach ($widgetFilters as $widgetFilter) {
            $this->addAutoLinkFilters($widgetFilter, 11);
            $this->addOtherFilters($widgetFilter);
        }
    }

    /**
     * Checks if a given ID is excluded based on the 'excludedIDs' variable.
     *
     * @param int $ID The ID to check if excluded.
     *
     * @return bool Returns true if the ID is excluded, false otherwise.
     */
    private function isIdExcluded(int $ID): bool
    {
        $excludedIds = explode(",", self::$cryptXOptions['excludedIDs']);

        return in_array($ID, $excludedIds);
    }

    /**
     * Replaces email addresses in content with link texts.
     *
     * @param string|null $content The content to replace the email addresses in.
     * @param bool $isShortcode Flag indicating whether the method is called from a shortcode.
     *
     * @return string|null The content with replaced email addresses.
     */
    public function replaceEmailInContent(?string $content, bool $isShortcode = false): ?string
    {
        global $post;

        if (self::$cryptXOptions['disable_rss'] && $this->isRssFeed()) return $content;

        // Check if current filter is a widget filter
        $widgetFilters = $this->config->getWidgetFilters();
        $isWidgetContext = in_array(current_filter(), $widgetFilters);

        $postId = (is_object($post)) ? $post->ID : -1;

        // For widgets, always process; for other content, check exclusion rules
        if (($isWidgetContext || !$this->isIdExcluded($postId) || $isShortcode) && !empty($content)) {
            $content = $this->replaceEmailWithLinkText($content);
        }

        return $content;
    }


    /**
     * Replace email addresses in a given content with link text.
     *
     * @param string $content The content to search for email addresses.
     *
     * @return string The content with email addresses replaced with link text.
     */
    private function replaceEmailWithLinkText(string $content): string
    {
        $emailPattern = "/([_a-zA-Z0-9-+]+(\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,}))/i";

        return preg_replace_callback($emailPattern, [$this, 'encodeEmailToLinkText'], $content);
    }

    /**
     * Encode email address to link text.
     *
     * @param array $Match The matched email address.
     *
     * @return string The encoded link text.
     */
    private function encodeEmailToLinkText(array $Match): string
    {
        if ($this->inWhiteList($Match)) {
            return $Match[1];
        }
        switch (self::$cryptXOptions['opt_linktext']) {
            case 1:
                $text = $this->getLinkText();
                break;
            case 2:
                $text = $this->getLinkImage();
                break;
            case 3:
                $img_url = wp_get_attachment_url(self::$cryptXOptions['alt_uploadedimage']);
                $text = $this->getUploadedImage($img_url);
                self::$imageCounter++;
                break;
            case 4:
                $text = antispambot($Match[1]);
                break;
            case 5:
                $text = $this->getImageFromText($Match);
                self::$imageCounter++;
                break;
            default:
                $text = $this->getDefaultLinkText($Match);
        }

        return $text;
    }

    /**
     * Check if the given match is in the whitelist.
     *
     * @param array $Match The match to check against the whitelist.
     *
     * @return bool True if the match is in the whitelist, false otherwise.
     */
    private function inWhiteList(array $Match): bool
    {
        $whiteList = array_filter(array_map('trim', explode(",", self::$cryptXOptions['whiteList'])));
        $tmp = explode(".", $Match[0]);

        return in_array(end($tmp), $whiteList);
    }

    /**
     * Get the link text from cryptXOptions
     *
     * @return string The link text
     */
    private function getLinkText(): string
    {
        return self::$cryptXOptions['alt_linktext'];
    }

    /**
     * Generate an HTML image tag with the link image URL as the source
     *
     * @return string The HTML image tag
     */
    private function getLinkImage(): string
    {
        return "<img src=\"" . self::$cryptXOptions['alt_linkimage'] . "\" class=\"cryptxImage\" alt=\"" . self::$cryptXOptions['alt_linkimage_title'] . "\" title=\"" . antispambot(self::$cryptXOptions['alt_linkimage_title']) . "\" />";
    }

    /**
     * Get the HTML tag for an uploaded image.
     *
     * @param string $img_url The URL of the image.
     *
     * @return string The HTML tag for the image.
     */
    private function getUploadedImage(string $img_url): string
    {
        return "<img src=\"" . $img_url . "\" class=\"cryptxImage cryptxImage_" . self::$imageCounter . "\" alt=\"" . self::$cryptXOptions['http_linkimage_title'] . " title=\"" . antispambot(self::$cryptXOptions['http_linkimage_title']) . "\" />";
    }

    /**
     * Converts a matched image URL into an HTML image element with cryptX classes and attributes.
     *
     * @param array $Match The matched image URL and other related data.
     *
     * @return string Returns the HTML image element.
     */
    private function getImageFromText(array $Match): string
    {
        return "<img src=\"" . get_bloginfo('url') . "/" . md5(get_bloginfo('url')) . "/" . antispambot($Match[1]) . "\" class=\"cryptxImage cryptxImage_" . self::$imageCounter . "\" alt=\"" . antispambot($Match[1]) . "\" title=\"" . antispambot($Match[1]) . "\" />";
    }

    /**
     * Replaces specific characters with values from cryptX options in a given string.
     *
     * @param array $Match The array containing matches from a regular expression search.
     *                     Array format: `[0 => string, 1 => string, ...]`.
     *                     The first element is ignored, and the second element is used as input string.
     *
     * @return string The string with replaced characters or the original array if no matches were found.
     *                     If the input string is an array, the function returns an array with replaced characters
     *                     for each element.
     */
    private function getDefaultLinkText(array $Match): string
    {
        $text = str_replace("@", self::$cryptXOptions['at'], $Match[1]);

        return str_replace(".", self::$cryptXOptions['dot'], $text);
    }

    /**
     * List all files in a directory that match the given filter.
     *
     * @param string $path The path of the directory to list files from.
     * @param array $filter The file extensions to filter by.
     *                            If it's a string, it will be converted to an array of a single element.
     *
     * @return array An array of file names that match the filter.
     */
    public function getFilesInDirectory(string $path, array $filter): array
    {
        $directoryHandle = opendir($path);
        $directoryContent = array();
        while ($file = readdir($directoryHandle)) {
            $fileExtension = substr(strtolower($file), -3);
            if (in_array($fileExtension, $filter)) {
                $directoryContent[] = $file;
            }
        }

        return $directoryContent;
    }

    /**
     * Finds and processes email addresses within the given content.
     *
     * This method scans the provided content for email addresses and encrypts them based on the configuration.
     * It checks for RSS feed settings and excluded post IDs to determine whether encryption should be applied.
     *
     * @param string|null $content The content to search for email addresses. If null, the method returns null.
     * @param bool $shortcode Specifies whether the method is invoked via a shortcode.
     * @return string|null The processed content with email addresses encrypted, or null if the input content is null.
     */
    public function findEmailAddressesInContent(?string $content, bool $shortcode = false): ?string
    {
        global $post;

        if (self::$cryptXOptions['disable_rss'] && $this->isRssFeed()) return $content;

        if ($content === null) {
            return null;
        }

        // Check if current filter is a widget filter
        $widgetFilters = $this->config->getWidgetFilters();
        $isWidgetContext = in_array(current_filter(), $widgetFilters);

        $postId = (is_object($post)) ? $post->ID : -1;
        $isIdExcluded = $this->isIdExcluded($postId);

        $mailtoRegex = '/<a\s+[^>]*href=(["\'])mailto:([^"\']+)\1[^>]*>(.*?)<\/a>/is';

        // For widgets, always process since there's no specific post context
        // For other content, check exclusion rules
        if ($isWidgetContext || !$isIdExcluded || $shortcode) {
            $content = preg_replace_callback($mailtoRegex, [$this, 'encryptEmailAddressSecure'], $content);
        }

        return $content;
    }

    /**
     * Generate a hash string for the given input string.
     *
     * @param string $inputString The input string to generate a hash for.
     *
     * @return string The generated hash string.
     */
    private function generateHashFromString(string $inputString): string
    {
        $inputString = str_replace("&", "&", $inputString);
        $crypt = '';

        for ($i = 0; $i < strlen($inputString); $i++) {
            do {
                $salt = wp_rand(0, 3);
                $asciiValue = ord(substr($inputString, $i)) + $salt;
                if (8364 <= $asciiValue) {
                    $asciiValue = 128;
                }
            } while (in_array($asciiValue, self::ASCII_VALUES_BLACKLIST));

            $crypt .= $salt . chr($asciiValue);
        }

        return $crypt;
    }

    /**
     *  add link to email addresses
     */
    /**
     * Auto-link emails in the given content.
     *
     * @param string $content The content to process.
     * @param bool $shortcode Whether the function is called from a shortcode or not.
     *
     * @return string The content with emails auto-linked.
     */
    public function addLinkToEmailAddresses(string $content, bool $shortcode = false): string
    {
        global $post;

        // Check if current filter is a widget filter
        $widgetFilters = $this->config->getWidgetFilters();
        $isWidgetContext = in_array(current_filter(), $widgetFilters);

        $postID = is_object($post) ? $post->ID : -1;

        // For widgets, always process; for other content, check exclusion rules
        if (!$isWidgetContext && $this->isIdExcluded($postID) && !$shortcode) {
            return $content;
        }

        $emailPattern = "[_a-zA-Z0-9-+]+(\\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\\.[a-zA-Z0-9-]+)*(\\.[a-zA-Z]{2,})";
        $linkPattern = "<a href=\"mailto:\\2\">\\2</a>";
        $src = [
                "/([\\s])($emailPattern)/si",
                "/(>)($emailPattern)(<)/si",
                "/(\\()($emailPattern)(\\))/si",
                "/(>)($emailPattern)([\\s])/si",
                "/([\\s])($emailPattern)(<)/si",
                "/^($emailPattern)/si",
                "/(<a[^>]*>)<a[^>]*>/",
                "/(<\\/A>)<\\/A>/i"
        ];
        $tar = [
                "\\1$linkPattern",
                "\\1$linkPattern\\6",
                "\\1$linkPattern\\6",
                "\\1$linkPattern\\6",
                "\\1$linkPattern\\6",
                "<a href=\"mailto:\\0\">\\0</a>",
                "\\1",
                "\\1"
        ];

        return preg_replace($src, $tar, $content);
    }

    /**
     * Installs the CryptX plugin by updating its options and loading default values.
     */
    public function installCryptX(): void
    {
        global $wpdb;
        self::$cryptXOptions['admin_notices_deprecated'] = true;
        if (self::$cryptXOptions['excludedIDs'] == "") {
            $tmp = array();
            $excludes = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'cryptxoff' AND meta_value = 'true'");
            if (count($excludes) > 0) {
                foreach ($excludes as $exclude) {
                    $tmp[] = $exclude->post_id;
                }
                sort($tmp);
                self::$cryptXOptions['excludedIDs'] = implode(",", $tmp);
                update_option('cryptX', self::$cryptXOptions);
                self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults(); // reread Options
                $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'cryptxoff'");
            }
        }
        if (empty(self::$cryptXOptions['c2i_font'])) {
            self::$cryptXOptions['c2i_font'] = CRYPTX_DIR_PATH . 'fonts/' . $firstFont[0];
        }
        if (empty(self::$cryptXOptions['c2i_fontSize'])) {
            self::$cryptXOptions['c2i_fontSize'] = 10;
        }
        if (empty(self::$cryptXOptions['c2i_fontRGB'])) {
            self::$cryptXOptions['c2i_fontRGB'] = '000000';
        }
        update_option('cryptX', self::$cryptXOptions);
        self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults(); // reread Options
    }

    private function addHooksHelper($function_name, $hook_name): void
    {
        if (function_exists($function_name)) {
            call_user_func($function_name, 'cryptx', 'CryptX', [$this, 'metaCheckbox'], $hook_name);
        } else {
            add_action("dbx_{$hook_name}_sidebar", [$this, 'metaOptionFieldset']);
        }
    }

    public function metaBox(): void
    {
        $this->addHooksHelper('add_meta_box', 'post');
        $this->addHooksHelper('add_meta_box', 'page');
    }

    /**
     * Displays a checkbox to disable CryptX for the current post or page.
     *
     * This function outputs HTML code for a checkbox that allows the user to disable CryptX
     * functionality for the current post or page. If the current post or page ID is excluded
     **/
    public function metaCheckbox(): void
    {
        global $post;
        ?>
        <label><input type="checkbox" name="disable_cryptx_pageid" <?php if ($this->isIdExcluded($post->ID)) {
                echo 'checked="checked"';
            } ?>/>
            Disable CryptX for this post/page</label>
        <?php
    }

    /**
     * Renders the CryptX option fieldset for the current post/page if the user has permission to edit posts.
     * This fieldset allows the user to enable or disable CryptX for the current post/page.
     *
     * @return void
     */
    public function metaOptionFieldset(): void
    {
        global $post;
        if (current_user_can('edit_posts')) { ?>
            <fieldset id="cryptxoption" class="dbx-box">
                <h3 class="dbx-handle">CryptX</h3>
                <div class="dbx-content">
                    <label><input type="checkbox"
                                  name="disable_cryptx_pageid" <?php if ($this->isIdExcluded($post->ID)) {
                            echo 'checked="checked"';
                        } ?>/> Disable CryptX for this post/page</label>
                </div>
            </fieldset>
            <?php
        }
    }

    /**
     * Adds a post ID to the excluded list in the cryptX options.
     *
     * @param int $postId The post ID to be added to the excluded list.
     *
     * @return void
     */
    public function addPostIdToExcludedList(int $postId): void
    {
        $postId = wp_is_post_revision($postId) ?: $postId;
        $excludedIds = $this->updateExcludedIdsList(self::$cryptXOptions['excludedIDs'], $postId);
        self::$cryptXOptions['excludedIDs'] = implode(",", array_filter($excludedIds));
        update_option('cryptX', self::$cryptXOptions);
    }

    /**
     * Updates the excluded IDs list based on a given ID and the current list.
     *
     * @param string $excludedIds The current excluded IDs list, separated by commas.
     * @param int $postId The ID to be updated in the excluded IDs list.
     *
     * @return array The updated excluded IDs list as an array, with the ID removed if it existed and added if necessary.
     */
    private function updateExcludedIdsList(string $excludedIds, int $postId): array
    {
        $excludedIdsArray = explode(",", $excludedIds);
        $excludedIdsArray = $this->removePostIdFromExcludedIds($excludedIdsArray, $postId);
        $excludedIdsArray = $this->addPostIdToExcludedIdsIfNecessary($excludedIdsArray, $postId);

        return $this->makeExcludedIdsUniqueAndSorted($excludedIdsArray);
    }

    /**
     * Removes a specific post ID from the array of excluded IDs.
     *
     * @param array $excludedIds The array of excluded IDs.
     * @param int $postId The ID of the post to be removed from the excluded IDs.
     *
     * @return array The updated array of excluded IDs without the specified post ID.
     */
    private function removePostIdFromExcludedIds(array $excludedIds, int $postId): array
    {
        foreach ($excludedIds as $key => $id) {
            if ($id == $postId) {
                unset($excludedIds[$key]);
                break;
            }
        }

        return $excludedIds;
    }

    /**
     * Adds the post ID to the list of excluded IDs if necessary.
     *
     * @param array $excludedIds The array of excluded IDs.
     * @param int $postId The post ID to be added to the excluded IDs.
     *
     * @return array The updated array of excluded IDs.
     */
    private function addPostIdToExcludedIdsIfNecessary(array $excludedIds, int $postId): array
    {
        if (isset($_POST['disable_cryptx_pageid'])) {
            $excludedIds[] = $postId;
        }

        return $excludedIds;
    }

    /**
     * Makes the excluded IDs unique and sorted.
     *
     * @param array $excludedIds The array of excluded IDs.
     *
     * @return array The array of excluded IDs with duplicate values removed and sorted in ascending order.
     */
    private function makeExcludedIdsUniqueAndSorted(array $excludedIds): array
    {
        $excludedIds = array_unique($excludedIds);
        sort($excludedIds);

        return $excludedIds;
    }

    /**
     * Displays a message in a styled div.
     *
     * @param string $message The message to be displayed.
     * @param bool $errormsg Optional. Indicates whether the message is an error message. Default is false.
     *
     * @return void
     */
    private function showMessage(string $message, bool $errormsg = false): void
    {
        if ($errormsg) {
            echo '<div id="message" class="error">';
        } else {
            echo '<div id="message" class="updated fade">';
        }

        echo esc_html($message, 'cryptx') . "</div>";
    }

    /**
     * Retrieves the domain from the current site URL.
     *
     * @return string The domain of the current site URL.
     */
    public function getDomain(): string
    {
        return $this->trimSlashFromDomain($this->removeProtocolFromUrl($this->getSiteUrl()));
    }

    /**
     * Retrieves the site URL.
     *
     * @return string The site URL.
     */
    private function getSiteUrl(): string
    {
        return get_option('siteurl');
    }

    /**
     * Removes the protocol from a URL.
     *
     * @param string $url The URL string to remove the protocol from.
     *
     * @return string The URL string without the protocol.
     */
    private function removeProtocolFromUrl(string $url): string
    {
        return preg_replace('|https?://|', '', $url);
    }

    /**
     * Trims the trailing slash from a domain.
     *
     * @param string $domain The domain to trim the slash from.
     *
     * @return string The domain with the trailing slash removed.
     */
    private function trimSlashFromDomain(string $domain): string
    {
        if ($slashPosition = strpos($domain, '/')) {
            $domain = substr($domain, 0, $slashPosition);
        }

        return $domain;
    }

    /**
     * Loads Javascript files required for CryptX functionality.
     *
     * @return void
     */
    public function loadJavascriptFiles(): void
    {
        wp_enqueue_script('cryptx-js', CRYPTX_DIR_URL . 'js/cryptx.min.js', false, CRYPTX_VERSION, self::$cryptXOptions['load_java']);
        wp_localize_script('cryptx-js', 'cryptxConfig', SecureEncryption::getJavaScriptConfig());
        wp_enqueue_style('cryptx-styles', CRYPTX_DIR_URL . 'css/cryptx.css', false, CRYPTX_VERSION);;
    }

    /**
     * Updates the CryptX settings.
     *
     * This method retrieves the current CryptX options from the database and checks if the version of CryptX
     * stored in the options is less than the current version of CryptX. If the version is outdated, the method
     * updates the necessary settings and saves the updated options back to the database.
     *
     * @return void
     */
    private function updateCryptXSettings(): void
    {
        self::$cryptXOptions = get_option('cryptX');
        if (isset(self::$cryptXOptions['version']) && version_compare(CRYPTX_VERSION, self::$cryptXOptions['version']) > 0) {
            if (isset(self::$cryptXOptions['version'])) {
                unset(self::$cryptXOptions['version']);
            }
            if (isset(self::$cryptXOptions['c2i_font'])) {
                unset(self::$cryptXOptions['c2i_font']);
            }
            if (isset(self::$cryptXOptions['c2i_fontRGB'])) {
                self::$cryptXOptions['c2i_fontRGB'] = "#" . self::$cryptXOptions['c2i_fontRGB'];
            }
            if (isset(self::$cryptXOptions['alt_uploadedimage']) && !is_int(self::$cryptXOptions['alt_uploadedimage'])) {
                unset(self::$cryptXOptions['alt_uploadedimage']);
                if (self::$cryptXOptions['opt_linktext'] == 3) {
                    unset(self::$cryptXOptions['opt_linktext']);
                }
            }
            self::$cryptXOptions = wp_parse_args(self::$cryptXOptions, $this->getCryptXOptionsDefaults());
            update_option('cryptX', self::$cryptXOptions);
        }
    }

    /**
     * Encodes a string by replacing special characters with their corresponding HTML entities.
     *
     * @param string|null $str The string to be encoded.
     *
     * @return string The encoded string, or an array of encoded strings if an array was passed.
     */
    private function encodeString(?string $str): string
    {
        $str = htmlentities($str, ENT_QUOTES, 'UTF-8');
        $special = array(
                '[' => '&#91;',
                ']' => '&#93;',
        );

        return str_replace(array_keys($special), array_values($special), $str);
    }

    /**
     * Decodes a string that has been HTML entity encoded.
     *
     * @param string|null $str The string to decode. If null, an empty string is returned.
     *
     * @return string The decoded string.
     */
    private function decodeString(?string $str): string
    {
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Converts an associative array into an argument string.
     *
     * @param array $args An optional associative array where keys represent argument names and values represent argument values.
     * @return string A formatted string of arguments where each key-value pair is encoded and concatenated.
     */
    public function convertArrayToArgumentString(array $args = []): string
    {
        $string = "";
        if (!empty($args)) {
            foreach ($args as $key => $value) {
                $string .= sprintf(" %s=\"%s\"", $key, esc_attr($value));
            }
            $string .= " encoded=\"true\"";
        }

        return $string;
    }

    /**
     * Check if current request is for an RSS feed
     *
     * @return bool True if current request is for an RSS feed, false otherwise
     */
    private function isRssFeed(): bool
    {
        return is_feed();
    }

    /**
     * Adds plugin action links to the WordPress plugin row
     *
     * @param array $links Existing plugin row links
     * @param string $file Plugin file path
     * @return array Modified plugin row links
     */
    public function add_plugin_action_links(array $links, string $file): array
    {
        if ($file !== CRYPTX_BASENAME) {
            return $links;
        }

        $additional_links = [
                $this->create_settings_link(),
                $this->create_donation_link()
        ];

        return array_merge($links, $additional_links);
    }

    /**
     * Creates and returns a settings link for the options page.
     *
     * @return string The HTML link to the settings page.
     */
    private function create_settings_link(): string
    {
        return sprintf(
                '<a href="options-general.php?page=%s">%s</a>',
                CRYPTX_BASEFOLDER,
                esc_html__('Settings', 'cryptx')
        );
    }

    /**
     * Creates and returns a donation link in HTML format.
     *
     * @return string The HTML string for the donation link.
     */
    private function create_donation_link(): string
    {
        return sprintf(
                '<a href="%s">%s</a>',
                self::PAYPAL_DONATION_URL,
                esc_html__('Donate', 'cryptx')
        );
    }

    /**
     * Adds a universal filter for all widget types by hooking into the widget display process.
     *
     * @return void
     */
    private function addUniversalWidgetFilters(): void
    {
        // Hook into the widget display process to catch all widget types
        add_filter('widget_display_callback', [$this, 'processWidgetContent'], 10, 3);
    }

    /**
     * Processes widget content to handle email addresses by adding links, identifying occurrences,
     * and replacing them based on predefined rules.
     *
     * @param array|false $instance An array containing widget instance data, or false if no instance was provided.
     * @param object $widget The widget object whose content is being processed.
     * @param array $args Additional arguments provided to the widget.
     *
     * @return array|false Modified widget instance data as an array, or false if processing was not applicable.
     */
    public function processWidgetContent(array|false $instance, $widget, $args): array|false
    {
        if ($instance === false) {
            return false;
        }

        // Only process if widget_text option is enabled
        if (!(self::$cryptXOptions['widget_text'] ?? false)) {
            return $instance;
        }

        // Check if instance has text content (traditional text widgets)
        if (isset($instance['text']) && stripos($instance['text'], '@') !== false) {
            $instance['text'] = $this->addLinkToEmailAddresses($instance['text']);
            $instance['text'] = $this->findEmailAddressesInContent($instance['text']);
            $instance['text'] = $this->replaceEmailInContent($instance['text']);
        }

        // Check if instance has content field (block widgets)
        if (isset($instance['content']) && stripos($instance['content'], '@') !== false) {
            $instance['content'] = $this->addLinkToEmailAddresses($instance['content']);
            $instance['content'] = $this->findEmailAddressesInContent($instance['content']);
            $instance['content'] = $this->replaceEmailInContent($instance['content']);
        }

        return $instance;
    }

    /**
     * Generates hash using secure or legacy encryption based on settings
     *
     * @param string $inputString
     * @return string
     */
    private function generateSecureHashFromString(string $inputString): string
    {
        if ($this->config->isSecureEncryptionEnabled()) {
            try {
                $password = $this->config->getEncryptionPassword();
                return SecureEncryption::encrypt($inputString, $password);
            } catch (\Exception $e) {
                // Fallback to legacy encryption
                return $this->generateHashFromString($inputString);
            }
        }

        return $this->generateHashFromString($inputString);
    }

    /**
     * Enhanced email encryption with security validation
     *
     * @param array $searchResults
     * @return string
     */
    private function encryptEmailAddressSecure(array $searchResults): string
    {
        $originalValue = $searchResults[0];  // Full match
        $emailAddress = sanitize_email($searchResults[2]);   // Email address
        $linkText = esc_html($searchResults[3]);       // Link text

        if (strpos($emailAddress, '@') === self::NOT_FOUND) {
            return $originalValue;
        }

        if (str_starts_with($emailAddress, self::SUBJECT_IDENTIFIER)) {
            return $originalValue;
        }

        $return = $originalValue;

        // Apply JavaScript handler if enabled
        if (!empty(self::$cryptXOptions['java'])) {
            $encryptionMode = $this->config->getEncryptionMode();

            // Determine which encryption method to use
            if ($encryptionMode === 'secure' &&
                    $this->config->isSecureEncryptionEnabled() &&
                    class_exists('CryptX\SecureEncryption')) {

                // Use modern AES-256-GCM encryption
                try {
                    $password = $this->config->getEncryptionPassword();
                    $mailtoUrl = 'mailto:' . $emailAddress;
                    $encryptedEmail = SecureEncryption::encrypt($mailtoUrl, $password);

                    $javaHandler = "javascript:secureDecryptAndNavigate('" .
                            esc_js($encryptedEmail) . "', '" .
                            esc_js($password) . "')";
                } catch (\Exception $e) {
                    // Fallback to legacy if secure encryption fails
                    $encryptedEmail = $this->generateHashFromString($emailAddress);
                    $javaHandler = "javascript:DeCryptX('" . esc_js($encryptedEmail) . "')";
                }
            } else {
                // Use legacy encryption (original algorithm)
                $encryptedEmail = $this->generateHashFromString($emailAddress);
                $javaHandler = "javascript:DeCryptX('" . esc_js($encryptedEmail) . "')";
            }

            $return = str_replace('mailto:' . $emailAddress, $javaHandler, $originalValue);
        } else {
            // Fallback to antispambot if JavaScript is not enabled
            $return = str_replace('mailto:' . $emailAddress,
                    antispambot('mailto:' . $emailAddress), $return);
        }

        // Add CSS attributes if specified
        if (!empty(self::$cryptXOptions['css_id'])) {
            $cssId = esc_attr(self::$cryptXOptions['css_id']);
            if (preg_match('/<a\s+[^>]*\bid\s*=\s*["\']/i', $return)) {
                $return = preg_replace('/(<a\s+[^>]*\bid\s*=\s*(["\']))(.*?)\2/i', '$1$3 ' . $cssId . '$2', $return);
            } else {
                $return = preg_replace('/(<a\s+[^>]*)(>)/i',
                        '$1 id="' . $cssId . '"$2', $return);
            }
        }

        if (!empty(self::$cryptXOptions['css_class'])) {
            $cssClass = esc_attr(self::$cryptXOptions['css_class']);
            if (preg_match('/<a\s+[^>]*\bclass\s*=\s*["\']/i', $return)) {
                $return = preg_replace('/(<a\s+[^>]*\bclass\s*=\s*(["\']))(.*?)\2/i', '$1$3 ' . $cssClass . '$2', $return);
            } else {
                $return = preg_replace('/(<a\s+[^>]*)(>)/i',
                        '$1 class="' . $cssClass . '"$2', $return);
            }
        }

        return $return;
    }

    /**
     * Secure URL validation
     *
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        return SecureEncryption::validateUrl($url);
    }

}