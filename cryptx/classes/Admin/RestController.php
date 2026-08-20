<?php

namespace CryptX\Admin;

use CryptX\CryptX;
use CryptX\Exposure;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The endpoints the settings screen talks to.
 *
 * Four routes, all behind the same gate: the capability that guards the
 * settings page itself. WordPress checks the REST nonce before any of this
 * runs, and apiFetch in the browser sends it automatically.
 *
 * @package CryptX
 * @since   4.1.0
 */
final class RestController
{
    private const NAMESPACE = 'cryptx/v1';

    /**
     * Hooks the routes in.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Declares the four routes.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'saveSettings'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'values' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/preview', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'getPreview'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'values' => [
                    'required' => true,
                    'type' => 'object',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/settings/reset', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resetSettings'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/secrets/rotate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rotateSecrets'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Only on a network, and behind a different capability: these are the
        // defaults a new site starts with, which is a network administrator's
        // decision and not a site administrator's.
        if (is_multisite()) {
            register_rest_route(self::NAMESPACE, '/network-defaults', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'getNetworkDefaults'],
                    'permission_callback' => [$this, 'checkNetworkPermission'],
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'saveNetworkDefaults'],
                    'permission_callback' => [$this, 'checkNetworkPermission'],
                    'args' => [
                        'values' => [
                            'required' => true,
                            'type' => 'object',
                        ],
                    ],
                ],
            ]);
        }

        register_rest_route(self::NAMESPACE, '/changelog', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getChangelog'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    /**
     * The most recent releases, for the help tab.
     *
     * @return WP_REST_Response
     */
    public function getChangelog(): WP_REST_Response
    {
        return new WP_REST_Response([
            'releases' => Changelog::recent(),
            // So the screen can say which of these the site is actually running.
            // "What changed" is only useful next to "since when".
            'current' => CRYPTX_VERSION,
        ]);
    }

