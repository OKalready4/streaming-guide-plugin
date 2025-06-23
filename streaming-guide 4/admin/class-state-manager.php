<?php
/**
 * State Manager - CRITICAL FIX VERSION
 * 
 * Manages plugin state, generation tracking, and database operations.
 * FIXED: Simple constructor that doesn't require parameters
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_State_Manager {
    private $table_name;
    
    /**
     * CRITICAL FIX: Simple constructor with no required parameters
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'streaming_guide_history';
        
        // Create table if it doesn't exist
        $this->create_tables();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            generator_type varchar(50) NOT NULL,
            platform varchar(50) NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            params longtext,
            error_message text,
            content_hash varchar(32),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY generator_platform (generator_type, platform),
            KEY status (status),
            KEY created_at (created_at),
            KEY content_hash (content_hash)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
                'params' => maybe_serialize($params),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('Failed to start generation: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Complete a generation
     */
    public function complete_generation($generation_id, $post_id = null, $status = 'success', $error_message = null) {
        global $wpdb;
        
        $data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($post_id) {
            $data['post_id'] = $post_id;
        }
        
        if ($error_message) {
            $data['error_message'] = $error_message;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $generation_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            error_log('Failed to complete generation: ' . $wpdb->last_error);
        }
        
        return $result !== false;
    }
    
    /**
     * Check if content exists by hash
     */
    public function content_exists($content_hash) {
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$this->table_name} 
             WHERE content_hash = %s 
             AND status = 'success' 
             AND post_id IS NOT NULL 
             ORDER BY created_at DESC 
             LIMIT 1",
            $content_hash
        ));
        
        // Verify the post still exists
        if ($post_id && get_post($post_id)) {
            return intval($post_id);
        }
        
        return false;
    }
    
    /**
     * Store content hash
     */
    public function store_content_hash($generation_id, $content_hash) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            array('content_hash' => $content_hash),
            array('id' => $generation_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Check for recent content generation
     */
    public function has_recent_content($generator_type, $platform, $hours = 12) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE generator_type = %s 
             AND platform = %s 
             AND status = 'success' 
             AND created_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $generator_type,
            $platform,
            $hours
        ));
        
        return intval($count) > 0;
    }
    
    /**
     * Get last generated date
     */
    public function get_last_generated($generator_type, $platform) {
        global $wpdb;
        
        $date = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$this->table_name} 
             WHERE generator_type = %s 
             AND platform = %s 
             AND status = 'success' 
             ORDER BY created_at DESC 
             LIMIT 1",
            $generator_type,
            $platform
        ));
        
        return $date;
    }
    
    /**
     * Get generation history
     */
    public function get_history($limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * Get generation by ID
     */
    public function get_generation($generation_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $generation_id
        ));
    }
    
    /**
     * Mark post as deleted
     */
    public function mark_post_deleted($generation_id) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            array(
                'post_id' => null,
                'status' => 'deleted',
                'updated_at' => current_time('mysql')
            ),
            array('id' => $generation_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Clean up old records
     */
    public function cleanup_old_records($days = 90) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) 
             AND status IN ('failed', 'cancelled')",
            $days
        ));
        
        return $result;
    }
    
    /**
     * Get statistics
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total generations
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Successful generations
        $stats['successful'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'success'");
        
        // Failed generations
        $stats['failed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'");
        
        // Recent generations (last 7 days)
        $stats['recent'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Popular platforms
        $stats['platforms'] = $wpdb->get_results(
            "SELECT platform, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE status = 'success' 
             GROUP BY platform 
             ORDER BY count DESC 
             LIMIT 5"
        );
        
        // Popular generators
        $stats['generators'] = $wpdb->get_results(
            "SELECT generator_type, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE status = 'success' 
             GROUP BY generator_type 
             ORDER BY count DESC 
             LIMIT 5"
        );
        
        return $stats;
    }
    
    /**
     * Update generation status
     */
    public function update_status($generation_id, $status, $error_message = null) {
        global $wpdb;
        
        $data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($error_message) {
            $data['error_message'] = $error_message;
        }
        
        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $generation_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get pending generations
     */
    public function get_pending_generations() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'pending' 
             ORDER BY created_at ASC"
        );
    }
    
    /**
     * Cancel generation
     */
    public function cancel_generation($generation_id) {
        return $this->update_status($generation_id, 'cancelled');
    }
    
    /**
     * Check if table exists
     */
    public function table_exists() {
        global $wpdb;
        
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        return $table === $this->table_name;
    }
}