<?php
/**
 * Social Media Sharing System - COMPLETELY FIXED VERSION
 * 
 * FIXED: Bulletproof duplicate prevention, error handling, and memory management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Social_Media {
    private static $instance = null;
    private $state_manager;
    private $error_handler;
    private static $processing_posts = array(); // Prevent concurrent processing
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load dependencies with error handling
        $this->load_dependencies();
        
        // Hook for automatic sharing when post is generated
        add_action('streaming_guide_post_generated', array($this, 'handle_automatic_share'), 10, 1);
        
        // Schedule delayed sharing with duplicate prevention
        add_action('streaming_guide_delayed_social_share', array($this, 'process_delayed_share'), 10, 1);
        
        // Clean up stuck processing states
        add_action('init', array($this, 'cleanup_stuck_processing'));
    }
    
    /**
     * BULLETPROOF: Load dependencies with comprehensive error handling and validation
     */
    private function load_dependencies() {
        try {
            // Define dependency paths with validation
            $dependencies = array(
                'Streaming_Guide_State_Manager' => 'admin/class-state-manager.php',
                'Streaming_Guide_Error_Handler' => 'admin/class-error-handler.php'
            );
            
            foreach ($dependencies as $class_name => $file_path) {
                if (!class_exists($class_name)) {
                    $full_path = plugin_dir_path(dirname(__FILE__)) . $file_path;
                    
                    if (!file_exists($full_path)) {
                        throw new Exception("Required dependency file not found: {$full_path}");
                    }
                    
                    require_once $full_path;
                    
                    if (!class_exists($class_name)) {
                        throw new Exception("Failed to load required class: {$class_name}");
                    }
                }
            }
            
            // Initialize with error handling
            $this->state_manager = new Streaming_Guide_State_Manager();
            $this->error_handler = new Streaming_Guide_Error_Handler();
            
            // Validate initialization
            if (!$this->state_manager || !$this->error_handler) {
                throw new Exception("Failed to initialize required dependencies");
            }
            
        } catch (Exception $e) {
            error_log('[Social Media] Failed to load dependencies: ' . $e->getMessage());
            
            // Create fallback instances to prevent fatal errors
            $this->state_manager = null;
            $this->error_handler = null;
        }
    }

    /**
     * FIXED: Handle automatic sharing with bulletproof duplicate prevention
     */
    public function handle_automatic_share($post_id) {
        // Validate post ID
        if (!$post_id || !is_numeric($post_id)) {
            $this->log_error("Invalid post ID provided: {$post_id}");
            return false;
        }
        
        // Check if auto-sharing is enabled
        if (!get_option('streaming_guide_auto_share_facebook', false)) {
            $this->log_info("Auto-sharing disabled for post #{$post_id}");
            return false;
        }
        
        // CRITICAL: Prevent concurrent processing of the same post
        if (in_array($post_id, self::$processing_posts)) {
            $this->log_warning("Post #{$post_id} already being processed, skipping");
            return false;
        }
        
        // CRITICAL: Check if already shared using multiple methods
        if ($this->is_already_shared($post_id)) {
            $this->log_info("Post #{$post_id} already shared to Facebook, skipping");
            return false;
        }
        
        // Mark as processing to prevent duplicates
        self::$processing_posts[] = $post_id;
        update_post_meta($post_id, '_facebook_sharing_status', 'processing');
        update_post_meta($post_id, '_facebook_processing_start', time());
        
        try {
            // Get share delay (in minutes)
            $delay_minutes = intval(get_option('streaming_guide_share_delay', 5));
            
            if ($delay_minutes > 0) {
                // Schedule delayed sharing
                $scheduled = wp_schedule_single_event(
                    time() + ($delay_minutes * 60),
                    'streaming_guide_delayed_social_share',
                    array($post_id)
                );
                
                if ($scheduled === false) {
                    throw new Exception('Failed to schedule delayed share');
                }
                
                $this->log_info("Scheduled delayed share for post #{$post_id} in {$delay_minutes} minutes");
                update_post_meta($post_id, '_facebook_sharing_status', 'scheduled');
                
            } else {
                // Share immediately
                $this->process_delayed_share($post_id);
            }
            
            return true;
            
        } catch (Exception $e) {
            // Clean up on error
            $this->cleanup_processing_state($post_id);
            $this->log_error("Failed to handle automatic share for post #{$post_id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * FIXED: Bulletproof duplicate checking
     */
    private function is_already_shared($post_id) {
        // Check 1: State manager
        if ($this->state_manager && $this->state_manager->was_shared_to_platform($post_id, 'facebook')) {
            return true;
        }
        
        // Check 2: Post meta
        $facebook_post_id = get_post_meta($post_id, '_facebook_post_id', true);
        if (!empty($facebook_post_id)) {
            return true;
        }
        
        // Check 3: Sharing status
        $sharing_status = get_post_meta($post_id, '_facebook_sharing_status', true);
        if ($sharing_status === 'completed') {
            return true;
        }
        
        // Check 4: Recent processing (prevent getting stuck)
        $processing_start = get_post_meta($post_id, '_facebook_processing_start', true);
        if ($processing_start && (time() - $processing_start) < 3600) { // 1 hour timeout
            return true;
        }
        
        return false;
    }
    
    /**
     * FIXED: Process delayed social media sharing with full error handling
     */
    public function process_delayed_share($post_id) {
        // Validate post
        if (!$post_id || !is_numeric($post_id)) {
            $this->log_error("Invalid post ID for delayed share: {$post_id}");
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            $this->log_error("Post #{$post_id} not found for delayed share");
            return;
        }
        
        // Final duplicate check
        if ($this->is_already_shared($post_id)) {
            $this->log_info("Post #{$post_id} already shared during delayed processing");
            $this->cleanup_processing_state($post_id);
            return;
        }
        
        try {
            // Mark as processing
            if (!in_array($post_id, self::$processing_posts)) {
                self::$processing_posts[] = $post_id;
            }
            update_post_meta($post_id, '_facebook_sharing_status', 'processing');
            
            // Share to Facebook if enabled
            if (get_option('streaming_guide_auto_share_facebook', false)) {
                $result = $this->share_to_facebook($post_id);
                
                if ($result) {
                    update_post_meta($post_id, '_facebook_sharing_status', 'completed');
                    update_post_meta($post_id, '_facebook_shared_at', current_time('mysql'));
                    $this->log_info("Successfully completed Facebook share for post #{$post_id}");
                } else {
                    update_post_meta($post_id, '_facebook_sharing_status', 'failed');
                    $this->log_error("Facebook sharing failed for post #{$post_id}");
                }
            }
            
        } catch (Exception $e) {
            update_post_meta($post_id, '_facebook_sharing_status', 'failed');
            $this->log_error("Exception during delayed share for post #{$post_id}: " . $e->getMessage());
            
        } finally {
            // Always clean up processing state
            $this->cleanup_processing_state($post_id);
        }
    }

    /**
     * FIXED: Share to Facebook with comprehensive error handling and rate limiting
     */
    public function share_to_facebook($post_id) {
        // Final safety check
        if ($this->is_already_shared($post_id)) {
            $this->log_warning("Post #{$post_id} already shared, aborting Facebook share");
            return false;
        }
        
        // Get Facebook settings with validation
        $page_id = get_option('streaming_guide_facebook_page_id');
        $access_token = get_option('streaming_guide_facebook_access_token');
        
        if (empty($page_id) || empty($access_token)) {
            $this->log_error("Facebook credentials not configured for post #{$post_id}");
            return false;
        }
        
        // Get post data with validation
        $post = get_post($post_id);
        if (!$post) {
            $this->log_error("Post #{$post_id} not found for Facebook sharing");
            return false;
        }
        
        try {
            // Rate limiting check
            if (!$this->check_rate_limit()) {
                $this->log_warning("Facebook rate limit reached, skipping post #{$post_id}");
                // Reschedule for later
                wp_schedule_single_event(time() + 3600, 'streaming_guide_delayed_social_share', array($post_id));
                return false;
            }
            
            // Generate message and get content
            $message = $this->generate_facebook_message($post_id);
            $post_url = get_permalink($post_id);
            $featured_image_url = get_the_post_thumbnail_url($post_id, 'large');
            
            // Validate content
            if (empty($message) || empty($post_url)) {
                throw new Exception("Invalid content for post #{$post_id}");
            }
            
            // Prepare API request
            if ($featured_image_url && filter_var($featured_image_url, FILTER_VALIDATE_URL)) {
                // Post with photo
                $api_url = "https://graph.facebook.com/v19.0/{$page_id}/photos";
                $body = array(
                    'caption' => $message . "\n\n📺 Read more: " . $post_url,
                    'url' => $featured_image_url,
                    'access_token' => $access_token,
                );
            } else {
                // Text post with link
                $api_url = "https://graph.facebook.com/v19.0/{$page_id}/feed";
                $body = array(
                    'message' => $message,
                    'link' => $post_url,
                    'access_token' => $access_token,
                );
            }
            
            // Make API request with retries
            $response = $this->make_facebook_request($api_url, $body);
            
            if (is_wp_error($response)) {
                throw new Exception("Facebook API request failed: " . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($response_code === 200 && !empty($response_body['id'])) {
                // Success!
                $facebook_post_id = $response_body['id'];
                
                // Record success in multiple places
                if ($this->state_manager) {
                    $this->state_manager->track_social_share($post_id, 'facebook', $facebook_post_id, 'success');
                }
                update_post_meta($post_id, '_facebook_post_id', $facebook_post_id);
                update_post_meta($post_id, '_facebook_shared_at', current_time('mysql'));
                
                $this->log_info("Successfully shared post #{$post_id} to Facebook. FB Post ID: {$facebook_post_id}");
                
                // Update rate limit tracking
                $this->update_rate_limit_tracking();
                
                return true;
                
            } else {
                // Handle API errors
                $error_message = isset($response_body['error']['message']) 
                    ? $response_body['error']['message'] 
                    : 'Unknown Facebook API error (Code: ' . $response_code . ')';
                
                // Check for specific error types
                if (isset($response_body['error']['code'])) {
                    $error_code = $response_body['error']['code'];
                    
                    // Rate limiting
                    if ($error_code == 32 || $error_code == 4) {
                        $this->log_warning("Facebook rate limit hit for post #{$post_id}");
                        // Reschedule for later
                        wp_schedule_single_event(time() + 3600, 'streaming_guide_delayed_social_share', array($post_id));
                        return false;
                    }
                    
                    // Duplicate content
                    if ($error_code == 506) {
                        $this->log_info("Facebook reported duplicate content for post #{$post_id}, marking as shared");
                        update_post_meta($post_id, '_facebook_post_id', 'duplicate');
                        return true;
                    }
                }
                
                throw new Exception($error_message);
            }
            
        } catch (Exception $e) {
            $this->log_error("Facebook sharing failed for post #{$post_id}: " . $e->getMessage());
            
            if ($this->state_manager) {
                $this->state_manager->track_social_share($post_id, 'facebook', null, 'failed', $e->getMessage());
            }
            
            return false;
        }
    }
    
    /**
     * Make Facebook API request with retries and timeouts
     */
    private function make_facebook_request($url, $body, $max_retries = 2) {
        $attempt = 0;
        
        while ($attempt <= $max_retries) {
            $response = wp_remote_post($url, array(
                'body' => $body,
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'sslverify' => true,
                'blocking' => true
            ));
            
            // Check for network errors
            if (is_wp_error($response)) {
                $attempt++;
                if ($attempt > $max_retries) {
                    return $response;
                }
                
                // Wait before retry
                sleep(2);
                continue;
            }
            
            // Check response code
            $response_code = wp_remote_retrieve_response_code($response);
            
            // Retry on server errors
            if ($response_code >= 500 && $attempt < $max_retries) {
                $attempt++;
                sleep(2);
                continue;
            }
            
            return $response;
        }
        
        return new WP_Error('max_retries', 'Maximum retries exceeded');
    }
    
    /**
     * Check Facebook rate limits
     */
    private function check_rate_limit() {
        $last_post_time = get_option('streaming_guide_fb_last_post', 0);
        $posts_this_hour = get_option('streaming_guide_fb_posts_hour', 0);
        $hour_start = get_option('streaming_guide_fb_hour_start', 0);
        
        $current_time = time();
        
        // Reset hourly counter if needed
        if ($current_time - $hour_start > 3600) {
            update_option('streaming_guide_fb_posts_hour', 0);
            update_option('streaming_guide_fb_hour_start', $current_time);
            $posts_this_hour = 0;
        }
        
        // Check minimum interval (30 seconds between posts)
        if ($current_time - $last_post_time < 30) {
            return false;
        }
        
        // Check hourly limit (max 25 posts per hour to be safe)
        if ($posts_this_hour >= 25) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update rate limit tracking
     */
    private function update_rate_limit_tracking() {
        $current_time = time();
        update_option('streaming_guide_fb_last_post', $current_time);
        
        $posts_this_hour = get_option('streaming_guide_fb_posts_hour', 0);
        update_option('streaming_guide_fb_posts_hour', $posts_this_hour + 1);
    }
    
    /**
     * Generate dynamic Facebook message
     */
    private function generate_facebook_message($post_id) {
        $title = get_the_title($post_id);
        $platform = get_post_meta($post_id, 'streaming_platform', true);
        $platform_name = $this->get_platform_display_name($platform);
        $article_type = get_post_meta($post_id, 'article_type', true);
        
        // Get content type
        $featured_content_type = get_post_meta($post_id, 'featured_content_type', true);
        $is_movie = ($featured_content_type === 'movie');
        
        // Generate contextual message based on article type
        $templates = $this->get_message_templates($article_type, $platform_name);
        
        // Pick random template
        $template = $templates[array_rand($templates)];
        
        // Replace placeholders
        $message = str_replace(
            array('{platform}', '{title}'),
            array($platform_name, $title),
            $template
        );
        
        // Add hashtags
        $hashtags = $this->generate_hashtags($platform, $article_type, $is_movie);
        $message .= "\n\n" . implode(' ', $hashtags);
        
        return $message;
    }
    
    /**
     * Get message templates for different article types
     */
    private function get_message_templates($article_type, $platform_name) {
        switch ($article_type) {
            case 'weekly_whats_new':
                return array(
                    "🎬 New on {platform} this week! Check out our latest spotlight: {title}",
                    "📺 What's streaming on {platform}? We're featuring {title} in this week's guide!",
                    "🍿 Weekend plans? {title} just landed on {platform} - here's what you need to know!",
                    "✨ Fresh on {platform}: {title} is making waves. Get the full scoop here!",
                    "🎯 This week's must-watch on {platform}: {title}. Find out why everyone's talking about it!"
                );
                
            case 'monthly_roundup':
                return array(
                    "📅 {platform} Monthly Highlights! Featuring {title} and more amazing content",
                    "🌟 Best of {platform} this month - {title} leads our curated selection!",
                    "📺 Monthly streaming guide for {platform} is here! Don't miss {title}",
                    "🎬 {platform}'s month in review: {title} and other gems you shouldn't miss"
                );
                
            case 'trending':
                return array(
                    "🔥 Trending NOW on {platform}: {title} is taking over! See what the buzz is about",
                    "📈 Everyone's watching {title} on {platform} - here's why you should too!",
                    "🌊 Riding the wave: {title} is trending on {platform}. Dive into our analysis!",
                    "⚡ Hot on {platform}: {title} is the talk of the town. Get the inside scoop!"
                );
                
            default:
                return array(
                    "🎬 New streaming guide: {title} on {platform}. Everything you need to know!",
                    "📺 Just published: Our take on {title}, now streaming on {platform}",
                    "🍿 {title} is now on {platform} - check out our detailed review and guide!",
                    "✨ Spotlight on {platform}: {title} - worth your time? Find out here!"
                );
        }
    }
    
    /**
     * Generate relevant hashtags
     */
    private function generate_hashtags($platform, $article_type, $is_movie = true) {
        $hashtags = array();
        
        // Platform hashtag
        $platform_tags = array(
            'netflix' => '#Netflix',
            'amazon-prime' => '#PrimeVideo', 
            'disney-plus' => '#DisneyPlus',
            'hulu' => '#Hulu',
            'max' => '#MaxStreaming',
            'paramount-plus' => '#ParamountPlus',
            'apple-tv' => '#AppleTVPlus'
        );
        
        if (isset($platform_tags[$platform])) {
            $hashtags[] = $platform_tags[$platform];
        }
        
        // Content type hashtags
        $hashtags[] = $is_movie ? '#Movies' : '#TVShows';
        $hashtags[] = '#Streaming';
        
        // Article type specific hashtags
        switch ($article_type) {
            case 'weekly_whats_new':
                $hashtags[] = '#NewOnStreaming';
                $hashtags[] = '#WeekendWatch';
                break;
            case 'monthly_roundup':
                $hashtags[] = '#MonthlyPicks';
                break;
            case 'trending':
                $hashtags[] = '#Trending';
                $hashtags[] = '#MustWatch';
                break;
        }
        
        // General hashtags
        $hashtags[] = '#StreamingGuide';
        $hashtags[] = '#WhatToWatch';
        
        return array_slice($hashtags, 0, 8); // Limit to 8 hashtags
    }
    
    /**
     * BULLETPROOF: Get platform display name with consistent mapping
     */
    private function get_platform_display_name($platform) {
        // CRITICAL: Platform mapping must be consistent across entire plugin
        $platform_mappings = array(
            'netflix' => 'Netflix',
            'amazon-prime' => 'Prime Video',
            'amazon' => 'Prime Video',  // Fallback mapping
            'disney-plus' => 'Disney+',
            'disney' => 'Disney+',     // Fallback mapping
            'hulu' => 'Hulu',
            'max' => 'Max',
            'hbo' => 'Max',            // Fallback mapping for old HBO references
            'paramount-plus' => 'Paramount+',
            'paramount' => 'Paramount+', // Fallback mapping
            'apple-tv' => 'Apple TV+',
            'apple' => 'Apple TV+'     // Fallback mapping
        );
        
        // Normalize platform identifier
        $normalized_platform = strtolower(trim($platform));
        
        if (isset($platform_mappings[$normalized_platform])) {
            return $platform_mappings[$normalized_platform];
        }
        
        // Fallback: capitalize first letter and replace hyphens
        return ucwords(str_replace(array('-', '_'), ' ', $normalized_platform));
    }
    
    /**
     * Clean up processing state
     */
    private function cleanup_processing_state($post_id) {
        // Remove from processing array
        $key = array_search($post_id, self::$processing_posts);
        if ($key !== false) {
            unset(self::$processing_posts[$key]);
        }
        
        // Clear processing meta
        delete_post_meta($post_id, '_facebook_processing_start');
    }
    
    /**
     * Clean up stuck processing states
     */
    public function cleanup_stuck_processing() {
        // Only run once per hour
        if (get_transient('streaming_guide_cleanup_running')) {
            return;
        }
        
        set_transient('streaming_guide_cleanup_running', true, 3600);
        
        try {
            // Find posts stuck in processing for more than 1 hour
            $stuck_posts = get_posts(array(
                'post_type' => 'post',
                'meta_query' => array(
                    array(
                        'key' => '_facebook_sharing_status',
                        'value' => 'processing',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_facebook_processing_start',
                        'value' => time() - 3600,
                        'compare' => '<',
                        'type' => 'NUMERIC'
                    )
                ),
                'posts_per_page' => 10
            ));
            
            foreach ($stuck_posts as $post) {
                $this->log_warning("Cleaning up stuck processing state for post #{$post->ID}");
                update_post_meta($post->ID, '_facebook_sharing_status', 'failed');
                $this->cleanup_processing_state($post->ID);
            }
            
        } catch (Exception $e) {
            $this->log_error("Error during cleanup: " . $e->getMessage());
        }
    }
    
    /**
     * Test Facebook connection
     */
    public function test_facebook_connection() {
        $page_id = get_option('streaming_guide_facebook_page_id');
        $access_token = get_option('streaming_guide_facebook_access_token');
        
        if (empty($page_id) || empty($access_token)) {
            return new WP_Error('missing_credentials', 'Facebook Page ID or Access Token not configured.');
        }
        
        // Test by getting page info
        $api_url = "https://graph.facebook.com/v19.0/{$page_id}?fields=name,id&access_token={$access_token}";
        
        $response = wp_remote_get($api_url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code === 200 && !empty($response_body['id'])) {
            return array(
                'success' => true,
                'page_name' => $response_body['name'],
                'page_id' => $response_body['id']
            );
        } else {
            $error_message = isset($response_body['error']['message']) 
                ? $response_body['error']['message'] 
                : 'Unknown error';
            return new WP_Error('api_error', $error_message);
        }
    }
    
    /**
     * Logging helpers
     */
    private function log_info($message) {
        if ($this->error_handler) {
            $this->error_handler->log_info("[Social Media] {$message}");
        } else {
            error_log("[Social Media Info] {$message}");
        }
    }
    
    private function log_warning($message) {
        if ($this->error_handler) {
            $this->error_handler->log_warning("[Social Media] {$message}");
        } else {
            error_log("[Social Media Warning] {$message}");
        }
    }
    
    private function log_error($message) {
        if ($this->error_handler) {
            $this->error_handler->log_error("[Social Media] {$message}");
        } else {
            error_log("[Social Media Error] {$message}");
        }
    }
}