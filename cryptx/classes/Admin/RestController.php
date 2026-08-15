<?php

namespace CryptX\Admin;

use CryptX\CryptX;
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
     * The address used in the preview. RFC 2606 reserves example.com, so this
     * can never be a real person's address.
     */
    private const SAMPLE_ADDRESS = 'info@example.com';

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
     * Current values plus the schema that describes them.
     *
     * @return WP_REST_Response
     */
    public function getSettings(): WP_REST_Response
    {
        return new WP_REST_Response([
            'values' => $this->currentValues(),
            'schema' => SettingsSchema::forClient(),
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
     * Renders the sample address with the values as they stand in the form.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return WP_REST_Response
     */
    public function getPreview(WP_REST_Request $request): WP_REST_Response
    {
        $overrides = SettingsSchema::sanitize((array) $request->get_param('values'));

        $sample = sprintf(
            /* translators: %s: a sample email address */
            __('Write to %s if you have any questions.', 'cryptx'),
            self::SAMPLE_ADDRESS
        );

        $markup = CryptX::get_instance()->renderPreviewMarkup($overrides, $sample);

        $exposure = $this->exposure($markup);

        return new WP_REST_Response([
            'markup' => $markup,
            'plain' => $sample,
            'exposure' => $exposure,
            // Kept so an older cached copy of the screen still shows something
            // sensible rather than nothing.
            'leaks' => $exposure === 'plain',
        ]);
    }

    /**
     * How exposed the sample address is in the produced markup.
     *
     * Three answers, not two. Several of the display options put the address
     * into the markup as HTML entities -- in an alt text, for instance, where
     * a screen reader needs it. A search of the raw markup finds nothing there
     * and the screen used to report "no readable address", which is true of the
     * bytes and false of the situation: any bot that decodes entities, and most
     * do, reads it straight off. Saying so is the difference between a preview
     * and a reassurance.
     *
     * @param string $markup The processed markup.
     *
     * @return string One of 'none', 'encoded' or 'plain'.
     */
    private function exposure(string $markup): string
    {
        $pattern = '/[_a-zA-Z0-9-+]+(\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})/';

        if (preg_match($pattern, $markup)) {
            return 'plain';
        }

        $decoded = html_entity_decode($markup, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match($pattern, $decoded)) {
            return 'encoded';
        }

        return 'none';
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
