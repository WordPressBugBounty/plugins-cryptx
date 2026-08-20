<?php

namespace CryptX\Admin;

use CryptX\CryptX;
use CryptX\Exposure;

/**
 * A Site Health test that answers the only question that matters.
 *
 * The settings screen already judges what the plugin produces -- hidden, merely
 * encoded, or plainly readable -- but only for whoever opens the Appearance tab
 * and looks. Site Health is where someone goes when they want to know whether
 * their site is in order, and until now CryptX said nothing there. A plugin
 * whose whole job is invisible needs to be able to report on itself.
 *
 * The test is deliberately not alarmist. Several settings weaken protection on
 * purpose, and the defaults are among them: feeds are left alone because a feed
 * reader runs no JavaScript. Those are reported as what they are -- a choice
 * with a consequence -- and not as a fault.
 *
 * @package CryptX
 * @since   4.2.0
 */
final class SiteHealth
{
    /**
     * Hooks the test in.
     *
     * @return void
     */
    public function register(): void
    {
        add_filter('site_status_tests', [$this, 'addTest']);
    }

    /**
     * Declares the test.
     *
     * @param array<string, mixed> $tests The tests Site Health knows about.
     *
     * @return array<string, mixed> The tests including ours.
     */
    public function addTest(array $tests): array
    {
        $tests['direct']['cryptx_protection'] = [
            'label' => __('Email address protection', 'cryptx'),
            'test' => [$this, 'run'],
        ];

        return $tests;
    }

    /**
     * Runs the sample through the real chain and reports what came out.
     *
     * @return array<string, mixed> The result in the shape Site Health expects.
     */
    public function run(): array
    {
        $result = [
            'label' => __('Email addresses are hidden from spam bots', 'cryptx'),
            'status' => 'good',
            'badge' => [
                'label' => __('Security', 'cryptx'),
                'color' => 'blue',
            ],
            'description' => '',
            'actions' => sprintf(
                '<p><a href="%s">%s</a></p>',
                esc_url(admin_url('options-general.php?page=' . SettingsPage::MENU_SLUG)),
                esc_html__('Review the CryptX settings', 'cryptx')
            ),
            'test' => 'cryptx_protection',
        ];

        $cryptx = CryptX::get_instance();
        $options = $cryptx->loadCryptXOptionsWithDefaults();

        // The real filter chain on a real sample, not a description of what it
        // ought to do. This is the same path the front end takes.
        //
        // The carrier sentence is NOT translatable. A translation that loses
        // the "%s" produces a sample without an address; PHP 8 discards the
        // surplus argument without complaint, Exposure::of() then finds nothing
        // and the test reports "good" for ever after. A security check that
        // reassures because its own input went missing is worse than no check.
        $sample = 'Write to ' . Exposure::SAMPLE_ADDRESS . ' if you have any questions.';

        // Belt and braces: if the sample ever stops carrying an address, say so
        // instead of reporting success.
        if (Exposure::of($sample) !== Exposure::PLAIN) {
            $result['status'] = 'recommended';
            $result['badge']['color'] = 'blue';
            $result['label'] = __('Email address protection could not be checked', 'cryptx');
            $result['description'] = '<p>' . esc_html__(
                'CryptX could not build its test address, so the check has nothing to judge. This is a fault in the plugin, not in your settings.',
                'cryptx'
            ) . '</p>';

            return $result;
        }

        // The exemption list is deliberately switched off for the measurement.
        // The sample lives at example.com, so a site that exempts
        // "@example.com" -- a plausible thing to write -- would make its own
        // sample readable and report a working installation as critical. The
        // question here is whether the mechanism works, not whether every
        // address on the site is covered; the exemptions are a choice, and
        // choices are listed below rather than counted against the result.
        $markup = $cryptx->renderPreviewMarkup(['exemptAddresses' => ''], $sample);

        $exposure = Exposure::of($markup);
        $notes = $this->settingNotes($options);

        // Das Badge bleibt blau. In WordPress selbst steht es 28 von 28 Mal
        // auf 'blue': es benennt die Kategorie ("Security"), nicht das
        // Ergebnis. Das Ergebnis traegt das Symbol und die Einsortierung in
        // "Kritische Probleme" bzw. "Empfohlene Verbesserungen". Ein orangenes
        // Security-Badge daneben liest sich wie eine zweite Kategorie.
        if ($exposure === Exposure::PLAIN) {
            $result['status'] = 'critical';
            $result['label'] = __('Email addresses are readable in your pages', 'cryptx');
            $result['description'] = '<p>' . esc_html__(
                'With the current settings an address survives in the delivered HTML exactly as it was written. Any spam bot that reads the page reads the address.',
                'cryptx'
            ) . '</p>';
        } elseif ($exposure === Exposure::ENCODED) {
            $result['status'] = 'recommended';
            $result['label'] = __('Email addresses are only lightly disguised', 'cryptx');
            $result['description'] = '<p>' . esc_html__(
                'Addresses reach the page as HTML entities. That defeats a simple scanner, but any spam bot that decodes entities -- and most do -- reads them. Switching the method to JavaScript hides the address until someone clicks.',
                'cryptx'
            ) . '</p>';
        } else {
            $result['description'] = '<p>' . esc_html__(
                'A test address was run through the same filters your pages use. Nothing resembling an address was left in the result.',
                'cryptx'
            ) . '</p>';
        }

        if ($notes !== []) {
            // Listed, not counted against the result. Every one of these is a
            // setting somebody chose, and the commonest of them -- feeds left
            // alone -- is the default. Turning a default into a warning is how
            // people learn to ignore warnings, so the status follows the
            // measurement above and nothing else.
            $result['description'] .= '<p>' . esc_html__(
                'These settings deliberately leave some addresses unprotected:',
                'cryptx'
            ) . '</p><ul><li>' . implode('</li><li>', array_map('esc_html', $notes)) . '</li></ul>';
        }

        return $result;
    }

