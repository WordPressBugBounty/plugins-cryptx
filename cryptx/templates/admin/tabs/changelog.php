
<?php
/**
 * CryptX Changelog Settings Tab Template
 * 
 * @var array $cryptx_changelogs Array of changelog entries
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cryptx-tab-content cryptx-changelog-settings">
    <table class="form-table">
        <!-- Header Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e('CryptX Changelog', 'cryptx'); ?>
                </h3>
                <p class="description">
                    <?php esc_html_e('View the complete history of CryptX updates, improvements, and bug fixes.', 'cryptx'); ?>
                </p>
            </th>
        </tr>

        <?php if (empty($cryptx_changelogs)): ?>
            <!-- No Changelog Available -->
            <tr>
                <th scope="row"><?php esc_html_e('Status', 'cryptx'); ?></th>
                <td>
                    <div class="notice notice-warning inline">
                        <p>
                            <span class="dashicons dashicons-warning" style="color: #f56e28;"></span>
                            <?php esc_html_e('No changelog information available. The readme.txt file may be missing or corrupted.', 'cryptx'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <!-- Changelog Entries -->
            <?php foreach ($cryptx_changelogs as $cryptx_index => $cryptx_changelog): ?>
                <tr>
                    <th scope="row" style="vertical-align: top; width: 150px;">
                        <div class="cryptx-version-badge">
                            <strong><?php echo esc_html($cryptx_changelog['version']); ?></strong>
                        </div>
                    </th>
                    <td>
                        <div class="cryptx-changelog-content">
                            <?php if (!empty($cryptx_changelog['items'])): ?>
                                <ul class="cryptx-changelog-list">
                                    <?php foreach ($cryptx_changelog['items'] as $cryptx_item): ?>
                                        <li><?php echo wp_kses_post($cryptx_item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="description"><?php esc_html_e('No details available for this version.', 'cryptx'); ?></p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                
                <?php if ($cryptx_index < count($cryptx_changelogs) - 1): ?>
                    <!-- Separator between versions -->
                    <tr>
                        <td colspan="2" style="padding: 0;">
                            <hr style="margin: 15px 0; border: none; border-top: 1px solid #f0f0f0;">
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Footer Information -->
            <tr>
                <th colspan="2">
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <p class="description" style="text-align: center; font-style: italic;">
                            <?php
                            printf(
                                /* translators: %1$d: Number of changelog entries, %2$s: Link to WordPress Plugin Directory */
                                esc_html__('Showing %1$d version entries. For complete documentation, visit the %2$s.', 'cryptx'),
                                count($cryptx_changelogs),
                                '<a href="https://wordpress.org/plugins/cryptx/" target="_blank">' . esc_html__('WordPress Plugin Directory', 'cryptx') . '</a>'
                            );
                            ?>
                        </p>
                    </div>
                </th>
            </tr>
        <?php endif; ?>
    </table>
</div>

<style>
    .cryptx-changelog-settings .cryptx-version-badge {
        background: #0073aa;
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 600;
        display: inline-block;
        min-width: 80px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .cryptx-changelog-settings .cryptx-changelog-content {
        margin-left: 0;
    }

    .cryptx-changelog-settings .cryptx-changelog-list {
        margin: 0;
        padding-left: 20px;
        list-style: none;
    }

    .cryptx-changelog-settings .cryptx-changelog-list li {
        position: relative;
        margin-bottom: 8px;
        padding-left: 20px;
        line-height: 1.5;
        color: #555;
    }

    .cryptx-changelog-settings .cryptx-changelog-list li:before {
        content: "•";
        color: #0073aa;
        font-weight: bold;
        position: absolute;
        left: 0;
        top: 0;
    }

    .cryptx-changelog-settings .cryptx-changelog-list li:last-child {
        margin-bottom: 0;
    }

    .cryptx-changelog-settings .notice.inline {
        margin: 0;
        padding: 12px;
    }

    .cryptx-changelog-settings .notice.inline .dashicons {
        float: left;
        margin-right: 8px;
        margin-top: 2px;
    }

    .cryptx-changelog-settings .form-table th {
        padding-right: 20px;
    }

    .cryptx-changelog-settings .form-table td {
        padding-left: 20px;
    }

    /* Responsive adjustments */
    @media (max-width: 782px) {
        .cryptx-changelog-settings .cryptx-version-badge {
            margin-bottom: 10px;
        }
        
        .cryptx-changelog-settings .form-table th {
            width: auto !important;
            padding-right: 10px;
        }
        
        .cryptx-changelog-settings .form-table td {
            padding-left: 10px;
        }
    }
</style>
