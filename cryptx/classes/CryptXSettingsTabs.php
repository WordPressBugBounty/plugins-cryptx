<?php

namespace CryptX;

use CryptX\Admin\ChangelogSettingsTab;
use CryptX\Admin\GeneralSettingsTab;
use CryptX\Admin\PresentationSettingsTab;
use CryptX\Util\DataSanitizer;

/**
 * Class CryptXSettingsTabs
 * Handles the settings tabs functionality for the CryptX plugin admin interface
 */
class CryptXSettingsTabs
{
    /**
     * WordPress hook name for the CryptX settings page
     */
    private const SETTINGS_PAGE_HOOK = 'settings_page_cryptx';

    /**
     * @var array List of allowed tabs
     */
    private array $allowedTabs = ['general', 'presentation', 'howto', 'changelog'];

    /**
     * @var string Current active tab
     */
    private string $activeTab;

    /**
     * @var CryptX Instance of the main CryptX class
     */
    private CryptX $cryptX;

    /**
     * CryptXSettingsTabs constructor.
     *
     * @param CryptX $cryptX Instance of the main CryptX class
     */
    public function __construct(CryptX $cryptX)
    {
        $this->cryptX = $cryptX;
        $this->activeTab = $this->determineActiveTab();
        $this->initHooks();
    }

    private function initHooks(): void
    {
        // Add menu registration hook
        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerSettingsMenu']);
        }

