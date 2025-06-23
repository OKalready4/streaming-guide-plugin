<?php
/**
 * Base Generator Class for Streaming Guide Pro
 * FINAL VERSION: Correctly registers the Custom Post Type on the 'init' hook
 * so that it is always available and visible in the WordPress Admin Menu.
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Streaming_Guide_Base_Generator {
    protected $tmdb_api;
    protected $openai_api;

    public function __construct($tmdb_api = null, $openai_api = null) {
        // API Initialization
        if ($tmdb_api === null) {
            $this->tmdb_api = new Streaming_Guide_TMDB_API();
        } else {
            $this->tmdb_api = $tmdb_api;
        }
        
        if ($openai_api === null) {
            $this->openai_api = new Streaming_Guide_OpenAI_API();
        } else {
            $this->openai_api = $openai_api;
        }
        
        if (!$this->tmdb_api || !$this->openai_api) {
            throw new Exception('Required API classes not available');
        }

        // --- CRITICAL FIX: HOOK THE CPT REGISTRATION TO WORDPRESS INIT ---
        add_action('init', array($this, 'register_streaming_article_cpt'));
    }

    /**
     * This function now correctly handles the CPT registration on every page load.
     */
    public function register_streaming_article_cpt() {
        $cpt_slug = 'sg_article';
    
        if (post_type_exists($cpt_slug)) {
            return;
        }

        $labels = [
            'name'                  => _x('Streaming Articles', 'Post Type General Name', 'streaming-guide'),
            'singular_name'         => _x('Streaming Article', 'Post Type Singular Name', 'streaming-guide'),
            'menu_name'             => _x('Streaming Articles', 'Admin Menu text', 'streaming-guide'),
            'all_items'             => __('All Articles', 'streaming-guide'),
            'add_new_item'          => __('Add New Article', 'streaming-guide'),
            'edit_item'             => __('Edit Article', 'streaming-guide'),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 20, // Position it below "Pages"
            'menu_icon'             => 'dashicons-format-video',
            'has_archive'           => true,
            'rewrite'               => ['slug' => 'streaming-articles'],
            'supports'              => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'author'],
            'taxonomies'            => ['category', 'post_tag'],
            'show_in_rest'          => true,
        ];
        register_post_type($cpt_slug, $args);
    }

    abstract public function generate_article($platform, $options = array());
    
    protected function create_wordpress_post($article_data, $options = array()) {
        $defaults = ['auto_publish' => false, 'seo_optimize' => true];
        $options = array_merge($defaults, $options);
        
        $post_data = [
            'post_title'   => wp_strip_all_tags($article_data['title']),
            'post_content' => $article_data['content'],
            'post_excerpt' => $article_data['excerpt'] ?? '',
            'post_status'  => $options['auto_publish'] ? 'publish' : 'draft',
            'post_type'    => 'sg_article', // Use our CPT
            'post_author'  => get_current_user_id() ?: 1,
        ];
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            error_log('Failed to create WordPress post: ' . $post_id->get_error_message());
            throw new Exception('Failed to create WordPress post: ' . $post_id->get_error_message());
        }
        
        $this->assign_categories_to_post($post_id, $article_data);
        do_action('streaming_guide_post_created', $post_id, $article_data, $this->get_generator_type());
        return $post_id;
    }

    protected function assign_categories_to_post($post_id, $article_data) {
        $categories = [];
        
        if ($main_category = $this->ensure_category_exists('Streaming Guide')) {
            $categories[] = $main_category;
        }
        
        switch ($this->get_generator_type()) {
            case 'weekly': $categories[] = $this->ensure_category_exists('Weekly Releases'); break;
            case 'trending': $categories[] = $this->ensure_category_exists('Trending Now'); break;
            case 'spotlight': $categories[] = $this->ensure_category_exists('Spotlight Reviews'); break;
        }
        
        if (!empty($article_data['platform'])) {
            $categories[] = $this->ensure_category_exists($this->get_platform_display_name($article_data['platform']));
        }
        
        if (!empty($article_data['media_type'])) {
            if ($article_data['media_type'] === 'movie') $categories[] = $this->ensure_category_exists('Movies');
            elseif ($article_data['media_type'] === 'tv') $categories[] = $this->ensure_category_exists('TV Shows');
        }
        
        if (!empty($article_data['genres']) && is_array($article_data['genres'])) {
            $genre_count = 0;
            foreach ($article_data['genres'] as $genre) {
                if ($genre_count >= 3) break;
                if ($genre_cat = $this->ensure_category_exists(is_array($genre) ? $genre['name'] : $genre)) {
                    $categories[] = $genre_cat;
                    $genre_count++;
                }
            }
        }
        
        if (!empty($categories)) {
            wp_set_post_categories($post_id, array_unique($categories));
        }
    }

    protected function ensure_category_exists($category_name) {
        if (empty($category_name)) return null;
        $category = get_category_by_slug(sanitize_title($category_name));
        if ($category) {
            return $category->term_id;
        } else {
            $category_id = wp_insert_category(['cat_name' => $category_name]);
            if (!is_wp_error($category_id)) return $category_id['term_id'];
        }
        return null;
    }
    
    protected function get_generator_type() {
        $class_name = get_class($this);
        if (strpos($class_name, 'Weekly') !== false) return 'weekly';
        if (strpos($class_name, 'Trending') !== false) return 'trending';
        if (strpos($class_name, 'Spotlight') !== false) return 'spotlight';
        return 'unknown';
    }
    
    protected function download_and_attach_image($image_url, $post_id, $title) {
        $response = wp_remote_get($image_url, ['timeout' => 30]);
        if (is_wp_error($response)) return $response;
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) return new WP_Error('empty_image', 'Downloaded image is empty');
        $filename = sanitize_file_name($title . '-' . time() . '.jpg');
        $upload = wp_upload_bits($filename, null, $image_data);
        if ($upload['error']) return new WP_Error('upload_error', $upload['error']);
        $attachment = ['post_mime_type' => 'image/jpeg', 'post_title' => $title, 'post_status' => 'inherit'];
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attachment_id)) return $attachment_id;
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        return $attachment_id;
    }
    
    protected function get_platform_display_name($platform) {
        $platforms = ['netflix' => 'Netflix', 'hulu' => 'Hulu', 'disney' => 'Disney+', 'hbo' => 'HBO Max', 'amazon' => 'Amazon Prime Video', 'apple' => 'Apple TV+', 'paramount' => 'Paramount+'];
        return $platforms[$platform] ?? ucfirst($platform);
    }
    
    protected function find_best_trailer($videos) {
        if (empty($videos) || !is_array($videos)) return null;
        $type_priority = ['Trailer', 'Teaser', 'Clip', 'Featurette'];
        foreach ($type_priority as $preferred_type) {
            foreach ($videos as $video) {
                if (($video['type'] ?? '') === $preferred_type && ($video['site'] ?? '') === 'YouTube' && !empty($video['key'])) {
                    return $video;
                }
            }
        }
        foreach ($videos as $video) { // Fallback
            if (($video['site'] ?? '') === 'YouTube' && !empty($video['key'])) return $video;
        }
        return null;
    }
}