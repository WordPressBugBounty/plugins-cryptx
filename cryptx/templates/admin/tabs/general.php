<?php if (!defined('ABSPATH')) exit; ?>

<div class="cryptx-tab-content cryptx-general-settings">
    <table class="form-table">

        <!-- Security Settings Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php _e('Encryption Mode', 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php echo $securitySettings['encryption_mode']['label']; ?></th>
            <td>
                <select name="cryptX_var[encryption_mode]" id="encryption_mode">
                    <?php foreach ($securitySettings['encryption_mode']['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($securitySettings['encryption_mode']['value'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php echo $securitySettings['encryption_mode']['description']; ?>
                    <br/>
                    <strong><?php _e('Legacy:', 'cryptx'); ?></strong> <?php _e('Uses the original CryptX encryption algorithm for backward compatibility.', 'cryptx'); ?><br/>
                    <strong><?php _e('Secure:', 'cryptx'); ?></strong> <?php _e('Uses modern AES-256-GCM encryption with PBKDF2 key derivation for enhanced security.', 'cryptx'); ?>
                </p>

                <!-- Hidden checkbox that gets set based on the dropdown -->
                <input type="hidden" name="cryptX_var[use_secure_encryption]" id="use_secure_encryption"
                       value="<?php echo $securitySettings['use_secure_encryption']['value']; ?>" />
            </td>
        </tr>
        <tr id="iterations-row" style="<?php echo $securitySettings['encryption_mode']['value'] === 'secure' ? '' : 'display: none;'; ?>">
            <th scope="row"><?php echo $securitySettings['iterations']['label']; ?></th>
            <td>
                <select name="cryptX_var[iterations]" id="iterations">
                    <?php foreach ($securitySettings['iterations']['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($securitySettings['iterations']['value'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php echo $securitySettings['iterations']['description']; ?>
                    <br/>
                    <strong><?php _e('Secure:', 'cryptx'); ?></strong> <?php _e('Uses 100,000 iterations, which is very secure but requires a lot of processing time and can slow down the page.', 'cryptx'); ?><br/>
                    <strong><?php _e('Balanced:', 'cryptx'); ?></strong> <?php _e('Uses 10,000 iterations, making it a compromise between security and speed.', 'cryptx'); ?><br/>
                    <strong><?php _e('Performance:', 'cryptx'); ?></strong> <?php _e('Uses 1,000 iterations, which is very fast but not very secure, but has only very little impact on page speed.', 'cryptx'); ?>
                </p>
            </td>
        </tr>
        <!-- Separator -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php _e('General Settings', 'cryptx'); ?>
                </h3>
            </th>
        </tr>

        <!-- Apply CryptX to Section -->
        <tr>
            <th scope="row"><?php _e('Apply CryptX to', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <?php foreach ($applyTo as $key => $setting): ?>
                        <label for="<?php echo esc_attr($key); ?>">
                            <input type="hidden" name="cryptX_var[<?php echo esc_attr($key); ?>]" value="0" />
                            <input type="checkbox"
                                   id="<?php echo esc_attr($key); ?>"
                                   name="cryptX_var[<?php echo esc_attr($key); ?>]"
                                   value="1"
                                    <?php checked($options[$key] ?? 0, 1); ?> />
                            <?php echo esc_html($setting['label']); ?>
                            <?php if (isset($setting['description'])): ?>
                                <span class="description"><?php echo esc_html($setting['description']); ?></span>
                            <?php endif; ?>
                        </label><br />
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>

        <!-- Decryption Type -->
        <tr>
            <th scope="row"><?php _e('Decryption type', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <?php foreach ($decryptionType as $key => $setting): ?>
                        <label for="java_<?php echo esc_attr($key); ?>">
                            <input type="radio"
                                   id="java_<?php echo esc_attr($key); ?>"
                                   name="cryptX_var[java]"
                                   value="<?php echo esc_attr($setting['value']); ?>"
                                    <?php checked($options['java'] ?? 1, $setting['value']); ?> />
                            <?php echo $setting['label']; ?>
                        </label><br />
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>

        <!-- JavaScript Location -->
        <tr>
            <th scope="row"><?php _e('Javascript location', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <?php foreach ($javascriptLocation as $key => $setting): ?>
                        <label for="load_java_<?php echo esc_attr($key); ?>">
                            <input type="radio"
                                   id="load_java_<?php echo esc_attr($key); ?>"
                                   name="cryptX_var[load_java]"
                                   value="<?php echo esc_attr($setting['value']); ?>"
                                    <?php checked($options['load_java'] ?? 1, $setting['value']); ?> />
                            <?php echo $setting['label']; ?>
                        </label><br />
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>

        <!-- Additional Options -->
        <tr>
            <th scope="row"><?php _e('Additional options', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <label for="autolink">
                        <input type="hidden" name="cryptX_var[autolink]" value="0" />
                        <input type="checkbox"
                               id="autolink"
                               name="cryptX_var[autolink]"
                               value="1"
                                <?php checked($options['autolink'] ?? 0, 1); ?> />
                        <?php _e('Automatically add a link to non-linked email addresses.', 'cryptx'); ?>
                    </label><br />

                    <label for="metaBox">
                        <input type="hidden" name="cryptX_var[metaBox]" value="0" />
                        <input type="checkbox"
                               id="metaBox"
                               name="cryptX_var[metaBox]"
                               value="1"
                                <?php checked($options['metaBox'] ?? 0, 1); ?> />
                        <?php _e('Show the "Disable CryptX" checkbox in the post editor.', 'cryptx'); ?>
                    </label><br />

                    <label for="disable_rss">
                        <input type="hidden" name="cryptX_var[disable_rss]" value="0" />
                        <input type="checkbox"
                               id="disable_rss"
                               name="cryptX_var[disable_rss]"
                               value="1"
                                <?php checked($options['disable_rss'] ?? 1, 1); ?> />
                        <?php _e('Disable CryptX in RSS feeds.', 'cryptx'); ?>
                    </label>
                </fieldset>
            </td>
        </tr>

        <!-- Excluded Post IDs -->
        <tr>
            <th scope="row">
                <label for="excludedIDs"><?php _e('Excluded posts/pages IDs', 'cryptx'); ?></label>
            </th>
            <td>
                <input type="text"
                       id="excludedIDs"
                       name="cryptX_var[excludedIDs]"
                       value="<?php echo esc_attr($options['excludedIDs'] ?? ''); ?>"
                       class="regular-text" />
                <p class="description">
                    <?php _e('Comma-separated list of post/page IDs where CryptX should be disabled.', 'cryptx'); ?>
                </p>
            </td>
        </tr>

        <!-- Whitelist -->
        <tr>
            <th scope="row">
                <label for="whiteList"><?php _e('Whitelist', 'cryptx'); ?></label>
            </th>
            <td>
                <input type="text"
                       id="whiteList"
                       name="cryptX_var[whiteList]"
                       value="<?php echo esc_attr($options['whiteList'] ?? 'jpeg,jpg,png,gif'); ?>"
                       class="regular-text" />
                <p class="description">
                    <?php _e('Comma-separated list of file extensions that should not be encrypted when found in email addresses.', 'cryptx'); ?>
                </p>
            </td>
        </tr>
    </table>

    <!-- Submit Button -->
    <p class="submit">
        <input type="submit"
               name="cryptX_save_general_settings"
               class="button-primary"
               value="<?php _e('Save Changes', 'cryptx'); ?>" />
        <input type="submit"
               name="cryptX_var_reset"
               class="button-secondary"
               value="<?php _e('Reset to Defaults', 'cryptx'); ?>"
               onclick="return confirm('<?php _e('Are you sure you want to reset all settings to defaults?', 'cryptx'); ?>');" />
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