        // Existing hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('rw_cryptx_settings_tab', [$this, 'renderTabNavigation']);
        add_action('rw_cryptx_settings_content', [$this, 'renderTabContent']);
    }

    /**
     * Enqueues necessary CSS and JavaScript assets for the CryptX admin settings page.
     *
     * @param string $hook The current admin page hook suffix.
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if ($hook !== self::SETTINGS_PAGE_HOOK) {
            return;
        }

        // Enqueue CSS files with version for cache busting
        wp_enqueue_style(
            'cryptx-admin-css',
            CRYPTX_DIR_URL . 'css/admin.css',
            [],
            CRYPTX_VERSION
        );

        wp_enqueue_style('wp-color-picker');

        // Enqueue JavaScript files
        wp_enqueue_script(
            'cryptx-admin-js',
            CRYPTX_DIR_URL . 'js/cryptx-admin.min.js',
            ['jquery', 'wp-color-picker'],
            CRYPTX_VERSION,
            true
        );

        wp_enqueue_media();
    }

    /**
     * Register the CryptX settings menu
     */
    public function registerSettingsMenu(): void
    {
        add_submenu_page(
            'options-general.php',
            _x('CryptX', 'CryptX settings page', 'cryptx'),
            _x('CryptX', 'CryptX settings menu', 'cryptx'),
            'manage_options',
            'cryptx',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Render the settings page
     */
    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $this->handleFormSubmission();
        $this->renderSettingsPageHtml();
    }

    /**
     * Handle form submission
     */
    private function handleFormSubmission(): void
    {
        if (!empty($_POST['cryptX_var'])) {
            if (!check_admin_referer('cryptX')) {
                wp_die(__('Security check failed'));
            }

            $saveOptions = DataSanitizer::sanitize($_POST['cryptX_var']);

            if (isset($_POST['cryptX_var_reset'])) {
                $saveOptions = $this->cryptX->getCryptXOptionsDefaults();
            }

            if (isset($_POST['cryptX_save_general_settings'])) {
                $saveOptions = $this->parseGeneralSettings($saveOptions);
            }

            $this->cryptX->saveCryptXOptions($saveOptions);
            $this->displaySuccessMessage();
        }
    }


    /**
     * Parse general settings
     */
    private function parseGeneralSettings(array $saveOptions): array
    {
        $checkboxes = [
            'the_content' => 0,
            'the_meta_key' => 0,
            'the_excerpt' => 0,
            'comment_text' => 0,
            'widget_text' => 0,
            'autolink' => 0,
            'metaBox' => 0,
        ];

        return wp_parse_args($saveOptions, $checkboxes);
    }

    /**
     * Display success message
     */
    private function displaySuccessMessage(): void
    {
        add_settings_error(
            'cryptx_messages',
            'cryptx_message',
            __('Settings saved.'),
            'updated'
        );
    }

    /**
     * Render the settings page HTML
     */
    private function renderSettingsPageHtml(): void
    {
        ?>
        <div class="cryptx-option-page">
            <h1><?php _e("CryptX settings", 'cryptx'); ?></h1>
            <form method="post" action="">
                <?php
                wp_nonce_field('cryptX');
                settings_errors('cryptx_messages');
                ?>
                <h2 class="nav-tab-wrapper">
                    <?php do_action('rw_cryptx_settings_tab'); ?>
                </h2>
                <div class="cryptx-tab-content-wrapper">
                    <?php do_action('rw_cryptx_settings_content'); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Determine the active tab from GET parameters
     *
     * @return string
     */
    private function determineActiveTab(): string
    {
        $tab = $_GET['tab'] ?? 'general';
        return in_array($tab, $this->allowedTabs) ? $tab : 'general';
    }

    /**
     * Get the current active tab
     *
     * @return string
     */
    public function getActiveTab(): string
    {
        return $this->activeTab;
    }

    /**
     * Render the tab navigation
     */
    public function renderTabNavigation(): void
    {
        $tabs = [
            'general' => __('General', 'cryptx'),
            'presentation' => __('Presentation', 'cryptx'),
            'howto' => __('How to&hellip;', 'cryptx'),
            'changelog' => __('Changelog', 'cryptx')
        ];

        foreach ($tabs as $tab => $label) {
            $this->renderTabLink($tab, $label);
        }
    }

    /**
     * Render individual tab link
     *
     * @param string $tab Tab identifier
     * @param string $label Tab label
     */
    private function renderTabLink(string $tab, string $label): void
    {
        $isActive = $this->activeTab === $tab || ($this->activeTab === '' && $tab === 'general');
        $activeClass = $isActive ? 'nav-tab-active' : '';
        $url = admin_url('options-general.php?page=' . CRYPTX_BASEFOLDER . '&tab=' . $tab);

        printf(
            '<a class="nav-tab %s" href="%s">%s</a>',
            esc_attr($activeClass),
            esc_url($url),
            esc_html($label)
        );
    }

    /**
     * Render the content for the current active tab
     */
    public function renderTabContent(): void
    {
        switch ($this->activeTab) {
            case 'general':
                $this->renderGeneralTab();
                break;
            case 'presentation':
                $this->renderPresentationTab();
                break;
            case 'howto':
                $this->renderHowtoTab();
                break;
            case 'changelog':
                $this->renderChangelogTab();
                break;
        }
    }

    /**
     * Render the general settings tab content
     */
    private function renderGeneralTab(): void
    {
        try {
            // Get the Config instance from CryptX
            $config = $this->cryptX->getConfig();

            // Create and render the General Settings Tab
            $generalTab = new GeneralSettingsTab($config);

            // Handle form submission if needed
            if (isset($_POST['cryptX_save_general_settings'])) {
                if (!empty($_POST['cryptX_var'])) {
                    $generalTab->saveSettings($_POST['cryptX_var']);
                }
            }

            // Render the tab content
            $generalTab->render();

        } catch (\Exception $e) {
            // Log error and display admin notice
            error_log('CryptX General Settings Tab Error: ' . $e->getMessage());
            add_settings_error(
                'cryptx_messages',
                'cryptx_error',
                __('An error occurred while loading the general settings.', 'cryptx'),
                'error'
            );
        }
    }

    /**
     * Render the presentation settings tab content
     */
    private function renderPresentationTab(): void
    {
        try {
            // Get the Config instance from CryptX
            $config = $this->cryptX->getConfig();

            // Create and render the Presentation Settings Tab
            $presentationTab = new PresentationSettingsTab($config);

            // Handle form submission if needed
            if (isset($_POST['cryptX_save_presentation_settings'])) {
                if (!empty($_POST['cryptX_var'])) {
                    $presentationTab->saveSettings($_POST['cryptX_var']);
                }
            }

            // Render the tab content
            $presentationTab->render();

        } catch (\Exception $e) {
            // Log error and display admin notice
            error_log('CryptX Presentation Settings Tab Error: ' . $e->getMessage());
            add_settings_error(
                'cryptx_messages',
                'cryptx_error',
                __('An error occurred while loading the presentation settings.', 'cryptx'),
                'error'
            );
        }
    }

    /**
     * Render the how-to tab content
     */
    private function renderHowtoTab(): void
    {
        require CRYPTX_DIR_PATH . '/templates/admin/tabs/howto.php';
    }

    /**
     * Render the changelog tab content
     */
    private function renderChangelogTab(): void
    {
        try {
            $changelogTab = new ChangelogSettingsTab($this->cryptX->getConfig());
            $changelogTab->render();
        } catch (\Exception $e) {
            error_log('CryptX Changelog Tab Error: ' . $e->getMessage());
            add_settings_error(
                'cryptx_messages',
                'cryptx_error',
                __('An error occurred while loading the changelog.', 'cryptx'),
                'error'
            );
        }
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