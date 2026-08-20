<?php

namespace CryptX;

use CryptX\Admin\SettingsSchema;
use WP_CLI;
use WP_Query;

/**
 * CryptX on the command line.
 *
 * Two things the settings screen cannot do well.
 *
 * The first is a network. Every site keeps its own settings, deliberately --
 * see Admin\NetworkDefaults for why -- which means changing one setting across
 * forty sites is forty visits to forty screens. With "--url" every command
 * below applies to one site, and a shell loop does the rest.
 *
 * The second is answering "is anything still readable?" for a whole site rather
 * than for one sample. The settings screen renders one address and judges the
 * result; "wp cryptx scan" does the same to every published post and says which
 * ones come out with an address still in them. That is a question a site owner
 * has after changing a setting, and until now the only way to answer it was to
 * look at pages one at a time.
 *
 * @package CryptX
 * @since   4.2.0
 */
final class Cli
{
    /**
     * How many posts are pulled from the database at once.
     *
     * The whole point of the scan is running on sites with a lot of content,
     * and a site with 50,000 posts must not need 50,000 posts' worth of memory
     * to be told that three of them leak.
     */
    private const BATCH = 100;

    /**
     * Registers the command, if WP-CLI is what is running.
     *
     * @return void
     */
    public static function register(): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        WP_CLI::add_command('cryptx settings', [self::class, 'settings']);
        WP_CLI::add_command('cryptx scan', [self::class, 'scan']);
    }

    /**
     * Reads and writes CryptX settings.
     *
     * ## OPTIONS
     *
     * [<key>]
     * : The setting to read or write. Left out, every setting is listed.
     *
     * [<value>]
     * : The new value. Left out, the setting is only read.
     *
     * [--force]
     * : Store the value even if it had to be changed to be usable.
     *
     * [--format=<format>]
     * : Output format for the list.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     # Everything, with the current values
     *     $ wp cryptx settings
     *
     *     # One setting
     *     $ wp cryptx settings opt_linktext
     *
     *     # Change one, on one site of a network
     *     $ wp cryptx settings disable_rss 0 --url=example.org
     *
     * @param array<int, string> $args Positional arguments.
     * @param array<string, string> $assoc Flags.
     *
     * @return void
     */
    public static function settings(array $args, array $assoc): void
    {
        $fields = SettingsSchema::fields();
        $stored = CryptX::get_instance()->loadCryptXOptionsWithDefaults();

        // No key: list everything.
        if ($args === []) {
            $rows = [];

            foreach ($fields as $key => $definition) {
                $rows[] = [
                    'key' => $key,
                    'value' => self::asText($stored[$key] ?? $definition['default'] ?? ''),
                    'default' => self::asText($definition['default'] ?? ''),
                    'label' => $definition['label'] ?? '',
                ];
            }

            WP_CLI\Utils\format_items(
                $assoc['format'] ?? 'table',
                $rows,
                ['key', 'value', 'default', 'label']
            );

            return;
        }

        $key = $args[0];

        if (!isset($fields[$key])) {
            // The list of what IS settable, rather than only what is not: the
            // names are not guessable, and a bare "unknown setting" leaves the
            // reader to open a browser.
            WP_CLI::error(sprintf(
                /* translators: 1: the unknown setting, 2: the known ones */
                __('Unknown setting "%1$s". Known settings: %2$s', 'cryptx'),
                $key,
                implode(', ', array_keys($fields))
            ));
        }

        // Reading.
        if (!isset($args[1])) {
            WP_CLI::line(self::asText($stored[$key] ?? $fields[$key]['default'] ?? ''));

            return;
        }

        // Writing. Through the same sanitiser the settings screen uses, so a
        // value the screen would refuse cannot arrive by another door.
        $clean = SettingsSchema::sanitize([$key => $args[1]]);

        // The sanitiser repairs, it does not refuse -- which is right for a
        // form, where the browser has already limited what can be sent, and
        // wrong here. "wp cryptx settings excludedIDs eins,zwei" quietly wiped
        // every exclusion and reported success; the next page view then served
        // addresses that had been excluded on purpose. So the CLI compares what
        // came back with what went in and stops when they differ.
        //
        // --force accepts the repaired value, because some differences are
        // wanted: an address list is lower-cased, a colour gains its "#".
        $result = self::asText($clean[$key] ?? '');

        if ($result !== (string) $args[1] && !isset($assoc['force'])) {
            WP_CLI::error(sprintf(
                /* translators: 1: the given value, 2: the setting, 3: what it would become */
                __('"%1$s" is not a usable value for %2$s -- it would be stored as "%3$s". Pass --force to store that instead.', 'cryptx'),
                $args[1],
                $key,
                $result
            ));
        }

        $options = get_option('cryptX', []);

        if (!is_array($options)) {
            $options = [];
        }

        update_option('cryptX', array_merge($options, $clean));

        WP_CLI::success(sprintf(
            /* translators: 1: the setting, 2: the new value */
            __('%1$s is now %2$s.', 'cryptx'),
            $key,
            // Same "?? ''" as the comparison above. Unreachable today, because
            // no field is also an internal key -- but writing it one way here
            // and the other way twenty lines up is the asymmetry that turns
            // into a warning the day those two lists ever overlap.
            self::asText($clean[$key] ?? '')
        ));
    }

    /**
     * Runs published content through CryptX and reports what is still readable.
     *
     * The same judgement the settings screen and the Site Health check use, on
     * real content instead of a sample: each post goes through the filters that
     * render it, and CryptX\Exposure decides whether an address survived.
     *
     * "encoded" is worth reading twice. It means the address is in the page as
     * HTML entities -- invisible to a naive scanner, plain to anything that
     * decodes them, which is most things. It is not "safe".
     *
     * ## OPTIONS
     *
     * [--post_type=<types>]
     * : Comma separated. Defaults to every public type.
     *
     * [--all]
     * : Report every post, not only the ones with something left in them.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     # What is still readable on this site
     *     $ wp cryptx scan
     *
     *     # Across a network
     *     $ wp site list --field=url | xargs -I{} wp cryptx scan --url={}
     *
     * @param array<int, string> $args Positional arguments.
     * @param array<string, string> $assoc Flags.
     *
     * @return void
     */
    public static function scan(array $args, array $assoc): void
    {
        $types = isset($assoc['post_type'])
            ? array_filter(array_map('trim', explode(',', (string) $assoc['post_type'])))
            : get_post_types(['public' => true]);

        // A type nobody registered would otherwise find nothing and be reported
        // as "nothing readable left" -- a clean bill of health for a run that
        // checked nothing. "--post_type=pages" instead of "page" is one
        // keystroke away.
        foreach ($types as $type) {
            if (!post_type_exists($type)) {
                WP_CLI::error(sprintf(
                    /* translators: 1: the unknown post type, 2: the known ones */
                    __('Unknown post type "%1$s". Known types: %2$s', 'cryptx'),
                    $type,
                    implode(', ', get_post_types(['public' => true]))
                ));
            }
        }

        $showAll = isset($assoc['all']);
        $format = $assoc['format'] ?? 'table';

        $rows = [];
        $checked = 0;
        $page = 1;

        // Saved and put back by hand. wp_reset_postdata() restores from the
        // main query, and under WP-CLI there is no main query -- so it does
        // nothing at all, and $GLOBALS['post'] is left pointing at whichever
        // post was checked last. Harmless when the command is the whole
        // process, not harmless when something else runs afterwards in the
        // same one, which is exactly what the test suite does.
        $previousPost = $GLOBALS['post'] ?? null;

        try {
            do {
                $query = new WP_Query([
                    'post_type' => array_values($types),
                    'post_status' => 'publish',
                    'posts_per_page' => self::BATCH,
                    'paged' => $page,
                    'ignore_sticky_posts' => true,
                    'no_found_rows' => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ]);

                if (!$query->have_posts()) {
                    break;
                }

                foreach ($query->posts as $post) {
                    $checked++;

                    // The real filter, with the post in place -- the exclusion list
                    // and the meta box both depend on which post is being rendered,
                    // so judging the content without setting it up would report
                    // leaks on posts the site owner had deliberately excluded, and
                    // miss the ones that matter.
                    $GLOBALS['post'] = $post;
                    setup_postdata($post);

                    $rendered = apply_filters('the_content', $post->post_content);

                    wp_reset_postdata();

                    $verdict = Exposure::of($rendered);

                    // The title as well, and it is not a nicety. CryptX does
                    // not filter titles: a title reaches the document head
                    // through wp_get_document_title(), which passes through
                    // neither the_content nor render_block. An address in a
                    // title is therefore readable -- and a scan that judged
                    // only the body reported "nothing readable left" for a page
                    // that had one. A clean bill of health on a page with a
                    // plain address is worse than no scan at all.
                    $inTitle = Exposure::of((string) $post->post_title);
                    $where = [];

                    // Not translated, and that is the point: this is a field
                    // value in machine-readable output, next to "exposure",
                    // which carries the untranslated Exposure constants. Under
                    // a German locale a --format=csv run would otherwise say
                    // "Inhalt, Titel", and the column this readme documents
                    // would stop being scriptable halfway through a network.
                    if ($verdict !== Exposure::NONE) {
                        $where[] = 'content';
                    }

                    if ($inTitle !== Exposure::NONE) {
                        $where[] = 'title';

                        // The worse of the two wins. An entity-encoded address
                        // in the body next to a plain one in the title is a
                        // plain leak, and reporting it as "encoded" would file
                        // it under the milder heading.
                        if ($verdict === Exposure::NONE || $inTitle === Exposure::PLAIN) {
                            $verdict = $inTitle;
                        }
                    }

                    if (!$showAll && $verdict === Exposure::NONE) {
                        continue;
                    }

                    $rows[] = [
                        'ID' => $post->ID,
                        'type' => $post->post_type,
                        'exposure' => $verdict,
                        'where' => implode(', ', $where),
                        'title' => $post->post_title,
                        'url' => get_permalink($post),
                    ];
                }

                $page++;
            } while (count($query->posts) === self::BATCH);
        } finally {
            $GLOBALS['post'] = $previousPost;
        }

        if ($format === 'count') {
            WP_CLI::line((string) count($rows));

            return;
        }

        if ($rows === []) {
            // What was measured, not "everything is fine". The scan reads the
            // body and the title of published posts; widgets, comments, feeds
            // and whatever a theme prints for itself are not in it. A summary
            // that did not say so invited exactly the wrong conclusion.
            WP_CLI::success(sprintf(
                /* translators: %d: number of posts checked */
                _n(
                    'Checked the body and title of %d published post; nothing readable left in it.',
                    'Checked the body and title of %d published posts; nothing readable left in them.',
                    $checked,
                    'cryptx'
                ),
                $checked
            ));

            return;
        }

        WP_CLI\Utils\format_items(
            $format,
            $rows,
            ['ID', 'type', 'exposure', 'where', 'title', 'url']
        );

        $plain = count(array_filter(
            $rows,
            static fn(array $row): bool => $row['exposure'] === Exposure::PLAIN
        ));

        if ($plain > 0 && !$showAll) {
            // Deliberately phrased so that no noun has to agree with a
            // number. "%1$d of %2$d posts still carry" cannot be pluralised
            // correctly: the verb follows the first number and the noun the
            // second, and at 1 of 1 they disagree. A sentence with two counts
            // in it only works if neither governs a plural.
            WP_CLI::warning(sprintf(
                /* translators: 1: number with a readable address, 2: number checked */
                __('Still carrying a plainly readable address: %1$d of %2$d checked.', 'cryptx'),
                $plain,
                $checked
            ));
        }
    }

    /**
     * A setting as one line of text.
     *
     * @param mixed $value The stored value.
     *
     * @return string Something a shell can read and pipe.
     */
    private static function asText($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return implode(',', array_map('strval', $value));
        }

        return (string) $value;
    }
}
