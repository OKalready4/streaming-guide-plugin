<?php
/**
 * Error Handler - Centralized error management
 * 
 * Handles all errors gracefully without breaking the site
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Error_Handler {
    private $log_file;
    private $max_log_size = 5242880; // 5MB
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/streaming-guide-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Add .htaccess to protect logs
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
        
        $this->log_file = $log_dir . '/errors.log';
        
        // Rotate log if too large
        $this->rotate_log_if_needed();
    }
    
    /**
     * Log an error with context
     */
    public function log_error($message, $context = array(), $severity = 'error') {
        $entry = array(
            'timestamp' => current_time('mysql'),
            'severity' => $severity,
            'message' => $message,
            'context' => $context
        );
        
        // Add WordPress context
        $entry['user_id'] = get_current_user_id();
        $entry['url'] = $_SERVER['REQUEST_URI'] ?? '';
        
        // Write to log file
        $log_line = date('Y-m-d H:i:s') . ' [' . strtoupper($severity) . '] ' . $message;
        
        if (!empty($context)) {
            $log_line .= ' | Context: ' . json_encode($context);
        }
        
        $log_line .= PHP_EOL;
        
        error_log($log_line, 3, $this->log_file);
        
        // Also log to WordPress debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Streaming Guide] ' . $log_line);
        }
    }
    
    /**
     * Log API errors with request/response details
     */
    public function log_api_error($api_name, $endpoint, $error, $request_data = null, $response_data = null) {
        $context = array(
            'api' => $api_name,
            'endpoint' => $endpoint,
            'request' => $this->sanitize_api_data($request_data),
            'response' => $this->sanitize_api_data($response_data)
        );
        
        $this->log_error("API Error: {$api_name} - {$error}", $context, 'error');
    }
    
    /**
     * Log generation failures
     */
    public function log_generation_failure($generator_type, $platform, $error, $params = array()) {
        $context = array(
            'generator' => $generator_type,
            'platform' => $platform,
            'params' => $params
        );
        
        $this->log_error("Generation Failed: {$generator_type}/{$platform} - {$error}", $context, 'error');
    }
    
    /**
     * Get recent errors for display
     */
    public function get_recent_errors($limit = 50) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $errors = array();
        
        // Get last N lines
        $recent_lines = array_slice($lines, -$limit);
        
        foreach (array_reverse($recent_lines) as $line) {
            // Parse log line
            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \[([A-Z]+)\] (.+)/', $line, $matches)) {
                $error = array(
                    'timestamp' => $matches[1],
                    'severity' => $matches[2],
                    'message' => $matches[3]
                );
                
                // Extract context if present
                if (strpos($error['message'], ' | Context: ') !== false) {
                    list($message, $context_json) = explode(' | Context: ', $error['message'], 2);
                    $error['message'] = $message;
                    $error['context'] = json_decode($context_json, true);
                }
                
                $errors[] = $error;
            }
        }
        
        return $errors;
    }
    
    /**
     * Get error statistics
     */
    public function get_error_stats($days = 7) {
        $stats = array(
            'total' => 0,
            'by_severity' => array(),
            'by_type' => array(),
            'by_day' => array()
        );
        
        if (!file_exists($this->log_file)) {
            return $stats;
        }
        
        $since = date('Y-m-d', strtotime("-{$days} days"));
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2}) \d{2}:\d{2}:\d{2} \[([A-Z]+)\] (.+)/', $line, $matches)) {
                $date = $matches[1];
                $severity = $matches[2];
                $message = $matches[3];
                
                if ($date < $since) {
                    continue;
                }
                
                $stats['total']++;
                
                // Count by severity
                if (!isset($stats['by_severity'][$severity])) {
                    $stats['by_severity'][$severity] = 0;
                }
                $stats['by_severity'][$severity]++;
                
                // Count by type
                $type = $this->extract_error_type($message);
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type]++;
                
                // Count by day
                if (!isset($stats['by_day'][$date])) {
                    $stats['by_day'][$date] = 0;
                }
                $stats['by_day'][$date]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear error log
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            // Archive current log
            $archive_name = str_replace('.log', '_' . date('Y-m-d_H-i-s') . '.log', $this->log_file);
            rename($this->log_file, $archive_name);
            
            // Create new empty log
            touch($this->log_file);
            
            $this->log_error('Log cleared and archived', array('archive' => basename($archive_name)), 'info');
        }
    }
    
    /**
     * Rotate log if too large
     */
    private function rotate_log_if_needed() {
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->clear_log();
        }
    }
    
    /**
     * Sanitize API data for logging (remove sensitive info)
     */
    private function sanitize_api_data($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $sensitive_keys = array('api_key', 'key', 'token', 'secret', 'password');
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitive_keys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize_api_data($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Extract error type from message
     */
    private function extract_error_type($message) {
        if (strpos($message, 'API Error:') === 0) {
            return 'API Error';
        } elseif (strpos($message, 'Generation Failed:') === 0) {
            return 'Generation Error';
        } elseif (strpos($message, 'Database Error:') === 0) {
            return 'Database Error';
        } else {
            return 'Other';
        }
    }
    
    /**
     * Handle fatal errors gracefully
     */
    public function handle_fatal_error($error) {
        $this->log_error(
            'Fatal Error: ' . $error['message'],
            array(
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ),
            'critical'
        );
        
        // Return user-friendly message
        return __('An unexpected error occurred. The administrator has been notified.', 'streaming-guide');
    }
}