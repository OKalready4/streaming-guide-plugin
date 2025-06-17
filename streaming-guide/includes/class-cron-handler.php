<?php
/**
 * Cron Handler - Manages scheduled content generation
 * Fixed version with proper memory management and error handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Cron_Handler {
    private $state_manager;
    private $error_handler;
    
    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        // Register cron hooks
        add_action('streaming_guide_weekly_generation', array($this, 'run_weekly_generation'));
        add_action('streaming_guide_monthly_generation', array($this, 'run_monthly_generation'));
        add_action('streaming_guide_cleanup_history', array($this, 'cleanup_old_data'));
        
        // Add custom schedules
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        
        // Schedule events on activation
        register_activation_hook(STREAMING_GUIDE_PLUGIN_DIR . 'streaming-guide.php', array($this, 'schedule_events'));
        register_deactivation_hook(STREAMING_GUIDE_PLUGIN_DIR . 'streaming-guide.php', array($this, 'unschedule_events'));
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        if (!class_exists('Streaming_Guide_State_Manager')) {
            require_once $plugin_dir . 'admin/class-state-manager.php';
        }
        if (!class_exists('Streaming_Guide_Error_Handler')) {
            require_once $plugin_dir . 'admin/class-error-handler.php';
        }
        
        $this->state_manager = new Streaming_Guide_State_Manager();
        $this->error_handler = new Streaming_Guide_Error_Handler();
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_schedules($schedules) {
        $schedules['weekly'] = array(
            'interval' => 604800, // 7 days
            'display' => __('Once Weekly', 'streaming-guide')
        );
        
        $schedules['monthly'] = array(
            'interval' => 2592000, // 30 days
            'display' => __('Once Monthly', 'streaming-guide')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule cron events
     */
    public function schedule_events() {
        // Weekly generation - every Monday at 9 AM
        if (!wp_next_scheduled('streaming_guide_weekly_generation')) {
            $next_monday = strtotime('next Monday 9:00');
            wp_schedule_event($next_monday, 'weekly', 'streaming_guide_weekly_generation');
        }
        
        // Monthly generation - first day of month at 10 AM
        if (!wp_next_scheduled('streaming_guide_monthly_generation')) {
            $first_of_month = strtotime('first day of next month 10:00');
            wp_schedule_event($first_of_month, 'monthly', 'streaming_guide_monthly_generation');
        }
        
        // Daily cleanup
        if (!wp_next_scheduled('streaming_guide_cleanup_history')) {
            wp_schedule_event(time() + 86400, 'daily', 'streaming_guide_cleanup_history');
        }
    }
    
    /**
     * Unschedule cron events
     */
    public function unschedule_events() {
        wp_clear_scheduled_hook('streaming_guide_weekly_generation');
        wp_clear_scheduled_hook('streaming_guide_monthly_generation');
        wp_clear_scheduled_hook('streaming_guide_cleanup_history');
    }
    
    /**
     * Run weekly content generation
     */
    public function run_weekly_generation() {
        // Check if automatic generation is enabled
        if (!get_option('streaming_guide_auto_generate_weekly', true)) {
            return;
        }
        
        // Set time limit for long-running process
        @set_time_limit(300);
        
        // Increase memory limit if possible
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        
        $this->error_handler->log_error('Starting scheduled weekly generation', array(), 'info');
        
        // Get enabled platforms
        $platforms = $this->get_enabled_platforms();
        $generated = 0;
        $failed = 0;
        
        foreach ($platforms as $platform) {
            // Check if already generated this week
            if ($this->state_manager->has_recent_content('weekly', $platform, 168)) { // 7 days
                continue;
            }
            
            try {
                // Load generator
                $generator = $this->load_generator('weekly');
                if (!$generator) {
                    throw new Exception('Failed to load weekly generator');
                }
                
                // Generate content immediately
                $post_id = $generator->generate($platform);
                
                if ($post_id && !is_wp_error($post_id)) {
                    $generated++;
                    $this->error_handler->log_error(
                        "Successfully generated weekly content for {$platform}",
                        array('post_id' => $post_id),
                        'info'
                    );
                } else {
                    throw new Exception(is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error');
                }
                
                // Free up memory
                $this->free_memory();
                
            } catch (Exception $e) {
                $failed++;
                $this->error_handler->log_error(
                    "Failed to generate weekly content for {$platform}",
                    array('error' => $e->getMessage()),
                    'error'
                );
            }
            
            // Prevent overwhelming the system
            if ($generated > 0 && $generated % 3 === 0) {
                sleep(5);
            }
        }
        
        $this->error_handler->log_error(
            "Completed weekly generation",
            array('generated' => $generated, 'failed' => $failed),
            'info'
        );
    }
    
    /**
     * Run monthly content generation
     */
    public function run_monthly_generation() {
        // Check if automatic generation is enabled
        if (!get_option('streaming_guide_auto_generate_monthly', true)) {
            return;
        }
        
        // Set time limit
        @set_time_limit(300);
        
        // Increase memory limit
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        
        $this->error_handler->log_error('Starting scheduled monthly generation', array(), 'info');
        
        // Get enabled platforms
        $platforms = $this->get_enabled_platforms();
        $generated = 0;
        $failed = 0;
        
        foreach ($platforms as $platform) {
            // Check if already generated this month
            if ($this->state_manager->has_recent_content('monthly', $platform, 720)) { // 30 days
                continue;
            }
            
            try {
                // Load generator
                $generator = $this->load_generator('monthly');
                if (!$generator) {
                    throw new Exception('Failed to load monthly generator');
                }
                
                // Generate content immediately
                $post_id = $generator->generate($platform);
                
                if ($post_id && !is_wp_error($post_id)) {
                    $generated++;
                    $this->error_handler->log_error(
                        "Successfully generated monthly content for {$platform}",
                        array('post_id' => $post_id),
                        'info'
                    );
                } else {
                    throw new Exception(is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error');
                }
                
                // Free up memory
                $this->free_memory();
                
            } catch (Exception $e) {
                $failed++;
                $this->error_handler->log_error(
                    "Failed to generate monthly content for {$platform}",
                    array('error' => $e->getMessage()),
                    'error'
                );
            }
            
            // Prevent overwhelming the system
            if ($generated > 0 && $generated % 3 === 0) {
                sleep(5);
            }
        }
        
        $this->error_handler->log_error(
            "Completed monthly generation",
            array('generated' => $generated, 'failed' => $failed),
            'info'
        );
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        try {
            // Clean up history older than 90 days
            $this->state_manager->cleanup_old_history(90);
            
            // Clean up orphaned post meta
            $this->cleanup_orphaned_meta();
            
            // Rotate error log if needed
            $this->error_handler->rotate_log_if_needed();
            
            $this->error_handler->log_error('Completed scheduled cleanup', array(), 'info');
            
        } catch (Exception $e) {
            $this->error_handler->log_error(
                'Failed to run cleanup: ' . $e->getMessage(),
                array(),
                'error'
            );
        }
    }
    
    /**
     * Get enabled platforms
     */
    private function get_enabled_platforms() {
        $all_platforms = array(
            'netflix',
            'amazon-prime',
            'disney-plus',
            'hulu',
            'max',
            'paramount-plus',
            'apple-tv'
        );
        
        $enabled = array();
        
        foreach ($all_platforms as $platform) {
            if (get_option("streaming_guide_enable_{$platform}", true)) {
                $enabled[] = $platform;
            }
        }
        
        return !empty($enabled) ? $enabled : $all_platforms;
    }
    
    /**
     * Free up memory between operations
     */
    private function free_memory() {
        global $wpdb;
        
        // Clear query cache
        $wpdb->flush();
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Clean up orphaned post meta
     */
    private function cleanup_orphaned_meta() {
        global $wpdb;
        
        // Delete post meta for non-existent posts
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.ID IS NULL
            AND pm.meta_key LIKE '_streaming_guide_%'"
        );
    }
    
    /**
     * Check if generation should run
     */
    private function should_run_generation($type) {
        // Check if enabled
        $option_key = "streaming_guide_auto_generate_{$type}";
        if (!get_option($option_key, true)) {
            return false;
        }
        
        // Check last run time to prevent duplicate runs
        $last_run = get_option("streaming_guide_last_{$type}_run", 0);
        $min_interval = $type === 'weekly' ? 518400 : 2419200; // 6 days for weekly, 28 days for monthly
        
        if (time() - $last_run < $min_interval) {
            $this->error_handler->log_error(
                "Skipping {$type} generation - too soon since last run",
                array('last_run' => date('Y-m-d H:i:s', $last_run)),
                'info'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Update last run time
     */
    private function update_last_run($type) {
        update_option("streaming_guide_last_{$type}_run", time());
    }
    
    /**
     * Load generator class
     */
    private function load_generator($type) {
        $generator_class = 'Streaming_Guide_' . ucfirst($type) . '_Generator';
        $generator_file = STREAMING_GUIDE_PLUGIN_DIR . "includes/generators/class-streaming-guide-{$type}-generator.php";
        
        if (!file_exists($generator_file)) {
            return false;
        }
        
        require_once $generator_file;
        
        if (!class_exists($generator_class)) {
            return false;
        }
        
        // Initialize TMDB and OpenAI APIs
        $tmdb_api = new Streaming_Guide_TMDB_API();
        $openai_api = new Streaming_Guide_OpenAI_API();
        
        return new $generator_class($tmdb_api, $openai_api);
    }
}