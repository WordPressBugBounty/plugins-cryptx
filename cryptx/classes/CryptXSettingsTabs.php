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
        add_action('cryptx_settings_tab', [$this, 'renderTabNavigation']);
        add_action('cryptx_settings_content', [$this, 'renderTabContent']);
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

        // Enqueue WordPress color picker assets
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Enqueue media uploader
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'cryptx'));
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
                wp_die(esc_html__('Security check failed', 'cryptx'));
            }

            $saveOptions = DataSanitizer::sanitize(wp_unslash($_POST['cryptX_var']));

            // Handle reset for any tab
            if (isset($_POST['cryptX_var_reset'])) {
                $this->cryptX->getCryptXOptionsDefaults();
                $this->cryptX->getConfig()->reset();
                $this->displayResetMessage();
                return;
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
                esc_html__('Settings saved.', 'cryptx'),
                'updated'
        );
    }

    /**
     * Display reset message
     */
    private function displayResetMessage(): void
    {
        add_settings_error(
                'cryptx_messages',
                'cryptx_reset',
                esc_html__('Settings have been reset to defaults.', 'cryptx'),
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
            <h1><?php esc_html_e("CryptX settings", 'cryptx'); ?></h1>
            <form method="post" action="">
                <?php
                wp_nonce_field('cryptX');
                settings_errors('cryptx_messages');
                ?>
                <h2 class="nav-tab-wrapper">
                    <?php do_action('cryptx_settings_tab'); ?>
                </h2>
                <div class="cryptx-tab-content-wrapper">
                    <?php do_action('cryptx_settings_content'); ?>
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
        $tab = (isset($_GET['tab']))? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
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
                'general' => esc_html__('General', 'cryptx'),
                'presentation' => esc_html__('Presentation', 'cryptx'),
                'howto' => esc_html__('How to&hellip;', 'cryptx'),
                'changelog' => esc_html__('Changelog', 'cryptx')
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
            if (isset($_POST['cryptX_save_general_settings']) || isset($_POST['cryptX_var_reset'])) {
                if (!empty($_POST['cryptX_var']) || isset($_POST['cryptX_var_reset'])) {
                    $generalTab->saveSettings(wp_unslash($_POST['cryptX_var']) ?? []);
                }
            }

            // Render the tab content
            $generalTab->render();

        } catch (\Exception $e) {
            // display admin notice
            add_settings_error(
                    'cryptx_messages',
                    'cryptx_error',
                    esc_html__('An error occurred while loading the general settings.', 'cryptx'),
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
            if (isset($_POST['cryptX_save_presentation_settings']) || isset($_POST['cryptX_var_reset'])) {
                if (!empty($_POST['cryptX_var']) || isset($_POST['cryptX_var_reset'])) {
                    $presentationTab->saveSettings($_POST['cryptX_var'] ?? []);
                }
            }

            // Render the tab content
            $presentationTab->render();

        } catch (\Exception $e) {
            // display admin notice
            add_settings_error(
                    'cryptx_messages',
                    'cryptx_error',
                    esc_html__('An error occurred while loading the presentation settings.', 'cryptx'),
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
            add_settings_error(
                    'cryptx_messages',
                    'cryptx_error',
                    esc_html__('An error occurred while loading the changelog.', 'cryptx'),
                    'error'
            );
        }
    }
}