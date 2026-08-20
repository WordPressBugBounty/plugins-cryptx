<?php

namespace CryptX\Admin;

/**
 * The one thing CryptX ever asks of the person running it.
 *
 * A plugin that works has nothing to say, which is the whole point of this one
 * -- and it means the people it serves best never think about it again. Reviews
 * are the only signal a stranger has that the plugin is still looked after, so
 * asking once is worth it. Asking twice is not.
 *
 * The shape of the ask is set by guideline 11 of the plugin directory, which is
 * worth quoting because everything below follows from it:
 *
 *   "Upgrade prompts, notices, alerts, and the like must be limited in scope
 *    and used sparingly, be that contextually or only on the plugin's setting
 *    page. Site wide notices or embedded dashboard widgets must be dismissible
 *    or self-dismiss when resolved."
 *
 * So: one line, in the ordinary notice style, and four separate limits on when
 * it may appear at all.
 *
 * 1. Only after a FEATURE update (4.1.x -> 4.2.x). A bugfix release triggers
 *    nothing, or a month with three patches would produce three asks.
 * 2. Not straight away, but DELAY later. Somebody who has just clicked
 *    "update" has no opinion about the new version yet; asking then measures
 *    nothing but their patience.
 * 3. It stops on its own after WINDOW, whether or not anybody touched it. This
 *    is the "self-dismiss when resolved" half of the guideline, and it is what
 *    keeps an ignored notice from becoming a permanent fixture.
 * 4. Dismissing is permanent and PER USER. Permanent, because "No thanks" has
 *    to mean it -- a plugin that asks again next spring has lied. Per user,
 *    because on a site with several administrators one of them clicking the
 *    cross would otherwise decide for all the others, and none of them would
 *    ever learn why they were never asked.
 *
 * And one limit on WHERE, which is the other half of the same guideline: the
 * three screens somebody actually notices a plugin update on. A notice on the
 * media library or the comment queue is about something the person is not
 * doing, and the sum of that -- every backend screen, for a month, after every
 * feature release -- is what the word "sparingly" is aimed at, even when each
 * separate showing is defensible.
 *
 * The two states are kept in different places on purpose, and it shows on a
 * network: the due date is per site, in that site's option, while a refusal is
 * user meta and user meta is network-wide. So somebody who declines on one
 * site of a network has declined everywhere. That is the reading of "No
 * thanks" this plugin takes -- the answer is about the plugin, not about the
 * site it was given on.
 *
 * There is deliberately no incentive of any kind attached. Guideline 9 forbids
 * "compensating, misleading, pressuring, extorting, or blackmailing others for
 * reviews", and the cheap version of that -- unlocking something in return for
 * a rating -- is exactly what it is aimed at.
 *
 * @package CryptX
 * @since   4.2.0
 */
final class ReviewNotice
{
    /**
     * Where the due date is kept, inside the plugin's own option.
     *
     * A separate option would have been one more row to create, migrate and
     * remember in uninstall.php. This one rides along with everything else and
     * is removed with it.
     */
    public const OPTION_KEY = 'review_prompt_due';

    /**
     * The user meta that records "do not ask me".
     *
     * Holds the version at which the person said no, rather than a bare 1. It
     * costs the same and answers the question somebody will eventually have
     * while looking at a support case.
     */
    public const USER_META = 'cryptx_review_dismissed';

    /**
     * The review page. Without ?rate=5: pre-selecting the rating for somebody
     * before they have written a word is the mildest form of the nudging that
     * guideline 9 is about, and this plugin can do without it.
     */
    public const REVIEW_URL = 'https://wordpress.org/support/plugin/cryptx/reviews/';

    /** How long after a feature update the question becomes fair. */
    private const DELAY = 14 * DAY_IN_SECONDS;

    /** How long it then stays before giving up by itself. */
    private const WINDOW = 30 * DAY_IN_SECONDS;

    /** The query argument and the nonce action share a name on purpose. */
    private const ACTION = 'cryptx-review-dismiss';

    /**
     * The only screens the notice may appear on.
     *
     * Where somebody would think about a plugin at all: the dashboard they
     * land on, the list they update from, and this plugin's own settings.
     */
    private const SCREENS = ['dashboard', 'plugins', 'settings_page_cryptx'];

    private const SCRIPT_HANDLE = 'cryptx-review-notice';

