<?php
/**
 * Uninstall routine for CryptX.
 *
 * Runs when the plugin is deleted through the WordPress admin (not on
 * deactivation). It removes everything CryptX has stored: the option 'cryptX',
 * any transient whose name starts with 'cryptx_', and the user meta that
 * records who declined to write a review.
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
     * Removes what CryptX stored for the network rather than for a site.
     *
     * Both kinds live in the sitemeta table, not in any site's options table,
     * so the per-site pass would never see them: the site transients, and the
     * defaults a newly created site started from.
     *
     * @return void
     */
    function cryptx_uninstall_clean_network(): void
    {
        global $wpdb;

        // The network defaults. Added in 4.2.0, and it was missed here at
        // first -- which would have left the file's own opening promise
        // ("removes everything CryptX has stored") untrue on exactly the kind
        // of installation where somebody notices.
        //
        // Written out rather than taken from Admin\NetworkDefaults::OPTION:
        // WordPress runs this file on its own, with the plugin unloaded, so
        // there is no class to ask. If that constant is ever renamed, this
        // line has to be renamed with it -- there is nothing that would
        // notice on its own.
        delete_site_option('cryptx_network_defaults');

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

if (!function_exists('cryptx_uninstall_clean_users')) {
    /**
     * Removes the "do not ask me for a review again" mark from every user.
     *
     * Runs exactly once, not once per site: user meta lives in one table for
     * the whole network, so the per-site pass would delete the same rows over
     * and over -- and on a network of a thousand sites, a thousand times.
     *
     * delete_metadata() with $delete_all rather than a loop over get_users():
     * the loop would pull every user of the installation into memory to find
     * the few who ever saw the notice.
     *
     * @return void
     */
    function cryptx_uninstall_clean_users(): void
    {
        // Spelled out rather than taken from Admin\ReviewNotice::USER_META,
        // for the same reason as the network option below: WordPress runs this
        // file with the plugin unloaded, so there is no class to ask.
        delete_metadata('user', 0, 'cryptx_review_dismissed', '', true);
    }
}

cryptx_uninstall_clean_users();

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
