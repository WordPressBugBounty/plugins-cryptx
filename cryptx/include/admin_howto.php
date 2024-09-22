<?php
/**
 * CryptX option page "How to"-Tab
 */
function rw_cryptx_settings_tab_content_howto() {
	if ( 'howto' != rw_cryptx_getActiveTab() )
		return;
	?>

	<h4><?php _e( "How to use CryptX in your Template",'cryptx' ); ?></h4>
    <table class="form-table">
        <tr>
            <td><?php _e("In your Template you can use the following function to encrypt a email address:",'cryptx'); ?><br>
            <pre>
&lt;?php
    $content = "name@example.com";
    // with this values the link text will be replaced by 'example', otherwise set $args = array();
    $args = array( 'alt_linktext' => 'example', 'opt_linktext' => 1 );
    echo (function_exists('encryptx'))? encryptx($content, $args) : $content;
?&gt;
            </pre>
            </td>
        </tr>
    </table>
	<?php
}
add_action( 'rw_cryptx_settings_content', 'rw_cryptx_settings_tab_content_howto' );