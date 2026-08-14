<?php

namespace CryptX\Admin;

use CryptX\CryptX;

/**
 * The single description of every CryptX setting.
 *
 * Label, explanation, type, allowed values, default, which tab it belongs to
 * and whether it is an everyday or a rare setting -- all of it lives here and
 * nowhere else. The REST controller validates against this array, and the
 * React screen renders itself from it. A new option therefore cannot end up
 * with a control but no validation, or with validation but no explanation.
 *
 * @package CryptX
 * @since   4.1.0
 */
final class SettingsSchema
{
    public const TAB_PROTECTION = 'protection';
    public const TAB_APPEARANCE = 'appearance';
    public const TAB_EXCEPTIONS = 'exceptions';
    public const TAB_ADVANCED   = 'advanced';

    /**
     * Options that exist in the stored array but are never offered for editing.
     *
     * 'version' and 'encryption_password' are written by the plugin itself;
     * 'echo' is a leftover that no code path reads any more;
     * 'use_secure_encryption' is derived from 'encryption_mode' on save, see
     * deriveImpliedValues() -- two switches for one decision only ever
     * contradict each other.
     */
    private const INTERNAL_KEYS = ['version', 'encryption_password', 'echo', 'use_secure_encryption'];

    /**
     * The tabs, in the order they appear.
     *
     * @return array<int, array{id: string, label: string, description: string}>
     */
    public static function tabs(): array
    {
        return [
            [
                'id' => self::TAB_PROTECTION,
                'label' => __('Protection', 'cryptx'),
                'description' => __('Where CryptX looks for email addresses, and how it hides them.', 'cryptx'),
            ],
            [
                'id' => self::TAB_APPEARANCE,
                'label' => __('Appearance', 'cryptx'),
                'description' => __('What your visitors see in place of the address.', 'cryptx'),
            ],
            [
                'id' => self::TAB_EXCEPTIONS,
                'label' => __('Exceptions', 'cryptx'),
                'description' => __('Content that CryptX should leave alone.', 'cryptx'),
            ],
            [
                'id' => self::TAB_ADVANCED,
                'label' => __('Advanced', 'cryptx'),
                'description' => __('Rarely needed. The defaults are right for almost every site.', 'cryptx'),
            ],
        ];
    }

