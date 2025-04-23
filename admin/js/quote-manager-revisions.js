/**
 * Quote Manager Revisions JavaScript
 * Handles the revision functionality interface
 */
(function ($) {
    'use strict';

    // RevisionManager object
    const RevisionManager = {
        init: function () {
            this.setupEventListeners();
        },

        setupEventListeners: function () {
            // Create revision button
            $(document).on('click', '#create-revision-btn', this.handleCreateRevision);

            // Restore revision button
            $(document).on('click', '.restore-revision-btn', this.handleRestoreRevision);

            // View revision button
            $(document).on('click', '.view-revision-btn', this.handleViewRevision);

            // Compare revisions button
            $(document).on('click', '.compare-revisions-btn', this.handleCompareRevisions);

            // Modal close button
            $(document).on('click', '.quote-modal-close', this.closeModals);

            // Close modal when clicking outside
            $(document).on('click', '.quote-modal', function (e) {
                if ($(e.target).hasClass('quote-modal')) {
                    RevisionManager.closeModals();
                }
            });
        },

        handleCreateRevision: function (e) {
            e.preventDefault();
            const btn = $(this);
            const quoteId = btn.data('quote-id');
            const statusEl = $('#revision-status');

            // Prevent multiple clicks
            if (btn.hasClass('disabled')) return;
            btn.addClass('disabled').text('Creating...');
            statusEl.text('');

            // AJAX request to create revision
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'quote_manager_create_revision',
                    quote_id: quoteId
                },
                success: function (response) {
                    if (response.success) {
                        statusEl.html('<span style="color:green;">' + response.data.message + '</span>');

                        // Reload the page to show the new revision
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        statusEl.html('<span style="color:red;">' + response.data.message + '</span>');
                        btn.removeClass('disabled').text('Create Revision');
                    }
                },
                error: function () {
                    statusEl.html('<span style="color:red;">An error occurred. Please try again.</span>');
                    btn.removeClass('disabled').text('Create Revision');
                }
            });
        },

        handleRestoreRevision: function (e) {
            e.preventDefault();
            const btn = $(this);
            const revisionId = btn.data('revision-id');
            const quoteId = btn.data('quote-id');

            // Confirm restore
            if (!confirm('Are you sure you want to restore this revision? This will replace the current quote data.')) {
                return;
            }

            // Prevent multiple clicks
            if (btn.hasClass('disabled')) return;
            btn.addClass('disabled').text('Restoring...');

            // AJAX request to restore revision
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'quote_manager_restore_revision',
                    quote_id: quoteId,
                    revision_id: revisionId
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data.message);

                        // Reload the page to show the restored data
                        location.reload();
                    } else {
                        alert(response.data.message);
                        btn.removeClass('disabled').text('Restore');
                    }
                },
                error: function () {
                    alert('An error occurred. Please try again.');
                    btn.removeClass('disabled').text('Restore');
                }
            });
        },

        handleViewRevision: function (e) {
            e.preventDefault();
            const btn = $(this);
            const revisionId = btn.data('revision-id');
            const modal = $('#revision-view-modal');
            const contentEl = $('#revision-view-content');

            // Clear content and show loading
            contentEl.html('<p>Loading revision data...</p>');
            modal.show();

            // AJAX request to get revision details
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'quote_manager_get_revision_details',
                    revision_id: revisionId
                },
                success: function (response) {
                    if (response.success) {
                        // Update modal title
                        modal.find('h2').text('Revision v' + response.data.revision_number + ' Details');

                        // Update content
                        contentEl.html(response.data.revision_data);
                    } else {
                        contentEl.html('<p style="color:red;">' + response.data.message + '</p>');
                    }
                },
                error: function () {
                    contentEl.html('<p style="color:red;">An error occurred while retrieving revision data.</p>');
                }
            });
        },

        handleCompareRevisions: function (e) {
            e.preventDefault();
            const btn = $(this);
            const rev1 = btn.data('rev1');
            const rev2 = btn.data('rev2');
            const modal = $('#revision-compare-modal');
            const contentEl = $('#revision-compare-content');

            // Clear content and show loading
            contentEl.html('<p>Comparing revisions...</p>');
            modal.show();

            // AJAX request to compare revisions
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'quote_manager_compare_revisions',
                    revision_id_1: rev1,
                    revision_id_2: rev2
                },
                success: function (response) {
                    if (response.success) {
                        const diff = response.data.differences;
                        const rev1Info = response.data.revision_1;
                        const rev2Info = response.data.revision_2;

                        // Update modal title
                        modal.find('h2').text('Compare Revisions: v' + rev1Info.number + ' vs v' + rev2Info.number);

                        // Create comparison content
                        let html = '<div class="revision-comparison">';
                        html += '<div class="revision-info">';
                        html += '<div class="revision-from">From: v' + rev2Info.number + ' (' + rev2Info.date + ')</div>';
                        html += '<div class="revision-to">To: v' + rev1Info.number + ' (' + rev1Info.date + ')</div>';
                        html += '</div>';

                        // If no differences found
                        if (Object.keys(diff).length === 0) {
                            html += '<div class="no-differences"><p>No differences found between these revisions.</p></div>';
                        } else {
                            // Display field changes
                            if (diff.fields && Object.keys(diff.fields).length > 0) {
                                html += '<div class="diff-section diff-fields">';
                                html += '<h3>Field Changes</h3>';
                                html += '<table class="widefat striped">';
                                html += '<thead><tr><th>Field</th><th>Old Value</th><th>New Value</th></tr></thead>';
                                html += '<tbody>';

                                for (const field in diff.fields) {
                                    html += '<tr>';
                                    html += '<td>' + field + '</td>';
                                    html += '<td>' + (diff.fields[field].old || '-') + '</td>';
                                    html += '<td>' + (diff.fields[field].new || '-') + '</td>';
                                    html += '</tr>';
                                }

                                html += '</tbody></table>';
                                html += '</div>';
                            }

                            // Display product changes
                            if (diff.products) {
                                // Added products
                                if (diff.products.added && diff.products.added.length > 0) {
                                    html += '<div class="diff-section diff-products-added">';
                                    html += '<h3>Added Products</h3>';
                                    html += '<table class="widefat striped">';
                                    html += '<thead><tr><th>Product</th><th>Price</th><th>Discount</th><th>Final Price</th><th>Quantity</th></tr></thead>';
                                    html += '<tbody>';

                                    diff.products.added.forEach(function (product) {
                                        html += '<tr>';
                                        html += '<td>' + product.title + '</td>';
                                        html += '<td>' + (product.list_price || '-') + '</td>';
                                        html += '<td>' + (product.discount ? product.discount + '%' : '-') + '</td>';
                                        html += '<td>' + (product.final_price_excl || '-') + '</td>';
                                        html += '<td>' + (product.qty || 1) + '</td>';
                                        html += '</tr>';
                                    });

                                    html += '</tbody></table>';
                                    html += '</div>';
                                }

                                // Removed products
                                if (diff.products.removed && diff.products.removed.length > 0) {
                                    html += '<div class="diff-section diff-products-removed">';
                                    html += '<h3>Removed Products</h3>';
                                    html += '<table class="widefat striped">';
                                    html += '<thead><tr><th>Product</th><th>Price</th><th>Discount</th><th>Final Price</th><th>Quantity</th></tr></thead>';
                                    html += '<tbody>';

                                    diff.products.removed.forEach(function (product) {
                                        html += '<tr>';
                                        html += '<td>' + product.title + '</td>';
                                        html += '<td>' + (product.list_price || '-') + '</td>';
                                        html += '<td>' + (product.discount ? product.discount + '%' : '-') + '</td>';
                                        html += '<td>' + (product.final_price_excl || '-') + '</td>';
                                        html += '<td>' + (product.qty || 1) + '</td>';
                                        html += '</tr>';
                                    });

                                    html += '</tbody></table>';
                                    html += '</div>';
                                }

                                // Modified products
                                if (diff.products.modified && diff.products.modified.length > 0) {
                                    html += '<div class="diff-section diff-products-modified">';
                                    html += '<h3>Modified Products</h3>';
                                    html += '<table class="widefat striped">';
                                    html += '<thead><tr><th>Product</th><th>Change</th><th>Old Value</th><th>New Value</th></tr></thead>';
                                    html += '<tbody>';

                                    diff.products.modified.forEach(function (product) {
                                        const rowspan = Object.keys(product).length - 1; // Minus 'title' key
                                        let isFirstRow = true;

                                        for (const field in product) {
                                            if (field === 'title') continue;

                                            html += '<tr>';

                                            if (isFirstRow) {
                                                html += '<td rowspan="' + rowspan + '">' + product.title + '</td>';
                                                isFirstRow = false;
                                            }

                                            html += '<td>' + field + '</td>';
                                            html += '<td>' + product[field].old + '</td>';
                                            html += '<td>' + product[field].new + '</td>';
                                            html += '</tr>';
                                        }
                                    });

                                    html += '</tbody></table>';
                                    html += '</div>';
                                }
                            }

                            // Display attachment changes
                            if (diff.attachments) {
                                // Added attachments
                                if (diff.attachments.added && diff.attachments.added.length > 0) {
                                    html += '<div class="diff-section diff-attachments-added">';
                                    html += '<h3>Added Attachments</h3>';
                                    html += '<ul>';

                                    diff.attachments.added.forEach(function (attachment) {
                                        html += '<li>' + attachment.filename + '</li>';
                                    });

                                    html += '</ul>';
                                    html += '</div>';
                                }

                                // Removed attachments
                                if (diff.attachments.removed && diff.attachments.removed.length > 0) {
                                    html += '<div class="diff-section diff-attachments-removed">';
                                    html += '<h3>Removed Attachments</h3>';
                                    html += '<ul>';

                                    diff.attachments.removed.forEach(function (attachment) {
                                        html += '<li>' + attachment.filename + '</li>';
                                    });

                                    html += '</ul>';
                                    html += '</div>';
                                }
                            }
                        }

                        html += '</div>'; // .revision-comparison

                        contentEl.html(html);
                    } else {
                        contentEl.html('<p style="color:red;">' + response.data.message + '</p>');
                    }
                },
                error: function () {
                    contentEl.html('<p style="color:red;">An error occurred while comparing revisions.</p>');
                }
            });
        },

        closeModals: function () {
            $('.quote-modal').hide();
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        RevisionManager.init();
    });

})(jQuery);