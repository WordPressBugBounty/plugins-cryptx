<?php

namespace CryptX\Admin;

use CryptX\Config;

class PresentationSettingsTab {
    private Config $config;
    private const TEMPLATE_PATH = CRYPTX_DIR_PATH . 'templates/admin/tabs/presentation.php';

    public function __construct(Config $config) {
        $this->config = $config;
    }

    /**
     * Renders the template for the active tab.
     *
     * The function checks if the 'presentation' tab is active. If not, it returns without further
     * execution. When active, it retrieves configuration options, organizes them into a structured
     * settings array, and renders the template with the specified settings.
     *
     * @return void
     */
    public function render(): void {
        if ('presentation' !== $this->getActiveTab()) {
            return;
        }

        $options = $this->config->getAll();

        // Extract variables for the template
        $settings = [
            'css' => [
                'id' => $options['css_id'],
                'class' => $options['css_class']
            ],
            'emailReplacements' => [
                'at' => $options['at'],
                'dot' => $options['dot']
            ],
            'linkTextOptions' => [
                'replacement' => [
                    'value' => 0,
                    'fields' => [
                        'at' => [
                            'label' => __('Replacement for \'@\'', 'cryptx'),
                            'value' => $options['at']
                        ],
                        'dot' => [
                            'label' => __('Replacement for \'.\'', 'cryptx'),
                            'value' => $options['dot']
                        ]
                    ]
                ],
                'customText' => [
                    'value' => 1,
                    'fields' => [
                        'alt_linktext' => [
                            'label' => __('Text for link', 'cryptx'),
                            'value' => $options['alt_linktext']
                        ]
                    ]
                ],
                'externalImage' => [
                    'value' => 2,
                    'fields' => [
                        'alt_linkimage' => [
                            'label' => __('Image-URL', 'cryptx'),
                            'value' => $options['alt_linkimage']
                        ],
                        'http_linkimage_title' => [
                            'label' => __('Title-Tag for the Image', 'cryptx'),
                            'value' => $options['http_linkimage_title']
                        ]
                    ]
                ],
                'uploadedImage' => [
                    'value' => 3,
                    'fields' => [
                        'alt_uploadedimage' => [
                            'value' => $options['alt_uploadedimage']
                        ],
                        'alt_linkimage_title' => [
                            'label' => __('Title-Tag for the Image', 'cryptx'),
                            'value' => $options['alt_linkimage_title']
                        ]
                    ]
                ],
                'scrambled' => [
                    'value' => 4,
                    'label' => __('Text scrambled by AntiSpamBot (Try it and look at your site and check the html source!)', 'cryptx')
                ],
                'pngImage' => [
                    'value' => 5,
                    'label' => __('Convert Email to PNG-image', 'cryptx'),
                    'fields' => [
                        'c2i_font' => [
                            'label' => __('Font', 'cryptx'),
                            'value' => $options['c2i_font'],
                            'options' => $this->getFontOptions()
                        ],
                        'c2i_fontSize' => [
                            'label' => __('Font size (pixel)', 'cryptx'),
                            'value' => $options['c2i_fontSize']
                        ],
                        'c2i_fontRGB' => [
                            'label' => __('Font color (RGB)', 'cryptx'),
                            'value' => $options['c2i_fontRGB']
                        ]
                    ]
                ]
            ],
            'selectedOption' => $options['opt_linktext']
        ];

        $this->renderTemplate(self::TEMPLATE_PATH, $settings);
    }

    /**
     * Renders a template file by including it and extracting the provided data for use within the template scope.
     *
     * @param string $path The file path to the template to be rendered. Must be a valid, existing file.
     * @param array $data An associative array of data to be extracted and made available to the template.
     *
     * @return void
     *
     * @throws \RuntimeException If the specified template file does not exist.
     */
    private function renderTemplate(string $path, array $data): void {
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Template file not found: %s', $path));
        }

