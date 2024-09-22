<?php
/**
 * CryptX option page "Presentation"-Tab
 */
function rw_cryptx_settings_tab_content_presentation() {
	$CryptX_instance = Cryptx\CryptX::getInstance();
	$cryptXOptions = $CryptX_instance->loadCryptXOptionsWithDefaults();
	if ( 'presentation' != rw_cryptx_getActiveTab() )
		return;
	?>

	<h4><?php _e("Define CSS Options",'cryptx'); ?></h4>

    <table>
        <tr>
            <th><label for="cryptX_var[css_id]"><?php _e("CSS ID",'cryptx'); ?></label></th>
            <td><input name="cryptX_var[css_id]" value="<?php echo $cryptXOptions['css_id']; ?>" type="text" class="regular-text" /><br /><?php _e("Please be careful using this feature! IDs should be unique. You should prefer of using a css class instead.",'cryptx'); ?></td>
        </tr>
        <tr>
            <th><label for="cryptX_var[css_class]"><?php _e("CSS Class",'cryptx'); ?></label></th>
            <td><input name="cryptX_var[css_class]" value="<?php echo $cryptXOptions['css_class']; ?>" type="text" class="regular-text" /></td>
        </tr>
    </table>

    <h4><?php _e("Define Presentation Options",'cryptx'); ?></h4>

    <table>
        <tbody>
            <tr>
                <td><input name="cryptX_var[opt_linktext]" type="radio" id="opt_linktext" value="0" <?php checked( $cryptXOptions['opt_linktext'], 0 ); ?> /></td>
                <th scope="row"><label for="cryptX_var[at]"><?php _e("Replacement for '@'",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[at]" value="<?php echo $cryptXOptions['at']; ?>" type="text" class="regular-text" /></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <th scope="row"><label for="cryptX_var[dot]"><?php _e("Replacement for '.'",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[dot]" value="<?php echo $cryptXOptions['dot']; ?>" type="text" class="regular-text" /></td>
            </tr>

            <tr class="spacer">
                <td colspan="3"><hr></td>
            </tr>

            <tr>
                <td scope="row"><input type="radio" name="cryptX_var[opt_linktext]" id="opt_linktext2" value="1" <?php checked( $cryptXOptions['opt_linktext'], 1 ); ?> /></td>
                <th><label for="cryptX_var[alt_linktext]"><?php _e("Text for link",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[alt_linktext]" value="<?php echo $cryptXOptions['alt_linktext']; ?>" type="text" class="regular-text" /></td>
            </tr>

            <tr class="spacer">
                <td colspan="3"><hr></td>
            </tr>

            <tr>
                <td scope="row"><input type="radio" name="cryptX_var[opt_linktext]" id="opt_linktext3" value="2" <?php checked( $cryptXOptions['opt_linktext'], 2 ); ?> /></td>
                <th><label for="cryptX_var[alt_linkimage]"><?php _e("Image-URL",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[alt_linkimage]" value="<?php echo $cryptXOptions['alt_linkimage']; ?>" type="text" class="regular-text" /></td>
            </tr>
            <tr>
                <td scope="row">&nbsp;</td>
                <th><label for="cryptX_var[http_linkimage_title]"><?php _e("Title-Tag for the Image",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[http_linkimage_title]" value="<?php echo $cryptXOptions['http_linkimage_title']; ?>" type="text" class="regular-text" /></td>
            </tr>

            <tr class="spacer">
                <td colspan="3"><hr></td>
            </tr>

            <tr>
                <td scope="row"><input type="radio" name="cryptX_var[opt_linktext]" id="opt_linktext4" value="3" <?php checked( $cryptXOptions['opt_linktext'], 3 ); ?>/></td>
                <th><label for="upload_image_button"><?php _e("Select an uploaded image",'cryptx'); ?></label></th>
                <td>
                    <input id="upload_image_button" type="button" class="button" value="<?php _e( 'Upload image' ); ?>" />
                    <input id="remove_image_button" type="button" class="button button-link-delete hidden" value="<?php _e( 'Delete image' ); ?>" />
                    <span id="opt_linktext4_notice"><?php _e("You have to upload an image first before this option can be activated.",'cryptx'); ?></span>
                    <input type='hidden' name='cryptX_var[alt_uploadedimage]' id='image_attachment_id' value='<?php echo $cryptXOptions['alt_uploadedimage']; ?>'>
                    <div>
                        <img id='image-preview' src='<?php echo wp_get_attachment_url( $cryptXOptions['alt_uploadedimage'] ); ?>'>
                    </div>
                </td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <th><label for="cryptX_var[alt_linkimage_title]"><?php _e("Title-Tag for the Image",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[alt_linkimage_title]" value="<?php echo $cryptXOptions['alt_linkimage_title']; ?>" type="text" class="regular-text" /></td>
            </tr>

            <tr class="spacer">
                <td colspan="3"><hr></td>
            </tr>

            <tr>
                <td scope="row"><input type="radio" name="cryptX_var[opt_linktext]" id="opt_linktext4" value="4" <?php checked( $cryptXOptions['opt_linktext'], 4 ); ?> /></td>
                <th colspan="2"><?php _e("Text scrambled by AntiSpamBot (<small>Try it and look at your site and check the html source!</small>)",'cryptx'); ?></th>
            </tr>

            <tr class="spacer">
                <td colspan="3"><hr></td>
            </tr>

            <tr>
                <td scope="row"><input type="radio" name="cryptX_var[opt_linktext]" id="opt_linktext5" value="5" <?php checked( $cryptXOptions['opt_linktext'], 5 ); ?> /></td>
                <th><?php _e("Convert Email to PNG-image",'cryptx'); ?></th>
                <td><?php _e("Example with the saved options: ",'cryptx'); ?> <img src="<?php echo get_bloginfo('url'); ?>/<?php echo md5( get_bloginfo('url') ); ?>/<?php echo antispambot("CryptX@".$CryptX_instance->getDomain()); ?>" align="absmiddle" alt="<?php echo antispambot("CryptX@".$CryptX_instance->getDomain()); ?>" title="<?php echo antispambot("CryptX@".$CryptX_instance->getDomain()); ?>"></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <th><label for="cryptX_var[c2i_font]"><?php _e("Font",'cryptx'); ?></label></th>
                <td><select name="cryptX_var[c2i_font]">
                    <?php
                        foreach($CryptX_instance->getFilesInDirectory( CRYPTX_DIR_PATH . 'fonts', ["ttf"]) as $font) {
                            printf('<option value="%1$s" %3$s>%2$s</option>',
                                    $font,
                                    str_replace(".ttf", "", $font),
                                    ($cryptXOptions['c2i_font'] == $font)? "selected" : ""
                                    );
                        }
                    ?>
                    </select></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <th><label for="cryptX_var[c2i_fontSize]"><?php _e("Font size (pixel)",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[c2i_fontSize]" value="<?php echo $cryptXOptions['c2i_fontSize']; ?>" type="number" class="regular-text" /></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <th><label for="cryptX_var[c2i_fontRGB]"><?php _e("Font color (RGB)",'cryptx'); ?></label></th>
                <td><input name="cryptX_var[c2i_fontRGB]" value="<?php echo $cryptXOptions['c2i_fontRGB']; ?>" type="text" class="color-field regular-text" /></td>
            </tr>
        </tbody>
    </table>
    <?php submit_button(); ?>
	<?php
}
add_action( 'rw_cryptx_settings_content', 'rw_cryptx_settings_tab_content_presentation' );