    /**
     * Hooks the notice in.
     *
     * @return void
     */
    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_init', [$this, 'handleDismissal']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'render']);
    }

    /**
     * The moment the question becomes fair, or null if this is not the kind of
     * update that earns one.
     *
     * Static and free of side effects so the rule can be tested directly. The
     * comparison is on the major.minor series rather than on version_compare()
     * alone: 4.2.0 -> 4.2.1 is an update, and not one anybody wants to be
     * congratulated for.
     *
     * @param string $from The version that was installed.
     * @param string $to   The version now installed.
     * @param int    $now  The current timestamp.
     *
     * @return int|null The timestamp to ask at, or null for "do not ask".
     */
    public static function dueAfterUpdate(string $from, string $to, int $now): ?int
    {
        if (version_compare($to, $from, '<=')) {
            return null;
        }

        if (self::series($from) === self::series($to)) {
            return null;
        }

        return $now + self::DELAY;
    }

    /**
     * The major.minor part of a version string.
     *
     * @param string $version A version.
     *
     * @return string Its series.
     */
    private static function series(string $version): string
    {
        $parts = explode('.', $version);

        return ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0');
    }

    /**
     * Whether the notice may be shown to whoever is looking.
     *
     * @return bool True when all four limits are satisfied.
     */
    public function isDue(): bool
    {
        // The person who can act on it. An editor cannot update the plugin and
        // has no business being asked about it.
        if (!current_user_can('manage_options')) {
            return false;
        }

        // In the network backend there is no per-site option to read, and the
        // network administrator is not necessarily the person who chose this
        // plugin. admin_notices does not fire there anyway; this says so out
        // loud rather than relying on that staying true.
        if (is_network_admin()) {
            return false;
        }

        $userId = get_current_user_id();

        if ($userId === 0 || get_user_meta($userId, self::USER_META, true) !== '') {
            return false;
        }

        $options = get_option('cryptX');
        $due = is_array($options) ? (int) ($options[self::OPTION_KEY] ?? 0) : 0;

        if ($due === 0) {
            return false;
        }

        $now = time();

        return $now >= $due && $now < $due + self::WINDOW;
    }

    /**
     * Whether this is one of the three screens the notice belongs on.
     *
     * Kept apart from isDue() rather than folded into it: isDue() is the rule
     * about time and person and can be measured anywhere, while this one needs
     * a screen to exist. Under WP-CLI there is none, and a single method would
     * have answered "no" to everything for a reason that has nothing to do
     * with the four limits.
     *
     * @return bool True on the dashboard, the plugin list or the CryptX page.
     */
    private function onRelevantScreen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        if (!$screen instanceof \WP_Screen) {
            return false;
        }

        return in_array($screen->id, self::SCREENS, true);
    }

    /**
     * The full judgement: the right moment, the right person, the right screen.
     *
     * @return bool True when the notice may be printed.
     */
    public function shouldShow(): bool
    {
        return $this->onRelevantScreen() && $this->isDue();
    }

    /**
     * Loads the small script that makes dismissing survive the page.
     *
     * Not built and not minified. Everything in build/ comes from
     * @wordpress/scripts and everything in js/cryptx.min.js from a pinned
     * terser call; adding a third path through the toolchain for twenty lines
     * would cost more to maintain than the bytes it saves.
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        if (!$this->shouldShow()) {
            return;
        }

        // The array form of the last argument rather than a bare true: it is
        // what the directory's own checker asks for, and "defer" is right here
        // -- nothing on the page waits for twenty lines that only matter once
        // somebody clicks.
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            CRYPTX_DIR_URL . 'js/admin-notice.js',
            [],
            CRYPTX_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );
    }

    /**
     * Prints the notice.
     *
     * @return void
     */
    public function render(): void
    {
        if (!$this->shouldShow()) {
            return;
        }

        $dismissUrl = $this->dismissUrl();

        // "noreferrer" as well as "noopener": wp-admin sets no referrer policy
        // of its own -- wp_strict_cross_origin_referrer() is registered on the
        // login and activation screens and nowhere else -- so on a browser
        // with an older default the full address of the admin screen would
        // travel to wordpress.org. No nonce rides along with it, but there is
        // nothing to gain by sending it either.
        $review = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" data-cryptx-review-action="review">%s</a>',
            esc_url(self::REVIEW_URL),
            esc_html__('Write a review', 'cryptx')
        );

        $decline = sprintf(
            '<a href="%s" data-cryptx-review-action="dismiss">%s</a>',
            esc_url($dismissUrl),
            esc_html__('No thanks', 'cryptx')
        );

        // The sprintf() is guarded, and this is the first translated format
        // string in the plugin that carries arguments at all -- so the failure
        // mode is worth naming. A translation is external input that arrives
        // from translate.wordpress.org without anybody here seeing it. One
        // carrying a "%4$s" makes sprintf() throw an ArgumentCountError under
        // PHP 8, and it would throw on EVERY admin screen for as long as the
        // window is open: a white screen, recovery mode, and the plugin
        // switched off, all for a sentence nobody needed. A notice that fails
        // to appear costs nothing by comparison.
        try {
            $message = sprintf(
                /* translators: 1: Plugin name and version, in bold. 2: "Write a review" link. 3: "No thanks" link. */
                __(
                    '%1$s &ndash; thanks for keeping it up to date. If it is doing its job quietly on your site, a short review helps other people find it. %2$s &middot; %3$s',
                    'cryptx'
                ),
                '<strong>' . esc_html(sprintf('CryptX %s', CRYPTX_VERSION)) . '</strong>',
                $review,
                $decline
            );
        } catch (\Throwable) {
            // Caught without binding: there is nothing to do with it here, and
            // an unused variable reads as a forgotten log line.
            return;
        }

        // wp_admin_notice() runs the finished markup through wp_kses_post(),
        // which keeps <strong>, <a href target rel> and data-* attributes and
        // drops everything else. Measured, not assumed -- the data-* part is
        // the one that would have failed quietly.
        wp_admin_notice($message, [
            'type' => 'info',
            'dismissible' => true,
            'id' => 'cryptx-review-notice',
            'attributes' => [
                'data-cryptx-review' => $dismissUrl,
            ],
        ]);
    }

    /**
     * Records the refusal when the link was followed without JavaScript.
     *
     * The cross that WordPress draws on a dismissible notice hides it and
     * nothing more -- it is undone by the next page load. The script turns
     * both the cross and the two links into a request to this handler; this
     * method is what happens when there is no script, and it is why "No
     * thanks" is a real link with a real target rather than a href="#".
     *
     * @return void
     */
    public function handleDismissal(): void
    {
        if (!isset($_GET[self::ACTION])) {
            return;
        }

        // A fetched link is not an answer. A browser that speculatively loads
        // what it thinks will be clicked next sends the session's cookies with
        // it, so the nonce and the capability check below would both be
        // satisfied -- and the person would have declined without knowing that
        // a question had been asked. They would simply never see one.
        //
        // Checked before the nonce rather than after: a prefetch that fails
        // check_admin_referer() gets wp_die()'d, and a browser holding that
        // page ready shows it if the link is then really clicked.
        if (self::isSpeculativeRequest()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Dies on a bad or missing nonce.
        check_admin_referer(self::ACTION);

        update_user_meta(get_current_user_id(), self::USER_META, CRYPTX_VERSION);

        // Back where they were. wp_get_referer() is already checked against
        // this site's host, and wp_safe_redirect() refuses a foreign one a
        // second time -- so the value never has to be trusted.
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * Whether the browser is loading this ahead of time rather than being told to.
     *
     * Four headers for one idea, because no two engines agreed on a name
     * before "Sec-Purpose" was standardised: Chromium sends "Sec-Purpose:
     * prefetch" (and "prerender"), older Chromium "Purpose: prefetch", Safari
     * "X-Purpose: preview", Firefox "X-Moz: prefetch".
     *
     * This is not a security control -- it is not trustworthy enough to be one
     * and does not need to be. Getting it wrong in either direction costs at
     * most one review prompt, and the guard fails towards asking again.
     *
     * @return bool True when nobody clicked anything.
     */
    private static function isSpeculativeRequest(): bool
    {
        $headers = ['HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ'];

        foreach ($headers as $header) {
            if (!isset($_SERVER[$header])) {
                continue;
            }

            // Unslashed and sanitised even though the value is only ever
            // compared against three words and never stored or printed. It is
            // what the rest of the plugin does with $_SERVER, and a header
            // read that looks different from the others invites the question
            // of which one is wrong.
            $value = strtolower(sanitize_text_field(wp_unslash($_SERVER[$header])));

            if ($value === '') {
                continue;
            }

            foreach (['prefetch', 'prerender', 'preview'] as $word) {
                if (strpos($value, $word) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The URL that records a refusal.
     *
     * Points at the dashboard rather than at the current screen: building it
     * from REQUEST_URI would reflect whatever the browser sent back into an
     * href, and there is nothing to gain by it -- the handler redirects to the
     * referring page anyway.
     *
     * @return string A nonce-carrying admin URL.
     */
    private function dismissUrl(): string
    {
        return wp_nonce_url(
            add_query_arg(self::ACTION, '1', admin_url()),
            self::ACTION
        );
    }

    /**
     * Sets the due date after an update, if the update earns one.
     *
     * Called from the migration, which is the only place that knows both
     * versions. Returns the option array rather than writing it: the migration
     * writes once, at the end, and a second write here would be a second
     * chance to get it wrong.
     *
     * @param array<string, mixed> $options The option array being migrated.
     * @param string               $from    The version that was installed.
     *
     * @return array<string, mixed> The option array, possibly with a due date.
     */
    public static function scheduleAfterUpdate(array $options, string $from): array
    {
        $due = self::dueAfterUpdate($from, CRYPTX_VERSION, time());

        if ($due !== null) {
            $options[self::OPTION_KEY] = $due;
        }

        return $options;
    }
}
