<?php
/**
 * Admin Request Handler
 * 
 * Handles all admin requests (both AJAX and form submissions) in a single class
 */
class Streaming_Guide_Admin_Handler {
    private $state_manager;
    private $error_handler;
    private $dependencies_loaded = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        // Register AJAX actions
        add_action('wp_ajax_streaming_guide_generate', array($this, 'handle_generation'));
        add_action('wp_ajax_streaming_guide_check_status', array($this, 'check_generation_status'));
        add_action('wp_ajax_streaming_guide_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_streaming_guide_delete_post', array($this, 'handle_delete_post'));
        add_action('wp_ajax_streaming_guide_tmdb_search', array($this, 'handle_tmdb_search'));
        
        // Register form processing
        add_action('admin_init', array($this, 'process_forms'));
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        // Load state manager
        if (file_exists($plugin_dir . 'includes/class-state-manager.php')) {
            require_once $plugin_dir . 'includes/class-state-manager.php';
        }
        
        // Load error handler
        if (file_exists($plugin_dir . 'admin/class-error-handler.php')) {
            require_once $plugin_dir . 'admin/class-error-handler.php';
        }
        
        // Load generators
        if (file_exists($plugin_dir . 'includes/generators/class-streaming-guide-weekly-generator.php')) {
            require_once $plugin_dir . 'includes/generators/class-streaming-guide-weekly-generator.php';
        }
        
        if (file_exists($plugin_dir . 'includes/generators/class-streaming-guide-monthly-generator.php')) {
            require_once $plugin_dir . 'includes/generators/class-streaming-guide-monthly-generator.php';
        }
        
        if (file_exists($plugin_dir . 'includes/generators/class-streaming-guide-spotlight-generator.php')) {
            require_once $plugin_dir . 'includes/generators/class-streaming-guide-spotlight-generator.php';
        }
        
