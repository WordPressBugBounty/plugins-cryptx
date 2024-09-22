<?php
/**
 * Don't load this file direct!
 */
if (!defined('ABSPATH')) {
    return ;
}

/**
 * load required code
 */
require_once(CRYPTX_DIR_PATH . 'include/admin_general.php');
require_once(CRYPTX_DIR_PATH . 'include/admin_presentation.php');
require_once(CRYPTX_DIR_PATH . 'include/admin_howto.php');
require_once(CRYPTX_DIR_PATH . 'include/admin_changelog.php');

/**
 * Add css to header
 */
function rw_cryptx_admin_css_script($hook) {
        // Load only on ?page=cryptx
        if($hook != 'settings_page_cryptx') {
                return;
        }
        wp_enqueue_style( 'cryptx-admin-css', CRYPTX_DIR_URL . 'css/admin.css' );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'cryptx-admin-js', CRYPTX_DIR_URL . 'js/cryptx-admin.min.js', array( 'jquery', 'wp-color-picker' ), false, true );
        wp_enqueue_media();

}
add_action( 'admin_enqueue_scripts', 'rw_cryptx_admin_css_script' );

/**
 * add links to plugin site
 */
function rw_cryptx_init_row_meta($links, $file) {
    if (CRYPTX_BASENAME == $file) {
        return array_merge(
            $links,
            array(
                sprintf(
                    '<a href="options-general.php?page=%s">%s</a>',
                    CRYPTX_BASEFOLDER,
                    __('Settings')
                )
            ),
            array(
                sprintf(
                    '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4026696">%s</a>',
                    __('Donate', 'cryptx')
                )
            )
        );
    }
    return $links;
}

/**
 * add CryptX to Option menu
 */
function rw_cryptx_menu() {
    add_submenu_page(
    	'options-general.php',
    	_x( 'CryptX', 'CryptX settings page', 'cryptx' ),
    	_x( 'CryptX', 'CryptX settings menu', 'cryptx' ),
    	'manage_options',
    	'cryptx',
    	'rw_cryptx_submenu'
    );
}
if (is_admin()) add_action('admin_menu', 'rw_cryptx_menu');

/**
 * save options
 */
function rw_cryptx_saveOptions(): void {
	if ( isValidPostRequest() ) {
		$CryptX_instance = Cryptx\CryptX::getInstance();
		$saveOptions = sanitizePostData();

		check_admin_referer( 'cryptX' );

		if ( isResetPost() ) {
			// $saveOptions = rw_Defaults();
			$saveOptions = $CryptX_instance->getCryptXOptionsDefaults();
		}

		if ( isSaveGeneralSettingsPost() ) {
			$saveOptions = parseGeneralSettings( $saveOptions );
		}

		// $saveOptions = wp_parse_args( $saveOptions, rw_loadCryptXOptionsWithDefaults() );
        $CryptX_instance->saveCryptXOptions($saveOptions);
		// $saveOptions = wp_parse_args( $saveOptions, $CryptX_instance->loadCryptXOptionsWithDefaults() );
		// update_option( 'cryptX', $saveOptions );
		// $cryptXOptions = rw_loadCryptXOptionsWithDefaults();

		displaySuccessMessage();
	}
}

function isValidPostRequest(): bool {
	if ( function_exists( 'current_user_can' ) === true && current_user_can( 'manage_options' ) === false ) {
		wp_die( "You don't have permission to access!" );
	}

	return ! empty( $_POST['cryptX_var'] );
}

function sanitizePostData() {
	return cryptx_sanitize_data( $_POST['cryptX_var'] );
}

function isResetPost(): bool {
	return isset( $_POST['cryptX_var_reset'] );
}

function isSaveGeneralSettingsPost(): bool {
	return isset( $_POST['cryptX_save_general_settings'] );
}

function parseGeneralSettings( $saveOptions ): array {
	$checkboxes = [
		'the_content'  => 0,
		'the_meta_key' => 0,
		'the_excerpt'  => 0,
		'comment_text' => 0,
		'widget_text'  => 0,
		'autolink'     => 0,
		'metaBox'      => 0,
	];

	return wp_parse_args( $saveOptions, $checkboxes );
}

