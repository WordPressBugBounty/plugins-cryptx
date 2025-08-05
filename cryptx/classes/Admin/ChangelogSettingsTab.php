<?php

namespace CryptX\Admin;

use CryptX\Config;

class ChangelogSettingsTab
{
    /**
     * @var Config Configuration instance
     */
    private Config $config;

    /**
     * Template path
     */
    private const TEMPLATE_PATH = CRYPTX_DIR_PATH . 'templates/admin/tabs/changelog.php';

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
        if ('changelog' !== $this->getActiveTab()) {
            return;
        }

        $changelogs = $this->getChangelogData();
        $this->renderTemplate(self::TEMPLATE_PATH, ['changelogs' => $changelogs]);
    }

    /**
     * Get changelog data
     */
    private function getChangelogData(): array
    {
        $readmePath = CRYPTX_DIR_PATH . '/readme.txt';
        if (!file_exists($readmePath)) {
            return [];
        }

        $fileContents = file_get_contents($readmePath);
        if ($fileContents === false) {
            return [];
        }

        return $this->parseChangelog($fileContents);
    }

    /**
     * Render template with data
     */
    private function renderTemplate(string $path, array $data): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Template file not found: %s', $path));
        }

        extract($data);
        include $path;
    }

    /**
     * Get active tab
     */
    private function getActiveTab(): string
    {
        return sanitize_text_field($_GET['tab'] ?? 'general');
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
            if (!isset($_sections[$i - 1]) || !isset($_sections[$i])) {
                continue;
            }

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
            if (!isset($_changelogs[$i - 1]) || !isset($_changelogs[$i])) {
                continue;
            }

            $version = $_changelogs[$i - 1];
            $content = ltrim($_changelogs[$i], "\n");

            // Parse changelog items
            $items = $this->parseChangelogItems($content);

            if (!empty($items)) {
                $changelogs[] = [
                    'version' => $version,
                    'items' => $items
                ];
            }
        }

        return $changelogs;
    }

    /**
     * Parse individual changelog items
     *
     * @param string $content
     * @return array
     */
    private function parseChangelogItems(string $content): array
    {
        $lines = explode("\n", $content);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Remove leading asterisk and clean up
            if (strpos($line, '* ') === 0) {
                $line = substr($line, 2);
            }

            if (!empty($line)) {
                $items[] = trim($line);
            }
        }

        return $items;
    }
}