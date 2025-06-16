<?php
/**
 * Form Processor - CRITICAL FIX VERSION
 * 
 * This class processes all forms BEFORE headers are sent,
 * preventing the "headers already sent" error.
 * 
 * FIXED: Proper constructor to prevent fatal error on line 22
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Form_Processor {
    private $state_manager;
    private $error_handler;
    private $generators = array();
    
    /**
     * CRITICAL FIX: Constructor with proper parameter handling
     * This prevents the fatal error on line 22
     */
    public function __construct($state_manager = null, $error_handler = null) {
        // Handle case where parameters might not be passed
        if ($state_manager === null) {
            $this->load_state_manager();
        } else {
            $this->state_manager = $state_manager;
        }
        
        if ($error_handler === null) {
            $this->load_error_handler();
        } else {
            $this->error_handler = $error_handler;
        }
        
        // Ensure we have working instances
        if (!$this->state_manager) {
            throw new Exception('State Manager could not be initialized');
        }
        
        if (!$this->error_handler) {
            throw new Exception('Error Handler could not be initialized');
        }
    }
    
    /**
     * Load state manager if not provided
     */
    private function load_state_manager() {
        if (!class_exists('Streaming_Guide_State_Manager')) {
            $state_manager_file = plugin_dir_path(__FILE__) . 'class-state-manager.php';
            if (file_exists($state_manager_file)) {
                require_once $state_manager_file;
            }
        }
        
        if (class_exists('Streaming_Guide_State_Manager')) {
            $this->state_manager = new Streaming_Guide_State_Manager();
        }
    }
    
    /**
     * Load error handler if not provided
     */
    private function load_error_handler() {
        if (!class_exists('Streaming_Guide_Error_Handler')) {
            $error_handler_file = plugin_dir_path(__FILE__) . 'class-error-handler.php';
            if (file_exists($error_handler_file)) {
                require_once $error_handler_file;
            }
        }
        
        if (class_exists('Streaming_Guide_Error_Handler')) {
            $this->error_handler = new Streaming_Guide_Error_Handler();
        }
    }
    
    /**
     * Main processing method - called before ANY output
     */
    public function process() {
        try {
            // Handle post deletion first
            if (isset($_POST['action']) && $_POST['action'] === 'delete_post') {
                $this->process_post_deletion();
                return;
            }
            
            // Check for SEO actions (separate nonce)
            if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'streaming_guide_seo_actions')) {
                $this->process_seo_actions();
                return;
            }
            
            // Verify nonce for regular actions
            if (!isset($_POST['streaming_guide_nonce']) || 
                !wp_verify_nonce($_POST['streaming_guide_nonce'], 'streaming_guide_generate') &&
                !wp_verify_nonce($_POST['streaming_guide_nonce'], 'streaming_guide_schedule')) {
                throw new Exception(__('Security check failed.', 'streaming-guide'));
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                throw new Exception(__('You do not have permission to perform this action.', 'streaming-guide'));
            }
            
            // Route to appropriate processor
            if (isset($_POST['generator_type'])) {
                $this->process_generator_form();
            } elseif (isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
                $this->process_schedule_form();
            }
            
        } catch (Exception $e) {
            $this->error_handler->log_error($e->getMessage());
            $this->add_admin_notice('error', $e->getMessage());
        }
    }
    
    /**
     * Process post deletion
     */
    private function process_post_deletion() {
        // Verify nonce
        if (!isset($_POST['delete_nonce']) || !wp_verify_nonce($_POST['delete_nonce'], 'streaming_guide_delete_post')) {
            throw new Exception(__('Security check failed for post deletion.', 'streaming-guide'));
        }
        
        // Check permissions
        if (!current_user_can('delete_posts')) {
            throw new Exception(__('You do not have permission to delete posts.', 'streaming-guide'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $generation_id = intval($_POST['generation_id'] ?? 0);
        
        if (!$post_id) {
            throw new Exception(__('Invalid post ID.', 'streaming-guide'));
        }
        
        // Delete the post
        $result = wp_delete_post($post_id, true); // Force delete
        
        if ($result) {
            // Update the generation record
            if ($generation_id && $this->state_manager) {
                $this->state_manager->mark_post_deleted($generation_id);
            }
            
            $this->add_admin_notice('success', __('Post deleted successfully.', 'streaming-guide'));
        } else {
            throw new Exception(__('Failed to delete post.', 'streaming-guide'));
        }
    }
    
    /**
     * Process SEO actions
     */
    private function process_seo_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        try {
            if (isset($_POST['optimize_existing'])) {
                $this->optimize_existing_posts();
            } elseif (isset($_POST['clear_keyphrases'])) {
                $this->clear_keyphrases();
            }
        } catch (Exception $e) {
            $this->error_handler->log_error('SEO action failed: ' . $e->getMessage());
            $this->add_admin_notice('error', $e->getMessage());
        }
    }
    
    /**
     * Optimize existing posts for SEO
     */
    private function optimize_existing_posts() {
        // Load SEO enhancer if available
        if (!class_exists('Streaming_Guide_SEO_Enhancer')) {
            $seo_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-seo-enhancer.php';
            if (file_exists($seo_file)) {
                require_once $seo_file;
            }
        }
        
        if (class_exists('Streaming_Guide_SEO_Enhancer')) {
            $seo_enhancer = new Streaming_Guide_SEO_Enhancer();
            
            // Get streaming guide posts
            $posts = get_posts(array(
                'post_type' => 'post',
                'meta_query' => array(
                    array(
                        'key' => '_streaming_guide_type',
                        'compare' => 'EXISTS'
                    )
                ),
                'posts_per_page' => 50,
                'post_status' => 'publish'
            ));
            
            $optimized_count = 0;
            foreach ($posts as $post) {
                if ($seo_enhancer->optimize_post($post->ID)) {
                    $optimized_count++;
                }
            }
            
            $this->add_admin_notice('success', sprintf(
                __('Optimized %d posts for SEO.', 'streaming-guide'),
                $optimized_count
            ));
        } else {
            $this->add_admin_notice('error', __('SEO Enhancer not available.', 'streaming-guide'));
        }
    }
    
    /**
     * Clear stored keyphrases
     */
    private function clear_keyphrases() {
        global $wpdb;
        
        $deleted = $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => '_yoast_wpseo_focuskw'),
            array('%s')
        );
        
        $this->add_admin_notice('success', sprintf(
            __('Cleared %d focus keyphrases.', 'streaming-guide'),
            $deleted
        ));
    }
    
    /**
     * Process generator form submission
     */
    private function process_generator_form() {
        $generator_type = sanitize_text_field($_POST['generator_type'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (empty($generator_type) || empty($platform)) {
            throw new Exception(__('Missing required parameters.', 'streaming-guide'));
        }
        
        // Check for recent duplicates
        $cooldown_hours = get_option('streaming_guide_duplicate_prevention', 12);
        
        if ($this->state_manager->has_recent_content($generator_type, $platform, $cooldown_hours)) {
            $last_generated = $this->state_manager->get_last_generated($generator_type, $platform);
            throw new Exception(sprintf(
                __('This content was recently generated on %s. Please wait before generating again.', 'streaming-guide'),
                date_i18n(get_option('date_format'), strtotime($last_generated))
            ));
        }
        
        // Initialize the appropriate generator
        $generator = $this->get_generator($generator_type);
        
        if (!$generator) {
            throw new Exception(__('Invalid generator type.', 'streaming-guide'));
        }
        
        // Prepare parameters based on generator type
        $params = $this->prepare_generator_params($generator_type);
        
        // Track generation start
        $generation_id = $this->state_manager->start_generation($generator_type, $platform, $params);
        
        try {
            // Generate content
            $result = $generator->generate($platform, $params);
            
            if ($result && is_numeric($result)) {
                // Success - record in state manager
                $this->state_manager->complete_generation($generation_id, $result, 'success');
                
                // Add success notice
                $post_title = get_the_title($result);
                $edit_link = get_edit_post_link($result);
                $view_link = get_permalink($result);
                
                $message = sprintf(
                    __('Successfully generated: %s', 'streaming-guide'),
                    $post_title
                );
                
                if ($edit_link && $view_link) {
                    $message .= sprintf(
                        ' <a href="%s">%s</a> | <a href="%s" target="_blank">%s</a>',
                        esc_url($edit_link),
                        __('Edit', 'streaming-guide'),
                        esc_url($view_link),
                        __('View', 'streaming-guide')
                    );
                }
                
                $this->add_admin_notice('success', $message);
                
            } else {
                throw new Exception(__('Content generation failed. Please check the logs.', 'streaming-guide'));
            }
            
        } catch (Exception $e) {
            // Record failure
            $this->state_manager->complete_generation($generation_id, null, 'failed', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get generator instance
     */
    private function get_generator($generator_type) {
        if (isset($this->generators[$generator_type])) {
            return $this->generators[$generator_type];
        }
        
        // Load APIs
        if (!class_exists('Streaming_Guide_TMDB_API')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-tmdb-api.php';
        }
        
        if (!class_exists('Streaming_Guide_OpenAI_API')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-openai-api.php';
        }
        
        $tmdb_api = new Streaming_Guide_TMDB_API();
        $openai_api = new Streaming_Guide_OpenAI_API();
        
        // Load and instantiate the appropriate generator
        $generator_class = 'Streaming_Guide_' . ucfirst($generator_type) . '_Generator';
        $generator_file = plugin_dir_path(dirname(__FILE__)) . "includes/generators/class-streaming-guide-{$generator_type}-generator.php";
        
        if (file_exists($generator_file)) {
            require_once $generator_file;
            
            if (class_exists($generator_class)) {
                $this->generators[$generator_type] = new $generator_class($tmdb_api, $openai_api);
                return $this->generators[$generator_type];
            }
        }
        
        return null;
    }
    
    /**
     * Prepare generator parameters
     */
    private function prepare_generator_params($generator_type) {
        $params = array();
        
        switch ($generator_type) {
            case 'monthly':
                $params['month'] = sanitize_text_field($_POST['month'] ?? date('Y-m'));
                break;
                
            case 'spotlight':
                $params['tmdb_id'] = intval($_POST['tmdb_id'] ?? 0);
                $params['media_type'] = sanitize_text_field($_POST['media_type'] ?? 'movie');
                break;
                
            case 'trending':
                $params['content_type'] = sanitize_text_field($_POST['content_type'] ?? 'mixed');
                break;
                
            default:
                // No special params needed
                break;
        }
        
        return $params;
    }
    
    /**
     * Process schedule update form
     */
    private function process_schedule_form() {
        $enable_weekly = isset($_POST['enable_weekly']) ? true : false;
        $enable_monthly = isset($_POST['enable_monthly']) ? true : false;
        $enable_trending = isset($_POST['enable_trending']) ? true : false;
        
        // Update schedule options
        update_option('streaming_guide_schedule_weekly', $enable_weekly);
        update_option('streaming_guide_schedule_monthly', $enable_monthly);
        update_option('streaming_guide_schedule_trending', $enable_trending);
        
        $this->add_admin_notice('success', __('Schedule settings updated.', 'streaming-guide'));
    }
    
    /**
     * Add admin notice
     */
    private function add_admin_notice($type, $message) {
        add_action('admin_notices', function() use ($type, $message) {
            $class = ($type === 'error') ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . $message . '</p></div>';
        });
    }
}