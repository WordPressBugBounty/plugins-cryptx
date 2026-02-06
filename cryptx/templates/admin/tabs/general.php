<?php if (!defined('ABSPATH')) exit; ?>

<div class="cryptx-tab-content cryptx-general-settings">
    <table class="form-table">

        <!-- Security Settings Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e('Encryption Mode', 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php echo esc_html($securitySettings['encryption_mode']['label'], 'cryptx'); ?></th>
            <td>
                <select name="cryptX_var[encryption_mode]" id="encryption_mode">
                    <?php foreach ($securitySettings['encryption_mode']['options'] as $cryptx_value => $cryptx_label): ?>
                        <option value="<?php echo esc_attr($cryptx_value); ?>" <?php selected($securitySettings['encryption_mode']['value'], $cryptx_value); ?>>
                            <?php echo esc_html($cryptx_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php echo esc_html($securitySettings['encryption_mode']['description'], 'cryptx'); ?>
                    <br/>
                    <strong><?php esc_html_e('Legacy:', 'cryptx'); ?></strong> <?php esc_html_e('Uses the original CryptX encryption algorithm for backward compatibility.', 'cryptx'); ?><br/>
                    <strong><?php esc_html_e('Secure:', 'cryptx'); ?></strong> <?php esc_html_e('Uses modern AES-256-GCM encryption with PBKDF2 key derivation for enhanced security.', 'cryptx'); ?>
                </p>

                <!-- Hidden checkbox that gets set based on the dropdown -->
                <input type="hidden" name="cryptX_var[use_secure_encryption]" id="use_secure_encryption"
                       value="<?php echo esc_html($securitySettings['use_secure_encryption']['value'], 'cryptx'); ?>" />
            </td>
        </tr>
        <tr id="iterations-row" style="<?php echo $securitySettings['encryption_mode']['value'] === 'secure' ? '' : 'display: none;'; ?>">
            <th scope="row"><?php echo esc_html($securitySettings['iterations']['label'], 'cryptx'); ?></th>
            <td>
                <select name="cryptX_var[iterations]" id="iterations">
                    <?php foreach ($securitySettings['iterations']['options'] as $cryptx_value => $cryptx_label): ?>
                        <option value="<?php echo esc_attr($cryptx_value); ?>" <?php selected($securitySettings['iterations']['value'], $cryptx_value); ?>>
                            <?php echo esc_html($cryptx_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php echo esc_html($securitySettings['iterations']['description'], 'cryptx'); ?>
                    <br/>
                    <strong><?php esc_html_e('Secure:', 'cryptx'); ?></strong> <?php esc_html_e('Uses 100,000 iterations, which is very secure but requires a lot of processing time and can slow down the page.', 'cryptx'); ?><br/>
                    <strong><?php esc_html_e('Balanced:', 'cryptx'); ?></strong> <?php esc_html_e('Uses 10,000 iterations, making it a compromise between security and speed.', 'cryptx'); ?><br/>
                    <strong><?php esc_html_e('Performance:', 'cryptx'); ?></strong> <?php esc_html_e('Uses 1,000 iterations, which is very fast but not very secure, but has only very little impact on page speed.', 'cryptx'); ?>
                </p>
            </td>
        </tr>
        <!-- Separator -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e('General Settings', 'cryptx'); ?>
                </h3>
            </th>
        </tr>

        <!-- Apply CryptX to Section -->
        <tr>
            <th scope="row"><?php esc_html_e('Apply CryptX to', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <?php foreach ($applyTo as $cryptx_key => $cryptx_setting): ?>
                        <label for="<?php echo esc_attr($cryptx_key); ?>">
                            <input type="hidden" name="cryptX_var[<?php echo esc_attr($cryptx_key); ?>]" value="0" />
                            <input type="checkbox"
                                   id="<?php echo esc_attr($cryptx_key); ?>"
                                   name="cryptX_var[<?php echo esc_attr($cryptx_key); ?>]"
                                   value="1"
                                    <?php checked($options[$cryptx_key] ?? 0, 1); ?> />
                            <?php echo esc_html($cryptx_setting['label']); ?>
                            <?php if (isset($cryptx_setting['description'])): ?>
                                <span class="description"><?php echo esc_html($cryptx_setting['description']); ?></span>
                            <?php endif; ?>
                        </label><br />
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>

        <!-- Decryption Type -->
        <tr>
            <th scope="row"><?php esc_html_e('Decryption type', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <?php foreach ($decryptionType as $cryptx_key => $cryptx_setting): ?>
                        <label for="java_<?php echo esc_attr($cryptx_key); ?>">
                            <input type="radio"
                                   id="java_<?php echo esc_attr($cryptx_key); ?>"
                                   name="cryptX_var[java]"
                                   value="<?php echo esc_attr($cryptx_setting['value']); ?>"
                                    <?php checked($options['java'] ?? 1, $cryptx_setting['value']); ?> />
                            <?php esc_html_e($cryptx_setting['label'], 'cryptx'); ?>
                        </label><br />
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>

        <!-- JavaScript Location -->
        <tr>
            <th scope="row"><?php esc_html_e('Javascript location', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <?php foreach ($javascriptLocation as $cryptx_key => $cryptx_setting): ?>
                        <label for="load_java_<?php echo esc_attr($cryptx_key); ?>">
                            <input type="radio"
                                   id="load_java_<?php echo esc_attr($cryptx_key); ?>"
                                   name="cryptX_var[load_java]"
                                   value="<?php echo esc_attr($cryptx_setting['value']); ?>"
                                    <?php checked($options['load_java'] ?? 1, $cryptx_setting['value']); ?> />
                            <?php esc_html_e($cryptx_setting['label'], 'cryptx'); ?>
                        </label><br />
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>

        <!-- Additional Options -->
        <tr>
            <th scope="row"><?php esc_html_e('Additional options', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <label for="autolink">
                        <input type="hidden" name="cryptX_var[autolink]" value="0" />
                        <input type="checkbox"
                               id="autolink"
                               name="cryptX_var[autolink]"
                               value="1"
                                <?php checked($options['autolink'] ?? 0, 1); ?> />
                        <?php esc_html_e('Automatically add a link to non-linked email addresses.', 'cryptx'); ?>
                    </label><br />

                    <label for="metaBox">
                        <input type="hidden" name="cryptX_var[metaBox]" value="0" />
                        <input type="checkbox"
                               id="metaBox"
                               name="cryptX_var[metaBox]"
                               value="1"
                                <?php checked($options['metaBox'] ?? 0, 1); ?> />
                        <?php esc_html_e('Show the "Disable CryptX" checkbox in the post editor.', 'cryptx'); ?>
                    </label><br />

                    <label for="disable_rss">
                        <input type="hidden" name="cryptX_var[disable_rss]" value="0" />
                        <input type="checkbox"
                               id="disable_rss"
                               name="cryptX_var[disable_rss]"
                               value="1"
                                <?php checked($options['disable_rss'] ?? 1, 1); ?> />
                        <?php esc_html_e('Disable CryptX in RSS feeds.', 'cryptx'); ?>
                    </label>
                </fieldset>
            </td>
        </tr>

        <!-- Excluded Post IDs -->
        <tr>
            <th scope="row">
                <label for="excludedIDs"><?php esc_html_e('Excluded posts/pages IDs', 'cryptx'); ?></label>
            </th>
            <td>
                <input type="text"
                       id="excludedIDs"
                       name="cryptX_var[excludedIDs]"
                       value="<?php echo esc_attr($options['excludedIDs'] ?? ''); ?>"
                       class="regular-text" />
                <p class="description">
                    <?php esc_html_e('Comma-separated list of post/page IDs where CryptX should be disabled.', 'cryptx'); ?>
                </p>
            </td>
        </tr>

        <!-- Whitelist -->
        <tr>
            <th scope="row">
                <label for="whiteList"><?php esc_html_e('Whitelist', 'cryptx'); ?></label>
            </th>
            <td>
                <input type="text"
                       id="whiteList"
                       name="cryptX_var[whiteList]"
                       value="<?php echo esc_attr($options['whiteList'] ?? 'jpeg,jpg,png,gif'); ?>"
                       class="regular-text" />
                <p class="description">
                    <?php esc_html_e('Comma-separated list of file extensions that should not be encrypted when found in email addresses.', 'cryptx'); ?>
                </p>
            </td>
        </tr>
    </table>

    <!-- Submit Button -->
    <p class="submit">
        <input type="submit"
               name="cryptX_save_general_settings"
               class="button-primary"
               value="<?php esc_html_e('Save Changes', 'cryptx'); ?>" />
        <input type="submit"
               name="cryptX_var_reset"
               class="button-secondary"
               value="<?php esc_html_e('Reset to Defaults', 'cryptx'); ?>"
               onclick="return confirm('<?php esc_html_e('Are you sure you want to reset all settings to defaults?', 'cryptx'); ?>');" />
    </p>
</div>

<script>
    // Auto-sync the dropdown with the hidden checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const encryptionModeSelect = document.getElementById('encryption_mode');
        const useSecureEncryption = document.getElementById('use_secure_encryption');
        const iterationsRow = document.getElementById('iterations-row');

        if (encryptionModeSelect && useSecureEncryption) {
            // Set initial value
            useSecureEncryption.value = (encryptionModeSelect.value === 'secure') ? '1' : '0';

            // Update on change
            encryptionModeSelect.addEventListener('change', function() {
                useSecureEncryption.value = (this.value === 'secure') ? '1' : '0';
                iterationsRow.style.display = (this.value === 'secure') ? '' : 'none';
            });
        }
    });
</script>