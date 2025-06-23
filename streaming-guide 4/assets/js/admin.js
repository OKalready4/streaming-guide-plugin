/**
 * Essential Admin JavaScript Fixes for Streaming Guide Pro
 * Add this to the TOP of your existing admin.js file
 */

// Override the form submission handling to ensure proper AJAX calls
jQuery(document).ready(function($) {
    'use strict';
    
    // Enhanced form handling for content generation
    $('.generator-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('.generate-btn, button[type="submit"]');
        const $resultDiv = $form.find('.generation-result');
        const originalButtonText = $button.text();
        
        // Get form data
        const formData = {
            action: 'streaming_guide_generate',
            type: $form.data('type') || $form.find('input[name="type"]').val(),
            platform: $form.find('select[name="platform"]').val(),
            nonce: streamingGuideAdmin.nonces.generate
        };
        
        // Add additional fields for spotlight generator
        if (formData.type === 'spotlight') {
            formData.tmdb_id = $form.find('input[name="tmdb_id"], #spotlight-tmdb-id').val();
            formData.media_type = $form.find('select[name="media_type"], #spotlight-media-type').val();
        }
        
        // Validate required fields
        if (!formData.type || !formData.platform) {
            showGenerationMessage($resultDiv, 'error', 'Please fill in all required fields.');
            return;
        }
        
        if (formData.type === 'spotlight' && !formData.tmdb_id) {
            showGenerationMessage($resultDiv, 'error', 'Please enter a TMDB ID for spotlight articles.');
            return;
        }
        
        // Update UI
        $button.prop('disabled', true).text('Generating...');
        showGenerationMessage($resultDiv, 'info', 'Generating content... This may take a few moments.');
        
        // Make AJAX request
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            timeout: 120000, // 2 minutes
            success: function(response) {
                if (response.success) {
                    let message = response.data.message || 'Content generated successfully!';
                    if (response.data.edit_url) {
                        message += '<br><br>';
                        message += '<a href="' + response.data.edit_url + '" target="_blank" class="button">Edit Article</a> ';
                        message += '<a href="' + response.data.view_url + '" target="_blank" class="button">View Article</a>';
                    }
                    showGenerationMessage($resultDiv, 'success', message);
                } else {
                    showGenerationMessage($resultDiv, 'error', response.data?.message || 'Generation failed.');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Generation failed';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out. Content may still be generating.';
                } else if (error) {
                    errorMessage += ': ' + error;
                }
                showGenerationMessage($resultDiv, 'error', errorMessage);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalButtonText);
            }
        });
    });
    
    // Enhanced API testing
    $('.test-api-btn, #test-apis').off('click').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const apiType = $button.data('api-type') || 'all';
        const originalText = $button.text();
        
        // Update UI
        $button.prop('disabled', true).text('Testing...');
        
        // Clear existing results
        $('#tmdb-status, #openai-status, #facebook-test-result').html('Testing...');
        
        // Make AJAX request
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_test_apis',
                api: apiType,
                nonce: streamingGuideAdmin.nonces.test
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    // Update status indicators
                    if (response.data.tmdb) {
                        updateStatusIndicator('tmdb-status', response.data.tmdb.status, response.data.tmdb.message);
                    }
                    if (response.data.openai) {
                        updateStatusIndicator('openai-status', response.data.openai.status, response.data.openai.message);
                    }
                } else {
                    $('#tmdb-status, #openai-status').html('❌ Test failed');
                }
            },
            error: function() {
                $('#tmdb-status, #openai-status').html('❌ Connection failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Facebook connection testing
    $('#test-facebook-connection').off('click').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $result = $('#facebook-test-result');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('Testing Facebook connection...');
        
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_test_facebook',
                nonce: streamingGuideAdmin.nonces.test
            },
            success: function(response) {
                if (response.success) {
                    let message = '✅ ' + response.data.message;
                    if (response.data.data && response.data.data.page_name) {
                        message += '<br>Connected to: <strong>' + response.data.data.page_name + '</strong>';
                    }
                    $result.html(message);
                } else {
                    $result.html('❌ ' + (response.data.message || 'Connection failed'));
                }
            },
            error: function() {
                $result.html('❌ Connection test failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Helper function to show generation messages
    function showGenerationMessage($container, type, message) {
        let className = 'notice notice-' + type;
        let icon = '';
        
        switch(type) {
            case 'success':
                icon = '✅ ';
                break;
            case 'error':
                icon = '❌ ';
                break;
            case 'info':
                icon = 'ℹ️ ';
                break;
        }
        
        $container.html('<div class="' + className + '"><p>' + icon + message + '</p></div>').show();
        
        // Auto-hide info messages
        if (type === 'info') {
            setTimeout(function() {
                if ($container.find('.notice-info').length) {
                    $container.fadeOut();
                }
            }, 10000);
        }
    }
    
    // Helper function to update status indicators
    function updateStatusIndicator(elementId, isSuccess, message) {
        const $element = $('#' + elementId);
        if (isSuccess) {
            $element.html('✅ ' + message).removeClass('error').addClass('connected');
        } else {
            $element.html('❌ ' + message).removeClass('connected').addClass('error');
        }
    }
    
    // Auto-test APIs on page load
    if ($('#tmdb-status, #openai-status').length) {
        setTimeout(function() {
            $('#test-apis').click();
        }, 1000);
    }
});

/**
 * Streamlined Admin JavaScript for Streaming Guide Pro
 * Handles content generation, API testing, and real-time feedback
 */

(function($) {
    'use strict';
    
    let activeRequests = new Set();
    let apiStatusChecked = false;
    
    $(document).ready(function() {
        initializeAdmin();
        checkApiStatus();
        bindEventHandlers();
    });
    
    /**
     * Initialize admin interface
     */
    function initializeAdmin() {
        // Add loading states to buttons
        $('.generate-btn').prop('disabled', false);
        
        // Initialize tooltips if needed
        $('[data-tooltip]').each(function() {
            $(this).attr('title', $(this).data('tooltip'));
        });
        
        // Clear any existing results
        $('.generation-result').hide().empty();
        
        // Focus first form field
        $('.generator-form:first input, .generator-form:first select').first().focus();
    }
    
    /**
     * Check API status on page load
     */
    function checkApiStatus() {
        if (apiStatusChecked) return;
        
        updateStatusIndicator('tmdb-status', 'checking', 'Checking...');
        updateStatusIndicator('openai-status', 'checking', 'Checking...');
        
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            method: 'POST',
            data: {
                action: 'streaming_guide_test_apis',
                nonce: streamingGuideAdmin.nonces.test
            },
            success: function(response) {
                apiStatusChecked = true;
                
                if (response.success && response.data) {
                    // Update TMDB status
                    if (response.data.tmdb) {
                        updateStatusIndicator(
                            'tmdb-status', 
                            response.data.tmdb.status ? 'connected' : 'error',
                            response.data.tmdb.message
                        );
                    }
                    
                    // Update OpenAI status
                    if (response.data.openai) {
                        updateStatusIndicator(
                            'openai-status',
                            response.data.openai.status ? 'connected' : 'error', 
                            response.data.openai.message
                        );
                    }
                } else {
                    updateStatusIndicator('tmdb-status', 'error', 'Check failed');
                    updateStatusIndicator('openai-status', 'error', 'Check failed');
                }
            },
            error: function() {
                updateStatusIndicator('tmdb-status', 'error', 'Connection failed');
                updateStatusIndicator('openai-status', 'error', 'Connection failed');
            }
        });
    }
    
    /**
     * Bind all event handlers
     */
    function bindEventHandlers() {
        // Generator form submissions
        $('.generator-form').on('submit', handleGeneratorSubmission);
        
        // Individual generate buttons
        $('.generate-btn').on('click', handleGenerateClick);
        
        // API testing button
        $('#test-apis').on('click', function(e) {
            e.preventDefault();
            apiStatusChecked = false;
            checkApiStatus();
        });
        
        // Form validation
        $('.generator-form input, .generator-form select').on('change blur', validateForm);
        
        // TMDB ID validation for spotlight
        $('#spotlight-tmdb-id').on('input', function() {
            const value = $(this).val();
            const isValid = /^\d+$/.test(value) && value.length > 0;
            
            $(this).toggleClass('invalid', !isValid && value.length > 0);
            
            if (isValid) {
                $(this).removeClass('invalid');
            }
        });
        
        // Platform selection helpers
        $('select[name="platform"]').on('change', function() {
            const $form = $(this).closest('.generator-form');
            const platform = $(this).val();
            
            // Update form styling based on platform
            $form.attr('data-platform', platform);
        });
    }
    
    /**
     * Handle generator form submission
     */
    function handleGeneratorSubmission(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('.generate-btn');
        
        handleGenerateClick.call($button[0], e);
    }
    
    /**
     * Handle generate button click
     */
    function handleGenerateClick(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $form = $button.closest('.generator-form');
        const generatorType = $form.data('type') || $button.data('type');
        
        // Validate form
        if (!validateFormData($form)) {
            showFormErrors($form);
            return;
        }
        
        // Check for active requests
        const requestKey = `${generatorType}_${Date.now()}`;
        if (activeRequests.has(generatorType)) {
            showNotice('A generation request is already in progress for this type.', 'warning');
            return;
        }
        
        // Start generation
        startGeneration($button, $form, generatorType, requestKey);
    }
    
    /**
     * Start content generation
     */
    function startGeneration($button, $form, generatorType, requestKey) {
        activeRequests.add(generatorType);
        
        const $resultDiv = $form.find('.generation-result');
        const originalButtonText = $button.text();
        
        // Update UI
        $button.prop('disabled', true)
               .text(streamingGuideAdmin.strings.generating)
               .addClass('generating');
        
        $resultDiv.removeClass('success error')
                  .addClass('loading')
                  .html('<div class="spinner"></div><p>Generating content... This may take a few moments.</p>')
                  .show();
        
        // Prepare request data
        const requestData = {
            action: 'streaming_guide_generate',
            type: generatorType,
            nonce: streamingGuideAdmin.nonces.generate
        };
        
        // Add form-specific data
        $form.find('input, select').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            const value = $field.val();
            
            if (name && value) {
                requestData[name] = value;
            }
        });
        
        // Add timestamp for uniqueness
        requestData.timestamp = Date.now();
        
        // Make AJAX request
        $.ajax({
            url: streamingGuideAdmin.ajaxurl,
            method: 'POST',
            data: requestData,
            timeout: 120000, // 2 minutes timeout
            success: function(response) {
                handleGenerationSuccess(response, $button, $form, $resultDiv, originalButtonText);
            },
            error: function(xhr, status, error) {
                handleGenerationError(xhr, status, error, $button, $form, $resultDiv, originalButtonText);
            },
            complete: function() {
                activeRequests.delete(generatorType);
            }
        });
    }
    
    /**
     * Handle successful generation
     */
    function handleGenerationSuccess(response, $button, $form, $resultDiv, originalButtonText) {
        $button.prop('disabled', false)
               .text(originalButtonText)
               .removeClass('generating');
        
        if (response.success && response.data) {
            $resultDiv.removeClass('loading error')
                      .addClass('success')
                      .html(createSuccessMessage(response.data));
            
            // Show success notification
            showNotice(response.data.message || 'Content generated successfully!', 'success');
            
            // Reset form
            resetForm($form);
            
        } else {
            const errorMessage = response.data?.message || 'Generation failed for unknown reason';
            $resultDiv.removeClass('loading success')
                      .addClass('error')
                      .html(createErrorMessage(errorMessage));
            
            showNotice('Generation failed: ' + errorMessage, 'error');
        }
    }
    
    /**
     * Handle generation error
     */
    function handleGenerationError(xhr, status, error, $button, $form, $resultDiv, originalButtonText) {
        $button.prop('disabled', false)
               .text(originalButtonText)
               .removeClass('generating');
        
        let errorMessage = 'An unexpected error occurred.';
        
        if (status === 'timeout') {
            errorMessage = 'Request timed out. The content may still be generating in the background.';
        } else if (xhr.responseJSON && xhr.responseJSON.data) {
            errorMessage = xhr.responseJSON.data.message || xhr.responseJSON.data;
        } else if (error) {
            errorMessage = error;
        }
        
        $resultDiv.removeClass('loading success')
                  .addClass('error')
                  .html(createErrorMessage(errorMessage));
        
        showNotice('Generation failed: ' + errorMessage, 'error');
    }
    
    /**
     * Validate form data
     */
    function validateFormData($form) {
        let isValid = true;
        
        $form.find('input[required], select[required]').each(function() {
            const $field = $(this);
            const value = $field.val();
            
            if (!value || value.trim() === '') {
                $field.addClass('invalid');
                isValid = false;
            } else {
                $field.removeClass('invalid');
            }
        });
        
        // Special validation for TMDB ID
        const $tmdbId = $form.find('#spotlight-tmdb-id');
        if ($tmdbId.length) {
            const tmdbValue = $tmdbId.val();
            if (tmdbValue && (!/^\d+$/.test(tmdbValue) || tmdbValue.length < 1)) {
                $tmdbId.addClass('invalid');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    /**
     * Show form validation errors
     */
    function showFormErrors($form) {
        let errorMessage = 'Please fill in all required fields.';
        
        const $invalidTmdbId = $form.find('#spotlight-tmdb-id.invalid');
        if ($invalidTmdbId.length) {
            errorMessage = 'Please enter a valid TMDB ID (numbers only).';
        }
        
        showNotice(errorMessage, 'error');
        
        // Focus first invalid field
        $form.find('.invalid').first().focus();
    }
    
    /**
     * Validate individual form
     */
    function validateForm() {
        const $field = $(this);
        const $form = $field.closest('.generator-form');
        
        // Remove validation state
        $field.removeClass('invalid');
        
        // Validate required fields
        if ($field.prop('required') && !$field.val()) {
            $field.addClass('invalid');
        }
        
        // Update generate button state
        updateGenerateButtonState($form);
    }
    
    /**
     * Update generate button state based on form validity
     */
    function updateGenerateButtonState($form) {
        const $button = $form.find('.generate-btn');
        const isValid = validateFormData($form);
        
        $button.prop('disabled', !isValid || $button.hasClass('generating'));
    }
    
    /**
     * Reset form after successful generation
     */
    function resetForm($form) {
        // Don't reset platform selection, but reset other fields
        $form.find('input:not([name="platform"])').val('');
        $form.find('select:not([name="platform"])').prop('selectedIndex', 0);
        $form.find('.invalid').removeClass('invalid');
        
        updateGenerateButtonState($form);
    }
    
    /**
     * Create success message HTML
     */
    function createSuccessMessage(data) {
        let html = '<div class="success-content">';
        html += '<div class="success-icon">✅</div>';
        html += '<div class="success-text">';
        html += '<h4>Content Generated Successfully!</h4>';
        html += '<p>' + (data.message || 'Your content has been generated and is ready for review.') + '</p>';
        
        if (data.edit_url) {
            html += '<div class="success-actions">';
            html += '<a href="' + data.edit_url + '" class="button button-primary" target="_blank">Edit Content</a>';
            
            if (data.view_url) {
                html += '<a href="' + data.view_url + '" class="button" target="_blank">View Article</a>';
            }
            html += '</div>';
        }
        
        html += '</div></div>';
        return html;
    }
    
    /**
     * Create error message HTML
     */
    function createErrorMessage(message) {
        let html = '<div class="error-content">';
        html += '<div class="error-icon">❌</div>';
        html += '<div class="error-text">';
        html += '<h4>Generation Failed</h4>';
        html += '<p>' + message + '</p>';
        html += '<p><em>Please check your API settings and try again. If the problem persists, check the automation logs.</em></p>';
        html += '</div></div>';
        return html;
    }
    
    /**
     * Update status indicator
     */
    function updateStatusIndicator(elementId, status, message) {
        const $indicator = $('#' + elementId);
        
        $indicator.removeClass('checking connected error')
                  .addClass(status)
                  .text(message);
        
        // Update parent status item
        const $statusItem = $indicator.closest('.status-item');
        $statusItem.attr('data-status', status);
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type = 'info') {
        // Remove existing notices
        $('.streaming-guide-notice').remove();
        
        const $notice = $('<div class="notice notice-' + type + ' streaming-guide-notice is-dismissible">')
            .html('<p>' + message + '</p>');
        
        $('.wrap').first().after($notice);
        
        // Auto-dismiss after 5 seconds for success notices
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Make dismissible
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
        
        // Scroll to notice
        $('html, body').animate({
            scrollTop: $notice.offset().top - 100
        }, 500);
    }
    
    /**
     * Add custom CSS for enhanced interactions
     */
    function addCustomCSS() {
        const css = `
            .generator-form .invalid {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
            }
            
            .generate-btn.generating {
                background: #666 !important;
                cursor: not-allowed !important;
                position: relative;
            }
            
            .generate-btn.generating::before {
                content: '';
                position: absolute;
                left: 10px;
                top: 50%;
                transform: translateY(-50%);
                width: 16px;
                height: 16px;
                border: 2px solid #fff;
                border-top: 2px solid transparent;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: translateY(-50%) rotate(0deg); }
                100% { transform: translateY(-50%) rotate(360deg); }
            }
            
            .generation-result.loading {
                background: #f0f8ff;
                border-left: 4px solid #0073aa;
            }
            
            .generation-result.success {
                background: #f0f9f0;
                border-left: 4px solid #4caf50;
            }
            
            .generation-result.error {
                background: #fef2f2;
                border-left: 4px solid #dc3545;
            }
            
            .success-content, .error-content {
                display: flex;
                align-items: flex-start;
                gap: 15px;
            }
            
            .success-icon, .error-icon {
                font-size: 24px;
                flex-shrink: 0;
            }
            
            .success-text h4, .error-text h4 {
                margin: 0 0 8px 0;
                color: #333;
            }
            
            .success-actions {
                margin-top: 12px;
                display: flex;
                gap: 10px;
            }
            
            .status-item[data-status="connected"] .status-label {
                color: #4caf50;
            }
            
            .status-item[data-status="error"] .status-label {
                color: #dc3545;
            }
            
            .streaming-guide-notice {
                margin: 15px 0 !important;
            }
        `;
        
        $('<style>').prop('type', 'text/css').html(css).appendTo('head');
    }
    
    // Initialize custom CSS
    addCustomCSS();
    
})(jQuery);