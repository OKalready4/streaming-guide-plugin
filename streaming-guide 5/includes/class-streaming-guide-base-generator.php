<?php
/**
 * Base Generator Class - Fixed version with proper content selection and full compatibility
 * 
 * Fixes:
 * - Added missing helper methods used by child generators
 * - Added block-to-HTML conversion for creating posts
 * - Standardized abstract function signature
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Streaming_Guide_Base_Generator {
    protected $tmdb;
    protected $openai;
    protected $platform_api;
    protected $error_handler;
    protected $state_manager;
    
    public function __construct($tmdb_api, $openai_api) {
        $this->tmdb = $tmdb_api;
        $this->openai = $openai_api;
        
        // Ensure dependencies are loaded only once
        if (!class_exists('Streaming_Guide_Platforms')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-platforms.php';
        }
        $this->platform_api = new Streaming_Guide_Platforms();
        
        if (!class_exists('Streaming_Guide_Error_Handler')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-error-handler.php';
        }
        $this->error_handler = new Streaming_Guide_Error_Handler();

        if (!class_exists('Streaming_Guide_State_Manager')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-state-manager.php';
        }
        $this->state_manager = new Streaming_Guide_State_Manager();
    }
    
    /**
     * Abstract method - must be implemented by child classes with this signature
     */
    abstract public function generate($platform, $param1 = null, $param2 = null, $param3 = null);
    
    /**
     * Create WordPress post
     */
    protected function create_post($title, $content_blocks, $platform, $tags = array(), $article_type = 'general') {
        try {
            // Convert content blocks to a single HTML string
            $html_content = $this->convert_blocks_to_html($content_blocks);

            // Check for duplicate content by hash
            $content_hash = md5($html_content);
            $existing_post_id = $this->state_manager->content_exists($content_hash);
            
            if ($existing_post_id) {
                $this->log_info("Duplicate content detected, returning existing post ID: {$existing_post_id}", ['hash' => $content_hash]);
                return $existing_post_id;
            }
            
            $author_id = get_option('streaming_guide_default_author', get_current_user_id());
            $post_status = get_option('streaming_guide_auto_publish', 1) ? 'publish' : 'draft';
            
            $post_data = array(
                'post_title'   => wp_strip_all_tags($title),
                'post_content' => $html_content,
                'post_status'  => $post_status,
                'post_author'  => $author_id,
                'post_type'    => 'post',
                'meta_input'   => array(
                    '_streaming_guide_generated' => 1,
                    '_streaming_guide_generator' => get_class($this),
                    '_streaming_guide_generated_date' => current_time('mysql'),
                    'streaming_platform' => $platform,
                    'article_type' => $article_type,
                    'content_hash' => $content_hash,
                )
            );
            
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                throw new Exception($post_id->get_error_message());
            }
            
            if (!empty($tags)) {
                wp_set_post_tags($post_id, $tags);
            }
            
            $this->log_info("Successfully created post: {$title} (ID: {$post_id})");
            return $post_id;
            
        } catch (Exception $e) {
            $this->log_error("Failed to create post", ['title' => $title, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Set featured image using a landscape (backdrop) image first, falling back to portrait (poster).
     */
    protected function set_featured_image_with_landscape_priority($post_id, $content_data, $title = '') {
        $image_url = '';
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
            
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $tmp = download_url($image_url);
            if (is_wp_error($tmp)) {
                throw new Exception("Failed to download image: " . $tmp->get_error_message());
            }
            
            $file_array = array(
                'name' => sanitize_file_name($title ?: 'featured-image') . '.jpg',
                'tmp_name' => $tmp
            );
            
            $attachment_id = media_handle_sideload($file_array, $post_id, $title);
            
            if (is_wp_error($attachment_id)) {
                @unlink($file_array['tmp_name']);
                throw new Exception("Failed to handle sideload: " . $attachment_id->get_error_message());
            }
            
            set_post_thumbnail($post_id, $attachment_id);
            $this->log_info("Successfully set featured image for post ID {$post_id}");
            return $attachment_id;
            
        } catch (Exception $e) {
            $this->log_error("Failed to set featured image for post ID {$post_id}", ['error' => $e->getMessage(), 'url' => $image_url]);
            // Clean up temp file if it exists
            if (isset($tmp) && !is_wp_error($tmp)) {
                @unlink($tmp);
            }
            return false;
        }
    }

    /**
     * NEW: Convert structured content blocks to an HTML string.
     */
    protected function convert_blocks_to_html($blocks) {
        if (is_string($blocks)) {
            return $blocks; // Already HTML
        }
        if (!is_array($blocks)) {
            return '';
        }

        $html = '';
        foreach ($blocks as $block) {
            if (!isset($block['type']) || !isset($block['content'])) continue;

            switch ($block['type']) {
                case 'heading':
                    $level = isset($block['level']) ? intval($block['level']) : 2;
                    $html .= "<h{$level}>" . esc_html($block['content']) . "</h{$level}>\n";
                    break;
                case 'paragraph':
                    $html .= "<p>" . $block['content'] . "</p>\n"; // Allow some HTML in paragraphs
                    break;
                case 'image':
                    $alt = isset($block['alt']) ? esc_attr($block['alt']) : '';
                    $caption = isset($block['caption']) ? esc_html($block['caption']) : '';
                    $html .= "<figure>";
                    $html .= "<img src=\"" . esc_url($block['url']) . "\" alt=\"{$alt}\" />";
                    if ($caption) {
                        $html .= "<figcaption>{$caption}</figcaption>";
                    }
                    $html .= "</figure>\n";
                    break;
                case 'list':
                    $list_type = isset($block['ordered']) && $block['ordered'] ? 'ol' : 'ul';
                    $html .= "<{$list_type}>\n";
                    foreach ($block['items'] as $item) {
                        $html .= "<li>" . $item . "</li>\n"; // Allow HTML in list items
                    }
                    $html .= "</{$list_type}>\n";
                    break;
            }
        }
        return $html;
    }

    /**
     * Get platform name from slug
     */
    protected function get_platform_name($platform_slug) {
        return $this->platform_api->get_platform_name($platform_slug);
    }
    
    /**
     * Get TMDB provider ID from platform slug
     */
    protected function get_provider_id($platform_slug) {
        return $this->platform_api->get_provider_id($platform_slug);
    }

    /**
     * Logging methods
     */
    protected function log_error($message, $context = array()) {
        $this->error_handler->log_error($message, array_merge($context, ['generator' => get_class($this)]));
    }
    
    protected function log_info($message, $context = array()) {
        $this->error_handler->log_error($message, array_merge($context, ['generator' => get_class($this)]), 'info');
    }

    protected function log_generation_failure($generator_type, $platform, $error, $params = array()) {
        $this->error_handler->log_generation_failure($generator_type, $platform, $error, $params);
    }
}