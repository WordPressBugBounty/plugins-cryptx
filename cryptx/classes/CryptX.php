<?php

namespace CryptX;

final class CryptX
{
    const NOT_FOUND = false;

    /**
     * Kept for compatibility: it is public, so a theme may reference it.
     *
     * @deprecated 4.1.1 The guard that used it compared against an already
     *             sanitised address and could therefore never match --
     *             sanitize_email('?subject=x') returns an empty string. Query
     *             handling now lives in sanitizeMailtoQuery().
     */
    const SUBJECT_IDENTIFIER = "?subject=";

    /** Upper bound for a single mailto header value, in characters. */
    private const MAX_MAILTO_VALUE_LENGTH = 512;

    /**
     * Upper bound for the whole "mailto:..." target, in characters.
     *
     * Matches CONFIG.MAX_URL_LENGTH in js/cryptx.js and the limit in
     * SecureEncryption::validateUrl(). Above it the click handler refuses to
     * navigate, and the link silently does nothing.
     */
    private const MAX_MAILTO_URL_LENGTH = 2048;

    /**
     * Shortcode attributes that describe the mail, not the plugin's settings.
     *
     * The names are those of the mailto headers in RFC 6068, so
     * [cryptx subject="..."] and href="mailto:...?subject=..." mean the same
     * thing and are cleaned by the same code.
     */
    private const MAILTO_ATTRIBUTES = ['subject', 'body', 'cc', 'bcc'];

    /**
     * The filters whose content a visitor wrote, not the site owner.
     *
     * A setting is the owner speaking about their own pages. Where the text
     * came in from outside, a blanket rule that switches protection off has to
     * be read narrowly -- see isAddressExempt().
     *
     * Only comments can be told apart with certainty. Forum and front-end
     * submission plugins -- bbPress, BuddyPress -- send visitor text through
     * 'the_content', which is also the owner's own filter, so no name can
     * separate the two. That is a limit of the approach and is documented in
     * the readme rather than papered over here.
     */
    private const VISITOR_WRITTEN_FILTERS = ['comment_text', 'comment_text_rss'];

    /**
     * Option keys a shortcode attribute must never reach.
     *
     * A shortcode may be written by anybody who may write a post. Key material
     * is not a presentation setting.
     */
    private const NOT_SETTABLE_BY_SHORTCODE = [
        'encryption_password',
        'image_token_secret',
        // The retired image secret is key material too -- it opens every token
        // made before the last rotation. Leaving it out was the exact mistake
        // this list exists to prevent, made one release after the list was
        // written.
        'image_token_secret_previous',
        'image_token_secret_previous_until',
        'secrets_rotated_at',
        'version',
        // Not key material, but not presentation either: shortcode_atts()
        // makes every key of the option array settable, so leaving this one in
        // would let anybody who may write a post move the date on which the
        // site owner is asked for a review.
        'review_prompt_due',
    ];

    /**
     * The feed counterpart of each content filter.
     *
     * WordPress builds a feed from its own filters, not from the ones that
     * render a page: <description> comes from 'the_excerpt_rss',
     * <content:encoded> from 'the_content_feed'.
     */
    private const FEED_FILTERS = [
        'the_content' => 'the_content_feed',
        'the_excerpt' => 'the_excerpt_rss',
        'comment_text' => 'comment_text_rss',
    ];

    /**
     * Filters after which WordPress expands shortcodes.
     *
     * Measured, not assumed: has_filter($name, 'do_shortcode') is 11 for these
     * four and false for the other five CryptX hangs on. Only here may an
     * unexpanded [cryptx] be set aside, because only here does something come
     * along afterwards to deal with it.
     */
    private const SHORTCODE_EXPANDED_AFTER = [
        'the_content',
        'render_block',
        'widget_text_content',
        'widget_block_content',
    ];
    const ASCII_VALUES_BLACKLIST = ['32', '34', '39', '60', '62', '63', '92', '94', '96', '127'];
    /** Upper bound for the text rendered into a PNG, see cryptXtinyUrl(). */
    private const MAX_IMAGE_TEXT_LENGTH = 254;

    /**
     * Upper bound for the path segment the image endpoint reads.
     *
     * Wider than the text it may draw, because a token is longer than the
     * address inside it -- padded to a multiple of 32, plus IV and tag, plus
     * base64. Wide enough for the longest address the drawing limit allows,
     * and still nowhere near a size that could hurt.
     */
    private const MAX_IMAGE_REQUEST_LENGTH = 512;
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
    private static ?array $exemptAddressCache = null;

    /**
     * True while the shortcode handler is processing its own content.
     *
     * Set in one place rather than threaded through the three stages: each of
     * them already carries a shortcode flag, but the decisions that need it sit
     * inside preg_replace_callback() handlers with fixed signatures, and
     * rewriting that machinery to pass an argument would risk more than it
     * buys.
     */
    private bool $inShortcode = false;

    private const FONT_EXTENSION = 'ttf';
    private const PAYPAL_DONATION_URL = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4026696';
    private Admin\SettingsPage $settingsPage;
    private Admin\SiteHealth $siteHealth;
    private Admin\ReviewNotice $reviewNotice;
    private Block $block;
    private Config $config;

    private function __construct()
    {
        $this->settingsPage = new Admin\SettingsPage();
        $this->siteHealth = new Admin\SiteHealth();
        $this->reviewNotice = new Admin\ReviewNotice();
        $this->block = new Block();
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
        $this->siteHealth->register();
        $this->reviewNotice->register();
        $this->block->register();

        $this->checkAndUpdateVersion();
        $this->addUniversalWidgetFilters(); // Add this line
        $this->initializePluginFilters();
        $this->registerCoreHooks();
        $this->initializeMetaBoxIfEnabled();
        $this->registerAdditionalHooks();
    }

    /**
     * Rebuilds the cached options and configuration for the site now in scope.
     *
     * Hooked to 'switch_blog', which WordPress fires for both switch_to_blog()
     * and restore_current_blog(), so the object follows the site rather than
     * the request.
     *
     * @return void
     */
    public function refreshForCurrentSite(): void
    {
        // wp_insert_site() switches into the new site BEFORE its tables exist,
        // and reading options there produces a database error in the log while
        // telling us nothing. wp_is_site_initialized() answers the question
        // without that -- it suppresses errors around its own query.
        //
        // The flag is not needed for the call below as the core stands today:
        // wp_is_site_initialized() only switches when the id differs from the
        // current one (wp-includes/ms-site.php), and we pass our own. It is
        // here for the two ways that changes -- a plugin filtering
        // 'pre_wp_is_site_initialized', or a later core version that switches
        // unconditionally -- either of which would call this method back into
        // itself.
        static $busy = false;

        if ($busy) {
            return;
        }

        $busy = true;

        try {
            if (is_multisite() && !wp_is_site_initialized(get_current_blog_id())) {
                return;
            }

            $this->config = new Config(get_option('cryptX', []));
            self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults();
            self::resetOptionCaches();
        } finally {
            $busy = false;
        }
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

            return;
        }

