<?php

namespace CryptX;

final class CryptX
{
    const NOT_FOUND = false;
    const SUBJECT_IDENTIFIER = "?subject=";
    const ASCII_VALUES_BLACKLIST = ['32', '34', '39', '60', '62', '63', '92', '94', '96', '127'];
    /** Upper bound for the text rendered into a PNG, see cryptXtinyUrl(). */
    private const MAX_IMAGE_TEXT_LENGTH = 254;
    private static ?self $instance = null;
    private static array $cryptXOptions = [];
    private static int $imageCounter = 0;

    /** CSS class of the links the click handler in cryptx.js listens for. */
    private const LINK_CLASS = 'cryptx-link';

    /** Marks a save request as coming from the post meta box. */
    private const METABOX_NONCE_ACTION = 'cryptx_metabox';
    private const METABOX_NONCE_FIELD = 'cryptx_metabox_nonce';

    /**
     * Set as soon as something on this page actually needs them. Version 3.2.7
     * once had this property ("the javascript will be loaded only if really
     * needed!"); the 4.0 rewrite lost it and loaded both files on every page,
     * including pages without a single address.
     */
    private static bool $scriptNeeded = false;
    private static bool $styleNeeded = false;

    /**
     * Parsed once per request instead of on every call. Both lists are read
     * from a comma separated option for every filter pass and, in the case of
     * the whitelist, for every single address found.
     */
    private static ?array $excludedIdCache = null;
    private static ?array $whiteListCache = null;

    private const FONT_EXTENSION = 'ttf';
    private const PAYPAL_DONATION_URL = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4026696';
    private Admin\SettingsPage $settingsPage;
    private Config $config;

    private function __construct()
    {
        $this->settingsPage = new Admin\SettingsPage();
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
        // The settings screen registers its own menu entry and REST routes.
        // Doing it here rather than in the constructor keeps the hooks out of
        // object construction, where they are easy to trigger by accident.
        $this->settingsPage->register();

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
        // Priority 1 so this still runs before wp_print_footer_scripts.
        add_action('wp_footer', [$this, 'enqueueAssetsIfNeeded'], 1);
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
            self::resetOptionCaches();
        }

        try {
            // Process content (inline the encryptAndLinkContent logic)
            if (self::$cryptXOptions['autolink'] ?? false) {
                $content = $this->addLinkToEmailAddresses($content, true);
            }
            $content = $this->findEmailAddressesInContent($content, true);
            $processedContent = $this->replaceEmailInContent($content, true);
        } finally {
            // Restored in a finally block: self::$cryptXOptions is static, so
            // an exception escaping from here would leave the shortcode's
            // values in place for the rest of the request.
            self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults();
            self::resetOptionCaches();
        }

        return $processedContent;
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
        // sanitize_text_field(), not esc_url(): the latter is an output
        // escaper and turned "&" into "&#038;" on the way in.
        $url = (!empty($_SERVER['REQUEST_URI']))
                ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
                : '';
        $params = explode('/', $url);

        if (count($params) < 2) {
            return;
        }

        if (!hash_equals(md5(get_bloginfo('url')), $params[count($params) - 2])) {
            return;
        }

        // Everything below writes an image to the output stream. Any PHP notice
        // that slips through would end up inside that stream, be served as
        // image/png and disclose the server path to the visitor. So every
        // prerequisite is checked first and the request is abandoned quietly
        // if one is missing.
        if (!function_exists('imagettfbbox')) {
            return;
        }

        $fontFile = self::$cryptXOptions['c2i_font'] ?? $this->getDefaultFont();
        if (!is_string($fontFile) || $fontFile === '') {
            return;
        }

        // basename() keeps the option from reaching outside the fonts folder,
        // even if it was tampered with in the database.
        $font = CRYPTX_DIR_PATH . 'fonts/' . basename(str_replace(' ', '_', $fontFile));
        // is_file(), not is_readable(): the latter is true for a directory as
        // well, and imagettfbbox() would then emit "Could not read font" with
        // the full server path -- exactly the disclosure this rewrite removes.
        if (!is_file($font) || !is_readable($font)) {
            return;
        }

