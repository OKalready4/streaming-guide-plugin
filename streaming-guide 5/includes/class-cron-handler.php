<?php
/**
 * Cron Handler - Manages scheduled content generation
 * 
 * Fixes the "fire and forget" issues with proper state tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Cron_Handler {
    private $state_manager;
    private $error_handler;
    
    public function __construct() {
        // Load dependencies
        $this->state_manager = new Streaming_Guide_State_Manager();
        $this->error_handler = new Streaming_Guide_Error_Handler();
        
        // Register cron hooks
        add_action('streaming_guide_weekly_cron', array($this, 'run_weekly_generation'));
        add_action('streaming_guide_monthly_cron', array($this, 'run_monthly_generation'));
        add_action('streaming_guide_trending_cron', array($this, 'run_trending_generation'));
        add_action('streaming_guide_cleanup_history', array($this, 'cleanup_old_data'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('streaming_guide_cleanup_history')) {
            wp_schedule_event(time(), 'daily', 'streaming_guide_cleanup_history');
        }
    }
    
    /**
     * Run weekly content generation
     */
    public function run_weekly_generation() {
        // Check if we should run
        if (!$this->should_run_generation('weekly')) {
            return;
        }
        
        // Get enabled platforms
        $platforms = $this->get_enabled_platforms();
        
        foreach ($platforms as $platform) {
            try {
                // Check if already generated this week
                if ($this->state_manager->has_recent_content('weekly', $platform, 168)) { // 7 days
                    $this->error_handler->log_error(
                        "Skipping weekly generation for {$platform} - already generated this week",
                        array('platform' => $platform),
                        'info'
                    );
                    continue;
                }
                
                // Start generation
                $generation_id = $this->state_manager->start_generation('weekly', $platform);
                
                // Schedule background processing
                wp_schedule_single_event(
                    time() + rand(1, 60), // Random delay 1-60 seconds to spread load
                    'streaming_guide_process_generation',
                    array($generation_id)
                );
                
                // Log
                $this->error_handler->log_error(
                    "Scheduled weekly generation for {$platform}",
                    array('generation_id' => $generation_id),
                    'info'
                );
                
                // Delay between platforms to avoid API rate limits
                sleep(2);
                
            } catch (Exception $e) {
                $this->error_handler->log_error(
                    "Failed to schedule weekly generation for {$platform}: " . $e->getMessage(),
                    array('platform' => $platform),
                    'error'
                );
            }
        }
        
        // Update last run time
        $this->update_schedule_last_run('weekly');
    }
    
    /**
     * Run monthly content generation
     */
    public function run_monthly_generation() {
        // Check if we should run
        if (!$this->should_run_generation('monthly')) {
            return;
        }
        
        // Get enabled platforms
        $platforms = $this->get_enabled_platforms();
        $month = date('Y-m', strtotime('-1 month')); // Previous month
        
        foreach ($platforms as $platform) {
            try {
                // Check if already generated for this month
                $existing = $this->check_monthly_exists($platform, $month);
                if ($existing) {
                    $this->error_handler->log_error(
                        "Skipping monthly generation for {$platform}/{$month} - already exists",
                        array('platform' => $platform, 'month' => $month),
                        'info'
                    );
                    continue;
                }
                
                // Start generation
                $params = array('month' => $month);
                $generation_id = $this->state_manager->start_generation('monthly', $platform, $params);
                
                // Schedule background processing
                wp_schedule_single_event(
                    time() + rand(1, 60),
                    'streaming_guide_process_generation',
                    array($generation_id)
                );
                
                // Log
                $this->error_handler->log_error(
                    "Scheduled monthly generation for {$platform}/{$month}",
                    array('generation_id' => $generation_id),
                    'info'
                );
                
                // Delay between platforms
                sleep(2);
                
            } catch (Exception $e) {
                $this->error_handler->log_error(
                    "Failed to schedule monthly generation for {$platform}: " . $e->getMessage(),
                    array('platform' => $platform, 'month' => $month),
                    'error'
                );
            }
        }
        
        // Update last run time
        $this->update_schedule_last_run('monthly');
    }
    
    /**
     * Run trending content generation
     */
    public function run_trending_generation() {
        // Check if we should run
        if (!$this->should_run_generation('trending')) {
            return;
        }
        
        // Get enabled platforms
        $platforms = $this->get_enabled_platforms();
        
        foreach ($platforms as $platform) {
            try {
                // Check if already generated recently (3 days for trending)
                if ($this->state_manager->has_recent_content('trending', $platform, 72)) {
                    continue;
                }
                
                // Start generation
                $params = array('content_type' => 'mixed');
                $generation_id = $this->state_manager->start_generation('trending', $platform, $params);
                
                // Schedule background processing
                wp_schedule_single_event(
                    time() + rand(1, 60),
                    'streaming_guide_process_generation',
                    array($generation_id)
                );
                
                // Delay between platforms
                sleep(2);
                
            } catch (Exception $e) {
                $this->error_handler->log_error(
                    "Failed to schedule trending generation for {$platform}: " . $e->getMessage(),
                    array('platform' => $platform),
                    'error'
                );
            }
        }
        
        // Update last run time
        $this->update_schedule_last_run('trending');
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        try {
            // Clean up history older than 90 days
            $this->state_manager->cleanup_old_history(90);
            
            // Clean up error logs if needed
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
     * Check if we should run generation
     */
    private function should_run_generation($type) {
        // Check if schedule is active
        if (!$this->state_manager->is_schedule_active($type)) {
            return false;
        }
        
        // Check if APIs are configured
        $tmdb_key = get_option('streaming_guide_tmdb_api_key');
        $openai_key = get_option('streaming_guide_openai_api_key');
        
        if (empty($tmdb_key) || empty($openai_key)) {
            $this->error_handler->log_error(
                "Cannot run {$type} generation - API keys not configured",
                array(),
                'warning'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Get enabled platforms
     */
    private function get_enabled_platforms() {
        $all_platforms = array('netflix', 'disney', 'max', 'prime', 'hulu', 'apple', 'paramount', 'peacock');
        $enabled_platforms = get_option('streaming_guide_enabled_platforms', $all_platforms);
        
        return array_intersect($all_platforms, $enabled_platforms);
    }
    
    /**
     * Check if monthly content already exists
     */
    private function check_monthly_exists($platform, $month) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'streaming_guide_history';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE generator_type = 'monthly' 
            AND platform = %s 
            AND params LIKE %s 
            AND status = 'success'",
            $platform,
            '%"month":"' . $month . '"%'
        ));
        
        return $exists > 0;
    }
    
    /**
     * Update schedule last run time
     */
    private function update_schedule_last_run($type) {
        $schedules = get_option('streaming_guide_active_schedules', array());
        
        if (isset($schedules[$type])) {
            $schedules[$type]['last_run'] = current_time('mysql');
            update_option('streaming_guide_active_schedules', $schedules);
        }
    }
}