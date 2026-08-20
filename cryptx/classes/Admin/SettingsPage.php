<?php

namespace CryptX\Admin;

/**
 * Registers the settings screen and loads the application that renders it.
 *
 * The PHP side is deliberately thin: a menu entry, one empty container and the
 * built assets. Everything the screen knows about the options comes from
 * SettingsSchema over the REST routes, so there is no second description of
 * the settings hiding in a template.
 *
 * @package CryptX
 * @since   4.1.0
 */
final class SettingsPage
{
    /**
     * The slug the settings page is registered under.
     *
     * Public because the link in the plugin list has to point at the same
     * place. That link used to be built from CRYPTX_BASEFOLDER, the directory
     * name -- identical on wordpress.org, and wrong the moment someone renames
     * the folder.
     */
    public const MENU_SLUG = 'cryptx';
    private const SCRIPT_HANDLE = 'cryptx-settings';

    private RestController $rest;

    public function __construct()
    {
        $this->rest = new RestController();
    }

    /**
     * Hooks the screen in.
     *
     * @return void
     */
    public function register(): void
    {
        $this->rest->register();

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerMenu']);
            add_action('network_admin_menu', [$this, 'registerNetworkMenu']);
        }
    }

    /**
     * Adds the entry under Settings.
     *
     * @return void
     */
    public function registerMenu(): void
    {
        $hook = add_submenu_page(
            'options-general.php',
            _x('CryptX', 'CryptX settings page', 'cryptx'),
            _x('CryptX', 'CryptX settings menu', 'cryptx'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );

        if ($hook) {
            add_action('load-' . $hook, [$this, 'onLoad']);
        }
    }

    /**
     * Adds the network entry, for the defaults a new site starts with.
     *
     * Under the network's own Settings and behind manage_network_options: a
     * site administrator configures their own site, a super administrator
     * decides what the next site begins with. Two different questions, two
     * different capabilities.
     *
     * @return void
     */
    public function registerNetworkMenu(): void
    {
        $hook = add_submenu_page(
            'settings.php',
            _x('CryptX', 'CryptX network defaults page', 'cryptx'),
            _x('CryptX', 'CryptX network defaults menu', 'cryptx'),
            'manage_network_options',
            self::MENU_SLUG,
            [$this, 'renderNetwork']
        );

        if ($hook) {
            add_action('load-' . $hook, [$this, 'onLoad']);
        }
    }

    /**
     * Runs only when our own screen is being loaded.
     *
     * @return void
     */
    public function onLoad(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Loads the built application.
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        $assetFile = CRYPTX_DIR_PATH . 'build/index.asset.php';

        if (!is_readable($assetFile)) {
            add_action('admin_notices', [$this, 'renderMissingBuildNotice']);

            return;
        }

        $asset = require $assetFile;

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            CRYPTX_DIR_URL . 'build/index.js',
            $asset['dependencies'] ?? [],
            $asset['version'] ?? CRYPTX_VERSION,
            true
        );

        wp_set_script_translations(self::SCRIPT_HANDLE, 'cryptx');

        $style = CRYPTX_DIR_PATH . 'build/index.css';
        if (is_readable($style)) {
            wp_enqueue_style(
                self::SCRIPT_HANDLE,
                CRYPTX_DIR_URL . 'build/index.css',
                ['wp-components'],
                $asset['version'] ?? CRYPTX_VERSION
            );
        }

        // The media library picker for the "image from the media library"
        // option needs the classic media modal to be present.
        //
        // Not in the network backend: the one field that needs it is left out
        // of the network defaults on purpose -- an attachment id means nothing
        // on another site -- and there is no media library there to pick from
        // either.
        if (!is_network_admin()) {
            wp_enqueue_media();
        }
    }

    /**
     * Shown when the plugin was installed without its built assets.
     *
     * @return void
     */
    public function renderMissingBuildNotice(): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('CryptX: the settings screen could not be loaded because its built assets are missing. If you installed CryptX from a source checkout, run "npm install && npm run build" in the plugin directory.', 'cryptx');
        echo '</p></div>';
    }

    /**
     * The container the application mounts into.
     *
     * @return void
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'cryptx'));
        }

        $this->renderRoot('site', __('Loading the settings…', 'cryptx'));
    }

    /**
     * The same application, told that it is editing the network defaults.
     *
     * @return void
     */
    public function renderNetwork(): void
    {
        if (!current_user_can('manage_network_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'cryptx'));
        }

        $this->renderRoot('network', __('Loading the network defaults…', 'cryptx'));
    }

    /**
     * The container the application mounts into.
     *
     * @param string $scope Either 'site' or 'network'.
     * @param string $loading What to show until the application takes over.
     *
     * @return void
     */
    private function renderRoot(string $scope, string $loading): void
    {
        printf(
            '<div class="wrap cryptx-settings-root" id="cryptx-settings-root" data-cryptx-scope="%s">',
            esc_attr($scope)
        );

        // Shown until the application takes over, and the only thing left if
        // JavaScript is unavailable.
        echo '<h1>' . esc_html__('CryptX', 'cryptx') . '</h1>';
        echo '<p>' . esc_html($loading) . '</p>';
        echo '</div>';
    }
}