        extract($data);
        include $path;
    }

    /**
     * Retrieves the active tab from the request, defaulting to 'general' if not specified.
     *
     * @return string The sanitized active tab value from the request or 'general' if no tab is provided.
     */
    private function getActiveTab(): string {
        return sanitize_text_field($_GET['tab'] ?? 'general');
    }

    /**
     * Retrieves the available font options by scanning a directory for font files.
     *
     * @return array An associative array where the keys are the font file names and the values are the font names without the '.ttf' extension.
     */
    private function getFontOptions(): array {
        $fonts = [];
        $fontFiles = $this->getFilesInDirectory(CRYPTX_DIR_PATH . 'fonts', ['ttf']);

        foreach ($fontFiles as $font) {
            $fonts[$font] = str_replace('.ttf', '', $font);
        }

        return $fonts;
    }

    /**
     * Retrieves a list of files in a specified directory that match the given file extensions.
     *
     * @param string $path The path to the directory to scan for files.
     * @param array $extensions An array of file extensions to filter the files by.
     *                          Only files matching these extensions will be included in the results.
     *
     * @return array An array of filenames from the specified directory that match the provided extensions.
     *               If the directory does not exist, an empty array is returned.
     */
    private function getFilesInDirectory(string $path, array $extensions): array {
        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $dir = new \DirectoryIterator($path);

        foreach ($dir as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions)) {
                $files[] = $file->getFilename();
            }
        }

        return $files;
    }

    /**
     * Saves and processes the settings for the application. Handles security checks,
     * optional reset functionality, and updates sanitized settings to the configuration.
     *
     * @param array $data The input array containing settings data to be saved.
     *                     The data is sanitized before being saved into the configuration.
     *
     * @return void This method does not return a value.
     */
    public function saveSettings(array $data): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('cryptX');

        // Handle reset button
        if (!empty($_POST['cryptX_var_reset'])) {
            $this->config->reset();
            add_settings_error(
                'cryptx_messages',
                'settings_reset',
                __('Presentation settings have been reset to defaults.', 'cryptx'),
                'updated'
            );
            return;
        }

        $sanitized = $this->sanitizeSettings($data);
        $this->config->update($sanitized);

        add_settings_error(
            'cryptx_messages',
            'settings_updated',
            __('Settings saved.', 'cryptx'),
            'updated'
        );
    }

    /**
     * Sanitizes an array of settings data to ensure secure and valid formatting.
     *
     * @param array $data The input array containing settings data to be sanitized.
     *                     Expected keys include CSS settings, text replacements, link text options,
     *                     and font settings, each of which is sanitized according to its respective type.
     *
     * @return array The sanitized array with cleaned or validated values for all specified settings.
     */
    private function sanitizeSettings(array $data): array {
        $sanitized = [];

        // CSS settings
        $sanitized['css_id'] = sanitize_html_class($data['css_id'] ?? '');
        $sanitized['css_class'] = sanitize_html_class($data['css_class'] ?? '');

        // Text replacements - preserve whitespace for 'at' and 'dot' fields
        $sanitized['at'] = wp_kses_post($data['at'] ?? ' [at] ');
        $sanitized['dot'] = wp_kses_post($data['dot'] ?? ' [dot] ');

        // Link text options
        $sanitized['opt_linktext'] = absint($data['opt_linktext'] ?? 0);
        $sanitized['alt_linktext'] = sanitize_text_field($data['alt_linktext'] ?? '');
        $sanitized['alt_linkimage'] = esc_url_raw($data['alt_linkimage'] ?? '');
        $sanitized['http_linkimage_title'] = sanitize_text_field($data['http_linkimage_title'] ?? '');
        $sanitized['alt_uploadedimage'] = absint($data['alt_uploadedimage'] ?? 0);
        $sanitized['alt_linkimage_title'] = sanitize_text_field($data['alt_linkimage_title'] ?? '');

        // Font settings
        $sanitized['c2i_font'] = sanitize_file_name($data['c2i_font'] ?? '');
        $sanitized['c2i_fontSize'] = absint($data['c2i_fontSize'] ?? 10);
        $sanitized['c2i_fontRGB'] = sanitize_hex_color($data['c2i_fontRGB'] ?? '#000000');

        return $sanitized;
    }

}