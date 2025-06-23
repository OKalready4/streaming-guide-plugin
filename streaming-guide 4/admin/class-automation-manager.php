<?php
/**
 * Enhanced Automation Manager with Spotlight Automation
 * File: includes/class-automation-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Automation_Manager {
    private $tmdb;
    private $openai;
    private $log_file;
    
    public function __construct($tmdb, $openai) {
        $this->tmdb = $tmdb;
        $this->openai = $openai;
        $this->log_file = STREAMING_GUIDE_PLUGIN_DIR . 'logs/automation.log';
        
        wp_mkdir_p(dirname($this->log_file));
        
        // Hook into WordPress cron
        add_action('streaming_guide_weekly_auto', array($this, 'run_weekly_automation'));
        add_action('streaming_guide_trending_auto', array($this, 'run_trending_automation'));
        add_action('streaming_guide_spotlight_auto', array($this, 'run_spotlight_automation')); // NEW
        
        // Hook for manual triggers
        add_action('wp_ajax_streaming_guide_trigger_automation', array($this, 'handle_manual_trigger'));
    }
    
    /**
     * NEW: Run automated spotlight generation for big new releases
     */
    public function run_spotlight_automation() {
        if (!get_option('streaming_guide_auto_spotlight', 1)) {
            $this->log('Spotlight automation is disabled, skipping');
            return;
        }
        
        $this->log('Starting automated spotlight generation for big new releases');
        
        try {
            // Get big new releases from the past week
            $big_releases = $this->get_big_new_releases();
            
            if (empty($big_releases)) {
                $this->log('No big new releases found for spotlight generation');
                return;
            }
            
            $spotlight_count = get_option('streaming_guide_spotlight_count', 3);
            $success_count = 0;
            $error_count = 0;
            
            // Generate spotlights for top releases
            foreach (array_slice($big_releases, 0, $spotlight_count) as $release) {
                try {
                    $result = $this->generate_spotlight_content($release);
                    
                    if ($result && !is_wp_error($result)) {
                        $success_count++;
                        $title = $release['title'] ?? $release['name'];
                        $this->log("Successfully generated spotlight for '{$title}' (Post ID: {$result})");
                        
                        // Delay between generations
                        sleep(60);
                    } else {
                        $error_count++;
                        $error_msg = is_wp_error($result) ? $result->get_error_message() : 'Unknown error';
                        $this->log("Failed to generate spotlight: {$error_msg}");
                    }
                    
                } catch (Exception $e) {
                    $error_count++;
                    $this->log("Exception during spotlight generation: " . $e->getMessage());
                }
            }
            
            $this->log("Spotlight automation completed: {$success_count} successful, {$error_count} failed");
            $this->send_automation_report('spotlight', $success_count, $error_count);
            
        } catch (Exception $e) {
            $this->log("Critical error in spotlight automation: " . $e->getMessage());
            $this->send_automation_report('spotlight', 0, 1);
        }
    }
    
    /**
     * Get big new releases worth spotlighting
     */
    private function get_big_new_releases() {
        $releases = array();
        
        try {
            // Get date range for past week's releases
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime('-7 days'));
            
            // Get new movie releases with high popularity
            $movies = $this->tmdb->make_request('discover/movie', array(
                'primary_release_date.gte' => $start_date,
                'primary_release_date.lte' => $end_date,
                'sort_by' => 'popularity.desc',
                'vote_count.gte' => 50, // Ensure some votes
                'with_original_language' => 'en',
                'page' => 1
            ));
            
            if (!is_wp_error($movies) && isset($movies['results'])) {
                foreach ($movies['results'] as $movie) {
                    // Filter for truly popular releases
                    if ($movie['popularity'] >= 100) { // High popularity threshold
                        $movie['media_type'] = 'movie';
                        $releases[] = $movie;
                    }
                }
            }
            
            // Get new TV show premieres
            $tv_shows = $this->tmdb->make_request('discover/tv', array(
                'first_air_date.gte' => $start_date,
                'first_air_date.lte' => $end_date,
                'sort_by' => 'popularity.desc',
                'vote_count.gte' => 20,
                'with_original_language' => 'en',
                'page' => 1
            ));
            
            if (!is_wp_error($tv_shows) && isset($tv_shows['results'])) {
                foreach ($tv_shows['results'] as $show) {
                    if ($show['popularity'] >= 80) { // Slightly lower threshold for TV
                        $show['media_type'] = 'tv';
                        $releases[] = $show;
                    }
                }
            }
            
            // Also check trending content for potential big releases
            $trending_movies = $this->tmdb->make_request('trending/movie/week');
            if (!is_wp_error($trending_movies) && isset($trending_movies['results'])) {
                foreach (array_slice($trending_movies['results'], 0, 5) as $movie) {
                    // Check if it's a recent release
                    if (!empty($movie['release_date'])) {
                        $release_date = strtotime($movie['release_date']);
                        $two_weeks_ago = strtotime('-14 days');
                        
                        if ($release_date >= $two_weeks_ago) {
                            $movie['media_type'] = 'movie';
                            $movie['is_trending'] = true;
                            $releases[] = $movie;
                        }
                    }
                }
            }
            
            // Remove duplicates and sort by popularity
            $releases = $this->deduplicate_releases($releases);
            usort($releases, function($a, $b) {
                return ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0);
            });
            
            $this->log("Found " . count($releases) . " potential big releases for spotlight");
            
            return $releases;
            
        } catch (Exception $e) {
            $this->log("Error getting big new releases: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Generate spotlight content for a release
     */
    private function generate_spotlight_content($release) {
        if (!class_exists('Streaming_Guide_Spotlight_Generator')) {
            throw new Exception('Spotlight generator class not found');
        }
        
        $generator = new Streaming_Guide_Spotlight_Generator($this->tmdb, $this->openai);
        
        $options = array(
            'include_trailers' => true,
            'auto_publish' => true,
            'auto_featured_image' => true,
            'seo_optimize' => true,
            'tmdb_id' => $release['id'],
            'media_type' => $release['media_type'],
            'include_landscape_images' => true
        );
        
        // Determine platform based on availability
        $platform = $this->determine_primary_platform($release);
        
        return $generator->generate_article($platform, $options);
    }
    
    /**
     * Determine primary streaming platform for content
     */
    private function determine_primary_platform($release) {
        try {
            $providers = $this->tmdb->make_request(
                "{$release['media_type']}/{$release['id']}/watch/providers"
            );
            
            if (!is_wp_error($providers) && isset($providers['results']['US']['flatrate'])) {
                // Priority order for platforms
                $platform_priority = array(
                    8 => 'netflix',
                    15 => 'hulu',
                    337 => 'disney',
                    1899 => 'hbo',
                    9 => 'amazon',
                    531 => 'paramount',
                    350 => 'apple'
                );
                
                foreach ($providers['results']['US']['flatrate'] as $provider) {
                    if (isset($platform_priority[$provider['provider_id']])) {
                        return $platform_priority[$provider['provider_id']];
                    }
                }
            }
        } catch (Exception $e) {
            $this->log("Error determining platform: " . $e->getMessage());
        }
        
        return 'all'; // Default if no specific platform found
    }
    
    /**
     * Remove duplicate releases
     */
    private function deduplicate_releases($releases) {
        $seen = array();
        $unique = array();
        
        foreach ($releases as $release) {
            $key = $release['media_type'] . '_' . $release['id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $release;
            }
        }
        
        return $unique;
    }

    /**
     * Run automated weekly content generation
     */
    public function run_weekly_automation() {
        if (!get_option('streaming_guide_auto_weekly', 1)) {
            $this->log('Weekly automation is disabled, skipping');
            return;
        }
        
        $this->log('Starting automated weekly generation');
        
        $platforms = array('netflix', 'hulu', 'disney', 'hbo', 'amazon');
        $success_count = 0;
        $error_count = 0;
        
        foreach ($platforms as $platform) {
            try {
                $result = $this->generate_weekly_content($platform);
                
                if ($result && !is_wp_error($result)) {
                    $success_count++;
                    $this->log("Successfully generated weekly content for {$platform} (Post ID: {$result})");
                    
                    // Add delay between generations to respect API limits
                    sleep(45);
                } else {
                    $error_count++;
                    $error_msg = is_wp_error($result) ? $result->get_error_message() : 'Unknown error';
                    $this->log("Failed to generate weekly content for {$platform}: {$error_msg}");
                }
                
            } catch (Exception $e) {
                $error_count++;
                $this->log("Exception during weekly generation for {$platform}: " . $e->getMessage());
            }
        }
        
        $this->log("Weekly automation completed: {$success_count} successful, {$error_count} failed");
        
        // Send notification email if configured
        $this->send_automation_report('weekly', $success_count, $error_count);
    }
    
    /**
     * Run automated trending content generation
     */
    public function run_trending_automation() {
        if (!get_option('streaming_guide_auto_trending', 1)) {
            $this->log('Trending automation is disabled, skipping');
            return;
        }
        
        $this->log('Starting automated trending generation');
        
        try {
            $result = $this->generate_trending_content();
            
            if ($result && !is_wp_error($result)) {
                $this->log("Successfully generated trending content (Post ID: {$result})");
                $this->send_automation_report('trending', 1, 0);
            } else {
                $error_msg = is_wp_error($result) ? $result->get_error_message() : 'Unknown error';
                $this->log("Failed to generate trending content: {$error_msg}");
                $this->send_automation_report('trending', 0, 1);
            }
            
        } catch (Exception $e) {
            $this->log("Exception during trending generation: " . $e->getMessage());
            $this->send_automation_report('trending', 0, 1);
        }
    }
    
    /**
     * Generate weekly content for a specific platform
     */
    private function generate_weekly_content($platform) {
        if (!class_exists('Streaming_Guide_Weekly_Generator')) {
            throw new Exception('Weekly generator class not found');
        }
        
        $generator = new Streaming_Guide_Weekly_Generator($this->tmdb, $this->openai);
        
        $options = array(
            'include_trailers' => get_option('streaming_guide_include_trailers', 1),
            'auto_publish' => true,
            'auto_featured_image' => true,
            'seo_optimize' => true
        );
        
        return $generator->generate_article($platform, $options);
    }
    
    /**
     * Generate trending content
     */
    private function generate_trending_content() {
        if (!class_exists('Streaming_Guide_Trending_Generator')) {
            throw new Exception('Trending generator class not found');
        }
        
        $generator = new Streaming_Guide_Trending_Generator($this->tmdb, $this->openai);
        
        $options = array(
            'include_trailers' => get_option('streaming_guide_include_trailers', 1),
            'auto_publish' => true,
            'auto_featured_image' => true,
            'seo_optimize' => true,
            'min_items' => get_option('streaming_guide_trending_count', 5),
            'max_items' => 10
        );
        
        return $generator->generate_article('all', $options);
    }
    
    /**
     * Handle manual automation triggers from admin
     */
    public function handle_manual_trigger() {
        if (!wp_verify_nonce($_POST['nonce'], 'streaming_guide_automation') || 
            !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        try {
            switch ($type) {
                case 'weekly':
                    $platform = sanitize_text_field($_POST['platform']);
                    $result = $this->generate_weekly_content($platform);
                    break;
                    
                case 'trending':
                    $result = $this->generate_trending_content();
                    break;
                    
                default:
                    wp_send_json_error('Invalid automation type');
                    return;
            }
            
            if ($result && !is_wp_error($result)) {
                wp_send_json_success(array(
                    'message' => 'Content generated successfully!',
                    'post_id' => $result,
                    'edit_url' => admin_url("post.php?post={$result}&action=edit"),
                    'view_url' => get_permalink($result)
                ));
            } else {
                $error_msg = is_wp_error($result) ? $result->get_error_message() : 'Generation failed';
                wp_send_json_error($error_msg);
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send automation report email
     */
    private function send_automation_report($type, $success_count, $error_count) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }
        
        $site_name = get_bloginfo('name');
        $subject = "[{$site_name}] Streaming Guide Automation Report: " . ucfirst($type);
        
        $message = "Streaming Guide Pro Automation Report\n\n";
        $message .= "Type: " . ucfirst($type) . " Content Generation\n";
        $message .= "Date: " . current_time('F j, Y g:i A') . "\n\n";
        $message .= "Results:\n";
        $message .= "- Successful: {$success_count}\n";
        $message .= "- Failed: {$error_count}\n\n";
        
        if ($error_count > 0) {
            $message .= "Please check the automation logs for details on any failures.\n\n";
        }
        
        $message .= "View your content: " . admin_url('edit.php') . "\n";
        $message .= "Automation settings: " . admin_url('admin.php?page=streaming-guide-automation') . "\n\n";
        $message .= "This is an automated message from Streaming Guide Pro.";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get automation statistics
     */
    public function get_automation_stats() {
        global $wpdb;
        
        // Get posts generated in the last 30 days
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $stats = array();
        
        // Weekly content stats
        $weekly_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_date >= %s
            AND pm1.meta_key = 'generated_by' AND pm1.meta_value = 'streaming_guide'
            AND pm2.meta_key = 'generator_type' AND pm2.meta_value = 'weekly'
        ", $thirty_days_ago));
        
        // Trending content stats
        $trending_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_date >= %s
            AND pm1.meta_key = 'generated_by' AND pm1.meta_value = 'streaming_guide'
            AND pm2.meta_key = 'generator_type' AND pm2.meta_value = 'trending'
        ", $thirty_days_ago));
        
        // Spotlight content stats (manual)
        $spotlight_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_date >= %s
            AND pm1.meta_key = 'generated_by' AND pm1.meta_value = 'streaming_guide'
            AND pm2.meta_key = 'generator_type' AND pm2.meta_value = 'spotlight'
        ", $thirty_days_ago));
        
        return array(
            'weekly_generated' => intval($weekly_count),
            'trending_generated' => intval($trending_count),
            'spotlight_generated' => intval($spotlight_count),
            'total_generated' => intval($weekly_count) + intval($trending_count) + intval($spotlight_count),
            'period_days' => 30
        );
    }
    
    /**
     * Get recent automation logs
     */
    public function get_recent_logs($lines = 50) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $logs = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($logs === false) {
            return array();
        }
        
        // Return the last N lines
        return array_slice($logs, -$lines);
    }
    
    /**
     * Clear automation logs
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
            $this->log('Automation logs cleared');
        }
    }
    
    /**
     * Check if automation is healthy
     */
    public function get_health_status() {
        $status = array(
            'overall' => 'healthy',
            'issues' => array(),
            'last_check' => current_time('mysql')
        );
        
        // Check if required APIs are configured
        if (!get_option('streaming_guide_tmdb_api_key')) {
            $status['issues'][] = 'TMDB API key not configured';
            $status['overall'] = 'error';
        }
        
        if (!get_option('streaming_guide_openai_api_key')) {
            $status['issues'][] = 'OpenAI API key not configured';
            $status['overall'] = 'error';
        }
        
        // Check if automation is scheduled
        if (!wp_next_scheduled('streaming_guide_weekly_auto') && get_option('streaming_guide_auto_weekly', 1)) {
            $status['issues'][] = 'Weekly automation not scheduled';
            $status['overall'] = 'warning';
        }
        
        if (!wp_next_scheduled('streaming_guide_trending_auto') && get_option('streaming_guide_auto_trending', 1)) {
            $status['issues'][] = 'Trending automation not scheduled';
            $status['overall'] = 'warning';
        }
        
        // Check recent generation success rate
        $recent_logs = $this->get_recent_logs(20);
        $failed_count = 0;
        
        foreach ($recent_logs as $log) {
            if (strpos($log, 'Failed') !== false || strpos($log, 'Exception') !== false) {
                $failed_count++;
            }
        }
        
        if ($failed_count > 5) {
            $status['issues'][] = 'High failure rate in recent generations';
            $status['overall'] = 'warning';
        }
        
        return $status;
    }
    
    /**
     * Log automation activities
     */
    private function log($message) {
        $timestamp = current_time('mysql');
        $log_entry = "[{$timestamp}] {$message}\n";
        
        // Ensure log file doesn't get too large (keep last 1000 lines)
        if (file_exists($this->log_file)) {
            $lines = file($this->log_file);
            if (count($lines) > 1000) {
                $lines = array_slice($lines, -500); // Keep last 500 lines
                file_put_contents($this->log_file, implode('', $lines));
            }
        }
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Force reschedule automation events
     */
    public function reschedule_automation() {
        // Clear existing schedules
        wp_clear_scheduled_hook('streaming_guide_weekly_auto');
        wp_clear_scheduled_hook('streaming_guide_trending_auto');
        
        // Reschedule if enabled
        if (get_option('streaming_guide_auto_weekly', 1)) {
            wp_schedule_event(strtotime('next Sunday 6:00 AM'), 'weekly', 'streaming_guide_weekly_auto');
        }
        
        if (get_option('streaming_guide_auto_trending', 1)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'twiceweekly', 'streaming_guide_trending_auto');
        }
        
        $this->log('Automation schedules have been reset');
    }
}