/*eslint-env jquery*/
/*eslint no-unused-vars: 0*/
/*global wp, console*/
 (function( $ ) {

    // Add Color Picker to all inputs that have 'color-field' class
    $(function() {
        $('.color-field').wpColorPicker();
    });

    var $cryptX_option = $('#opt_linktext4'),
        $cryptX_notice = $('#opt_linktext4_notice'),
        $cryptX_image_id = $('#image_attachment_id'),
        $cryptX_preview = $('#image-preview'),
        $cryptX_addButton = $('#upload_image_button'),
        $cryptX_delButton = $('#remove_image_button'),
        $cryptX_altOption = $('#opt_linktext'),
        file_frame,
        wp_media_post_id,
        set_to_post_id;

    function cryptx_check_image_state() {
        if ($cryptX_image_id.val() == 0) {
            if( $cryptX_option.prop('checked') == true ) {
                $cryptX_option.prop('checked', false);
                $cryptX_altOption.prop('checked', true);
            }
            $cryptX_option.prop("disabled", true);
            $cryptX_notice.removeClass( 'hidden' );
            $cryptX_preview.addClass( 'hidden' );
            $cryptX_addButton.removeClass( 'hidden' );
            $cryptX_delButton.addClass( 'hidden' );
        } else {
            $cryptX_option.prop("disabled", false);
            $cryptX_notice.addClass( 'hidden' );
            $cryptX_preview.removeClass( 'hidden' );
            $cryptX_addButton.addClass( 'hidden' );
            $cryptX_delButton.removeClass( 'hidden' );
        }
    }
    cryptx_check_image_state()

    $cryptX_image_id.on("change", function() {
        cryptx_check_image_state();
    });

/*
    $cryptX_preview.on("load", function() {
        cryptx_check_image_state();
    });
*/

	// Uploading files
	$cryptX_addButton.on('click', function( event ){
    	var attachment;
    	event.preventDefault();
        set_to_post_id = $cryptX_image_id.val();

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			// Set the post ID to what we want
            wp_media_post_id = wp.media.model.settings.post.id; // Store the old id
			file_frame.uploader.uploader.param( 'post_id', set_to_post_id );
			// Open frame
			file_frame.open();
			return;
		} else {
			// Set the wp.media post id so the uploader grabs the ID we want when initialised
			wp.media.model.settings.post.id = set_to_post_id;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: 'Select a image to upload',
			button: {
				text: 'Use this image'
			},
			multiple: false	// Set to true to allow multiple files to be selected
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			attachment = file_frame.state().get('selection').first().toJSON();
			// Do something with attachment.id and/or attachment.url here
			$cryptX_preview.attr( 'src', attachment.url ).css( 'width', 'auto' );
			$cryptX_image_id.val( attachment.id );
			$cryptX_image_id.trigger('change');
            // Un-hide the add image link
//             $cryptX_addButton.addClass( 'hidden' );
            // Hide the delete image link
//             $cryptX_delButton.removeClass( 'hidden' );
			// Restore the main post ID
			wp.media.model.settings.post.id = wp_media_post_id;
		});

			// Finally, open the modal
			file_frame.open();
	});

	// Restore the main ID when the add media button is pressed
	$( 'a.add_media' ).on( 'click', function() {
		wp.media.model.settings.post.id = wp_media_post_id;
	});

	// DELETE IMAGE LINK
    $cryptX_delButton.on( 'click', function( event ){
        event.preventDefault();
        // Clear out the preview image
        $cryptX_preview.attr( 'src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' );
        // Un-hide the add image link
        $cryptX_addButton.removeClass( 'hidden' );
        // Hide the delete image link
        $cryptX_delButton.addClass( 'hidden' );
        // Delete the image id from the hidden input
        $cryptX_image_id.val( '0' ).trigger('change');
    });


})( jQuery );