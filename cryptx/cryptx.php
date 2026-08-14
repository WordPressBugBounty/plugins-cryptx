<?php
/**
 * Plugin Name:       CryptX
 * Plugin URI:        https://wordpress.org/plugins/cryptx/
 * Description:       CryptX encrypts email addresses in your posts, pages, comments, and text widgets to protect them from spam bots while keeping them readable for your visitors.
 * Version:           4.1.0
 * Requires at least: 6.7
 * Tested up to:      7.0
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
define('CRYPTX_VERSION', '4.1.0');
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

if (version_compare(PHP_VERSION, CRYPTX_MIN_PHP, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        printf(
        /* translators: %1$s: Required PHP version, %2$s: Current PHP version */
            esc_html__('CryptX requires PHP version %1$s or higher. You are running version %2$s. Please update PHP.', 'cryptx'),
            esc_html(CRYPTX_MIN_PHP),
            esc_html(PHP_VERSION)
        );
        echo '</p></div>';
    });
    return;
}

// WordPress version check
global $wp_version;
if (version_compare($wp_version, CRYPTX_MIN_WP, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        printf(
        /* translators: %1$s: Required WordPress version, %2$s: Current WordPress version */
            esc_html__('CryptX requires WordPress version %1$s or higher. You are running version %2$s. Please update WordPress.', 'cryptx'),
            esc_html(CRYPTX_MIN_WP),
            esc_html($GLOBALS['wp_version'])
        );
        echo '</p></div>';
    });
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
        'CryptX\\Admin\\SettingsPage',
        'CryptX\\Admin\\SettingsSchema',
        'CryptX\\Admin\\RestController',
    ];

    $missingClasses = [];
    foreach ($requiredClasses as $class) {
        if (!class_exists($class)) {
            $missingClasses[] = $class;
        }
    }

    if (!empty($missingClasses)) {
        add_action('admin_notices', function() use ($missingClasses) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('CryptX: Missing required classes: ', 'cryptx') . esc_html( implode(', ', $missingClasses) );
            echo '</p></div>';
        });
        return;
    }

    // Initialize the main plugin class
    try {
        $cryptx_instance = CryptX\CryptX::get_instance();
        $cryptx_instance->startCryptX();
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('CryptX initialization failed: ', 'cryptx') . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
});

// Remove the strict activation requirements - let the plugin handle fallbacks
register_activation_hook(__FILE__, function() {
    // Just flush rewrite rules
    flush_rewrite_rules();
});

// Plugin deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up any transients or cached data.
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
});

// Add plugin action links - updated to match the settings page slug
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=cryptx') . '">' .
        esc_html__('Settings', 'cryptx') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

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
        $shortcode = '[cryptx' . $attributesString . ']' . esc_html($content) . '[/cryptx]';

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
        _doing_it_wrong( 'encryptx', 'This method has been deprecated in favor of the better named function "cryptx_encrypt"', '4.0.5' );

        return cryptx_encrypt($content, $args);
    }
}