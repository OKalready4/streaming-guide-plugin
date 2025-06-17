<?php
/**
 * Social Media Sharing System - Facebook and Instagram only
 * Fixed version with proper state management and duplicate prevention
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Social_Media {
    private static $instance = null;
    private $state_manager;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load state manager
        if (!class_exists('Streaming_Guide_State_Manager')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-state-manager.php';
        }
        $this->state_manager = new Streaming_Guide_State_Manager();
        
        // Hook for automatic sharing when post is generated
        add_action('streaming_guide_post_generated', array($this, 'handle_automatic_share'), 10, 1);
        
        // Schedule delayed sharing
        add_action('streaming_guide_delayed_social_share', array($this, 'process_delayed_share'), 10, 1);
    }

    /**
     * Handle automatic sharing when a post is generated
     */
    public function handle_automatic_share($post_id) {
        // Check if auto-sharing is enabled
        if (!get_option('streaming_guide_auto_share_facebook')) {
            return;
        }
        
        // Get share delay (in minutes)
        $delay_minutes = intval(get_option('streaming_guide_share_delay', 5));
        
        if ($delay_minutes > 0) {
            // Schedule delayed sharing
            wp_schedule_single_event(
                time() + ($delay_minutes * 60),
                'streaming_guide_delayed_social_share',
                array($post_id)
            );
        } else {
            // Share immediately
            $this->process_delayed_share($post_id);
        }
    }
    
    /**
     * Process delayed social media sharing
     */
    public function process_delayed_share($post_id) {
        // Share to Facebook if enabled
        if (get_option('streaming_guide_auto_share_facebook')) {
            $this->share_to_facebook($post_id);
        }
        
        // Share to Instagram if enabled (placeholder for future)
        if (get_option('streaming_guide_auto_share_instagram')) {
            $this->share_to_instagram($post_id);
        }
    }

    /**
     * Share a post to Facebook
     */
    public function share_to_facebook($post_id) {
        // Check if already shared
        if ($this->state_manager->was_shared_to_platform($post_id, 'facebook')) {
            error_log("[Social] Post #{$post_id} already shared to Facebook.");
            return false;
        }
        
        // Get Facebook settings
        $page_id = get_option('streaming_guide_facebook_page_id');
        $access_token = get_option('streaming_guide_facebook_access_token');
        
        if (empty($page_id) || empty($access_token)) {
            error_log("[Social] Cannot share post #{$post_id}: Facebook credentials not configured.");
            return false;
        }
        
        // Get post data
        $post = get_post($post_id);
        if (!$post) {
            error_log("[Social] Cannot share: Post #{$post_id} not found.");
            return false;
        }
        
        // Generate message
        $message = $this->generate_facebook_message($post_id);
        $post_url = get_permalink($post_id);
        $featured_image_url = get_the_post_thumbnail_url($post_id, 'large');
        
        // Determine API endpoint based on whether we have an image
        if ($featured_image_url) {
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
        
        // Make API request
        $response = wp_remote_post($api_url, array(
            'body' => $body,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            )
        ));
        
        // Handle response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("[Social] Failed to share post #{$post_id}. WP_Error: " . $error_message);
            $this->state_manager->track_social_share($post_id, 'facebook', null, 'failed', $error_message);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code === 200 && !empty($response_body['id'])) {
            // Success!
            $facebook_post_id = $response_body['id'];
            $this->state_manager->track_social_share($post_id, 'facebook', $facebook_post_id, 'success');
            error_log("[Social] Successfully shared post #{$post_id} to Facebook. FB Post ID: " . $facebook_post_id);
            
            // Save Facebook post ID as post meta for reference
            update_post_meta($post_id, '_facebook_post_id', $facebook_post_id);
            
            return true;
        } else {
            // Error
            $error_message = isset($response_body['error']['message']) 
                ? $response_body['error']['message'] 
                : 'Unknown Facebook API error (Code: ' . $response_code . ')';
            
            error_log("[Social] Failed to share post #{$post_id}. FB Error: " . $error_message);
            $this->state_manager->track_social_share($post_id, 'facebook', null, 'failed', $error_message);
            
            return false;
        }
    }
    
    /**
     * Share to Instagram (placeholder for future implementation)
     */
    public function share_to_instagram($post_id) {
        // Instagram sharing requires Instagram Business Account connected to Facebook Page
        // This is a placeholder for future implementation
        error_log("[Social] Instagram sharing not yet implemented for post #{$post_id}");
        return false;
    }

    /**
     * Generate dynamic Facebook message
     */
    private function generate_facebook_message($post_id) {
        $title = get_the_title($post_id);
        $platform = get_post_meta($post_id, 'streaming_platform', true);
        $platform_name = $this->get_platform_display_name($platform);
        $article_type = get_post_meta($post_id, 'article_type', true);
        
        // Get content type and additional metadata
        $featured_content_type = get_post_meta($post_id, 'featured_content_type', true);
        $is_movie = ($featured_content_type === 'movie');
        
        // Generate contextual message based on article type
        switch ($article_type) {
            case 'weekly_whats_new':
                $templates = array(
                    "🎬 New on {platform} this week! Check out our latest spotlight: {title}",
                    "📺 What's streaming on {platform}? We're featuring {title} in this week's guide!",
                    "🍿 Weekend plans? {title} just landed on {platform} - here's what you need to know!",
                    "✨ Fresh on {platform}: {title} is making waves. Get the full scoop here!",
                    "🎯 This week's must-watch on {platform}: {title}. Find out why everyone's talking about it!"
                );
                break;
                
            case 'monthly_roundup':
                $templates = array(
                    "📅 {platform} Monthly Highlights! Featuring {title} and more amazing content",
                    "🌟 Best of {platform} this month - {title} leads our curated selection!",
                    "📺 Monthly streaming guide for {platform} is here! Don't miss {title}",
                    "🎬 {platform}'s month in review: {title} and other gems you shouldn't miss"
                );
                break;
                
            case 'trending':
                $templates = array(
                    "🔥 Trending NOW on {platform}: {title} is taking over! See what the buzz is about",
                    "📈 Everyone's watching {title} on {platform} - here's why you should too!",
                    "🌊 Riding the wave: {title} is trending on {platform}. Dive into our analysis!",
                    "⚡ Hot on {platform}: {title} is the talk of the town. Get the inside scoop!"
                );
                break;
                
            default:
                $templates = array(
                    "🎬 New streaming guide: {title} on {platform}. Everything you need to know!",
                    "📺 Just published: Our take on {title}, now streaming on {platform}",
                    "🍿 {title} is now on {platform} - check out our detailed review and guide!",
                    "✨ Spotlight on {platform}: {title} - worth your time? Find out here!"
                );
        }
        
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
     * Get platform display name
     */
    private function get_platform_display_name($platform) {
        $names = array(
            'netflix' => 'Netflix',
            'amazon-prime' => 'Prime Video',
            'disney-plus' => 'Disney+',
            'hulu' => 'Hulu',
            'max' => 'Max',
            'paramount-plus' => 'Paramount+',
            'apple-tv' => 'Apple TV+'
        );
        
        return isset($names[$platform]) ? $names[$platform] : ucfirst($platform);
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
}