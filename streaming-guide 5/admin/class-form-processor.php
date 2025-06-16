<?php
/**
 * Form Processor - Handles all POST requests before any output
 * 
 * This class processes all forms BEFORE headers are sent,
 * preventing the "headers already sent" error.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Form_Processor {
    private $state_manager;
    private $error_handler;
    private $generators = array();
    
    public function __construct($state_manager, $error_handler) {
        $this->state_manager = $state_manager;
        $this->error_handler = $error_handler;
    }
    
    /**
     * Main processing method - called before ANY output
     */
    public function process() {
        try {
            // Check for SEO actions first (separate nonce)
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
        // Load SEO enhancer if not already loaded
        if (!class_exists('Streaming_Guide_SEO_Enhancer')) {
            $seo_file = plugin_dir_path(dirname(__FILE__)) . 'includes/class-streaming-guide-seo-enhancer.php';
            if (file_exists($seo_file)) {
                require_once $seo_file;
            } else {
                throw new Exception(__('SEO Enhancer module not found.', 'streaming-guide'));
            }
        }
        
        $openai = new Streaming_Guide_OpenAI_API();
        $seo_enhancer = new Streaming_Guide_SEO_Enhancer($openai);
        $optimized = $seo_enhancer->optimize_existing_posts(20);
        
        $this->add_admin_notice('success', sprintf(
            __('Successfully optimized %d posts for SEO.', 'streaming-guide'),
            $optimized
        ));
    }
    
    /**
     * Clear keyphrase history
     */
    private function clear_keyphrases() {
        delete_option('streaming_guide_used_keyphrases');
        $this->add_admin_notice('success', __('Keyphrase history cleared successfully.', 'streaming-guide'));
    }
    
    /**
     * Process content generation forms
     */
    private function process_generator_form() {
        $generator_type = sanitize_text_field($_POST['generator_type']);
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (empty($platform)) {
            throw new Exception(__('Please select a platform.', 'streaming-guide'));
        }
        
        // Check if we've already generated this content recently
        if ($this->state_manager->has_recent_content($generator_type, $platform)) {
            $last_generated = $this->state_manager->get_last_generated($generator_type, $platform);
            throw new Exception(sprintf(
                __('This content was already generated on %s. Please wait before generating again.', 'streaming-guide'),
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
                
                $message .= sprintf(
                    ' <a href="%s">%s</a> | <a href="%s" target="_blank">%s</a>',
                    esc_url($edit_link),
                    __('Edit', 'streaming-guide'),
                    esc_url($view_link),
                    __('View', 'streaming-guide')
                );
                
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
     * Process schedule update form
     */
    private function process_schedule_form() {
        $enable_weekly = isset($_POST['enable_weekly']) ? true : false;
        $enable_monthly = isset($_POST['enable_monthly']) ? true : false;
        $enable_trending = isset($_POST['enable_trending']) ? true : false;
        
        // Update schedules
        if ($enable_weekly) {
            $this->state_manager->activate_schedule('weekly', 'weekly');
            wp_schedule_event(strtotime('next monday 9am'), 'weekly', 'streaming_guide_weekly_cron');
        } else {
            $this->state_manager->deactivate_schedule('weekly');
            wp_clear_scheduled_hook('streaming_guide_weekly_cron');
        }
        
        if ($enable_monthly) {
            $this->state_manager->activate_schedule('monthly', 'monthly');
            wp_schedule_event(strtotime('first day of next month 9am'), 'monthly', 'streaming_guide_monthly_cron');
        } else {
            $this->state_manager->deactivate_schedule('monthly');
            wp_clear_scheduled_hook('streaming_guide_monthly_cron');
        }
        
        if ($enable_trending) {
            $this->state_manager->activate_schedule('trending', 'twiceweekly');
            wp_schedule_event(time(), 'twiceweekly', 'streaming_guide_trending_cron');
        } else {
            $this->state_manager->deactivate_schedule('trending');
            wp_clear_scheduled_hook('streaming_guide_trending_cron');
        }
        
        $this->add_admin_notice('success', __('Schedule settings updated successfully.', 'streaming-guide'));
    }
    
    /**
     * Prepare parameters for specific generator types
     */
    private function prepare_generator_params($generator_type) {
        $params = array();
        
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
        
        return $params;
    }
    
    /**
     * Get generator instance
     */
    private function get_generator($type) {
        if (!isset($this->generators[$type])) {
            $this->load_generators();
        }
        
        return $this->generators[$type] ?? null;
    }
    
    /**
     * Load generator classes
     */
    private function load_generators() {
        // First check if article-generator.php exists and use it
        $article_generator_file = plugin_dir_path(dirname(__FILE__)) . 'admin/article-generator.php';
        
        if (file_exists($article_generator_file)) {
            // Use existing article generator
            require_once $article_generator_file;
            
            // Get API instances
            $tmdb = new Streaming_Guide_TMDB_API();
            $openai = new Streaming_Guide_OpenAI_API();
            
            // Create article generator instance
            $article_generator = new Streaming_Guide_Article_Generator($tmdb, $openai);
            
            // Get generators from article generator if it has them
            if (method_exists($article_generator, 'get_generators')) {
                $this->generators = $article_generator->get_generators();
            } else {
                // Fall back to creating them manually
                $this->create_generators_manually($tmdb, $openai);
            }
        } else {
            // Fall back to manual creation
            $tmdb = new Streaming_Guide_TMDB_API();
            $openai = new Streaming_Guide_OpenAI_API();
            $this->create_generators_manually($tmdb, $openai);
        }
    }
    
    /**
     * Create generators manually if article-generator.php is not available
     */
    private function create_generators_manually($tmdb, $openai) {
        // Load generator files
        $generator_dir = plugin_dir_path(dirname(__FILE__)) . 'includes/generators/';
        
        $generator_classes = array(
            'weekly' => 'Streaming_Guide_Weekly_Generator',
            'monthly' => 'Streaming_Guide_Monthly_Generator',
            'trending' => 'Streaming_Guide_Trending_Generator',
            'spotlight' => 'Streaming_Guide_Spotlight_Generator',
            'top10' => 'Streaming_Guide_Top10_Generator',
            'seasonal' => 'Streaming_Guide_Seasonal_Generator'
        );
        
        foreach ($generator_classes as $type => $class_name) {
            $file_path = $generator_dir . "class-streaming-guide-{$type}-generator.php";
            
            if (file_exists($file_path)) {
                require_once $file_path;
                
                if (class_exists($class_name)) {
                    $this->generators[$type] = new $class_name($tmdb, $openai);
                }
            }
        }
    }
    
    /**
     * Add admin notice to be displayed later
     */
    private function add_admin_notice($type, $message) {
        $notices = get_transient('streaming_guide_admin_notices') ?: array();
        
        $notices[] = array(
            'type' => $type,
            'message' => $message
        );
        
        set_transient('streaming_guide_admin_notices', $notices, 60);
    }
}