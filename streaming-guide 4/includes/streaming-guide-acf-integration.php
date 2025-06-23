<?php
/**
 * ACF Integration for Streaming Guide Pro
 * Handles affiliate links and additional metadata
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_ACF_Integration {
    
    /**
     * Initialize ACF integration
     */
    public static function init() {
        // Hook into post creation to add ACF fields
        add_action('streaming_guide_post_created', array(__CLASS__, 'populate_acf_fields'), 10, 3);
        
        // Add affiliate links to content
        add_filter('the_content', array(__CLASS__, 'add_affiliate_links'), 15);
    }
    
    /**
     * Populate ACF fields when post is created
     */
    public static function populate_acf_fields($post_id, $content_data, $generator_type) {
        if (!function_exists('update_field')) {
            return;
        }
        
        // Set platform
        if (!empty($content_data['platform'])) {
            update_field('platform', $content_data['platform'], $post_id);
        }
        
        // Set genres
        if (!empty($content_data['genres'])) {
            $genre_names = array_column($content_data['genres'], 'name');
            update_field('genres', implode(', ', $genre_names), $post_id);
        }
        
        // Set AI summaries
        if (!empty($content_data['ai_summary_vibe_check'])) {
            update_field('ai_summary_vibe_check', $content_data['ai_summary_vibe_check'], $post_id);
        }
        
        if (!empty($content_data['ai_summary_why_watch'])) {
            update_field('ai_summary_why_watch', $content_data['ai_summary_why_watch'], $post_id);
        }
        
        // Set trailer URL
        if (!empty($content_data['trailer_url'])) {
            update_field('trailer_url', $content_data['trailer_url'], $post_id);
        }
        
        // Generate affiliate link based on platform
        $affiliate_link = self::generate_affiliate_link($content_data);
        if ($affiliate_link) {
            update_field('affiliate_link', $affiliate_link, $post_id);
        }
        
        // Set IMDb rating if available
        if (!empty($content_data['imdb_rating'])) {
            update_field('rating_imdb', $content_data['imdb_rating'], $post_id);
        }
    }
    
    /**
     * Generate affiliate link based on platform and content
     */
    private static function generate_affiliate_link($content_data) {
        $platform = $content_data['platform'] ?? '';
        $title = $content_data['title'] ?? $content_data['name'] ?? '';
        $media_type = $content_data['media_type'] ?? 'movie';
        
        // URL encode the title for search queries
        $encoded_title = urlencode($title);
        
        // Platform-specific affiliate link patterns
        $affiliate_patterns = array(
            'netflix' => "https://www.netflix.com/search?q={$encoded_title}",
            'amazon' => "https://www.amazon.com/s?k={$encoded_title}&i=instant-video&tag=YOUR_AFFILIATE_TAG",
            'hulu' => "https://www.hulu.com/search?q={$encoded_title}",
            'disney' => "https://www.disneyplus.com/search?q={$encoded_title}",
            'hbo' => "https://www.max.com/search?q={$encoded_title}",
            'paramount' => "https://www.paramountplus.com/search?q={$encoded_title}",
            'apple' => "https://tv.apple.com/search?term={$encoded_title}"
        );
        
        // Get custom affiliate tags from settings if available
        $amazon_tag = get_option('streaming_guide_amazon_affiliate_tag', 'YOUR_AFFILIATE_TAG');
        if ($platform === 'amazon' && $amazon_tag) {
            $affiliate_patterns['amazon'] = str_replace('YOUR_AFFILIATE_TAG', $amazon_tag, $affiliate_patterns['amazon']);
        }
        
        return $affiliate_patterns[$platform] ?? '';
    }
    
    /**
     * Add affiliate links to content
     */
    public static function add_affiliate_links($content) {
        global $post;
        
        if (!$post || get_post_meta($post->ID, 'generated_by', true) !== 'streaming_guide') {
            return $content;
        }
        
        // Get ACF field values
        if (function_exists('get_field')) {
            $affiliate_link = get_field('affiliate_link', $post->ID);
            $platform = get_field('platform', $post->ID);
            
            if ($affiliate_link) {
                // Add watch now button after content
                $button_html = '<div class="streaming-guide-cta">';
                $button_html .= '<a href="' . esc_url($affiliate_link) . '" class="watch-now-button" target="_blank" rel="noopener nofollow">';
                $button_html .= 'Watch Now on ' . ucfirst($platform);
                $button_html .= '</a>';
                $button_html .= '</div>';
                
                $content .= $button_html;
            }
        }
        
        return $content;
    }
}

// Initialize ACF integration
add_action('init', array('Streaming_Guide_ACF_Integration', 'init'));