    /**
     * Every editable option.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        // Filled once per request: the list depends on what is in the fonts
        // directory, and fields() is called several times per request.
        //
        // An empty result is deliberately not cached. Were the directory
        // briefly unreadable, caching [] would reinstate for the rest of the
        // request exactly the state this cache was introduced to remove: every
        // font choice falling back to the default.
        static $fonts = null;

        if ($fonts === null || $fonts === []) {
            $fonts = self::availableFonts();
        }

        return [
            // -------------------------------------------------- Protection --
            'the_content' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_PROTECTION,
                'section' => __('Where CryptX applies', 'cryptx'),
                'label' => __('Posts and pages', 'cryptx'),
                'help' => __('The main body of your posts and pages. This is the setting almost everyone wants. It can be switched off for single posts under Exceptions.', 'cryptx'),
            ],
            'the_excerpt' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_PROTECTION,
                'section' => __('Where CryptX applies', 'cryptx'),
                'label' => __('Excerpts', 'cryptx'),
                'help' => __('The short summaries many themes show on archive and search pages.', 'cryptx'),
            ],
            'comment_text' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_PROTECTION,
                'section' => __('Where CryptX applies', 'cryptx'),
                'label' => __('Comments', 'cryptx'),
                'help' => __('Addresses that visitors leave in the text of a comment. Worth keeping on: you have no control over what people type there.', 'cryptx'),
            ],
            'widget_text' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_PROTECTION,
                'section' => __('Where CryptX applies', 'cryptx'),
                'label' => __('Widgets', 'cryptx'),
                'help' => __('Text and HTML widgets, including block widgets. A contact address in a sidebar is one of the most common places a spam bot finds one.', 'cryptx'),
            ],
            'the_meta_key' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_PROTECTION,
                'section' => __('Where CryptX applies', 'cryptx'),
                'label' => __('Custom fields', 'cryptx'),
                'help' => __('Only takes effect where a theme prints custom fields with the_meta(). Most modern themes do not, so this rarely changes anything.', 'cryptx'),
            ],
            'autolink' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_PROTECTION,
                'section' => __('Recognition', 'cryptx'),
                'label' => __('Turn plain addresses into links', 'cryptx'),
                'help' => __('With this on, an address written as plain text becomes a working contact link and is protected. With it off, CryptX only protects addresses that were already linked -- a plain one stays readable for spam bots.', 'cryptx'),
            ],
            'java' => [
                'type' => 'choice',
                'default' => 1,
                'tab' => self::TAB_PROTECTION,
                'section' => __('Method', 'cryptx'),
                'label' => __('How the link is hidden', 'cryptx'),
                'help' => __('JavaScript protects considerably better, because the address is not in the page at all until someone clicks. Unicode keeps the link working without JavaScript, but a spam bot that decodes HTML entities will still read it.', 'cryptx'),
                'choices' => [
                    ['value' => 1, 'label' => __('JavaScript (recommended)', 'cryptx')],
                    ['value' => 0, 'label' => __('Unicode, works without JavaScript', 'cryptx')],
                ],
            ],
            'encryption_mode' => [
                'type' => 'choice',
                'default' => 'secure',
                'tab' => self::TAB_PROTECTION,
                'section' => __('Method', 'cryptx'),
                'label' => __('Encryption', 'cryptx'),
                'help' => __('Secure uses AES-256-GCM and costs a little computing time per page. Compatible uses the original CryptX method, which is far cheaper and still hides the address from anything that just scans the page text. Links created earlier keep working either way.', 'cryptx'),
                'depends' => ['java' => 1],
                'choices' => [
                    ['value' => 'secure', 'label' => __('Secure (AES-256-GCM)', 'cryptx')],
                    ['value' => 'legacy', 'label' => __('Compatible, faster', 'cryptx')],
                ],
            ],

            // -------------------------------------------------- Appearance --
            'opt_linktext' => [
                'type' => 'choice',
                'default' => 0,
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Instead of the address, show', 'cryptx'),
                'help' => __('The address in the link target is always protected. This only decides what is written on the link itself.', 'cryptx'),
                'choices' => [
                    ['value' => 0, 'label' => __('The address with @ and . replaced', 'cryptx')],
                    ['value' => 1, 'label' => __('A text of your choice', 'cryptx')],
                    ['value' => 2, 'label' => __('An image from a web address', 'cryptx')],
                    ['value' => 3, 'label' => __('An image from the media library', 'cryptx')],
                    ['value' => 4, 'label' => __('The address as HTML entities', 'cryptx')],
                    ['value' => 5, 'label' => __('The address drawn into a picture', 'cryptx')],
                ],
            ],
            'at' => [
                'type' => 'string',
                'default' => ' [at] ',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Replacement for @', 'cryptx'),
                'help' => __('Leading and trailing spaces are kept, so " [at] " reads as a word rather than running into the address.', 'cryptx'),
                'depends' => ['opt_linktext' => 0],
            ],
            'dot' => [
                'type' => 'string',
                'default' => ' [dot] ',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Replacement for .', 'cryptx'),
                'help' => __('Applies to every dot in the address, including the one in the domain.', 'cryptx'),
                'depends' => ['opt_linktext' => 0],
            ],
            'alt_linktext' => [
                'type' => 'string',
                'default' => '',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Link text', 'cryptx'),
                'help' => __('Shown in place of every address on the site. Useful when there is one contact address; confusing when there are several, because they all end up looking the same. Left empty, the link has no text at all -- the preview above shows what that looks like.', 'cryptx'),
                'depends' => ['opt_linktext' => 1],
            ],
            'alt_linkimage' => [
                'type' => 'url',
                'default' => '',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Image address', 'cryptx'),
                'help' => __('Full web address of the image to show instead of the text.', 'cryptx'),
                'depends' => ['opt_linktext' => 2],
            ],
            'http_linkimage_title' => [
                'type' => 'string',
                'default' => '',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Image description', 'cryptx'),
                'help' => __('Used as the alt text. Screen readers read this out, so describe what the image is for -- "Write us an email" rather than "envelope".', 'cryptx'),
                'depends' => ['opt_linktext' => 2],
            ],
            'alt_uploadedimage' => [
                'type' => 'media',
                'default' => 0,
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Image', 'cryptx'),
                'help' => __('Picked from your media library. The plugin stores the attachment, not the address, so the image survives a move to a different domain.', 'cryptx'),
                'depends' => ['opt_linktext' => 3],
            ],
            'alt_linkimage_title' => [
                'type' => 'string',
                'default' => '',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('What visitors see', 'cryptx'),
                'label' => __('Image description', 'cryptx'),
                'help' => __('Used as the alt text for screen readers.', 'cryptx'),
                'depends' => ['opt_linktext' => 3],
            ],

            // These three used to sit under Advanced, one tab away from the
            // choice they serve, with nothing to say where they had gone.
            'c2i_font' => [
                'type' => 'choice',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('Address as a picture', 'cryptx'),
                'label' => __('Font', 'cryptx'),
                'help' => __('Used when the address is drawn into a picture. The fonts shipped with CryptX are freely licensed.', 'cryptx'),
                // Like every other dependent field: shown when the option it
                // serves is actually in use, and out of the way otherwise.
                'depends' => ['opt_linktext' => 5],
                // The choices belong here rather than only in forClient():
                // sanitize() validates against fields(), so an empty list here
                // meant every save silently reset the stored font to ''.
                'choices' => $fonts,
                'default' => $fonts[0]['value'] ?? '',
            ],
            'c2i_fontSize' => [
                'type' => 'integer',
                'default' => 10,
                'min' => 6,
                'max' => 96,
                'tab' => self::TAB_APPEARANCE,
                'section' => __('Address as a picture', 'cryptx'),
                'label' => __('Font size in pixels', 'cryptx'),
                'help' => __('The picture is generated at this size. Larger means a sharper image on high resolution screens, but also a larger file.', 'cryptx'),
                'depends' => ['opt_linktext' => 5],
            ],
            'c2i_fontRGB' => [
                'type' => 'color',
                'default' => '#000000',
                'tab' => self::TAB_APPEARANCE,
                'section' => __('Address as a picture', 'cryptx'),
                'label' => __('Text colour', 'cryptx'),
                'help' => __('The background stays transparent, so the picture sits on whatever colour your theme uses.', 'cryptx'),
                'depends' => ['opt_linktext' => 5],
            ],

            // -------------------------------------------------- Exceptions --
            'excludedIDs' => [
                'type' => 'idlist',
                'default' => '',
                'tab' => self::TAB_EXCEPTIONS,
                'section' => __('Individual posts', 'cryptx'),
                'label' => __('Leave these posts and pages alone', 'cryptx'),
                'help' => __('Post and page IDs, separated by commas. Addresses in them stay exactly as written. The shortcode still works there, so you can protect single addresses by hand.', 'cryptx'),
            ],
            'metaBox' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_EXCEPTIONS,
                'section' => __('Individual posts', 'cryptx'),
                'label' => __('Offer a switch in the post editor', 'cryptx'),
                'help' => __('Adds a "Disable CryptX for this post/page" box to the editor, which writes into the list above. Without it the list can still be edited here.', 'cryptx'),
            ],
            'whiteList' => [
                'type' => 'string',
                'default' => 'jpeg,jpg,png,gif',
                'tab' => self::TAB_EXCEPTIONS,
                'section' => __('False positives', 'cryptx'),
                'label' => __('Endings that are not addresses', 'cryptx'),
                'help' => __('Anything ending in one of these is left alone. This is what keeps file names such as logo@2x.png from being treated as an email address. It cannot be used to exempt a particular address -- use the list of posts above for that.', 'cryptx'),
            ],
            'disable_rss' => [
                'type' => 'boolean',
                'default' => true,
                'tab' => self::TAB_EXCEPTIONS,
                'section' => __('Feeds', 'cryptx'),
                'label' => __('Leave RSS feeds unprotected', 'cryptx'),
                'help' => __('Feed readers do not run JavaScript, so a protected link would be dead in a feed. The trade-off is real: with this on, addresses are readable in your feed. Switch it off only if you accept that your feed links stop working for some readers.', 'cryptx'),
            ],

            // ---------------------------------------------------- Advanced --
            'iterations' => [
                'type' => 'choice',
                'default' => 10000,
                'tab' => self::TAB_ADVANCED,
                'section' => __('Encryption', 'cryptx'),
                'label' => __('Key strengthening', 'cryptx'),
                'help' => __('How much work goes into deriving the key. The cost is paid once per page, not per address. Higher makes life harder for anyone trying to unpick the addresses in bulk; lower renders pages faster.', 'cryptx'),
                'depends' => ['encryption_mode' => 'secure'],
                'choices' => [
                    ['value' => 100000, 'label' => __('Thorough (100,000)', 'cryptx')],
                    ['value' => 10000, 'label' => __('Balanced (10,000)', 'cryptx')],
                    ['value' => 1000, 'label' => __('Fast (1,000)', 'cryptx')],
                ],
            ],
            'link_mode' => [
                'type' => 'choice',
                'default' => 'data',
                'tab' => self::TAB_ADVANCED,
                'section' => __('Encryption', 'cryptx'),
                'label' => __('Link format', 'cryptx'),
                'help' => __('Data attributes work on sites with a Content-Security-Policy, where a javascript: link is blocked and every CryptX link would silently stop working. Only switch to the old format if something in your setup depends on it.', 'cryptx'),
                'choices' => [
                    ['value' => 'data', 'label' => __('Data attributes (recommended)', 'cryptx')],
                    ['value' => 'js', 'label' => __('javascript: link, as before 4.0.12', 'cryptx')],
                ],
            ],
            'load_java' => [
                'type' => 'choice',
                'default' => 1,
                'tab' => self::TAB_ADVANCED,
                'section' => __('Script', 'cryptx'),
                'label' => __('Load the script', 'cryptx'),
                'help' => __('In the footer the script is only loaded on pages that actually contain a protected address. In the header that decision cannot be made yet, so it is loaded everywhere -- choose the header only if a theme needs it early.', 'cryptx'),
                'choices' => [
                    ['value' => 1, 'label' => __('In the footer (recommended)', 'cryptx')],
                    ['value' => 0, 'label' => __('In the header', 'cryptx')],
                ],
            ],
            'css_id' => [
                'type' => 'htmlid',
                'default' => '',
                'tab' => self::TAB_ADVANCED,
                'section' => __('Styling', 'cryptx'),
                'label' => __('CSS id for the link', 'cryptx'),
                'help' => __('Added to every generated link. Remember that an id is meant to appear once per page -- with several addresses on one page, a class is the better choice.', 'cryptx'),
            ],
            'css_class' => [
                'type' => 'htmlid',
                'default' => '',
                'tab' => self::TAB_ADVANCED,
                'section' => __('Styling', 'cryptx'),
                'label' => __('CSS class for the link', 'cryptx'),
                'help' => __('Added to every generated link, alongside the class CryptX needs for itself.', 'cryptx'),
            ],
        ];
    }

    /**
     * The schema as the settings screen consumes it, with runtime values filled in.
     *
     * @return array<string, mixed>
     */
    public static function forClient(): array
    {
        $fields = self::fields();
        $out = [];
        foreach ($fields as $key => $definition) {
            $definition['key'] = $key;
            $out[] = $definition;
        }

        return [
            'tabs' => self::tabs(),
            'fields' => $out,
        ];
    }

