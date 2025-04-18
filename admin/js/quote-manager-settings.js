/**
 * Quote Manager Settings Page JavaScript
 */
(function($) {
    'use strict';

    // On document load
    $(document).ready(function() {
        // Handle media uploader for logo
        if ($('.upload-logo-button').length) {
            $('.upload-logo-button').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var logoField = $('#' + button.data('input-id'));
                
                // If wp.media is not available, don't proceed
                if (typeof wp === 'undefined' || typeof wp.media !== 'function') {
                    console.error('WordPress Media API is not available');
                    return;
                }
                
                var mediaUploader = wp.media({
                    title: 'Select Logo',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    logoField.val(attachment.url);
                    
                    // Update preview
                    if (logoField.siblings('.logo-preview').length) {
                        logoField.siblings('.logo-preview').find('img').attr('src', attachment.url);
                    } else {
                        $('<div class="logo-preview"><img src="' + attachment.url + '" alt="Company Logo" style="max-width: 300px; height: auto; margin-top: 10px;"></div>').insertAfter(logoField);
                    }
                });
                
                mediaUploader.open();
            });
        }
    });
})(jQuery);