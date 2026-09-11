<?php
/**
 * Plugin Name:       CryptX
 * Plugin URI:        https://wordpress.org/plugins/cryptx/
 * Description:       CryptX encrypts email addresses in your posts, pages, comments, and text widgets to protect them from spam bots while keeping them readable for your visitors.
 * Version:           4.2.1
 * Requires at least: 6.7
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            Ralf Weber
 * Author URI:        https://weber-nrw.de/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cryptx
 *
 * CryptX is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * CryptX is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CryptX. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @package CryptX
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CRYPTX_VERSION', '4.2.1');
define('CRYPTX_PLUGIN_FILE', __FILE__);
define('CRYPTX_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CRYPTX_BASENAME', plugin_basename(__FILE__)); // Add this missing constant
define('CRYPTX_DIR_PATH', plugin_dir_path(__FILE__));
define('CRYPTX_DIR_URL', plugin_dir_url(__FILE__));
define('CRYPTX_BASEFOLDER', dirname(CRYPTX_PLUGIN_BASENAME));

// Minimum requirements. Keep these in sync with the plugin header above and
// with "Requires at least" / "Requires PHP" in readme.txt. They are defined
// once and used both for the check and for the notice, so the number shown to
// the user cannot drift away from the number actually enforced.
define('CRYPTX_MIN_PHP', '8.1');
define('CRYPTX_MIN_WP', '6.7');

/**
 * Shows an admin notice to whoever is in a position to act on it.
 *
 * Registered on both admin_notices and network_admin_notices. Without the
 * second, a network administrator working in the network backend -- the only
 * person who can deactivate a network-activated plugin -- never saw that the
 * server runs too old a PHP, or that a class file is missing. The capability
 * differs per screen: on a site it is activate_plugins, in the network backend
 * manage_network_plugins, which a mere site administrator does not hold.
 *
 * @param string $message The message, already translated and unescaped.
 *
 * @return void
 */
function cryptx_admin_notice(string $message): void
{
    $render = static function () use ($message): void {
        $capability = is_network_admin() ? 'manage_network_plugins' : 'activate_plugins';

        if (!current_user_can($capability)) {
            return;
        }

        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    };

    add_action('admin_notices', $render);
    add_action('network_admin_notices', $render);
}

if (version_compare(PHP_VERSION, CRYPTX_MIN_PHP, '<')) {
    cryptx_admin_notice(sprintf(
    /* translators: %1$s: Required PHP version, %2$s: Current PHP version */
        __('CryptX requires PHP version %1$s or higher. You are running version %2$s. Please update PHP.', 'cryptx'),
        CRYPTX_MIN_PHP,
        PHP_VERSION
    ));
    return;
}

// WordPress version check
global $wp_version;
if (version_compare($wp_version, CRYPTX_MIN_WP, '<')) {
    cryptx_admin_notice(sprintf(
    /* translators: %1$s: Required WordPress version, %2$s: Current WordPress version */
        __('CryptX requires WordPress version %1$s or higher. You are running version %2$s. Please update WordPress.', 'cryptx'),
        CRYPTX_MIN_WP,
        $GLOBALS['wp_version']
    ));
    return;
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Check if the class belongs to our namespace
    if (strpos($class, 'CryptX\\') !== 0) {
        return;
    }

    // Remove namespace prefix
    $class = substr($class, 7);

    // Convert namespace separators to directory separators
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

    // Build the full path
    $file = CRYPTX_DIR_PATH . 'classes' . DIRECTORY_SEPARATOR . $class . '.php';

    // Include the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
});

