<?php
/**
 * AJAX Handler - Handles asynchronous content generation
 * 
 * Prevents timeout issues by running generation tasks via AJAX
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
        add_action('wp_ajax_streaming_guide_cancel_generation', array($this, 'cancel_generation'));
        add_action('wp_ajax_streaming_guide_search_tmdb', array($this, 'handle_tmdb_search'));
        
        // Cron job handlers
        add_action('streaming_guide_process_generation', array($this, 'process_generation_background'), 10, 1);
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'class-state-manager.php';
        require_once plugin_dir_path(__FILE__) . 'class-error-handler.php';
        
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
        $params = array();
        
        // Validate inputs
        if (empty($generator_type) || empty($platform)) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters.', 'streaming-guide')
            ));
        }
        
        // Check for recent duplicates
        if ($this->state_manager->has_recent_content($generator_type, $platform, 12)) {
            $last_generated = $this->state_manager->get_last_generated($generator_type, $platform);
            wp_send_json_error(array(
                'message' => sprintf(
                    __('This content was recently generated on %s. Please wait at least 12 hours before generating again.', 'streaming-guide'),
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_generated))
                )
            ));
        }
        
        // Collect additional parameters based on type
        switch ($generator_type) {
            case 'monthly':
                $params['month'] = sanitize_text_field($_POST['month'] ?? date('Y-m'));
                break;
                
            case 'trending':
                $params['content_type'] = sanitize_text_field($_POST['content_type'] ?? 'mixed');
                break;
                
            case 'spotlight':
                $params['tmdb_id'] = absint($_POST['tmdb_id'] ?? 0);
                $params['media_type'] = sanitize_text_field($_POST['media_type'] ?? 'movie');
                break;
        }
        
        // Start generation tracking
        $generation_id = $this->state_manager->start_generation($generator_type, $platform, $params);
        
        // Schedule background processing
        wp_schedule_single_event(time() + 1, 'streaming_guide_process_generation', array($generation_id));
        
        // Return generation ID for status checking
        wp_send_json_success(array(
            'generation_id' => $generation_id,
            'message' => __('Content generation started. This may take a few moments...', 'streaming-guide')
        ));
    }
    
    /**
     * Process generation in background
     */
    public function process_generation_background($generation_id) {
        // Set longer time limit for background processing
        @set_time_limit(300);
        
        try {
            // Get generation details from database
            global $wpdb;
            $table = $wpdb->prefix . 'streaming_guide_history';
            
            $generation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE generation_id = %s",
                $generation_id
            ));
            
            if (!$generation) {
                throw new Exception('Generation record not found');
            }
            
            // Parse parameters
            $params = json_decode($generation->params, true) ?: array();
            
            // Load appropriate generator
            $generator = $this->load_generator($generation->generator_type);
            
            if (!$generator) {
                throw new Exception('Invalid generator type: ' . $generation->generator_type);
            }
            
            // Perform generation
            $result = $generator->generate($generation->platform, $params);
            
            if ($result && is_numeric($result)) {
                // Success
                $this->state_manager->complete_generation($generation_id, $result, 'success');
                
                // Schedule social media sharing (if enabled)
                $this->maybe_schedule_social_share($result);
                
            } else {
                throw new Exception('Generation returned invalid result');
            }
            
        } catch (Exception $e) {
            $this->error_handler->log_generation_failure(
                $generation->generator_type ?? 'unknown',
                $generation->platform ?? 'unknown',
                $e->getMessage(),
                $params ?? array()
            );
            
            $this->state_manager->complete_generation($generation_id, null, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Check generation status via AJAX
     */
    public function check_generation_status() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        $generation_id = sanitize_text_field($_POST['generation_id'] ?? '');
        
        if (empty($generation_id)) {
            wp_send_json_error(array(
                'message' => __('Missing generation ID.', 'streaming-guide')
            ));
        }
        
        // Get status from database
        global $wpdb;
        $table = $wpdb->prefix . 'streaming_guide_history';
        
        $generation = $wpdb->get_row($wpdb->prepare(
            "SELECT g.*, p.post_title, p.post_status 
            FROM {$table} g
            LEFT JOIN {$wpdb->posts} p ON g.post_id = p.ID
            WHERE g.generation_id = %s",
            $generation_id
        ));
        
        if (!$generation) {
            wp_send_json_error(array(
                'message' => __('Generation not found.', 'streaming-guide')
            ));
        }
        
        $response = array(
            'status' => $generation->status,
            'message' => $this->get_status_message($generation->status)
        );
        
        if ($generation->status === 'success' && $generation->post_id) {
            $response['post_id'] = $generation->post_id;
            $response['post_title'] = $generation->post_title;
            $response['edit_url'] = get_edit_post_link($generation->post_id);
            $response['view_url'] = get_permalink($generation->post_id);
        } elseif ($generation->status === 'failed') {
            $response['error'] = $generation->error_message ?: __('Generation failed. Check error logs.', 'streaming-guide');
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Cancel ongoing generation
     */
    public function cancel_generation() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        $generation_id = sanitize_text_field($_POST['generation_id'] ?? '');
        
        if (empty($generation_id)) {
            wp_send_json_error(array(
                'message' => __('Missing generation ID.', 'streaming-guide')
            ));
        }
        
        // Update status to cancelled
        $this->state_manager->complete_generation($generation_id, null, 'cancelled', 'Cancelled by user');
        
        // Unschedule background task
        wp_unschedule_event(wp_next_scheduled('streaming_guide_process_generation', array($generation_id)), 'streaming_guide_process_generation');
        
        wp_send_json_success(array(
            'message' => __('Generation cancelled.', 'streaming-guide')
        ));
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
    
    /**
     * Maybe schedule social media sharing
     */
    private function maybe_schedule_social_share($post_id) {
        $auto_share = get_option('streaming_guide_auto_share', false);
        
        if ($auto_share) {
            // Schedule share for 5 minutes later
            wp_schedule_single_event(
                time() + 300,
                'streaming_guide_share_to_social',
                array($post_id)
            );
        }
    }
    
    /**
     * Handle TMDB search requests
     */
    public function handle_tmdb_search() {
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
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        if (empty($query)) {
            wp_send_json_error(array(
                'message' => __('Search query is required.', 'streaming-guide')
            ));
        }
        
        try {
            // Load TMDB API
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-tmdb-api.php';
            $tmdb = new Streaming_Guide_TMDB_API();
            
            // Perform search
            $results = $tmdb->search($query, 'multi');
            
            if (is_wp_error($results)) {
                throw new Exception($results->get_error_message());
            }
            
            // Filter results to only movies and TV shows
            $filtered_results = array();
            if (isset($results['results'])) {
                foreach ($results['results'] as $result) {
                    if (in_array($result['media_type'], array('movie', 'tv'))) {
                        $filtered_results[] = $result;
                    }
                }
            }
            
            wp_send_json_success(array(
                'results' => array_slice($filtered_results, 0, 10) // Limit to 10 results
            ));
            
        } catch (Exception $e) {
            $this->error_handler->log_error('TMDB search failed: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Search failed. Please try again.', 'streaming-guide')
            ));
        }
    }
}

// Initialize AJAX handler
new Streaming_Guide_AJAX_Handler();