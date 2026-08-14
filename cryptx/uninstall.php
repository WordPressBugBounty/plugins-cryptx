<?php
/**
 * Uninstall routine for CryptX.
 *
 * Runs when the plugin is deleted through the WordPress admin (not on
 * deactivation). It removes everything CryptX has stored: the option 'cryptX'
 * and any transient whose name starts with 'cryptx_'.
 *
 * Deliberately uses the WordPress API (delete_option(), delete_transient(),
 * delete_site_transient()) instead of raw DELETE statements, so object caches
 * and the *_option hooks see the removal. Only *finding* the transients needs
 * SQL, because WordPress offers no way to enumerate transients by prefix.
 *
 * @package CryptX
 * @since   4.0.12
 */

// Without this constant the file was called directly instead of by WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * The single option this plugin stores.
 */
const CRYPTX_UNINSTALL_OPTION = 'cryptX';

/**
 * Prefix of every transient this plugin may have created.
 */
const CRYPTX_UNINSTALL_TRANSIENT_PREFIX = 'cryptx_';

if (!function_exists('cryptx_uninstall_clean_site')) {
    /**
     * Removes all CryptX data of the current site.
     *
     * On multisite this runs once per site, inside switch_to_blog(), so that
     * $wpdb->options points at that site's table.
     *
     * @return void
     */
    function cryptx_uninstall_clean_site(): void
    {
        global $wpdb;

        delete_option(CRYPTX_UNINSTALL_OPTION);

        // Transients live as two option rows: '_transient_<name>' holds the
        // value, '_transient_timeout_<name>' its expiry. delete_transient()
        // removes both, which is why the value rows are handled first.
        // esc_like() escapes the underscores, otherwise "_" would match any
        // single character and the pattern would reach into other plugins'
        // rows. The table name is an identifier and stays out of prepare().
        $valueLike = $wpdb->esc_like('_transient_' . CRYPTX_UNINSTALL_TRANSIENT_PREFIX) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $transientRows = $wpdb->get_col(
            $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $valueLike)
        );

        foreach ($transientRows as $optionName) {
            delete_transient(substr($optionName, strlen('_transient_')));
        }

        // A timeout row whose value row had already expired and been removed
        // would survive the loop above, so sweep the remaining ones directly.
        $timeoutLike = $wpdb->esc_like('_transient_timeout_' . CRYPTX_UNINSTALL_TRANSIENT_PREFIX) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $timeoutRows = $wpdb->get_col(
            $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $timeoutLike)
        );

        foreach ($timeoutRows as $optionName) {
            delete_option($optionName);
        }
    }
}

if (!function_exists('cryptx_uninstall_clean_network')) {
    /**
     * Removes network-wide CryptX site transients.
     *
     * These live in the sitemeta table, not in any site's options table, so the
     * per-site pass would never see them.
     *
     * @return void
     */
    function cryptx_uninstall_clean_network(): void
    {
        global $wpdb;

        $valueLike = $wpdb->esc_like('_site_transient_' . CRYPTX_UNINSTALL_TRANSIENT_PREFIX) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key LIKE %s",
                get_current_network_id(),
                $valueLike
            )
        );

        foreach ($rows as $metaKey) {
            delete_site_transient(substr($metaKey, strlen('_site_transient_')));
        }
    }
}

if (is_multisite()) {
    // Work in batches instead of loading every site of a large network at once:
    // get_sites() without a limit would pull all of them into memory.
    $cryptx_uninstall_batch_size = 100;
    $cryptx_uninstall_offset     = 0;

    do {
        $cryptx_uninstall_site_ids = get_sites([
            'fields'                 => 'ids',
            'number'                 => $cryptx_uninstall_batch_size,
            'offset'                 => $cryptx_uninstall_offset,
            'orderby'                => 'id',
            'update_site_meta_cache' => false,
        ]);

        foreach ($cryptx_uninstall_site_ids as $cryptx_uninstall_site_id) {
            switch_to_blog($cryptx_uninstall_site_id);
            cryptx_uninstall_clean_site();
            restore_current_blog();
        }

        $cryptx_uninstall_offset += $cryptx_uninstall_batch_size;
    } while (count($cryptx_uninstall_site_ids) === $cryptx_uninstall_batch_size);

    cryptx_uninstall_clean_network();

    unset(
        $cryptx_uninstall_batch_size,
        $cryptx_uninstall_offset,
        $cryptx_uninstall_site_ids,
        $cryptx_uninstall_site_id
    );
} else {
    cryptx_uninstall_clean_site();
}
