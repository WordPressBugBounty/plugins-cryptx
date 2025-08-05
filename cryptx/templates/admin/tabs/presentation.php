<?php
/**
 * CryptX Presentation Settings Tab Template
 * Variables extracted from PresentationSettingsTab:
 * - $css: CSS settings array
 * - $emailReplacements: Email replacement settings
 * - $linkTextOptions: Link text configuration options
 * - $selectedOption: Currently selected option value
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cryptx-tab-content cryptx-presentation-settings">
    <table class="form-table">

        <!-- CSS Settings Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php _e('CSS Settings', 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php _e("CSS-ID", 'cryptx'); ?></th>
            <td>
                <input name="cryptX_var[css_id]" id="css_id" value="<?php echo esc_attr($css['id']); ?>" type="text" class="regular-text" />
                <p class="description"><?php _e("Please be careful using this feature! IDs should be unique. You should prefer using a CSS class instead.", 'cryptx'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e("CSS-Class", 'cryptx'); ?></th>
            <td>
                <input name="cryptX_var[css_class]" id="css_class" value="<?php echo esc_attr($css['class']); ?>" type="text" class="regular-text" />
                <p class="description"><?php _e("Add custom CSS class to encrypted email links.", 'cryptx'); ?></p>
            </td>
        </tr>

        <!-- Link Text Options Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php _e('Link Text Options', 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php _e('Presentation Method', 'cryptx'); ?></th>
            <td>
                <fieldset>
                    <!-- Option 0: Replacement Text -->
                    <label>
                        <input name="cryptX_var[opt_linktext]" type="radio" value="0" <?php checked($selectedOption, 0); ?> />
                        <strong><?php _e("Show Email with text replacement", 'cryptx'); ?></strong>
                    </label>
                    <div style="margin-left: 25px; margin-top: 10px; margin-bottom: 20px;">
                        <table class="form-table" style="margin: 0;">
                            <?php foreach ($linkTextOptions['replacement']['fields'] as $field => $config): ?>
                                <tr>
                                    <th scope="row" style="padding-left: 0;"><?php echo $config['label']; ?></th>
                                    <td style="padding-left: 10px;">
                                        <input name="cryptX_var[<?php echo esc_attr($field); ?>]"
                                               value="<?php echo esc_attr($config['value']); ?>"
                                               type="text" class="regular-text" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <!-- Option 1: Custom Text -->
                    <label>
                        <input name="cryptX_var[opt_linktext]" type="radio" value="1" <?php checked($selectedOption, 1); ?> />
                        <strong><?php _e("Show custom text", 'cryptx'); ?></strong>
                    </label>
                    <div style="margin-left: 25px; margin-top: 10px; margin-bottom: 20px;">
                        <table class="form-table" style="margin: 0;">
                            <?php foreach ($linkTextOptions['customText']['fields'] as $field => $config): ?>
                                <tr>
                                    <th scope="row" style="padding-left: 0;"><?php echo $config['label']; ?></th>
                                    <td style="padding-left: 10px;">
                                        <input name="cryptX_var[<?php echo esc_attr($field); ?>]"
                                               value="<?php echo esc_attr($config['value']); ?>"
                                               type="text" class="regular-text" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <!-- Option 2: External Image -->
                    <label>
                        <input name="cryptX_var[opt_linktext]" type="radio" value="2" <?php checked($selectedOption, 2); ?> />
                        <strong><?php _e("Show external image", 'cryptx'); ?></strong>
                    </label>
                    <div style="margin-left: 25px; margin-top: 10px; margin-bottom: 20px;">
                        <table class="form-table" style="margin: 0;">
                            <?php foreach ($linkTextOptions['externalImage']['fields'] as $field => $config): ?>
                                <tr>
                                    <th scope="row" style="padding-left: 0;"><?php echo $config['label']; ?></th>
                                    <td style="padding-left: 10px;">
                                        <?php if ($field === 'alt_linkimage'): ?>
                                            <input name="cryptX_var[<?php echo esc_attr($field); ?>]"
                                                   value="<?php echo esc_attr($config['value']); ?>"
                                                   type="url" class="regular-text"
                                                   placeholder="https://example.com/image.png" />
                                        <?php else: ?>
                                            <input name="cryptX_var[<?php echo esc_attr($field); ?>]"
                                                   value="<?php echo esc_attr($config['value']); ?>"
                                                   type="text" class="regular-text" />
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <!-- Option 3: Uploaded Image -->
                    <label>
                        <input name="cryptX_var[opt_linktext]" type="radio" value="3" <?php checked($selectedOption, 3); ?> />
                        <strong><?php _e("Show uploaded image", 'cryptx'); ?></strong>
                    </label>
                    <div style="margin-left: 25px; margin-top: 10px; margin-bottom: 20px;">
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th scope="row" style="padding-left: 0;"><?php _e("Select Image", 'cryptx'); ?></th>
                                <td style="padding-left: 10px;">
                                    <input name="cryptX_var[alt_uploadedimage]" id="alt_uploadedimage"
                                           value="<?php echo esc_attr($linkTextOptions['uploadedImage']['fields']['alt_uploadedimage']['value']); ?>"
                                           type="hidden" />
                                    <button type="button" class="button" id="upload_image_button">
                                        <?php _e("Choose Image", 'cryptx'); ?>
                                    </button>
                                    <div id="image_preview" style="margin-top: 10px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding-left: 0;">
                                    <?php echo $linkTextOptions['uploadedImage']['fields']['alt_linkimage_title']['label']; ?>
                                </th>
                                <td style="padding-left: 10px;">
                                    <input name="cryptX_var[alt_linkimage_title]"
                                           value="<?php echo esc_attr($linkTextOptions['uploadedImage']['fields']['alt_linkimage_title']['value']); ?>"
                                           type="text" class="regular-text" />
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Option 4: Scrambled Text -->
                    <label>
                        <input name="cryptX_var[opt_linktext]" type="radio" value="4" <?php checked($selectedOption, 4); ?> />
                        <strong><?php echo $linkTextOptions['scrambled']['label']; ?></strong>
                    </label><br/><br/>

                    <!-- Option 5: PNG Image -->
                    <label>
                        <input name="cryptX_var[opt_linktext]" type="radio" value="5" <?php checked($selectedOption, 5); ?> />
                        <strong><?php echo $linkTextOptions['pngImage']['label']; ?></strong>
                    </label>
                    <div style="margin-left: 25px; margin-top: 10px; margin-bottom: 20px;">
                        <table class="form-table" style="margin: 0;">
                            <?php foreach ($linkTextOptions['pngImage']['fields'] as $field => $config): ?>
                                <tr>
                                    <th scope="row" style="padding-left: 0;"><?php echo $config['label']; ?></th>
                                    <td style="padding-left: 10px;">
                                        <?php if ($field === 'c2i_font'): ?>
                                            <select name="cryptX_var[<?php echo esc_attr($field); ?>]">
                                                <?php foreach ($config['options'] as $value => $label): ?>
                                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($config['value'], $value); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($field === 'c2i_fontRGB'): ?>
                                            <input name="cryptX_var[<?php echo esc_attr($field); ?>]"
                                                   id="c2i_fontRGB"
                                                   value="<?php echo esc_attr($config['value']); ?>"
                                                   type="text" class="color-field" />
                                        <?php elseif ($field === 'c2i_fontSize'): ?>
                                            <input name="cryptX_var[<?php echo esc_attr($field); ?>]"
                                                   value="<?php echo esc_attr($config['value']); ?>"
                                                   type="number" min="8" max="72" />
                                        <?php else: ?>
                                            <input name="cryptX_var[<?php echo esc_attr($field); ?>]"
                                                   value="<?php echo esc_attr($config['value']); ?>"
                                                   type="text" class="regular-text" />
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </fieldset>
            </td>
        </tr>
    </table>

    <!-- Submit Button -->
    <p class="submit">
        <input type="submit"
               name="cryptX_save_presentation_settings"
               class="button-primary"
               value="<?php _e('Save Changes', 'cryptx'); ?>" />
        <input type="submit"
               name="cryptX_var_reset"
               class="button-secondary"
               value="<?php _e('Reset to Defaults', 'cryptx'); ?>"
               onclick="return confirm('<?php _e('Are you sure you want to reset all presentation settings to defaults?', 'cryptx'); ?>');" />
    </p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize WordPress Color Picker
        if (typeof jQuery !== 'undefined' && jQuery.fn.wpColorPicker) {
            jQuery('.color-field').wpColorPicker();
        }

        // Handle image upload functionality
        const uploadButton = document.getElementById('upload_image_button');
        const imageField = document.getElementById('alt_uploadedimage');
        const imagePreview = document.getElementById('image_preview');

        if (uploadButton && typeof wp !== 'undefined' && wp.media) {
            let mediaUploader;

            uploadButton.addEventListener('click', function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: '<?php _e("Choose Image", "cryptx"); ?>',
                    button: {
                        text: '<?php _e("Choose Image", "cryptx"); ?>'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });

                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    imageField.value = attachment.id;

                    if (attachment.sizes && attachment.sizes.thumbnail) {
                        imagePreview.innerHTML = '<img src="' + attachment.sizes.thumbnail.url + '" style="max-width: 150px; height: auto;" />';
                    } else {
                        imagePreview.innerHTML = '<img src="' + attachment.url + '" style="max-width: 150px; height: auto;" />';
                    }
                });

                mediaUploader.open();
            });

            // Show current image if one is selected
            if (imageField.value && imageField.value !== '0') {
                wp.media.attachment(imageField.value).fetch().then(function(attachment) {
                    if (attachment.attributes.sizes && attachment.attributes.sizes.thumbnail) {
                        imagePreview.innerHTML = '<img src="' + attachment.attributes.sizes.thumbnail.url + '" style="max-width: 150px; height: auto;" />';
                    } else {
                        imagePreview.innerHTML = '<img src="' + attachment.attributes.url + '" style="max-width: 150px; height: auto;" />';
                    }
                });
            }
        }
    });
</script>