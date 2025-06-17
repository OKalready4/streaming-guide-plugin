<?php
/**
 * Comprehensive SEO Enhancement System for Streaming Guide
 * 
 * This is a new file: includes/class-streaming-guide-seo-enhancer.php
 * Add this as a separate class that integrates with your existing system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_SEO_Enhancer {
    
    private $used_keyphrases = array();
    private $openai_api;
    
    public function __construct($openai_api = null) {
        $this->openai_api = $openai_api;
        $this->load_used_keyphrases();
        
        // Hook into post creation
        add_action('wp_insert_post', array($this, 'enhance_post_seo'), 10, 2);
        add_filter('streaming_guide_before_post_creation', array($this, 'prepare_seo_data'), 10, 3);
    }
    
    /**
     * Main method to enhance a post's SEO after creation
     */
    public function enhance_post_seo($post_id, $post) {
        // Only enhance posts created by our plugin
        if (get_post_meta($post_id, 'generated_by', true) !== 'streaming_guide') {
            return;
        }
        
        $this->add_internal_links($post_id);
        $this->add_outbound_links($post_id);
        $this->optimize_images_alt_text($post_id);
    }
    
    /**
     * Prepare comprehensive SEO data before post creation
     */
    public function prepare_seo_data($post_data, $platform, $content_info) {
        // Generate focus keyphrase
        $focus_keyphrase = $this->generate_focus_keyphrase($platform, $content_info);
        
        // Optimize title for SEO
        $seo_title = $this->optimize_title($post_data['title'], $focus_keyphrase, $platform);
        
        // Create SEO-optimized slug
        $seo_slug = $this->create_seo_slug($seo_title, $focus_keyphrase);
        
        // Generate keyphrase-optimized meta description
        $meta_description = $this->generate_keyphrase_meta_description($seo_title, $focus_keyphrase, $platform, $content_info);
        
        // Store for later use
        $post_data['seo_title'] = $seo_title;
        $post_data['seo_slug'] = $seo_slug;
        $post_data['focus_keyphrase'] = $focus_keyphrase;
        $post_data['meta_description'] = $meta_description;
        
        return $post_data;
    }
    
    /**
     * Generate intelligent focus keyphrase based on content
     */
    private function generate_focus_keyphrase($platform, $content_info) {
        $platform_name = ucfirst($platform);
        $content_type = '';
        
        // Determine content type
        if (isset($content_info['article_type'])) {
            switch ($content_info['article_type']) {
                case 'weekly_whats_new':
                    $content_type = 'new releases';
                    break;
                case 'trending':
                    $content_type = 'trending';
                    break;
                case 'top_10_movie':
                    $content_type = 'best movies';
                    break;
                case 'top_10_tv':
                    $content_type = 'best shows';
                    break;
                case 'spotlight':
                    $content_type = 'review';
                    break;
                case 'seasonal':
                    $content_type = 'seasonal shows';
                    break;
                default:
                    $content_type = 'streaming';
            }
        }
        
        // Create keyphrase combinations
        $keyphrase_options = array(
            "{$platform_name} {$content_type}",
            "best {$content_type} {$platform_name}",
            "{$content_type} streaming {$platform_name}",
            "what to watch {$platform_name}",
            "{$platform_name} movies shows"
        );
        
        // Check for unused keyphrases
        foreach ($keyphrase_options as $keyphrase) {
            if (!$this->is_keyphrase_used($keyphrase)) {
                $this->mark_keyphrase_as_used($keyphrase);
                return $keyphrase;
            }
        }
        
        // If all are used, create a unique variation
        $base_keyphrase = $keyphrase_options[0];
        $unique_keyphrase = $base_keyphrase . ' ' . date('Y');
        $this->mark_keyphrase_as_used($unique_keyphrase);
        
        return $unique_keyphrase;
    }
    
    /**
     * Optimize title for SEO and length
     */
    private function optimize_title($original_title, $focus_keyphrase, $platform) {
        $platform_name = ucfirst($platform);
        
        // Check if title is too long
        if (strlen($original_title) > 60) {
            // Truncate and add keyphrase
            $short_title = substr($original_title, 0, 40);
            $optimized_title = $short_title . " - " . $platform_name;
        } else {
            $optimized_title = $original_title;
        }
        
        // Ensure keyphrase is in title
        if (stripos($optimized_title, $focus_keyphrase) === false) {
            // Try to naturally include keyphrase
            if (strlen($optimized_title . " | " . $focus_keyphrase) <= 60) {
                $optimized_title .= " | " . ucwords($focus_keyphrase);
            }
        }
        
        return $optimized_title;
    }
    
    /**
     * Create SEO-friendly slug with keyphrase
     */
    private function create_seo_slug($title, $focus_keyphrase) {
        // Combine title and keyphrase for slug
        $slug_text = $title . ' ' . $focus_keyphrase;
        
        // WordPress-style slug creation
        $slug = sanitize_title($slug_text);
        
        // Limit length
        if (strlen($slug) > 50) {
            $slug = substr($slug, 0, 50);
            $slug = rtrim($slug, '-');
        }
        
        return $slug;
    }
    
    /**
     * Generate meta description with keyphrase inclusion
     */
    private function generate_keyphrase_meta_description($title, $focus_keyphrase, $platform, $content_info) {
        $platform_name = ucfirst($platform);
        
        // Base description templates that include keyphrase naturally
        $templates = array(
            "Discover the {focus_keyphrase} on {platform_name}. Complete guide with reviews, ratings, and what to watch next.",
            "Find {focus_keyphrase} worth watching. Expert recommendations and reviews for {platform_name} subscribers.",
            "Everything you need to know about {focus_keyphrase}. Latest additions, reviews, and streaming guide for {platform_name}.",
            "Best {focus_keyphrase} to stream now. Updated guide with ratings, reviews, and recommendations on {platform_name}."
        );
        
        $template = $templates[array_rand($templates)];
        $description = str_replace(
            array('{focus_keyphrase}', '{platform_name}'),
            array($focus_keyphrase, $platform_name),
            $template
        );
        
        // Ensure length is under 155 characters
        if (strlen($description) > 155) {
            $description = substr($description, 0, 152) . '...';
        }
        
        return $description;
    }
    
    /**
     * Add intelligent internal links to related content
     */
    private function add_internal_links($post_id) {
        $post = get_post($post_id);
        $platform = get_post_meta($post_id, 'streaming_platform', true);
        
        // Find related posts
        $related_posts = $this->find_related_posts($post_id, $platform);
        
        if (empty($related_posts)) {
            return;
        }
        
        $content = $post->post_content;
        $modified_content = $this->insert_internal_links($content, $related_posts, $platform);
        
        if ($modified_content !== $content) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $modified_content
            ));
        }
    }
    
    /**
     * Add relevant outbound links to authoritative sources
     */
    private function add_outbound_links($post_id) {
        $post = get_post($post_id);
        $platform = get_post_meta($post_id, 'streaming_platform', true);
        
        // Define authoritative outbound links
        $outbound_links = $this->get_authoritative_links($platform);
        
        $content = $post->post_content;
        $modified_content = $this->insert_outbound_links($content, $outbound_links, $platform);
        
        if ($modified_content !== $content) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $modified_content
            ));
        }
    }
    
    /**
     * Optimize image alt text to include keyphrases
     */
    private function optimize_images_alt_text($post_id) {
        $focus_keyphrase = get_post_meta($post_id, 'focus_keyphrase', true);
        $platform = get_post_meta($post_id, 'streaming_platform', true);
        
        if (empty($focus_keyphrase)) {
            return;
        }
        
        // Get featured image
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $current_alt = get_post_meta($featured_image_id, '_wp_attachment_image_alt', true);
            
            // Enhance alt text with keyphrase if not already present
            if (stripos($current_alt, $focus_keyphrase) === false) {
                $enhanced_alt = $current_alt . ' - ' . $focus_keyphrase;
                update_post_meta($featured_image_id, '_wp_attachment_image_alt', $enhanced_alt);
            }
        }
        
        // Get all images in content and update their alt text
        $post = get_post($post_id);
        $content = $post->post_content;
        
        // Find images in content
        preg_match_all('/<img[^>]+>/i', $content, $images);
        
        foreach ($images[0] as $img_tag) {
            if (preg_match('/wp-image-(\d+)/i', $img_tag, $matches)) {
                $image_id = $matches[1];
                $current_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                
                if (stripos($current_alt, $focus_keyphrase) === false) {
                    $enhanced_alt = !empty($current_alt) ? 
                        $current_alt . ' - ' . $focus_keyphrase : 
                        ucfirst($focus_keyphrase) . ' image';
                    update_post_meta($image_id, '_wp_attachment_image_alt', $enhanced_alt);
                }
            }
        }
    }
    
    /**
     * Find related posts for internal linking
     */
    private function find_related_posts($post_id, $platform) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'post__not_in' => array($post_id),
            'meta_query' => array(
                array(
                    'key' => 'streaming_platform',
                    'value' => $platform,
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        return get_posts($args);
    }
    
    /**
     * Insert internal links naturally into content
     */
    private function insert_internal_links($content, $related_posts, $platform) {
        $platform_name = ucfirst($platform);
        
        // Look for opportunities to add internal links
        $link_opportunities = array(
            "more {$platform_name} content" => '',
            "other {$platform_name} shows" => '',
            "trending on {$platform_name}" => '',
            "best {$platform_name} movies" => '',
            "new {$platform_name} releases" => ''
        );
        
        foreach ($related_posts as $index => $related_post) {
            $link_text = array_keys($link_opportunities)[$index % count($link_opportunities)];
            $link_url = get_permalink($related_post->ID);
            $link_html = '<a href="' . $link_url . '">' . $link_text . '</a>';
            
            // Try to replace the text with linked version
            $pattern = '/\b' . preg_quote($link_text, '/') . '\b/i';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $link_html, $content, 1);
                break; // Only add one internal link per post
            }
        }
        
        // If no natural opportunities, add a related content section
        if (stripos($content, '<a href=') === false && !empty($related_posts)) {
            $related_section = "\n\n<!-- wp:heading {\"level\":3} -->\n";
            $related_section .= "<h3>Related {$platform_name} Content</h3>\n";
            $related_section .= "<!-- /wp:heading -->\n\n";
            $related_section .= "<!-- wp:paragraph -->\n";
            $related_section .= "<p>Check out our other <a href=\"" . get_permalink($related_posts[0]->ID) . "\">latest {$platform_name} recommendations</a> for more streaming suggestions.</p>\n";
            $related_section .= "<!-- /wp:paragraph -->\n\n";
            
            $content .= $related_section;
        }
        
        return $content;
    }
    
    /**
     * Get authoritative outbound links
     */
    private function get_authoritative_links($platform) {
        $links = array(
            'netflix' => array(
                'url' => 'https://www.netflix.com',
                'text' => 'Netflix official site',
                'context' => 'streaming service'
            ),
            'hulu' => array(
                'url' => 'https://www.hulu.com',
                'text' => 'Hulu official site',
                'context' => 'streaming platform'
            ),
            'disney' => array(
                'url' => 'https://www.disneyplus.com',
                'text' => 'Disney+ official site',
                'context' => 'Disney+ streaming'
            ),
            'hbo' => array(
                'url' => 'https://www.max.com',
                'text' => 'Max (HBO) official site',
                'context' => 'HBO Max streaming'
            ),
            'amazon' => array(
                'url' => 'https://www.amazon.com/prime-video',
                'text' => 'Amazon Prime Video',
                'context' => 'Prime Video streaming'
            ),
            'paramount' => array(
                'url' => 'https://www.paramountplus.com',
                'text' => 'Paramount+ official site',
                'context' => 'Paramount+ streaming'
            ),
            'apple' => array(
                'url' => 'https://www.apple.com/apple-tv-plus',
                'text' => 'Apple TV+ official site',
                'context' => 'Apple TV+ streaming'
            )
        );
        
        return isset($links[$platform]) ? $links[$platform] : null;
    }
    
    /**
     * Insert outbound links naturally into content
     */
    private function insert_outbound_links($content, $outbound_link, $platform) {
        if (!$outbound_link) {
            return $content;
        }
        
        $platform_name = ucfirst($platform);
        
        // Look for natural places to add outbound links
        $patterns = array(
            "/\b{$platform_name}\b(?! official| site)/i",
            "/streaming on {$platform_name}/i",
            "/available on {$platform_name}/i"
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $replacement = '<a href="' . $outbound_link['url'] . '" target="_blank" rel="noopener">' . $platform_name . '</a>';
                $content = preg_replace($pattern, $replacement, $content, 1);
                break; // Only add one outbound link per post
            }
        }
        
        return $content;
    }
    
    /**
     * Check if keyphrase has been used recently
     */
    private function is_keyphrase_used($keyphrase) {
        // Check against recently used keyphrases
        return in_array(strtolower($keyphrase), $this->used_keyphrases);
    }
    
    /**
     * Mark keyphrase as used
     */
    private function mark_keyphrase_as_used($keyphrase) {
        $this->used_keyphrases[] = strtolower($keyphrase);
        $this->save_used_keyphrases();
    }
    
    /**
     * Load previously used keyphrases
     */
    private function load_used_keyphrases() {
        $this->used_keyphrases = get_option('streaming_guide_used_keyphrases', array());
        
        // Clean old keyphrases (older than 90 days)
        $this->clean_old_keyphrases();
    }
    
    /**
     * Save used keyphrases
     */
    private function save_used_keyphrases() {
        // Keep only last 100 keyphrases to prevent database bloat
        if (count($this->used_keyphrases) > 100) {
            $this->used_keyphrases = array_slice($this->used_keyphrases, -100);
        }
        
        update_option('streaming_guide_used_keyphrases', $this->used_keyphrases);
    }
    
    /**
     * Clean old keyphrases
     */
    private function clean_old_keyphrases() {
        // This is a simplified version - in a full implementation, 
        // you'd store timestamps with keyphrases
        if (count($this->used_keyphrases) > 200) {
            $this->used_keyphrases = array_slice($this->used_keyphrases, -100);
            $this->save_used_keyphrases();
        }
    }
    
    /**
     * Manual method to optimize existing posts
     */
    public function optimize_existing_posts($limit = 10) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => 'generated_by',
                    'value' => 'streaming_guide',
                    'compare' => '='
                ),
                array(
                    'key' => 'seo_optimized',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $posts = get_posts($args);
        $optimized_count = 0;
        
        foreach ($posts as $post) {
            $platform = get_post_meta($post->ID, 'streaming_platform', true);
            $article_type = get_post_meta($post->ID, 'article_type', true);
            
            if (!empty($platform)) {
                // Prepare SEO data
                $content_info = array('article_type' => $article_type);
                $post_data = array('title' => $post->post_title);
                
                $seo_data = $this->prepare_seo_data($post_data, $platform, $content_info);
                
                // Update post with SEO improvements
                $this->apply_seo_improvements($post->ID, $seo_data);
                
                // Mark as optimized
                update_post_meta($post->ID, 'seo_optimized', current_time('mysql'));
                $optimized_count++;
            }
        }
        
        return $optimized_count;
    }
    
    /**
     * Apply SEO improvements to existing post
     */
    private function apply_seo_improvements($post_id, $seo_data) {
        // Update meta descriptions
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_data['meta_description']);
        update_post_meta($post_id, '_aioseo_description', $seo_data['meta_description']);
        update_post_meta($post_id, 'focus_keyphrase', $seo_data['focus_keyphrase']);
        
        // Add SEO enhancements
        $this->enhance_post_seo($post_id, get_post($post_id));
    }
}