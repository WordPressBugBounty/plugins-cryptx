<?php

namespace CryptX;

Final class CryptX {

	const NOT_FOUND = false;
	const MAIL_IDENTIFIER = 'mailto:';
	const SUBJECT_IDENTIFIER = "?subject=";
	const INDEX_TO_CHECK = 4;
	const PATTERN = '/(.*)(">)/i';
	const ASCII_VALUES_BLACKLIST = [ '32', '34', '39', '60', '62', '63', '92', '94', '96', '127' ];

	private static ?CryptX $_instance = null;
	private static array $cryptXOptions = [];
	private static array $defaults = array(
		'version'              => null,
		'at'                   => ' [at] ',
		'dot'                  => ' [dot] ',
		'css_id'               => '',
		'css_class'            => '',
		'the_content'          => 1,
		'the_meta_key'         => 1,
		'the_excerpt'          => 1,
		'comment_text'         => 1,
		'widget_text'          => 1,
		'java'                 => 1,
		'load_java'            => 1,
		'opt_linktext'         => 0,
		'autolink'             => 1,
		'alt_linktext'         => '',
		'alt_linkimage'        => '',
		'http_linkimage_title' => '',
		'alt_linkimage_title'  => '',
		'excludedIDs'          => '',
		'metaBox'              => 1,
		'alt_uploadedimage'    => '0',
		'c2i_font'             => null,
		'c2i_fontSize'         => 10,
		'c2i_fontRGB'          => '#000000',
		'echo'                 => 1,
		'filter'               => array( 'the_content', 'the_meta_key', 'the_excerpt', 'comment_text', 'widget_text' ),
		'whiteList'            => 'jpeg,jpg,png,gif',
	);
	private static int $imageCounter = 0;

	private function __construct() {
		self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults();
	}

	/**
	 * Get the instance of the CryptX class.
	 *
	 * This method returns the instance of the CryptX class. If an instance does not exist,
	 * it creates a new instance of the CryptX class and stores it in the static property.
	 * Subsequent calls to this method will return the previously created instance.
	 *
	 * @return CryptX The instance of the CryptX class.
	 */
	public static function getInstance(): CryptX {
		if ( ! ( self::$_instance instanceof self ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Starts the CryptX plugin.
	 *
	 * This method initializes and configures the CryptX plugin by performing the following actions:
	 * - Updates CryptX settings if a new version is available
	 * - Adds plugin filters based on the configured options
	 * - Adds action hooks for plugin activation, enqueueing JavaScript files, and handling meta box functionality
	 * - Adds a plugin row meta filter
	 * - Adds a filter for generating tiny URLs
	 * - Adds a shortcode for CryptX functionality
	 *
	 * @return void
	 */
	public function startCryptX(): void {
		if ( isset( self::$cryptXOptions['version'] ) && version_compare( CRYPTX_VERSION, self::$cryptXOptions['version'] ) > 0 ) {
			$this->updateCryptXSettings();
		}
		foreach ( self::$cryptXOptions['filter'] as $filter ) {
			if ( @self::$cryptXOptions[ $filter ] ) {
				$this->addPluginFilters( $filter );
			}
		}
		add_action( 'activate_' . CRYPTX_BASENAME, [ $this, 'installCryptX' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'loadJavascriptFiles' ] );
		if ( @self::$cryptXOptions['metaBox'] ) {
			add_action( 'admin_menu', [ $this, 'metaBox' ] );
			add_action( 'wp_insert_post', [ $this, 'addPostIdToExcludedList' ] );
			add_action( 'wp_update_post', [ $this, 'addPostIdToExcludedList' ] );
		}
		add_filter( 'plugin_row_meta', 'rw_cryptx_init_row_meta', 10, 2 );
		add_filter( 'init', [ $this, 'cryptXtinyUrl' ] );
		add_shortcode( 'cryptx', [ $this, 'cryptXShortcode' ] );
	}

	/**
	 * Returns an array of default options for CryptX.
	 *
	 * This function retrieves an array of default options for CryptX. The default options include
	 * the current version of CryptX and the first available TrueType font from the "fonts" directory.
	 *
	 * @return array The array of default options.
	 */
	public function getCryptXOptionsDefaults(): array {
		$firstFont = $this->getFilesInDirectory( CRYPTX_DIR_PATH . 'fonts', [ "ttf" ] );

		return array_merge( self::$defaults, [ 'version' => CRYPTX_VERSION, 'c2i_font' => $firstFont[0] ] );
	}

	/**
	 * Loads the cryptX options with default values.
	 *
	 * @return array The cryptX options array with default values.
	 */
	public function loadCryptXOptionsWithDefaults(): array {
		$defaultValues  = $this->getCryptXOptionsDefaults();
		$currentOptions = get_option( 'cryptX' );

		return wp_parse_args( $currentOptions, $defaultValues );
	}

	/**
	 * Saves the cryptX options by updating the 'cryptX' option with the saved options merged with the default options.
	 *
	 * @param array $saveOptions The options to be saved.
	 *
	 * @return void
	 */
	public function saveCryptXOptions( array $saveOptions ): void {
		update_option( 'cryptX', wp_parse_args( $saveOptions, $this->loadCryptXOptionsWithDefaults() ) );
	}

	/**
	 * Generates a shortcode for encrypting email addresses in search results.
	 *
	 * @param array $atts An associative array of attributes for the shortcode.
	 * @param string $content The content inside the shortcode.
	 * @param string $tag The shortcode tag.
	 *
	 * @return string The encrypted search results content.
	 */
	public function cryptXShortcode( array $atts = [], string $content = '', string $tag = '' ): string {
		if ( isset( $atts['encoded'] ) && $atts['encoded'] == "true" ) {
			foreach ( $atts as $key => $value ) {
				$atts[ $key ] = $this->decodeString( $value );
			}
			unset( $atts['encoded'] );
		}
        if(!empty($atts)) self::$cryptXOptions = shortcode_atts( $this->loadCryptXOptionsWithDefaults(), array_change_key_case( $atts, CASE_LOWER ), $tag );
		if ( @self::$cryptXOptions['autolink'] ) {
			$content = $this->addLinkToEmailAddresses( $content, true );
		}
		$content             = $this->encryptAndLinkContent( $content, true );
        // reset CryptX options
		self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults();

		return $content;
	}

	/**
	 * Encrypts and links content.
	 *
	 * @param string $content The content to be encrypted and linked.
	 *
	 * @return string The encrypted and linked content.
	 */
	private function encryptAndLinkContent( string $content, bool $shortcode = false ): string {
		$content = $this->findEmailAddressesInContent( $content, $shortcode );

		return $this->replaceEmailInContent( $content, $shortcode );
	}

	/**
	 * Generates and returns a tiny URL image.
	 *
	 * @return void
	 */
	public function cryptXtinyUrl(): void {
		$url    = $_SERVER['REQUEST_URI'];
		$params = explode( '/', $url );
		if ( count( $params ) > 1 ) {
			$tiny_url = $params[ count( $params ) - 2 ];
			if ( $tiny_url == md5( get_bloginfo( 'url' ) ) ) {
				$font        = CRYPTX_DIR_PATH . 'fonts/' . self::$cryptXOptions['c2i_font'];
				$msg         = $params[ count( $params ) - 1 ];
				$size        = self::$cryptXOptions['c2i_fontSize'];
				$pad         = 1;
				$transparent = 1;
				$rgb         = str_replace( "#", "", self::$cryptXOptions['c2i_fontRGB'] );
				$red         = hexdec( substr( $rgb, 0, 2 ) );
				$grn         = hexdec( substr( $rgb, 2, 2 ) );
				$blu         = hexdec( substr( $rgb, 4, 2 ) );
				$bg_red      = 255 - $red;
				$bg_grn      = 255 - $grn;
				$bg_blu      = 255 - $blu;
				$width       = 0;
				$height      = 0;
				$offset_x    = 0;
				$offset_y    = 0;
				$bounds      = array();
				$image       = "";
				$bounds      = ImageTTFBBox( $size, 0, $font, "W" );
				$font_height = abs( $bounds[7] - $bounds[1] );
				$bounds      = ImageTTFBBox( $size, 0, $font, $msg );
				$width       = abs( $bounds[4] - $bounds[6] );
				$height      = abs( $bounds[7] - $bounds[1] );
				$offset_y    = $font_height + abs( ( $height - $font_height ) / 2 ) - 1;
				$offset_x    = 0;
				$image       = imagecreatetruecolor( $width + ( $pad * 2 ), $height + ( $pad * 2 ) );
				imagesavealpha( $image, true );
				$foreground = ImageColorAllocate( $image, $red, $grn, $blu );
				$background = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
				imagefill( $image, 0, 0, $background );
				ImageTTFText( $image, $size, 0, round( $offset_x + $pad, 0 ), round( $offset_y + $pad, 0 ), $foreground, $font, $msg );
				Header( "Content-type: image/png" );
				imagePNG( $image );
				die;
			}
		}
	}

	/**
	 * Add plugin filters.
	 *
	 * This function adds the specified plugin filter if the 'autolink' key is present and its value is true in the global $cryptXOptions variable.
	 * It also adds the 'autolink' function as a filter to the $filterName if the global $shortcode_tags variable is not empty.
	 * Additionally, this function calls the addCommonFilters() and addOtherFilters() functions at specific points.
	 *
	 * @param string $filterName The name of the filter to add.
	 *
	 * @return void
	 */
	private function addPluginFilters( string $filterName ): void {
		global $shortcode_tags;
		if ( array_key_exists( 'autolink', self::$cryptXOptions ) && self::$cryptXOptions['autolink'] ) {
			$this->addAutoLinkFilters( $filterName );
			if ( ! empty( $shortcode_tags ) ) {
				$this->addAutoLinkFilters( $filterName, 11 );
				//add_filter($filterName, [$this,'autolink'], 11);
			}
		}
		$this->addOtherFilters( $filterName );
	}

	/**
	 * Adds common filters to a given filter name.
	 *
	 * This function adds the common filter 'autolink' to the provided $filterName.
	 *
	 * @param string $filterName The name of the filter to add common filters to.
	 *
	 * @return void
	 */
	private function addAutoLinkFilters( string $filterName, $prio = 5 ): void {
		add_filter( $filterName, [ $this, 'addLinkToEmailAddresses' ], $prio );
	}

	/**
	 * Adds additional filters to a given filter name.
	 *
	 * This function adds two additional filters, 'encryptx' and 'replaceEmailInContent',
	 * to the specified filter name. The 'encryptx' filter is added with a priority of 12,
	 * and the 'replaceEmailInContent' filter is added with a priority of 13.
	 *
	 * @param string $filterName The name of the filter to add the additional filters to.
	 *
	 * @return void
	 */
	private function addOtherFilters( string $filterName ): void {
		add_filter( $filterName, [ $this, 'findEmailAddressesInContent' ], 12 );
		add_filter( $filterName, [ $this, 'replaceEmailInContent' ], 13 );
	}

	/**
	 * Checks if a given ID is excluded based on the 'excludedIDs' variable.
	 *
	 * @param int $ID The ID to check if excluded.
	 *
	 * @return bool Returns true if the ID is excluded, false otherwise.
	 */
	private function isIdExcluded( int $ID ): bool {
		$excludedIds = explode( ",", self::$cryptXOptions['excludedIDs'] );

		return in_array( $ID, $excludedIds );
	}

	/**
	 * Replaces email addresses in content with link texts.
	 *
	 * @param string|null $content The content to replace the email addresses in.
	 * @param bool $isShortcode Flag indicating whether the method is called from a shortcode.
	 *
	 * @return string|null The content with replaced email addresses.
	 */
	public function replaceEmailInContent( ?string $content, bool $isShortcode = false ): ?string {
		global $post;
		$postId = ( is_object( $post ) ) ? $post->ID : - 1;
		if (( ! $this->isIdExcluded( $postId ) || $isShortcode ) && !empty($content) ) {
			$content = $this->replaceEmailWithLinkText( $content );
		}

		return $content;
	}

	/**
	 * Replace email addresses in a given content with link text.
	 *
	 * @param string $content The content to search for email addresses.
	 *
	 * @return string The content with email addresses replaced with link text.
	 */
	private function replaceEmailWithLinkText( string $content ): string {
		$emailPattern = "/([_a-zA-Z0-9-+]+(\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,}))/i";

		return preg_replace_callback( $emailPattern, [ $this, 'encodeEmailToLinkText' ], $content );
	}

	/**
	 * Encode email address to link text.
	 *
	 * @param array $Match The matched email address.
	 *
	 * @return string The encoded link text.
	 */
	private function encodeEmailToLinkText( array $Match ): string {
		if ( $this->inWhiteList( $Match ) ) {
			return $Match[1];
		}
		switch ( self::$cryptXOptions['opt_linktext'] ) {
			case 1:
				$text = $this->getLinkText();
				break;
			case 2:
				$text = $this->getLinkImage();
				break;
			case 3:
				$img_url = wp_get_attachment_url( self::$cryptXOptions['alt_uploadedimage'] );
				$text    = $this->getUploadedImage( $img_url );
				self::$imageCounter ++;
				break;
			case 4:
				$text = antispambot( $Match[1] );
				break;
			case 5:
				$text = $this->getImageFromText( $Match );
				self::$imageCounter ++;
				break;
			default:
				$text = $this->getDefaultLinkText( $Match );
		}

		return $text;
	}

	/**
	 * Check if the given match is in the whitelist.
	 *
	 * @param array $Match The match to check against the whitelist.
	 *
	 * @return bool True if the match is in the whitelist, false otherwise.
	 */
	private function inWhiteList( array $Match ): bool {
		$whiteList = array_filter( array_map( 'trim', explode( ",", self::$cryptXOptions['whiteList'] ) ) );
		$tmp       = explode( ".", $Match[0] );

		return in_array( end( $tmp ), $whiteList );
	}

	/**
	 * Get the link text from cryptXOptions
	 *
	 * @return string The link text
	 */
	private function getLinkText(): string {
		return self::$cryptXOptions['alt_linktext'];
	}

	/**
	 * Generate an HTML image tag with the link image URL as the source
	 *
	 * @return string The HTML image tag
	 */
	private function getLinkImage(): string {
		return "<img src=\"" . self::$cryptXOptions['alt_linkimage'] . "\" class=\"cryptxImage\" alt=\"" . self::$cryptXOptions['alt_linkimage_title'] . "\" title=\"" . antispambot( self::$cryptXOptions['alt_linkimage_title'] ) . "\" />";
	}

	/**
	 * Get the HTML tag for an uploaded image.
	 *
	 * @param string $img_url The URL of the image.
	 *
	 * @return string The HTML tag for the image.
	 */
	private function getUploadedImage( string $img_url ): string {
		return "<img src=\"" . $img_url . "\" class=\"cryptxImage cryptxImage_" . self::$imageCounter . "\" alt=\"" . self::$cryptXOptions['http_linkimage_title'] . " title=\"" . antispambot( self::$cryptXOptions['http_linkimage_title'] ) . "\" />";
	}

	/**
	 * Converts a matched image URL into an HTML image element with cryptX classes and attributes.
	 *
	 * @param array $Match The matched image URL and other related data.
	 *
	 * @return string Returns the HTML image element.
	 */
	private function getImageFromText( array $Match ): string {
		return "<img src=\"" . get_bloginfo( 'url' ) . "/" . md5( get_bloginfo( 'url' ) ) . "/" . antispambot( $Match[1] ) . "\" class=\"cryptxImage cryptxImage_" . self::$imageCounter . "\" alt=\"" . antispambot( $Match[1] ) . "\" title=\"" . antispambot( $Match[1] ) . "\" />";
	}

	/**
	 * Replaces specific characters with values from cryptX options in a given string.
	 *
	 * @param array $Match The array containing matches from a regular expression search.
	 *                     Array format: `[0 => string, 1 => string, ...]`.
	 *                     The first element is ignored, and the second element is used as input string.
	 *
	 * @return string The string with replaced characters or the original array if no matches were found.
	 *                     If the input string is an array, the function returns an array with replaced characters
	 *                     for each element.
	 */
	private function getDefaultLinkText( array $Match ): string {
		$text = str_replace( "@", self::$cryptXOptions['at'], $Match[1] );

		return str_replace( ".", self::$cryptXOptions['dot'], $text );
	}

	/**
	 * List all files in a directory that match the given filter.
	 *
	 * @param string $path The path of the directory to list files from.
	 * @param array $filter The file extensions to filter by.
	 *                            If it's a string, it will be converted to an array of a single element.
	 *
	 * @return array An array of file names that match the filter.
	 */
	public function getFilesInDirectory( string $path, array $filter ): array {
		$directoryHandle  = opendir( $path );
		$directoryContent = array();
		while ( $file = readdir( $directoryHandle ) ) {
			$fileExtension = substr( strtolower( $file ), - 3 );
			if ( in_array( $fileExtension, $filter ) ) {
				$directoryContent[] = $file;
			}
		}

		return $directoryContent;
	}

	/**
	 * Finds and encrypts email addresses in content.
	 *
	 * @param string|null $content The content where email addresses will be searched and encrypted.
	 * @param bool $shortcode Specifies whether shortcodes should be processed or not. Default is false.
	 *
	 * @return string|null The content with encrypted email addresses, or null if $content is null.
	 */
	public function findEmailAddressesInContent( ?string $content, bool $shortcode = false ): ?string {
		global $post;

		if ( $content === null ) {
			return null;
		}

		$postId = ( is_object( $post ) ) ? $post->ID : - 1;

		$isIdExcluded = $this->isIdExcluded( $postId );
		$mailtoRegex  = '/<a (.*?)(href=("|\')mailto:(.*?)("|\')(.*?)|)>\s*(.*?)\s*<\/a>/i';

		if ( ( ! $isIdExcluded || $shortcode !== null ) ) {
			$content = preg_replace_callback( $mailtoRegex, [ $this, 'encryptEmailAddress' ], $content );
		}

		return $content;
	}

	/**
	 * Encrypts email addresses in search results.
	 *
	 * @param array $searchResults The search results containing email addresses.
	 *
	 * @return string The search results with encrypted email addresses.
	 */
	private function encryptEmailAddress( array $searchResults ): string {
		$originalValue = $searchResults[0];

		if ( strpos( $searchResults[ self::INDEX_TO_CHECK ], '@' ) === self::NOT_FOUND ) {
			return $originalValue;
		}

		$mailReference = self::MAIL_IDENTIFIER . $searchResults[ self::INDEX_TO_CHECK ];

		if ( str_starts_with( $searchResults[ self::INDEX_TO_CHECK ], self::SUBJECT_IDENTIFIER ) ) {
			return $originalValue;
		}

		$return = $originalValue;
		if ( ! empty( self::$cryptXOptions['java'] ) ) {
			$javaHandler = "javascript:DeCryptX('" . $this->generateHashFromString( $searchResults[ self::INDEX_TO_CHECK ] ) . "')";
			$return      = str_replace( self::MAIL_IDENTIFIER . $searchResults[ self::INDEX_TO_CHECK ], $javaHandler, $originalValue );
		}

		$return = str_replace( $mailReference, antispambot( $mailReference ), $return );

		if ( ! empty( self::$cryptXOptions['css_id'] ) ) {
			$return = preg_replace( self::PATTERN, '$1" id="' . self::$cryptXOptions['css_id'] . '">', $return );
		}

		if ( ! empty( self::$cryptXOptions['css_class'] ) ) {
			$return = preg_replace( self::PATTERN, '$1" class="' . self::$cryptXOptions['css_class'] . '">', $return );
		}

		return $return;
	}


	/**
	 * Generate a hash string for the given input string.
	 *
	 * @param string $inputString The input string to generate a hash for.
	 *
	 * @return string The generated hash string.
	 */
	private function generateHashFromString( string $inputString ): string {
		$inputString = str_replace( "&", "&", $inputString );
		$crypt       = '';

		for ( $i = 0; $i < strlen( $inputString ); $i ++ ) {
			do {
				$salt       = mt_rand( 0, 3 );
				$asciiValue = ord( substr( $inputString, $i ) ) + $salt;
				if ( 8364 <= $asciiValue ) {
					$asciiValue = 128;
				}
			} while ( in_array( $asciiValue, self::ASCII_VALUES_BLACKLIST ) );

			$crypt .= $salt . chr( $asciiValue );
		}

		return $crypt;
	}
	/**
	 *  add link to email addresses
	 */
	/**
	 * Auto-link emails in the given content.
	 *
	 * @param string $content The content to process.
	 * @param bool $shortcode Whether the function is called from a shortcode or not.
	 *
	 * @return string The content with emails auto-linked.
	 */
	public function addLinkToEmailAddresses( string $content, bool $shortcode = false ): string {
		global $post;
		$postID = is_object( $post ) ? $post->ID : - 1;

		if ( $this->isIdExcluded( $postID ) && ! $shortcode ) {
			return $content;
		}

		$emailPattern = "[_a-zA-Z0-9-+]+(\.[_a-zA-Z0-9-+]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})";
		$linkPattern  = "<a href=\"mailto:\\2\">\\2</a>";
		$src          = [
			"/([\s])($emailPattern)/si",
			"/(>)($emailPattern)(<)/si",
			"/(\()($emailPattern)(\))/si",
			"/(>)($emailPattern)([\s])/si",
			"/([\s])($emailPattern)(<)/si",
			"/^($emailPattern)/si",
			"/(<a[^>]*>)<a[^>]*>/",
			"/(<\/A>)<\/A>/i"
		];
		$tar          = [
			"\\1$linkPattern",
			"\\1$linkPattern\\6",
			"\\1$linkPattern\\6",
			"\\1$linkPattern\\6",
			"\\1$linkPattern\\6",
			"<a href=\"mailto:\\0\">\\0</a>",
			"\\1",
			"\\1"
		];

		return preg_replace( $src, $tar, $content );
	}

	/**
	 * Installs the CryptX plugin by updating its options and loading default values.
	 */
	public function installCryptX(): void {
		global $wpdb;
		self::$cryptXOptions['admin_notices_deprecated'] = true;
		if ( self::$cryptXOptions['excludedIDs'] == "" ) {
			$tmp      = array();
			$excludes = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'cryptxoff' AND meta_value = 'true'" );
			if ( count( $excludes ) > 0 ) {
				foreach ( $excludes as $exclude ) {
					$tmp[] = $exclude->post_id;
				}
				sort( $tmp );
				self::$cryptXOptions['excludedIDs'] = implode( ",", $tmp );
				update_option( 'cryptX', self::$cryptXOptions );
				self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults(); // reread Options
				$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = 'cryptxoff'" );
			}
		}
		if ( empty( self::$cryptXOptions['c2i_font'] ) ) {
			self::$cryptXOptions['c2i_font'] = CRYPTX_DIR_PATH . 'fonts/' . $firstFont[0];
		}
		if ( empty( self::$cryptXOptions['c2i_fontSize'] ) ) {
			self::$cryptXOptions['c2i_fontSize'] = 10;
		}
		if ( empty( self::$cryptXOptions['c2i_fontRGB'] ) ) {
			self::$cryptXOptions['c2i_fontRGB'] = '000000';
		}
		update_option( 'cryptX', self::$cryptXOptions );
		self::$cryptXOptions = $this->loadCryptXOptionsWithDefaults(); // reread Options
	}

	private function addHooksHelper( $function_name, $hook_name ): void {
		if ( function_exists( $function_name ) ) {
			call_user_func( $function_name, 'cryptx', 'CryptX', [ $this, 'metaCheckbox' ], $hook_name );
		} else {
			add_action( "dbx_{$hook_name}_sidebar", [ $this, 'metaOptionFieldset' ] );
		}
	}

	public function metaBox(): void {
		$this->addHooksHelper( 'add_meta_box', 'post' );
		$this->addHooksHelper( 'add_meta_box', 'page' );
	}

	/**
	 * Displays a checkbox to disable CryptX for the current post or page.
	 *
	 * This function outputs HTML code for a checkbox that allows the user to disable CryptX
	 * functionality for the current post or page. If the current post or page ID is excluded
	 **/
	public function metaCheckbox(): void {
		global $post;
		?>
        <label><input type="checkbox" name="disable_cryptx_pageid" <?php if ( $this->isIdExcluded( $post->ID ) ) {
				echo 'checked="checked"';
			} ?>/>
            Disable CryptX for this post/page</label>
		<?php
	}

	/**
	 * Renders the CryptX option fieldset for the current post/page if the user has permission to edit posts.
	 * This fieldset allows the user to enable or disable CryptX for the current post/page.
	 *
	 * @return void
	 */
	public function metaOptionFieldset(): void {
		global $post;
		if ( current_user_can( 'edit_posts' ) ) { ?>
            <fieldset id="cryptxoption" class="dbx-box">
                <h3 class="dbx-handle">CryptX</h3>
                <div class="dbx-content">
                    <label><input type="checkbox"
                                  name="disable_cryptx_pageid" <?php if ( $this->isIdExcluded( $post->ID ) ) {
							echo 'checked="checked"';
						} ?>/> Disable CryptX for this post/page</label>
                </div>
            </fieldset>
			<?php
		}
	}

	/**
	 * Adds a post ID to the excluded list in the cryptX options.
	 *
	 * @param int $postId The post ID to be added to the excluded list.
	 *
	 * @return void
	 */
	public function addPostIdToExcludedList( int $postId ): void {
		$postId                             = wp_is_post_revision( $postId ) ?: $postId;
		$excludedIds                        = $this->updateExcludedIdsList( self::$cryptXOptions['excludedIDs'], $postId );
		self::$cryptXOptions['excludedIDs'] = implode( ",", array_filter( $excludedIds ) );
		update_option( 'cryptX', self::$cryptXOptions );
	}

	/**
	 * Updates the excluded IDs list based on a given ID and the current list.
	 *
	 * @param string $excludedIds The current excluded IDs list, separated by commas.
	 * @param int $postId The ID to be updated in the excluded IDs list.
	 *
	 * @return array The updated excluded IDs list as an array, with the ID removed if it existed and added if necessary.
	 */
	private function updateExcludedIdsList( string $excludedIds, int $postId ): array {
		$excludedIdsArray = explode( ",", $excludedIds );
		$excludedIdsArray = $this->removePostIdFromExcludedIds( $excludedIdsArray, $postId );
		$excludedIdsArray = $this->addPostIdToExcludedIdsIfNecessary( $excludedIdsArray, $postId );

		return $this->makeExcludedIdsUniqueAndSorted( $excludedIdsArray );
	}

	/**
	 * Removes a specific post ID from the array of excluded IDs.
	 *
	 * @param array $excludedIds The array of excluded IDs.
	 * @param int $postId The ID of the post to be removed from the excluded IDs.
	 *
	 * @return array The updated array of excluded IDs without the specified post ID.
	 */
	private function removePostIdFromExcludedIds( array $excludedIds, int $postId ): array {
		foreach ( $excludedIds as $key => $id ) {
			if ( $id == $postId ) {
				unset( $excludedIds[ $key ] );
				break;
			}
		}

		return $excludedIds;
	}

	/**
	 * Adds the post ID to the list of excluded IDs if necessary.
	 *
	 * @param array $excludedIds The array of excluded IDs.
	 * @param int $postId The post ID to be added to the excluded IDs.
	 *
	 * @return array The updated array of excluded IDs.
	 */
	private function addPostIdToExcludedIdsIfNecessary( array $excludedIds, int $postId ): array {
		if ( isset( $_POST['disable_cryptx_pageid'] ) ) {
			$excludedIds[] = $postId;
		}

		return $excludedIds;
	}

	/**
	 * Makes the excluded IDs unique and sorted.
	 *
	 * @param array $excludedIds The array of excluded IDs.
	 *
	 * @return array The array of excluded IDs with duplicate values removed and sorted in ascending order.
	 */
	private function makeExcludedIdsUniqueAndSorted( array $excludedIds ): array {
		$excludedIds = array_unique( $excludedIds );
		sort( $excludedIds );

		return $excludedIds;
	}

	/**
	 * Displays a message in a styled div.
	 *
	 * @param string $message The message to be displayed.
	 * @param bool $errormsg Optional. Indicates whether the message is an error message. Default is false.
	 *
	 * @return void
	 */
	private function showMessage( string $message, bool $errormsg = false ): void {
		if ( $errormsg ) {
			echo '<div id="message" class="error">';
		} else {
			echo '<div id="message" class="updated fade">';
		}

		echo "$message</div>";
	}

	/**
	 * Retrieves the domain from the current site URL.
	 *
	 * @return string The domain of the current site URL.
	 */
	public function getDomain(): string {
		return $this->trimSlashFromDomain( $this->removeProtocolFromUrl( $this->getSiteUrl() ) );
	}

	/**
	 * Retrieves the site URL.
	 *
	 * @return string The site URL.
	 */
	private function getSiteUrl(): string {
		return get_option( 'siteurl' );
	}

	/**
	 * Removes the protocol from a URL.
	 *
	 * @param string $url The URL string to remove the protocol from.
	 *
	 * @return string The URL string without the protocol.
	 */
	private function removeProtocolFromUrl( string $url ): string {
		return preg_replace( '|https?://|', '', $url );
	}

	/**
	 * Trims the trailing slash from a domain.
	 *
	 * @param string $domain The domain to trim the slash from.
	 *
	 * @return string The domain with the trailing slash removed.
	 */
	private function trimSlashFromDomain( string $domain ): string {
		if ( $slashPosition = strpos( $domain, '/' ) ) {
			$domain = substr( $domain, 0, $slashPosition );
		}

		return $domain;
	}

	/**
	 * Loads Javascript files required for CryptX functionality.
	 *
	 * @return void
	 */
	public function loadJavascriptFiles(): void {
		wp_enqueue_script( 'cryptx-js', CRYPTX_DIR_URL . 'js/cryptx.min.js', false, false, self::$cryptXOptions['load_java'] );
		wp_enqueue_style( 'cryptx-styles', CRYPTX_DIR_URL . 'css/cryptx.css' );
	}

	/**
	 * Updates the CryptX settings.
	 *
	 * This method retrieves the current CryptX options from the database and checks if the version of CryptX
	 * stored in the options is less than the current version of CryptX. If the version is outdated, the method
	 * updates the necessary settings and saves the updated options back to the database.
	 *
	 * @return void
	 */
	private function updateCryptXSettings(): void {
		self::$cryptXOptions = get_option( 'cryptX' );
		if ( isset( self::$cryptXOptions['version'] ) && version_compare( CRYPTX_VERSION, self::$cryptXOptions['version'] ) > 0 ) {
			if ( isset( self::$cryptXOptions['version'] ) ) {
				unset( self::$cryptXOptions['version'] );
			}
			if ( isset( self::$cryptXOptions['c2i_font'] ) ) {
				unset( self::$cryptXOptions['c2i_font'] );
			}
			if ( isset( self::$cryptXOptions['c2i_fontRGB'] ) ) {
				self::$cryptXOptions['c2i_fontRGB'] = "#" . self::$cryptXOptions['c2i_fontRGB'];
			}
			if ( isset( self::$cryptXOptions['alt_uploadedimage'] ) && ! is_int( self::$cryptXOptions['alt_uploadedimage'] ) ) {
				unset( self::$cryptXOptions['alt_uploadedimage'] );
				if ( self::$cryptXOptions['opt_linktext'] == 3 ) {
					unset( self::$cryptXOptions['opt_linktext'] );
				}
			}
			self::$cryptXOptions = wp_parse_args( self::$cryptXOptions, $this->getCryptXOptionsDefaults() );
			update_option( 'cryptX', self::$cryptXOptions );
		}
	}

	/**
	 * Encodes a string by replacing special characters with their corresponding HTML entities.
	 *
	 * @param string|null $str The string to be encoded.
	 *
	 * @return string The encoded string, or an array of encoded strings if an array was passed.
	 */
	private function encodeString( ?string $str ): string {
		$str     = htmlentities( $str, ENT_QUOTES, 'UTF-8' );
		$special = array(
			'[' => '&#91;',
			']' => '&#93;',
		);

		return str_replace( array_keys( $special ), array_values( $special ), $str );
	}

	/**
	 * Decodes a string that has been HTML entity encoded.
	 *
	 * @param string|null $str The string to decode. If null, an empty string is returned.
	 *
	 * @return string The decoded string.
	 */
	private function decodeString( ?string $str ): string {
		return html_entity_decode( $str, ENT_QUOTES, 'UTF-8' );
	}

	public function convertArrayToArgumentString( array $args = [] ): string {
		$string = "";
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $value ) {
				$string .= sprintf( " %s=\"%s\"", $key, $this->encodeString( $value ) );
			}
			$string .= " encoded=\"true\"";
		}

		return $string;
	}
}