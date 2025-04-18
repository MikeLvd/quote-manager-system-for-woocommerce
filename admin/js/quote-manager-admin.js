/**
 * Quote Manager for WooCommerce - Admin JavaScript
 * Using module pattern for better organization and performance
 */
(function($) {
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
        init: function() {
            // Read initial VAT status from the table's data attribute
            this.config.includeVAT = $('#quote-products-table').attr('data-vat-status') === 'enabled';
            
            this.cacheElements();
            this.setupEventListeners();
            this.initializeTable();
            this.createModalElements();
            this.initSortableTable(); // Add call for sortable
            
            // Initialize internal info table
            this.updateInternalInfo();
            
            // Check if TinyMCE is available and initialize it for quote_email_message
            if (typeof tinyMCE !== 'undefined' && document.getElementById('quote_email_message')) {
                this.initEditor();
            }
        },

        /**
         * Initialize the table drag-and-drop functionality using TableDnD
         */
        initSortableTable: function() {
            const self = this;
            
            // Initialize TableDnD
            $('#quote-products-table').tableDnD({
                onDragClass: "dragging", // CSS class when dragging a row
                dragHandle: ".quote-td-number", // Use number column as drag handle
                
                // Customize draggable object
                onDragStart: function(table, row) {
                    // Add special style to row when drag starts
                    $(row).addClass('tablednd-dragging-row');
                    // Store original row index for use in onDrop
                    $(row).attr('data-original-index', $(row).index());
                },
                
                // Execute actions after drag-and-drop is complete
                onDrop: function(table, row) {
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
        cacheElements: function() {
            this.elements.$search = $('#quote-product-search');
            this.elements.$suggestions = $('#quote-product-suggestions');
            this.elements.$productsTable = $('#quote-products-table');
            this.elements.$emailModal = $('#quote-email-modal');
        },

        /**
         * Set up all event listeners
         */
        setupEventListeners: function() {
            // Store reference to this
            const self = this;
            
            // === SEARCH FUNCTIONALITY === //
            // Product search - these must be separate from other handlers
            this.elements.$search.on('keyup', function(e) {
                self.handleSearch(e);
            });
            
            $(document).on('click', '.suggestion-item', function(e) {
                self.handleProductSelect(e);
            });
            
            // === PRODUCT TABLE FUNCTIONALITY === //
            $(document).on('click', '.remove-row', this.handleRemoveRow.bind(this));
            $(document).on('click', '.add-manual-product', this.handleAddManualProduct.bind(this));
            $(document).on('keydown', '#quote-products-table input, #quote-product-search', this.preventEnterSubmit.bind(this));
            
            // === PRICE FIELD HANDLERS === //
            // Add keydown handler to catch decimal separators immediately
            $(document).on('keydown', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function(e) {
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
                            setTimeout(function() {
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
            $(document).on('input change', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[qty]"], input[name*="[purchase_price]"]', function(e) {
                const $row = $(this).closest('tr');
                self.recalcRowPrices($row);
                self.updateInternalInfo();
            });
            
            // Real-time validation for price fields
            $(document).on('input', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function(e) {
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
                        setTimeout(function() {
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
            
            $(document).on('focus', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function() {
                $(this).data('original-value', $(this).val());
            });
            
            $(document).on('blur', 'input[name*="[list_price]"], input[name*="[discount]"], input[name*="[final_price_excl]"], input[name*="[purchase_price]"]', function() {
                const $this = $(this);
                let value = $this.val().trim();
                
                // Remove tooltip with animation
                if ($this.data('tooltip-added')) {
                    $this.data('tooltip').removeClass('visible');
                    $this.removeClass('price-error');
                    
                    // Delay the removal to allow for animation
                    setTimeout(function() {
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
            $('input[name="quote_include_vat"]').on('change', function() {
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
                
                self.elements.$productsTable.find('tbody tr').each(function() {
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

        /**
         * Initialize the table numbers and calculations
         */
        initializeTable: function() {
            this.updateRowNumbers();
            
            // Store reference to this
            const self = this;
            
            // Calculate prices for each row before calculating totals
            this.elements.$productsTable.find('tbody tr').each(function() {
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
        createModalElements: function() {
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
        initEditor: function() {
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
        formatPrice: function(price) {
            if (price === '' || price === null || isNaN(price)) return '';
            
            return price.toFixed(quoteManagerData.decimals)
                .replace('.', quoteManagerData.decimalSeparator) // Replace decimal point
                .replace(/\B(?=(\d{3})+(?!\d))/g, quoteManagerData.thousandSeparator); // Add thousand separators
        },

        /**
         * Parse a formatted price string back to a number
         */
        parsePrice: function(priceString) {
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
        handleSearch: function(e) {
            clearTimeout(this.typingTimer);
            const term = this.elements.$search.val().trim();
            if (term.length < 2) {
                this.elements.$suggestions.slideUp().empty();
                return;
            }
            
            this.typingTimer = setTimeout(function() {
                this.performSearch(term);
            }.bind(this), 300);
        },

        /**
         * Perform AJAX search for products
         */
        performSearch: function(term) {
            $.ajax({
                type: 'POST',
                url: this.config.ajaxUrl,
                dataType: 'json',
                data: {
                    action: 'quote_manager_search_products',
                    term: term
                },
                success: this.handleSearchResults.bind(this),
                error: function(xhr, status, error) {
                    console.error(quoteManagerData.i18n.productSearchFailed, error);
                }
            });
        },

        /**
         * Handle search results
         */
        handleSearchResults: function(products) {
            if (products && products.length) {
                let html = '<ul class="suggestion-list">';
                products.forEach(function(product) {
                    const safeTitle = $('<div>').text(product.title).html();
                    const safeSku = $('<div>').text(product.sku).html();
                    html += `
                        <li class="suggestion-item"
                            data-id="${product.id}"
                            data-title="${safeTitle}"
                            data-sku="${safeSku}"
                            data-price="${product.price}"
                            data-regular_price="${product.regular_price}"
                            data-image="${product.image}">
                            <img src="${product.image}" />
                            <span>${safeTitle}</span>
                            <small>${safeSku}</small>
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
        handleProductSelect: function(e) {
            const $item = $(e.currentTarget);
            const productData = {
                id: $item.data('id'),
                image: $item.data('image'),
                title: $item.data('title'),
                sku: $item.data('sku'),
                price: $item.data('price'),
                regular_price: $item.data('regular_price')
            };
            
            this.addProductRow(productData);
            this.elements.$suggestions.slideUp().empty();
            this.elements.$search.val('');
        },

        /**
         * Handle remove row button click
         */
        handleRemoveRow: function(e) {
            $(e.currentTarget).closest('tr').remove();
            this.updateRowNumbers();
            this.calculateTotals();
            
            // Update internal info table
            this.updateInternalInfo();
        },

        /**
         * Handle add manual product button click
         */
        handleAddManualProduct: function() {
            this.addProductRow({});
        },

        /**
         * Handle price or quantity change
         */
        handlePriceChange: function(e) {
            const $input = $(e.target);
            const $row = $input.closest('tr');
            
            this.recalcRowPrices($row);
            
            // Update internal info table
            this.updateInternalInfo();
        },

        /**
         * Prevent form submission on Enter key
         */
        preventEnterSubmit: function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Handle image wrapper click
         */
        handleImageClick: function(e) {
            this.currentWrapper = $(e.currentTarget);
            $('#custom-image-choice-modal').fadeIn(200);
        },

        /**
         * Open WordPress media library
         */
        openMediaLibrary: function() {
            $('#custom-image-choice-modal').fadeOut(200);
            this.media_frame = wp.media({
                title: quoteManagerData.i18n.selectImage,
                button: { text: quoteManagerData.i18n.useThisImage },
                multiple: false
            });
            
            this.media_frame.on('select', this.handleMediaSelection.bind(this));
            this.media_frame.open();
        },

        /**
         * Handle media selection
         */
        handleMediaSelection: function() {
            const attachment = this.media_frame.state().get('selection').first().toJSON();
            if (this.currentWrapper) {
                this.currentWrapper.find('.quote-img-selectable').attr('src', attachment.url);
                this.currentWrapper.find('.quote-img-input').val(attachment.url);
            }
        },

        /**
         * Open URL input modal
         */
        openUrlInput: function() {
            $('#custom-image-choice-modal').fadeOut(200);
            $('#custom-url-input').val('');
            $('#custom-url-modal').fadeIn(200);
        },

        /**
         * Confirm image URL
         */
        confirmImageUrl: function() {
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
        closeImageModals: function() {
            $('#custom-url-modal, #custom-image-choice-modal').fadeOut(200);
        },

        /**
         * Open email modal
         */
        openEmailModal: function() {
            $('#quote-email-modal').fadeIn();
        },

        /**
         * Close email modal
         */
        closeEmailModal: function() {
            $('#quote-email-modal').fadeOut();
        },

        /**
         * Send email
         */
        sendEmail: function() {
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
                success: function(res) {
                    if (res.success) {
                        statusEl.text(quoteManagerData.i18n.emailSentSuccess);
                        $('#quote-email-modal').fadeOut();
                    } else {
                        statusEl.text(quoteManagerData.i18n.emailSendError + ' ' + (res.data?.message || quoteManagerData.i18n.failedToSend));
                    }
                },
                error: function() {
                    statusEl.text(quoteManagerData.i18n.errorWhileSending);
                }
            });
        },

        /**
         * Open message modal
         */
        openMessageModal: function(e) {
            e.preventDefault();
            const index = $(e.currentTarget).data('message-index');
            $('#email-message-modal-' + index).fadeIn();
        },

        /**
         * Close message modal
         */
        closeMessageModal: function(e) {
            const index = $(e.currentTarget).data('message-index');
            $('#email-message-modal-' + index).fadeOut();
        },

        /**
         * Handle background click on modal
         */
        handleBackgroundClick: function(e) {
            if ($(e.target).hasClass('email-message-modal')) {
                $(e.target).fadeOut();
            }
        },

        /**
         * Add a new product row
         */
        addProductRow: function(product) {
            const index = this.getNextProductIndex();
            const image = product.image || this.config.wc_placeholder_img_src;
            const title = product.title || '';
            const sku = product.sku || '';
            const id = product.id || '';

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
                    <td class="quote-td-purchase"><input type="text" class="quote-input" name="quote_products[${index}][purchase_price]" value="" placeholder="${quoteManagerData.i18n.cost}" /></td>
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
        getNextProductIndex: function() {
            let maxIndex = -1;
            this.elements.$productsTable.find('tbody tr').each(function() {
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
        updateRowNumbers: function() {
            this.elements.$productsTable.find('tbody tr').each(function(index) {
                $(this).children('td').first().text((index + 1) + '#');
            });
        },

        /**
         * Recalculate row prices
         */
        recalcRowPrices: function($row) {
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

            // Update discount based on final price
            if ($(document.activeElement).is($finalExInput) && listVal > 0) {
                const finalExVal = this.parsePrice($finalExInput.val());
                const newDiscount = (1 - (finalExVal / listVal)) * 100;
                $discountInput.val(this.formatPrice(newDiscount));
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
        calculateTotals: function() {
            let subtotal = 0;
            let vat = 0;

            const self = this;
            this.elements.$productsTable.find('tbody tr').each(function() {
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
        updateInternalInfo: function() {
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
            this.elements.$productsTable.find('tbody tr').each(function(index) {
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
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        QuoteManager.init();
    });

})(jQuery);