    /**
     * The TTF files shipped in the fonts directory.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function availableFonts(): array
    {
        $files = CryptX::get_instance()->getFilesInDirectory(CRYPTX_DIR_PATH . 'fonts', ['ttf']);
        $fonts = [];

        foreach ($files as $file) {
            $fonts[] = [
                'value' => $file,
                'label' => str_replace('_', ' ', preg_replace('/\.ttf$/i', '', $file)),
            ];
        }

        return $fonts;
    }

    /**
     * Default values for every editable option.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::fields() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }

    /**
     * Validates and cleans incoming values.
     *
     * Anything that is not a known key is dropped, not merely cleaned: the
     * screen has no business writing options it does not know about, and the
     * internal ones must never arrive from a request at all.
     *
     * @param array<string, mixed> $input Raw values.
     *
     * @return array<string, mixed> Values fit to be stored.
     */
    public static function sanitize(array $input): array
    {
        $fields = self::fields();
        $clean = [];

        foreach ($input as $key => $value) {
            if (!isset($fields[$key]) || in_array($key, self::INTERNAL_KEYS, true)) {
                continue;
            }

            $definition = $fields[$key];
            $clean[$key] = self::sanitizeValue($value, $definition);
        }

        return self::deriveImpliedValues($clean);
    }

    /**
     * Cleans one value according to its type.
     *
     * @param mixed $value The incoming value.
     * @param array<string, mixed> $definition The field definition.
     *
     * @return mixed The cleaned value.
     */
    private static function sanitizeValue($value, array $definition)
    {
        if (is_array($value)) {
            return $definition['default'];
        }

        switch ($definition['type']) {
            case 'boolean':
                return (bool) $value ? 1 : 0;

            case 'integer':
                $number = (int) $value;
                $min = $definition['min'] ?? PHP_INT_MIN;
                $max = $definition['max'] ?? PHP_INT_MAX;

                return max($min, min($max, $number));

            case 'choice':
                foreach ($definition['choices'] as $choice) {
                    // Loose comparison on purpose: a choice value of 1 arrives
                    // from JSON as an integer but from a form as the string "1".
                    if ($choice['value'] == $value) {
                        return $choice['value'];
                    }
                }

                return $definition['default'];

            case 'color':
                $color = sanitize_hex_color(is_string($value) ? $value : '');

                return $color ?? $definition['default'];

            case 'url':
                return esc_url_raw((string) $value);

            case 'media':
                return absint($value);

            case 'htmlid':
                return sanitize_html_class((string) $value);

            case 'idlist':
                $ids = array_filter(array_map('absint', explode(',', (string) $value)));
                sort($ids);

                return implode(',', array_unique($ids));

            case 'string':
            default:
                // wp_kses_post rather than sanitize_text_field: the replacements
                // for @ and . are allowed to carry simple markup, and their
                // leading and trailing spaces have to survive.
                return wp_kses_post((string) $value);
        }
    }

    /**
     * Fills in the options that follow from another one.
     *
     * 'use_secure_encryption' exists in storage but has no control: two
     * switches for one decision only ever end up contradicting each other, and
     * the stored defaults for these two did exactly that before 4.1.0.
     *
     * @param array<string, mixed> $values Cleaned values.
     *
     * @return array<string, mixed> Values including the derived ones.
     */
    private static function deriveImpliedValues(array $values): array
    {
        if (isset($values['encryption_mode'])) {
            $values['use_secure_encryption'] = $values['encryption_mode'] === 'secure' ? 1 : 0;
        }

        return $values;
    }

    /**
     * Whether a key is one the settings screen must never write.
     *
     * @param string $key The option key.
     *
     * @return bool
     */
    public static function isInternal(string $key): bool
    {
        return in_array($key, self::INTERNAL_KEYS, true);
    }
}
