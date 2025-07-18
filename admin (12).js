// assets/js/admin.js - COMPLETE ENHANCED VERSION with 1-5 selection and direct TMDB support

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎬 Enhanced Movies Plugin Admin Interface Loaded');
    
    // Delete confirmation with enhanced messaging
    $('.upcoming-movies-delete-button').on('click', function(e) {
        const movieTitle = $(this).closest('tr').find('strong a').text() || 'this item';
        const confirmMessage = upcomingMovies.deleteConfirm.replace('%s', movieTitle);
        
        if (!confirm(confirmMessage)) {
            e.preventDefault();
        }
    });
    
    // Enhanced movie search functionality
    if ($('#movie-search').length) {
        let searchTimeout;
        
        $('#movie-search').on('input', function() {
            const query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length >= 3) {
                searchTimeout = setTimeout(() => {
                    showSearchSuggestions(query);
                }, 500);
            } else {
                hideSearchSuggestions();
            }
        });
        
        // Close suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-suggestions').length && !$(e.target).is('#movie-search')) {
                hideSearchSuggestions();
            }
        });
    }
    
    // ENHANCED: Mass Producer functionality with 1-5 selection support
    if ($('.movie-checkbox').length || $('#mass-generate-btn').length) {
        initEnhancedMassProducer();
    }
    
    // API key validation
    if ($('#upcoming_movies_tmdb_api_key, #upcoming_movies_openai_api_key').length) {
        initAPIKeyValidation();
    }
    
    // Progress tracking for bulk operations
    if ($('#mass-producer-progress').length) {
        initProgressTracking();
    }
    
    // ENHANCED: Direct TMDB ID processing
    if ($('#direct-tmdb-form').length) {
        initDirectTMDBProcessing();
    }
    
    /**
     * Show search suggestions (future enhancement)
     */
    function showSearchSuggestions(query) {
        console.log('🔍 Searching for:', query);
        // Future: Add live search suggestions here
    }
    
    function hideSearchSuggestions() {
        $('.search-suggestions').remove();
    }
    
    /**
     * ENHANCED: Initialize Mass Producer with flexible 1-5 selection
     */
    function initEnhancedMassProducer() {
        console.log('🚀 Initializing Enhanced Mass Producer');
        
        let selectedMovies = [];
        const maxSelection = 5;
        const minSelection = 1; // NEW: Allow 1-5 selections
        
        // ENHANCED: Movie selection handling with flexible count
        $(document).off('change.massProducer', '.movie-checkbox');
        $(document).on('change.massProducer', '.movie-checkbox', function() {
            const movieId = $(this).val();
            const movieTitle = $(this).data('title') || 'Unknown Item';
            const mediaType = $(this).data('media-type') || 'movie';
            const card = $(this).closest('.movie-select-card');
            
            console.log('Item checkbox changed:', movieId, movieTitle, mediaType, $(this).is(':checked'));
            
            if ($(this).is(':checked')) {
                if (selectedMovies.length < maxSelection) {
                    selectedMovies.push({
                        id: movieId,
                        title: movieTitle,
                        media_type: mediaType
                    });
                    card.addClass('selected');
                    
                    // Add selection animation
                    card.animate({
                        backgroundColor: '#e8f5e8'
                    }, 200);
                    
                    console.log('Added item. Total selected:', selectedMovies.length);
                } else {
                    $(this).prop('checked', false);
                    showNotification('error', `You can only select ${maxSelection} items at a time.`);
                }
            } else {
                selectedMovies = selectedMovies.filter(m => m.id !== movieId);
                card.removeClass('selected');
                card.animate({
                    backgroundColor: '#ffffff'
                }, 200);
                
                console.log('Removed item. Total selected:', selectedMovies.length);
            }
            
            updateEnhancedMassProducerControls(selectedMovies, minSelection, maxSelection);
            
            // Disable other checkboxes if limit reached
            if (selectedMovies.length >= maxSelection) {
                $('.movie-checkbox:not(:checked)').prop('disabled', true);
                $('.movie-select-card:not(.selected)').addClass('disabled');
            } else {
                $('.movie-checkbox').prop('disabled', false);
                $('.movie-select-card').removeClass('disabled');
            }
        });
        
        // ENHANCED: Quick selection buttons
        $(document).off('click.massProducer', '#select-all-btn, #clear-selection-btn');
        
        $(document).on('click.massProducer', '#select-all-btn', function() {
            console.log('Select all clicked');
            const unchecked = $('.movie-checkbox:not(:checked)');
            const toSelect = Math.min(unchecked.length, maxSelection - selectedMovies.length);
            
            unchecked.slice(0, toSelect).each(function() {
                $(this).prop('checked', true).trigger('change');
            });
        });
        
        $(document).on('click.massProducer', '#clear-selection-btn', function() {
            console.log('Clear selection clicked');
            $('.movie-checkbox:checked').each(function() {
                $(this).prop('checked', false).trigger('change');
            });
        });
        
        // ENHANCED: Mass generate button with flexible selection (1-5)
        $(document).off('click.massProducer', '#mass-generate-btn');
        $(document).on('click.massProducer', '#mass-generate-btn', function() {
            console.log('Mass generate clicked. Selected items:', selectedMovies.length);
            
            if (selectedMovies.length < minSelection || selectedMovies.length > maxSelection) {
                showNotification('error', `Please select between ${minSelection}-${maxSelection} items.`);
                return;
            }
            
            const platform = $('#target-platform').val();
            if (!platform) {
                showNotification('error', 'Platform information is missing.');
                return;
            }
            
            const itemWord = selectedMovies.length === 1 ? 'item' : 'items';
            const articleWord = selectedMovies.length === 1 ? 'article' : 'articles';
            
            if (!confirm(`🎬 Generate ${selectedMovies.length} complete ${articleWord} for ${platform}?\n\n⏱️ This will take 2-3 minutes per ${itemWord} and happens in the background.`)) {
                return;
            }
            
            startEnhancedMassGeneration(selectedMovies, platform);
        });
    }
    
    /**
     * ENHANCED: Update Mass Producer controls with 1-5 flexibility
     */
    function updateEnhancedMassProducerControls(selectedMovies, minSelection, maxSelection) {
        const selectedCount = selectedMovies.length;
        
        // Update selection count display
        $('.selected-count, #selected-count').text(selectedCount);
        
        // Update button state - NEW: Enable for 1-5 selections
        const generateBtn = $('#mass-generate-btn');
        const isValidSelection = selectedCount >= minSelection && selectedCount <= maxSelection;
        
        generateBtn.prop('disabled', !isValidSelection);
        
        if (isValidSelection) {
            generateBtn.removeClass('button-secondary').addClass('button-primary');
        } else {
            generateBtn.removeClass('button-primary').addClass('button-secondary');
        }
        
        // Update button text dynamically
        const platform = $('#target-platform').val() || 'Platform';
        if (selectedCount === 0) {
            generateBtn.html('🚀 Generate Articles for <span class="platform-name">' + platform + '</span>');
        } else {
            const itemWord = selectedCount === 1 ? 'Article' : 'Articles';
            generateBtn.html(`🚀 Generate ${selectedCount} ${itemWord} for <span class="platform-name">${platform}</span>`);
        }
        
        // Update selection counter
        $('#selected-count').text(selectedCount);
        
        console.log('Updated enhanced controls. Selected:', selectedCount, 'Valid:', isValidSelection);
    }
    
    /**
     * ENHANCED: Start mass generation with flexible count
     */
    function startEnhancedMassGeneration(selectedMovies, platform) {
        console.log('🎬 Starting enhanced mass generation for', selectedMovies.length, 'items on', platform);
        
        // Show progress section
        $('#mass-producer-progress').show();
        $('#mass-generate-btn').prop('disabled', true).text('🔄 Processing...');
        
        // Scroll to progress section
        $('html, body').animate({
            scrollTop: $('#mass-producer-progress').offset().top - 100
        }, 500);
        
        const movieIds = selectedMovies.map(m => parseInt(m.id));
        
        console.log('Sending enhanced AJAX request with data:', {
            action: 'process_bulk_movies',
            nonce: upcomingMovies.nonce,
            movie_ids: movieIds,
            platform: platform,
            count: selectedMovies.length
        });
        
        // Start AJAX request with enhanced data
        $.ajax({
            url: upcomingMovies.ajaxUrl,
            method: 'POST',
            data: {
                action: 'process_bulk_movies',
                nonce: upcomingMovies.nonce,
                movie_ids: movieIds,
                platform: platform,
                count: selectedMovies.length // Send count for validation
            },
            timeout: 600000, // 10 minute timeout for larger batches
            success: function(response) {
                console.log('Enhanced AJAX response received:', response);
                
                if (response && response.success) {
                    showNotification('success', '✅ Batch processing started!');
                    if (response.data && response.data.batch_id) {
                        trackEnhancedBatchProgress(response.data.batch_id, selectedMovies);
                    } else {
                        showNotification('error', '❌ No batch ID received');
                        resetEnhancedMassProducer();
                    }
                } else {
                    const errorMsg = response && response.data ? response.data : 'Unknown error occurred';
                    showNotification('error', '❌ Failed to start batch: ' + errorMsg);
                    resetEnhancedMassProducer();
                }
            },
            error: function(xhr, status, error) {
                console.error('Enhanced AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    xhr: xhr
                });
                
                let errorMessage = 'Network error occurred. ';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. ';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied. ';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Server error occurred. ';
                }
                
                showNotification('error', '🔌 ' + errorMessage + 'Please try again.');
                resetEnhancedMassProducer();
            }
        });
    }
    
    /**
     * NEW: Initialize Direct TMDB ID processing
     */
    function initDirectTMDBProcessing() {
        console.log('🎯 Initializing Direct TMDB Processing');
        
        // Validate TMDB IDs on input
        $('#tmdb_ids').on('input', function() {
            const input = $(this).val().trim();
            const ids = input.split(',').map(id => id.trim()).filter(id => id.length > 0);
            
            let validIds = 0;
            let invalidIds = [];
            
            ids.forEach(id => {
                if (/^\d+$/.test(id) && parseInt(id) > 0) {
                    validIds++;
                } else if (id.length > 0) {
                    invalidIds.push(id);
                }
            });
            
            // Remove existing validation message
            $('.tmdb-validation').remove();
            
            let validationMessage = '';
            let validationClass = '';
            
            if (validIds > 5) {
                validationMessage = `⚠️ Too many IDs (${validIds}). Maximum 5 allowed.`;
                validationClass = 'notice-warning';
            } else if (invalidIds.length > 0) {
                validationMessage = `❌ Invalid IDs: ${invalidIds.join(', ')}. Use numbers only.`;
                validationClass = 'notice-error';
            } else if (validIds > 0) {
                validationMessage = `✅ ${validIds} valid TMDB ID${validIds > 1 ? 's' : ''} found.`;
                validationClass = 'notice-success';
            }
            
            if (validationMessage) {
                $(this).after(`<div class="tmdb-validation notice ${validationClass} inline" style="margin-top: 5px; padding: 5px 10px;"><p style="margin: 0; font-size: 0.9em;">${validationMessage}</p></div>`);
            }
        });
        
        // Handle direct TMDB form submission
        $('#direct-tmdb-form').on('submit', function(e) {
            const tmdbIds = $('#tmdb_ids').val().trim();
            const platform = $('#direct_platform').val();
            
            if (!tmdbIds) {
                e.preventDefault();
                showNotification('error', 'Please enter at least one TMDB ID.');
                return;
            }
            
            if (!platform) {
                e.preventDefault();
                showNotification('error', 'Please select a platform.');
                return;
            }
            
            const ids = tmdbIds.split(',').map(id => id.trim()).filter(id => /^\d+$/.test(id));
            
            if (ids.length === 0) {
                e.preventDefault();
                showNotification('error', 'Please enter valid TMDB IDs (numbers only).');
                return;
            }
            
            if (ids.length > 5) {
                e.preventDefault();
                showNotification('error', 'Maximum 5 TMDB IDs allowed at once.');
                return;
            }
            
            // Show confirmation
            const itemWord = ids.length === 1 ? 'item' : 'items';
            const confirmation = `🎯 Process ${ids.length} ${itemWord} from TMDB for ${platform}?\n\nTMDB IDs: ${ids.join(', ')}\n\n⏱️ This will take 2-3 minutes per item.`;
            
            if (!confirm(confirmation)) {
                e.preventDefault();
                return;
            }
            
            console.log('🎯 Direct TMDB form submitted:', {
                ids: ids,
                platform: platform,
                count: ids.length
            });
        });
    }
    
    /**
     * NEW: Global function for direct TMDB processing (called from template)
     */
    window.startDirectProcessing = function(batchId, tmdbIds, platform) {
        console.log('🎯 Starting direct TMDB processing:', {
            batchId: batchId,
            tmdbIds: tmdbIds,
            platform: platform
        });
        
        // Show progress section
        $('#mass-producer-progress').show();
        
        // Update progress section title
        $('#mass-producer-progress h2').text('🎯 Processing Direct TMDB IDs...');
        
        // Scroll to progress section
        $('html, body').animate({
            scrollTop: $('#mass-producer-progress').offset().top - 100
        }, 500);
        
        // Create mock selected movies array for progress tracking
        const mockSelectedMovies = tmdbIds.map(id => ({
            id: id,
            title: `TMDB ID ${id}`,
            media_type: 'auto-detect'
        }));
        
        // Start tracking progress
        trackEnhancedBatchProgress(batchId, mockSelectedMovies);
        
        showNotification('info', `🎯 Started processing ${tmdbIds.length} direct TMDB ID(s) for ${platform}`);
    };
    
    /**
     * ENHANCED: Track batch progress with better handling
     */
    function trackEnhancedBatchProgress(batchId, selectedMovies) {
        console.log('📊 Tracking enhanced batch progress:', batchId);
        
        let checkCount = 0;
        const maxChecks = 300; // 10 minutes at 2-second intervals
        
        const progressInterval = setInterval(function() {
            checkCount++;
            
            if (checkCount > maxChecks) {
                clearInterval(progressInterval);
                showNotification('warning', '⏰ Progress tracking timed out. Please check manually.');
                return;
            }
            
            $.ajax({
                url: upcomingMovies.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_bulk_status',
                    nonce: upcomingMovies.nonce,
                    batch_id: batchId
                },
                timeout: 10000, // 10 second timeout for status checks
                success: function(response) {
                    console.log('Enhanced progress check response:', response);
                    
                    if (response && response.success && response.data) {
                        const data = response.data;
                        updateEnhancedProgressDisplay(data, selectedMovies);
                        
                        if (data.status === 'completed') {
                            clearInterval(progressInterval);
                            handleEnhancedBatchCompletion(data);
                        }
                    } else {
                        console.error('Failed to get batch status:', response);
                        if (checkCount > 5) { // Give it a few tries before showing error
                            showNotification('warning', '⚠️ Progress tracking issue. Process may still be running.');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Enhanced progress check error:', error);
                    if (checkCount > 10) { // Give more tries for enhanced version
                        showNotification('warning', '⚠️ Connection issue while tracking progress.');
                    }
                }
            });
        }, 2000); // Check every 2 seconds
    }
    
    /**
     * ENHANCED: Update progress display with better formatting
     */
    function updateEnhancedProgressDisplay(data, selectedMovies) {
        const total = data.total || selectedMovies.length;
        const completed = data.completed || 0;
        const errors = (data.errors && data.errors.length) || 0;
        const skipped = (data.skipped && data.skipped.length) || 0;
        const processed = completed + errors + skipped;
        
        const progress = total > 0 ? (processed / total) * 100 : 0;
        
        console.log('Enhanced progress update:', {
            completed: completed,
            errors: errors,
            skipped: skipped,
            processed: processed,
            total: total,
            progress: progress
        });
        
        $('#progress-bar').css('width', progress + '%');
        
        // Enhanced status text
        let statusText = `Processing ${processed} of ${total} ${total === 1 ? 'item' : 'items'}... `;
        statusText += `${completed} successful`;
        if (errors > 0) {
            statusText += `, ${errors} failed`;
        }
        if (skipped > 0) {
            statusText += `, ${skipped} skipped`;
        }
        
        $('#progress-text').text(statusText);
        
        // Show current item with enhanced formatting
        if (data.current_movie && processed <= selectedMovies.length) {
            const currentItem = selectedMovies.find(m => m.id == data.current_movie) || 
                               selectedMovies[Math.min(processed, selectedMovies.length - 1)];
            if (currentItem) {
                const status = data.errors && data.errors.includes(parseInt(currentItem.id)) ? 'failed' : 
                              data.skipped && data.skipped.includes(parseInt(currentItem.id)) ? 'skipped' : 'completed';
                
                const statusEmoji = status === 'completed' ? '✅' : status === 'skipped' ? '⏭️' : '❌';
                const statusText = status === 'completed' ? 'Completed' : 
                                  status === 'skipped' ? 'Skipped (already exists)' : 'Failed';
                
                $('#current-movie').html(`<strong>Latest:</strong> ${currentItem.title} - ${statusEmoji} ${statusText}`);
                
                // Add to log with enhanced formatting
                const statusClass = status === 'completed' ? 'success' : status === 'skipped' ? 'info' : 'error';
                const logEntry = `<div class="log-entry ${statusClass}" style="padding: 5px 10px; margin: 2px 0; border-radius: 4px; background: rgba(255,255,255,0.1);">${statusEmoji} ${currentItem.title}</div>`;
                $('#progress-log').append(logEntry);
                
                // Auto-scroll log
                const logElement = document.getElementById('progress-log');
                if (logElement) {
                    logElement.scrollTop = logElement.scrollHeight;
                }
            }
        }
        
        // Update progress stats
        $('#progress-stats').html(`
            <div style="text-align: right; font-size: 0.9rem;">
                <div>✅ Success: ${completed}</div>
                ${errors > 0 ? `<div>❌ Errors: ${errors}</div>` : ''}
                ${skipped > 0 ? `<div>⏭️ Skipped: ${skipped}</div>` : ''}
                <div><strong>Total: ${processed}/${total}</strong></div>
            </div>
        `);
    }
    
    /**
     * ENHANCED: Handle batch completion with better messaging
     */
    function handleEnhancedBatchCompletion(data) {
        console.log('✅ Enhanced batch completed:', data);
        
        $('#progress-bar').addClass('completed').css({
            'background': 'linear-gradient(90deg, #10b981, #34d399)',
            'width': '100%'
        });
        
        const completed = data.completed || 0;
        const errors = (data.errors && data.errors.length) || 0;
        const skipped = (data.skipped && data.skipped.length) || 0;
        const total = completed + errors + skipped;
        
        // Enhanced completion message
        let message = `🎉 Generation complete!`;
        
        if (completed > 0) {
            const articleWord = completed === 1 ? 'article' : 'articles';
            message += ` Successfully created ${completed} ${articleWord}.`;
        }
        
        if (errors > 0) {
            const itemWord = errors === 1 ? 'item' : 'items';
            message += ` ${errors} ${itemWord} failed to process.`;
        }
        
        if (skipped > 0) {
            const itemWord = skipped === 1 ? 'item' : 'items';
            message += ` ${skipped} ${itemWord} were skipped (already exist).`;
        }
        
        $('#progress-text').html(`<strong>${message}</strong>`);
        $('#current-movie').html('<strong>Status:</strong> All items processed! 🎬');
        
        showNotification('success', message);
        
        // Add enhanced completion actions
        const completionActions = `
            <div class="completion-actions" style="margin-top: 1rem; text-align: center; padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 8px;">
                <p><strong>🎬 Your content is ready!</strong></p>
                <div style="margin-top: 1rem;">
                    <a href="${upcomingMovies.adminUrl || 'admin.php'}?page=upcoming-movies" class="button button-primary" style="margin-right: 0.5rem;">📚 View All Content</a>
                    <button type="button" class="button button-secondary" onclick="window.location.reload();">🔄 Generate More</button>
                </div>
                <div style="margin-top: 1rem; font-size: 0.9rem; color: rgba(255,255,255,0.8);">
                    <p>📊 <strong>Summary:</strong> ${completed} created, ${errors} failed, ${skipped} skipped</p>
                </div>
            </div>
        `;
        
        $('#progress-log').append(completionActions);
        
        // Auto-redirect offer after 10 seconds for enhanced version
        setTimeout(() => {
            if (confirm('🎬 Would you like to view your generated content now?')) {
                const adminUrl = upcomingMovies.adminUrl || 'admin.php';
                window.location.href = adminUrl + '?page=upcoming-movies';
            }
        }, 10000);
    }
    
    /**
     * ENHANCED: Reset mass producer interface
     */
    function resetEnhancedMassProducer() {
        $('#mass-producer-progress').hide();
        $('#mass-generate-btn').prop('disabled', true).html('🚀 Generate Articles for <span class="platform-name">Platform</span>');
        $('#progress-bar').css('width', '0%').removeClass('completed');
        $('#progress-text').text('Starting generation...');
        $('#progress-log').empty();
        $('#current-movie').empty();
        $('#progress-stats').empty();
        
        // Reset selections
        $('.movie-checkbox:checked').each(function() {
            $(this).prop('checked', false).trigger('change');
        });
        
        // Reset form if exists
        if ($('#direct-tmdb-form').length) {
            $('#direct-tmdb-form')[0].reset();
            $('.tmdb-validation').remove();
        }
    }
    
    /**
     * Initialize API key validation
     */
    function initAPIKeyValidation() {
        console.log('🔑 Initializing API key validation');
        
        $('#upcoming_movies_tmdb_api_key').on('blur', function() {
            validateAPIKey('tmdb', $(this).val());
        });
        
        $('#upcoming_movies_openai_api_key').on('blur', function() {
            validateAPIKey('openai', $(this).val());
        });
    }
    
    /**
     * Validate API key
     */
    function validateAPIKey(type, key) {
        if (!key || key.length < 10) {
            return;
        }
        
        const field = $(`#upcoming_movies_${type}_api_key`);
        let statusDiv = field.next('.api-key-status');
        
        if (statusDiv.length === 0) {
            field.after('<div class="api-key-status"></div>');
            statusDiv = field.next('.api-key-status');
        }
        
        statusDiv.html('<span style="color: #666;">Validating...</span>');
        
        // Enhanced validation
        setTimeout(() => {
            if (type === 'tmdb' && key.length === 32 && /^[a-f0-9]{32}$/.test(key)) {
                statusDiv.html('<span style="color: #00a32a;">✓ Valid format</span>');
            } else if (type === 'openai' && key.startsWith('sk-') && key.length > 20) {
                statusDiv.html('<span style="color: #00a32a;">✓ Valid format</span>');
            } else {
                statusDiv.html('<span style="color: #dc3232;">⚠ Invalid format</span>');
            }
        }, 1000);
    }
    
    /**
     * ENHANCED: Show notification with better styling
     */
    function showNotification(type, message) {
        const notificationClass = type === 'success' ? 'notice-success' : 
                                 type === 'error' ? 'notice-error' : 
                                 type === 'info' ? 'notice-info' : 'notice-warning';
        
        const notification = $(`
            <div class="notice ${notificationClass} is-dismissible" style="margin: 10px 0;">
                <p><strong>${message}</strong></p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notification);
        
        // Auto-dismiss after 7 seconds (longer for enhanced version)
        setTimeout(() => {
            notification.fadeOut();
        }, 7000);
        
        // Manual dismiss
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut();
        });
    }
    
    /**
     * Initialize progress tracking
     */
    function initProgressTracking() {
        // Add smooth animations to progress elements
        $('.progress-bar').css('transition', 'width 0.3s ease');
        
        // Make progress log scrollable
        $('#progress-log').css({
            'max-height': '250px',
            'overflow-y': 'auto',
            'padding': '10px',
            'border': '1px solid rgba(255,255,255,0.2)',
            'border-radius': '4px',
            'background': 'rgba(255,255,255,0.05)'
        });
    }
    
    /**
     * Enhanced form validation
     */
    $('form').on('submit', function(e) {
        const form = $(this);
        
        // Skip validation for direct TMDB form (handled separately)
        if (form.attr('id') === 'direct-tmdb-form') {
            return;
        }
        
        // Check for required fields
        const requiredFields = form.find('[required]');
        let hasErrors = false;
        
        requiredFields.each(function() {
            const field = $(this);
            if (!field.val().trim()) {
                field.addClass('error');
                hasErrors = true;
            } else {
                field.removeClass('error');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            showNotification('error', 'Please fill in all required fields.');
        }
    });
    
    /**
     * Auto-save functionality for settings
     */
    let settingsTimeout;
    $('.form-table input, .form-table select, .form-table textarea').on('input change', function() {
        clearTimeout(settingsTimeout);
        
        settingsTimeout = setTimeout(() => {
            showNotification('info', 'Remember to save your changes!');
        }, 3000);
    });
    
    /**
     * Enhanced table interactions
     */
    $('.wp-list-table tbody tr').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );
    
    /**
     * Keyboard shortcuts
     */
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.which === 83) {
            e.preventDefault();
            $('input[type="submit"], button[type="submit"]').first().click();
        }
        
        // Esc to close modals or notifications
        if (e.which === 27) {
            $('.notice').fadeOut();
        }
    });
    
    /**
     * Initialize tooltips
     */
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[title]').tooltip({
            placement: 'top',
            delay: { show: 500, hide: 100 }
        });
    }
    
    /**
     * Enhanced error handling
     */
    window.addEventListener('error', function(e) {
        console.error('JavaScript Error:', e.error);
        if (typeof upcomingMovies !== 'undefined' && upcomingMovies.debug) {
            showNotification('error', 'A JavaScript error occurred. Please check the console.');
        }
    });
    
    /**
     * ENHANCED: AJAX error handling
     */
    $(document).ajaxError(function(event, xhr, settings, error) {
        console.error('AJAX Error:', {
            url: settings.url,
            error: error,
            status: xhr.status,
            response: xhr.responseText
        });
        
        if (xhr.status === 0) {
            showNotification('error', 'Network connection lost. Please check your internet connection.');
        } else if (xhr.status === 403) {
            showNotification('error', 'Permission denied. You may need to log in again.');
        } else if (xhr.status >= 500) {
            showNotification('error', 'Server error occurred. Please try again later.');
        }
    });
    
    /**
     * Performance monitoring
     */
    if (typeof performance !== 'undefined' && performance.mark) {
        performance.mark('admin-js-loaded');
        
        $(window).on('load', function() {
            performance.mark('admin-page-loaded');
            performance.measure('admin-load-time', 'admin-js-loaded', 'admin-page-loaded');
            
            const measure = performance.getEntriesByName('admin-load-time')[0];
            if (measure && measure.duration > 3000) {
                console.warn('Admin page loaded slowly:', measure.duration + 'ms');
            }
        });
    }
    
    console.log('✅ Enhanced Movies Plugin Admin Interface Ready - Supports 1-5 selections and direct TMDB input');
});