    /**
     * The same capability that guards the settings page.
     *
     * @return true|WP_Error
     */
    public function checkPermission()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error(
            'cryptx_forbidden',
            __('You do not have sufficient permissions to manage CryptX settings.', 'cryptx'),
            ['status' => rest_authorization_required_code()]
        );
    }

    /**
     * The capability that guards the network defaults.
     *
     * Deliberately not the same one: a site administrator may configure their
     * own site, and that is what manage_options is for. Deciding what every
     * future site starts with is a different question, and on a network only a
     * super administrator holds it.
     *
     * @return true|WP_Error
     */
    public function checkNetworkPermission()
    {
        if (is_multisite() && current_user_can('manage_network_options')) {
            return true;
        }

        return new WP_Error(
            'cryptx_forbidden',
            __('You do not have sufficient permissions to manage the network defaults.', 'cryptx'),
            ['status' => rest_authorization_required_code()]
        );
    }

    /**
     * The defaults a newly created site starts with.
     *
     * @return WP_REST_Response
     */
    public function getNetworkDefaults(): WP_REST_Response
    {
        return new WP_REST_Response([
            'values' => array_merge(
                array_diff_key(
                    SettingsSchema::defaults(),
                    array_flip(NetworkDefaults::notShareable())
                ),
                NetworkDefaults::get()
            ),
            'schema' => SettingsSchema::forClient(NetworkDefaults::notShareable()),
        ]);
    }

    /**
     * Stores the defaults a newly created site starts with.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return WP_REST_Response
     */
    public function saveNetworkDefaults(WP_REST_Request $request): WP_REST_Response
    {
        NetworkDefaults::save((array) $request->get_param('values'));

        return new WP_REST_Response([
            'values' => array_merge(
                array_diff_key(
                    SettingsSchema::defaults(),
                    array_flip(NetworkDefaults::notShareable())
                ),
                NetworkDefaults::get()
            ),
            'message' => __('Network defaults saved. Sites that already exist are not changed; these values apply to sites created from now on.', 'cryptx'),
        ]);
    }

    /**
     * Current values plus the schema that describes them.
     *
     * @return WP_REST_Response
     */
    public function getSettings(): WP_REST_Response
    {
        $config = CryptX::get_instance()->getConfig();

        // Housekeeping, here rather than on the image endpoint: that one is
        // reached by strangers, and a stranger should not decide when this site
        // writes to its own database.
        $config->forgetExpiredImageTokenSecret();

        return new WP_REST_Response([
            'values' => $this->currentValues(),
            'schema' => SettingsSchema::forClient(),
            // When the secrets were last replaced, so the screen can say it.
            // A rotation nobody meant to trigger is otherwise invisible.
            //
            // Formatted here, not in the browser: toLocaleDateString() uses the
            // reader's time zone and language, wp_date() the site's. Two dates
            // in the same card, one of each, would disagree by a day for any
            // administrator sitting in a different zone from the site -- on a
            // card whose whole job is to make an unexpected date stand out.
            'secretsRotatedAt' => self::formatRotationDate($config->secretsRotatedAt()),
        ]);
    }

    /**
     * Stores the submitted values.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return WP_REST_Response
     */
    public function saveSettings(WP_REST_Request $request): WP_REST_Response
    {
        $incoming = (array) $request->get_param('values');
        $clean = SettingsSchema::sanitize($incoming);

        // Read fresh and merge, rather than writing the submitted set wholesale:
        // the stored array also holds keys this screen never shows, and they
        // have to survive a save untouched.
        $stored = get_option('cryptX', []);
        if (!is_array($stored)) {
            $stored = [];
        }

        update_option('cryptX', array_merge($stored, $clean));

        return new WP_REST_Response([
            'values' => $this->currentValues(),
            'message' => __('Settings saved.', 'cryptx'),
        ]);
    }

    /**
     * Puts every editable option back to its default.
     *
     * @return WP_REST_Response
     */
    public function resetSettings(): WP_REST_Response
    {
        $stored = get_option('cryptX', []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $defaults = SettingsSchema::sanitize(SettingsSchema::defaults());
        update_option('cryptX', array_merge($stored, $defaults));

        return new WP_REST_Response([
            'values' => $this->currentValues(),
            'message' => __('Settings restored to their defaults.', 'cryptx'),
        ]);
    }

    /**
     * Replaces both secrets with fresh ones.
     *
     * Worth knowing before pressing it, and said on the screen as well: links
     * already delivered keep working for ever, because the key travels inside
     * them. Pictures do not -- their token is opened on the server -- so the
     * replaced image secret is kept for a grace period and the answer says
     * until when.
     *
     * @return WP_REST_Response
     */
    public function rotateSecrets(): WP_REST_Response
    {
        $cryptx = CryptX::get_instance();
        $config = $cryptx->getConfig();

        $config->rotateSecrets();

        // The static option list and the Config instance were built when the
        // request started; without this they would go on serving the replaced
        // secret for the rest of it, and the preview underneath would render
        // with a key the site no longer uses.
        $cryptx->refreshForCurrentSite();

        $graceEnds = $cryptx->getConfig()->previousImageTokenSecret() === ''
            ? 0
            : (int) (get_option('cryptX')['image_token_secret_previous_until'] ?? 0);

        return new WP_REST_Response([
            'values' => $this->currentValues(),
            'graceEnds' => $graceEnds,
            'secretsRotatedAt' => self::formatRotationDate($cryptx->getConfig()->secretsRotatedAt()),
            'message' => $graceEnds > 0
                ? sprintf(
                    /* translators: %s: a date */
                    __('New secrets created. Links already published keep working. Pictures made with the old secret keep working until %s.', 'cryptx'),
                    // wp_date(), not date_i18n(): the latter expects a stamp
                    // that has already been shifted by the site's offset, and
                    // this one comes straight from time(). On a site two hours
                    // ahead the date shown was a day out.
                    wp_date(get_option('date_format'), $graceEnds)
                )
                : __('New secrets created. Links already published keep working.', 'cryptx'),
        ]);
    }

    /**
     * A rotation date in the site's own time zone and format.
     *
     * @param int $timestamp A Unix timestamp, or 0 for "never".
     *
     * @return string The formatted date, or an empty string.
     */
    private static function formatRotationDate(int $timestamp): string
    {
        return $timestamp > 0 ? wp_date(get_option('date_format'), $timestamp) : '';
    }

    /**
     * Renders the sample address with the values as they stand in the form.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return WP_REST_Response
     */
    public function getPreview(WP_REST_Request $request): WP_REST_Response
    {
        $overrides = SettingsSchema::sanitize((array) $request->get_param('values'));

        // The exemption list is switched off for the measurement, exactly as in
        // the Site Health check. The sample lives at example.com, and both the
        // field's own help text and the FAQ use "@example.com" as the example
        // to type -- so an administrator trying the feature out would have
        // watched the preview declare their working installation readable.
        // What the preview answers is whether the settings hide an address, not
        // whether every address on the site is covered.
        $overrides['exemptAddresses'] = '';

        $sample = sprintf(
            /* translators: %s: a sample email address */
            __('Write to %s if you have any questions.', 'cryptx'),
            Exposure::SAMPLE_ADDRESS
        );

        $markup = CryptX::get_instance()->renderPreviewMarkup($overrides, $sample);

        // CryptX\Exposure and not a method here: the Site Health check needs
        // the same judgement, and two implementations would eventually
        // disagree about the same page.
        $exposure = Exposure::of($markup);

        return new WP_REST_Response([
            'markup' => $markup,
            'plain' => $sample,
            'exposure' => $exposure,
            // Kept so an older cached copy of the screen still shows something
            // sensible rather than nothing.
            'leaks' => $exposure === Exposure::PLAIN,
        ]);
    }

    /**
     * The stored values, limited to the keys the screen knows about.
     *
     * @return array<string, mixed>
     */
    private function currentValues(): array
    {
        $stored = CryptX::get_instance()->loadCryptXOptionsWithDefaults();
        $values = [];

        foreach (SettingsSchema::defaults() as $key => $default) {
            $values[$key] = $stored[$key] ?? $default;
        }

        return $values;
    }
}
