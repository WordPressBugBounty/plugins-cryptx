<?php
/**
 * Template for the CryptX presentation settings tab
 *
 * @var array $css CSS settings
 * @var array $linkTextOptions Link text display options
 * @var int $selectedOption Currently selected link text option
 */

defined('ABSPATH') || exit;
?>

    <h4><?php _e("Define CSS Options", 'cryptx'); ?></h4>
    <table class="form-table">
        <tr>
            <th><label for="cryptX_var[css_id]"><?php _e("CSS ID", 'cryptx'); ?></label></th>
            <td>
                <input name="cryptX_var[css_id]"
                       id="cryptX_var[css_id]"
                       type="text"
                       value="<?php echo esc_attr($css['id']); ?>"
                       class="regular-text" />
                <p class="description">
                    <?php _e("Please be careful using this feature! IDs should be unique. You should prefer using a css class instead.", 'cryptx'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th><label for="cryptX_var[css_class]"><?php _e("CSS Class", 'cryptx'); ?></label></th>
            <td>
                <input name="cryptX_var[css_class]"
                       id="cryptX_var[css_class]"
                       type="text"
                       value="<?php echo esc_attr($css['class']); ?>"
                       class="regular-text" />
            </td>
        </tr>
    </table>

    <h4><?php _e("Define Presentation Options", 'cryptx'); ?></h4>
    <table class="form-table">
        <tbody>
        <!-- Character Replacement Option -->
        <tr>
            <td>
                <input type="radio"
                       name="cryptX_var[opt_linktext]"
                       id="opt_linktext_0"
                       value="<?php echo esc_attr($linkTextOptions['replacement']['value']); ?>"
                    <?php checked($selectedOption, $linkTextOptions['replacement']['value']); ?> />
            </td>
            <th scope="row">
                <?php foreach ($linkTextOptions['replacement']['fields'] as $key => $field): ?>
                    <div class="cryptx-field-row">
                        <label for="cryptX_var[<?php echo esc_attr($key); ?>]">
                            <?php echo esc_html($field['label']); ?>
                        </label>
                        <input type="text"
                               name="cryptX_var[<?php echo esc_attr($key); ?>]"
                               id="cryptX_var[<?php echo esc_attr($key); ?>]"
                               value="<?php echo esc_attr($field['value']); ?>"
                               class="regular-text" />
                    </div>
                <?php endforeach; ?>
            </th>
        </tr>

        <tr class="spacer"><td colspan="3"><hr></td></tr>

        <!-- Custom Text Option -->
        <tr>
            <td>
                <input type="radio"
                       name="cryptX_var[opt_linktext]"
                       id="opt_linktext_1"
                       value="<?php echo esc_attr($linkTextOptions['customText']['value']); ?>"
                    <?php checked($selectedOption, $linkTextOptions['customText']['value']); ?> />
            </td>
            <th>
                <?php foreach ($linkTextOptions['customText']['fields'] as $key => $field): ?>
                    <label for="cryptX_var[<?php echo esc_attr($key); ?>]">
                        <?php echo esc_html($field['label']); ?>
                    </label>
                    <input type="text"
                           name="cryptX_var[<?php echo esc_attr($key); ?>]"
                           id="cryptX_var[<?php echo esc_attr($key); ?>]"
                           value="<?php echo esc_attr($field['value']); ?>"
                           class="regular-text" />
                <?php endforeach; ?>
            </th>
        </tr>

        <tr class="spacer"><td colspan="3"><hr></td></tr>

        <!-- External Image Option -->
        <tr>
            <td>
                <input type="radio"
                       name="cryptX_var[opt_linktext]"
                       id="opt_linktext_2"
                       value="<?php echo esc_attr($linkTextOptions['externalImage']['value']); ?>"
                    <?php checked($selectedOption, $linkTextOptions['externalImage']['value']); ?> />
            </td>
            <th>
                <?php foreach ($linkTextOptions['externalImage']['fields'] as $key => $field): ?>
                    <div class="cryptx-field-row">
                        <label for="cryptX_var[<?php echo esc_attr($key); ?>]">
                            <?php echo esc_html($field['label']); ?>
                        </label>
                        <input type="text"
                               name="cryptX_var[<?php echo esc_attr($key); ?>]"
                               id="cryptX_var[<?php echo esc_attr($key); ?>]"
                               value="<?php echo esc_attr($field['value']); ?>"
                               class="regular-text" />
                    </div>
                <?php endforeach; ?>
            </th>
        </tr>

        <tr class="spacer"><td colspan="3"><hr></td></tr>

        <!-- Uploaded Image Option -->
        <tr>
            <td>
                <input type="radio"
                       name="cryptX_var[opt_linktext]"
                       id="opt_linktext_3"
                       value="<?php echo esc_attr($linkTextOptions['uploadedImage']['value']); ?>"
                    <?php checked($selectedOption, $linkTextOptions['uploadedImage']['value']); ?> />
            </td>
            <th>
                <label for="upload_image_button"><?php _e("Select an uploaded image", 'cryptx'); ?></label>
            </th>
            <td>
                <input id="upload_image_button" type="button" class="button" value="<?php esc_attr_e('Upload image'); ?>" />
                <input id="remove_image_button" type="button" class="button button-link-delete hidden" value="<?php esc_attr_e('Delete image'); ?>" />
                <span id="opt_linktext4_notice"><?php _e("You have to upload an image first before this option can be activated.", 'cryptx'); ?></span>
                <input type="hidden"
                       name="cryptX_var[alt_uploadedimage]"
                       id="image_attachment_id"
                       value="<?php echo esc_attr($linkTextOptions['uploadedImage']['fields']['alt_uploadedimage']['value']); ?>">
                <div>
                    <img id="image-preview" src="<?php echo esc_url(wp_get_attachment_url($linkTextOptions['uploadedImage']['fields']['alt_uploadedimage']['value'])); ?>">
                </div>
                <label for="cryptX_var[alt_linkimage_title]">
                    <?php echo esc_html($linkTextOptions['uploadedImage']['fields']['alt_linkimage_title']['label']); ?>
                </label>
                <input type="text"
                       name="cryptX_var[alt_linkimage_title]"
                       value="<?php echo esc_attr($linkTextOptions['uploadedImage']['fields']['alt_linkimage_title']['value']); ?>"
                       class="regular-text" />
            </td>
        </tr>

        <tr class="spacer"><td colspan="3"><hr></td></tr>

        <!-- Text Scrambling Option -->
        <tr>
            <td>
                <input type="radio"
                       name="cryptX_var[opt_linktext]"
                       id="opt_linktext_4"
                       value="<?php echo esc_attr($linkTextOptions['scrambled']['value']); ?>"
                    <?php checked($selectedOption, $linkTextOptions['scrambled']['value']); ?> />
            </td>
            <th colspan="2">
                <?php echo esc_html($linkTextOptions['scrambled']['label']); ?>
                <small><?php _e("Try it and look at your site and check the html source!", 'cryptx'); ?></small>
            </th>
        </tr>

        <tr class="spacer"><td colspan="3"><hr></td></tr>

        <!-- PNG Image Conversion Option -->
        <tr>
            <td>
                <input type="radio"
                       name="cryptX_var[opt_linktext]"
                       id="opt_linktext_5"
                       value="<?php echo esc_attr($linkTextOptions['pngImage']['value']); ?>"
                    <?php checked($selectedOption, $linkTextOptions['pngImage']['value']); ?> />
            </td>
            <th><?php echo esc_html($linkTextOptions['pngImage']['label']); ?></th>
            <td>
                <?php foreach ($linkTextOptions['pngImage']['fields'] as $key => $field): ?>
                    <div class="cryptx-field-row">
                        <label for="cryptX_var[<?php echo esc_attr($key); ?>]">
                            <?php echo esc_html($field['label']); ?>
                        </label>
                        <?php if ($key === 'c2i_font'): ?>
                            <select name="cryptX_var[<?php echo esc_attr($key); ?>]"
                                    id="cryptX_var[<?php echo esc_attr($key); ?>]">
                                <?php foreach ($field['options'] as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>"
                                        <?php selected($field['value'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($key === 'c2i_fontSize'): ?>
                            <input type="number"
                                   name="cryptX_var[<?php echo esc_attr($key); ?>]"
                                   id="cryptX_var[<?php echo esc_attr($key); ?>]"
                                   value="<?php echo esc_attr($field['value']); ?>"
                                   class="small-text" />
                        <?php else: ?>
                            <input type="text"
                                   name="cryptX_var[<?php echo esc_attr($key); ?>]"
                                   id="cryptX_var[<?php echo esc_attr($key); ?>]"
                                   value="<?php echo esc_attr($field['value']); ?>"
                                   class="color-field" />
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </td>
        </tr>
        </tbody>
    </table>

<?php submit_button(__('Save Presentation Settings', 'cryptx'), 'primary', 'cryptX_save_presentation_settings'); ?>
