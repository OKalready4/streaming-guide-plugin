<?php
/**
 * Complete TMDB API Class - Fixed and Updated
 *
 * This version uses wp_remote_get() for all API calls and integrates
 * with the centralized error handler for robust logging and debugging.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_TMDB_API {
    private $api_key;
    private $error_handler;
    const API_BASE_URL = 'https://api.themoviedb.org/3';
    
    public function __construct() {
        $this->api_key = get_option('streaming_guide_tmdb_api_key');
        
        // Ensure the error handler is loaded and initialized
        if (!class_exists('Streaming_Guide_Error_Handler')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-error-handler.php';
        }
        $this->error_handler = new Streaming_Guide_Error_Handler();
    }
    
    /**
     * Make API request to TMDB using wp_remote_get for reliability and security.
     */
    public function make_request($endpoint, $params = array()) {
        if (empty($this->api_key)) {
            $this->error_handler->log_api_error('TMDB', $endpoint, 'TMDB API key not configured.');
            return new WP_Error('no_api_key', 'TMDB API key not configured');
        }
        
        // Add required parameters
        $params['api_key'] = $this->api_key;
        $params['language'] = 'en-US';
        
        $url = add_query_arg($params, self::API_BASE_URL . $endpoint);
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ],
            'sslverify' => true,
            'redirection' => 5,
            'blocking' => true
        ]);
        
        // Check for WordPress-level connection errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            
            // Handle specific error cases
            if (strpos($error_message, 'cURL error 28') !== false) {
                $this->error_handler->log_api_error('TMDB', $endpoint, 'Request timed out', $params);
                return new WP_Error('timeout', 'TMDB API request timed out');
            }
            
            if (strpos($error_message, 'SSL certificate') !== false) {
                $this->error_handler->log_api_error('TMDB', $endpoint, 'SSL certificate verification failed', $params);
                return new WP_Error('ssl_error', 'TMDB API SSL certificate verification failed');
            }
            
            $this->error_handler->log_api_error('TMDB', $endpoint, $error_message, $params);
            return $response;
        }

        // Check for API-level errors (e.g., 401, 404, 429)
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['status_message']) ? $error_data['status_message'] : 'Unknown API error';
            
            // Handle rate limiting
            if ($status_code === 429) {
                $retry_after = wp_remote_retrieve_header($response, 'Retry-After');
                $this->error_handler->log_api_error('TMDB', $endpoint, "Rate limit exceeded. Retry after: {$retry_after}", $params);
                return new WP_Error('rate_limit', 'TMDB API rate limit exceeded', ['retry_after' => $retry_after]);
            }
            
            // Handle authentication errors
            if ($status_code === 401) {
                $this->error_handler->log_api_error('TMDB', $endpoint, 'Invalid API key', $params);
                return new WP_Error('auth_error', 'Invalid TMDB API key');
            }
            
            $this->error_handler->log_api_error('TMDB', $endpoint, "{$error_message} (Status: {$status_code})", $params, $error_data);
            return new WP_Error('api_error', $error_message, ['status_code' => $status_code]);
        }
        
        $data = json_decode($body, true);
        
        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error_handler->log_api_error('TMDB', $endpoint, 'Failed to parse JSON response', $params, $body);
            return new WP_Error('json_error', 'Failed to parse TMDB response');
        }
        
        return $data;
    }
    
    /**
     * Discover movies with flexible parameters
     */
    public function discover_movies($params = array()) {
        $default_params = array(
            'sort_by' => 'popularity.desc',
            'page' => 1,
            'include_adult' => false,
            'with_original_language' => 'en'
        );
        $params = array_merge($default_params, $params);
        
        return $this->make_request('/discover/movie', $params);
    }
    
    /**
     * Discover TV shows with flexible parameters  
     */
    public function discover_tv($params = array()) {
        $default_params = array(
            'sort_by' => 'popularity.desc', 
            'page' => 1,
            'include_adult' => false,
            'with_original_language' => 'en'
        );
        $params = array_merge($default_params, $params);
        
        return $this->make_request('/discover/tv', $params);
    }
    
    /**
     * Get watch providers for content - Used by Weekly, Trending
     */
    public function get_watch_providers($content_id, $media_type) {
        try {
            if (empty($content_id) || empty($media_type)) {
                $this->error_handler->log_api_error('TMDB', 'watch/providers', 'Missing required parameters', [
                    'content_id' => $content_id,
                    'media_type' => $media_type
                ]);
                return new WP_Error('invalid_params', 'Missing required parameters for watch providers');
            }
            
            $response = $this->make_request("/{$media_type}/{$content_id}/watch/providers");
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (!isset($response['results'])) {
                $this->error_handler->log_api_error('TMDB', 'watch/providers', 'Invalid response format', [
                    'content_id' => $content_id,
                    'media_type' => $media_type,
                    'response' => $response
                ]);
                return new WP_Error('invalid_response', 'Invalid watch providers response format');
            }
            
            return $response;
            
        } catch (Exception $e) {
            $this->error_handler->log_api_error('TMDB', 'watch/providers', $e->getMessage(), [
                'content_id' => $content_id,
                'media_type' => $media_type
            ]);
            return new WP_Error('exception', 'Error getting watch providers: ' . $e->getMessage());
        }
    }
    
    /**
     * TRENDING METHODS - Used by Multiple Generators
     */
    
    /**
     * Get trending movies (daily or weekly)
     */
    public function get_trending_movies($time_window = 'week') {
        return $this->make_request("/trending/movie/{$time_window}");
    }
    
    /**
     * Get trending TV shows (daily or weekly)
     */
    public function get_trending_tv($time_window = 'week') {
        return $this->make_request("/trending/tv/{$time_window}");
    }
    
    /**
     * PROVIDER/PLATFORM METHODS - Used by Monthly, Top10
     */
    
    /**
     * Get content by provider (streaming platform)
     */
    public function get_content_by_provider($provider_id, $type = 'movie', $page = 1) {
        return $this->make_request("/discover/{$type}", array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'page' => $page,
            'sort_by' => 'popularity.desc',
            'with_original_language' => 'en',  // Focus on English language content
            'vote_count.gte' => 5,            // Basic quality threshold
            'include_adult' => false
        ));
    }
    
    /**
     * Get content by date range (for monthly articles)
     */
    public function get_content_by_date_range($provider_id, $start_date, $end_date, $type = 'movie') {
        $date_param = ($type === 'movie') ? 'primary_release_date' : 'first_air_date';
        
        return $this->make_request("/discover/{$type}", array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            $date_param . '.gte' => $start_date,
            $date_param . '.lte' => $end_date,
            'sort_by' => $date_param . '.desc',
            'with_original_language' => 'en',  // Focus on English language content
            'vote_count.gte' => 10,           // Basic quality threshold
            'include_adult' => false
        ));
    }
    
    /**
     * Get content that's new to a provider (added in the last 7 days)
     */
    public function get_new_on_provider($provider_id, $type = 'movie') {
        // For newer content, get the last 14 days instead of 7 to ensure we have results
        $date_14_days_ago = date('Y-m-d', strtotime('-14 days'));
        $today = date('Y-m-d');
        
        $date_param = ($type === 'movie') ? 'primary_release_date' : 'first_air_date';
        
        return $this->make_request("/discover/{$type}", array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'sort_by' => 'popularity.desc',
            $date_param . '.gte' => $date_14_days_ago,
            $date_param . '.lte' => $today,
            'with_original_language' => 'en',  // Focus on English language content
            'vote_count.gte' => 1,             // Lower threshold for newer content
            'include_adult' => false
        ));
    }
    
    /**
     * Get content rated highly that's available on platform
     */
    public function get_highly_rated_content($provider_id, $type = 'movie', $min_rating = 8.0) {
        return $this->make_request("/discover/{$type}", array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'vote_average.gte' => $min_rating,
            'vote_count.gte' => 30,           // Minimum votes to ensure legitimacy
            'sort_by' => 'vote_average.desc',
            'with_original_language' => 'en', // Focus on English language content
            'include_adult' => false
        ));
    }
    
    /**
     * DETAILS METHODS - Used by All Generators
     */
    
    /**
     * Get movie details including streaming availability
     */
    public function get_movie_details($movie_id) {
        return $this->make_request("/movie/{$movie_id}", array(
            'append_to_response' => 'watch/providers,credits,videos,release_dates'
        ));
    }
    
    /**
     * Get TV show details including streaming availability
     */
    public function get_tv_details($tv_id) {
        return $this->make_request("/tv/{$tv_id}", array(
            'append_to_response' => 'watch/providers,credits,videos,content_ratings'
        ));
    }
    
    /**
     * SEARCH METHODS - Used by Seasonal, Spotlight, Manual Search
     */
    
    /**
     * General search for multiple content types
     */
    public function search($query, $type = 'multi', $page = 1) {
        return $this->make_request("/search/{$type}", array(
            'query' => $query,
            'page' => $page,
            'include_adult' => false,        // Exclude adult content
            'with_original_language' => 'en' // Focus on English language content
        ));
    }
    
    /**
     * Search movies specifically - Used by Seasonal Generator
     */
    public function search_movies($query, $page = 1) {
        return $this->make_request('/search/movie', array(
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
            'with_original_language' => 'en'
        ));
    }
    
    /**
     * Search TV shows specifically - Used by Seasonal Generator
     */
    public function search_tv($query, $page = 1) {
        return $this->make_request('/search/tv', array(
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
            'with_original_language' => 'en'
        ));
    }
    
    /**
     * UTILITY METHODS
     */
    
    /**
     * Get genre list
     */
    public function get_genres($type = 'movie') {
        return $this->make_request("/genre/{$type}/list");
    }
    
    /**
     * Get configuration for image URLs
     */
    public function get_configuration() {
        static $configuration = null;
        
        if ($configuration === null) {
            $configuration = $this->make_request('/configuration');
        }
        
        return $configuration;
    }
    
    /**
     * Get full image URL
     */
    public function get_image_url($path, $size = 'w500') {
        if (empty($path)) {
            return '';
        }
        
        $config = $this->get_configuration();
        if (is_wp_error($config)) {
            return '';
        }
        
        $base_url = $config['images']['secure_base_url'];
        return $base_url . $size . $path;
    }
    
    /**
     * Get YouTube trailer URL from video results
     */
    public function get_trailer_url($video_results, $language = 'en') {
        if (isset($video_results['results']) && is_array($video_results['results'])) {
            // First try to find language-specific trailers
            foreach ($video_results['results'] as $video) {
                if ($video['site'] === 'YouTube' && 
                    ($video['type'] === 'Trailer' || $video['type'] === 'Teaser') &&
                    $video['iso_639_1'] === $language) {
                    return 'https://www.youtube.com/watch?v=' . $video['key'];
                }
            }
            
            // If no language-specific trailer found, fallback to any trailer
            foreach ($video_results['results'] as $video) {
                if ($video['site'] === 'YouTube' && 
                    ($video['type'] === 'Trailer' || $video['type'] === 'Teaser')) {
                    return 'https://www.youtube.com/watch?v=' . $video['key'];
                }
            }
        }
        return '';
    }
}