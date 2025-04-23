/**
 * Quote Manager Settings Page JavaScript
 */
(function ($) {
    'use strict';
    // On document load
    $(document).ready(function () {
        // Handle media uploader for logo
        if ($('.upload-logo-button').length) {
            $('.upload-logo-button').on('click', function (e) {
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
                mediaUploader.on('select', function () {
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
        
                // Create default variables if not defined by wp_localize_script
                if (typeof quote_manager_i18n === 'undefined' || quote_manager_i18n === null) {
                    window.quote_manager_i18n = {
                        reset_terms_confirm: 'Reset to default terms? This will replace your current text.',
                        reset_email_confirm: 'Reset to default email template? This will replace your current template.'
                    };
                }
                
                if (typeof quote_manager_vars === 'undefined' || quote_manager_vars === null) {
                    window.quote_manager_vars = {
                        default_terms: '',
                        default_email: ''
                    };
                }

        // Handle reset to default terms button
        if ($('#reset-to-default-terms').length) {
            $('#reset-to-default-terms').on('click', function () {
                if (confirm(quote_manager_i18n.reset_terms_confirm)) {
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('quote_terms_editor')) {
                        // For the visual editor
                        var editor = tinyMCE.get('quote_terms_editor');
                        // Disable auto formatting temporarily
                        var oldForcePasteAsPlainText = editor.settings.force_p_newlines;
                        editor.settings.force_p_newlines = false;
                        // Force raw mode and prevent auto formatting
                        editor.undoManager.transact(function () {
                            // First switch to text mode to preserve exact HTML
                            editor.setMode('text');
                            // Set content as raw HTML
                            editor.setContent(quote_manager_vars.default_terms);
                            // Switch back to visual mode
                            editor.setMode('design');
                        });
                        // Restore settings
                        editor.settings.force_p_newlines = oldForcePasteAsPlainText;
                    } else {
                        // For the text editor (HTML view)
                        $('#quote_terms_editor').val(quote_manager_vars.default_terms);
                    }
                }
            });
        }
        
        // Handle reset to default email template button
        if ($('#reset-to-default-email').length) {
            $('#reset-to-default-email').on('click', function () {
                // Get default template from data attribute
                var defaultEmailTemplate = $(this).data('default-template') || '';
                
                // Fallback confirmation message if translation is not available
                var confirmMessage = typeof quote_manager_i18n !== 'undefined' && quote_manager_i18n.reset_email_confirm ? 
                    quote_manager_i18n.reset_email_confirm : 
                    'Reset to default email template? This will replace your current template.';
                    
                if (confirm(confirmMessage)) {
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('quote_email_template_editor')) {
                        // For the visual editor
                        var editor = tinyMCE.get('quote_email_template_editor');
                        // Disable auto formatting temporarily
                        var oldForcePasteAsPlainText = editor.settings.force_p_newlines;
                        editor.settings.force_p_newlines = false;
                        // Force raw mode and prevent auto formatting
                        editor.undoManager.transact(function () {
                            // First switch to text mode to preserve exact HTML
                            editor.setMode('text');
                            // Set content as raw HTML from the data attribute
                            editor.setContent(defaultEmailTemplate);
                            // Switch back to visual mode
                            editor.setMode('design');
                        });
                        // Restore settings
                        editor.settings.force_p_newlines = oldForcePasteAsPlainText;
                    } else {
                        // For the text editor (HTML view)
                        $('#quote_email_template_editor').val(defaultEmailTemplate);
                    }
                }
            });
        }
    });
})(jQuery);