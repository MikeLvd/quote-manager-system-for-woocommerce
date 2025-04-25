/**
 * Quote Manager System for WooCommerce - Public JS
 * Version: 1.8.8
 */
(function ($) {
    'use strict';

    /**
     * Quote Manager Public
     */
    var QuoteManager = {
        /**
         * Variables for signature pad
         */
        signaturePad: null,
        canvas: null,

        /**
         * Variables for quote data
         */
        quoteId: null,
        securityHash: null,
        securityNonce: null,

        /**
         * Initialize
         */
        init: function () {
            // Check if we're on a quote page
            if (this.isQuotePage()) {
                // Determine which page we're on
                var viewParam = this.getUrlParam('view');
                
                if (viewParam === 'accept') {
                    this.initSignature();
                } else if (viewParam === 'reject') {
                    this.initRejection();
                }
            }
        },
        
        /**
         * Check if we're on a quote-related page
         */
        isQuotePage: function () {
            // Check URL parameters
            var viewParam = this.getUrlParam('view');
            if (viewParam && ['quote', 'accept', 'reject', 'response'].indexOf(viewParam) !== -1) {
                return true;
            }
            
            // Check for quote containers (fallback)
            if ($('.quote-view-container, .quote-accept-container, .quote-reject-container, .quote-response-container').length > 0) {
                return true;
            }
            
            return false;
        },
        
        /**
         * Get URL parameter value
         */
        getUrlParam: function (paramName) {
            var urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(paramName);
        },
        
        /**
         * Initialize signature functionality
         */
        initSignature: function () {
            // Check if signature pad exists
            this.canvas = document.getElementById('signature-pad');
            if (!this.canvas) {
                return;
            }
            
            // Check if SignaturePad is loaded
            if (typeof SignaturePad === 'undefined') {
                console.error('SignaturePad library not loaded');
                return;
            }
            
            // Initialize SignaturePad
            this.signaturePad = new SignaturePad(this.canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)'
            });
            
            // Resize canvas for better display
            this.resizeCanvas();
            
            // Add resize listener
            window.addEventListener('resize', this.resizeCanvas.bind(this));
            
            // Set up event listeners
            this.setupSignatureEvents();
        },
        
        /**
         * Resize the canvas to match container
         */
        resizeCanvas: function () {
            if (!this.canvas) return;
            
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            this.canvas.width = this.canvas.offsetWidth * ratio;
            this.canvas.height = this.canvas.offsetHeight * ratio;
            this.canvas.getContext("2d").scale(ratio, ratio);
            
            if (this.signaturePad) {
                this.signaturePad.clear(); // Otherwise isEmpty() might return incorrect value
            }
        },
        
        /**
         * Set up signature events
         */
        setupSignatureEvents: function () {
            var self = this;
            
            // Clear button
            $('#clear-signature').on('click', function () {
                self.signaturePad.clear();
            });
            
            // Submit button
            $('#sign-accept-button').on('click', function () {
                self.handleAcceptSubmit();
            });
        },
        
        /**
         * Handle the submission with signature
         */
        handleAcceptSubmit: function () {
            if (this.signaturePad.isEmpty()) {
                alert(quote_manager_i18n.provide_signature || 'Please provide your signature to accept the quote.');
                return false;
            }
            
            // Show loading state
            var button = document.getElementById('sign-accept-button');
            var originalText = button.textContent;
            button.disabled = true;
            button.textContent = quote_manager_i18n.processing || 'Processing...';
            
            // Show message
            var messageEl = document.getElementById('quote-accept-message');
            if (messageEl) {
                messageEl.style.display = 'block';
                messageEl.textContent = quote_manager_i18n.submitting || 'Submitting your acceptance. Please wait...';
                messageEl.className = 'quote-message';
            }
            
            // Get signature data
            var signatureData = this.signaturePad.toDataURL();
            
            // Get form data
            var quoteId = this.getUrlParam('id');
            var token = this.getUrlParam('token');
            
            // Prepare form data
            var formData = new FormData();
            formData.append('action', 'quote_manager_accept');
            formData.append('nonce', $('#quote-accept-nonce').val());
            formData.append('quote_id', quoteId);
            formData.append('token', token);
            formData.append('signature', signatureData);
            
            // Send AJAX request
            $.ajax({
                url: quote_manager_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success && response.data && response.data.redirect) {
                        // Success - redirect to the success page
                        if (messageEl) {
                            messageEl.textContent = quote_manager_i18n.quote_accepted || 'Quote accepted successfully! Redirecting...';
                            messageEl.className = 'quote-message success';
                        }
                        
                        setTimeout(function () {
                            window.location.href = response.data.redirect;
                        }, 1000); // Short delay for user to see the success message
                    } else {
                        // Error
                        if (messageEl) {
                            messageEl.textContent = response.data && response.data.message ? 
                                response.data.message : 
                                (quote_manager_i18n.error_submitting || 'An error occurred while submitting your acceptance. Please try again.');
                            messageEl.className = 'quote-message error';
                        }
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                },
                error: function () {
                    // Error
                    if (messageEl) {
                        messageEl.textContent = quote_manager_i18n.error_server || 'A server error occurred. Please try again later.';
                        messageEl.className = 'quote-message error';
                    }
                    button.disabled = false;
                    button.textContent = originalText;
                }
            });
        },
        
        /**
         * Initialize rejection functionality
         */
        initRejection: function () {
            // Set up event listeners
            this.setupRejectionEvents();
        },
        
        /**
         * Set up rejection events
         */
        setupRejectionEvents: function () {
            var self = this;
            
            // Submit button
            $('#decline-quote-button').on('click', function () {
                self.handleRejectSubmit();
            });
        },
        
        /**
         * Handle the quote rejection submission
         */
        handleRejectSubmit: function () {
            // Show loading state
            var button = document.getElementById('decline-quote-button');
            var originalText = button.textContent;
            button.disabled = true;
            button.textContent = quote_manager_i18n.processing || 'Processing...';
            
            // Show message
            var messageEl = document.getElementById('quote-reject-message');
            if (messageEl) {
                messageEl.style.display = 'block';
                messageEl.textContent = quote_manager_i18n.submitting || 'Submitting your response. Please wait...';
                messageEl.className = 'quote-message';
            }
            
            // Get form data
            var quoteId = this.getUrlParam('id');
            var token = this.getUrlParam('token');
            var reason = $('#reject-reason').val();
            
            // Send AJAX request
            $.ajax({
                url: quote_manager_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'quote_manager_reject',
                    nonce: $('#quote-reject-nonce').val(),
                    quote_id: quoteId,
                    token: token,
                    reason: reason
                },
                success: function (response) {
                    if (response.success && response.data && response.data.redirect) {
                        // Success - redirect to the success page
                        if (messageEl) {
                            messageEl.textContent = quote_manager_i18n.quote_rejected || 'Quote declined successfully! Redirecting...';
                            messageEl.className = 'quote-message success';
                        }
                        
                        setTimeout(function () {
                            window.location.href = response.data.redirect;
                        }, 1000); // Short delay for user to see the success message
                    } else {
                        // Error
                        if (messageEl) {
                            messageEl.textContent = response.data && response.data.message ? 
                                response.data.message : 
                                (quote_manager_i18n.error_submitting || 'An error occurred while submitting your response. Please try again.');
                            messageEl.className = 'quote-message error';
                        }
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                },
                error: function () {
                    // Error
                    if (messageEl) {
                        messageEl.textContent = quote_manager_i18n.error_server || 'A server error occurred. Please try again later.';
                        messageEl.className = 'quote-message error';
                    }
                    button.disabled = false;
                    button.textContent = originalText;
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function () {
        QuoteManager.init();
    });
    
})(jQuery);