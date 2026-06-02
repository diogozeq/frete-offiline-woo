/* global cctsAdminParams */
(function ( $ ) {
  'use strict';

  /**
   * Theme Options and Metaboxes.
   */
  $( function () {

    /**
     * Upload.
     */
    $( '.ccts-upload-button' ).on( 'click', function ( e ) {
      e.preventDefault();

      $( '.ccts_error.invalid-file' ).addClass( 'hidden' );

      var uploadFrame,
        uploadInput = $( cctsAdminParams.uploadInput );

      // If the media frame already exists, reopen it.
      if ( uploadFrame ) {
        uploadFrame.open();

        return;
      }

      // Create the media frame.
      uploadFrame = wp.media.frames.downloadable_file = wp.media({
        title: cctsAdminParams.uploadTitle,
        button: {
          text: cctsAdminParams.uploadButton
        },
        multiple: false,
        // mime_types: 'pdf',         // 'pdf, etc'
        mode: 'select',
        // library: wp.media.query( attributes.library )
      });

      uploadFrame.on( 'select', function () {
        var attachment = uploadFrame.state().get( 'selection').first().toJSON();

        console.log( attachment );

        if ( 'text/csv' !== attachment.mime && 'text/plain' !== attachment.mime ) {
          $( '.ccts_error.invalid-file' ).removeClass( 'hidden' );
          return false;
        }

        uploadInput.val( attachment.id );

        if ( attachment.id > 0 ) {
          $( '.ccts-upload-wrapper' ).addClass( 'hidden' );
          $( '.ccts-file-uploader' ).removeClass( 'hidden' );

          $( '.ccts-file-uploader' ).find( '.file-icon img' ).attr( 'src', attachment.icon );
          $( '.ccts-file-uploader' ).find( '[data-name="title"]' ).html( attachment.title );
          $( '.ccts-file-uploader' ).find( '[data-name="filename"]' ).html( attachment.filename ).attr( 'href', attachment.url );
          $( '.ccts-file-uploader' ).find( '[data-name="filesize"]' ).html( attachment.filesizeHumanReadable );

          $( '.refresh-page-to-see-results' ).removeClass( 'hidden' );

          $( '.woocommerce-save-button' ).click();

        }

      });

      // Finally, open the modal.
      uploadFrame.open();
    });
  });
}( jQuery ));
