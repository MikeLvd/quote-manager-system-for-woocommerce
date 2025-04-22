/**
 * Quote Manager Status JavaScript
 * Handles the quote status functionality
 */
(function($) {
    'use strict';

    // StatusManager object
    const StatusManager = {
        init: function() {
            this.setupEventListeners();
            this.checkExpiredQuotes();
        },

        setupEventListeners: function() {
            // Status change in the metabox
            $('#quote_status').on('change', this.handleStatusChange);
            
            // Expose updateStatus method for external use
            window.QuoteStatusManager = {
                updateStatus: this.updateStatus
            };
        },

        // Handle status change from dropdown
        handleStatusChange: function(e) {
            const $select = $(this);
            const quoteId = $('#post_ID').val();
            const newStatus = $select.val();
            
            // Save the original value in case we need to revert
            const originalStatus = $select.data('original-value') || newStatus;
            $select.data('original-value', originalStatus);
            
            // Disable the select while updating
            $select.prop('disabled', true);
            
            // Show updating message
            $('#status-updated-message')
                .text(quoteManagerStatusData.i18n.updatingStatus)
                .css('color', '#0073aa')
                .show();
            
            // Send AJAX request
            $.ajax({
                url: quoteManagerStatusData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'quote_manager_update_status',
                    quote_id: quoteId,
                    status: newStatus,
                    security: quoteManagerStatusData.statusNonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $('#status-updated-message')
                            .text(response.data.message)
                            .css('color', '#46b450');
                            
                        // Hide message after 3 seconds
                        setTimeout(function() {
                            $('#status-updated-message').fadeOut();
                        }, 3000);
                    } else {
                        // Show error and revert to original status
                        $('#status-updated-message')
                            .text(response.data.message)
                            .css('color', '#dc3232');
                            
                        $select.val(originalStatus);
                            
                        // Hide message after 5 seconds
                        setTimeout(function() {
                            $('#status-updated-message').fadeOut();
                        }, 5000);
                    }
                },
                error: function() {
                    // Show generic error and revert
                    $('#status-updated-message')
                        .text(quoteManagerStatusData.i18n.errorUpdating)
                        .css('color', '#dc3232');
                        
                    $select.val(originalStatus);
                        
                    // Hide message after 5 seconds
                    setTimeout(function() {
                        $('#status-updated-message').fadeOut();
                    }, 5000);
                },
                complete: function() {
                    // Re-enable the select
                    $select.prop('disabled', false);
                }
            });
        },

        // Public method to update status
        updateStatus: function(quoteId, newStatus, callback) {
            $.ajax({
                url: quoteManagerStatusData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'quote_manager_update_status',
                    quote_id: quoteId,
                    status: newStatus,
                    security: quoteManagerStatusData.statusNonce
                },
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                }
            });
        },
        
        // Check for expired quotes
        checkExpiredQuotes: function() {
            if (quoteManagerStatusData.isQuoteList) {
                $.ajax({
                    url: quoteManagerStatusData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'quote_manager_check_expired',
                        security: quoteManagerStatusData.expiredNonce
                    },
                    success: function(response) {
                        if (response.success && response.data.expired_count > 0) {
                            // Show notification about expired quotes
                            $('h1.wp-heading-inline').after(
                                '<div class="notice notice-warning is-dismissible"><p>' + 
                                quoteManagerStatusData.i18n.expiredNotice.replace('%d', response.data.expired_count) +
                                '</p></div>'
                            );
                        }
                    }
                });
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        StatusManager.init();
    });

})(jQuery);