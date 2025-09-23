/**
 * Frontend Sync JavaScript for WordPress to Salesforce Integration
 */

(function($) {
    'use strict';
    
    // Initialize frontend sync functionality
    $(document).ready(function() {
        initFrontendSync();
    });
    
    function initFrontendSync() {
        // Auto-sync on form submission (if configured)
        $('.wsi-auto-sync').on('submit', function(e) {
            var $form = $(this);
            var postId = $form.data('post-id');
            
            if (postId) {
                e.preventDefault();
                syncPostAndSubmit(postId, $form);
            }
        });
        
        // Manual sync buttons
        $('.wsi-sync-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var postId = $button.data('post-id');
            
            if (postId) {
                syncPost(postId, $button);
            }
        });
        
        // Auto-sync after ACF field changes (if configured)
        if (typeof acf !== 'undefined') {
            acf.addAction('change', function(field) {
                var postId = field.closest('[data-id]').data('id');
                
                if (postId && field.closest('.wsi-auto-sync-on-change').length > 0) {
                    // Debounce sync to avoid multiple rapid calls
                    clearTimeout(window.wsiSyncTimeout);
                    window.wsiSyncTimeout = setTimeout(function() {
                        syncPost(postId);
                    }, 2000);
                }
            });
        }
    }
    
    /**
     * Sync post to Salesforce
     */
    function syncPost(postId, $button) {
        if (!$button) {
            $button = $('.wsi-sync-button[data-post-id="' + postId + '"]');
        }
        
        var originalText = $button.text();
        var $status = $button.siblings('.sync-status');
        
        // Update button state
        $button.text('Syncing...').prop('disabled', true);
        
        // Show status
        if ($status.length === 0) {
            $status = $('<div class="sync-status"></div>');
            $button.after($status);
        }
        
        $status.html('<span class="syncing">Syncing to Salesforce...</span>');
        
        // Make AJAX request
        $.ajax({
            url: wsi_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'wsi_sync_post',
                post_id: postId,
                nonce: wsi_frontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span class="success">✓ Synced successfully</span>');
                    $button.text('Re-sync');
                    
                    // Trigger custom event
                    $(document).trigger('wsi:sync:success', {
                        postId: postId,
                        response: response.data
                    });
                } else {
                    $status.html('<span class="error">✗ Sync failed: ' + (response.data || 'Unknown error') + '</span>');
                    $button.text(originalText);
                    
                    // Trigger custom event
                    $(document).trigger('wsi:sync:error', {
                        postId: postId,
                        error: response.data
                    });
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span class="error">✗ Sync failed: Network error</span>');
                $button.text(originalText);
                
                // Trigger custom event
                $(document).trigger('wsi:sync:error', {
                    postId: postId,
                    error: 'Network error: ' + error
                });
            },
            complete: function() {
                $button.prop('disabled', false);
                
                // Hide status after 5 seconds
                setTimeout(function() {
                    $status.fadeOut();
                }, 5000);
            }
        });
    }
    
    /**
     * Sync post and then submit form
     */
    function syncPostAndSubmit(postId, $form) {
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        
        // Update button state
        $button.text('Syncing...').prop('disabled', true);
        
        // Sync first
        $.ajax({
            url: wsi_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'wsi_sync_post',
                post_id: postId,
                nonce: wsi_frontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Submit form after successful sync
                    $form.off('submit').submit();
                } else {
                    // Show error and restore button
                    alert('Sync failed: ' + (response.data || 'Unknown error'));
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('Sync failed: Network error');
                $button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    /**
     * Check sync status
     */
    function checkSyncStatus(postId) {
        return $.ajax({
            url: wsi_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'wsi_get_sync_status',
                post_id: postId,
                nonce: wsi_frontend.nonce
            }
        });
    }
    
    /**
     * Batch sync multiple posts
     */
    function batchSyncPosts(postIds) {
        var deferred = $.Deferred();
        var results = [];
        var completed = 0;
        
        function syncNext() {
            if (completed >= postIds.length) {
                deferred.resolve(results);
                return;
            }
            
            var postId = postIds[completed];
            
            $.ajax({
                url: wsi_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsi_sync_post',
                    post_id: postId,
                    nonce: wsi_frontend.nonce
                },
                success: function(response) {
                    results.push({
                        postId: postId,
                        success: response.success,
                        data: response.data
                    });
                },
                error: function() {
                    results.push({
                        postId: postId,
                        success: false,
                        error: 'Network error'
                    });
                },
                complete: function() {
                    completed++;
                    
                    // Update progress
                    var progress = (completed / postIds.length) * 100;
                    $(document).trigger('wsi:batch:progress', {
                        completed: completed,
                        total: postIds.length,
                        progress: progress
                    });
                    
                    // Small delay before next sync
                    setTimeout(syncNext, 500);
                }
            });
        }
        
        syncNext();
        return deferred.promise();
    }
    
    // Expose functions globally for external use
    window.WSIFrontendSync = {
        syncPost: syncPost,
        checkSyncStatus: checkSyncStatus,
        batchSyncPosts: batchSyncPosts
    };
    
})(jQuery);
