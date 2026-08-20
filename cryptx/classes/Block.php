<?php

namespace CryptX;

/**
 * The block editor's way of asking for a protected address.
 *
 * Until now the only way to protect one address deliberately was to type
 * "[cryptx]info@example.com[/cryptx]" into a paragraph. That works, and it goes
 * on working, but nobody discovers it: a shortcode is invisible in the inserter,
 * and the site owner who most needs it is the one least likely to know the
 * syntax.
 *
 * Two properties decide how this is built, and both point the same way:
 *
 * It renders on the server, every time. A block that saved its own markup would
 * freeze one ciphertext into the post content -- and the ciphertext is bound to
 * the site's secret. Change the secret, or restore a site from a backup taken
 * before it was minted, and every saved block becomes a link to nowhere with no
 * indication of what went wrong. Nothing is stored but the address and what the
 * author typed alongside it.
 *
 * And it renders through cryptXShortcode(), rather than reimplementing the three
 * stages. That is not only about avoiding duplication: the shortcode path is
 * where "this is an explicit instruction" lives. A block is the same kind of
 * statement as a shortcode -- somebody pointing at one address and asking for it
 * to be protected -- so it has to outrank the exemption list in exactly the same
 * way, and it does, because it is the same code.
 *
 * @package CryptX
 * @since   4.2.0
 */
final class Block
{
    /**
     * Where the built block lives, relative to the plugin directory.
     *
     * The block.json next to it is what register_block_type() reads; the
     * attributes are declared there once and used by both sides.
     */
    private const BUILD_PATH = 'build/block';

    /**
     * The attributes that become mailto headers rather than settings.
     *
     * The same four the shortcode takes, under the same names, so the two
     * cannot drift apart.
     */
    private const MAILTO_ATTRIBUTES = ['subject', 'body', 'cc', 'bcc'];

    /**
     * Hooks the block in.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerBlockType']);
    }

    /**
     * Declares the block against its own metadata.
     *
     * @return void
     */
    public function registerBlockType(): void
    {
        $path = CRYPTX_DIR_PATH . self::BUILD_PATH;

        // A missing build directory is a packaging fault, not a site fault.
        // register_block_type() would emit a _doing_it_wrong() notice on every
        // request; saying nothing is the better failure here, because the
        // editor simply does not offer a block it never heard of.
        if (!file_exists($path . '/block.json')) {
            return;
        }

        register_block_type($path, ['render_callback' => [$this, 'render']]);
    }

    /**
     * One attribute, as a trimmed string, or nothing.
     *
     * Casting straight to string looks equivalent and is not: an array reaches
     * (string) as "Array" and a PHP warning, which lands in the page or the
     * log. Through the block parser that cannot happen -- WordPress checks the
     * declared type and substitutes the default -- but render() is public, and
     * a method that only behaves when called the expected way is a method that
     * will one day be called another way.
     *
     * @param array<string, mixed> $attributes The attributes.
     * @param string $name Which one.
     *
     * @return string The value, or an empty string if it is not usable.
     */
    private static function text(array $attributes, string $name): string
    {
        $value = $attributes[$name] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Renders one block through the same path the shortcode uses.
     *
     * @param array<string, mixed> $attributes The block's stored attributes.
     *
     * @return string The markup for the front end.
     */
    public function render(array $attributes): string
    {
        // Cleaned before it is judged, and the cleaned form is what gets
        // rendered -- so what the editor accepted and what the page shows are
        // the same string. Judging one and using the other is how a validator
        // ends up guarding nothing.
        //
        // Deliberately not through text(), which trims: PHP's trim() also
        // removes a NUL byte, and NUL is the one character in its set that
        // neither cleaner touches. Trimming here would therefore have made the
        // server accept a value the editor was warning about -- the same
        // disagreement, one character wide.
        $raw = $attributes['address'] ?? '';
        $address = is_string($raw) ? Exposure::cleanAddress($raw) : '';

        // Validated, and nothing rendered if it fails. The attribute reaches
        // this method exactly as it was stored in the post, and the stages
        // below hand back anything they do not recognise as an address --
        // unchanged. So "<img src=x onerror=...>@example.com" would have been
        // written into the page verbatim: stored cross-site scripting, needing
        // no more than the right to edit a post.
        //
        // Exposure::isAddress() rather than is_email(), and neither is
        // sanitize_email(). sanitize_email() strips what it dislikes and
        // returns the rest, so it would render a repaired address the author
        // never typed. is_email() is the other way round -- too generous: it
        // accepts what RFC 5321 allows in a local part, and "x'/y'@example.com"
        // passes it while matching none of the plugin's own patterns, so it
        // travelled through every stage untouched and landed in the page in
        // plain text, under a block whose whole promise is the opposite.
        //
        // The gate is therefore the plugin's own idea of an address: if it
        // renders, it is protected, and if it cannot be protected, it does not
        // render. The editor says so before it gets that far.
        if ($address === '' || !Exposure::isAddress($address)) {
            return '';
        }

        $atts = [];

        foreach (self::MAILTO_ATTRIBUTES as $name) {
            $value = self::text($attributes, $name);

            if ($value !== '') {
                $atts[$name] = $value;
            }
        }

        $linkText = self::text($attributes, 'linkText');

        if ($linkText !== '') {
            // opt_linktext=1 is "use the text below"; without it the alternative
            // text is stored and ignored, which is the trap the shortcode's
            // "subject" attribute sat in for years.
            $atts['opt_linktext'] = '1';
            $atts['alt_linktext'] = $linkText;
        }

        $markup = CryptX::get_instance()->cryptXShortcode($atts, $address, 'cryptx');

        // get_block_wrapper_attributes() carries the class names the editor
        // promised -- alignment, custom class, the block's own. Without it a
        // block that looks styled in the editor arrives unstyled on the page.
        //
        // Trimmed and conditional, so an empty result gives "<div>" and not
        // "<div >".
        $wrapper = trim(get_block_wrapper_attributes());

        return sprintf(
            '<div%s>%s</div>',
            $wrapper === '' ? '' : ' ' . $wrapper,
            $markup
        );
    }
}
