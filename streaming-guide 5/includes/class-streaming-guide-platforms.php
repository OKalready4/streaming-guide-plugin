<?php
/**
 * Platform definitions for Streaming Guide
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Platforms {
    
    /**
     * Get all platform definitions
     * 
     * @return array Platform definitions with keys, names, and TMDB IDs
     */
    public static function get_platforms() {
        return array(
            'netflix' => array('name' => 'Netflix', 'id' => 8),
            'hulu' => array('name' => 'Hulu', 'id' => 15),
            'disney' => array('name' => 'Disney+', 'id' => 337),
            'hbo' => array('name' => 'HBO Max', 'id' => 1899),
            'amazon' => array('name' => 'Amazon Prime Video', 'id' => 9),   // Changed from 'amazonprime'
            'paramount' => array('name' => 'Paramount+', 'id' => 531),
            'apple' => array('name' => 'Apple TV+', 'id' => 350)            // Changed from 'appletv'
        );
    }
    
    /**
     * Get platform display names
     * 
     * @return array Platform keys and display names
     */
    public static function get_platform_names() {
        $result = array();
        foreach (self::get_platforms() as $key => $data) {
            $result[$key] = $data['name'];
        }
        return $result;
    }
    
    /**
     * Get TMDB provider ID for a platform
     * 
     * @param string $platform Platform key
     * @return int|null TMDB provider ID or null if not found
     */
    public static function get_provider_id($platform) {
        $platforms = self::get_platforms();
        return isset($platforms[$platform]) ? $platforms[$platform]['id'] : null;
    }
    
    /**
     * Get platform name from key
     * 
     * @param string $platform Platform key
     * @return string Platform display name
     */
    public static function get_platform_name($platform) {
        $platforms = self::get_platforms();
        return isset($platforms[$platform]) ? $platforms[$platform]['name'] : ucfirst($platform);
    }
}