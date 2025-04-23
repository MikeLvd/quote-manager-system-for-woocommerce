(function ($) {
    'use strict';

    /**
     * Quote Approval Handler
     * Handles the customer interactions on the quote approval page
     */
    var QuoteApproval = {
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
         * Initialize the approval handler
         */
        init: function () {
            // Check if we're on the approval page
            if ($('#signature-pad').length === 0) {
                return;
            }

            // Store variables
            this.canvas = document.getElementById('signature-pad');
            this.quoteId = $('#quote-id-field').val();
            this.securityHash = $('#quote-hash-field').val();
            this.securityNonce = $('#quote-nonce-field').val();

            // Initialize signature pad
            this.initSignaturePad();

            // Set up event listeners
            this.setupEventListeners();
        },

        /**
         * Initialize the signature pad
         */
        initSignaturePad: function () {
            this.signaturePad = new SignaturePad(this.canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)'
            });

            // Resize canvas for better display
            this.resizeCanvas();

            // Add resize listener
            window.addEventListener('resize', this.resizeCanvas.bind(this));
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
         * Set up all event listeners
         */
        setupEventListeners: function () {
            var self = this;

            // Handle Accept button click
            $('#accept-quote').on('click', function () {
                $('#signature-container').show();
                $('#rejection-container').hide();
                $('html, body').animate({
                    scrollTop: $('#signature-container').offset().top
                }, 500);
            });

            // Handle Reject button click
            $('#reject-quote').on('click', function () {
                $('#rejection-container').show();
                $('#signature-container').hide();
                $('html, body').animate({
                    scrollTop: $('#rejection-container').offset().top
                }, 500);
            });

            // Handle Clear button click
            $('#clear-signature').on('click', function () {
                self.signaturePad.clear();
            });

            // Handle Submit button click
            $('#submit-signature').on('click', this.handleSubmitSignature.bind(this));

            // Handle Confirm Rejection button click
            $('#confirm-rejection').on('click', this.handleConfirmRejection.bind(this));
        },

        /**
         * Handle the submission of a signature
         */
        handleSubmitSignature: function () {
            if (this.signaturePad.isEmpty()) {
                alert(quote_approval_i18n.provide_signature);
                return false;
            }

            // Show loading overlay
            $('#loading-overlay').css('display', 'flex');

            // Get signature data
            var signatureData = this.signaturePad.toDataURL();

            // Submit via AJAX
            $.ajax({
                url: quote_approval_i18n.ajax_url,
                type: 'POST',
                data: {
                    action: 'quote_manager_process_signature',
                    quote_id: this.quoteId,
                    hash: this.securityHash,
                    signature: signatureData,
                    security: this.securityNonce
                },
                success: function (response) {
                    if (response.success) {
                        // Redirect to success page
                        window.location.href = response.data.redirect;
                    } else {
                        // Hide loading overlay
                        $('#loading-overlay').hide();
                        alert(response.data.message || quote_approval_i18n.error_try_again);
                    }
                },
                error: function () {
                    // Hide loading overlay
                    $('#loading-overlay').hide();
                    alert(quote_approval_i18n.error_try_again);
                }
            });
        },

        /**
         * Handle the confirmation of a rejection
         */
        handleConfirmRejection: function () {
            // Show loading overlay
            $('#loading-overlay').css('display', 'flex');

            // Get rejection reason
            var reason = $('#rejection-reason').val();

            // Submit via AJAX
            $.ajax({
                url: quote_approval_i18n.ajax_url,
                type: 'POST',
                data: {
                    action: 'quote_manager_reject_quote',
                    quote_id: this.quoteId,
                    hash: this.securityHash,
                    reason: reason,
                    security: this.securityNonce
                },
                success: function (response) {
                    if (response.success) {
                        // Redirect to rejection page
                        window.location.href = response.data.redirect;
                    } else {
                        // Hide loading overlay
                        $('#loading-overlay').hide();
                        alert(response.data.message || quote_approval_i18n.error_try_again);
                    }
                },
                error: function () {
                    // Hide loading overlay
                    $('#loading-overlay').hide();
                    alert(quote_approval_i18n.error_try_again);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        QuoteApproval.init();
    });

})(jQuery);