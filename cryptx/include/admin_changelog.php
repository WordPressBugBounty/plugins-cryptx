<?php
/**
 * CryptX option page "Changelog"-Tab
 */
function rw_cryptx_settings_tab_content_changelog() {
	if ( 'changelog' != rw_cryptx_getActiveTab() ) {
		return;
	}

	/**
	 *  the following code is quick and dirty to parse the changelog content of the readme.txt file
	 */
	$file_contents = @implode( '', @file( CRYPTX_DIR_PATH . '/readme.txt' ) );
	$file_contents = str_replace( array( "\r\n", "\r" ), "\n", $file_contents );
	$file_contents = trim( $file_contents );
    // split $file_content into sections
	$_sections = preg_split( '/^[\s]*==[\s]*(.+?)[\s]*==/m', $file_contents, - 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	$sections  = array();
	for ( $i = 1; $i <= count( $_sections ); $i += 2 ) {
		$_sections[ $i ]                                           = $_sections[ $i ];
		$title                                                     = $_sections[ $i - 1 ];
		$sections[ str_replace( ' ', '_', strtolower( $title ) ) ] = array(
			'title'   => $title,
			'content' => $_sections[ $i ]
		);
	}
    // split changelog section into single version entries
	$_changelogs = preg_split( '/^[\s]*=[\s]*(.+?)[\s]*=/m', $sections['changelog']['content'], - 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	$entries     = array();
	for ( $i = 1; $i <= count( $_changelogs ); $i += 2 ) {
		$_changelogs[ $i ]                                          = $_changelogs[ $i ];
		$version                                                    = $_changelogs[ $i - 1 ];
		$entries[ str_replace( ' ', '_', strtolower( $version ) ) ] = array(
			'version' => $version,
			'content' => $_changelogs[ $i ]
		);
	}
    // rearrange version entries as html
	$changelogs = array();
	foreach ( $entries as $entry ) {
		$content      = $entry['content'];
		$content      = ltrim( $content, "\n" );
		$content      = str_replace( "* ", "<li>", $content );
		$content      = str_replace( "\n", " </li>\n", $content );
		$changelogs[] = array(
			'version' => "<dt>" . $entry['version'] . "</dt>",
			'content' => "<dd><ul>" . $content . "</ul></dd>"
		);
	}
	unset( $file_contents, $_sections, $sections, $_changelogs, $entries );
	?>
    <h4><?php _e( "Changelog", 'cryptx' ); ?></h4>
	<?php
	foreach ( $changelogs as $log ) {
		echo "<dl>" . implode( "", $log ) . "</dl>";
	}
	unset( $changelogs );
}

add_action( 'rw_cryptx_settings_content', 'rw_cryptx_settings_tab_content_changelog' );