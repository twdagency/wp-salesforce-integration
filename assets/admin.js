jQuery(document).ready(function($) {
    'use strict';
    
    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });
    
    // Test connection functionality
    $('#test-connection').on('click', function() {
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text(wsi_admin.strings.testing).prop('disabled', true);
        
        $.ajax({
            url: wsi_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wsi_test_connection',
                nonce: wsi_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', wsi_admin.strings.connection_success);
                } else {
                    showNotice('error', response.data || wsi_admin.strings.connection_failed);
                }
            },
            error: function() {
                showNotice('error', wsi_admin.strings.connection_failed);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Field mapping functionality
    $('.add-mapping').on('click', function() {
        var $container = $(this).closest('.post-type-mapping');
        var postType = $container.data('post-type');
        var $list = $container.find('.field-mapping-list');
        
        var newRow = createFieldMappingRow(postType, '', '');
        $list.append(newRow);
    });
    
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.field-mapping-row').remove();
    });
    
    // Checkbox strategy change handler
    $(document).on('change', 'select[name*="[checkbox_strategy]"]', function() {
        var $row = $(this).closest('.field-mapping-row');
        var $customDelimiter = $row.find('.custom-delimiter');
        
        if ($(this).val() === 'custom_delimiter') {
            $customDelimiter.show();
        } else {
            $customDelimiter.hide();
        }
    });
    
    // Save field mappings
    $('#save-field-mappings').on('click', function() {
        var mappings = {};
        
        $('.field-mapping-row').each(function() {
            var $row = $(this);
            var fieldKey = $row.data('field');
            var salesforceField = $row.find('.salesforce-field').val();
            
            if (salesforceField) {
                mappings[fieldKey] = {
                    salesforce_field: salesforceField,
                    checkbox_strategy: $row.find('select[name*="[checkbox_strategy]"]').val() || '',
                    custom_delimiter: $row.find('input[name*="[custom_delimiter]"]').val() || ''
                };
            }
        });
        
        $.ajax({
            url: wsi_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wsi_save_field_mappings',
                nonce: wsi_admin.nonce,
                mappings: mappings
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Field mappings saved successfully!');
                } else {
                    showNotice('error', response.data || 'Failed to save field mappings');
                }
            },
            error: function() {
                showNotice('error', 'Failed to save field mappings');
            }
        });
    });
    
    // Manual sync functionality
    $(document).on('click', '.sync-post', function() {
        var $button = $(this);
        var postId = $button.data('post-id');
        var originalText = $button.text();
        
        $button.text('Syncing...').prop('disabled', true);
        
        $.ajax({
            url: wsi_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wsi_sync_single_post',
                nonce: wsi_admin.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', wsi_admin.strings.sync_success);
                } else {
                    showNotice('error', response.data || wsi_admin.strings.sync_failed);
                }
            },
            error: function() {
                showNotice('error', wsi_admin.strings.sync_failed);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Sortable field mapping rows
    $('.field-mapping-list').sortable({
        handle: '.mapping-field',
        placeholder: 'field-mapping-placeholder',
        update: function(event, ui) {
            // Optional: Save order via AJAX
        }
    });
    
    // Helper functions
    function createFieldMappingRow(postType, fieldKey, fieldLabel) {
        var rowHtml = '<div class="field-mapping-row" data-field="' + fieldKey + '">' +
            '<div class="mapping-field">' +
                '<strong>' + fieldLabel + '</strong>' +
                '<small>(' + fieldKey + ')</small>' +
            '</div>' +
            '<div class="mapping-controls">' +
                '<label>' +
                    'Salesforce Field: ' +
                    '<input type="text" name="wsi_field_mappings[' + fieldKey + '][salesforce_field]" ' +
                           'value="" class="salesforce-field" />' +
                '</label>' +
                '<button type="button" class="button remove-mapping">Remove</button>' +
            '</div>' +
        '</div>';
        
        return $(rowHtml);
    }
    
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    // Initialize checkbox strategy visibility
    $('select[name*="[checkbox_strategy]"]').each(function() {
        $(this).trigger('change');
    });
    
    // Auto-refresh sync logs every 30 seconds
    setInterval(function() {
        if ($('#sync-logs').hasClass('active')) {
            location.reload();
        }
    }, 30000);
});