        if ($currentVersion) {
            return;
        }

        // No stamp at all, and the option nevertheless exists. That is not the
        // fresh install it looks like: Config::save() writes the whole option
        // array whenever it has to mint a secret, and on a front-end request
        // that happens without installCryptX() ever running -- so the row is
        // created with 'version' => null, from Config::DEFAULT_OPTIONS.
        //
        // Left alone, that state is permanent. updateCryptXSettings() treats a
        // null version as "nothing to migrate" and returns, and nothing else
        // ever writes the stamp, so every future migration is skipped in
        // silence. Stamping it here costs one write, once.
        //
        // Stamping rather than migrating is the right half: a null version
        // means there is no earlier CryptX data to bring forward, which is
        // exactly the case the migrations already decline to handle.
        if (get_option('cryptX', null) === null) {
            return;
        }

        self::$cryptXOptions['version'] = CRYPTX_VERSION;
        update_option('cryptX', self::$cryptXOptions);
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

        $this->addFeedFilters();
    }

    /**
     * Registers the feed counterparts of the active content filters.
     *
     * Without these, "Leave RSS feeds unprotected = off" only half worked. A
     * feed's <description> comes from the_excerpt_rss(), and nothing CryptX
     * hangs on runs on the way there: on a block theme the plugin sits on
     * 'render_block', which fires only from do_blocks() -- and
     * wp_trim_excerpt() detaches do_blocks before building the excerpt. The
     * address went out in the feed while the setting said it would not.
     *
     * The guard inside the three stages stays as it is; it is what makes the
     * option work in the other direction, for filters that run in both feed
     * and page context.
     *
     * @return void
     */
    private function addFeedFilters(): void
    {
        // The default: feeds are deliberately left alone, because a feed
        // reader runs no JavaScript and a protected link would be dead in it.
        //
        // Read from the store rather than from the static list. The two agree
        // when this runs during startup, but the static one is swapped for the
        // duration of a shortcode and of the settings preview -- and a method
        // that decides which hooks exist has no business depending on which of
        // those happened to be in flight.
        $options = $this->loadCryptXOptionsWithDefaults();

        if (!empty($options['disable_rss'])) {
            return;
        }

        foreach ($this->config->getActiveFilters() as $filter) {
            if (!isset(self::FEED_FILTERS[$filter])) {
                continue;
            }

            $feedFilter = self::FEED_FILTERS[$filter];

            if ($this->config->isAutolinkEnabled()) {
                $this->addAutoLinkFilters($feedFilter, 11);
            }

            $this->addOtherFilters($feedFilter);
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

        // Multisite: this object is built once per request, from whichever site
        // was current at the time. switch_to_blog() changes what get_option()
        // returns but not what this instance already holds -- and
        // getCryptXOptionsDefaults() hands out $this->config, which is the
        // FIRST site's stored values, not a set of defaults. Everything read
        // after a switch therefore came from the wrong site, up to and
        // including its encryption secret.
        add_action('switch_blog', [$this, 'refreshForCurrentSite']);
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

        // Only 'wp_insert_post'. There was a second registration on
        // 'wp_update_post' -- a hook WordPress does not have: the core defines
        // a *function* of that name, and the only do_action() calls are
        // 'wp_insert_post' in wp-includes/post.php. Since wp_update_post()
        // routes through wp_insert_post(), an update was covered all along;
        // the line did nothing and suggested it did.
        add_action('wp_insert_post', [$this, 'addPostIdToExcludedList']);
    }

    /**
     * Registers additional WordPress hooks and shortcodes.
     *
     * @return void
     */
    private function registerAdditionalHooks(): void
    {
        add_filter('plugin_row_meta', [$this, 'add_plugin_action_links'], 10, 2);
        // add_action, nicht add_filter: 'init' ist eine Action. Intern
        // dasselbe, aber der Aufruf soll sagen, was er tut.
        add_action('init', [$this, 'cryptXtinyUrl']);
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
    /**
     * Runs a processing step with unexpanded [cryptx] shortcodes masked out.
     *
     * On a block theme the three filters hang on 'render_block', which fires
     * from do_blocks() at 'the_content' priority 9 -- while do_shortcode()
     * runs at priority 11. CryptX therefore sees the shortcode as raw text,
     * long before it becomes anything.
     *
     * Left alone, that ends badly in two ways. The address inside
     * "[cryptx]info@example.com[/cryptx]" is not linked, because it sits
     * behind a "]", yet the display stage replaces it anyway -- the same
     * silent failure the autolink patterns were widened for. And once the
     * replacement inserts "[at]" and "[dot]", the new square brackets tear the
     * shortcode apart, so the parser later prints the wreckage into the page.
     *
     * Masking hands the shortcode to do_shortcode() untouched. It does its own
     * encrypting, with its own attributes, exactly as on a classic theme.
     *
     * @param string $content The content.
     * @param callable $process Receives the masked content, returns the result.
     *
     * @return string The processed content, with the shortcodes back in place.
     */
    private function withShortcodesProtected(string $content, callable $process): string
    {
        // Masking is only safe where do_shortcode() runs after us. In
        // 'comment_text', 'the_excerpt', 'the_meta_key', 'widget_text' and
        // 'widget_custom_html_content' it does not -- WordPress never expands
        // shortcodes there. Masking unconditionally therefore handed the
        // address to nobody at all: it was skipped here and never picked up
        // later, and a "[cryptx]" written into a comment shipped the address in
        // the clear. 4.1.0 at least obfuscated it.
        //
        // Where the shortcode is not going to be expanded, the literal
        // "[cryptx]" stays visible in the output and the address inside it is
        // obfuscated like any other. Ugly, and the same as before -- but the
        // address is covered.
        if (stripos($content, '[cryptx') === false) {
            return $process($content);
        }

        if (!in_array(current_filter(), self::SHORTCODE_EXPANDED_AFTER, true)) {
            // The shortcode never runs here, so cryptXShortcode() never sets
            // its flag -- and without it the exemption list would win over a
            // "[cryptx]" that was written precisely to overrule it. On a site
            // that exempts its own domain, an address wrapped in a shortcode
            // inside a hand-written excerpt or a custom field would have gone
            // out in the clear: not a shortcoming of the new list, but a step
            // back from 4.1.1, which obfuscated it.
            //
            // The flag covers the whole string rather than the shortcode's
            // body, because finding the body means splitting the content, and
            // splitting changes what the autolink patterns see either side of
            // the cut. The cost is that an exempt address elsewhere in the same
            // excerpt is obfuscated too. That is the harmless direction: too
            // much protection in a rare case, never too little.
            $wasInShortcode = $this->inShortcode;
            $this->inShortcode = true;

            try {
                return $process($content);
            } finally {
                $this->inShortcode = $wasInShortcode;
            }
        }

        $store = [];
        $prefix = $this->maskingPrefix('sc');

        // WordPress' own idea of what a shortcode looks like, rather than a
        // hand-rolled one: it knows the self-closing form, the enclosing form
        // and -- the reason this matters below -- the escaped form.
        $pattern = '/' . get_shortcode_regex(['cryptx']) . '/s';

        $masked = preg_replace_callback(
            $pattern,
            static function (array $match) use (&$store, $prefix): string {
                // "[[cryptx]...[/cryptx]]" is how a page shows a shortcode
                // instead of running it -- an instructions page explaining
                // CryptX, typically. do_shortcode() deliberately leaves it as
                // text, so masking it would carry the address straight through
                // to the visitor in the clear. Groups 1 and 6 are the extra
                // brackets; when both are there, this is not ours to protect
                // and has to go through the normal obfuscation.
                if (($match[1] ?? '') === '[' && ($match[6] ?? '') === ']') {
                    return $match[0];
                }

                $store[] = $match[0];

                return sprintf('<!--%s:%d-->', $prefix, count($store) - 1);
            },
            $content
        );

        // A PCRE failure must not cost the content; process it unmasked.
        if ($masked === null) {
            return $process($content);
        }

        $result = $process($masked);

        // Under 'render_block' the shortcode is expanded here rather than left
        // for later. The other three entries in SHORTCODE_EXPANDED_AFTER carry
        // do_shortcode() themselves; 'render_block' does not -- it relies on
        // the_content running afterwards, and there are core paths where that
        // never happens. A block pattern pulled in through core/pattern is
        // rendered by do_blocks() alone (wp-includes/blocks/pattern.php), so a
        // masked shortcode would have been handed to nobody and the address
        // would have reached the page in the clear.
        //
        // Expanding twice is harmless: whatever runs later finds an anchor, no
        // shortcode.
        if (current_filter() === 'render_block') {
            $store = array_map('do_shortcode', $store);
        }

        $tokens = array_map(
            static fn(int $index): string => sprintf('<!--%s:%d-->', $prefix, $index),
            array_keys($store)
        );

        return str_replace($tokens, $store, $result);
    }

    /**
     * Builds a mailto query from the shortcode's mail attributes.
     *
     * @param array<string, mixed> $attributes Lower-cased shortcode attributes.
     *
     * @return string The cleaned query, or an empty string.
     */
    private function buildMailtoQueryFromAttributes(array $attributes): string
    {
        $pairs = [];

        foreach (self::MAILTO_ATTRIBUTES as $name) {
            if (!isset($attributes[$name]) || is_array($attributes[$name])) {
                continue;
            }

            $value = (string) $attributes[$name];

            if (trim($value) === '') {
                continue;
            }

            $pairs[] = $name . '=' . rawurlencode($value);
        }

        // Straight through the same gate an address in the page goes through,
        // so the shortcode cannot express anything a link could not.
        return $this->sanitizeMailtoQuery(implode('&', $pairs));
    }

    /**
     * Appends a query to every mailto link that does not already carry one.
     *
     * A link written by hand with its own "?subject=" keeps it: the more
     * specific instruction wins over the shortcode's blanket one.
     *
     * @param string $content The content, after autolinking.
     * @param string $query The query to append, without the "?".
     *
     * @return string The content with the query in place.
     */
    private function addQueryToMailtoLinks(string $content, string $query): string
    {
        $result = preg_replace_callback(
            '/(href\s*=\s*(["\']))mailto:([^"\']+)(\2)/i',
            static function (array $match) use ($query): string {
                if (strpos($match[3], '?') !== false) {
                    return $match[0];
                }

                return $match[1] . 'mailto:' . $match[3] . '?' . $query . $match[4];
            },
            $content
        );

        // Same reasoning as every other preg_* call site here: a PCRE failure
        // yields null, and handing that on would empty the content.
        return $result ?? $content;
    }

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
        $attributes = array_change_key_case($attributes, CASE_LOWER);

        // The mail headers are pulled out first. They are not options -- there
        // is no "subject" in the option store and never was -- so leaving them
        // in would hand them to shortcode_atts(), which drops anything it does
        // not recognise. That is precisely what happened to "subject" for
        // years: accepted by the parser, silently discarded, and documented as
        // working.
        $mailQuery = $this->buildMailtoQueryFromAttributes($attributes);
        $attributes = array_diff_key($attributes, array_flip(self::MAILTO_ATTRIBUTES));

        // Update options if attributes provided
        if (!empty($attributes)) {
            // shortcode_atts() keeps whatever is in the defaults it is given,
            // so the option array decides which attribute names have an effect
            // -- and the option array holds the two secrets. Nothing reads them
            // from here today (both go through Config), so this changes no
            // behaviour; it is here so that the next person to reach for
            // self::$cryptXOptions cannot accidentally make key material
            // settable by anyone who may write a post.
            $overridable = array_diff_key(
                $this->loadCryptXOptionsWithDefaults(),
                array_flip(self::NOT_SETTABLE_BY_SHORTCODE)
            );

            self::$cryptXOptions = array_merge(
                array_intersect_key(
                    $this->loadCryptXOptionsWithDefaults(),
                    array_flip(self::NOT_SETTABLE_BY_SHORTCODE)
                ),
                shortcode_atts($overridable, $attributes, $tag)
            );
            self::resetOptionCaches();
        }

        // Saved and restored rather than set to false at the end: should this
        // ever run nested, the outer shortcode must keep its own state.
        $wasInShortcode = $this->inShortcode;
        $this->inShortcode = true;

        try {
            // Process content (inline the encryptAndLinkContent logic)
            if (self::$cryptXOptions['autolink'] ?? false) {
                $content = $this->addLinkToEmailAddresses($content, true);
            }

            // After autolinking, so a bare address in the shortcode body has a
            // link to carry the headers, and before encrypting, so they end up
            // inside the payload rather than in the page.
            if ($mailQuery !== '') {
                $content = $this->addQueryToMailtoLinks($content, $mailQuery);
            }

            $content = $this->findEmailAddressesInContent($content, true);
            $processedContent = $this->replaceEmailInContent($content, true);
        } finally {
            // Restored in a finally block: self::$cryptXOptions is static, so
            // an exception escaping from here would leave the shortcode's
            // values in place for the rest of the request.
            $this->inShortcode = $wasInShortcode;
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

        // Two different bounds, and they used to be one. What has to be limited
        // is the text that gets DRAWN -- without a bound a long request sizes
        // the canvas up accordingly and exhausts the memory limit, a cheap
        // denial of service. What arrives in the URL is now a token, and a
        // token is longer than the address inside it: cutting the request at
        // the drawing limit silently broke every address from about 160
        // characters upwards, because the token was truncated before it could
        // be read. So the request gets a bound of its own, wide enough for any
        // token and still far from anything that could hurt.
        $requested = substr(rawurldecode($params[count($params) - 1]), 0, self::MAX_IMAGE_REQUEST_LENGTH);
        if ($requested === '') {
            return;
        }

        $msg = ImageToken::read($requested);

        if ($msg === '') {
            // No token: either an URL from a page cached before the update, or
            // somebody asking for arbitrary text to be drawn. Pages cached
            // before the update carry the address entity-encoded, and dropping
            // them would leave a broken image where an address should be for as
            // long as the cache lives -- so they are still served.
            //
            // But only if what they ask for really is an address. That is the
            // difference to before: this endpoint used to draw whatever text a
            // request named, which made it a picture generator for anyone who
            // found it. The old form stays workable, the abuse does not.
            //
            // The entity decoding is belt and braces with no path to it, and
            // that is worth saying so nobody later mistakes it for a tested
            // guarantee: a browser resolves the entities before it makes the
            // request, so what arrives here is the plain address. The encoded
            // form cannot even reach this line -- sanitize_text_field() above
            // strips percent sequences, and an unencoded "#" is cut off as a
            // fragment. It stays because it costs nothing and would carry a
            // proxy that did deliver the encoded form.
            $decoded = html_entity_decode($requested, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (!Exposure::isAddress($decoded)) {
                return;
            }

            $msg = $decoded;
        }

        // Whichever way it arrived, this is the bound that matters: it is what
        // gets drawn, and therefore what sizes the canvas. Exposure::isAddress()
        // has no length limit of its own.
        $msg = substr($msg, 0, self::MAX_IMAGE_TEXT_LENGTH);

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
            // No isAutolinkEnabled() check here, unlike the branch above that
            // handles every other filter -- so switching autolink off leaves it
            // on in widgets. Measured, not assumed: with autolink=0,
            // has_filter() is false for the_content, the_excerpt and
            // comment_text and true for all three widget filters.
            //
            // Left as it is on purpose. The difference errs towards protection:
            // a plain address in a sidebar is linked and encrypted rather than
            // left readable. Honouring the setting here would mean an update
            // that makes addresses readable on sites that never asked for that,
            // which is the one direction this plugin must not move in silently.
            // The setting's help text says so instead.
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
        self::$exemptAddressCache = null;
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
            $content = $this->withShortcodesProtected(
                $content,
                fn(string $masked): string => $this->replaceEmailWithLinkText($masked)
            );
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
        if ($this->inWhiteList($Match) || $this->isAddressExempt($Match[1])) {
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
     * A placeholder that content cannot forge.
     *
     * The masking steps set a piece of content aside, run something over the
     * rest, and put it back by searching for the placeholder they left. With a
     * fixed placeholder that search cannot tell its own marker from one an
     * author typed: a post explaining CryptX, a code example, or a comment
     * written by a stranger. Whoever wrote it got the stored value substituted
     * into their text -- an address they never wrote, appearing in their post.
     *
     * Nothing could be injected that way, because the store only ever holds
     * matches of the address pattern and those cannot contain a markup
     * character. But it altered content, and content nobody typed is a bug
     * whatever its contents. The random part per call closes it: the author
     * cannot write a placeholder that this call will look for.
     *
     * @param string $kind Distinguishes the two masking steps.
     *
     * @return string The prefix, unique to this call.
     */
    private function maskingPrefix(string $kind): string
    {
        try {
            $nonce = bin2hex(random_bytes(8));
        } catch (\Exception $e) {
            // Only reachable when the platform has no source of randomness at
            // all. Falling back keeps the page rendering; wp_rand() is seeded
            // well enough for a marker that lives for one request.
            $nonce = dechex(wp_rand(0, PHP_INT_MAX)) . dechex(wp_rand(0, PHP_INT_MAX));
        }

        return 'cryptx-' . $kind . '-' . $nonce;
    }

    /**
     * Runs a step with the exempt addresses masked out of the content.
     *
     * @param string $content The content.
     * @param callable $process Receives the masked content, returns the result.
     *
     * @return string The processed content, addresses back in place.
     */
    private function withExemptAddressesProtected(string $content, callable $process): string
    {
        if (strpos($content, '@') === false) {
            return $process($content);
        }

        $store = [];
        $prefix = $this->maskingPrefix('keep');

        $masked = preg_replace_callback(
            '/[_a-zA-Z0-9-+]+(\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})/',
            function (array $match) use (&$store, $prefix): string {
                if (!$this->isAddressExempt($match[0])) {
                    return $match[0];
                }

                $store[] = $match[0];

                // No "@" in the token, so none of the address patterns can see
                // it, and no square brackets, so a shortcode cannot be torn
                // apart by it either.
                return sprintf('<!--%s:%d-->', $prefix, count($store) - 1);
            },
            $content
        );

        if ($masked === null) {
            return $process($content);
        }

        $result = $process($masked);

        $tokens = array_map(
            static fn(int $index): string => sprintf('<!--%s:%d-->', $prefix, $index),
            array_keys($store)
        );

        return str_replace($tokens, $store, $result);
    }

    /**
     * Whether an address is one the site owner asked CryptX to leave alone.
     *
     * The endings list next door answers a different question -- it keeps
     * "logo@2x.png" from being mistaken for an address at all. This one is
     * about real addresses that are meant to stay readable: a support address
     * a helpdesk parses out of the page, an address in a code example, an
     * address a partner site scrapes on purpose. Until now the answer was "not
     * possible", and the FAQ said so.
     *
     * An entry is either a whole address, or "@example.com" for every address
     * at that domain. The domain form is the common case: a site tends to want
     * its own addresses treated alike.
     *
     * Two places deliberately do not honour the list, both for the same
     * reason -- a blanket setting must not overrule a narrower instruction:
     *
     * Inside "[cryptx]...[/cryptx]" nothing is exempt. The shortcode is
     * somebody writing "protect this one, here"; a list entry set months ago on
     * another screen is not an answer to that. The reverse order let a site
     * that had exempted its own domain publish, in the clear, exactly the
     * address it had wrapped in a shortcode to protect.
     *
     * In comments only the whole-address form counts. Comments are written by
     * strangers, and "@example.com" is a statement about the site's own
     * addresses, not about every address at that domain a visitor might leave
     * behind. Honouring the domain form there turned the comment section into a
     * harvest for anyone who could guess the domain. A whole address is
     * different: the site owner named that one address exactly, and a visitor
     * quoting it is quoting the site's own.
     *
     * @param string $address The address, as written in the content.
     *
     * @return bool True when CryptX must not touch it.
     */
    private function isAddressExempt(string $address): bool
    {
        if ($this->inShortcode) {
            return false;
        }

        if (self::$exemptAddressCache === null) {
            $raw = (string) (self::$cryptXOptions['exemptAddresses'] ?? '');
            self::$exemptAddressCache = array_filter(
                array_map(
                    static fn(string $entry): string => strtolower(trim($entry)),
                    explode(',', $raw)
                ),
                'strlen'
            );
        }

        if (self::$exemptAddressCache === []) {
            return false;
        }

        $address = strtolower(trim($address));

        if (in_array($address, self::$exemptAddressCache, true)) {
            return true;
        }

        if (in_array(current_filter(), self::VISITOR_WRITTEN_FILTERS, true)) {
            return false;
        }

        $at = strrpos($address, '@');

        if ($at === false) {
            return false;
        }

        return in_array(substr($address, $at), self::$exemptAddressCache, true);
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
        // Escaped here rather than at the source: a shortcode attribute of the
        // same name reaches self::$cryptXOptions without passing through the
        // settings validation at all.
        //
        // esc_html and not wp_kses_post, although the settings screen stores
        // the value with wp_kses_post: the link text sits inside an anchor that
        // CryptX builds itself, and markup there could close that anchor early.
        // The two stages therefore mean different things on purpose -- storage
        // keeps what a post may contain, output shows it as text. See
        // SettingsSchema::sanitizeValue().
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

        $address = (string) $Match[1];

        // Until 4.2.0 this put antispambot($address) into the URL, the alt and
        // the title. Entity-encoding stops nothing that decodes entities -- and
        // the browser decodes them before it makes the request, so the address
        // travelled in the request line of every image load: into the access
        // log, and through every proxy and CDN on the way. A visitor's browser
        // handed the address to more machines than a plainly written one would
        // have.

        // Built before the token, because the fallback below needs it too.
        //
        // _x() rather than __(): on its own the phrase could be a field label,
        // a heading or a column name, and a translator seeing it in a list has
        // no way to tell.
        $label = _x(
            'Email address',
            'alt text of the picture that shows an email address',
            'cryptx'
        );

        $token = ImageToken::mint($address);

        if ($token === '') {
            // No token, no picture. Three answers were possible and two are
            // wrong. Falling back to the old URL would put the address straight
            // back where this took it out. Returning an empty string leaves
            // "<a href=\"#\" data-cx=\"...\"></a>" -- a link with nothing in it,
            // invisible on the page and nameless to a screen reader.
            //
            // The third, and the tempting one, is the ordinary obfuscated text.
            // It writes the address as " [at] " and " [dot] ", which any
            // harvester undoes with a single regular expression -- and escaping
            // exactly that is why somebody chose this variant. Worse, those two
            // separators are free-text settings: a site that put them back to
            // "@" and "." would have the address written out in full.
            //
            // So the label the picture would have carried, and no address
            // anywhere.
            return esc_html($label);
        }

        // Not the address in the alt attribute either. An alt is read out by
        // screen readers and indexed by crawlers alike; putting the address
        // there would hand it to both, and the link works for either of them
        // without it. "Email address" says what the picture is, which is what
        // an alt attribute is for.

        // No title attribute. It used to repeat the alt text, which some
        // assistive software then reads out twice and which adds nothing for
        // anybody else. While both carried the address that was merely
        // pointless; now it would be noise.
        return sprintf(
                '<img src="%s" class="cryptxImage cryptxImage_%d" alt="%s" />',
                esc_url(get_bloginfo('url') . '/' . md5(get_bloginfo('url')) . '/' . $token),
                self::$imageCounter,
                esc_attr($label)
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
        $that = $this;

        // For widgets, always process since there's no specific post context
        // For other content, check exclusion rules
        if ($isWidgetContext || !$isIdExcluded || $shortcode) {
            $content = $this->withShortcodesProtected($content, static function (string $masked) use ($mailtoRegex, $that): string {
                $result = preg_replace_callback($mailtoRegex, [$that, 'encryptEmailAddressSecure'], $masked);

                // null means PCRE gave up (backtrack limit). Keeping the
                // original content is far better than returning null and
                // wiping the page.
                return $result ?? $masked;
            });
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

        // The same gate the other two stages carry, and missing here until
        // 4.1.1. "Leave RSS feeds unprotected" is meant as "do not touch
        // feeds"; without this, the autolink stage still turned a bare address
        // into a mailto link in the feed, while the two stages that protect it
        // stepped aside. The result was not a leak -- with the option on, the
        // address is in the feed either way -- but it was CryptX changing
        // content it had just been told to leave alone.
        //
        // The $shortcode exception is made here and not in the other two
        // stages: those bail out of a feed unconditionally. Keeping it means
        // the shortcode path behaves exactly as it did before this guard
        // existed, which is the point -- the shortcode is an explicit
        // instruction and outranks a blanket setting.
        if (!$shortcode && self::$cryptXOptions['disable_rss'] && $this->isRssFeed()) {
            return $content;
        }

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
        // Two widenings, both from the same report. The patterns after ">"
        // required a "<" or whitespace to follow, so an address that ended the
        // string right after a tag -- "Kontakt:<br>info@example.com" -- was
        // never linked; hence the "$" variant. And they accepted only ">",
        // while wp_kses_post() turns a bare ">" into "&gt;", leaving a ";"
        // in front of the address; hence "[>;]", which covers the end of any
        // HTML entity.
        //
        // In post content neither showed much, because a closing tag almost
        // always follows an address. Through cryptx_encrypt() both showed every
        // time. Worse than the missing link was what came next: the display
        // stage still swapped the address for the configured link text, so the
        // address vanished from the page without anything working taking its
        // place.
        $src = [
                "/([\\s])($emailPattern)/si",
                "/([>;])($emailPattern)(<)/si",
                "/(\\()($emailPattern)(\\))/si",
                "/([>;])($emailPattern)([\\s])/si",
                "/([\\s])($emailPattern)(<)/si",
                "/([>;])($emailPattern)$/si",
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
                "\\1$linkPattern",
                "<a href=\"mailto:\\0\">\\0</a>",
                "\\1",
                "\\1"
        ];

        return $this->withShortcodesProtected($content, function (string $masked) use ($src, $tar): string {
            // Exempt addresses are set aside for the duration. The eight
            // patterns below are a preg_replace, not a callback, so there is no
            // per-match decision to hook into -- and rewriting that machinery
            // to get one would risk far more than it buys.
            return $this->withExemptAddressesProtected(
                $masked,
                static function (string $inner) use ($src, $tar): string {
                    $result = preg_replace($src, $tar, $inner);

                    // Same reasoning as elsewhere: a PCRE failure yields null,
                    // and handing that on would silently empty the page.
                    return $result ?? $inner;
                }
            );
        });
    }

    /**
     * Installs the CryptX plugin by updating its options and loading default values.
     */
    public function installCryptX(): void
    {
        global $wpdb;

        // Load-bearing, not a duplicate of the 'switch_blog' hook -- do not
        // remove it as one. When a plugin is activated, WordPress includes its
        // file from activate_plugin(), long after plugins_loaded has fired, so
        // startCryptX() never runs in that request and the hook is not
        // registered. Measured: activating an inactive plugin, has_action(
        // 'switch_blog') is false throughout. Without this line the network
        // activation loop writes site 1's values into every other site --
        // secret, link text and exclusion list -- which is how the bug was
        // found in the first place.
        $this->refreshForCurrentSite();

        // Nothing is written into a site whose tables do not exist yet.
        // refreshForCurrentSite() returns early in that case WITHOUT touching
        // the static option list -- and that list is static, so it survives
        // switch_to_blog(). The update_option() at the end of this method would
        // then write the PREVIOUS site's values into the new one, exclusion
        // list included: the 4.1.1 bug, reached through a different door.
        //
        // No caller does that today (wp_initialize_site runs at priority 20,
        // after the tables exist), which is exactly why this is here: the
        // guarantee should not depend on the priority of somebody else's hook.
        if (is_multisite() && !wp_is_site_initialized(get_current_blog_id())) {
            return;
        }

        // A site that has never stored anything starts from the network's
        // defaults rather than the plugin's. Only then: a site with a stored
        // option has an administrator who chose something, and a network
        // default is a starting point, not an instruction. Getting that
        // backwards is how 4.1.1 came to publish addresses on sites whose
        // owners had excluded them -- the two settings that caused it are not
        // shareable at all, see Admin\NetworkDefaults.
        if (is_multisite() && get_option('cryptX', null) === null) {
            self::$cryptXOptions = array_merge(
                self::$cryptXOptions,
                Admin\NetworkDefaults::forNewSite()
            );
        }

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
        // The only caller, addPostIdToExcludedList(), checks the nonce field,
        // then wp_verify_nonce(), then current_user_can('edit_post', $postId)
        // before reaching this method. The scanner cannot follow three call
        // levels and sees only the superglobal. Read as presence or absence of
        // a checkbox; the value is never used.
        //
        // The annotation has to sit on the line directly above the statement --
        // with the explanation above it, it silenced the next comment line and
        // the warning stayed.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
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
     * That head branch deliberately does not look at "java" at all, and never
     * did -- this method leaves it untouched. It follows that a
     * "[cryptx java=...]" shortcode override has nothing to add there: the
     * script is already on every page regardless of the global setting, so
     * only the footer branch below needs to read "java" or care about a
     * shortcode overriding it.
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
        } elseif (!empty(self::$cryptXOptions['java'])) {
            // Footer placement, JavaScript handler enabled: enqueue cryptx-js
            // unconditionally, here, before it is known whether THIS request's
            // content carries an address. wp_register_script() above already
            // registered it with $inFooter = true, so this does not move the
            // print location -- it still prints in wp_footer, exactly as
            // before. Deferring to enqueueAssetsIfNeeded() (scriptNeeded) only
            // covers a classic page load, where the address a visitor clicks
            // is guaranteed to be in the same document that carried the
            // script. A client-side navigation (swup.js, PJAX, Barba, Turbo)
            // can land a visitor on a page with no address at all and then
            // drop .cryptx-link elements in later, without ever loading a
            // second script -- the delegated handler in cryptx.js was simply
            // never attached. See
            // docs/entscheidungen/2026-09-11-assets-bei-clientseitiger-navigation.md
            // for the analysis.
            //
            // Known remaining gap, not closable from here: this branch only
            // reads the global "java" setting. A page whose first load carries
            // no [cryptx java="1"] shortcode, under a global java = 0, still
            // enqueues nothing here -- so a later client-side navigation to a
            // page that DOES carry that shortcode still finds no click handler
            // attached. See the decision doc above for why this is left open.
            wp_enqueue_script('cryptx-js');
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
            // Still needed as a net: a shortcode can set java=1 for its own
            // instance while the global setting says java=0, since "java" is
            // not in NOT_SETTABLE_BY_SHORTCODE. loadJavascriptFiles() only
            // sees the global setting, so this is what catches that case. A
            // second wp_enqueue_script() on an already-enqueued handle is a
            // no-op.
            wp_enqueue_script('cryptx-js');
        }

        // wp_register_style() has no footer flag to lean on the way the
        // script does, and enqueuing the stylesheet at wp_enqueue_scripts
        // would move it from the footer to <head> -- a behaviour change for
        // classic navigation, which is exactly what must not happen. So the
        // same client-side-navigation gap for a configured picture variant
        // (opt_linktext 2/3/5, the only settings that need img.cryptxImage
        // { height: 1em }) is closed here instead, gated on the setting
        // alone rather than on whether THIS request's content produced one.
        if (self::$styleNeeded || self::isPictureLinktext((int) (self::$cryptXOptions['opt_linktext'] ?? 0))) {
            wp_enqueue_style('cryptx-styles');
        }
    }

    /**
     * Whether an opt_linktext setting renders links as pictures.
     *
     * Same known gap as the script branch in loadJavascriptFiles(), and purely
     * cosmetic here: a page whose first load carries no picture-producing
     * shortcode override, under a global opt_linktext that is not 2/3/5, still
     * skips the stylesheet -- a later client-side navigation to a page that
     * DOES render a picture link can arrive without img.cryptxImage. See
     * docs/entscheidungen/2026-09-11-assets-bei-clientseitiger-navigation.md.
     *
     * @param int $optLinktext The opt_linktext setting value.
     *
     * @return bool
     */
    private static function isPictureLinktext(int $optLinktext): bool
    {
        return in_array($optLinktext, [2, 3, 5], true);
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

        $storedVersion = self::$cryptXOptions['version'] ?? null;

        if ($storedVersion === null || version_compare(CRYPTX_VERSION, $storedVersion) <= 0) {
            return;
        }

        // Every step below used to run on EVERY version bump, although each was
        // written for one particular upgrade. Measured on an installation
        // carrying 4.1.0: the chosen font fell back to the first available one,
        // the colour "#3366ff" became "##3366ff" -- gaining another "#" with
        // every future update -- and the encryption secret was thrown away, so
        // every link on an already cached page stopped resolving. None of that
        // was intended, and none of it was visible to the site owner.
        //
        // Each migration is now tied to the version it belongs to, or written
        // so that repeating it changes nothing.

        // Up to 4.0.11 the password was derived from AUTH_KEY and
        // SECURE_AUTH_KEY, and that value is published in the markup of every
        // page. Since site_url is public, an attacker could test candidate keys
        // offline -- above all the placeholders from wp-config-sample.php that
        // unattended installations still carry. Dropping it lets
        // Config::getEncryptionPassword() mint a random one. The price is that
        // links on pages already sitting in a cache stop resolving until that
        // cache turns over, which is why it must happen exactly once.
        if (version_compare($storedVersion, '4.0.12', '<')) {
            unset(self::$cryptXOptions['encryption_password']);

            // 4.0.12 replaced the bundled Arial, Times New Roman and Verdana
            // with freely licensed faces. A stored name from the old set no
            // longer exists on disk, so the choice has to be made again.
            unset(self::$cryptXOptions['c2i_font']);
        }

        // Value-based rather than version-based, and therefore harmless to
        // repeat: colours were stored without the leading "#" before 4.0.
        if (!empty(self::$cryptXOptions['c2i_fontRGB'])
            && strpos((string) self::$cryptXOptions['c2i_fontRGB'], '#') !== 0) {
            self::$cryptXOptions['c2i_fontRGB'] = '#' . self::$cryptXOptions['c2i_fontRGB'];
        }

        // Also value-based: an attachment id that is not an id is unusable, no
        // matter which version wrote it.
        if (isset(self::$cryptXOptions['alt_uploadedimage'])
            && !is_int(self::$cryptXOptions['alt_uploadedimage'])
            && !ctype_digit((string) self::$cryptXOptions['alt_uploadedimage'])) {
            unset(self::$cryptXOptions['alt_uploadedimage']);

            if ((int) (self::$cryptXOptions['opt_linktext'] ?? 0) === 3) {
                unset(self::$cryptXOptions['opt_linktext']);
            }
        }

        // Only a feature update earns a review prompt, and only in a fortnight
        // -- Admin\ReviewNotice decides both, because that is where the rule
        // can be read next to the reason for it. This is the only place that
        // still knows which version was installed before.
        self::$cryptXOptions = Admin\ReviewNotice::scheduleAfterUpdate(
            self::$cryptXOptions,
            (string) $storedVersion
        );

        self::$cryptXOptions['version'] = CRYPTX_VERSION;
        self::$cryptXOptions = wp_parse_args(self::$cryptXOptions, $this->getCryptXOptionsDefaults());
        update_option('cryptX', self::$cryptXOptions);
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
        // Admin\SettingsPage::MENU_SLUG und nicht CRYPTX_BASEFOLDER: die
        // Seite haengt am Slug, nicht am Verzeichnisnamen. Auf wordpress.org
        // sind beide 'cryptx', nach einem Umbenennen des Ordners zeigte der
        // Link ins Leere.
        return sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('options-general.php?page=' . Admin\SettingsPage::MENU_SLUG)),
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
                esc_url(self::PAYPAL_DONATION_URL),
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
    /**
     * Cleans the query of a mailto link -- the "?subject=..." part.
     *
     * A positive list, not an exclusion list, because this value ends up
     * decrypted in the browser and handed to window.location. RFC 6068 defines
     * exactly these four headers as safe to accept from a link; everything else
     * is dropped rather than escaped, because there is no legitimate reason for
     * it to be there and no way to be sure what a mail client would do with it.
     *
     * Values are decoded and re-encoded rather than passed through: an incoming
     * "Hallo%20Welt" must not become "Hallo%2520Welt", and a raw space must not
     * stay a raw space.
     *
     * @param string $rawQuery The query as written in the href, without the "?".
     * @param int $budget How many characters the finished query may occupy.
     *
     * @return string The cleaned query, or an empty string if nothing survives.
     */
    private function sanitizeMailtoQuery(string $rawQuery, int $budget = PHP_INT_MAX): string
    {
        if ($rawQuery === '' || $budget <= 0) {
            return '';
        }

        // "&amp;" is how a second parameter is spelled in valid HTML, and that
        // is what the regular expression handed us.
        $rawQuery = html_entity_decode($rawQuery, ENT_QUOTES, 'UTF-8');

        $allowed = ['subject', 'body', 'cc', 'bcc'];
        $parts = [];

        foreach (explode('&', $rawQuery) as $pair) {
            if ($pair === '' || strpos($pair, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = strtolower(trim($key));

            if (!in_array($key, $allowed, true) || isset($parts[$key])) {
                continue;
            }

            $value = rawurldecode($value);

            // A recipient list is still a list of addresses, and an invalid one
            // has no business being carried into a mail client.
            if ($key === 'cc' || $key === 'bcc') {
                $addresses = array_filter(array_map(
                    static fn($address) => sanitize_email(trim($address)),
                    explode(',', $value)
                ));

                if ($addresses === []) {
                    continue;
                }

                $value = implode(',', $addresses);
            } else {
                // Control characters would let a payload break out of the
                // header it is written into.
                $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

                if (trim($value) === '') {
                    continue;
                }

                $value = mb_substr($value, 0, self::MAX_MAILTO_VALUE_LENGTH);
            }

            $pair = $this->fitPairToBudget(
                $key,
                $value,
                // What is left once the pairs already collected, and the "&"
                // that would join this one, are accounted for.
                $budget - strlen(implode('&', $parts)) - ($parts === [] ? 0 : 1),
                ($key === 'cc' || $key === 'bcc') ? ',' : ''
            );

            if ($pair === '') {
                continue;
            }

            $parts[$key] = $pair;
        }

        return implode('&', $parts);
    }

    /**
     * Encodes one header and shortens it until it fits the space left.
     *
     * The value is cut before encoding, never after: percent encoding turns one
     * character into up to twelve, and a cut through "%C3%A4" leaves a sequence
     * no client can read.
     *
     * Why there is a budget at all: cryptx.js refuses to navigate to a URL
     * longer than 2048 characters, and so does SecureEncryption::validateUrl().
     * Counting the value in characters before encoding is not the same measure
     * -- 512 characters of Japanese become over 4000 once encoded. The link
     * then did nothing at all, with nothing on the page to say why.
     *
     * @param string $key The header name.
     * @param string $value The decoded value.
     * @param int $available Characters left for the encoded pair.
     * @param string $separator Set for list values: whole entries are dropped
     *                          instead of characters.
     *
     * @return string The encoded pair, or an empty string if it cannot fit.
     */
    private function fitPairToBudget(
        string $key,
        string $value,
        int $available,
        string $separator = ''
    ): string {
        $encodedKey = rawurlencode($key);

        // The shortest useful pair is "key=" plus one character.
        if ($available < strlen($encodedKey) + 2) {
            return '';
        }

        $pair = $encodedKey . '=' . rawurlencode($value);

        // A recipient list is not free text. Cutting it by characters leaves a
        // fragment like "chef@examp" in a header a mail client will act on --
        // either bouncing or, worse, delivering somewhere unintended. Whole
        // addresses go, or the header goes.
        if ($separator !== '') {
            $items = explode($separator, $value);

            while (strlen($pair) > $available && count($items) > 1) {
                array_pop($items);
                $pair = $encodedKey . '=' . rawurlencode(implode($separator, $items));
            }

            return strlen($pair) > $available ? '' : $pair;
        }

        while (strlen($pair) > $available && $value !== '') {
            $value = mb_substr($value, 0, mb_strlen($value) - 1);
            $pair = $encodedKey . '=' . rawurlencode($value);
        }

        return $value === '' ? '' : $pair;
    }

    private function encryptEmailAddressSecure(array $searchResults): string
    {
        $originalValue = $searchResults[0];  // Full match
        $rawTarget = $searchResults[2];      // Everything after "mailto:", verbatim

        // Address and query are separated BEFORE sanitising. sanitize_email()
        // used to run over the whole target, and it strips "?" and "=" -- so
        // "sales@example.com?subject=Hello" became
        // "sales@example.comsubjectHello". Two things followed from that, both
        // reported in the support forum and neither obvious: the payload
        // carried a broken address, and the str_replace() below could no longer
        // find its needle, so the untouched "mailto:" href stayed in the page.
        $queryPosition = strpos($rawTarget, '?');
        $rawAddress = $queryPosition === false ? $rawTarget : substr($rawTarget, 0, $queryPosition);
        $rawQuery = $queryPosition === false ? '' : substr($rawTarget, $queryPosition + 1);

        $emailAddress = sanitize_email($rawAddress);

        if (strpos($emailAddress, '@') === self::NOT_FOUND) {
            return $originalValue;
        }

        // Left exactly as written, link and all. "Leave this address alone"
        // has to mean all three stages, not just the visible text -- an
        // address that keeps its readable form but loses its working mailto is
        // neither protected nor usable.
        if ($this->isAddressExempt($emailAddress)) {
            return $originalValue;
        }

        // The budget is what the browser will still accept once "mailto:",
        // the address and the "?" are in place.
        $query = $this->sanitizeMailtoQuery(
            $rawQuery,
            self::MAX_MAILTO_URL_LENGTH - strlen('mailto:' . $emailAddress . '?')
        );
        $mailtoTarget = $emailAddress . ($query === '' ? '' : '?' . $query);

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
                    $mailtoUrl = 'mailto:' . $mailtoTarget;
                    $encryptedEmail = SecureEncryption::encrypt($mailtoUrl, $password);
                    $payloadMode = 'secure';
                } catch (\Exception $e) {
                    // Fallback to legacy if secure encryption fails
                    $encryptedEmail = $this->generateHashFromString($mailtoTarget);
                    $password = '';
                }
            } else {
                // Use legacy encryption (original algorithm). cryptx.js puts
                // "mailto:" in front of whatever comes out, so the query rides
                // along here as well.
                $encryptedEmail = $this->generateHashFromString($mailtoTarget);
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
                    // The key travels with the link, so changing the secret
                    // never breaks one that is already out there. The iteration
                    // count did not, and that was the real dead-link problem:
                    // it was read from the global cryptxConfig at click time,
                    // so raising it in the settings silently killed every link
                    // in every cached page and in every browser tab still open.
                    // Now each link says how it was made.
                    $attributes .= sprintf(
                        ' data-cxk="%s" data-cxi="%d"',
                        esc_attr($password),
                        SecureEncryption::getIterations()
                    );
                }

                // The raw target, not the sanitised address: they differ as
                // soon as a query is present, and a needle that is not in the
                // haystack leaves the plain "mailto:" href untouched.
                //
                // str_ireplace, because the pattern above matches case
                // insensitively: an href written "MAILTO:" was found, but a
                // lower-case needle then missed it -- same failure, reached
                // through the spelling of the scheme instead of the query.
                $return = str_ireplace('mailto:' . $rawTarget, '#', $originalValue);
                $return = $this->addAttributesToAnchor($return, $attributes);
                $return = $this->addClassToAnchor($return, self::LINK_CLASS);
            } else {
                // Legacy form, kept for installations that depend on it.
                // The iteration count is passed here too, as a third argument.
                // Older pages call the function with two, which still works --
                // it then falls back to the configured value, exactly as before.
                $javaHandler = $payloadMode === 'secure'
                        ? "javascript:secureDecryptAndNavigate('" . esc_js($encryptedEmail) . "', '"
                            . esc_js($password) . "', " . SecureEncryption::getIterations() . ")"
                        : "javascript:DeCryptX('" . esc_js($encryptedEmail) . "')";

                $return = str_ireplace('mailto:' . $rawTarget, $javaHandler, $originalValue);
            }
        } else {
            // Fallback to antispambot if JavaScript is not enabled
            $return = str_ireplace('mailto:' . $rawTarget,
                    antispambot('mailto:' . $mailtoTarget), $return);
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

        // Same reasoning, same trap, second secret. The image variant mints one
        // of its own the first time an address is drawn as a picture -- and the
        // first time that happens is usually in this very preview, the moment
        // an administrator picks "image" from the list. Minting it through the
        // throwaway Config below would save the unsaved form along with it.
        if (empty($stored['image_token_secret'])) {
            $stored['image_token_secret'] = (new Config($stored))->getImageTokenSecret();
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