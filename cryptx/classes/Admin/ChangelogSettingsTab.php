<?php

namespace CryptX\Admin;

use CryptX\Config;

/**
 * Class ChangelogSettingsTab
 * Handles the changelog tab functionality in the CryptX plugin admin interface
 */
class ChangelogSettingsTab
{
    /**
     * @var Config Configuration instance
     */
    private Config $config;

    /**
     * ChangelogSettingsTab constructor.
     *
     * @param Config $config Configuration instance
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Render the changelog tab content
     */
    public function render(): void
    {
        echo '<h4>' . esc_html__('Changelog', 'cryptx') . '</h4>';
        $this->renderChangelogContent();
    }

    /**
     * Parse and render changelog content from readme.txt
     */
    private function renderChangelogContent(): void
    {
        $readmePath = CRYPTX_DIR_PATH . '/readme.txt';
        if (!file_exists($readmePath)) {
            return;
        }

        $fileContents = file_get_contents($readmePath);
        if ($fileContents === false) {
            return;
        }

        $changelogs = $this->parseChangelog($fileContents);
        foreach ($changelogs as $log) {
            echo wp_kses_post("<dl>" . implode("", $log) . "</dl>");
        }
    }

    /**
     * Parse changelog content from readme.txt
     *
     * @param string $content
     * @return array
     */
    private function parseChangelog(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);

        // Split into sections
        $sections = $this->parseSections($content);
        if (!isset($sections['changelog'])) {
            return [];
        }

        // Parse changelog entries
        return $this->parseChangelogEntries($sections['changelog']['content']);
    }

    /**
     * Parse sections from readme content
     *
     * @param string $content
     * @return array
     */
    private function parseSections(string $content): array
    {
        $_sections = preg_split('/^[\s]*==[\s]*(.+?)[\s]*==/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $sections = [];

        for ($i = 1; $i <= count($_sections); $i += 2) {
            $title = $_sections[$i - 1];
            $sections[str_replace(' ', '_', strtolower($title))] = [
                'title' => $title,
                'content' => $_sections[$i]
            ];
        }

        return $sections;
    }

    /**
     * Parse changelog entries
     *
     * @param string $content
     * @return array
     */
    private function parseChangelogEntries(string $content): array
    {
        $_changelogs = preg_split('/^[\s]*=[\s]*(.+?)[\s]*=/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $changelogs = [];

        for ($i = 1; $i <= count($_changelogs); $i += 2) {
            $version = $_changelogs[$i - 1];
            $content = ltrim($_changelogs[$i], "\n");
            $content = str_replace("* ", "<li>", $content);
            $content = str_replace("\n", " </li>\n", $content);

            $changelogs[] = [
                'version' => "<dt>" . esc_html($version) . "</dt>",
                'content' => "<dd><ul>" . wp_kses_post($content) . "</ul></dd>"
            ];
        }

        return $changelogs;
    }
}