<?php
/**
 * AJAX Handler - Handles asynchronous content generation
 * Fixed version with proper error handling and state management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_AJAX_Handler {
    private $state_manager;
    private $error_handler;
    
    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        // Register AJAX handlers
        add_action('wp_ajax_streaming_guide_generate', array($this, 'handle_generation'));
        add_action('wp_ajax_streaming_guide_check_status', array($this, 'check_generation_status'));
        add_action('wp_ajax_streaming_guide_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_streaming_guide_search_tmdb', array($this, 'handle_tmdb_search'));
        add_action('wp_ajax_streaming_guide_delete_post', array($this, 'handle_delete_post'));
        
        // Background processing
        add_action('streaming_guide_process_generation', array($this, 'process_generation_background'), 10, 1);
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        if (file_exists($plugin_dir . 'admin/class-state-manager.php')) {
            require_once $plugin_dir . 'admin/class-state-manager.php';
        }
        if (file_exists($plugin_dir . 'admin/class-error-handler.php')) {
            require_once $plugin_dir . 'admin/class-error-handler.php';
        }
        
        $this->state_manager = new Streaming_Guide_State_Manager();
        $this->error_handler = new Streaming_Guide_Error_Handler();
    }
    
    /**
     * Handle AJAX generation request
     */
    public function handle_generation() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'streaming-guide')
            ));
        }
        
        // Get parameters
        $generator_type = sanitize_text_field($_POST['type'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        // Validate inputs
        if (empty($generator_type) || empty($platform)) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters.', 'streaming-guide')
            ));
        }
        
        // Check for recent duplicates for weekly content
        if ($generator_type === 'weekly' && $platform !== 'all') {
            $existing_post = $this->check_existing_weekly_content($platform);
            if ($existing_post) {
                // Return the existing post instead of an error
                wp_send_json_success(array(
                    'message' => __('Weekly content already exists for this platform.', 'streaming-guide'),
                    'post_id' => $existing_post->ID,
                    'post_url' => get_permalink($existing_post->ID),
                    'edit_url' => get_edit_post_link($existing_post->ID, 'raw'),
                    'existing' => true
                ));
            }
        }
        
        // Handle multi-platform generation
        if ($platform === 'all') {
            $platforms = array('netflix', 'amazon-prime', 'disney-plus', 'hulu', 'max', 'paramount-plus', 'apple-tv');
            $generation_ids = array();
            
            foreach ($platforms as $single_platform) {
                // Skip if weekly content exists
                if ($generator_type === 'weekly') {
                    $existing = $this->check_existing_weekly_content($single_platform);
                    if ($existing) {
                        continue;
                    }
                }
                
                // Start generation
                $generation_id = $this->state_manager->start_generation($generator_type, $single_platform, array());
                if ($generation_id) {
                    $generation_ids[] = $generation_id;
                    
                    // Schedule background processing
                    wp_schedule_single_event(
                        time() + rand(1, 10),
                        'streaming_guide_process_generation',
                        array($generation_id)
                    );
                }
                
                // Small delay between scheduling
                usleep(500000); // 0.5 second
            }
            
            if (empty($generation_ids)) {
                wp_send_json_error(array(
                    'message' => __('All platforms already have recent content.', 'streaming-guide')
                ));
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('Starting generation for %d platforms.', 'streaming-guide'), count($generation_ids)),
                'generation_ids' => $generation_ids
            ));
        } else {
            // Single platform generation
            $generation_id = $this->state_manager->start_generation($generator_type, $platform, array());
            
            if (!$generation_id) {
                wp_send_json_error(array(
                    'message' => __('Failed to start generation.', 'streaming-guide')
                ));
            }
            
            // Schedule background processing
            wp_schedule_single_event(
                time() + 1,
                'streaming_guide_process_generation',
                array($generation_id)
            );
            
            wp_send_json_success(array(
                'message' => __('Generation started successfully.', 'streaming-guide'),
                'generation_id' => $generation_id
            ));
        }
    }
    
    /**
     * Check for existing weekly content
     */
    private function check_existing_weekly_content($platform) {
        $args = array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'streaming_platform',
                    'value' => $platform,
                    'compare' => '='
                ),
                array(
                    'key' => 'article_type',
                    'value' => 'weekly_whats_new',
                    'compare' => '='
                ),
                array(
                    'key' => 'weekly_generation_date',
                    'value' => date('Y-m-d', strtotime('-7 days')),
                    'compare' => '>',
                    'type' => 'DATE'
                )
            )
        );
        
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }
    
    /**
     * Process generation in background
     */
    public function process_generation_background($generation_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'streaming_guide_history';
        
        // Get generation details
        $generation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $generation_id
        ));
        
        if (!$generation || $generation->status !== 'pending') {
            return;
        }
        
        // Update status to processing
        $this->state_manager->update_generation_status($generation_id, 'processing');
        
        try {
            // Load generator
            $generator = $this->load_generator($generation->generator_type);
            if (!$generator) {
                throw new Exception('Invalid generator type: ' . $generation->generator_type);
            }
            
            // Generate content
            $post_id = $generator->generate($generation->platform);
            
            if ($post_id && !is_wp_error($post_id)) {
                // Update with success
                $this->state_manager->complete_generation($generation_id, $post_id);
                
                // Trigger social media sharing if enabled
                if (get_option('streaming_guide_auto_share_facebook')) {
                    do_action('streaming_guide_post_generated', $post_id);
                }
                
                $this->error_handler->log_error('Successfully generated content', array(
                    'generation_id' => $generation_id,
                    'post_id' => $post_id,
                    'type' => $generation->generator_type,
                    'platform' => $generation->platform
                ), 'success');
            } else {
                $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error';
                throw new Exception($error_message);
            }
            
        } catch (Exception $e) {
            $this->state_manager->fail_generation($generation_id, $e->getMessage());
            $this->error_handler->log_error('Generation failed', array(
                'generation_id' => $generation_id,
                'error' => $e->getMessage(),
                'type' => $generation->generator_type,
                'platform' => $generation->platform
            ), 'error');
        }
    }
    
    /**
     * Check generation status
     */
    public function check_generation_status() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        $generation_ids = isset($_POST['generation_ids']) ? array_map('intval', $_POST['generation_ids']) : array();
        
        if (empty($generation_ids)) {
            wp_send_json_error(array(
                'message' => __('No generation IDs provided.', 'streaming-guide')
            ));
        }
        
        $statuses = array();
        foreach ($generation_ids as $id) {
            $status = $this->state_manager->get_generation_status($id);
            if ($status) {
                $statuses[] = array(
                    'id' => $id,
                    'status' => $status->status,
                    'platform' => $status->platform,
                    'message' => $this->get_status_message($status->status),
                    'post_id' => $status->post_id,
                    'post_url' => $status->post_id ? get_permalink($status->post_id) : null,
                    'edit_url' => $status->post_id ? get_edit_post_link($status->post_id, 'raw') : null,
                    'error' => $status->error_message
                );
            }
        }
        
        wp_send_json_success(array(
            'statuses' => $statuses
        ));
    }
    
    /**
     * Test API connections
     */
    public function test_api_connection() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'streaming-guide')
            ));
        }
        
        $api_type = sanitize_text_field($_POST['api'] ?? '');
        
        if ($api_type === 'tmdb') {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-tmdb-api.php';
            $tmdb = new Streaming_Guide_TMDB_API();
            
            // Test with a simple request
            $result = $tmdb->search_multi('Avatar');
            
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => 'TMDB API Error: ' . $result->get_error_message()
                ));
            } else {
                wp_send_json_success(array(
                    'message' => 'TMDB API connection successful!'
                ));
            }
        } elseif ($api_type === 'openai') {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-openai-api.php';
            $openai = new Streaming_Guide_OpenAI_API();
            
            // Test with a simple completion
            $result = $openai->test_connection();
            
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => 'OpenAI API Error: ' . $result->get_error_message()
                ));
            } else {
                wp_send_json_success(array(
                    'message' => 'OpenAI API connection successful!'
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => 'Invalid API type specified.'
            ));
        }
    }
    
    /**
     * Handle post deletion
     */
    public function handle_delete_post() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        // Check capabilities
        if (!current_user_can('delete_posts')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'streaming-guide')
            ));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(array(
                'message' => __('Invalid post ID.', 'streaming-guide')
            ));
        }
        
        // Check if it's a streaming guide post
        $is_streaming = get_post_meta($post_id, '_streaming_guide_generated', true);
        
        if (!$is_streaming) {
            wp_send_json_error(array(
                'message' => __('This is not a streaming guide post.', 'streaming-guide')
            ));
        }
        
        // Delete the post
        $result = wp_delete_post($post_id, true);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Post deleted successfully.', 'streaming-guide')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete post.', 'streaming-guide')
            ));
        }
    }
    
    /**
     * Handle TMDB search
     */
    public function handle_tmdb_search() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        if (empty($query)) {
            wp_send_json_error(array(
                'message' => __('Search query is required.', 'streaming-guide')
            ));
        }
        
        try {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-tmdb-api.php';
            $tmdb = new Streaming_Guide_TMDB_API();
            
            $results = $tmdb->search_multi($query);
            
            if (is_wp_error($results)) {
                throw new Exception($results->get_error_message());
            }
            
            $formatted_results = array();
            
            if (!empty($results['results'])) {
                foreach ($results['results'] as $item) {
                    if ($item['media_type'] === 'movie' || $item['media_type'] === 'tv') {
                        $formatted_results[] = array(
                            'id' => $item['id'],
                            'title' => $item['media_type'] === 'movie' ? $item['title'] : $item['name'],
                            'media_type' => $item['media_type'],
                            'year' => isset($item['release_date']) ? 
                                substr($item['release_date'], 0, 4) : 
                                (isset($item['first_air_date']) ? substr($item['first_air_date'], 0, 4) : ''),
                            'poster' => !empty($item['poster_path']) ? 
                                'https://image.tmdb.org/t/p/w92' . $item['poster_path'] : ''
                        );
                    }
                }
            }
            
            wp_send_json_success(array(
                'results' => array_slice($formatted_results, 0, 10)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Search failed: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Load generator instance
     */
    private function load_generator($type) {
        // Get API instances
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-tmdb-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-openai-api.php';
        
        $tmdb = new Streaming_Guide_TMDB_API();
        $openai = new Streaming_Guide_OpenAI_API();
        
        // Load base generator
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-base-generator.php';
        
        // Load specific generator
        $generator_file = plugin_dir_path(dirname(__FILE__)) . "includes/generators/class-streaming-guide-{$type}-generator.php";
        
        if (!file_exists($generator_file)) {
            return null;
        }
        
        require_once $generator_file;
        
        $class_name = 'Streaming_Guide_' . ucfirst($type) . '_Generator';
        
        if (!class_exists($class_name)) {
            return null;
        }
        
        return new $class_name($tmdb, $openai);
    }
    
    /**
     * Get user-friendly status message
     */
    private function get_status_message($status) {
        $messages = array(
            'pending' => __('Waiting to start...', 'streaming-guide'),
            'processing' => __('Generating content...', 'streaming-guide'),
            'success' => __('Content generated successfully!', 'streaming-guide'),
            'failed' => __('Generation failed.', 'streaming-guide'),
            'cancelled' => __('Generation cancelled.', 'streaming-guide')
        );
        
        return $messages[$status] ?? __('Unknown status', 'streaming-guide');
    }
}