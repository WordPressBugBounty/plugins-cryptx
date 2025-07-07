<?php
/**
 * CryptX General Settings Tab Template
 *
 * @var array $options
 * @var array $applyTo
 * @var array $decryptionType
 * @var array $javascriptLocation
 * @var string $excludedIds
 * @var bool $metaBox
 * @var bool $autolink
 * @var string $whiteList
 */

defined('ABSPATH') || exit;
?>

<h4><?php esc_html_e("General", 'cryptx'); ?></h4>
<table class="form-table" role="presentation">
    <!-- Apply CryptX Section -->
    <tr>
        <th scope="row"><?php _e("Apply CryptX to...", 'cryptx'); ?></th>
        <td>
            <?php foreach ($applyTo as $key => $setting): ?>
                <label>
                    <input type="checkbox"
                           name="cryptX_var[<?php echo esc_attr($key); ?>]"
                           value="1"
                        <?php checked($options[$key] ?? 0, 1); ?> />
                    <?php echo esc_html($setting['label']); ?>
                    <?php if (isset($setting['description'])): ?>
                        <small><?php echo esc_html($setting['description']); ?></small>
                    <?php endif; ?>
                </label><br/>
            <?php endforeach; ?>
        </td>
    </tr>

    <tr class="spacer">
        <td colspan="2"><hr></td>
    </tr>

    <!-- RSS Feed Section -->
    <tr>
        <th scope="row" colspan="2">
            <label>
                <input name="cryptX_var[disable_rss]"
                       type="checkbox"
                       value="1"
                    <?php checked($options['disable_rss'] ?? true, 1); ?> />
                <?php esc_html_e("Disable CryptX in RSS feeds", 'cryptx'); ?>
            </label>
            <p class="description">
                <?php esc_html_e("When enabled, email addresses in RSS feeds will not be encrypted.", 'cryptx'); ?>
            </p>
        </th>
    </tr>

    <tr class="spacer">
        <td colspan="2"><hr></td>
    </tr>

    <!-- Excluded IDs Section -->
    <tr>
        <th scope="row"><?php esc_html_e("Excluded ID's...", 'cryptx'); ?></th>
        <td>
            <input name="cryptX_var[excludedIDs]"
                   type="text"
                   value="<?php echo esc_attr($excludedIds); ?>"
                   class="regular-text" />
            <p class="description">
                <?php esc_html_e("Enter all Page/Post ID's to exclude from CryptX as comma separated list.", 'cryptx'); ?>
            </p>
            <label>
                <input name="cryptX_var[metaBox]"
                       type="checkbox"
                       value="1"
                    <?php checked($metaBox, 1); ?> />
                <?php esc_html_e("Enable the CryptX Widget on editing a post or page.", 'cryptx'); ?>
            </label>
        </td>
    </tr>

    <tr class="spacer">
        <td colspan="2"><hr></td>
    </tr>

    <!-- Decryption Type Section -->
    <tr>
        <th scope="row"><?php esc_html_e("Type of decryption", 'cryptx'); ?></th>
        <td>
            <?php foreach ($decryptionType as $type): ?>
                <label>
                    <input name="cryptX_var[java]"
                           type="radio"
                           value="<?php echo esc_attr($type['value']); ?>"
                           <?php checked($options['java'], $type['value']); ?> />
                    <?php echo esc_html($type['label']); ?>
                </label><br />
            <?php endforeach; ?>
        </td>
    </tr>

    <tr class="spacer">
        <td colspan="2"><hr></td>
    </tr>

    <!-- JavaScript Location Section -->
    <tr>
        <th scope="row"><?php esc_html_e("Where to load the needed javascript...", 'cryptx'); ?></th>
        <td>
            <?php foreach ($javascriptLocation as $location): ?>
                <label>
                    <input name="cryptX_var[load_java]"
                           type="radio"
                           value="<?php echo esc_attr($location['value']); ?>"
                        <?php //checked(get_option('load_java'), $location['value']); ?>
                        <?php checked($options['load_java'], $location['value']); ?> />
                    <?php echo wp_kses($location['label'], ['b' => []]); ?>
                </label><br />
            <?php endforeach; ?>
        </td>
    </tr>

    <tr class="spacer">
        <td colspan="2"><hr></td>
    </tr>

    <!-- Autolink Section -->
    <tr>
        <th scope="row" colspan="2">
            <label>
                <input name="cryptX_var[autolink]"
                       type="checkbox"
                       value="1"
                    <?php checked($autolink, 1); ?> />
                <?php esc_html_e("Add mailto to all unlinked email addresses", 'cryptx'); ?>
            </label>
        </th>
    </tr>

    <tr class="spacer">
        <td colspan="2"><hr></td>
    </tr>

    <!-- Whitelist Section -->
    <tr>
        <th scope="row"><?php esc_html_e("Whitelist of extensions", 'cryptx'); ?></th>
        <td>
            <input name="cryptX_var[whiteList]"
                   type="text"
                   value="<?php echo esc_attr($whiteList); ?>"
                   class="regular-text" />
            <p class="description">
                <?php echo wp_kses(
                    __("<strong>This is a workaround for the 'retina issue'.</strong><br/>You can provide a comma separated list of extensions like 'jpeg,jpg,png,gif' which will be ignored by CryptX.", 'cryptx'),
                    ['strong' => [], 'br' => []]
                ); ?>
            </p>
        </td>
    </tr>

    <tr class="spacer">
        <td colspan="2"><hr></td>
    </tr>

    <!-- Reset Options Section -->
    <tr>
        <th scope="row" colspan="2" class="warning">
            <label>
                <input name="cryptX_var_reset"
                       type="checkbox"
                       value="1" />
                <?php esc_html_e("Reset CryptX options to defaults. Use it carefully and at your own risk. All changes will be deleted!", 'cryptx'); ?>
            </label>
        </th>
    </tr>
</table>

<input type="hidden" name="cryptX_save_general_settings" value="true">
<?php submit_button(__('Save General Settings', 'cryptx'), 'primary', 'cryptX_save_general_settings'); ?>

