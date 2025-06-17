/**
 * Streaming Guide Frontend JavaScript
 * Handles interactive features for movie/show articles
 * Compatible with existing CSS and WordPress
 */

(function($) {
    'use strict';

    // Ensure we have data from the localized script
    if (typeof streamingGuide === 'undefined') {
        streamingGuide = {
            ajaxurl: '/wp-admin/admin-ajax.php',
            nonce: '',
            isStreamingPost: false,
            debug: false
        };
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only run if jQuery is properly loaded
        if (typeof $ !== 'undefined' && $.fn) {
            initStreamingGuide();
        } else {
            console.warn('Streaming Guide: jQuery not properly loaded');
        }
    });

    /**
     * Main initialization function
     */
    function initStreamingGuide() {
        if (streamingGuide.debug) {
            console.log('Streaming Guide frontend initialized');
        }
        
        // Initialize all components
        initSmoothScrolling();
        initCardAnimations();
        initSocialSharing();
        initTrailerHandling();
        initLazyLoading();
        initAccessibility();
        initAnalytics();
        
        // Mark as initialized
        $('body').addClass('streaming-guide-initialized');
        
        // Log successful initialization
        if (streamingGuide.debug) {
            console.log('All Streaming Guide components initialized successfully');
        }
    }

    /**
     * Smooth scrolling for internal links
     */
    function initSmoothScrolling() {
        $('a[href^="#"]').on('click', function(e) {
            const href = $(this).attr('href');
            const target = $(href);
            
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 600);
            }
        });
    }

    /**
     * Enhanced card hover animations
     */
    function initCardAnimations() {
        // Add hover effects to streaming guide cards
        $('.streaming-guide-card, .content-item, .movie-item, .tv-item').hover(
            function() {
                $(this).addClass('hovered');
            },
            function() {
                $(this).removeClass('hovered');
            }
        );

        // Intersection Observer for animations (if supported)
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('in-view');
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            // Observe content items
            $('.content-item, .streaming-guide-card').each(function() {
                observer.observe(this);
            });
        }
    }

    /**
     * Social sharing functionality
     */
    function initSocialSharing() {
        // Handle social sharing buttons
        $(document).on('click', '.social-button, [data-share]', function(e) {
            e.preventDefault();
            
            const platform = $(this).data('platform') || $(this).data('share');
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(document.title);
            
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                    break;
                case 'pinterest':
                    const image = $('meta[property="og:image"]').attr('content') || '';
                    shareUrl = `https://pinterest.com/pin/create/button/?url=${url}&description=${title}&media=${encodeURIComponent(image)}`;
                    break;
                default:
                    if (streamingGuide.debug) {
                        console.warn('Unknown social platform:', platform);
                    }
                    return;
            }
            
            if (shareUrl) {
                openShareWindow(shareUrl, platform);
                trackShare(platform);
            }
        });
    }

    /**
     * Open social sharing window
     */
    function openShareWindow(url, platform) {
        const popup = window.open(
            url,
            'share-' + platform,
            'width=600,height=400,scrollbars=no,resizable=no,menubar=no,toolbar=no,status=no'
        );
        
        if (popup) {
            popup.focus();
        }
    }

    /**
     * Track social shares
     */
    function trackShare(platform) {
        if (!streamingGuide.isStreamingPost) return;
        
        $.ajax({
            url: streamingGuide.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_frontend',
                sg_action: 'share_count',
                platform: platform,
                post_id: getPostId(),
                streaming_guide_nonce: streamingGuide.nonce
            },
            success: function(response) {
                if (streamingGuide.debug && response.success) {
                    console.log('Share tracked:', response.data);
                }
            }
        });
    }

    /**
     * Enhanced trailer and video handling
     */
    function initTrailerHandling() {
        // Handle YouTube embeds
        $('.wp-block-embed-youtube iframe, .wp-block-embed iframe[src*="youtube"]').each(function() {
            const $iframe = $(this);
            const src = $iframe.attr('src');
            
            if (src && src.includes('youtube.com')) {
                // Add parameters for better UX
                const separator = src.includes('?') ? '&' : '?';
                const enhancedSrc = src + separator + 'rel=0&modestbranding=1&playsinline=1';
                $iframe.attr('src', enhancedSrc);
            }
        });

        // Add play state tracking
        $(window).on('message', function(e) {
            const data = e.originalEvent.data;
            if (typeof data === 'string' && data.includes('youtube')) {
                try {
                    const videoData = JSON.parse(data);
                    if (videoData.event === 'video-progress' && streamingGuide.debug) {
                        console.log('Video progress:', videoData);
                    }
                } catch (err) {
                    // Ignore parsing errors
                }
            }
        });
    }

    /**
     * Lazy loading for images
     */
    function initLazyLoading() {
        // Native lazy loading support
        $('img').each(function() {
            if (!$(this).attr('loading')) {
                $(this).attr('loading', 'lazy');
            }
        });

        // Fallback for older browsers
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const $img = $(img);
                        
                        if ($img.data('src')) {
                            $img.attr('src', $img.data('src'));
                            $img.removeClass('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });

            $('img[data-src]').each(function() {
                imageObserver.observe(this);
            });
        }
    }

    /**
     * Accessibility enhancements
     */
    function initAccessibility() {
        // Add ARIA labels to interactive elements
        $('.social-button').each(function() {
            const platform = $(this).data('platform');
            if (platform && !$(this).attr('aria-label')) {
                $(this).attr('aria-label', `Share on ${platform}`);
            }
        });

        // Keyboard navigation for cards
        $('.content-item, .streaming-guide-card').attr('tabindex', '0');
        
        $('.content-item, .streaming-guide-card').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const link = $(this).find('a').first();
                if (link.length) {
                    e.preventDefault();
                    link[0].click();
                }
            }
        });

        // Skip to content link
        if (!$('#skip-to-content').length) {
            $('body').prepend('<a href="#main" id="skip-to-content" class="screen-reader-text">Skip to content</a>');
        }
    }

    /**
     * Analytics and view tracking
     */
    function initAnalytics() {
        if (!streamingGuide.isStreamingPost) return;
        
        // Track page view
        trackPageView();
        
        // Track scroll depth
        let maxScroll = 0;
        let scrollTracked = {};
        
        $(window).on('scroll', debounce(function() {
            const scrollPercent = Math.round(($(window).scrollTop() / ($(document).height() - $(window).height())) * 100);
            maxScroll = Math.max(maxScroll, scrollPercent);
            
            // Track milestones
            [25, 50, 75, 90].forEach(function(milestone) {
                if (scrollPercent >= milestone && !scrollTracked[milestone]) {
                    scrollTracked[milestone] = true;
                    if (streamingGuide.debug) {
                        console.log('Scroll milestone:', milestone + '%');
                    }
                }
            });
        }, 250));
    }

    /**
     * Track page view
     */
    function trackPageView() {
        $.ajax({
            url: streamingGuide.ajaxurl,
            type: 'POST',
            data: {
                action: 'streaming_guide_frontend',
                sg_action: 'track_view',
                post_id: getPostId(),
                streaming_guide_nonce: streamingGuide.nonce
            },
            success: function(response) {
                if (streamingGuide.debug && response.success) {
                    console.log('Page view tracked:', response.data);
                }
            }
        });
    }

    /**
     * Get current post ID
     */
    function getPostId() {
        return $('body').attr('class').match(/postid-(\d+)/)?.[1] || 0;
    }

    /**
     * Debounce function for performance
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Mobile-specific enhancements
     */
    function initMobileEnhancements() {
        if (window.innerWidth <= 768) {
            // Touch-friendly interactions
            $('.content-item, .streaming-guide-card').on('touchstart', function() {
                $(this).addClass('touch-active');
            }).on('touchend', function() {
                $(this).removeClass('touch-active');
            });
        }
    }

    // Initialize mobile enhancements
    initMobileEnhancements();
    
    // Re-run on window resize
    $(window).on('resize', debounce(initMobileEnhancements, 250));

})(jQuery);