// The settings screen posts nothing: it talks to the REST routes in
// CryptX\Admin\RestController, which carry their own capability check and are
// covered by WordPress' REST nonce. The global cryptx_nonce_check() that used
// to sit here guarded $_POST['cryptX_var'], a key nothing has sent since the
// old settings form was removed in 4.1.0 -- a dead guard next to a live one is
// a trap for whoever wires up the next form.

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Check if all required classes can be loaded
    $requiredClasses = [
        'CryptX\\CryptX',
        'CryptX\\Config',
        'CryptX\\SecureEncryption',
        'CryptX\\Exposure',
        'CryptX\\Block',
        'CryptX\\ImageToken',
        'CryptX\\Admin\\NetworkDefaults',
        'CryptX\\Admin\\SettingsPage',
        'CryptX\\Admin\\SettingsSchema',
        'CryptX\\Admin\\RestController',
        'CryptX\\Admin\\SiteHealth',
    ];

    $missingClasses = [];
    foreach ($requiredClasses as $class) {
        if (!class_exists($class)) {
            $missingClasses[] = $class;
        }
    }

    if (!empty($missingClasses)) {
        cryptx_admin_notice(
            __('CryptX: Missing required classes: ', 'cryptx') . implode(', ', $missingClasses)
        );
        return;
    }

    // Initialize the main plugin class
    try {
        $cryptx_instance = CryptX\CryptX::get_instance();
        $cryptx_instance->startCryptX();
        cryptx_register_action_links();

        // Guarded here as well as inside register(), and deliberately not
        // listed in $requiredClasses above. Both of those would load the file
        // on every single front-end and admin request -- class_exists() runs
        // the autoloader, and so does a static call -- to register commands
        // that only exist under WP-CLI.
        //
        // class_exists() is still asked so that a package missing the file
        // cannot raise a fatal Error, which catch (Exception) below would not
        // have caught. It buys no notice: the commands are then simply absent.
        // That is the right silence -- nothing a visitor or an administrator
        // sees depends on them.
        if (defined('WP_CLI') && WP_CLI && class_exists('CryptX\Cli')) {
            CryptX\Cli::register();
        }
    } catch (Exception $e) {
        cryptx_admin_notice(
            __('CryptX initialization failed: ', 'cryptx') . $e->getMessage()
        );
    }
});

/**
 * Runs a callback once for every site of the network, in batches.
 *
 * get_sites() without a limit pulls the whole network into memory, and a
 * network can have thousands of sites. Same construction as uninstall.php,
 * deliberately: the two do the same job at opposite ends of the plugin's life,
 * and one of them being cleverer than the other only makes both harder to
 * trust.
 *
 * On a single site the callback simply runs once.
 *
 * @param callable $callback Receives nothing; runs with the site switched in.
 *
 * @return void
 */
function cryptx_for_each_site(callable $callback): void
{
    if (!is_multisite()) {
        $callback();

        return;
    }

    $batch_size = 100;
    $offset     = 0;

    do {
        $site_ids = get_sites([
            'fields'                 => 'ids',
            'number'                 => $batch_size,
            'offset'                 => $offset,
            'orderby'                => 'id',
            'update_site_meta_cache' => false,
        ]);

        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);

            // finally, so a failing callback does not leave the blog stack
            // switched for whatever runs next. It does not keep the loop
            // going: an exception still travels upwards and the remaining
            // sites are skipped. Catching it here would hide a broken site
            // instead, and that is a trade to make deliberately, not in
            // passing.
            try {
                $callback();
            } finally {
                restore_current_blog();
            }
        }

        $offset += $batch_size;
    } while (count($site_ids) === $batch_size);
}

/**
 * Removes the plugin's transients from the site that is currently switched in.
 *
 * @return void
 */
function cryptx_delete_transients(): void
{
    global $wpdb;

    // The LIKE patterns run through $wpdb->esc_like() and $wpdb->prepare().
    // No user input is involved here, so this is not a hole, but "_" is a
    // single-character wildcard in LIKE: unescaped, '_transient_cryptx_%' also
    // matches names like 'Xtransient1cryptxZ...' belonging to other plugins.
    // esc_like() turns those underscores into literal ones. The table name is
    // an identifier, not a value, and therefore must stay outside prepare().
    $transientLike        = $wpdb->esc_like('_transient_cryptx_') . '%';
    $transientTimeoutLike = $wpdb->esc_like('_transient_timeout_cryptx_') . '%';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transientLike));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transientTimeoutLike));
}

/**
 * Sets up the plugin's options for the site that is currently switched in.
 *
 * @return void
 */
function cryptx_install_site(): void
{
    if (!class_exists('CryptX\\CryptX')) {
        return;
    }

    CryptX\CryptX::get_instance()->installCryptX();
}

