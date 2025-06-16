/**
 * Streaming Guide Admin JavaScript
 * Fixed version with proper AJAX handling and status updates
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Store active generation IDs
    let activeGenerations = [];
    let statusCheckInterval = null;
    
    /**
     * Handle generate button clicks
     */
    $('.generate-btn').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $form = $button.closest('.generator-form');
        const $card = $button.closest('.generator-card');
        const $statusDiv = $card.find('.generation-status');
        const $resultDiv = $card.find('.generation-result');
        
        // Get form data
        const generatorType = $button.data('type');
        const platform = $form.find('select[name="platform"]').val();
        
        if (!platform) {
            alert('Please select a platform first.');
            return;
        }
        
        // Clear previous results
        $statusDiv.html('').show();
        $resultDiv.html('');
        
        // Disable button and show loading
        $button.prop('disabled', true).text('Generating...');
        
        // Show status
        showStatus($statusDiv, 'info', 'Starting content generation...');
        
        // Make AJAX request
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_generate',
                type: generatorType,
                platform: platform,
                nonce: streamingGuideAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.existing) {
                        // Content already exists
                        showStatus($statusDiv, 'warning', response.data.message);
                        showResult($resultDiv, response.data);
                        $button.prop('disabled', false).text('Generate');
                    } else if (response.data.generation_ids) {
                        // Multiple generations started
                        activeGenerations = activeGenerations.concat(response.data.generation_ids);
                        showStatus($statusDiv, 'info', response.data.message);
                        startStatusChecking();
                    } else if (response.data.generation_id) {
                        // Single generation started
                        activeGenerations.push(response.data.generation_id);
                        showStatus($statusDiv, 'info', response.data.message);
                        startStatusChecking();
                    }
                } else {
                    showStatus($statusDiv, 'error', response.data.message || 'Generation failed.');
                    $button.prop('disabled', false).text('Generate');
                }
            },
            error: function(xhr, status, error) {
                showStatus($statusDiv, 'error', 'Request failed: ' + error);
                $button.prop('disabled', false).text('Generate');
            }
        });
    });
    
    /**
     * Start checking generation status
     */
    function startStatusChecking() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
        
        statusCheckInterval = setInterval(checkGenerationStatus, 3000);
    }
    
    /**
     * Check status of active generations
     */
    function checkGenerationStatus() {
        if (activeGenerations.length === 0) {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = null;
            }
            return;
        }
        
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_check_status',
                generation_ids: activeGenerations,
                nonce: streamingGuideAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.statuses) {
                    let stillActive = [];
                    
                    response.data.statuses.forEach(function(status) {
                        const $card = $('.generator-card').filter(function() {
                            return $(this).find('.generate-btn').data('type') === status.platform;
                        });
                        
                        if ($card.length === 0) {
                            // Try to find by checking all cards for the matching generation
                            $('.generator-card').each(function() {
                                const $statusDiv = $(this).find('.generation-status');
                                if ($statusDiv.text().includes(status.platform)) {
                                    updateGenerationStatus($(this), status);
                                }
                            });
                        } else {
                            updateGenerationStatus($card, status);
                        }
                        
                        if (status.status === 'pending' || status.status === 'processing') {
                            stillActive.push(status.id);
                        }
                    });
                    
                    activeGenerations = stillActive;
                    
                    if (activeGenerations.length === 0 && statusCheckInterval) {
                        clearInterval(statusCheckInterval);
                        statusCheckInterval = null;
                    }
                }
            }
        });
    }
    
    /**
     * Update generation status for a card
     */
    function updateGenerationStatus($card, status) {
        const $statusDiv = $card.find('.generation-status');
        const $resultDiv = $card.find('.generation-result');
        const $button = $card.find('.generate-btn');
        
        switch (status.status) {
            case 'pending':
                showStatus($statusDiv, 'info', 'Waiting in queue...');
                break;
                
            case 'processing':
                showStatus($statusDiv, 'info', 'Generating content for ' + status.platform + '...');
                break;
                
            case 'success':
                showStatus($statusDiv, 'success', 'Content generated successfully!');
                showResult($resultDiv, status);
                $button.prop('disabled', false).text('Generate');
                break;
                
            case 'failed':
                showStatus($statusDiv, 'error', 'Generation failed: ' + (status.error || 'Unknown error'));
                $button.prop('disabled', false).text('Generate');
                break;
        }
    }
    
    /**
     * Show status message
     */
    function showStatus($statusDiv, type, message) {
        const typeClass = {
            'info': 'notice-info',
            'success': 'notice-success',
            'warning': 'notice-warning',
            'error': 'notice-error'
        }[type] || 'notice-info';
        
        $statusDiv.html(
            '<div class="notice ' + typeClass + ' inline">' +
            '<p>' + message + '</p>' +
            '</div>'
        );
    }
    
    /**
     * Show generation result
     */
    function showResult($resultDiv, data) {
        if (data.post_url) {
            let html = '<div class="generation-success">';
            html += '<p>Post created successfully!</p>';
            html += '<p>';
            html += '<a href="' + data.post_url + '" target="_blank" class="button button-primary">View Post</a> ';
            html += '<a href="' + data.edit_url + '" class="button">Edit Post</a>';
            html += '</p>';
            html += '</div>';
            
            $resultDiv.html(html);
        }
    }
    
    /**
     * Handle platform "All" selection
     */
    $('select[name="platform"]').on('change', function() {
        const $select = $(this);
        const $form = $select.closest('.generator-form');
        const $button = $form.find('.generate-btn');
        
        if ($select.val() === 'all') {
            $button.text('Generate for All Platforms');
        } else {
            $button.text('Generate');
        }
    });
    
    /**
     * Test API connections
     */
    $('.test-api-btn').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const api = $button.data('api');
        const $resultDiv = $('#' + api + '-test-result');
        
        $button.prop('disabled', true).text('Testing...');
        $resultDiv.html('<em>Testing connection...</em>');
        
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_test_api',
                api: api,
                nonce: streamingGuideAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $resultDiv.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $resultDiv.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $resultDiv.html('<span style="color: red;">✗ Connection test failed</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
    });
    
    /**
     * Handle delete button clicks
     */
    $(document).on('click', '.delete-post-btn', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
            return;
        }
        
        const $button = $(this);
        const postId = $button.data('post-id');
        const $row = $button.closest('tr');
        
        $button.prop('disabled', true).text('Deleting...');
        
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_delete_post',
                post_id: postId,
                nonce: streamingGuideAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $row.remove();
                    });
                } else {
                    alert('Failed to delete post: ' + response.data.message);
                    $button.prop('disabled', false).text('Delete');
                }
            },
            error: function() {
                alert('Failed to delete post. Please try again.');
                $button.prop('disabled', false).text('Delete');
            }
        });
    });
    
    /**
     * Handle tab navigation
     */
    $('.nav-tab').on('click', function(e) {
        if ($(this).attr('href').indexOf('#') === 0) {
            e.preventDefault();
            
            const $tab = $(this);
            const target = $tab.attr('href').substring(1);
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide content
            $('.tab-content').hide();
            $('#' + target).show();
            
            // Update URL without reload
            if (history.pushState) {
                history.pushState(null, null, $tab.attr('href'));
            }
        }
    });
    
    // Show correct tab on load
    const hash = window.location.hash;
    if (hash && $(hash).length) {
        $('.nav-tab[href="' + hash + '"]').click();
    }
    
    /**
     * Handle settings form submission
     */
    $('#streaming-guide-settings-form').on('submit', function(e) {
        const $form = $(this);
        const $submit = $form.find('input[type="submit"]');
        
        // Show saving message
        $submit.val('Saving...').prop('disabled', true);
        
        // Re-enable after a moment (form will submit normally)
        setTimeout(function() {
            $submit.val('Save Changes').prop('disabled', false);
        }, 1000);
    });
    
    /**
     * Toggle visibility of API key fields
     */
    $('.toggle-password').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $input = $button.prev('input');
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $button.text('Hide');
        } else {
            $input.attr('type', 'password');
            $button.text('Show');
        }
    });
});