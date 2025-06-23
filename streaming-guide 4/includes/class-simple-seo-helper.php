<?php
/**
 * Simple SEO Helper - Add this to your includes folder
 * File: includes/class-simple-seo-helper.php
 * 
 * This provides basic SEO improvements without complexity
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Simple_SEO_Helper {
    
    /**
     * Generate focus keyphrase based on platform and content type
     */
    public static function generate_focus_keyphrase($platform, $generator_type) {
        $platform_name = self::get_platform_name($platform);
        
        $keyphrases = array(
            'weekly' => array(
                "new on {$platform_name}",
                "{$platform_name} new releases", 
                "what's new {$platform_name}",
                "{$platform_name} this week"
            ),
            'trending' => array(
                "{$platform_name} trending",
                "trending on {$platform_name}",
                "{$platform_name} popular shows",
                "what to watch {$platform_name}"
            ),
            'spotlight' => array(
                "{$platform_name} review",
                "{$platform_name} movie review",
                "{$platform_name} show review",
                "streaming review {$platform_name}"
            )
        );
        
        $options = $keyphrases[$generator_type] ?? $keyphrases['weekly'];
        return $options[0]; // Use the first one for consistency
    }
    
    /**
     * Generate SEO-optimized title with keyphrase
     */
    public static function optimize_title($original_title, $focus_keyphrase) {
        // If title already contains keyphrase, return as-is
        if (stripos($original_title, $focus_keyphrase) !== false) {
            return $original_title;
        }
        
        // If title is too long, shorten and add keyphrase
        if (strlen($original_title) > 45) {
            $short_title = substr($original_title, 0, 40) . '...';
            return ucwords($focus_keyphrase) . ': ' . $short_title;
        }
        
        // Add keyphrase to beginning of title
        return ucwords($focus_keyphrase) . ' - ' . $original_title;
    }
    
    /**
     * Generate meta description with keyphrase
     */
    public static function generate_meta_description($platform, $generator_type, $focus_keyphrase, $content_count = 0) {
        $platform_name = self::get_platform_name($platform);
        
        $templates = array(
            'weekly' => "Discover {$focus_keyphrase} this week. Complete guide to new movies and shows streaming on {$platform_name} with reviews and trailers.",
            'trending' => "Find {$focus_keyphrase} now. Top movies and TV shows everyone's watching on {$platform_name} with ratings and where to watch.",
            'spotlight' => "Complete {$focus_keyphrase} with ratings, cast info, and streaming details. In-depth analysis of the latest content on {$platform_name}."
        );
        
        $description = $templates[$generator_type] ?? $templates['weekly'];
        
        // Ensure under 155 characters
        if (strlen($description) > 155) {
            $description = substr($description, 0, 152) . '...';
        }
        
        return $description;
    }
    
    /**
     * Add basic outbound link to platform
     */
    public static function add_platform_outbound_link($content, $platform) {
        $platform_links = array(
            'netflix' => 'https://www.netflix.com',
            'hulu' => 'https://www.hulu.com', 
            'disney' => 'https://www.disneyplus.com',
            'hbo' => 'https://www.max.com',
            'amazon' => 'https://www.amazon.com/prime-video',
            'paramount' => 'https://www.paramountplus.com',
            'apple' => 'https://www.apple.com/apple-tv-plus'
        );
        
        if (!isset($platform_links[$platform])) {
            return $content;
        }
        
        $platform_name = self::get_platform_name($platform);
        $link = $platform_links[$platform];
        
        // Add a simple outbound link at the end
        $outbound_text = "\n\n<p><strong>Ready to start watching?</strong> <a href=\"{$link}\" target=\"_blank\" rel=\"noopener\">Visit {$platform_name} official site</a> to stream these titles and discover more great content.</p>";
        
        return $content . $outbound_text;
    }
    
    /**
     * Generate improved alt text for images
     */
    public static function generate_image_alt_text($original_title, $focus_keyphrase, $platform) {
        $platform_name = self::get_platform_name($platform);
        
        if (empty($original_title)) {
            return ucwords($focus_keyphrase) . " on {$platform_name}";
        }
        
        // If alt text already contains keyphrase, return enhanced version
        if (stripos($original_title, $focus_keyphrase) !== false) {
            return $original_title . " - {$platform_name} streaming";
        }
        
        // Add keyphrase to alt text
        return $original_title . " - " . $focus_keyphrase;
    }
    
    /**
     * Add basic SEO metadata to post
     */
    public static function add_seo_metadata($post_id, $focus_keyphrase, $meta_description, $platform, $generator_type) {
        if (!$post_id) {
            return false;
        }
        
        // Add Yoast SEO meta if plugin is active
        if (defined('WPSEO_VERSION')) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyphrase);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
        }
        
        // Add RankMath meta if plugin is active
        if (defined('RANK_MATH_VERSION')) {
            update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyphrase);
            update_post_meta($post_id, 'rank_math_description', $meta_description);
        }
        
        // Add generic meta for other SEO plugins
        update_post_meta($post_id, '_seo_focus_keyphrase', $focus_keyphrase);
        update_post_meta($post_id, '_seo_meta_description', $meta_description);
        update_post_meta($post_id, '_seo_platform', $platform);
        update_post_meta($post_id, '_seo_generator_type', $generator_type);
        
        return true;
    }
    
    /**
     * Get platform display name
     */
    private static function get_platform_name($platform) {
        $platforms = array(
            'netflix' => 'Netflix',
            'hulu' => 'Hulu', 
            'disney' => 'Disney+',
            'hbo' => 'Max',
            'amazon' => 'Prime Video',
            'paramount' => 'Paramount+',
            'apple' => 'Apple TV+'
        );
        
        return $platforms[$platform] ?? ucfirst($platform);
    }
    
    /**
     * Create SEO-friendly slug
     */
    public static function create_seo_slug($title, $focus_keyphrase) {
        // Combine title and keyphrase for slug
        $slug_text = $focus_keyphrase . ' ' . $title;
        
        // WordPress-style slug creation
        $slug = sanitize_title($slug_text);
        
        // Limit length to 50 characters
        if (strlen($slug) > 50) {
            $slug = substr($slug, 0, 50);
            $slug = rtrim($slug, '-');
        }
        
        return $slug;
    }
}