        // The text comes straight from the URL. Without a bound, a long request
        // would size the canvas up accordingly and exhaust the memory limit --
        // a cheap denial of service. No address is anywhere near this long.
        $msg = substr(rawurldecode($params[count($params) - 1]), 0, self::MAX_IMAGE_TEXT_LENGTH);
        if ($msg === '') {
            return;
        }

        $size = (int) (self::$cryptXOptions['c2i_fontSize'] ?? 10);
        $size = max(1, min(96, $size));

        $rgb = ltrim((string) (self::$cryptXOptions['c2i_fontRGB'] ?? '#000000'), '#');
        if (!preg_match('/^[0-9a-f]{6}$/i', $rgb)) {
            $rgb = '000000';
        }
        $red = hexdec(substr($rgb, 0, 2));
        $grn = hexdec(substr($rgb, 2, 2));
        $blu = hexdec(substr($rgb, 4, 2));

        $pad = 1;
        $bounds = imagettfbbox($size, 0, $font, 'W');
        if ($bounds === false) {
            return;
        }
        $font_height = abs($bounds[7] - $bounds[1]);

        $bounds = imagettfbbox($size, 0, $font, $msg);
        if ($bounds === false) {
            return;
        }
        $width = abs($bounds[4] - $bounds[6]);
        $height = abs($bounds[7] - $bounds[1]);
        if ($width < 1 || $height < 1) {
            return;
        }

        $offset_y = $font_height + abs(($height - $font_height) / 2) - 1;
        $offset_x = 0;

        $image = imagecreatetruecolor($width + ($pad * 2), $height + ($pad * 2));
        if ($image === false) {
            return;
        }
        imagesavealpha($image, true);
        $foreground = imagecolorallocate($image, $red, $grn, $blu);
        $background = imagecolorallocatealpha($image, 0, 0, 0, 127);

        // Both return false when the palette is exhausted. Passing that on
        // would emit a warning into the image stream -- the very thing this
        // method is built to avoid.
        if ($foreground === false || $background === false) {
            imagedestroy($image);
            return;
        }

        imagefill($image, 0, 0, $background);
        imagettftext($image, $size, 0, (int) round($offset_x + $pad), (int) round($offset_y + $pad), $foreground, $font, $msg);

        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        imagepng($image);
        imagedestroy($image);
        die;
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
        if (self::$excludedIdCache === null) {
            $raw = (string) (self::$cryptXOptions['excludedIDs'] ?? '');
            self::$excludedIdCache = array_map(
                    'intval',
                    array_filter(array_map('trim', explode(',', $raw)), 'strlen')
            );
        }

