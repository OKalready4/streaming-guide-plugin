<?php
/**
 * State Manager - Tracks generated content and prevents duplicates
 * 
 * This class maintains a record of all generated content to prevent
 * duplicate posts and track generation history.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_State_Manager {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'streaming_guide_history';
        
        // Create table if it doesn't exist
        $this->create_tables();
    }
    
    /**
     * Create database tables for tracking
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            generation_id varchar(64) NOT NULL,
            generator_type varchar(50) NOT NULL,
            platform varchar(50) NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error_message text DEFAULT NULL,
            params longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            content_hash varchar(64) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_generator_platform (generator_type, platform),
            KEY idx_created_at (created_at),
            KEY idx_content_hash (content_hash),
            KEY idx_generation_id (generation_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Social media tracking table
        $social_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}streaming_guide_social_posts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            social_post_id varchar(255) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            shared_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_post_platform (post_id, platform),
            KEY idx_shared_at (shared_at)
        ) $charset_collate;";
        
        dbDelta($social_sql);
    }
    
    /**
     * Check if similar content was generated recently
     */
    public function has_recent_content($generator_type, $platform, $hours = 24) {
        global $wpdb;
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE generator_type = %s 
            AND platform = %s 
            AND status = 'success'
            AND created_at > %s",
            $generator_type,
            $platform,
            $since
        ));
        
        return $count > 0;
    }
    
    /**
     * Get last generation date for specific type/platform
     */
    public function get_last_generated($generator_type, $platform) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$this->table_name} 
            WHERE generator_type = %s 
            AND platform = %s 
            AND status = 'success'
            ORDER BY created_at DESC 
            LIMIT 1",
            $generator_type,
            $platform
        ));
    }
    
    /**
     * Start tracking a new generation
     */
    public function start_generation($generator_type, $platform, $params = array()) {
        global $wpdb;
        
        $generation_id = wp_generate_uuid4();
        
        $wpdb->insert(
            $this->table_name,
            array(
                'generation_id' => $generation_id,
                'generator_type' => $generator_type,
                'platform' => $platform,
                'status' => 'processing',
                'params' => json_encode($params),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $generation_id;
    }
    
    /**
     * Complete a generation (success or failure)
     */
    public function complete_generation($generation_id, $post_id = null, $status = 'success', $error = null) {
        global $wpdb;
        
        $update_data = array(
            'status' => $status,
            'completed_at' => current_time('mysql')
        );
        
        if ($post_id) {
            $update_data['post_id'] = $post_id;
            
            // Generate content hash to detect exact duplicates
            $content = get_post_field('post_content', $post_id);
            $update_data['content_hash'] = md5($content);
        }
        
        if ($error) {
            $update_data['error_message'] = $error;
        }
        
        $wpdb->update(
            $this->table_name,
            $update_data,
            array('generation_id' => $generation_id),
            array('%s', '%s', '%d', '%s', '%s'),
            array('%s')
        );
    }
    
    /**
     * Check if exact content already exists (by hash)
     */
    public function content_exists($content_hash) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$this->table_name} 
            WHERE content_hash = %s 
            AND status = 'success' 
            LIMIT 1",
            $content_hash
        ));
        
        return $exists ? intval($exists) : false;
    }
    
    /**
     * Get content generation history
     */
    public function get_content_history($limit = 50, $offset = 0) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, p.post_title 
            FROM {$this->table_name} h
            LEFT JOIN {$wpdb->posts} p ON h.post_id = p.ID
            ORDER BY h.created_at DESC 
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);
        
        // Format results
        $history = array();
        foreach ($results as $row) {
            $history[] = array(
                'id' => $row['id'],
                'date' => $row['created_at'],
                'title' => $row['post_title'] ?: __('(Generation Failed)', 'streaming-guide'),
                'type' => $row['generator_type'],
                'platform' => $row['platform'],
                'status' => $row['status'],
                'post_id' => $row['post_id'],
                'error' => $row['error_message']
            );
        }
        
        return $history;
    }
    
    /**
     * Track social media share
     */
    public function track_social_share($post_id, $platform, $social_post_id = null, $status = 'success', $error = null) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'streaming_guide_social_posts',
            array(
                'post_id' => $post_id,
                'platform' => $platform,
                'social_post_id' => $social_post_id,
                'status' => $status,
                'shared_at' => current_time('mysql'),
                'error_message' => $error
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Check if post was already shared to platform
     */
    public function was_shared_to_platform($post_id, $platform) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}streaming_guide_social_posts 
            WHERE post_id = %d 
            AND platform = %s 
            AND status = 'success'",
            $post_id,
            $platform
        ));
        
        return $count > 0;
    }
    
    /**
     * Get posts pending social share
     */
    public function get_pending_social_posts($platform, $limit = 5) {
        global $wpdb;
        
        // Get recent posts that haven't been shared to this platform
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h.post_id, p.post_title, p.post_date
            FROM {$this->table_name} h
            INNER JOIN {$wpdb->posts} p ON h.post_id = p.ID
            LEFT JOIN {$wpdb->prefix}streaming_guide_social_posts s 
                ON h.post_id = s.post_id AND s.platform = %s AND s.status = 'success'
            WHERE h.status = 'success' 
            AND h.post_id IS NOT NULL
            AND p.post_status = 'publish'
            AND s.id IS NULL
            AND h.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY h.created_at DESC
            LIMIT %d",
            $platform,
            $limit
        ), ARRAY_A);
        
        return $results;
    }
    
    /**
     * Schedule management methods
     */
    public function get_active_schedules() {
        $schedules = get_option('streaming_guide_active_schedules', array());
        $formatted = array();
        
        foreach ($schedules as $type => $data) {
            if ($data['active']) {
                $formatted[] = array(
                    'id' => $type,
                    'type' => ucfirst($type),
                    'frequency' => $data['frequency'],
                    'last_run' => $data['last_run'] ?? null,
                    'next_run' => wp_next_scheduled('streaming_guide_' . $type . '_cron')
                );
            }
        }
        
        return $formatted;
    }
    
    public function activate_schedule($type, $frequency) {
        $schedules = get_option('streaming_guide_active_schedules', array());
        
        $schedules[$type] = array(
            'active' => true,
            'frequency' => $frequency,
            'activated_at' => current_time('mysql')
        );
        
        update_option('streaming_guide_active_schedules', $schedules);
    }
    
    public function deactivate_schedule($type) {
        $schedules = get_option('streaming_guide_active_schedules', array());
        
        if (isset($schedules[$type])) {
            $schedules[$type]['active'] = false;
            $schedules[$type]['deactivated_at'] = current_time('mysql');
        }
        
        update_option('streaming_guide_active_schedules', $schedules);
    }
    
    public function is_schedule_active($type) {
        $schedules = get_option('streaming_guide_active_schedules', array());
        return isset($schedules[$type]) && $schedules[$type]['active'];
    }
    
    /**
     * Clean up old history entries
     */
    public function cleanup_old_history($days = 90) {
        global $wpdb;
        
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $cutoff
        ));
    }
}