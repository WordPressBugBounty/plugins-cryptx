<?php

namespace CryptX\Admin;

/**
 * What a newly created site in a network starts with.
 *
 * On a network every site keeps its own settings, and since 4.1.1 that is
 * enforced rather than assumed -- network activation used to copy the first
 * site's values to every other one, exclusion list and encryption secret
 * included, which left addresses in the open on sites whose administrator had
 * excluded nothing. The fix was to give each site its own.
 *
 * That fix left a gap this closes: a network administrator who wants every new
 * site to start with, say, the picture variant and feeds protected had to open
 * each site and set it again. So there is now one list of defaults for the
 * network, and it applies at exactly one moment -- when a site is set up.
 *
 * Two properties keep this from becoming the bug it grew out of:
 *
 * It never touches a site that already exists. Not on save, not on activation,
 * not ever. A network default is a starting point, not an instruction.
 *
 * And two settings are deliberately not shareable, because they mean different
 * things on different sites. The list of excluded post IDs is the one that
 * caused the original bug: post 17 on one site has nothing to do with post 17
 * on another, so copying the list excludes the wrong posts -- and an excluded
 * post is an unprotected post. The uploaded image is an attachment ID and has
 * the same problem, in the harmless direction: it would simply not exist.
 *
 * The secrets are not here at all, because they are not settings -- they never
 * reach SettingsSchema, so there is no way for them to arrive.
 *
 * @package CryptX
 * @since   4.2.0
 */
final class NetworkDefaults
{
    /** Where the list lives. A site option, so it is one per network. */
    public const OPTION = 'cryptx_network_defaults';

    /**
     * Settings that mean something different on every site.
     *
     * @return array<int, string> The keys that are never shared.
     */
    public static function notShareable(): array
    {
        return ['excludedIDs', 'alt_uploadedimage'];
    }

    /**
     * The fields a network administrator may set.
     *
     * @return array<string, array<string, mixed>> The schema, minus the two.
     */
    public static function fields(): array
    {
        return array_diff_key(
            SettingsSchema::fields(),
            array_flip(self::notShareable())
        );
    }

    /**
     * The stored defaults, cleaned.
     *
     * @return array<string, mixed> Only keys a network default may carry.
     */
    public static function get(): array
    {
        if (!is_multisite()) {
            return [];
        }

        $stored = get_site_option(self::OPTION, []);

        if (!is_array($stored)) {
            return [];
        }

        // Through the same sanitiser as everything else, then filtered again:
        // a key could have been written before it joined the excluded list, or
        // by something other than this screen.
        return array_diff_key(
            SettingsSchema::sanitize($stored),
            array_flip(self::notShareable())
        );
    }

    /**
     * Stores a new set of defaults.
     *
     * @param array<string, mixed> $values The submitted values.
     *
     * @return array<string, mixed> What was actually stored.
     */
    public static function save(array $values): array
    {
        $clean = array_diff_key(
            SettingsSchema::sanitize($values),
            array_flip(self::notShareable())
        );

        update_site_option(self::OPTION, $clean);

        return $clean;
    }

    /**
     * The values a site should start with.
     *
     * @return array<string, mixed> Plugin defaults with the network's on top.
     */
    public static function forNewSite(): array
    {
        return array_merge(
            SettingsSchema::sanitize(SettingsSchema::defaults()),
            self::get()
        );
    }
}
