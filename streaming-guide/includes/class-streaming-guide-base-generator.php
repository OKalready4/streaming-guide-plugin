<?php
/**
 * Base Generator Class
 * Fixed version with proper method signatures and featured image handling
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Streaming_Guide_Base_Generator {
    protected $tmdb;
    protected $openai;
    protected $platforms;
    protected $error_handler;
    
    public function __construct($tmdb_api, $openai_api) {
        $this->tmdb = $tmdb_api;
        $this->openai = $openai_api;
        $this->platforms = new Streaming_Guide_Platforms();
        
        // Initialize error handler
        if (class_exists('Streaming_Guide_Error_Handler')) {
            $this->error_handler = new Streaming_Guide_Error_Handler();
        }
    }
    
    /**
     * Abstract method that must be implemented by child classes
     * Fixed signature to allow optional parameters
     */
    abstract public function generate($platform, $param1 = null, $param2 = null, $param3 = null);
    
    /**
     * Get platform display name
     */
    protected function get_platform_name($platform) {
        return $this->platforms->get_platform_name($platform);
    }
    
    /**
     * Get platform provider ID for TMDB
     */
    protected function get_provider_id($platform) {
        return $this->platforms->get_provider_id($platform);
    }
    
    /**
     * Log information
     */
    protected function log_info($message, $context = array()) {
        if (class_exists('Streaming_Guide_Error_Handler')) {
            $handler = new Streaming_Guide_Error_Handler();
            $handler->log_error($message, $context, 'info');
        } else {
            error_log('[Streaming Guide Info] ' . $message . ' - ' . json_encode($context));
        }
    }
    
    /**
     * Log error
     */
    protected function log_error($message, $context = array()) {
        if (class_exists('Streaming_Guide_Error_Handler')) {
            $handler = new Streaming_Guide_Error_Handler();
            $handler->log_error($message, $context, 'error');
        } else {
            error_log('[Streaming Guide Error] ' . $message . ' - ' . json_encode($context));
        }
    }
    
    /**
     * Create WordPress post
     */
    protected function create_post($title, $content, $platform, $tags = array(), $article_type = '') {
        try {
            // Handle both string content and block arrays
            if (is_array($content)) {
                $html_content = $this->convert_blocks_to_html($content);
            } else {
                $html_content = $content;
            }
            
            // Validate content
            if (empty($html_content)) {
                throw new Exception('Empty content generated');
            }
            
            // Create content hash to check for duplicates
            $content_hash = md5($title . $platform . $article_type);
            
            // Check for duplicate content
            $existing = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'any',
                'meta_key' => 'content_hash',
                'meta_value' => $content_hash,
                'posts_per_page' => 1
            ));
            
            if (!empty($existing)) {
                $this->log_info("Duplicate content detected", array(
                    'title' => $title,
                    'existing_id' => $existing[0]->ID
                ));
                return $existing[0]->ID;
            }
            
            // Prepare post data
            $post_status = get_option('streaming_guide_auto_publish', true) ? 'publish' : 'draft';
            
            $post_data = array(
                'post_title'   => wp_strip_all_tags($title),
                'post_content' => $html_content,
                'post_status'  => $post_status,
                'post_author'  => get_current_user_id() ?: 1,
                'post_type'    => 'post',
                'meta_input'   => array(
                    '_streaming_guide_generated' => 1,
                    '_streaming_guide_generator' => get_class($this),
                    '_streaming_guide_generated_date' => current_time('mysql'),
                    'streaming_platform' => $platform,
                    'article_type' => $article_type,
                    'content_hash' => $content_hash,
                    '_streaming_guide_type' => 'streaming' // For theme detection
                )
            );
            
            // Insert post
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                throw new Exception($post_id->get_error_message());
            }
            
            // Set tags
            if (!empty($tags)) {
                wp_set_post_tags($post_id, $tags);
            }
            
            // Set category if exists
            $category_name = 'Streaming Guides';
            $category = get_category_by_slug(sanitize_title($category_name));
            
            if (!$category) {
                $category_id = wp_create_category($category_name);
                if ($category_id) {
                    wp_set_post_categories($post_id, array($category_id));
                }
            } else {
                wp_set_post_categories($post_id, array($category->term_id));
            }
            
            $this->log_info("Successfully created post: {$title} (ID: {$post_id})");
            
            // Trigger post generated action for social media
            do_action('streaming_guide_post_generated', $post_id);
            
            return $post_id;
            
        } catch (Exception $e) {
            $this->log_error("Failed to create post", array(
                'title' => $title,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Set featured image with landscape (backdrop) priority
     */
    protected function set_featured_image_with_landscape_priority($post_id, $content_data, $title = '') {
        $image_url = '';
        
        // Prioritize backdrop (landscape) over poster (portrait)
        if (!empty($content_data['backdrop_url'])) {
            $image_url = $content_data['backdrop_url'];
        } elseif (!empty($content_data['poster_url'])) {
            $image_url = $content_data['poster_url'];
        }
        
        if (empty($image_url)) {
            $this->log_info("No image URL provided for post ID {$post_id}");
            return false;
        }
        
        return $this->set_featured_image($post_id, $image_url, $title);
    }
    
    /**
     * Set featured image from URL
     */
    protected function set_featured_image($post_id, $image_url, $title = '') {
        try {
            if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid image URL: {$image_url}");
            }
            
            // Check if image already exists
            $existing_attachment = $this->get_attachment_by_url($image_url);
            if ($existing_attachment) {
                set_post_thumbnail($post_id, $existing_attachment);
                $this->log_info("Reused existing image for post ID {$post_id}");
                return $existing_attachment;
            }
            
            // Load required files
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            // Download image
            $tmp = download_url($image_url, 10);
            
            if (is_wp_error($tmp)) {
                throw new Exception("Failed to download image: " . $tmp->get_error_message());
            }
            
            // Prepare file array
            $file_array = array(
                'name' => sanitize_file_name($title ?: 'featured-image') . '.jpg',
                'tmp_name' => $tmp
            );
            
            // Handle sideload
            $attachment_id = media_handle_sideload($file_array, $post_id, $title);
            
            if (is_wp_error($attachment_id)) {
                @unlink($file_array['tmp_name']);
                throw new Exception("Failed to handle sideload: " . $attachment_id->get_error_message());
            }
            
            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);
            
            // Store original URL for reference
            update_post_meta($attachment_id, '_source_url', $image_url);
            
            $this->log_info("Successfully set featured image for post ID {$post_id}");
            
            return $attachment_id;
            
        } catch (Exception $e) {
            $this->log_error("Failed to set featured image for post ID {$post_id}", array(
                'error' => $e->getMessage(),
                'url' => $image_url
            ));
            
            // Clean up temp file if exists
            if (isset($tmp) && !is_wp_error($tmp)) {
                @unlink($tmp);
            }
            
            return false;
        }
    }
    
    /**
     * Get attachment by source URL
     */
    protected function get_attachment_by_url($url) {
        global $wpdb;
        
        $attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_source_url' 
            AND meta_value = %s 
            LIMIT 1",
            $url
        ));
        
        return $attachment ? intval($attachment) : null;
    }
    
    /**
     * Convert content blocks to HTML
     */
    protected function convert_blocks_to_html($blocks) {
        if (is_string($blocks)) {
            return $blocks;
        }
        
        if (!is_array($blocks)) {
            return '';
        }
        
        $html = '';
        
        foreach ($blocks as $block) {
            if (!isset($block['type'])) {
                continue;
            }
            
            switch ($block['type']) {
                case 'heading':
                    $level = isset($block['level']) ? intval($block['level']) : 2;
                    $content = isset($block['content']) ? esc_html($block['content']) : '';
                    $html .= "<h{$level}>{$content}</h{$level}>\n\n";
                    break;
                    
                case 'paragraph':
                    $content = isset($block['content']) ? $block['content'] : '';
                    $html .= "<p>{$content}</p>\n\n";
                    break;
                    
                case 'image':
                    if (isset($block['url'])) {
                        $alt = isset($block['alt']) ? esc_attr($block['alt']) : '';
                        $caption = isset($block['caption']) ? esc_html($block['caption']) : '';
                        
                        $html .= '<figure class="wp-block-image">';
                        $html .= '<img src="' . esc_url($block['url']) . '" alt="' . $alt . '" />';
                        if ($caption) {
                            $html .= '<figcaption>' . $caption . '</figcaption>';
                        }
                        $html .= "</figure>\n\n";
                    }
                    break;
                    
                case 'list':
                    $items = isset($block['items']) ? $block['items'] : array();
                    $ordered = isset($block['ordered']) && $block['ordered'];
                    
                    if (!empty($items)) {
                        $tag = $ordered ? 'ol' : 'ul';
                        $html .= "<{$tag}>\n";
                        foreach ($items as $item) {
                            $html .= '<li>' . esc_html($item) . "</li>\n";
                        }
                        $html .= "</{$tag}>\n\n";
                    }
                    break;
                    
                case 'blockquote':
                    $content = isset($block['content']) ? $block['content'] : '';
                    $citation = isset($block['citation']) ? esc_html($block['citation']) : '';
                    
                    $html .= '<blockquote>';
                    $html .= '<p>' . esc_html($content) . '</p>';
                    if ($citation) {
                        $html .= '<cite>' . $citation . '</cite>';
                    }
                    $html .= "</blockquote>\n\n";
                    break;
            }
        }
        
        return $html;
    }
    
    /**
     * Generate content hash for duplicate checking
     */
    protected function generate_content_hash($title, $platform, $type) {
        return md5($title . $platform . $type . date('Y-W'));
    }
    
    /**
     * Log generation failure
     */
    protected function log_generation_failure($type, $platform, $message, $context = array()) {
        $context['generator_type'] = $type;
        $context['platform'] = $platform;
        
        if ($this->error_handler) {
            $this->error_handler->log_error($message, $context, 'error');
        } else {
            error_log(sprintf(
                '[Streaming Guide Error] %s (Type: %s, Platform: %s) - %s',
                $message,
                $type,
                $platform,
                json_encode($context)
            ));
        }
    }
    
    /**
     * Validate platform
     */
    protected function validate_platform($platform) {
        if ($platform === 'all') {
            return true;
        }
        
        $platforms = $this->platforms->get_platforms();
        return isset($platforms[$platform]);
    }
    
    /**
     * Get platform configuration
     */
    protected function get_platform_config($platform) {
        if ($platform === 'all') {
            return array(
                'name' => 'All Platforms',
                'provider_id' => null
            );
        }
        
        $platforms = $this->platforms->get_platforms();
        if (!isset($platforms[$platform])) {
            return false;
        }
        
        return array(
            'name' => $platforms[$platform]['name'],
            'provider_id' => $platforms[$platform]['id']
        );
    }
    
    /**
     * Handle API errors
     */
    protected function handle_api_error($api_name, $error, $context = array()) {
        $error_message = is_wp_error($error) ? $error->get_error_message() : $error;
        $this->log_error("{$api_name} API Error: {$error_message}", $context);
        return false;
    }
    
    /**
     * Check if content is streaming worthy
     */
    protected function is_streaming_worthy($item, $media_type) {
        // Basic validation
        if (empty($item['id']) || empty($item['title'] ?? $item['name'])) {
            return false;
        }
        
        // Check rating threshold
        $rating = $item['vote_average'] ?? 0;
        $vote_count = $item['vote_count'] ?? 0;
        
        if ($rating < 5.0 || $vote_count < 10) {
            return false;
        }
        
        // Check release date (within last 2 years for movies, 5 years for TV)
        $release_date = $media_type === 'movie' ? 
            ($item['release_date'] ?? '') : 
            ($item['first_air_date'] ?? '');
            
        if (empty($release_date)) {
            return false;
        }
        
        $max_years = $media_type === 'movie' ? 2 : 5;
        $release_timestamp = strtotime($release_date);
        $cutoff_timestamp = strtotime("-{$max_years} years");
        
        return $release_timestamp >= $cutoff_timestamp;
    }
}