// Activation.
//
// $network_wide is true when someone ticks "Network Activate". WordPress then
// fires this hook exactly once, not once per site -- so without the loop, every
// site but the current one is left without its stored options and, more to the
// point, without the one-time migration of the pre-4.0 "cryptxoff" post meta.
// The plugin still works there, because the defaults fill in, but a site that
// carried excluded posts from an old version would silently lose them.
register_activation_hook(__FILE__, function ($network_wide = false) {
    if ($network_wide) {
        cryptx_for_each_site('cryptx_install_site');
    } else {
        cryptx_install_site();
    }

    flush_rewrite_rules();
});

// Deactivation. Same reason, other direction: the transients of every site but
// the current one used to survive a network deactivation.
register_deactivation_hook(__FILE__, function ($network_wide = false) {
    if ($network_wide) {
        cryptx_for_each_site('cryptx_delete_transients');
    } else {
        cryptx_delete_transients();
    }
});

// A site created while the plugin is network-active gets the same treatment as
// one that existed at activation time. Without this the new site works -- the
// defaults see to that -- but nothing is ever written until someone saves, and
// the behaviour differs from every other site in the network for no reason
// anybody could see.
add_action('wp_initialize_site', function ($site) {
    // The network option directly, not is_plugin_active_for_network(): that
    // lives in wp-admin/includes/plugin.php, which is not loaded when a site is
    // created over the REST API or WP-CLI -- exactly the paths that create sites
    // in bulk.
    $network_active = get_site_option('active_sitewide_plugins', []);

    if (!isset($network_active[CRYPTX_PLUGIN_BASENAME])) {
        return;
    }

    $site_id = is_object($site) ? (int) $site->blog_id : (int) $site;

    switch_to_blog($site_id);
    cryptx_install_site();
    restore_current_blog();
}, 20);

// Add plugin action links.
//
// Registered inside the successful-initialisation path on purpose, not at the
// top level of this file. It refers to a class constant, and the plugins screen
// is exactly where someone goes to switch off a plugin whose class files are
// missing -- a fatal error there would take away the only lever they have.
// A plugin that did not initialise has no settings page to link to anyway.
function cryptx_register_action_links(): void
{
    add_filter('plugin_action_links_' . CRYPTX_PLUGIN_BASENAME, function ($links) {
        $settings_link = '<a href="' .
            esc_url(admin_url('options-general.php?page=' . CryptX\Admin\SettingsPage::MENU_SLUG)) .
            '">' . esc_html__('Settings', 'cryptx') . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    });
}

/**
 * Encrypts the given content using the CryptX library and wraps it with a shortcode.
 *
 * @param string $content The content to be encrypted.
 * @param array|null $args Optional arguments to customize the encryption process.
 *
 * @return string The encrypted content wrapped in the appropriate shortcode.
 */
if (!function_exists('cryptx_encrypt')) {
    function cryptx_encrypt(string $content, ?array $args = []): string
    {
        $cryptXInstance = Cryptx\CryptX::get_instance();
        // $attributesString contains the escaped (esc_attr()) shortcode attributes from $args
        // The signature allows null, convertArrayToArgumentString() does not.
        $attributesString = $cryptXInstance->convertArrayToArgumentString($args ?? []);

        // wp_kses_post() and not esc_html(): the caller passes content, and
        // content in WordPress may carry markup. esc_html() turned a "<br>" in
        // a theme field into a visible "&lt;br&gt;" -- reported in the support
        // forum, and worked around there with html_entity_decode(), which
        // undoes the plugin's own protection in the Unicode and entity modes.
        // wp_kses_post keeps what a post may contain and drops the rest.
        $shortcode = '[cryptx' . $attributesString . ']' . wp_kses_post($content) . '[/cryptx]';

        return do_shortcode($shortcode);
    }
}

/**
 * Encrypts the given content using the CryptX library and wraps it with a shortcode.
 *
 * @deprecated 4.0.5 Use cryptx_encrypt() instead.
 * @see cryptx_encrypt()
 */
if (!function_exists('encryptx')) {
    function encryptx(string $content, ?array $args = []): string
    {
        _doing_it_wrong(
            'encryptx',
            esc_html__('This function is deprecated. Use cryptx_encrypt() instead.', 'cryptx'),
            '4.0.5'
        );

        return cryptx_encrypt($content, $args);
    }
}