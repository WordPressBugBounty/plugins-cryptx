<?php

namespace CryptX\Admin;

use CryptX\Config;

class GeneralSettingsTab {
    private Config $config;
    private const TEMPLATE_PATH = CRYPTX_DIR_PATH . 'templates/admin/tabs/general.php';
    private const DEFAULTS = [
        'the_content' => 0,
        'the_meta_key' => 0,
        'the_excerpt' => 0,
        'comment_text' => 0,
        'widget_text' => 0,
        'autolink' => 0,
        'metaBox' => 0,
        'disable_rss' => 0,
        'java' => 1,
        'load_java' => 1,
        'use_secure_encryption' => 0,
        'encryption_mode' => 'legacy',
        'iteration' => '10000'
    ];

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function render(): void {
        if ('general' !== $this->getActiveTab()) {
            return;
        }

        $options = $this->config->getAll();
        // Extract variables for the template
        $settings = [
            'options' => $options, // Pass all options
            'securitySettings' => [
                'use_secure_encryption' => [
                    'label' => esc_html__('Use Secure Encryption', 'cryptx'),
                    'description' => esc_html__('Enable AES-256-GCM encryption for enhanced security (recommended for new installations)', 'cryptx'),
                    'value' => $options['use_secure_encryption'] ?? 0
                ],
                'encryption_mode' => [
                    'label' => esc_html__('Encryption Mode', 'cryptx'),
                    'description' => esc_html__('Choose encryption method. Legacy mode maintains backward compatibility with existing encrypted emails.', 'cryptx'),
                    'value' => $options['encryption_mode'] ?? 'legacy',
                    'options' => [
                        'legacy' => esc_html__('Legacy (Compatible)', 'cryptx'),
                        'secure' => esc_html__('Secure (AES-256-GCM)', 'cryptx')
                    ]
                ],
                'iterations' => [
                    'label' => esc_html__('PBKDF2 iterations', 'cryptx'),
                    'description' => esc_html__('Choose iterations mode. It\'s a trade-off between security and speed. The more email addresses there are on a page, the greater the impact will be.', 'cryptx'),
                    'value' => $options['iterations'] ?? '10000',
                    'options' => [
                        '100000' => esc_html__('Secure', 'cryptx'),
                        '10000' => esc_html__('Balanced', 'cryptx'),
                        '1000' => esc_html__('Performance', 'cryptx')
                    ]
                ]
            ],
            'applyTo' => [
                'the_content' => [
                    'label' => esc_html__('Content', 'cryptx'),
                    'description' => esc_html__('(this can be disabled per Post by an Option)', 'cryptx')
                ],
                'the_meta_key' => [
                    'label' => esc_html__('Custom fields', 'cryptx'),
                    'description' => esc_html__('(works only with the_meta()!)', 'cryptx')
                ],
                'the_excerpt' => [
                    'label' => esc_html__('Excerpt', 'cryptx'),
                ],
                'comment_text' => [
                    'label' => esc_html__('Comments', 'cryptx'),
                ],
                'widget_text' => [
                    'label' => esc_html__('Widgets', 'cryptx'),
                    'description' => esc_html__('(applies to all text and HTML widgets)', 'cryptx')
                ]
            ],
            'decryptionType' => [
                'javascript' => [
                    'value' => 1,
                    'label' => esc_html__('Use javascript to hide the Email-Link.', 'cryptx')
                ],
                'unicode' => [
                    'value' => 0,
                    'label' => esc_html__('Use Unicode to hide the Email-Link.', 'cryptx')
                ]
            ],
            'javascriptLocation' => [
                'header' => [
                    'value' => 0,
                    'label' => esc_html__('Load the javascript in the "header" of the page.', 'cryptx')
                ],
                'footer' => [
                    'value' => 1,
                    'label' => esc_html__('Load the javascript in the "footer" of the page.', 'cryptx')
                ]
            ],
            'excludedIds' => $options['excludedIDs'],
            'metaBox' => $options['metaBox'],
            'autolink' => $options['autolink'],
            'whiteList' => $options['whiteList']
        ];

        $this->renderTemplate(self::TEMPLATE_PATH, $settings);
    }

    private function renderTemplate(string $path, array $data): void {
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Template file not found: %s', esc_html($path, 'cryptx')));
        }

        extract($data);
        include $path;
    }

    private function getActiveTab(): string {
        return sanitize_text_field(wp_unslash($_GET['tab'] ?? 'general'));
    }

    public function saveSettings(array $data): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'cryptx'));
        }

        check_admin_referer('cryptX');

        // Merge POST data with defaults to handle unchecked checkboxes
        $sanitized = array_merge(
            self::DEFAULTS,
            array_map(function($value) {
                if ($value === '1') return 1; // Convert string '1' to integer 1 for checkboxes
                return sanitize_text_field($value);
            }, $data)
        );

        if (!empty($_POST['cryptX_var_reset'])) {
            $this->config->reset();
            return;
        }

        $this->config->update($sanitized);

        add_settings_error(
            'cryptx_messages',
            'settings_updated',
            esc_html__('Settings saved.', 'cryptx'),
            'updated'
        );
    }

    private function sanitizeSettings(array $data): array {
        return array_merge(
            self::DEFAULTS,
            array_map('sanitize_text_field', $data)
        );
    }
}