function displaySuccessMessage(): void {
	echo "<div id='message' class='updated fade'><p><strong>";
	_e( 'Settings saved.' );
	echo "</strong></p></div>";
}

/**
 * sanitize given options
 */
function cryptx_sanitize_data( $data ) {
	$textFields = [
		'version',
		'at',
		'dot',
		'css_id',
		'css_class',
		'alt_linktext',
		'http_linkimage_title',
		'alt_linkimage_title',
		'excludedIDs',
		'alt_uploadedimage',
		'c2i_font',
		'c2i_fontRGB',
		'whiteList'
	];

	$intFields = [
		'the_content',
		'the_meta_key',
		'the_excerpt',
		'comment_text',
		'java',
		'load_java',
		'opt_linktext',
		'autolink',
		'c2i_fontSize',
		'echo'
	];

	$boolFields = [ 'metaBox' ];

	foreach ( $textFields as $field ) {
		if ( isset( $data[ $field ] ) ) {
			$data[ $field ] = sanitize_text_field( $data[ $field ] );
		}
	}

	foreach ( $intFields as $field ) {
		if ( isset( $data[ $field ] ) ) {
			$data[ $field ] = (int) $data[ $field ];
		}
	}

	foreach ( $boolFields as $field ) {
		if ( isset( $data[ $field ] ) ) {
			$data[ $field ] = (bool) $data[ $field ];
		}
	}

	return $data;
}

/**
 * print CryptX Option Page
 */
function rw_cryptx_submenu() {
	// global $cryptXOptions, $data, $rw_cryptx_active_tab;
	rw_cryptx_saveOptions();

	// Give meaningful name to tab variable
	rw_cryptx_generateHtml();
}

function rw_cryptx_generateHtml(): void {
	?>
    <div class="cryptx-option-page">
        <h1><?php _e("CryptX settings", 'cryptx'); ?></h1>
        <form method="post" action="">
			<?php wp_nonce_field('cryptX') ?>
            <h2 class="nav-tab-wrapper">
				<?php
				do_action('rw_cryptx_settings_tab');
				?>
            </h2>
            <div class="cryptx-tab-content-wrapper">
				<?php
				do_action('rw_cryptx_settings_content');
				?>
            </div><!-- /.cryptx-tab-content-wrapper -->
        </form>
    </div><!-- /.cryptx-option-page -->
	<?php
}

function rw_cryptx_getActiveTab() {
	$allowedTabs = ['general', 'presentation', 'howto', 'changelog'];
	$tab = $_GET['tab'] ?? 'general';
	return in_array($tab, $allowedTabs) ? $tab : 'general';
}
/**
 * Option page navigation
 */
function rw_cryptx_settings_tab_presentation(): void {
	//global $rw_cryptx_active_tab;
    ?>
	<a class="nav-tab <?php echo rw_cryptx_getActiveTab() == 'general' || '' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'options-general.php?page=' . CRYPTX_BASEFOLDER . '&tab=general' ); ?>">
    	<?php _e("General",'cryptx'); ?>
    </a>
	<a class="nav-tab <?php echo rw_cryptx_getActiveTab() == 'presentation' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'options-general.php?page=' . CRYPTX_BASEFOLDER . '&tab=presentation' ); ?>">
    	<?php _e("Presentation",'cryptx'); ?>
    </a>
	<a class="nav-tab <?php echo rw_cryptx_getActiveTab() == 'howto' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'options-general.php?page=' . CRYPTX_BASEFOLDER . '&tab=howto' ); ?>">
    	<?php _e("How to&hellip;",'cryptx'); ?>
    </a>
	<a class="nav-tab <?php echo rw_cryptx_getActiveTab() == 'changelog' ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'options-general.php?page=' . CRYPTX_BASEFOLDER . '&tab=changelog' ); ?>">
    	<?php _e("Changelog",'cryptx'); ?>
    </a>
	<?php
}
add_action( 'rw_cryptx_settings_tab', 'rw_cryptx_settings_tab_presentation');
