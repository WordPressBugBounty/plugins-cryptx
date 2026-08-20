<?php

namespace CryptX;

/**
 * How exposed an address is in a piece of finished markup.
 *
 * Three answers, not two. Several display options put the address into the
 * markup as HTML entities -- in an alt text, for instance, where a screen
 * reader needs it. A search of the raw markup finds nothing there, and saying
 * "no readable address" would be true of the bytes and false of the situation:
 * any bot that decodes entities, and most do, reads it straight off.
 *
 * This lives on its own because two very different places need the same
 * answer: the live preview on the settings screen, and the Site Health check.
 * A second implementation would drift, and the two would disagree about the
 * same page.
 *
 * @package CryptX
 * @since   4.2.0
 */
final class Exposure
{
    /**
     * The address both self-checks render.
     *
     * RFC 2606 reserves example.com, so this can never be a real person's
     * address. It lives next to the judgement for the same reason the
     * judgement is shared at all: the settings preview and the Site Health
     * check have to measure the same thing the same way, and two copies of the
     * sample would eventually drift apart just as two copies of Exposure::of()
     * would.
     */
    public const SAMPLE_ADDRESS = 'info@example.com';

    /** Nothing resembling an address is left. */
    public const NONE = 'none';

    /** Readable only after decoding HTML entities. */
    public const ENCODED = 'encoded';

    /** Plainly readable. */
    public const PLAIN = 'plain';

    /**
     * What the plugin itself considers an address, without delimiters.
     *
     * Kept apart from the pattern below because it is used twice: once
     * unanchored, to find addresses in a piece of markup, and once anchored, to
     * ask whether a whole string is one. Building the anchored form by trimming
     * the delimiters off the finished pattern worked, but only as long as
     * nobody added a modifier -- "/.../i" would have become "/^...  /i$/", which
     * preg_match() rejects outright. Every address would then have been judged
     * "not an address": no block would render anywhere, fail-closed and very
     * hard to trace back to a lone "i".
     */
    private const ADDRESS_EXPRESSION =
        '[_a-zA-Z0-9-+]+(\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})';

    /**
     * The pattern the plugin itself uses to find addresses, turned against its
     * own output.
     */
    private const ADDRESS_PATTERN = '/' . self::ADDRESS_EXPRESSION . '/';

    /**
     * Everything invisible that a paste can drag along.
     *
     * These are exactly the 29 code points JavaScript's \s matches, plus the
     * zero-width group (U+200B..U+200D, U+2060) that it does not. Written out
     * rather than left to PHP's \s, which covers six of them and would put the
     * editor and the server back into disagreement over a character nobody can
     * see -- see cleanAddress().
     */
    private const INVISIBLE_PATTERN =
        '/[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200D}'
        . '\x{2028}\x{2029}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]+/u';

    /**
     * Judges a piece of markup.
     *
     * @param string $markup The processed markup.
     *
     * @return string One of the three constants above.
     */
    public static function of(string $markup): string
    {
        if (preg_match(self::ADDRESS_PATTERN, $markup)) {
            return self::PLAIN;
        }

        $decoded = html_entity_decode($markup, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match(self::ADDRESS_PATTERN, $decoded)) {
            return self::ENCODED;
        }

        return self::NONE;
    }

    /**
     * Whether CryptX would recognise this as an address at all.
     *
     * Not the same question as is_email(), and the difference matters wherever
     * something promises protection. is_email() accepts what RFC 5321 allows in
     * a local part, which includes quotes and slashes; the pattern above is
     * what the plugin actually looks for. An address that passes the one and
     * not the other -- "x'/y'@example.com" -- travels through every stage
     * untouched and arrives in the page in plain text, under a heading that
     * says it is protected.
     *
     * So the gate is this, not is_email(): if it renders, it is protected, and
     * if it will not be protected, it does not render.
     *
     * @param string $value The address as it was written.
     *
     * @return bool True when the three stages will find it.
     */
    public static function isAddress(string $value): bool
    {
        // The "D" matters. Without it PCRE lets "$" match before a trailing
        // newline, so "info@example.com\n" would be judged an address -- and
        // JavaScript's "$" does not do that, so the two sides would disagree
        // about a value with a line break at the end. It went unnoticed while
        // this method trimmed for itself; taking the trim out (the caller
        // normalises, and judging one string while using another is how a
        // validator ends up guarding nothing) is what brought it to the
        // surface.
        return (bool) preg_match('/^' . self::ADDRESS_EXPRESSION . '$/D', $value);
    }

    /**
     * Removes what a paste dragged in with the address.
     *
     * An address never contains whitespace, so taking all of it out -- not just
     * at the edges -- is safe, and it is the only way the editor and the server
     * can agree. Copy an address out of Word or a PDF and it usually arrives
     * with a non-breaking space attached. PHP's trim() leaves that one alone,
     * so an address cleaned only by the browser was accepted there and refused
     * here, and the block was simply absent from the published page over a
     * character nobody can see.
     *
     * Doing it on both sides rather than warning about it: at the edges of a
     * value there is exactly one address the author meant, and it is the one
     * without the invisible character. That is what separates this from the
     * exemption list in Admin\SettingsSchema, which rejects rather than
     * repairs -- there, repairing would decide WHICH address loses its
     * protection, and a silently changed entry is not noticed.
     *
     * Inside a value the argument is weaker and worth knowing: "a@exam b.com"
     * is not an address at all, and cleaning makes one out of two visible
     * pieces. It stays harmless because joining can only concatenate, never
     * substitute, and the joined result is what the page then shows. Still --
     * this belongs on values meant as ONE address, never on a list or on
     * several lines of text.
     *
     * The editor does the same, in cleanAddress() in src/block/index.js. The
     * two lists have to stay equal; the test case "the editor and the server
     * agree on what an address is" is what notices if they stop being.
     *
     * @param string $value The address as it arrived.
     *
     * @return string The address without invisible characters.
     */
    public static function cleanAddress(string $value): string
    {
        $cleaned = preg_replace(self::INVISIBLE_PATTERN, '', $value);

        // A PCRE failure must not turn into a silently accepted address.
        return $cleaned ?? $value;
    }
}
