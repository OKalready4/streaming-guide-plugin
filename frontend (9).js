/**
 * Enhanced Trailer Modal System - Frontend JavaScript
 * Handles YouTube trailer popups with improved accessibility and mobile support
 */
(function($) {
    'use strict';
    
    let currentModal = null;
    let isModalOpen = false;
    let currentTrailer = null;
    
    /**
     * Initialize trailer modal functionality
     */
    function initTrailerModals() {
        console.log('🎬 Initializing enhanced trailer system');
        
        // Create modal if it doesn't exist
        createModalIfNeeded();
        
        // Get modal elements
        const $trailerModal = $('.trailer-modal');
        const $trailerEmbed = $('.trailer-embed');
        const $closeModal = $('.close-modal');
        
        // Bind trailer button clicks - Enhanced selectors
        bindTrailerEvents();
        
        // Close modal events
        $closeModal.off('click.trailerModal').on('click.trailerModal', closeTrailerModal);
        
        // Click outside to close
        $trailerModal.off('click.trailerModal').on('click.trailerModal', function(e) {
            if (e.target === this) {
                closeTrailerModal();
            }
        });
        
        // Keyboard events
        $(document).off('keydown.trailerModal').on('keydown.trailerModal', function(e) {
            if (isModalOpen) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    closeTrailerModal();
                }
                // Trap focus within modal
                if (e.key === 'Tab') {
                    trapFocus(e);
                }
            }
        });
        
        console.log('🎬 Trailer modal initialization complete');
    }
    
    /**
     * Bind all trailer events with enhanced selectors
     */
    function bindTrailerEvents() {
        // Enhanced selector list for all possible trailer buttons
        const trailerSelectors = [
            '.play-trailer',
            '.play-trailer-btn', 
            '.movie-trailer-button',
            '.trailer-btn',
            '.trailer-button',
            '.watch-trailer',
            '.trailer-thumbnail',
            '[data-trailer]',
            '[data-youtube-id]'
        ].join(', ');
        
        // Remove existing events and rebind
        $(document).off('click.trailerModal', trailerSelectors);
        
        $(document).on('click.trailerModal', trailerSelectors, function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const trailerSource = getTrailerSource($button);
            const movieTitle = getMovieTitle($button);
            
            console.log('🎬 Trailer button clicked:', {
                source: trailerSource,
                title: movieTitle,
                element: $button[0]
            });
            
            if (trailerSource) {
                openTrailerModal(trailerSource, movieTitle, $button);
            } else {
                console.error('❌ No trailer source found');
                showErrorMessage('No trailer available for this movie.');
            }
        });
        
        console.log('✅ Trailer events bound to selectors:', trailerSelectors);
    }
    
    /**
     * Get trailer source from element
     */
    function getTrailerSource($element) {
        return $element.data('trailer') || 
               $element.data('youtube-id') || 
               $element.attr('data-trailer') || 
               $element.attr('data-youtube-id') ||
               $element.find('[data-trailer]').data('trailer') ||
               $element.find('[data-youtube-id]').data('youtube-id');
    }
    
    /**
     * Get movie title from element
     */
    function getMovieTitle($element) {
        return $element.data('movie-title') || 
               $element.attr('data-movie-title') ||
               $element.closest('[data-movie-title]').data('movie-title') ||
               $element.find('[data-movie-title]').data('movie-title') ||
               $element.closest('article').find('h1, h2, h3, h4').first().text() ||
               document.title ||
               'Movie Trailer';
    }
    
    /**
     * Create modal HTML structure if it doesn't exist
     */
    function createModalIfNeeded() {
        if ($('.trailer-modal').length > 0) {
            currentModal = $('.trailer-modal');
            return;
        }
        
        console.log('🎬 Creating trailer modal container');
        
        const modalHTML = `
            <div class="trailer-modal" role="dialog" aria-labelledby="trailer-title" aria-modal="true" aria-hidden="true">
                <div class="modal-content">
                    <button class="close-modal" aria-label="Close trailer" title="Close trailer">&times;</button>
                    <div class="trailer-embed" aria-live="polite">
                        <div class="trailer-loading">
                            <div class="loading-spinner"></div>
                            <span>Loading trailer...</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHTML);
        currentModal = $('.trailer-modal');
        
        // Add CSS if not already present
        addTrailerModalCSS();
    }
    
    /**
     * Add comprehensive CSS for trailer modal
     */
    function addTrailerModalCSS() {
        if ($('#trailer-modal-css').length > 0) return;
        
        const css = `
            <style id="trailer-modal-css">
            .trailer-modal {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: rgba(0, 0, 0, 0.95) !important;
                z-index: 999999 !important;
                display: none !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 20px !important;
                box-sizing: border-box !important;
                backdrop-filter: blur(10px) !important;
                opacity: 0;
                transition: opacity 0.3s ease, visibility 0.3s ease;
            }
            
            .trailer-modal.show {
                display: flex !important;
                opacity: 1 !important;
                visibility: visible !important;
            }
            
            .trailer-modal .modal-content {
                position: relative !important;
                width: 100% !important;
                max-width: 1200px !important;
                aspect-ratio: 16/9 !important;
                background: #000 !important;
                border-radius: 12px !important;
                overflow: hidden !important;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.7) !important;
                transform: scale(0.8) translateY(50px);
                transition: transform 0.3s ease;
            }
            
            .trailer-modal.show .modal-content {
                transform: scale(1) translateY(0) !important;
            }
            
            .trailer-modal .close-modal {
                position: absolute !important;
                top: -50px !important;
                right: 0 !important;
                width: 44px !important;
                height: 44px !important;
                background: rgba(255, 255, 255, 0.95) !important;
                color: #000 !important;
                border: none !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 24px !important;
                font-weight: bold !important;
                cursor: pointer !important;
                z-index: 1000000 !important;
                transition: all 0.2s ease !important;
                line-height: 1 !important;
            }
            
            .trailer-modal .close-modal:hover {
                background: #fff !important;
                transform: scale(1.1) !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
            }
            
            .trailer-modal .trailer-embed {
                width: 100% !important;
                height: 100% !important;
                position: relative !important;
                border-radius: 12px !important;
                overflow: hidden !important;
            }
            
            .trailer-modal .trailer-embed iframe {
                width: 100% !important;
                height: 100% !important;
                border: none !important;
                border-radius: 12px !important;
            }
            
            .trailer-loading {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                height: 100% !important;
                color: #fff !important;
                font-size: 18px !important;
                background: #000 !important;
                gap: 20px !important;
            }
            
            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 3px solid rgba(255, 255, 255, 0.3);
                border-top: 3px solid #fff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Mobile responsive */
            @media (max-width: 768px) {
                .trailer-modal {
                    padding: 15px !important;
                }
                
                .trailer-modal .modal-content {
                    border-radius: 8px !important;
                }
                
                .trailer-modal .close-modal {
                    top: -45px !important;
                    width: 38px !important;
                    height: 38px !important;
                    font-size: 20px !important;
                }
            }
            
            @media (max-width: 480px) {
                .trailer-modal {
                    padding: 10px !important;
                }
                
                .trailer-modal .close-modal {
                    top: -40px !important;
                    width: 35px !important;
                    height: 35px !important;
                    font-size: 18px !important;
                }
            }
            
            /* Landscape mobile */
            @media (max-height: 500px) and (orientation: landscape) {
                .trailer-modal {
                    padding: 5px !important;
                }
                
                .trailer-modal .close-modal {
                    top: 10px !important;
                    right: 10px !important;
                    background: rgba(0, 0, 0, 0.8) !important;
                    color: #fff !important;
                }
            }
            
            /* Prevent body scroll */
            body.modal-open {
                overflow: hidden !important;
                height: 100vh !important;
            }
            
            /* Accessibility */
            .trailer-modal .close-modal:focus {
                outline: 3px solid #4285f4 !important;
                outline-offset: 2px !important;
            }
            
            /* High contrast support */
            @media (prefers-contrast: high) {
                .trailer-modal .close-modal {
                    background: #ffffff !important;
                    color: #000000 !important;
                    border: 2px solid #000000 !important;
                }
            }
            
            /* Reduced motion support */
            @media (prefers-reduced-motion: reduce) {
                .trailer-modal,
                .trailer-modal .modal-content,
                .trailer-modal .close-modal,
                .loading-spinner {
                    transition: none !important;
                    animation: none !important;
                }
            }
            </style>
        `;
        
        $('head').append(css);
    }
    
    /**
     * Open trailer modal with enhanced features
     */
    function openTrailerModal(trailerSource, movieTitle, triggerElement) {
        if (!trailerSource) {
            console.error('❌ No trailer source provided');
            showErrorMessage('No trailer available for this movie.');
            return;
        }
        
        const videoId = extractYouTubeId(trailerSource);
        
        if (!videoId) {
            console.error('❌ Failed to extract YouTube ID from:', trailerSource);
            showErrorMessage('Unable to load trailer. Invalid format.');
            return;
        }
        
        console.log('🎬 Opening trailer:', videoId, 'for:', movieTitle);
        
        // Store current trailer info
        currentTrailer = {
            youtubeId: videoId,
            movieTitle: movieTitle,
            triggerElement: triggerElement
        };
        
        // Prevent body scroll
        $('body').addClass('modal-open');
        
        // Update accessibility attributes
        currentModal.attr('aria-labelledby', 'trailer-title');
        
        // Show modal with loading state
        currentModal.addClass('show').attr('aria-hidden', 'false');
        isModalOpen = true;
        
        // Create iframe with enhanced parameters
        const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1&playsinline=1&iv_load_policy=3&enablejsapi=1&origin=${encodeURIComponent(window.location.origin)}`;
        
        const iframe = `
            <h2 id="trailer-title" style="position: absolute; left: -10000px;">${movieTitle} Trailer</h2>
            <iframe 
                src="${embedUrl}" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                allowfullscreen 
                title="${movieTitle} Trailer"
                loading="eager">
            </iframe>
        `;
        
        // Load iframe after modal animation
        setTimeout(() => {
            $('.trailer-embed').html(iframe);
            
            // Focus close button for accessibility
            setTimeout(() => {
                $('.close-modal').focus();
            }, 100);
            
            console.log('✅ Trailer modal opened successfully');
        }, 300);
        
        // Analytics tracking
        trackTrailerView(movieTitle, videoId);
    }
    
    /**
     * Close trailer modal
     */
    function closeTrailerModal() {
        if (!isModalOpen) return;
        
        console.log('🎬 Closing trailer modal');
        
        const movieTitle = currentTrailer ? currentTrailer.movieTitle : 'trailer';
        
        // Remove body scroll lock
        $('body').removeClass('modal-open');
        
        // Hide modal
        currentModal.removeClass('show').attr('aria-hidden', 'true');
        isModalOpen = false;
        
        // Return focus to trigger element
        if (currentTrailer && currentTrailer.triggerElement) {
            setTimeout(() => {
                currentTrailer.triggerElement.focus();
            }, 300);
        }
        
        // Clear embed content after animation
        setTimeout(() => {
            $('.trailer-embed').html(`
                <div class="trailer-loading">
                    <div class="loading-spinner"></div>
                    <span>Loading trailer...</span>
                </div>
            `);
        }, 300);
        
        // Clear current trailer
        currentTrailer = null;
        
        // Track close event
        trackTrailerClose(movieTitle);
    }
    
    /**
     * Trap focus within modal for accessibility
     */
    function trapFocus(e) {
        if (!currentModal) return;
        
        const focusableElements = currentModal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const firstElement = focusableElements.first();
        const lastElement = focusableElements.last();
        
        if (e.shiftKey) {
            if (document.activeElement === firstElement[0]) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement[0]) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    }
    
    /**
     * Extract YouTube ID from various URL formats
     */
    function extractYouTubeId(url) {
        if (!url) return null;
        
        // If it's already an 11-character ID
        if (typeof url === 'string' && url.length === 11 && /^[a-zA-Z0-9_-]{11}$/.test(url)) {
            return url;
        }
        
        // Extract from various YouTube URL formats
        const patterns = [
            /(?:youtube\.com\/watch\?v=)([a-zA-Z0-9_-]{11})/,
            /(?:youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
            /(?:youtu\.be\/)([a-zA-Z0-9_-]{11})/,
            /(?:youtube\.com\/v\/)([a-zA-Z0-9_-]{11})/,
            /(?:youtube\.com\/.*[?&]v=)([a-zA-Z0-9_-]{11})/,
            /(?:youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/
        ];
        
        for (let i = 0; i < patterns.length; i++) {
            const match = url.match(patterns[i]);
            if (match && match[1]) {
                return match[1];
            }
        }
        
        return null;
    }
    
    /**
     * Show error message to user
     */
    function showErrorMessage(message) {
        const $error = $(`
            <div class="trailer-error-notification">
                <span>${message}</span>
                <button class="error-close">&times;</button>
            </div>
        `);
        
        $error.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: '#dc3545',
            color: 'white',
            padding: '15px 20px',
            borderRadius: '8px',
            zIndex: '1000000',
            fontSize: '14px',
            maxWidth: '300px',
            boxShadow: '0 4px 12px rgba(0, 0, 0, 0.3)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            gap: '10px'
        });
        
        $('body').append($error);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            $error.fadeOut(() => $error.remove());
        }, 5000);
        
        // Manual close
        $error.find('.error-close').on('click', () => {
            $error.fadeOut(() => $error.remove());
        });
    }
    
    /**
     * Track trailer views for analytics
     */
    function trackTrailerView(movieTitle, youtubeId) {
        // Google Analytics 4
        if (typeof gtag !== 'undefined') {
            gtag('event', 'trailer_view', {
                'movie_title': movieTitle,
                'youtube_id': youtubeId,
                'event_category': 'engagement',
                'event_label': movieTitle
            });
        }
        
        // Google Analytics Universal
        if (typeof ga !== 'undefined') {
            ga('send', 'event', 'Trailer', 'View', movieTitle);
        }
        
        // Custom tracking
        if (typeof upcomingMoviesTrailer !== 'undefined' && upcomingMoviesTrailer.trackingEnabled) {
            $.post(upcomingMoviesTrailer.ajaxUrl, {
                action: 'track_trailer_view',
                nonce: upcomingMoviesTrailer.nonce,
                movie_title: movieTitle,
                youtube_id: youtubeId
            });
        }
        
        console.log('📊 Tracked trailer view:', movieTitle);
    }
    
    /**
     * Track trailer close events
     */
    function trackTrailerClose(movieTitle) {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'trailer_close', {
                'movie_title': movieTitle,
                'event_category': 'engagement'
            });
        }
    }
    
    /**
     * Public API for external use
     */
    window.UpcomingMoviesTrailer = {
        open: function(youtubeId, title, triggerElement) {
            openTrailerModal(youtubeId, title || 'Movie Trailer', triggerElement);
        },
        close: closeTrailerModal,
        isOpen: function() {
            return isModalOpen;
        },
        extractId: extractYouTubeId,
        getCurrentTrailer: function() {
            return currentTrailer;
        },
        reinitialize: function() {
            initTrailerModals();
            bindTrailerEvents();
        }
    };
    
    /**
     * Initialize when document ready
     */
    $(document).ready(function() {
        initTrailerModals();
        
        console.log('🎬 Enhanced trailer system ready!');
        console.log('Found trailer elements:', $(
            '.play-trailer, .play-trailer-btn, .movie-trailer-button, .trailer-btn, ' +
            '.trailer-button, .watch-trailer, .trailer-thumbnail, [data-trailer], [data-youtube-id]'
        ).length);
    });
    
    /**
     * Re-initialize on AJAX content loads
     */
    $(document).ajaxComplete(function() {
        setTimeout(() => {
            console.log('🔄 Re-initializing trailer system after AJAX');
            bindTrailerEvents();
        }, 100);
    });
    
    /**
     * Handle dynamic content changes
     */
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            let shouldReinit = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length) {
                    for (let node of mutation.addedNodes) {
                        if (node.nodeType === 1) { // Element node
                            const $node = $(node);
                            if ($node.is('.play-trailer, [data-trailer]') || 
                                $node.find('.play-trailer, [data-trailer]').length) {
                                shouldReinit = true;
                                break;
                            }
                        }
                    }
                }
            });
            
            if (shouldReinit) {
                setTimeout(bindTrailerEvents, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
})(jQuery);