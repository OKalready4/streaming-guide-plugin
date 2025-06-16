<?php
/**
 * AJAX Handler - Fixed version with better error handling
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
        add_action('wp_ajax_test_social_connection', array($this, 'test_social_connection'));
        add_action('wp_ajax_streaming_guide_search_tmdb', array($this, 'handle_tmdb_search'));
        add_action('wp_ajax_streaming_guide_cancel_generation', array($this, 'cancel_generation'));
        
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
        
        if (class_exists('Streaming_Guide_State_Manager')) {
            $this->state_manager = new Streaming_Guide_State_Manager();
        }
        if (class_exists('Streaming_Guide_Error_Handler')) {
            $this->error_handler = new Streaming_Guide_Error_Handler();
        }
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
        
        // Handle "All Platforms" specially
        if ($platform === 'all_platforms') {
            $this->handle_all_platforms_generation($generator_type, $params);
            return;
        }
        
        // Check for recent duplicates (only for time-based generators)
        if (in_array($generator_type, ['weekly', 'monthly']) && $this->state_manager) {
            if ($this->state_manager->has_recent_content($generator_type, $platform, 12)) {
                // For duplicates, check if there's an existing post we can return
                $existing_post = $this->get_existing_post($generator_type, $platform);
                if ($existing_post) {
                    wp_send_json_success(array(
                        'message' => sprintf(__('Content already exists for %s %s. Showing existing article.', 'streaming-guide'), 
                                           ucfirst($generator_type), 
                                           $this->format_platform_name($platform)),
                        'post_id' => $existing_post->ID,
                        'view_url' => get_permalink($existing_post->ID),
                        'edit_url' => get_edit_post_link($existing_post->ID)
                    ));
                } else {
                    $last_generated = $this->state_manager->get_last_generated($generator_type, $platform);
                    wp_send_json_error(array(
                        'message' => sprintf(
                            __('This content was recently generated on %s. Please wait at least 12 hours before generating again.', 'streaming-guide'),
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_generated))
                        )
                    ));
                }
            }
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
                if (empty($params['tmdb_id'])) {
                    wp_send_json_error(array(
                        'message' => __('TMDB ID is required for spotlight content.', 'streaming-guide')
                    ));
                }
                break;
        }
        
        // Try immediate generation first (for simpler content types)
        if (in_array($generator_type, ['weekly', 'trending']) && !$this->is_high_load_time()) {
            $result = $this->try_immediate_generation($generator_type, $platform, $params);
            if ($result) {
                wp_send_json_success(array(
                    'message' => sprintf(__('%s content generated successfully!', 'streaming-guide'), ucfirst($generator_type)),
                    'post_id' => $result,
                    'view_url' => get_permalink($result),
                    'edit_url' => get_edit_post_link($result)
                ));
            }
        }
        
        // Fall back to background processing
        if ($this->state_manager) {
            $generation_id = $this->state_manager->start_generation($generator_type, $platform, $params);
        } else {
            $generation_id = uniqid('gen_');
        }
        
        // Schedule background processing
        wp_schedule_single_event(time() + 1, 'streaming_guide_process_generation', array($generation_id));
        
        // Return generation ID for status checking
        wp_send_json_success(array(
            'generation_id' => $generation_id,
            'message' => __('Content generation started. This may take a few moments...', 'streaming-guide')
        ));
    }
    
    /**
     * Handle "All Platforms" generation
     */
    private function handle_all_platforms_generation($generator_type, $params) {
        $platforms = array('netflix', 'hulu', 'disney_plus', 'amazon_prime', 'apple_tv', 'hbo_max');
        $generation_ids = array();
        $immediate_results = array();
        
        foreach ($platforms as $platform) {
            // Try immediate generation for smaller platforms/content
            if (in_array($platform, ['apple_tv', 'peacock']) && !$this->is_high_load_time()) {
                $result = $this->try_immediate_generation($generator_type, $platform, $params);
                if ($result) {
                    $immediate_results[] = array(
                        'platform' => $platform,
                        'post_id' => $result,
                        'view_url' => get_permalink($result),
                        'edit_url' => get_edit_post_link($result)
                    );
                    continue;
                }
            }
            
            // Schedule background generation for this platform
            if ($this->state_manager) {
                $generation_id = $this->state_manager->start_generation($generator_type, $platform, $params);
                $generation_ids[] = $generation_id;
                wp_schedule_single_event(time() + (count($generation_ids) * 2), 'streaming_guide_process_generation', array($generation_id));
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Starting generation for %d platforms. This may take a few minutes.', 'streaming-guide'), count($platforms)),
            'generation_ids' => $generation_ids,
            'immediate_results' => $immediate_results,
            'platforms' => $platforms
        ));
    }
    
    /**
     * Try immediate generation (for simple content)
     */
    private function try_immediate_generation($generator_type, $platform, $params = array()) {
        try {
            // Set a shorter time limit for immediate generation
            @set_time_limit(45);
            
            $generator = $this->load_generator($generator_type);
            if (!$generator) {
                return false;
            }
            
            $result = $generator->generate($platform, $params);
            
            if ($result && is_numeric($result)) {
                return $result;
            }
            
        } catch (Exception $e) {
            // Log error but don't stop the process
            error_log('Streaming Guide immediate generation failed: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Check if it's a high load time (avoid immediate generation)
     */
    private function is_high_load_time() {
        $hour = date('H');
        // Avoid immediate generation during peak hours (9 AM - 6 PM)
        return ($hour >= 9 && $hour <= 18);
    }
    
    /**
     * Get existing post for generator type and platform
     */
    private function get_existing_post($generator_type, $platform) {
        global $wpdb;
        
        // Look for posts from the last 7 days
        $recent_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        $post = $wpdb->get_row($wpdb->prepare("
            SELECT p.* FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_status = 'publish'
            AND p.post_date >= %s
            AND pm1.meta_key = '_streaming_guide_type'
            AND pm1.meta_value = %s
            AND pm2.meta_key = '_streaming_guide_platform'
            AND pm2.meta_value = %s
            ORDER BY p.post_date DESC
            LIMIT 1
        ", $recent_date, $generator_type, $platform));
        
        return $post;
    }
    
    /**
     * Format platform name for display
     */
    private function format_platform_name($platform) {
        $names = array(
            'netflix' => 'Netflix',
            'hulu' => 'Hulu',
            'disney_plus' => 'Disney+',
            'amazon_prime' => 'Amazon Prime Video',
            'apple_tv' => 'Apple TV+',
            'hbo_max' => 'HBO Max',
            'paramount_plus' => 'Paramount+',
            'peacock' => 'Peacock'
        );
        
        return $names[$platform] ?? ucfirst(str_replace('_', ' ', $platform));
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
            
            // Update status to processing
            $wpdb->update(
                $table,
                array('status' => 'processing', 'started_at' => current_time('mysql')),
                array('generation_id' => $generation_id)
            );
            
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
                if ($this->state_manager) {
                    $this->state_manager->complete_generation($generation_id, $result, 'success');
                } else {
                    // Fallback database update
                    $wpdb->update(
                        $table,
                        array(
                            'status' => 'success',
                            'post_id' => $result,
                            'completed_at' => current_time('mysql')
                        ),
                        array('generation_id' => $generation_id)
                    );
                }
                
                // Schedule social media sharing (if enabled)
                $this->maybe_schedule_social_share($result);
                
            } else {
                throw new Exception('Generation returned invalid result');
            }
            
        } catch (Exception $e) {
            if ($this->error_handler) {
                $this->error_handler->log_generation_failure(
                    $generation->generator_type ?? 'unknown',
                    $generation->platform ?? 'unknown',
                    $e->getMessage(),
                    $params ?? array()
                );
            }
            
            if ($this->state_manager) {
                $this->state_manager->complete_generation($generation_id, null, 'failed', $e->getMessage());
            } else {
                // Fallback database update
                global $wpdb;
                $table = $wpdb->prefix . 'streaming_guide_history';
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => current_time('mysql')
                    ),
                    array('generation_id' => $generation_id)
                );
            }
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
            $response['error_message'] = $generation->error_message ?: __('Generation failed. Check error logs.', 'streaming-guide');
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Get human-readable status message
     */
    private function get_status_message($status) {
        $messages = array(
            'pending' => __('Waiting to start...', 'streaming-guide'),
            'processing' => __('Generating content...', 'streaming-guide'),
            'success' => __('Generation completed successfully!', 'streaming-guide'),
            'failed' => __('Generation failed.', 'streaming-guide'),
            'cancelled' => __('Generation was cancelled.', 'streaming-guide')
        );
        
        return $messages[$status] ?? __('Unknown status', 'streaming-guide');
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
        if ($this->state_manager) {
            $this->state_manager->complete_generation($generation_id, null, 'cancelled', 'Cancelled by user');
        } else {
            global $wpdb;
            $table = $wpdb->prefix . 'streaming_guide_history';
            $wpdb->update(
                $table,
                array(
                    'status' => 'cancelled',
                    'error_message' => 'Cancelled by user',
                    'completed_at' => current_time('mysql')
                ),
                array('generation_id' => $generation_id)
            );
        }
        
        // Unschedule background task
        $timestamp = wp_next_scheduled('streaming_guide_process_generation', array($generation_id));
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'streaming_guide_process_generation', array($generation_id));
        }
        
        wp_send_json_success(array(
            'message' => __('Generation cancelled.', 'streaming-guide')
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
        
        $api_type = sanitize_text_field($_POST['api_type'] ?? '');
        
        if ($api_type === 'tmdb') {
            $this->test_tmdb_connection();
        } elseif ($api_type === 'openai') {
            $this->test_openai_connection();
        } else {
            wp_send_json_error(array(
                'message' => __('Invalid API type.', 'streaming-guide')
            ));
        }
    }
    
    /**
     * Test TMDB API connection
     */
    private function test_tmdb_connection() {
        try {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-tmdb-api.php';
            
            if (!class_exists('Streaming_Guide_TMDB_API')) {
                throw new Exception('TMDB API class not found');
            }
            
            $tmdb = new Streaming_Guide_TMDB_API();
            $result = $tmdb->test_connection();
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => __('TMDB API connection successful!', 'streaming-guide')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('TMDB API connection failed. Check your API key.', 'streaming-guide')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('TMDB API test failed: %s', 'streaming-guide'), $e->getMessage())
            ));
        }
    }
    
    /**
     * Test OpenAI API connection
     */
    private function test_openai_connection() {
        try {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-openai-api.php';
            
            if (!class_exists('Streaming_Guide_OpenAI_API')) {
                throw new Exception('OpenAI API class not found');
            }
            
            $openai = new Streaming_Guide_OpenAI_API();
            $result = $openai->test_connection();
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => __('OpenAI API connection successful!', 'streaming-guide')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('OpenAI API connection failed. Check your API key.', 'streaming-guide')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('OpenAI API test failed: %s', 'streaming-guide'), $e->getMessage())
            ));
        }
    }
    
    /**
     * Test social media connections
     */
    public function test_social_connection() {
        // Verify nonce
        if (!check_ajax_referer('streaming_guide_ajax', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'streaming-guide')
            ));
        }
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (empty($platform)) {
            wp_send_json_error(array(
                'message' => __('Missing platform parameter.', 'streaming-guide')
            ));
        }
        
        try {
            $social_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-social-media.php';
            if (!file_exists($social_file)) {
                $social_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-social-media.php';
            }
            
            if (file_exists($social_file)) {
                require_once $social_file;
                
                if (class_exists('Streaming_Guide_Social_Media')) {
                    $social = new Streaming_Guide_Social_Media();
                    $result = $social->test_connection($platform);
                    
                    if ($result) {
                        wp_send_json_success(array(
                            'message' => sprintf(__('%s connection successful!', 'streaming-guide'), ucfirst($platform))
                        ));
                    } else {
                        wp_send_json_error(array(
                            'message' => sprintf(__('%s connection failed. Check your credentials.', 'streaming-guide'), ucfirst($platform))
                        ));
                    }
                } else {
                    wp_send_json_error(array(
                        'message' => __('Social media class not available.', 'streaming-guide')
                    ));
                }
            } else {
                wp_send_json_error(array(
                    'message' => __('Social media functionality not installed.', 'streaming-guide')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Social media test failed: %s', 'streaming-guide'), $e->getMessage())
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
        $type = sanitize_text_field($_POST['type'] ?? 'multi');
        
        if (empty($query)) {
            wp_send_json_error(array(
                'message' => __('Search query is required.', 'streaming-guide')
            ));
        }
        
        try {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-tmdb-api.php';
            
            if (!class_exists('Streaming_Guide_TMDB_API')) {
                throw new Exception('TMDB API class not found');
            }
            
            $tmdb = new Streaming_Guide_TMDB_API();
            $results = $tmdb->search($query, $type);
            
            if ($results && !empty($results['results'])) {
                wp_send_json_success(array(
                    'results' => $results['results'],
                    'total_results' => $results['total_results'] ?? count($results['results'])
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('No results found for your search.', 'streaming-guide')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Search failed: %s', 'streaming-guide'), $e->getMessage())
            ));
        }
    }
    
    /**
     * Load generator instance
     */
    private function load_generator($type) {
        // Get API instances first
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        require_once $plugin_dir . 'includes/class-streaming-guide-tmdb-api.php';
        require_once $plugin_dir . 'includes/class-streaming-guide-openai-api.php';
        require_once $plugin_dir . 'includes/class-streaming-guide-platforms.php';
        require_once $plugin_dir . 'includes/class-streaming-guide-base-generator.php';
        
        // Load specific generator
        $generator_file = $plugin_dir . "includes/generators/class-streaming-guide-{$type}-generator.php";
        
        if (!file_exists($generator_file)) {
            return null;
        }
        
        require_once $generator_file;
        
        $class_name = 'Streaming_Guide_' . ucfirst($type) . '_Generator';
        
        if (!class_exists($class_name)) {
            return null;
        }
        
        return new $class_name();
    }
    
    /**
     * Maybe schedule social media sharing
     */
    private function maybe_schedule_social_share($post_id) {
        $auto_share = get_option('streaming_guide_auto_social_share', false);
        
        if ($auto_share && $post_id) {
            // Schedule social sharing 5 minutes after generation
            wp_schedule_single_event(
                time() + 300,
                'streaming_guide_social_share',
                array($post_id)
            );
        }
    }
}