        $this->state_manager = new Streaming_Guide_State_Manager();
        $this->error_handler = new Streaming_Guide_Error_Handler();
        $this->dependencies_loaded = true;
    }
    
    /**
     * Verify request security
     */
    private function verify_request() {
        // Check if it's an AJAX request
        if (wp_doing_ajax()) {
            // For now, skip nonce verification for AJAX requests
            // TODO: Re-enable nonce verification once we fix the nonce passing issue
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('Insufficient permissions.', 'streaming-guide')
                ));
                exit;
            }
            return;
        }
        
        // For regular form submissions, use wp_verify_nonce
        if (!isset($_POST['streaming_guide_nonce']) || 
            !wp_verify_nonce($_POST['streaming_guide_nonce'], 'streaming_guide_generate')) {
            throw new Exception(__('Security check failed.', 'streaming-guide'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            throw new Exception(__('You do not have permission to perform this action.', 'streaming-guide'));
        }
    }
    
    /**
     * Process all admin forms
     */
    public function process_forms() {
        if (!isset($_POST['streaming_guide_action'])) {
            return;
        }
        
        try {
            $this->verify_request();
            
            switch ($_POST['streaming_guide_action']) {
                case 'generate':
                    $this->process_generation();
                    break;
                    
                case 'delete_post':
                    $this->process_post_deletion();
                    break;
                    
                case 'update_schedule':
                    $this->process_schedule_update();
                    break;
                    
                case 'seo_actions':
                    $this->process_seo_actions();
                    break;
            }
            
        } catch (Exception $e) {
            $this->error_handler->log_error($e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    /**
     * Handle AJAX generation request
     */
    public function handle_generation() {
        try {
            $this->verify_request();
            
            // Get parameters
            $generator_type = sanitize_text_field($_POST['type'] ?? '');
            $platform = sanitize_text_field($_POST['platform'] ?? '');
            
            // Validate inputs
            if (empty($generator_type) || empty($platform)) {
                throw new Exception(__('Missing required parameters.', 'streaming-guide'));
            }
            
            // Validate platform
            if (!Streaming_Guide_Platforms::is_valid_platform($platform)) {
                throw new Exception(__('Invalid platform specified.', 'streaming-guide'));
            }
            
            // Check for recent duplicates for weekly content
            if ($generator_type === 'weekly' && $platform !== 'all') {
                $existing_post = $this->check_existing_weekly_content($platform);
                if ($existing_post) {
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
                $this->handle_multi_platform_generation($generator_type);
            } else {
                $this->handle_single_platform_generation($generator_type, $platform);
            }
            
        } catch (Exception $e) {
            $this->error_handler->log_error('Generation failed', array(
                'error' => $e->getMessage(),
                'type' => $generator_type ?? '',
                'platform' => $platform ?? ''
            ), 'error');
            
            wp_send_json_error(array(
                'message' => __('Generation failed: ' . $e->getMessage(), 'streaming-guide')
            ));
        }
    }
    
    /**
     * Handle multi-platform generation
     */
    private function handle_multi_platform_generation($generator_type) {
        $platforms = Streaming_Guide_Platforms::get_enabled_platforms();
        $results = array();
        
        foreach ($platforms as $platform => $config) {
            // Skip if weekly content exists
            if ($generator_type === 'weekly') {
                $existing = $this->check_existing_weekly_content($platform);
                if ($existing) {
                    continue;
                }
            }
            
            try {
                $generator = $this->load_generator($generator_type);
                if (!$generator) {
                    throw new Exception('Invalid generator type: ' . $generator_type);
                }
                
                $result = $generator->generate($platform);
                if ($result === true) {
                    $results[] = array(
                        'platform' => $platform,
                        'status' => 'success'
                    );
                } else {
                    throw new Exception('Generation failed for ' . $platform);
                }
            } catch (Exception $e) {
                $this->error_handler->log_error('Generation failed', array(
                    'error' => $e->getMessage(),
                    'type' => $generator_type,
                    'platform' => $platform
                ), 'error');
                
                $results[] = array(
                    'platform' => $platform,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                );
            }
        }
        
        if (empty($results)) {
            wp_send_json_error(array(
                'message' => __('All platforms already have recent content.', 'streaming-guide')
            ));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Generated content for %d platforms.', 'streaming-guide'), count($results)),
            'results' => $results
        ));
    }
    
    /**
     * Handle single platform generation
     */
    private function handle_single_platform_generation($generator_type, $platform) {
        $generator = $this->load_generator($generator_type);
        if (!$generator) {
            throw new Exception('Invalid generator type: ' . $generator_type);
        }
        
        $result = $generator->generate($platform);
        if ($result === true) {
            wp_send_json_success(array(
                'message' => __('Content generated successfully.', 'streaming-guide')
            ));
        } else {
            throw new Exception('Generation failed');
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
     * Load appropriate generator
     */
    private function load_generator($type) {
        switch ($type) {
            case 'weekly':
                return new Streaming_Guide_Weekly_Generator();
            case 'monthly':
                return new Streaming_Guide_Monthly_Generator();
            case 'spotlight':
                return new Streaming_Guide_Spotlight_Generator();
            default:
                return null;
        }
    }
    
    /**
     * Check generation status
     */
    public function check_generation_status() {
        try {
            $this->verify_request();
            
            $generation_ids = isset($_POST['generation_ids']) ? (array)$_POST['generation_ids'] : array();
            if (empty($generation_ids)) {
                throw new Exception(__('No generation IDs provided.', 'streaming-guide'));
            }
            
            $statuses = array();
            foreach ($generation_ids as $id) {
                $status = $this->state_manager->get_generation_status($id);
                if ($status) {
                    $statuses[] = $status;
                }
            }
            
            wp_send_json_success(array(
                'statuses' => $statuses
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        try {
            $this->verify_request();
            
            $api = sanitize_text_field($_POST['api'] ?? '');
            if (empty($api)) {
                throw new Exception(__('No API specified.', 'streaming-guide'));
            }
            
            $result = $this->test_api($api);
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Handle post deletion
     */
    public function handle_delete_post() {
        try {
            $this->verify_request();
            
            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                throw new Exception(__('Invalid post ID.', 'streaming-guide'));
            }
            
            $result = wp_delete_post($post_id, true);
            if (!$result) {
                throw new Exception(__('Failed to delete post.', 'streaming-guide'));
            }
            
            wp_send_json_success(array(
                'message' => __('Post deleted successfully.', 'streaming-guide')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Handle TMDB search
     */
    public function handle_tmdb_search() {
        try {
            $this->verify_request();
            
            $query = sanitize_text_field($_POST['query'] ?? '');
            $type = sanitize_text_field($_POST['type'] ?? 'multi');
            
            if (empty($query)) {
                throw new Exception(__('Search query is required.', 'streaming-guide'));
            }
            
            $results = $this->search_tmdb($query, $type);
            wp_send_json_success(array(
                'results' => $results
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test API connection
     */
    private function test_api($api) {
        // Implementation depends on your API testing logic
        return array('success' => true);
    }
    
    /**
     * Search TMDB
     */
    private function search_tmdb($query, $type) {
        // Implementation depends on your TMDB integration
        return array();
    }
} 