        return in_array($ID, self::$excludedIdCache, true);
    }

    /**
     * Drops the parsed option lists.
     *
     * Both caches mirror values from self::$cryptXOptions. Whenever those are
     * replaced -- by the shortcode or after saving -- the caches have to go
     * with them, otherwise a stale exclusion list survives the change.
     *
     * @return void
     */
    private static function resetOptionCaches(): void
    {
        self::$excludedIdCache = null;
        self::$whiteListCache = null;
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

        // Nothing to find without an at sign. Bailing out here skips the whole
        // regular expression machinery for the vast majority of content -- and
        // on a block theme this filter runs once per block, not once per post.
        if ($content === null || strpos($content, '@') === false) {
            return $content;
        }

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

        $result = preg_replace_callback($emailPattern, [$this, 'encodeEmailToLinkText'], $content);

        // On a PCRE error -- a backtrack or recursion limit on unusually large
        // or awkward content -- preg_* returns null. Handing that back would
        // make the whole post body disappear, so the untouched content wins.
        return $result ?? $content;
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
                // false when the attachment was deleted; would have produced
                // <img src=""> and a TypeError on the string parameter.
                $text = $img_url === false ? $this->getDefaultLinkText($Match) : $this->getUploadedImage($img_url);
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
        if (self::$whiteListCache === null) {
            $raw = (string) (self::$cryptXOptions['whiteList'] ?? '');
            self::$whiteListCache = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        }

        if (self::$whiteListCache === []) {
            return false;
        }

        $tmp = explode(".", $Match[0]);

        return in_array(end($tmp), self::$whiteListCache, true);
    }

    /**
     * Get the link text from cryptXOptions
     *
     * @return string The link text
     */
    private function getLinkText(): string
    {
        // Escaped here rather than at the source: the settings page runs this
        // value through sanitize_text_field(), but a shortcode attribute of the
        // same name reaches self::$cryptXOptions without passing that filter.
        return esc_html((string) self::$cryptXOptions['alt_linktext']);
    }

    /**
     * Generate an HTML image tag with the link image URL as the source
     *
     * @return string The HTML image tag
     */
    private function getLinkImage(): string
    {
        self::$styleNeeded = true;
        $title = (string) self::$cryptXOptions['alt_linkimage_title'];

        return sprintf(
                '<img src="%s" class="cryptxImage" alt="%s" title="%s" />',
                esc_url(self::$cryptXOptions['alt_linkimage']),
                esc_attr($title),
                esc_attr(antispambot($title))
        );
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
        self::$styleNeeded = true;
        $title = (string) self::$cryptXOptions['http_linkimage_title'];

        // The alt attribute used to be missing its closing quote, which ran the
        // title straight into it and produced broken markup.
        return sprintf(
                '<img src="%s" class="cryptxImage cryptxImage_%d" alt="%s" title="%s" />',
                esc_url($img_url),
                self::$imageCounter,
                esc_attr($title),
                esc_attr(antispambot($title))
        );
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
        self::$styleNeeded = true;
        $scrambled = antispambot($Match[1]);

        return sprintf(
                '<img src="%s" class="cryptxImage cryptxImage_%d" alt="%s" title="%s" />',
                esc_url(get_bloginfo('url') . '/' . md5(get_bloginfo('url')) . '/' . $scrambled),
                self::$imageCounter,
                esc_attr($scrambled),
                esc_attr($scrambled)
        );
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
        // Escaped here for the same reason as in getLinkText(): the settings
        // page runs both values through wp_kses_post(), but a shortcode
        // attribute of the same name reaches self::$cryptXOptions unfiltered.
        // Today only KSES stops an author from putting markup here -- that is
        // WordPress protecting the plugin, not the plugin protecting itself.
        $at = esc_html((string) self::$cryptXOptions['at']);
        $dot = esc_html((string) self::$cryptXOptions['dot']);

        $text = str_replace("@", $at, $Match[1]);

        return str_replace(".", $dot, $text);
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
        if (!is_dir($path)) {
            return [];
        }

        $directoryContent = [];
        foreach (new \DirectoryIterator($path) as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (in_array(strtolower($file->getExtension()), $filter, true)) {
                $directoryContent[] = $file->getFilename();
            }
        }

        // readdir() order depends on the file system, which made the default
        // font differ between servers. Sorting keeps it reproducible.
        sort($directoryContent);

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

        // A mailto link without an at sign cannot carry an address. Cheapest
        // possible way out before the regular expression runs.
        if (strpos($content, '@') === false) {
            return $content;
        }

        // Check if current filter is a widget filter
        $widgetFilters = $this->config->getWidgetFilters();
        $isWidgetContext = in_array(current_filter(), $widgetFilters);

        $postId = (is_object($post)) ? $post->ID : -1;
        $isIdExcluded = $this->isIdExcluded($postId);

        // Quoted attribute values may contain ">", so the tag must not simply
        // end at the first one -- title="a > b" used to cut the match in half
        // and produce mangled markup. Same construction as in
        // rewriteOpeningAnchorTag(); the two have to agree on what a tag is.
        $mailtoRegex = '/<a\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*?href\s*=\s*(["\'])mailto:([^"\']+)\1(?:[^>"\']|"[^"]*"|\'[^\']*\')*>(.*?)<\/a>/is';

        // For widgets, always process since there's no specific post context
        // For other content, check exclusion rules
        if ($isWidgetContext || !$isIdExcluded || $shortcode) {
            $result = preg_replace_callback($mailtoRegex, [$this, 'encryptEmailAddressSecure'], $content);

            // null means PCRE gave up (backtrack limit). Keeping the original
            // content is far better than returning null and wiping the page.
            $content = $result ?? $content;
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

        // Eight regular expressions follow, each carrying the full address
        // pattern. Without an at sign not one of them can match, so this test
        // saves the entire pass.
        if (strpos($content, '@') === false) {
            return $content;
        }

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

        $result = preg_replace($src, $tar, $content);

        // Same reasoning as elsewhere: a PCRE failure yields null, and handing
        // that on would silently empty the page.
        return $result ?? $content;
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
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $excludes = $wpdb->get_results($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                    'cryptxoff',
                    'true'
            ));
            if (count($excludes) > 0) {
                foreach ($excludes as $exclude) {
                    $tmp[] = $exclude->post_id;
                }
                sort($tmp);
                self::$cryptXOptions['excludedIDs'] = implode(",", $tmp);
                update_option('cryptX', self::$cryptXOptions);
                self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults(); // reread Options
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                        'cryptxoff'
                ));
            }
        }
        if (empty(self::$cryptXOptions['c2i_font'])) {
            // Only the file name is stored here. cryptXtinyUrl() prepends
            // CRYPTX_DIR_PATH . 'fonts/' itself, so an absolute path would
            // produce an unusable font path.
            self::$cryptXOptions['c2i_font'] = $this->getDefaultFont();
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

        if (!is_object($post)) {
            return;
        }

        wp_nonce_field(self::METABOX_NONCE_ACTION, self::METABOX_NONCE_FIELD);
        ?>
        <label><input type="checkbox" name="disable_cryptx_pageid" <?php if ($this->isIdExcluded($post->ID)) {
                echo 'checked="checked"';
            } ?>/>
            <?php esc_html_e('Disable CryptX for this post/page', 'cryptx'); ?></label>
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

        if (!is_object($post) || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        ?>
        <fieldset id="cryptxoption" class="dbx-box">
            <h3 class="dbx-handle">CryptX</h3>
            <div class="dbx-content">
                <?php wp_nonce_field(self::METABOX_NONCE_ACTION, self::METABOX_NONCE_FIELD); ?>
                <label><input type="checkbox"
                              name="disable_cryptx_pageid" <?php if ($this->isIdExcluded($post->ID)) {
                        echo 'checked="checked"';
                    } ?>/> <?php esc_html_e('Disable CryptX for this post/page', 'cryptx'); ?></label>
            </div>
        </fieldset>
        <?php
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
        // The meta box has to have taken part in this request. Without this
        // gate every save that carries no $_POST at all -- REST, WP-CLI,
        // autosave, the block editor's first pass -- removed the post from the
        // exclusion list and silently switched CryptX back on for it.
        //
        // The gate hangs on the nonce, deliberately not on the checkbox: an
        // unchecked box is not submitted at all, so "checkbox missing" would
        // mean both "meta box was not involved" and "user cleared the tick".
        // Guarding on that would make an excluded post impossible to include
        // again.
        if (!isset($_POST[self::METABOX_NONCE_FIELD])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::METABOX_NONCE_FIELD]));
        if (!wp_verify_nonce($nonce, self::METABOX_NONCE_ACTION)) {
            return;
        }

        $postId = wp_is_post_revision($postId) ?: $postId;

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Read the option fresh instead of writing back self::$cryptXOptions.
        // That property is static and the shortcode overwrites it while it
        // runs; storing it wholesale could persist a shortcode's temporary
        // values. Only the one key we are responsible for is touched.
        $options = get_option('cryptX', []);
        if (!is_array($options)) {
            $options = [];
        }

        $excludedIds = $this->updateExcludedIdsList((string) ($options['excludedIDs'] ?? ''), $postId);
        $options['excludedIDs'] = implode(',', array_filter($excludedIds));

        update_option('cryptX', $options);

        self::$cryptXOptions['excludedIDs'] = $options['excludedIDs'];
        self::resetOptionCaches();
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

        echo esc_html($message) . "</div>";
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
     * Registers the frontend assets.
     *
     * Registering is not loading. Whether the files end up on the page is
     * decided in enqueueAssetsIfNeeded() once the content has been processed
     * and it is known whether anything was encrypted at all.
     *
     * One exception: with the script placed in the head (load_java = 0) that
     * decision cannot be deferred -- the head is sent before the content runs.
     * In that configuration the script is enqueued unconditionally, as before.
     *
     * @return void
     */
    public function loadJavascriptFiles(): void
    {
        $inFooter = !empty(self::$cryptXOptions['load_java']);

        wp_register_script('cryptx-js', CRYPTX_DIR_URL . 'js/cryptx.min.js', [], CRYPTX_VERSION, $inFooter);
        wp_localize_script('cryptx-js', 'cryptxConfig', SecureEncryption::getJavaScriptConfig());
        wp_register_style('cryptx-styles', CRYPTX_DIR_URL . 'css/cryptx.css', [], CRYPTX_VERSION);

        if (!$inFooter) {
            wp_enqueue_script('cryptx-js');
            wp_enqueue_style('cryptx-styles');
        }
    }

    /**
     * Loads the assets that this page turned out to need.
     *
     * Runs late, in the footer, when every filter has done its work.
     *
     * @return void
     */
    public function enqueueAssetsIfNeeded(): void
    {
        if (self::$scriptNeeded) {
            wp_enqueue_script('cryptx-js');
        }

        if (self::$styleNeeded) {
            wp_enqueue_style('cryptx-styles');
        }
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
            // Installations from before 4.0.12 hold a password derived from
            // AUTH_KEY and SECURE_AUTH_KEY, and that value is published in the
            // markup of every page. Since site_url is public, an attacker can
            // test candidate keys offline -- above all the placeholders from
            // wp-config-sample.php that unattended installations still carry.
            // Dropping it here lets Config::getEncryptionPassword() mint a
            // random one. The price: links on pages already sitting in a cache
            // stop resolving until that cache turns over, which is why this
            // happens once, at the version bump, and is called out in the
            // upgrade notice.
            if (isset(self::$cryptXOptions['encryption_password'])) {
                unset(self::$cryptXOptions['encryption_password']);
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
     * Enhanced email encryption with security validation
     *
     * @param array $searchResults
     * @return string
     */
    private function encryptEmailAddressSecure(array $searchResults): string
    {
        $originalValue = $searchResults[0];  // Full match
        $emailAddress = sanitize_email($searchResults[2]);   // Email address

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
            $payloadMode = 'legacy';
            $password = '';

            // Determine which encryption method to use
            if ($encryptionMode === 'secure' &&
                    $this->config->isSecureEncryptionEnabled() &&
                    class_exists('CryptX\SecureEncryption')) {

                // Use modern AES-256-GCM encryption
                try {
                    $password = $this->config->getEncryptionPassword();
                    $mailtoUrl = 'mailto:' . $emailAddress;
                    $encryptedEmail = SecureEncryption::encrypt($mailtoUrl, $password);
                    $payloadMode = 'secure';
                } catch (\Exception $e) {
                    // Fallback to legacy if secure encryption fails
                    $encryptedEmail = $this->generateHashFromString($emailAddress);
                    $password = '';
                }
            } else {
                // Use legacy encryption (original algorithm)
                $encryptedEmail = $this->generateHashFromString($emailAddress);
            }

            self::$scriptNeeded = true;

            if ($this->getLinkMode() === 'data') {
                // Preferred form: the payload travels in data attributes and a
                // delegated click handler in cryptx.js does the work. A
                // "javascript:" URI would be blocked outright by any halfway
                // strict Content-Security-Policy, taking every CryptX link on
                // the page with it -- silently.
                $attributes = sprintf(
                        ' data-cx="%s" data-cxm="%s"',
                        esc_attr($encryptedEmail),
                        esc_attr($payloadMode)
                );
                if ($payloadMode === 'secure') {
                    $attributes .= sprintf(' data-cxk="%s"', esc_attr($password));
                }

                $return = str_replace('mailto:' . $emailAddress, '#', $originalValue);
                $return = $this->addAttributesToAnchor($return, $attributes);
                $return = $this->addClassToAnchor($return, self::LINK_CLASS);
            } else {
                // Legacy form, kept for installations that depend on it.
                $javaHandler = $payloadMode === 'secure'
                        ? "javascript:secureDecryptAndNavigate('" . esc_js($encryptedEmail) . "', '" . esc_js($password) . "')"
                        : "javascript:DeCryptX('" . esc_js($encryptedEmail) . "')";

                $return = str_replace('mailto:' . $emailAddress, $javaHandler, $originalValue);
            }
        } else {
            // Fallback to antispambot if JavaScript is not enabled
            $return = str_replace('mailto:' . $emailAddress,
                    antispambot('mailto:' . $emailAddress), $return);
        }

        // Add CSS attributes if specified
        if (!empty(self::$cryptXOptions['css_id'])) {
            // Guarded like every other preg_* call site in this class: a PCRE
            // error yields null, and $return is declared string.
            $return = $this->addIdToAnchor($return, self::$cryptXOptions['css_id']);
        }

        if (!empty(self::$cryptXOptions['css_class'])) {
            $return = $this->addClassToAnchor($return, self::$cryptXOptions['css_class']);
        }

        return $return;
    }

    /**
     * Runs a sample through the real processing chain for the settings preview.
     *
     * Deliberately not a reimplementation: the preview calls the same three
     * filters the front end calls, with the same encryption. A separate
     * "preview renderer" would drift away from the truth sooner or later, and
     * a preview that lies is worse than none.
     *
     * Nothing is written. Both the static option list and the Config instance
     * are swapped for the duration and restored in a finally block -- Config
     * matters because the encryption path reads its mode and password from
     * there, not from the static list.
     *
     * @param array $overrides Option values as they stand in the unsaved form.
     * @param string $content The sample content.
     *
     * @return string The processed markup.
     */
    public function renderPreviewMarkup(array $overrides, string $content): string
    {
        $previousOptions = self::$cryptXOptions;
        $previousConfig = $this->config;

        // Make sure a secret exists before the swap, and mint it through the
        // REAL Config if it does not.
        //
        // Config::getEncryptionPassword() writes when it has to mint, and
        // Config::save() stores the whole option array -- which, on the
        // throwaway Config below, is the administrator's unsaved form state.
        // A preview would then silently persist settings that were only being
        // tried out. The window is real: updateCryptXSettings() drops the
        // secret on every version bump, and the settings screen is the first
        // place an administrator goes after an update.
        $stored = $this->loadCryptXOptionsWithDefaults();

        if (empty($stored['encryption_password'])) {
            // Mint through a Config built from the STORED options, and carry the
            // result into $merged by hand.
            //
            // Doing it through the live Config instead was not enough: that one
            // holds an in-memory copy taken at startup, so it can believe it has
            // a password while the row no longer does. It then writes nothing,
            // $merged is still without a secret, and the throwaway Config below
            // mints -- persisting the unsaved form along with it. A test that
            // watches pre_update_option_cryptX found exactly that.
            $stored['encryption_password'] = (new Config($stored))->getEncryptionPassword();
        }

        $merged = wp_parse_args($overrides, $stored);

        self::$cryptXOptions = $merged;
        $this->config = new Config($merged);
        self::resetOptionCaches();

        try {
            if (!empty(self::$cryptXOptions['autolink'])) {
                $content = $this->addLinkToEmailAddresses($content, true);
            }

            $content = $this->findEmailAddressesInContent($content, true);

            return (string) $this->replaceEmailInContent($content, true);
        } finally {
            self::$cryptXOptions = $previousOptions;
            $this->config = $previousConfig;
            self::resetOptionCaches();
        }
    }

    /**
     * Which link form the encrypted address is delivered in.
     *
     * 'data' puts the payload into data attributes and lets a delegated click
     * handler take over -- the only form that survives a Content-Security-Policy.
     * 'js' is the historical "javascript:" URI, offered under Advanced for
     * installations that depend on the old behaviour.
     *
     * @return string Either 'data' or 'js'.
     */
    private function getLinkMode(): string
    {
        $mode = (string) (self::$cryptXOptions['link_mode'] ?? 'data');

        return $mode === 'js' ? 'js' : 'data';
    }

    /**
     * Inserts additional attributes into the opening tag of an anchor.
     *
     * @param string $html The anchor markup.
     * @param string $attributes Attribute string, starting with a space.
     *
     * @return string The markup with the attributes added.
     */
    private function addAttributesToAnchor(string $html, string $attributes): string
    {
        return $this->rewriteOpeningAnchorTag(
                $html,
                static fn(string $tag): string => preg_replace('/(\s*\/?>)$/', $attributes . '$1', $tag, 1) ?? $tag
        );
    }

    /**
     * Adds a class to an anchor, keeping any class that is already there.
     *
     * @param string $html The anchor markup.
     * @param string $class The class to add.
     *
     * @return string The markup with the class added.
     */
    private function addClassToAnchor(string $html, string $class): string
    {
        $class = esc_attr($class);

        return $this->rewriteOpeningAnchorTag(
                $html,
                function (string $tag) use ($class): string {
                    // (?:^|\s) rather than \b: a word boundary also sits
                    // between the quote and the "c" of an attribute value such
                    // as data-x="class='y'", so \bclass would bind to the text
                    // inside that value. Requiring whitespace before the name
                    // makes this an attribute rather than any occurrence of the
                    // word -- and it holds no matter which attribute comes
                    // first, which the greedy and the lazy variant each got
                    // wrong in one of the two orders.
                    if (preg_match('/(?:^|\s)class\s*=\s*(["\'])(.*?)\1/i', $tag)) {
                        return preg_replace(
                                '/((?:^|\s)class\s*=\s*(["\']))(.*?)\2/i',
                                '$1$3 ' . $class . '$2',
                                $tag,
                                1
                        ) ?? $tag;
                    }

                    return preg_replace('/(\s*\/?>)$/', ' class="' . $class . '"$1', $tag, 1) ?? $tag;
                }
        );
    }

    /**
     * Applies a rewrite to the opening tag of the first anchor only.
     *
     * Regular expressions on HTML are a poor tool, and this is the narrow case
     * where it is still defensible: the markup comes from CryptX's own mailto
     * pattern, so there is exactly one anchor and the payload is escaped before
     * it gets here. Isolating the opening tag keeps the rewrite from reaching
     * into attribute values or into the link text.
     *
     * @param string $html The anchor markup.
     * @param callable $rewrite Receives the opening tag, returns the new one.
     *
     * @return string The markup with the rewritten opening tag.
     */
    private function rewriteOpeningAnchorTag(string $html, callable $rewrite): string
    {
        // Quoted attribute values may legitimately contain ">", so a plain
        // [^>]* would end the tag too early and splice the new attribute into
        // the middle of somebody else's title.
        $openingTag = '/<a\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i';

        if (!preg_match($openingTag, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $tag = $matches[0][0];
        $offset = $matches[0][1];
        $rewritten = $rewrite($tag);

        return substr($html, 0, $offset) . $rewritten . substr($html, $offset + strlen($tag));
    }

    /**
     * Adds an id to an anchor, keeping any id that is already there.
     *
     * @param string $html The anchor markup.
     * @param string $id The id to add.
     *
     * @return string The markup with the id added.
     */
    private function addIdToAnchor(string $html, string $id): string
    {
        $id = esc_attr($id);

        return $this->rewriteOpeningAnchorTag(
                $html,
                function (string $tag) use ($id): string {
                    // Same reasoning as in addClassToAnchor().
                    if (preg_match('/(?:^|\s)id\s*=\s*(["\'])(.*?)\1/i', $tag)) {
                        return preg_replace(
                                '/((?:^|\s)id\s*=\s*(["\']))(.*?)\2/i',
                                '$1$3 ' . $id . '$2',
                                $tag,
                                1
                        ) ?? $tag;
                    }

                    return preg_replace('/(\s*\/?>)$/', ' id="' . $id . '"$1', $tag, 1) ?? $tag;
                }
        );
    }

}