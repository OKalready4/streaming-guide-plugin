<?php
/**
 * Error Handler - CRITICAL FIX VERSION
 * 
 * Handles error logging and management for the plugin.
 * FIXED: Simple constructor with no required parameters
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Error_Handler {
    private $log_file;
    private $max_log_size;
    
    /**
     * CRITICAL FIX: Simple constructor with no required parameters
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/streaming-guide-errors.log';
        $this->max_log_size = 10 * 1024 * 1024; // 10MB
        
        // Ensure log file directory exists
        $this->ensure_log_directory();
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'];
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }
    
    /**
     * Log an error message
     */
    public function log_error($message, $context = array(), $level = 'error') {
        $this->rotate_log_if_needed();
        
        $timestamp = current_time('Y-m-d H:i:s');
        $context_string = !empty($context) ? ' | Context: ' . json_encode($this->sanitize_context($context)) : '';
        
        $log_entry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $context_string
        );
        
        // Log to WordPress error log as well
        error_log('Streaming Guide Plugin: ' . $message);
        
        // Log to our custom file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log info message
     */
    public function log_info($message, $context = array()) {
        $this->log_error($message, $context, 'info');
    }
    
    /**
     * Log warning message
     */
    public function log_warning($message, $context = array()) {
        $this->log_error($message, $context, 'warning');
    }
    
    /**
     * Log debug message
     */
    public function log_debug($message, $context = array()) {
        // Only log debug messages if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_error($message, $context, 'debug');
        }
    }
    
    /**
     * Get recent errors
     */
    public function get_recent_errors($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $file_content = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$file_content) {
            return array();
        }
        
        // Return last N lines
        return array_slice($file_content, -$lines);
    }
    
    /**
     * Clear error log
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            // Archive current log before clearing
            $archive_name = str_replace('.log', '_' . date('Y-m-d_H-i-s') . '.log', $this->log_file);
            rename($this->log_file, $archive_name);
            
            // Create new empty log
            touch($this->log_file);
            
            $this->log_info('Log cleared and archived', array('archive' => basename($archive_name)));
        }
    }
    
    /**
     * Get log file size
     */
    public function get_log_size() {
        if (file_exists($this->log_file)) {
            return filesize($this->log_file);
        }
        return 0;
    }
    
    /**
     * Check if log file exists
     */
    public function log_exists() {
        return file_exists($this->log_file);
    }
    
    /**
     * Rotate log if it's too large
     */
    private function rotate_log_if_needed() {
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->clear_log();
        }
    }
    
    /**
     * Sanitize context data for logging
     */
    private function sanitize_context($context) {
        if (!is_array($context)) {
            return $context;
        }
        
        $sensitive_keys = array('api_key', 'key', 'token', 'secret', 'password', 'pass');
        
        foreach ($context as $key => $value) {
            if (in_array(strtolower($key), $sensitive_keys)) {
                $context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $context[$key] = $this->sanitize_context($value);
            }
        }
        
        return $context;
    }
    
    /**
     * Get error statistics
     */
    public function get_error_stats() {
        $errors = $this->get_recent_errors(1000);
        
        $stats = array(
            'total' => count($errors),
            'levels' => array(
                'error' => 0,
                'warning' => 0,
                'info' => 0,
                'debug' => 0
            ),
            'recent' => array()
        );
        
        foreach ($errors as $error) {
            // Extract log level
            if (preg_match('/\[(ERROR|WARNING|INFO|DEBUG)\]/', $error, $matches)) {
                $level = strtolower($matches[1]);
                if (isset($stats['levels'][$level])) {
                    $stats['levels'][$level]++;
                }
            }
            
            // Get recent errors (last 10)
            if (count($stats['recent']) < 10) {
                $stats['recent'][] = $error;
            }
        }
        
        return $stats;
    }
    
    /**
     * Handle fatal errors
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
        
        return __('An unexpected error occurred. The administrator has been notified.', 'streaming-guide');
    }
    
    /**
     * Log API errors specifically
     */
    public function log_api_error($api_name, $endpoint, $response_code, $error_message, $context = array()) {
        $this->log_error(
            sprintf('API Error [%s]: %s (HTTP %d)', $api_name, $error_message, $response_code),
            array_merge($context, array(
                'api' => $api_name,
                'endpoint' => $endpoint,
                'response_code' => $response_code
            ))
        );
    }
    
    /**
     * Log generation errors
     */
    public function log_generation_error($generator_type, $platform, $error_message, $context = array()) {
        $this->log_error(
            sprintf('Generation Error [%s/%s]: %s', $generator_type, $platform, $error_message),
            array_merge($context, array(
                'generator_type' => $generator_type,
                'platform' => $platform
            ))
        );
    }
    
    /**
     * Get log file path (for admin display)
     */
    public function get_log_file_path() {
        return $this->log_file;
    }
}