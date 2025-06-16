/**
 * Streaming Guide Admin JavaScript
 * Handles AJAX generation, status checking, and UI interactions
 */

(function($) {
    'use strict';

    // Main admin object
    const StreamingGuideAdmin = {
        
        // Current generation tracking
        currentGeneration: null,
        statusCheckInterval: null,
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Quick generate button
            $('#quick-generate-btn').on('click', this.handleQuickGenerate.bind(this));
            
            // Tab switching
            $('.nav-tab').on('click', this.handleTabSwitch);
            
            // Form submissions (convert to AJAX)
            $('.generator-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Schedule deactivation links
            $('.deactivate-schedule').on('click', this.handleScheduleDeactivate);
            
            // Cancel generation button (if visible)
            $(document).on('click', '#cancel-generation', this.cancelGeneration.bind(this));
            
            // Spotlight search functionality
            $('#spotlight-search-btn').on('click', this.handleSpotlightSearch.bind(this));
            $(document).on('click', '.spotlight-result', this.handleSpotlightSelect.bind(this));
        },
        
        /**
         * Initialize tabs
         */
        initTabs: function() {
            // Get active tab from URL hash
            const hash = window.location.hash.substring(1);
            if (hash) {
                this.switchToTab(hash);
            }
        },
        
        /**
         * Handle tab switching
         */
        handleTabSwitch: function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            StreamingGuideAdmin.switchToTab(tab);
        },
        
        /**
         * Switch to specific tab
         */
        switchToTab: function(tab) {
            // Update nav tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').hide();
            $('#' + tab + '-tab').show();
            
            // Update URL hash
            window.location.hash = tab;
        },
        
        /**
         * Handle quick generate
         */
        handleQuickGenerate: function(e) {
            e.preventDefault();
            
            const type = $('#quick-type').val();
            const platform = $('#quick-platform').val();
            
            this.startGeneration({
                type: type,
                platform: platform
            });
        },
        
        /**
         * Handle form submit (convert to AJAX)
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            // Extract data
            const data = {
                type: formData.get('generator_type'),
                platform: formData.get('platform')
            };
            
            // Add type-specific data
            switch (data.type) {
                case 'monthly':
                    data.month = formData.get('month');
                    break;
                case 'trending':
                    data.content_type = formData.get('content_type');
                    break;
                case 'spotlight':
                    data.tmdb_id = formData.get('tmdb_id');
                    data.media_type = formData.get('media_type');
                    break;
            }
            
            // Disable form
            $form.find('button[type="submit"]').prop('disabled', true);
            
            // Start generation
            this.startGeneration(data, function() {
                // Re-enable form on complete
                $form.find('button[type="submit"]').prop('disabled', false);
            });
        },
        
        /**
         * Start content generation
         */
        startGeneration: function(data, onComplete) {
            // Show progress
            this.showProgress(streamingGuideAdmin.strings.generating);
            
            // Add nonce
            data.action = 'streaming_guide_generate';
            data.nonce = streamingGuideAdmin.nonce;
            
            // Start AJAX request
            $.post(streamingGuideAdmin.ajaxurl, data)
                .done((response) => {
                    if (response.success) {
                        this.currentGeneration = response.data.generation_id;
                        this.startStatusChecking();
                    } else {
                        this.showError(response.data.message);
                        if (onComplete) onComplete();
                    }
                })
                .fail(() => {
                    this.showError(streamingGuideAdmin.strings.error);
                    if (onComplete) onComplete();
                });
        },
        
        /**
         * Start checking generation status
         */
        startStatusChecking: function() {
            // Clear any existing interval
            if (this.statusCheckInterval) {
                clearInterval(this.statusCheckInterval);
            }
            
            // Check immediately
            this.checkStatus();
            
            // Then check every 2 seconds
            this.statusCheckInterval = setInterval(this.checkStatus.bind(this), 2000);
        },
        
        /**
         * Check generation status
         */
        checkStatus: function() {
            if (!this.currentGeneration) {
                this.stopStatusChecking();
                return;
            }
            
            $.post(streamingGuideAdmin.ajaxurl, {
                action: 'streaming_guide_check_status',
                generation_id: this.currentGeneration,
                nonce: streamingGuideAdmin.nonce
            })
            .done((response) => {
                if (response.success) {
                    const data = response.data;
                    
                    // Update progress message
                    this.updateProgress(data.message);
                    
                    // Check if complete
                    if (data.status === 'success') {
                        this.handleGenerationSuccess(data);
                    } else if (data.status === 'failed' || data.status === 'cancelled') {
                        this.handleGenerationFailure(data);
                    }
                    // If still processing, continue checking
                }
            });
        },
        
        /**
         * Stop status checking
         */
        stopStatusChecking: function() {
            if (this.statusCheckInterval) {
                clearInterval(this.statusCheckInterval);
                this.statusCheckInterval = null;
            }
            this.currentGeneration = null;
        },
        
        /**
         * Handle successful generation
         */
        handleGenerationSuccess: function(data) {
            this.stopStatusChecking();
            
            // Build success message with links
            let message = `
                <div class="generation-success">
                    <p><strong>${streamingGuideAdmin.strings.success}</strong></p>
                    <p>${data.post_title}</p>
                    <p>
                        <a href="${data.edit_url}" class="button button-primary">Edit Post</a>
                        <a href="${data.view_url}" class="button" target="_blank">View Post</a>
                    </p>
                </div>
            `;
            
            this.showResult(message, 'success');
        },
        
        /**
         * Handle generation failure
         */
        handleGenerationFailure: function(data) {
            this.stopStatusChecking();
            
            const message = data.error || 'Generation failed. Please check the error logs.';
            this.showError(message);
        },
        
        /**
         * Cancel current generation
         */
        cancelGeneration: function(e) {
            e.preventDefault();
            
            if (!this.currentGeneration) return;
            
            $.post(streamingGuideAdmin.ajaxurl, {
                action: 'streaming_guide_cancel_generation',
                generation_id: this.currentGeneration,
                nonce: streamingGuideAdmin.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.stopStatusChecking();
                    this.hideProgress();
                    this.showNotice(response.data.message, 'warning');
                }
            });
        },
        
        /**
         * Handle schedule deactivation
         */
        handleScheduleDeactivate: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to deactivate this schedule?')) {
                return;
            }
            
            const scheduleId = $(this).data('schedule');
            
            // TODO: Implement schedule deactivation AJAX
        },
        
        /**
         * UI Helper Methods
         */
        
        showProgress: function(message) {
            const $progress = $('#generation-progress');
            if ($progress.length) {
                $progress.find('span').text(message);
                $progress.show();
            } else {
                // Create progress element if it doesn't exist
                const progressHtml = `
                    <div id="generation-progress" class="generation-progress">
                        <div class="spinner is-active"></div>
                        <span>${message}</span>
                        <button type="button" id="cancel-generation" class="button button-link">Cancel</button>
                    </div>
                `;
                $('#generation-result').html(progressHtml);
            }
        },
        
        updateProgress: function(message) {
            $('#generation-progress span').text(message);
        },
        
        hideProgress: function() {
            $('#generation-progress').hide();
        },
        
        showResult: function(html, type = 'info') {
            $('#generation-result').html(`<div class="notice notice-${type}">${html}</div>`);
        },
        
        showError: function(message) {
            this.hideProgress();
            this.showResult(`<p>${message}</p>`, 'error');
        },
        
        showNotice: function(message, type = 'info') {
            const noticeHtml = `
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;
            
            $('.wrap.streaming-guide-admin').prepend(noticeHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 5000);
        },
        
        /**
         * Handle Spotlight search
         */
        handleSpotlightSearch: function(e) {
            e.preventDefault();
            
            const searchQuery = $('#spotlight-search').val().trim();
            
            if (!searchQuery) {
                alert('Please enter a title to search for.');
                return;
            }
            
            // Show loading
            $('#spotlight-search-btn').prop('disabled', true).text('Searching...');
            
            // Perform search via AJAX
            $.post(streamingGuideAdmin.ajaxurl, {
                action: 'streaming_guide_search_tmdb',
                query: searchQuery,
                nonce: streamingGuideAdmin.nonce
            })
            .done((response) => {
                if (response.success && response.data.results) {
                    this.displaySpotlightResults(response.data.results);
                } else {
                    $('#search-results-list').html('<p>No results found.</p>');
                    $('#spotlight-results').show();
                }
            })
            .fail(() => {
                alert('Search failed. Please try again.');
            })
            .always(() => {
                $('#spotlight-search-btn').prop('disabled', false).text('Search');
            });
        },
        
        /**
         * Display Spotlight search results
         */
        displaySpotlightResults: function(results) {
            let html = '';
            
            results.forEach((item) => {
                const title = item.media_type === 'movie' ? item.title : item.name;
                const year = item.media_type === 'movie' 
                    ? (item.release_date ? `(${item.release_date.substring(0, 4)})` : '')
                    : (item.first_air_date ? `(${item.first_air_date.substring(0, 4)})` : '');
                const type = item.media_type === 'movie' ? 'Movie' : 'TV Show';
                
                html += `
                    <div class="spotlight-result" data-id="${item.id}" data-type="${item.media_type}">
                        <input type="radio" name="spotlight_selection" id="spotlight_${item.id}" />
                        <label for="spotlight_${item.id}">
                            <strong>${title}</strong> ${year} - <em>${type}</em>
                            ${item.overview ? `<br><small>${item.overview.substring(0, 100)}...</small>` : ''}
                        </label>
                    </div>
                `;
            });
            
            $('#search-results-list').html(html);
            $('#spotlight-results').show();
        },
        
        /**
         * Handle Spotlight selection
         */
        handleSpotlightSelect: function(e) {
            const $result = $(e.currentTarget);
            const tmdbId = $result.data('id');
            const mediaType = $result.data('type');
            
            // Update hidden fields
            $('#spotlight-tmdb-id').val(tmdbId);
            $('#spotlight-media-type').val(mediaType);
            
            // Enable submit button
            $result.closest('form').find('button[type="submit"]').prop('disabled', false);
            
            // Check the radio button
            $result.find('input[type="radio"]').prop('checked', true);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        StreamingGuideAdmin.init();
    });

})(jQuery);