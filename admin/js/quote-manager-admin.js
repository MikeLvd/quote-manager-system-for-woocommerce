/**
 * Quote Manager for WooCommerce - Admin JavaScript
 * Using module pattern for better organization and performance
 */
(function ($) {
    'use strict';

    // Main QuoteManager object
    var QuoteManager = {
        // Configuration
        config: {
            wc_placeholder_img_src: (typeof quoteManagerData !== 'undefined' && quoteManagerData.placeholderImage)
                ? quoteManagerData.placeholderImage
                : '/wp-content/uploads/woocommerce-placeholder.png',
            taxRate: (typeof quoteManagerData !== 'undefined' && quoteManagerData.taxRatePercent)
                ? (quoteManagerData.taxRatePercent / 100)
                : 0,
            ajaxUrl: (typeof quoteManagerData !== 'undefined' ? quoteManagerData.ajaxUrl : ajaxurl),
            includeVAT: false // Will be initialized in init method
        },

        // Element cache
        elements: {
            $search: null,
            $suggestions: null,
            $productsTable: null,
            $emailModal: null
        },

        // Variables
        typingTimer: null,
        currentWrapper: null,
        media_frame: null,

        /**
         * Initialize the module
         */
        init: function () {
            // Read initial VAT status from the table's data attribute
            this.config.includeVAT = $('#quote-products-table').attr('data-vat-status') === 'enabled';

            this.cacheElements();
            this.setupEventListeners();
            this.initializeTable();
            this.createModalElements();
            this.initSortableTable(); // Add call for sortable

            // Initialize internal info table
            this.updateInternalInfo();

            // Initialize attachments handling
            this.handleAttachments();

            // Handle shipping/billing address interactions
            this.handleAddressInteractions();

            // Check if TinyMCE is available and initialize it for quote_email_message
            if (typeof tinyMCE !== 'undefined' && document.getElementById('quote_email_message')) {
                this.initEditor();
            }
        },

        /**
         * Initialize the table drag-and-drop functionality using TableDnD
         */
        initSortableTable: function () {
            const self = this;

            // Initialize TableDnD
            $('#quote-products-table').tableDnD({
                onDragClass: "dragging", // CSS class when dragging a row
                dragHandle: ".quote-td-number", // Use number column as drag handle

                // Customize draggable object
                onDragStart: function (table, row) {
                    // Add special style to row when drag starts
                    $(row).addClass('tablednd-dragging-row');
                    // Store original row index for use in onDrop
                    $(row).attr('data-original-index', $(row).index());
                },

                // Execute actions after drag-and-drop is complete
                onDrop: function (table, row) {
                    // Remove special style from row
                    $(row).removeClass('tablednd-dragging-row');

                    // Update row numbers
                    self.updateRowNumbers();

                    // Recalculate totals
                    self.calculateTotals();

                    // Update internal info table
                    self.updateInternalInfo();
                }
            });
        },

        /**
         * Cache DOM elements for better performance
         */
        cacheElements: function () {
            this.elements.$search = $('#quote-product-search');
            this.elements.$suggestions = $('#quote-product-suggestions');
            this.elements.$productsTable = $('#quote-products-table');
            this.elements.$emailModal = $('#quote-email-modal');
        },

        /**
         * Set up all event listeners
         */
        setupEventListeners: function () {
            // Store reference to this
            const self = this;

            // === SEARCH FUNCTIONALITY === //
            this.elements.$search.on('keyup', function (e) {
                self.handleSearch(e);
            });
            
            $(document).on('click', '.suggestion-item', function (e) {
                self.handleProductSelect(e);
            });
            
            // Close product suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.quote-search-wrap').length && 
                    !$(e.target).closest('.quote-suggestions').length) {
                    self.elements.$suggestions.slideUp().empty();
                }
            });

            // === PRODUCT TABLE FUNCTIONALITY === //
            $(document).on('click', '.remove-row', this.handleRemoveRow.bind(this));
            $(document).on('click', '.add-manual-product', this.handleAddManualProduct.bind(this));
            $(document).on('keydown', '#quote-products-table input, #quote-product-search', this.preventEnterSubmit.bind(this));

            // === PRICE FIELD HANDLERS === //
            // Add keydown handler to catch decimal separators immediately
            $(document).on('keydown', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function (e) {
                // Check specifically for decimal separator keys
                if (e.key === '.' || e.key === ',') {
                    const $this = $(this);

                    // If this isn't the correct decimal separator, prevent input and show tooltip
                    if (e.key !== quoteManagerData.decimalSeparator) {
                        e.preventDefault();

                        // Show tooltip immediately
                        if (!$this.data('tooltip-added')) {
                            const tooltip = $('<div class="price-tooltip">' +
                                quoteManagerData.i18n.decimalSeparatorWarning.replace('%s', quoteManagerData.decimalSeparator) +
                                '</div>');

                            // Position the tooltip directly under the input field
                            tooltip.css({
                                top: $this.offset().top + $this.outerHeight() + 8,
                                left: $this.offset().left
                            });

                            $('body').append(tooltip);
                            $this.data('tooltip', tooltip);
                            $this.data('tooltip-added', true);

                            // Add the visible class to trigger the animation and add error class to the input
                            setTimeout(function () {
                                tooltip.addClass('visible');
                                $this.addClass('price-error');
                            }, 10);
                        } else {
                            $this.data('tooltip').addClass('visible');
                            $this.addClass('price-error');
                        }

                        return false;
                    }

                    // Check if there's already a decimal separator (prevent multiple)
                    const value = $this.val();
                    if (value.includes(quoteManagerData.decimalSeparator)) {
                        e.preventDefault();
                        return false;
                    }
                }
            });

            // Price and quantity change handlers
            $(document).on('input change', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[qty]"], input[name*="[purchase_price]"]', function (e) {
                const $row = $(this).closest('tr');
                self.recalcRowPrices($row);
                self.updateInternalInfo();
            });

            // Real-time validation for price fields
            $(document).on('input', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function (e) {
                const $this = $(this);
                const value = $this.val().trim();

                // Check character being typed
                const lastChar = value.slice(-1);
                let allowInput = true;
                let showError = false;

                // If a decimal separator is typed, verify it's the correct one
                if (lastChar === '.' || lastChar === ',') {
                    // Check if this is the correct decimal separator
                    if (lastChar !== quoteManagerData.decimalSeparator) {
                        showError = true;

                        // Prevent further input until correction
                        allowInput = false;
                    }

                    // Check if there's already a decimal separator
                    const decimalCount = (value.match(new RegExp('\\' + quoteManagerData.decimalSeparator, 'g')) || []).length;
                    if (decimalCount > 1) {
                        showError = true;
                        allowInput = false;
                    }
                } else if (value) {
                    // For any input, check if there's an incorrect decimal separator already present
                    const wrongSeparator = quoteManagerData.decimalSeparator === ',' ? '.' : ',';
                    if (value.indexOf(wrongSeparator) !== -1) {
                        showError = true;
                        allowInput = false;
                    }

                    // Only allow numbers and the correct decimal separator
                    if (!/^\d$/.test(lastChar) && lastChar !== quoteManagerData.decimalSeparator) {
                        showError = true;
                        allowInput = false;
                    }
                }

                // Show/hide tooltip based on validation
                if (showError) {
                    if (!$this.data('tooltip-added')) {
                        const tooltip = $('<div class="price-tooltip">' +
                            quoteManagerData.i18n.decimalSeparatorWarning.replace('%s', quoteManagerData.decimalSeparator) +
                            '</div>');

                        // Position the tooltip directly under the input field
                        tooltip.css({
                            top: $this.offset().top + $this.outerHeight() + 8,
                            left: $this.offset().left
                        });

                        $('body').append(tooltip);
                        $this.data('tooltip', tooltip);
                        $this.data('tooltip-added', true);

                        // Add the visible class to trigger the animation and add error class to the input
                        setTimeout(function () {
                            tooltip.addClass('visible');
                            $this.addClass('price-error');
                        }, 10);
                    } else {
                        $this.data('tooltip').addClass('visible');
                        $this.addClass('price-error');
                    }
                } else if ($this.data('tooltip-added')) {
                    $this.data('tooltip').removeClass('visible');
                    $this.removeClass('price-error');
                }

                // If input is not allowed, prevent it
                if (!allowInput) {
                    // Remove the last character
                    const newValue = value.substring(0, value.length - 1);
                    $this.val(newValue);

                    // Prevent default behavior
                    e.preventDefault();
                    return false;
                }
            });

            $(document).on('focus', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function () {
                $(this).data('original-value', $(this).val());
            });

            $(document).on('blur', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function () {
                const $this = $(this);
                let value = $this.val().trim();

                // Remove tooltip with animation
                if ($this.data('tooltip-added')) {
                    $this.data('tooltip').removeClass('visible');
                    $this.removeClass('price-error');

                    // Delay the removal to allow for animation
                    setTimeout(function () {
                        $this.data('tooltip').remove();
                        $this.removeData('tooltip');
                        $this.removeData('tooltip-added');
                    }, 200);
                }

                // Parse and format value
                let numValue;

                // Replace incorrect decimal separator if found
                if (quoteManagerData.decimalSeparator === ',') {
                    // If user used dot instead of comma, replace it
                    if (value.indexOf('.') !== -1 && value.indexOf(',') === -1) {
                        value = value.replace('.', ',');
                    }
                    numValue = parseFloat(value.replace(/\./g, '').replace(',', '.'));
                } else {
                    // If user used comma instead of dot, replace it
                    if (value.indexOf(',') !== -1 && value.indexOf('.') === -1) {
                        value = value.replace(',', '.');
                    }
                    numValue = parseFloat(value.replace(/,/g, ''));
                }

                if (!isNaN(numValue) && numValue >= 0) {
                    $this.val(self.formatPrice(numValue));

                    // Trigger recalculation
                    const $row = $this.closest('tr');
                    self.recalcRowPrices($row);
                    self.updateInternalInfo();
                } else {
                    $this.val('');
                }
            });

            // === VAT CHECKBOX === //
            $('input[name="quote_include_vat"]').on('change', function () {
                self.config.includeVAT = $(this).is(':checked');

                if (self.config.includeVAT) {
                    $('.quote-th-final-incl, .quote-td-final-incl').show();
                    $('.vat-row').show();
                    $('.quote-td-label').attr('colspan', '10');
                } else {
                    $('.quote-th-final-incl, .quote-td-final-incl').hide();
                    $('.vat-row').hide();
                    $('.quote-td-label').attr('colspan', '9');
                }

                $('#quote-products-table').attr('data-vat-status', self.config.includeVAT ? 'enabled' : 'disabled');

                self.elements.$productsTable.find('tbody tr').each(function () {
                    self.recalcRowPrices($(this));
                });

                self.calculateTotals();
                self.updateInternalInfo();
            });

            // === IMAGE SELECTION === //
            $(document).on('click', '.quote-img-wrapper', this.handleImageClick.bind(this));
            $(document).on('click', '.custom-media-btn', this.openMediaLibrary.bind(this));
            $(document).on('click', '.custom-url-btn', this.openUrlInput.bind(this));
            $(document).on('click', '.custom-url-confirm', this.confirmImageUrl.bind(this));
            $(document).on('click', '.custom-url-cancel, .custom-cancel-btn', this.closeImageModals.bind(this));

            // === EMAIL HANDLERS === //
            $('#quote-send-email').on('click', this.openEmailModal.bind(this));
            $('#cancel-send-email, #close-offer-modal').on('click', this.closeEmailModal.bind(this));
            $('#confirm-send-email').on('click', this.sendEmail.bind(this));

            // Email logs
            $(document).on('click', '.view-full-message', this.openMessageModal.bind(this));
            $(document).on('click', '.close-message-modal', this.closeMessageModal.bind(this));
            $(document).on('click', '.email-message-modal', this.handleBackgroundClick.bind(this));
        },

        // Add the handleAddressInteractions method here, at the same level as other methods
        handleAddressInteractions: function () {
            // Copy billing address to shipping fields
            $('#copy-billing-address').on('click', function (e) {
                e.preventDefault();

                $('#shipping_first_name').val($('#customer_first_name').val());
                $('#shipping_last_name').val($('#customer_last_name').val());
                $('#shipping_company').val($('#customer_company').val());
                $('#shipping_address').val($('#customer_address').val());
                $('#shipping_city').val($('#customer_city').val());
                $('#shipping_postcode').val($('#customer_postcode').val());

                // Handle country and state selects
                $('#shipping_country').val($('#customer_country').val());

                // If state is a select, update it
                if ($('#shipping_state').is('select') && $('#customer_state').is('select')) {
                    $('#shipping_state').val($('#customer_state').val());
                } else {
                    $('#shipping_state').val($('#customer_state').val());
                }

                $('#shipping_phone').val($('#customer_phone').val());
            });

            // Country change event handler to update states
            $('#customer_country, #shipping_country').on('change', function () {
                var isShipping = this.id === 'shipping_country';
                var countryField = $(this);
                var stateField = isShipping ? $('#shipping_state') : $('#customer_state');
                var selectedCountry = $(this).val();

                // Make AJAX call to get states for the selected country
                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'quote_manager_get_states',
                        country: selectedCountry,
                        security: quoteManagerData.statesNonce
                    },
                    type: 'POST',
                    success: function (response) {
                        if (response.success && response.data) {
                            var states = response.data;
                            var stateSelect = $('<select class="quote-select" id="' + stateField.attr('id') + '" name="' + stateField.attr('name') + '"></select>');

                            // Add state options
                            $.each(states, function (code, name) {
                                stateSelect.append($('<option></option>').attr('value', code).text(name));
                            });

                            // Replace the input with select
                            stateField.replaceWith(stateSelect);
                        } else {
                            // If no states, replace with a text input
                            var stateInput = $('<input type="text" class="quote-input" id="' + stateField.attr('id') + '" name="' + stateField.attr('name') + '" value="">');
                            stateField.replaceWith(stateInput);
                        }
                    }
                });
            });


             // Customer search functionality
            // Variables for customer search
            let customerTypingTimer = null;
            const customerDoneTypingInterval = 300; // ms
            
            // Customer search functionality - real time
            $('#search-customer').on('keyup', function(e) {
                clearTimeout(customerTypingTimer);
                
                const term = $(this).val().trim();
                if (term.length < 2) {
                    $('#customer-suggestions').slideUp().empty();
                    return;
                }
                
                customerTypingTimer = setTimeout(function() {
                    performCustomerSearch(term);
                }, customerDoneTypingInterval);
            });
            
            // Function to perform customer search via AJAX
            function performCustomerSearch(term) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'quote_manager_search_customers',
                        term: term,
                        security: $('#search_customers_nonce').val() // Add nonce
                    },
                    success: function(customers) {
                        handleCustomerResults(customers);
                    },
                    error: function() {
                        $('#customer-suggestions').slideUp().empty();
                    }
                });
            }
            
            // Handle customer search results
            function handleCustomerResults(customers) {
                const $suggestions = $('#customer-suggestions');
                
                if (customers && customers.length) {
                    let html = '<ul class="customer-list">';
                    
                    customers.forEach(function(customer) {
                        // Get customer details
                        const firstName = customer.data.first_name || '';
                        const lastName = customer.data.last_name || '';
                        const fullName = (firstName + ' ' + lastName).trim();
                        const company = customer.data.company || '';
                        const email = customer.data.email || '';
                        
                        // Create avatar with initials
                        let initials = '';
                        if (firstName) initials += firstName.charAt(0).toUpperCase();
                        if (lastName) initials += lastName.charAt(0).toUpperCase();
                        if (!initials) initials = '?';
                        
                        // Encode the customer data as an attribute
                        const customerDataAttr = encodeURIComponent(JSON.stringify(customer.data));
                        
                        html += `
                            <li class="customer-item" data-customer="${customerDataAttr}">
                                <div class="customer-avatar">${initials}</div>
                                <div class="customer-info">
                                    <span class="customer-name">${fullName || 'Unknown Customer'}</span>
                                    <div class="customer-details">
                                        ${company ? '<span class="customer-company">' + company + '</span>' : ''}
                                        <span class="customer-email">${email}</span>
                                    </div>
                                </div>
                            </li>
                        `;
                    });
                    
                    html += '</ul>';
                    $suggestions.html(html).slideDown();
                } else {
                    $suggestions.html('<div class="no-customers-found">No customers found matching your search</div>').slideDown();
                    
                    // Hide after a short delay if no results
                    setTimeout(function() {
                        $suggestions.slideUp();
                    }, 3000);
                }
            }
            
            // Handle customer selection
            $(document).on('click', '.customer-item', function() {
                // Get the customer data
                const customerDataEncoded = $(this).data('customer');
                const customerData = JSON.parse(decodeURIComponent(customerDataEncoded));
                
                // Fill in the billing fields
                $('#customer_first_name').val(customerData.first_name);
                $('#customer_last_name').val(customerData.last_name);
                $('#customer_company').val(customerData.company);
                $('#customer_address').val(customerData.address);
                $('#customer_city').val(customerData.city);
                $('#customer_postcode').val(customerData.postcode);
                $('#customer_email').val(customerData.email);
                $('#customer_phone').val(customerData.phone);
                
                // Set country and trigger change event to load states
                if (customerData.country) {
                    $('#customer_country').val(customerData.country).trigger('change');
                    
                    // Add small delay to allow states to load before setting state
                    setTimeout(function() {
                        if (customerData.state) {
                            if ($('#customer_state').is('select')) {
                                $('#customer_state').val(customerData.state);
                            } else {
                                $('#customer_state').val(customerData.state);
                            }
                        }
                    }, 500);
                }
            /*    
                // Fill shipping fields if available (for the quote purpose this its not needed, as the admin can hit the Copy billing address button)
                if (customerData.shipping_first_name || customerData.shipping_last_name) {
                    $('#shipping_first_name').val(customerData.shipping_first_name);
                    $('#shipping_last_name').val(customerData.shipping_last_name);
                    $('#shipping_company').val(customerData.shipping_company);
                    $('#shipping_address').val(customerData.shipping_address);
                    $('#shipping_city').val(customerData.shipping_city);
                    $('#shipping_postcode').val(customerData.shipping_postcode);
                    $('#shipping_phone').val(customerData.shipping_phone);
                    
                    // Set shipping country and state
                    if (customerData.shipping_country) {
                        $('#shipping_country').val(customerData.shipping_country).trigger('change');
                        
                        // Add small delay for states
                        setTimeout(function() {
                            if (customerData.shipping_state) {
                                if ($('#shipping_state').is('select')) {
                                    $('#shipping_state').val(customerData.shipping_state);
                                } else {
                                    $('#shipping_state').val(customerData.shipping_state);
                                }
                            }
                        }, 500);
                    }
                }
            */    
                // Hide the suggestions
                $('#customer-suggestions').slideUp().empty();
                
                // Clear the search input
                $('#search-customer').val('');
                
                // Show success message
                const $status = $('<div class="notice notice-success inline" style="margin:8px 0;padding:6px 12px;"><p>✓ Customer data loaded successfully</p></div>')
                    .insertAfter('#search-customer')
                    .delay(3000)
                    .fadeOut(500, function() { $(this).remove(); });
            });
            
            // Close customer suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.customer-search-wrap').length) {
                    $('#customer-suggestions').slideUp();
                }
            });

            // Save as customer button
            $('#save-as-customer-btn').on('click', function () {
                var $button = $(this);
                var $status = $('#save-customer-status');

                // Disable button to prevent multiple clicks
                $button.prop('disabled', true).addClass('button-busy');

                // Show loading status
                $status.removeClass('success error').text(quoteManagerData.i18n.creatingCustomer || 'Creating customer...').show();

                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'quote_manager_create_customer',
                        quote_id: quoteManagerData.quoteId,
                        security: quoteManagerData.createCustomerNonce
                    },
                    success: function (response) {
                        if (response.success) {
                            // Show success message
                            $status.html(
                                '<span class="dashicons dashicons-yes"></span> ' +
                                (quoteManagerData.i18n.customerCreated || 'Customer created successfully.') +
                                ' <a href="' + response.data.edit_url + '" target="_blank">' +
                                (quoteManagerData.i18n.viewCustomer || 'View customer') +
                                '</a>'
                            ).removeClass('error').addClass('success');
                        } else {
                            // Show error message
                            $status.html(
                                '<span class="dashicons dashicons-warning"></span> ' +
                                (response.data.message || quoteManagerData.i18n.errorCreatingCustomer)
                            ).removeClass('success').addClass('error');
                        }
                    },
                    error: function () {
                        // Show error message
                        $status.html(
                            '<span class="dashicons dashicons-warning"></span> ' +
                            (quoteManagerData.i18n.errorCreatingCustomer || 'Error creating customer.')
                        ).removeClass('success').addClass('error');
                    },
                    complete: function () {
                        // Re-enable button
                        $button.prop('disabled', false).removeClass('button-busy');
                    }
                });
            });
        },

        /**
         * Initialize the table numbers and calculations
         */
        initializeTable: function () {
            this.updateRowNumbers();

            // Store reference to this
            const self = this;

            // Calculate prices for each row before calculating totals
            this.elements.$productsTable.find('tbody tr').each(function () {
                self.recalcRowPrices($(this));
            });

            // Set initial visibility based on VAT setting
            if (this.config.includeVAT) {
                $('.quote-th-final-incl, .quote-td-final-incl').show();
                $('.vat-row').show();
            } else {
                $('.quote-th-final-incl, .quote-td-final-incl').hide();
                $('.vat-row').hide();
            }

            // Calculate totals
            this.calculateTotals();
        },

        /**
         * Create modal elements if they don't exist
         */
        createModalElements: function () {
            if ($('#custom-image-choice-modal').length === 0) {
                $('body').append(`
                    <div id="custom-image-choice-modal" class="custom-modal-overlay" style="display:none;">
                        <div class="custom-modal-content">
                            <h3>${quoteManagerData.i18n.selectImage}</h3>
                            <button class="custom-media-btn">📁 ${quoteManagerData.i18n.mediaLibrary}</button>
                            <button class="custom-url-btn">🌐 ${quoteManagerData.i18n.fromURL}</button>
                            <button class="custom-cancel-btn">❌ ${quoteManagerData.i18n.cancel}</button>
                        </div>
                    </div>
                    <div id="custom-url-modal" class="custom-modal-overlay" style="display:none;">
                        <div class="custom-modal-content">
                            <h3>${quoteManagerData.i18n.enterImageURL}</h3>
                            <input type="text" id="custom-url-input" placeholder="https://example.com/image.jpg" style="width:100%;padding:8px;margin-bottom:15px;">
                            <button class="custom-url-confirm">✅ ${quoteManagerData.i18n.confirm}</button>
                            <button class="custom-url-cancel">❌ ${quoteManagerData.i18n.cancel}</button>
                        </div>
                    </div>
                `);
            }
        },

        /**
         * Initialize TinyMCE editor if available
         */
        initEditor: function () {
            if (typeof tinyMCE !== 'undefined' && $('#quote_email_message').length) {
                tinyMCE.init({
                    selector: '#quote_email_message',
                    height: 200,
                    menubar: false,
                    plugins: 'lists link',
                    toolbar: 'bold italic | bullist numlist | link'
                });
            }
        },

        /**
         * Format a number according to WooCommerce settings
         */
        formatPrice: function (price) {
            if (price === '' || price === null || isNaN(price)) return '';

            return price.toFixed(quoteManagerData.decimals)
                .replace('.', quoteManagerData.decimalSeparator) // Replace decimal point
                .replace(/\B(?=(\d{3})+(?!\d))/g, quoteManagerData.thousandSeparator); // Add thousand separators
        },

        /**
         * Parse a formatted price string back to a number
         */
        parsePrice: function (priceString) {
            if (!priceString) return 0;

            // Get a clean string
            let cleanString = priceString.toString().trim();

            // Remove currency symbol
            if (quoteManagerData.currencySymbol) {
                cleanString = cleanString.replace(new RegExp('\\' + quoteManagerData.currencySymbol, 'g'), '');
            }

            // Handle decimal separators consistently
            if (quoteManagerData.decimalSeparator === ',') {
                // For European format (1.000,00)
                // First, handle case where user entered with wrong decimal separator
                if (cleanString.indexOf('.') !== -1 && cleanString.indexOf(',') === -1) {
                    // User entered 65.25 instead of 65,25
                    return parseFloat(cleanString);
                }
                // Normal case, convert European format to standard float
                cleanString = cleanString.replace(/\./g, '').replace(',', '.');
            } else {
                // For US format (1,000.00)
                // First, handle case where user entered with wrong decimal separator
                if (cleanString.indexOf(',') !== -1 && cleanString.indexOf('.') === -1) {
                    // User entered 65,25 instead of 65.25
                    return parseFloat(cleanString.replace(',', '.'));
                }
                // Normal case, just remove thousand separators
                cleanString = cleanString.replace(/,/g, '');
            }

            // Remove any remaining non-numeric chars except decimal point
            cleanString = cleanString.replace(/[^\d.-]/g, '');

            return parseFloat(cleanString) || 0;
        },

        /**
         * Handle product search input
         */
        handleSearch: function (e) {
            clearTimeout(this.typingTimer);
            const term = this.elements.$search.val().trim();
            if (term.length < 2) {
                this.elements.$suggestions.slideUp().empty();
                return;
            }

            this.typingTimer = setTimeout(function () {
                this.performSearch(term);
            }.bind(this), 300);
        },

        /**
         * Perform AJAX search for products
         */
        performSearch: function (term) {
            $.ajax({
                type: 'POST',
                url: this.config.ajaxUrl,
                dataType: 'json',
                data: {
                    action: 'quote_manager_search_products',
                    term: term
                },
                success: this.handleSearchResults.bind(this),
                error: function (xhr, status, error) {
                    console.error(quoteManagerData.i18n.productSearchFailed, error);
                }
            });
        },

        /**
         * Handle search results
         */
        handleSearchResults: function (products) {
            if (products && products.length) {
                let html = '<ul class="suggestion-list">';
                products.forEach(function (product) {
                    const safeTitle = $('<div>').text(product.title).html();
                    const safeSku = $('<div>').text(product.sku).html();
                    // Determine if there's a discount by comparing regular_price and price
                    const hasDiscount = product.regular_price > product.price;
                    
                    html += `
                        <li class="suggestion-item"
                            data-id="${product.id}"
                            data-title="${safeTitle}"
                            data-sku="${safeSku}"
                            data-price="${product.price}"
                            data-regular_price="${product.regular_price}"
                            data-purchase_price="${product.purchase_price || ''}"
                            data-image="${product.image}">
                            <div class="suggestion-item-image">
                                <img src="${product.image}" alt="${safeTitle}" />
                            </div>
                            <div class="suggestion-item-details">
                                <span class="suggestion-item-title">${safeTitle}</span>
                                <div class="suggestion-item-meta">
                                    <span class="suggestion-item-sku">SKU: <strong>${safeSku}</strong></span>
                                    ${hasDiscount ? '<span class="suggestion-item-discount">On Sale</span>' : ''}
                                </div>
                            </div>
                        </li>`;
                });
                html += '</ul>';
                this.elements.$suggestions.html(html).slideDown();
            } else {
                this.elements.$suggestions.slideUp().empty();
            }
        },

        /**
         * Handle product selection from search results
         */
        handleProductSelect: function (e) {
            const $item = $(e.currentTarget);
            
            const productData = {
                id: $item.data('id'),
                image: $item.data('image'),
                title: $item.data('title'),
                sku: $item.data('sku'),
                price: $item.data('price'),
                regular_price: $item.data('regular_price'),
                purchase_price: $item.data('purchase_price')
            };
        
            this.addProductRow(productData);
            this.elements.$suggestions.slideUp().empty();
            this.elements.$search.val('');
        },

        /**
         * Handle remove row button click
         */
        handleRemoveRow: function (e) {
            $(e.currentTarget).closest('tr').remove();
            this.updateRowNumbers();
            this.calculateTotals();

            // Update internal info table
            this.updateInternalInfo();
        },

        /**
         * Handle add manual product button click
         */
        handleAddManualProduct: function () {
            this.addProductRow({});
        },

        /**
         * Handle price or quantity change
         */
        handlePriceChange: function (e) {
            const $input = $(e.target);
            const $row = $input.closest('tr');

            this.recalcRowPrices($row);

            // Update internal info table
            this.updateInternalInfo();
        },

        /**
         * Prevent form submission on Enter key
         */
        preventEnterSubmit: function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Handle image wrapper click
         */
        handleImageClick: function (e) {
            this.currentWrapper = $(e.currentTarget);
            $('#custom-image-choice-modal').fadeIn(200);
        },

        /**
         * Open WordPress media library
         */
        openMediaLibrary: function () {
            $('#custom-image-choice-modal').fadeOut(200);
            this.media_frame = wp.media({
                title: quoteManagerData.i18n.selectImage,
                button: {text: quoteManagerData.i18n.useThisImage},
                multiple: false
            });

            this.media_frame.on('select', this.handleMediaSelection.bind(this));
            this.media_frame.open();
        },

        /**
         * Handle media selection
         */
        handleMediaSelection: function () {
            const attachment = this.media_frame.state().get('selection').first().toJSON();
            if (this.currentWrapper) {
                this.currentWrapper.find('.quote-img-selectable').attr('src', attachment.url);
                this.currentWrapper.find('.quote-img-input').val(attachment.url);
            }
        },

        /**
         * Open URL input modal
         */
        openUrlInput: function () {
            $('#custom-image-choice-modal').fadeOut(200);
            $('#custom-url-input').val('');
            $('#custom-url-modal').fadeIn(200);
        },

        /**
         * Confirm image URL
         */
        confirmImageUrl: function () {
            const url = $('#custom-url-input').val().trim();
            if (url && this.currentWrapper) {
                this.currentWrapper.find('.quote-img-selectable').attr('src', url);
                this.currentWrapper.find('.quote-img-input').val(url);
            }
            $('#custom-url-modal').fadeOut(200);
        },

        /**
         * Close image modals
         */
        closeImageModals: function () {
            $('#custom-url-modal, #custom-image-choice-modal').fadeOut(200);
        },

        /**
         * Open email modal
         */
        openEmailModal: function () {
            $('#quote-email-modal').fadeIn();
        },

        /**
         * Close email modal
         */
        closeEmailModal: function () {
            $('#quote-email-modal').fadeOut();
        },

        /**
         * Send email
         */
        sendEmail: function () {
            const quoteId = $('#modal-quote-id').val();
            const subject = $('#quote-email-subject').val();
            // Get content from TinyMCE if available, otherwise from textarea
            const message = typeof tinyMCE !== 'undefined' && tinyMCE.get('quote_email_message')
                ? tinyMCE.get('quote_email_message').getContent()
                : $('#quote_email_message').val();

            const statusEl = $('#send-quote-status');

            statusEl.text(quoteManagerData.i18n.sendingInProgress);

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'quote_manager_send_email',
                    quote_id: quoteId,
                    subject: subject,
                    message: message
                },
                success: function (res) {
                    if (res.success) {
                        statusEl.text(quoteManagerData.i18n.emailSentSuccess);
                        $('#quote-email-modal').fadeOut();
                    } else {
                        statusEl.text(quoteManagerData.i18n.emailSendError + ' ' + (res.data?.message || quoteManagerData.i18n.failedToSend));
                    }
                },
                error: function () {
                    statusEl.text(quoteManagerData.i18n.errorWhileSending);
                }
            });
        },

        /**
         * Open message modal
         */
        openMessageModal: function (e) {
            e.preventDefault();
            const index = $(e.currentTarget).data('message-index');
            $('#email-message-modal-' + index).fadeIn();
        },

        /**
         * Close message modal
         */
        closeMessageModal: function (e) {
            const index = $(e.currentTarget).data('message-index');
            $('#email-message-modal-' + index).fadeOut();
        },

        /**
         * Handle background click on modal
         */
        handleBackgroundClick: function (e) {
            if ($(e.target).hasClass('email-message-modal')) {
                $(e.target).fadeOut();
            }
        },

        /**
         * Add a new product row
         */
        addProductRow: function (product) {
            const index = this.getNextProductIndex();
            const image = product.image || this.config.wc_placeholder_img_src;
            const title = product.title || '';
            const sku = product.sku || '';
            const id = product.id || '';
            
            // Extract purchase price
            const purchasePrice = (typeof product.purchase_price !== 'undefined' && product.purchase_price !== null && product.purchase_price !== '')
                ? parseFloat(product.purchase_price)
                : '';
        
            const listPrice = (typeof product.regular_price !== 'undefined')
                ? parseFloat(product.regular_price).toFixed(2)
                : '';
            const finalExPrice = (typeof product.price !== 'undefined' && product.price !== null)
                ? parseFloat(product.price).toFixed(2)
                : listPrice;
            let discount = '';
            if (listPrice && parseFloat(listPrice) > 0) {
                const lp = parseFloat(listPrice);
                const fp = parseFloat(finalExPrice);
                if (fp < lp) {
                    const discPercent = (1 - (fp / lp)) * 100;
                    discount = discPercent.toFixed(2);
                }
            }
        
            const finalInclPrice = finalExPrice
                ? (parseFloat(finalExPrice) * (1 + this.config.taxRate)).toFixed(2)
                : '';
            const qty = product.qty || 1;

            // Format prices according to WooCommerce settings
            const formattedListPrice = listPrice ? this.formatPrice(parseFloat(listPrice)) : '';
            const formattedFinalExPrice = finalExPrice ? this.formatPrice(parseFloat(finalExPrice)) : '';
            const formattedFinalInclPrice = finalInclPrice ? this.formatPrice(parseFloat(finalInclPrice)) : '';
            const formattedDiscount = discount ? this.formatPrice(parseFloat(discount)) : '';

            const safeTitle = $('<div>').text(title).html();
            const safeSku = $('<div>').text(sku).html();

            const rowHtml = `
                <tr class="quote-product-row">
                    <td class="quote-td-number"></td>
                    <td class="quote-td-image">
                        <div class="quote-img-wrapper">
                            <img src="${image}" class="quote-img quote-img-selectable" />
                            <input type="hidden" name="quote_products[${index}][image]" value="${image}" class="quote-img-input" />
                        </div>
                    </td>
                    <td class="quote-td-title"><input type="text" class="quote-input" name="quote_products[${index}][title]" value="${safeTitle}" /></td>
                    <td class="quote-td-sku"><input type="text" class="quote-input" name="quote_products[${index}][sku]" value="${safeSku}" /></td>
                    <td class="quote-td-purchase"><input type="text" class="quote-input" name="quote_products[${index}][purchase_price]" value="${purchasePrice ? this.formatPrice(purchasePrice) : ''}" placeholder="${quoteManagerData.i18n.cost}" /></td>
                    <td class="quote-td-listprice"><input type="text" class="quote-input" name="quote_products[${index}][list_price]" value="${formattedListPrice}" /></td>
                    <td class="quote-td-discount"><input type="text" class="quote-input" name="quote_products[${index}][discount]" value="${formattedDiscount}" /></td>
                    <td class="quote-td-final-excl"><input type="text" class="quote-input" name="quote_products[${index}][final_price_excl]" value="${formattedFinalExPrice}" /></td>
                    <td class="quote-td-final-incl" ${!this.config.includeVAT ? 'style="display:none;"' : ''}>
                        <input type="text" class="quote-input" name="quote_products[${index}][final_price_incl]" value="${formattedFinalInclPrice}" readonly />
                    </td>
                    <td class="quote-td-qty"><input type="number" class="quote-input" name="quote_products[${index}][qty]" value="${qty}" /></td>
                    <td class="quote-td-total quote-line-total">0.00${quoteManagerData.currencySymbol}</td>
                    <td class="quote-td-remove">
                        <span class="remove-row" title="${quoteManagerData.i18n.remove}">✖</span>
                        <input type="hidden" name="quote_products[${index}][id]" value="${id}" />
                    </td>
                </tr>`;

            const $row = $(rowHtml);
            this.elements.$productsTable.find('tbody').prepend($row);
            this.updateRowNumbers();
            this.recalcRowPrices($row);

            // Update internal info table
            this.updateInternalInfo();
        },

        /**
         * Get the next available product index
         */
        getNextProductIndex: function () {
            let maxIndex = -1;
            this.elements.$productsTable.find('tbody tr').each(function () {
                const inputs = $(this).find('input[name^="quote_products["]');
                if (inputs.length) {
                    const name = inputs.first().attr('name');
                    const match = name.match(/^quote_products\[(\d+)\]/);
                    if (match && match[1]) {
                        const num = parseInt(match[1], 10);
                        if (num > maxIndex) {
                            maxIndex = num;
                        }
                    }
                }
            });
            return maxIndex + 1;
        },

        /**
         * Update row numbers
         */
        updateRowNumbers: function () {
            this.elements.$productsTable.find('tbody tr').each(function (index) {
                $(this).children('td').first().text((index + 1) + '#');
            });
        },

        /**
         * Recalculate row prices
         */
        recalcRowPrices: function ($row) {
            const $listInput = $row.find('input[name*="[list_price]"]');
            const $discountInput = $row.find('input[name*="[discount]"]');
            const $finalExInput = $row.find('input[name*="[final_price_excl]"]');
            const $finalInclInput = $row.find('input[name*="[final_price_incl]"]');
            const $qtyInput = $row.find('input[name*="[qty]"]');
            const $lineTotal = $row.find('.quote-line-total');
        
            const listVal = this.parsePrice($listInput.val()) || 0;
            const discountVal = this.parsePrice($discountInput.val()) || 0;
            const qtyVal = parseFloat($qtyInput.val()) || 0;
        
            // Update final price based on which field the user is editing
            if ($(document.activeElement).is($listInput) || $(document.activeElement).is($discountInput)) {
                let newFinalEx = listVal;
                if (listVal > 0 && discountVal > 0) {
                    newFinalEx = listVal * (1 - discountVal / 100);
                }
                $finalExInput.val(this.formatPrice(newFinalEx));
            }
        
            // Remove automatic discount calculation when editing final price
            // Only calculate discount when discount field is focused
            if ($(document.activeElement).is($discountInput) && listVal > 0) {
                // If user is editing the discount field directly, calculate final price
                const finalExVal = listVal * (1 - discountVal / 100);
                $finalExInput.val(this.formatPrice(finalExVal));
            }
        
            // Calculate final price with VAT
            const finalExVal = this.parsePrice($finalExInput.val()) || 0;
            const updatedFinalIncl = finalExVal * (1 + this.config.taxRate);
            $finalInclInput.val(this.formatPrice(updatedFinalIncl));
        
            // Calculate line total based on VAT setting
            const total = (this.config.includeVAT ? updatedFinalIncl : finalExVal) * qtyVal;
            $lineTotal.text(this.formatPrice(total) + quoteManagerData.currencySymbol);
        
            // Update page totals
            this.calculateTotals();
        },

        /**
         * Calculate all totals
         */
        calculateTotals: function () {
            let subtotal = 0;
            let vat = 0;

            const self = this;
            this.elements.$productsTable.find('tbody tr').each(function () {
                const $row = $(this);
                const qty = parseFloat($row.find('input[name*="[qty]"]').val()) || 0;
                const priceExcl = self.parsePrice($row.find('input[name*="[final_price_excl]"]').val()) || 0;

                if (qty > 0 && priceExcl > 0) {
                    subtotal += priceExcl * qty;
                }
            });

            // Calculate VAT only if included
            if (this.config.includeVAT) {
                vat = subtotal * this.config.taxRate;
            }

            // Total depends on whether VAT is included
            const total = this.config.includeVAT ? (subtotal + vat) : subtotal;

            $('.quote-td-subtotal').text(this.formatPrice(subtotal) + quoteManagerData.currencySymbol);
            $('.quote-td-vat').text(this.config.includeVAT ? this.formatPrice(vat) + quoteManagerData.currencySymbol : '-');
            $('.quote-td-total-all').text(this.formatPrice(total) + quoteManagerData.currencySymbol);

            // Show/hide VAT related elements
            if (this.config.includeVAT) {
                $('.vat-row').show();
                $('.quote-th-final-incl, .quote-td-final-incl').show();
            } else {
                $('.vat-row').hide();
                $('.quote-th-final-incl, .quote-td-final-incl').hide();
            }
        },

        /**
         * Update the internal information table
         */
        updateInternalInfo: function () {
            const self = this;
            const $internalTable = $('#quote-internal-table');

            if ($internalTable.length === 0) return; // Check if table exists

            // Clear the table
            $internalTable.find('tbody').empty();

            // Total values for the quote
            let totalCost = 0;
            let totalFinalPrice = 0;
            let totalMarkup = 0;
            let totalMargin = 0;
            let markupCount = 0;

            // Iterate through product table
            this.elements.$productsTable.find('tbody tr').each(function (index) {
                const $row = $(this);
                const productId = $row.find('input[name*="[id]"]').val() || '0';
                const title = $row.find('input[name*="[title]"]').val() || '';
                const costRaw = self.parsePrice($row.find('input[name*="[purchase_price]"]').val()) || 0;
                const finalPriceExcl = self.parsePrice($row.find('input[name*="[final_price_excl]"]').val()) || 0;
                const qty = parseInt($row.find('input[name*="[qty]"]').val()) || 1;

                // Calculate per-product totals
                const totalCostItem = costRaw * qty;
                const totalPriceItem = finalPriceExcl * qty;

                let markupPercent = 0;
                let marginPercent = 0;

                if (costRaw > 0 && finalPriceExcl > 0) {
                    // Calculate markup and margin per unit
                    markupPercent = ((finalPriceExcl - costRaw) / costRaw) * 100;
                    marginPercent = ((finalPriceExcl - costRaw) / finalPriceExcl) * 100;

                    // Add to totals
                    totalCost += totalCostItem;
                    totalFinalPrice += totalPriceItem;
                    totalMarkup += markupPercent;
                    totalMargin += marginPercent;
                    markupCount++;
                }

                // Display with VAT (for display only)
                let displayCost = self.formatPrice(costRaw) + quoteManagerData.currencySymbol;
                if (self.config.includeVAT) {
                    const withVat = self.formatPrice(costRaw * (1 + self.config.taxRate)) + quoteManagerData.currencySymbol;
                    displayCost += ' <small style="color:#888;">(' + quoteManagerData.i18n.inclVAT + ': ' + withVat + ')</small>';
                }

                // Create the table row
                const rowHtml = `
                    <tr class="quote-summary-row" style="border-bottom:1px solid #ccc;"
                        data-product-id="${productId}"
                        data-cost="${costRaw}"
                        data-price="${finalPriceExcl}"
                        data-qty="${qty}">
                        <td class="quote-td-num">${index + 1}</td>
                        <td class="quote-td-title">${title}</td>
                        <td class="quote-td-cost">${displayCost}</td>
                        <td class="quote-td-qty">${qty}</td>
                        <td class="quote-td-total-cost">${self.formatPrice(totalCostItem)}${quoteManagerData.currencySymbol}</td>
                        <td class="quote-td-total-price">${self.formatPrice(totalPriceItem)}${quoteManagerData.currencySymbol}</td>
                        <td class="quote-td-markup">${markupPercent > 0 ? self.formatPrice(markupPercent) + '%' : '-'}</td>
                        <td class="quote-td-margin">${marginPercent > 0 ? self.formatPrice(marginPercent) + '%' : '-'}</td>
                    </tr>
                `;

                $internalTable.find('tbody').append(rowHtml);
            });

            // Calculate averages and total profit
            const avgMarkup = markupCount > 0 ? totalMarkup / markupCount : 0;
            const avgMargin = markupCount > 0 ? totalMargin / markupCount : 0;
            const totalProfit = totalFinalPrice - totalCost;

            // Summary row
            const summaryHtml = `
                <tr class="quote-summary-total" style="font-weight:bold; background:#f0f0f0;">
                    <td colspan="2" class="quote-td-label" style="text-align:right;">${quoteManagerData.i18n.totals}:</td>
                    <td class="quote-td-cost-summary">-</td>
                    <td class="quote-td-qty-summary">-</td>
                    <td class="quote-td-cost-total">${this.formatPrice(totalCost)}${quoteManagerData.currencySymbol}</td>
                    <td class="quote-td-price-total">${this.formatPrice(totalFinalPrice)}${quoteManagerData.currencySymbol}</td>
                    <td class="quote-td-markup-avg">${this.formatPrice(avgMarkup)}%</td>
                    <td class="quote-td-margin-avg">${this.formatPrice(avgMargin)}%</td>
                </tr>
                <tr class="quote-summary-profit" style="font-weight:bold; background:#e8f9e8;">
                    <td colspan="5" class="quote-td-profit-label" style="text-align:right;">${quoteManagerData.i18n.totalNetProfit}:</td>
                    <td colspan="3" class="quote-td-profit">${this.formatPrice(totalProfit)}${quoteManagerData.currencySymbol}</td>
                </tr>
            `;

            $internalTable.find('tbody').append(summaryHtml);
        },

        /**
         * Handle file attachments
         */
        handleAttachments: function () {
            const self = this;

            // Add attachment button
            $('#add-attachment').on('click', function () {
                // Create a file input
                const fileInput = $('<input type="file" style="display:none;" />');
                $('body').append(fileInput);

                fileInput.on('change', function (e) {
                    if (this.files.length === 0) {
                        return;
                    }

                    const file = this.files[0];
                    const statusEl = $('#attachment-upload-status');

                    // Create form data
                    const formData = new FormData();
                    formData.append('action', 'quote_manager_upload_attachment');
                    formData.append('security', quoteManagerData.attachmentNonce);
                    formData.append('quote_id', quoteManagerData.quoteId);
                    formData.append('file', file);

                    // Show uploading status
                    statusEl.text(quoteManagerData.i18n.uploadingFile);

                    // Upload the file
                    $.ajax({
                        url: quoteManagerData.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                statusEl.text(quoteManagerData.i18n.fileUploaded);

                                // Add the file to the list
                                self.addAttachmentItem(response.data);

                                // Clear status after a delay
                                setTimeout(function () {
                                    statusEl.text('');
                                }, 3000);
                            } else {
                                statusEl.text(quoteManagerData.i18n.uploadError + ' ' + (response.data?.message || ''));
                            }
                        },
                        error: function () {
                            statusEl.text(quoteManagerData.i18n.uploadError + ' ' + quoteManagerData.i18n.errorWhileSending);
                        }
                    });

                    // Remove the file input
                    fileInput.remove();
                });

                // Trigger click on the file input
                fileInput.trigger('click');
            });

            // Remove attachment
            $(document).on('click', '.remove-attachment', function () {
                const $item = $(this).closest('.quote-attachment-item');
                const fileUrl = $item.find('input[name*="[url]"]').val();
                const attachmentId = $item.find('input[name*="[id]"]').val();

                // Confirm deletion
                if (confirm(quoteManagerData.i18n.confirmDeleteFile || 'Are you sure you want to delete this file?')) {
                    // Send AJAX request to delete the file
                    $.ajax({
                        url: quoteManagerData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'quote_delete_attachment',
                            security: quoteManagerData.attachmentDeleteNonce,
                            file_url: fileUrl,
                            attachment_id: attachmentId,
                            quote_id: quoteManagerData.quoteId
                        },
                        success: function (response) {
                            if (response.success) {
                                // Remove the item from the UI
                                $item.fadeOut(300, function () {
                                    $(this).remove();
                                });

                                if (response.data && response.data.partial) {
                                    alert(response.data.message);
                                }
                            } else {
                                // Show error message
                                alert(response.data.message || 'Error deleting file.');
                            }
                        },
                        error: function () {
                            alert(quoteManagerData.i18n.errorDeletingFile || 'Error deleting file.');
                        }
                    });
                }
            });
        },

        /**
         * Add attachment item to the list
         */
        addAttachmentItem: function (data) {
            const template = $('#attachment-item-template').html();
            const $list = $('#quote-attachment-list');

            // Get next index
            const index = this.getNextAttachmentIndex();

            // Get file icon based on type
            const icon = this.getFileIcon(data.type);

            // Get file type label
            const typeLabel = this.getFileTypeLabel(data.type);

            // Replace placeholders in template
            let item = template
                .replace(/{index}/g, index)
                .replace(/{id}/g, data.id)
                .replace(/{url}/g, data.url)
                .replace(/{filename}/g, data.filename)
                .replace(/{type}/g, data.type)
                .replace(/{icon}/g, icon)
                .replace(/{typelabel}/g, typeLabel);

            // Add to list
            $list.append(item);
        },

        /**
         * Get next attachment index
         */
        getNextAttachmentIndex: function () {
            let maxIndex = -1;
            $('.quote-attachment-item').each(function () {
                const index = parseInt($(this).data('index'), 10);
                if (!isNaN(index) && index > maxIndex) {
                    maxIndex = index;
                }
            });
            return maxIndex + 1;
        },

        /**
         * Get file icon based on mime type
         */
        getFileIcon: function (mimeType) {
            switch (mimeType) {
                case 'application/pdf':
                    return '📄';
                case 'application/msword':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    return '📝';
                case 'application/vnd.ms-excel':
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    return '📊';
                case 'application/vnd.ms-powerpoint':
                case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                    return '📺';
                case 'image/jpeg':
                case 'image/png':
                case 'image/gif':
                    return '🖼️';
                case 'application/zip':
                case 'application/x-rar-compressed':
                    return '📦';
                default:
                    return '📎';
            }
        },

        /**
         * Get file type label based on mime type
         */
        getFileTypeLabel: function (mimeType) {
            switch (mimeType) {
                case 'application/pdf':
                    return 'PDF Document';
                case 'application/msword':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    return 'Word Document';
                case 'application/vnd.ms-excel':
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    return 'Excel Spreadsheet';
                case 'application/vnd.ms-powerpoint':
                case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                    return 'PowerPoint Presentation';
                case 'image/jpeg':
                case 'image/png':
                case 'image/gif':
                    return 'Image';
                case 'application/zip':
                    return 'ZIP Archive';
                case 'application/x-rar-compressed':
                    return 'RAR Archive';
                default:
                    return 'File';
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        QuoteManager.init();
    });

})(jQuery);