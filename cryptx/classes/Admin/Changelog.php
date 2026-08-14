<?php

namespace CryptX\Admin;

/**
 * Reads the changelog out of readme.txt.
 *
 * readme.txt is the single source: it is what wordpress.org publishes, so a
 * second copy kept for the admin screen would inevitably fall behind.
 *
 * @package CryptX
 * @since   4.1.0
 */
final class Changelog
{
    /**
     * How many releases the settings screen shows. The full history lives in
     * readme.txt and on wordpress.org; showing all of it would bury the recent
     * entries, which are the ones anyone actually reads.
     */
    private const MAX_ENTRIES = 12;

    /**
     * The most recent releases.
     *
     * @return array<int, array{version: string, items: array<int, string>}>
     */
    public static function recent(): array
    {
        $path = CRYPTX_DIR_PATH . 'readme.txt';

        if (!is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        return array_slice(self::parse($contents), 0, self::MAX_ENTRIES);
    }

    /**
     * Pulls the changelog section apart into releases and their entries.
     *
     * @param string $contents The readme contents.
     *
     * @return array<int, array{version: string, items: array<int, string>}>
     */
    private static function parse(string $contents): array
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        if (!preg_match('/^==\s*Changelog\s*==\s*$(.*?)(?=^==\s|\z)/ms', $contents, $section)) {
            return [];
        }

        // preg_match_all rather than preg_split with DELIM_CAPTURE: the split
        // put whatever stood before the first "= version =" -- here the newline
        // after the section heading -- into element zero. It is not empty, so
        // PREG_SPLIT_NO_EMPTY kept it, and every version was paired with the
        // previous release's entries. Matching version and body together makes
        // that class of off-by-one impossible.
        if (!preg_match_all('/^=\s*(.+?)\s*=\s*$\n(.*?)(?=^=\s|\z)/ms', $section[1], $matches, PREG_SET_ORDER)) {
            return [];
        }

        $releases = [];

        foreach ($matches as $match) {
            $version = trim($match[1]);
            $items = [];

            foreach (explode("\n", $match[2]) as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, '* ')) {
                    $line = substr($line, 2);
                }

                $items[] = self::formatEntry($line);
            }

            if ($version !== '' && $items !== []) {
                $releases[] = [
                    'version' => $version,
                    'items' => $items,
                ];
            }
        }

        return $releases;
    }

    /**
     * Turns one changelog line into the markup the screen shows.
     *
     * readme.txt is written in the markup wordpress.org understands, which is
     * a small subset of Markdown mixed with plain HTML. Passing it through
     * untouched left literal asterisks on screen -- "* **Security** fixed..."
     * instead of a bold word.
     *
     * @param string $line One entry, without its leading bullet.
     *
     * @return string Safe markup.
     */
    private static function formatEntry(string $line): string
    {
        // Escape first, then reintroduce exactly the markup we mean. Doing it
        // the other way round would let a stray "<" from the readme through.
        $line = esc_html($line);

        $line = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $line) ?? $line;
        $line = preg_replace('/`(.+?)`/s', '<code>$1</code>', $line) ?? $line;

        // The older entries carry real links to the support forum. They were
        // escaped above, so bring back just the anchor.
        $line = preg_replace(
            '/&lt;a href=&quot;(https?:\/\/[^&]+)&quot;&gt;(.*?)&lt;\/a&gt;/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>',
            $line
        ) ?? $line;

        return wp_kses(
            $line,
            [
                'strong' => [],
                'em' => [],
                'code' => [],
                'a' => ['href' => [], 'target' => [], 'rel' => []],
            ]
        );
    }
}
