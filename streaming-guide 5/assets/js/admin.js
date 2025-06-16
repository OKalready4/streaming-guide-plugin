/**
 * Streaming Guide Admin JavaScript
 * Handles AJAX functionality for the admin interface
 */
(function($) {
    'use strict';

    // Wait for document ready
    $(document).ready(function() {
        
        // Initialize all admin functionality
        initSocialMediaTesting();
        initGeneratorTesting();
        initScheduleManagement();
        initFormValidation();
        initUIEnhancements();
        
    });

    /**
     * Social Media API Connection Testing
     */
    function initSocialMediaTesting() {
        // Handle social media connection testing
        $('.test-connection button[id^="test-"], button[data-platform]').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const platform = $button.data('platform') || $button.attr('id').replace('test-', '').replace('-connection', '');
            const $resultDiv = $('#' + platform + '-test-result');
            
            if (!platform) {
                console.error('No platform specified for test button');
                return;
            }
            
            // Update button state
            $button.prop('disabled', true).text('Testing...');
            $resultDiv.removeClass('success error').html('<em>Testing connection...</em>');
            
            // Make AJAX request
            $.post(streamingGuideAjax.ajaxurl, {
                action: 'test_social_connection',
                platform: platform,
                nonce: streamingGuideAjax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $resultDiv.removeClass('error').addClass('success')
                             .html('<strong>✓ Success:</strong> ' + response.data.message);
                } else {
                    $resultDiv.removeClass('success').addClass('error')
                             .html('<strong>✗ Error:</strong> ' + (response.data.message || 'Connection failed'));
                }
            })
            .fail(function(xhr, status, error) {
                $resultDiv.removeClass('success').addClass('error')
                         .html('<strong>✗ Error:</strong> Request failed - ' + error);
            })
            .always(function() {
                // Reset button
                $button.prop('disabled', false)
                       .text('Test ' + platform.charAt(0).toUpperCase() + platform.slice(1) + ' Connection');
            });
        });
    }

    /**
     * Generator Testing Functionality
     */
    function initGeneratorTesting() {
        // Handle test generation form in Status tab
        $('form').on('submit', function(e) {
            const $form = $(this);
            const testAction = $form.find('input[name="test_action"]').val();
            
            if (testAction === 'test_single_generation') {
                e.preventDefault();
                handleTestGeneration($form);
            }
        });
        
        // AJAX test generation
        function handleTestGeneration($form) {
            const platform = $form.find('#test_platform').val();
            const type = $form.find('#test_type').val();
            const $button = $form.find('input[type="submit"]');
            const $statusArea = getOrCreateStatusArea($form);
            
            if (!platform || !type) {
                showMessage($statusArea, 'error', 'Please select both platform and type.');
                return;
            }
            
            // Update UI
            $button.prop('disabled', true).val('Generating...');
            showMessage($statusArea, 'info', 'Starting test generation...');
            
            // Make AJAX request
            $.post(streamingGuideAjax.ajaxurl, {
                action: 'streaming_guide_test_generation',
                platform: platform,
                type: type,
                nonce: streamingGuideAjax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    let message = response.data.message;
                    if (response.data.view_url) {
                        message += ' <a href="' + response.data.view_url + '" target="_blank">View Article</a>';
                        message += ' | <a href="' + response.data.edit_url + '" target="_blank">Edit Article</a>';
                    }
                    showMessage($statusArea, 'success', message);
                } else {
                    showMessage($statusArea, 'error', response.data.message || 'Generation failed.');
                }
            })
            .fail(function(xhr, status, error) {
                showMessage($statusArea, 'error', 'Request failed: ' + error);
            })
            .always(function() {
                $button.prop('disabled', false).val('Generate Test Article');
            });
        }
    }

    /**
     * Schedule Management
     */
    function initScheduleManagement() {
        // Handle schedule activation
        $('form').on('submit', function(e) {
            const $form = $(this);
            const action = $form.find('input[name="action"]').val();
            
            if (action === 'activate_professional_schedule') {
                e.preventDefault();
                handleScheduleActivation($form);
            }
        });
        
        function handleScheduleActivation($form) {
            const $button = $form.find('input[type="submit"]');
            const $statusArea = getOrCreateStatusArea($form);
            
            $button.prop('disabled', true).val('Activating...');
            showMessage($statusArea, 'info', 'Activating professional schedule...');
            
            $.post(streamingGuideAjax.ajaxurl, {
                action: 'streaming_guide_activate_schedule',
                nonce: streamingGuideAjax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    showMessage($statusArea, 'success', response.data.message);
                    // Reload page after 2 seconds to show updated schedule status
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showMessage($statusArea, 'error', response.data.message || 'Schedule activation failed.');
                }
            })
            .fail(function(xhr, status, error) {
                showMessage($statusArea, 'error', 'Request failed: ' + error);
            })
            .always(function() {
                $button.prop('disabled', false).val('Activate/Reset All Schedules');
            });
        }
    }

    /**
     * Form Validation and UX Improvements
     */
    function initFormValidation() {
        // Platform comparison validation
        $('select[name="platform1"], select[name="platform2"]').on('change', function() {
            const $form = $(this).closest('form');
            const platform1 = $form.find('select[name="platform1"]').val();
            const platform2 = $form.find('select[name="platform2"]').val();
            const $submit = $form.find('input[type="submit"]');
            
            if (platform1 && platform2 && platform1 === platform2) {
                $submit.prop('disabled', true);
                showFormMessage($form, 'error', 'Please select two different platforms for comparison.');
            } else {
                $submit.prop('disabled', false);
                hideFormMessage($form);
            }
        });
        
        // Required field validation
        $('form select[required], form input[required]').on('change blur', function() {
            validateForm($(this).closest('form'));
        });
        
        function validateForm($form) {
            let isValid = true;
            const $requiredFields = $form.find('[required]');
            
            $requiredFields.each(function() {
                const $field = $(this);
                if (!$field.val() || $field.val() === '') {
                    isValid = false;
                    $field.addClass('invalid');
                } else {
                    $field.removeClass('invalid');
                }
            });
            
            const $submit = $form.find('input[type="submit"]');
            $submit.prop('disabled', !isValid);
            
            return isValid;
        }
    }

    /**
     * UI Enhancements
     */
    function initUIEnhancements() {
        // Auto-dismiss notices after 10 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 10000);
        
        // Smooth scrolling for anchor links
        $('a[href^="#"]').on('click', function(e) {
            const target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 50
                }, 500);
            }
        });
        
        // Confirm dangerous actions
        $('input[value*="Clear"], input[value*="clear"]').on('click', function(e) {
            if (!confirm('Are you sure you want to clear all schedules? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
        
        // Character counter for social media templates
        $('textarea[name*="template"]').each(function() {
            const $textarea = $(this);
            const maxLength = getPlatformCharLimit($textarea.attr('name'));
            if (maxLength > 0) {
                addCharacterCounter($textarea, maxLength);
            }
        });
        
        function getPlatformCharLimit(name) {
            if (name.includes('twitter') || name.includes('x_')) return 280;
            if (name.includes('instagram')) return 2200;
            if (name.includes('facebook')) return 63206;
            return 0;
        }
        
        function addCharacterCounter($textarea, maxLength) {
            const $counter = $('<div class="char-counter" style="font-size: 0.9em; color: #666; margin-top: 5px;"></div>');
            $textarea.after($counter);
            
            function updateCounter() {
                const length = $textarea.val().length;
                const remaining = maxLength - length;
                $counter.text(length + ' / ' + maxLength + ' characters');
                
                if (remaining < 50) {
                    $counter.css('color', '#d63638');
                } else if (remaining < 100) {
                    $counter.css('color', '#dba617');
                } else {
                    $counter.css('color', '#666');
                }
            }
            
            $textarea.on('input keyup', updateCounter);
            updateCounter();
        }
    }

    /**
     * Utility Functions
     */
    function getOrCreateStatusArea($form) {
        let $statusArea = $form.find('.ajax-status');
        if ($statusArea.length === 0) {
            $statusArea = $('<div class="ajax-status" style="margin-top: 15px;"></div>');
            $form.append($statusArea);
        }
        return $statusArea;
    }
    
    function showMessage($container, type, message) {
        const alertClass = type === 'success' ? 'notice-success' : 
                          type === 'error' ? 'notice-error' : 
                          type === 'warning' ? 'notice-warning' : 'notice-info';
        
        $container.html('<div class="notice ' + alertClass + ' inline"><p>' + message + '</p></div>');
    }
    
    function showFormMessage($form, type, message) {
        let $messageArea = $form.find('.form-message');
        if ($messageArea.length === 0) {
            $messageArea = $('<div class="form-message" style="margin: 10px 0;"></div>');
            $form.prepend($messageArea);
        }
        showMessage($messageArea, type, message);
    }
    
    function hideFormMessage($form) {
        $form.find('.form-message').fadeOut().remove();
    }

    // Debug helper - remove in production
    if (typeof streamingGuideAjax === 'undefined') {
        console.warn('Streaming Guide: AJAX object not found. Make sure wp_localize_script is working.');
    }

})(jQuery);