    /**
     * The settings that knowingly leave addresses in the open.
     *
     * @param array<string, mixed> $options The stored options.
     *
     * @return array<int, string> One sentence per setting, or an empty array.
     */
    private function settingNotes(array $options): array
    {
        $notes = [];

        if (!empty($options['disable_rss'])) {
            $notes[] = __('RSS feeds are left unprotected, so addresses are readable in your feed. This is the default, because a feed reader runs no JavaScript and a protected link would be dead there.', 'cryptx');
        }

        if (empty($options['autolink'])) {
            $notes[] = __('Plain addresses are not turned into links. They are still disguised, but they never become a working contact link.', 'cryptx');
        }

        $exempt = array_filter(array_map('trim', explode(',', (string) ($options['exemptAddresses'] ?? ''))));

        if ($exempt !== []) {
            $notes[] = sprintf(
                /* translators: %d: number of exempt email addresses */
                _n(
                    '%d address is on the list of addresses to leave alone, and stays readable wherever it appears.',
                    '%d addresses are on the list of addresses to leave alone, and stay readable wherever they appear.',
                    count($exempt),
                    'cryptx'
                ),
                count($exempt)
            );
        }

        $excluded = array_filter(array_map('trim', explode(',', (string) ($options['excludedIDs'] ?? ''))));

        if ($excluded !== []) {
            $notes[] = sprintf(
                /* translators: %d: number of excluded posts */
                _n(
                    '%d post or page is excluded from CryptX. Addresses in it stay exactly as written.',
                    '%d posts or pages are excluded from CryptX. Addresses in them stay exactly as written.',
                    count($excluded),
                    'cryptx'
                ),
                count($excluded)
            );
        }

        // Four whole sentences rather than one with a noun slotted in. A
        // sentence assembled from parts survives translation into German and
        // falls apart in any language that inflects the noun -- the i18n
        // handbook says so, and there are only four of them.
        $switchedOff = [
            'the_content' => __('CryptX is switched off for posts and pages.', 'cryptx'),
            'the_excerpt' => __('CryptX is switched off for excerpts.', 'cryptx'),
            'comment_text' => __('CryptX is switched off for comments.', 'cryptx'),
            'widget_text' => __('CryptX is switched off for widgets.', 'cryptx'),
        ];

        foreach ($switchedOff as $key => $sentence) {
            if (empty($options[$key])) {
                $notes[] = $sentence;
            }
        }

        return $notes;
    }
}
