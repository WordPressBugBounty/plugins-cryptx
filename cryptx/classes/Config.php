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
     *                  On block themes this filter is swapped for 'render_block'
     *                  at runtime, see CryptX::initializePluginFilters(). There is
     *                  no separate option for it.
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
     * - 'exemptAddresses': Comma-separated addresses CryptX leaves alone; an entry
     *                     may also be a bare domain such as '@example.com' (default: '').
     * - 'whiteList': Comma-separated string of allowed file extensions (default: 'jpeg,jpg,png,gif').
     * - 'disable_rss': Flag to disable CryptX in RSS feeds by default (default: 1).
     * - 'encryption_mode': Encryption mode setting (default: 'secure').
     * - 'encryption_password': Password for encryption; a random secret is generated
     *                          on first use if null and then kept forever (default: null).
     * - 'use_secure_encryption': Flag to enable secure encryption by default (default: 1).
     * - 'iterations': PBKDF2 iteration count for the secure mode (default: 10000).
     * - 'link_mode': How the encrypted link is delivered -- 'data' puts the payload
     *                into data attributes and lets a delegated click handler take
     *                over (survives a Content-Security-Policy), 'js' is the historical
     *                "javascript:" URI (default: 'data').
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
        'exemptAddresses' => '',
        'whiteList' => 'jpeg,jpg,png,gif',
        'disable_rss' => 1,
        'encryption_mode' => 'secure',
        'encryption_password' => null,
        'image_token_secret' => null,
        'image_token_secret_previous' => null,
        'image_token_secret_previous_until' => 0,
        'secrets_rotated_at' => 0,
        'use_secure_encryption' => 1,
        'iterations' => 10000,
        'link_mode' => 'data',
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

    public function __construct(array $options = []) {
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);
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

    // updateFromShortcode() and restoreOriginalOptions() used to sit here. They
    // had no caller anywhere in the plugin -- CryptX::cryptXShortcode() does
    // that job on the static option list -- and they were the more dangerous of
    // the two implementations: they merged shortcode attributes straight into
    // $this->options, which is what getEncryptionPassword() and
    // getImageTokenSecret() read from and what save() writes to the database.
    // Whoever revived them would have made key material settable by anyone
    // allowed to write a post, and the guard added to cryptXShortcode() would
    // not have covered it. Dead code that only waits for someone to call it is
    // worse than no code.

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
     * Resets all options to their default values.
     *
     * Careful before wiring this up again: it has had no caller since 4.1.0,
     * because the settings screen resets through SettingsSchema instead. This
     * method sets the whole array to DEFAULT_OPTIONS, in which
     * encryption_password is null -- and then saves. That discards the secret
     * every already delivered link was encrypted with, so those links stop
     * resolving until the pages are regenerated. SettingsSchema::defaults()
     * covers only the editable options and leaves the secret alone, which is
     * why the REST route uses it.
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
        // A stored password is returned untouched and is NEVER regenerated.
        // The password is baked into every link this plugin has ever emitted,
        // so a new one would turn all already delivered and cached pages into
        // undecryptable garbage. Only a missing (or empty) value is filled in.
        if (empty($this->options['encryption_password'])) {
            // Random secret instead of a value derived from the WordPress keys.
            // Rationale: this password is published. It is handed to the browser
            // as the second argument of the generated
            // javascript:secureDecryptAndNavigate(...) link and therefore sits
            // in the HTML of every page in clear text. Deriving it from AUTH_KEY
            // and SECURE_AUTH_KEY was not reversible, but there is no reason to
            // publish anything at all that is a function of the site's secrets.
            //
            // bin2hex(random_bytes(32)) is the choice because random_bytes() is
            // the platform CSPRNG (always available on the required PHP 8.1+,
            // not filterable by other plugins) and hex output is pure [0-9a-f]:
            // it survives every escaping stage on the way into the JavaScript
            // string literal and into the option row unchanged. 32 bytes = 256
            // bits, matching the AES-256 key later derived from it via PBKDF2.
            try {
                $this->options['encryption_password'] = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                // random_bytes() throws when the system has no usable source of
                // randomness. wp_generate_password() then provides the fallback;
                // 64 chars without special characters keeps the value safe to
                // embed unescaped.
                $this->options['encryption_password'] = wp_generate_password(64, false, false);
            }
            $this->save();
        }
        return $this->options['encryption_password'];
    }

    /**
     * The secret behind the image tokens -- and the one that is never published.
     *
     * getEncryptionPassword() above is handed to the browser with every link;
     * it has to be, because the visitor's browser does the decrypting. Anything
     * keyed with it is therefore readable by whoever reads the page, which is
     * exactly the audience the image variant is hiding from. So the tokens in
     * the image URLs get their own secret, and this one stays on the server.
     *
     * Stored rather than derived from the WordPress salts: rotating those --
     * which an administrator may do at any time, and which only logs everyone
     * out -- would invalidate every image URL in every cached page at once.
     *
     * @return string The secret, minted on first use and then kept.
     */
    public function getImageTokenSecret(): string
    {
        if (empty($this->options['image_token_secret'])) {
            // No fallback to wp_generate_password() here, deliberately -- and
            // that is the difference to getEncryptionPassword() above, which
            // has one. If random_bytes() throws, random_int() throws too, and
            // wp_rand() then falls back to a source that is not cryptographic.
            // For the link password that costs nothing, because the value is
            // published in every link anyway. For this one it is the whole
            // protection: a guessable secret would let anybody rebuild the
            // tokens and read the addresses back out of an access log, while
            // the site went on reporting itself as protected.
            //
            // Returning nothing instead means ImageToken mints nothing, and
            // getImageFromText() renders no picture. The link around it still
            // works and the address is still hidden. Missing a picture is the
            // better failure.
            try {
                $this->options['image_token_secret'] = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                return '';
            }

            $this->save();
        }

        return (string) $this->options['image_token_secret'];
    }

    /**
     * How long a replaced image secret keeps working.
     *
     * Long enough to outlive any ordinary page cache, short enough that a
     * secret somebody wanted rid of does not stay usable indefinitely.
     */
    private const IMAGE_SECRET_GRACE = 30 * DAY_IN_SECONDS;

    /**
     * Replaces both secrets with fresh ones.
     *
     * The two behave completely differently under a change, and that is the
     * whole reason this method exists rather than a line of code somewhere:
     *
     * The link password can be replaced at any moment with no consequence at
     * all. It travels inside every link -- data-cxk, or the second argument of
     * the javascript: call -- so a link already sitting in a cache carries the
     * password it was made with and goes on working for ever. Measured, not
     * assumed: the note in updateCryptXSettings() claiming that a new password
     * kills cached links describes a format that no longer exists.
     *
     * The image secret is the opposite: it never leaves the server, so a token
     * in a cached page can only be read while the secret that made it is still
     * known. Replacing it therefore keeps the old one for a grace period, and
     * ImageToken::read() falls back to it.
     *
     * @return void
     */
    public function rotateSecrets(): void
    {
        $previous = $this->peekImageTokenSecret();

        unset($this->options['encryption_password'], $this->options['image_token_secret']);

        if ($previous !== '') {
            $this->options['image_token_secret_previous'] = $previous;
            $this->options['image_token_secret_previous_until'] = time() + self::IMAGE_SECRET_GRACE;
        }

        // Minted here rather than left to the next page view, so that a failure
        // is visible while an administrator is looking at the screen.
        $this->getEncryptionPassword();
        $this->getImageTokenSecret();

        $this->options['secrets_rotated_at'] = time();

        $this->save();
    }

    /**
     * The replaced image secret, while it is still within its grace period.
     *
     * @return string The previous secret, or an empty string.
     */
    public function previousImageTokenSecret(): string
    {
        $until = (int) ($this->options['image_token_secret_previous_until'] ?? 0);

        if ($until < time()) {
            return '';
        }

        return (string) ($this->options['image_token_secret_previous'] ?? '');
    }

    /**
     * Drops a replaced image secret once its grace period is over.
     *
     * Separate from the getter above, and called only from the settings screen,
     * because the getter runs on the image endpoint -- on an unauthenticated
     * request from a stranger. Writing an option there is the same mistake that
     * was taken out of ImageToken::read() one round earlier: a stranger should
     * not decide when this site writes to its own database.
     *
     * Returning '' is already enough to stop using the value. This is about not
     * leaving a retired secret sitting in wp_options for ever next to the one
     * that replaced it -- hygiene, not a hole: whoever can read that table has
     * the current secret anyway.
     *
     * @return void
     */
    public function forgetExpiredImageTokenSecret(): void
    {
        $until = (int) ($this->options['image_token_secret_previous_until'] ?? 0);

        if ($until >= time() || empty($this->options['image_token_secret_previous'])) {
            return;
        }

        $this->options['image_token_secret_previous'] = null;
        $this->options['image_token_secret_previous_until'] = 0;
        $this->save();
    }

    /**
     * When the secrets were last replaced, if ever.
     *
     * @return int A Unix timestamp, or 0.
     */
    public function secretsRotatedAt(): int
    {
        return (int) ($this->options['secrets_rotated_at'] ?? 0);
    }

    /**
     * The image secret if there is one, without minting.
     *
     * Reading a token never needs one to exist: if there is no secret, no token
     * was ever made and nothing can decode. Minting on the read path would let
     * a stranger who calls the image endpoint decide the moment the secret
     * comes into being -- and two such calls arriving together can each mint
     * one, after which whichever loses has published image URLs that will never
     * resolve again. Narrow, but the consequence outlives the request: those
     * URLs sit in caches.
     *
     * To be precise about what this does and does not fix: it takes the timing
     * away from a stranger. Two ordinary first page views arriving together can
     * still each mint, with the same consequence -- the same race the link
     * password has always had. That window closes at the first uncached render;
     * it is not worth an option row of its own.
     *
     * @return string The stored secret, or an empty string.
     */
    public function peekImageTokenSecret(): string
    {
        return (string) ($this->options['image_token_secret'] ?? '');
    }
}