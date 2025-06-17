<?php
/**
 * State Manager - Handles content generation history and state tracking
 * Fixed version with proper duplicate prevention
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_State_Manager {
    private $table_name;
    private $social_table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'streaming_guide_history';
        $this->social_table_name = $wpdb->prefix . 'streaming_guide_social_shares';
        
        // Create tables if they don't exist
        $this->create_tables();
    }
    
    /**
     * Create necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // History table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            generator_type varchar(50) NOT NULL,
            platform varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            post_id bigint(20) DEFAULT NULL,
            params text,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY generator_platform (generator_type, platform),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Social shares table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->social_table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            social_post_id varchar(255) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_platform (post_id, platform),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
    }
    
    /**
     * Start a new generation
     */
    public function start_generation($generator_type, $platform, $params = array()) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'generator_type' => $generator_type,
                'platform' => $platform,
                'status' => 'pending',
                'params' => json_encode($params),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update generation status
     */
    public function update_generation_status($generation_id, $status, $error_message = null) {
        global $wpdb;
        
        $data = array('status' => $status);
        $format = array('%s');
        
        if ($error_message !== null) {
            $data['error_message'] = $error_message;
            $format[] = '%s';
        }
        
        $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $generation_id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Complete generation successfully
     */
    public function complete_generation($generation_id, $post_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'success',
                'post_id' => $post_id,
                'completed_at' => current_time('mysql')
            ),
            array('id' => $generation_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
    }
    
    /**
     * Fail generation
     */
    public function fail_generation($generation_id, $error_message) {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'failed',
                'error_message' => $error_message,
                'completed_at' => current_time('mysql')
            ),
            array('id' => $generation_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Check if content was recently generated
     */
    public function has_recent_content($generator_type, $platform, $hours = 24) {
        global $wpdb;
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE generator_type = %s 
            AND platform = %s 
            AND status = 'success' 
            AND created_at > %s",
            $generator_type,
            $platform,
            $since
        ));
        
        return intval($exists) > 0;
    }
    
    /**
     * Get last generated content
     */
    public function get_last_generated($generator_type, $platform) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
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
     * Get generation status
     */
    public function get_generation_status($generation_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $generation_id
        ));
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
        
        return $results;
    }
    
    /**
     * Track social media share
     */
    public function track_social_share($post_id, $platform, $social_post_id = null, $status = 'success', $error = null) {
        global $wpdb;
        
        $wpdb->insert(
            $this->social_table_name,
            array(
                'post_id' => $post_id,
                'platform' => $platform,
                'social_post_id' => $social_post_id,
                'status' => $status,
                'error_message' => $error,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Check if post was shared to platform
     */
    public function was_shared_to_platform($post_id, $platform) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->social_table_name} 
            WHERE post_id = %d 
            AND platform = %s 
            AND status = 'success'",
            $post_id,
            $platform
        ));
        
        return intval($exists) > 0;
    }
    
    /**
     * Get social share history
     */
    public function get_social_share_history($post_id = null, $limit = 50) {
        global $wpdb;
        
        if ($post_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->social_table_name} 
                WHERE post_id = %d 
                ORDER BY created_at DESC 
                LIMIT %d",
                $post_id,
                $limit
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, p.post_title 
                FROM {$this->social_table_name} s
                LEFT JOIN {$wpdb->posts} p ON s.post_id = p.ID
                ORDER BY s.created_at DESC 
                LIMIT %d",
                $limit
            ));
        }
    }
    
    /**
     * Clean up old history records
     */
    public function cleanup_old_history($days = 90) {
        global $wpdb;
        
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Delete old history records
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
            WHERE created_at < %s 
            AND status IN ('success', 'failed', 'cancelled')",
            $cutoff
        ));
        
        // Delete old social share records
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->social_table_name} 
            WHERE created_at < %s",
            $cutoff
        ));
    }
    
    /**
     * Get statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total generations
        $stats['total_generations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );
        
        // Successful generations
        $stats['successful_generations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'success'"
        );
        
        // Failed generations
        $stats['failed_generations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'"
        );
        
        // Recent generations (last 7 days)
        $stats['recent_generations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Generations by type
        $stats['by_type'] = $wpdb->get_results(
            "SELECT generator_type, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE status = 'success' 
            GROUP BY generator_type"
        );
        
        // Generations by platform
        $stats['by_platform'] = $wpdb->get_results(
            "SELECT platform, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE status = 'success' 
            GROUP BY platform"
        );
        
        // Social shares
        $stats['total_shares'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->social_table_name} WHERE status = 'success'"
        );
        
        return $stats;
    }
}