<?php
/**
 * TMDB API Handler Class
 * 
 * Handles all interactions with The Movie Database API
 * Supports both movies and TV shows with comprehensive error handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class Upcoming_Movies_TMDB_API {
    private $api_key;
    private $base_url = 'https://api.themoviedb.org/3';

    public function __construct() {
        $this->api_key = get_option('upcoming_movies_tmdb_api_key');
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Make API request with error handling
     */
    public function make_request($endpoint, $params = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'TMDB API key not configured');
        }

        $params['api_key'] = $this->api_key;
        $params['language'] = 'en-US';

        $url = $this->base_url . $endpoint . '?' . http_build_query($params);
        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse TMDB response');
        }

        return $data;
    }

    // ========================================================================
    // MOVIE METHODS
    // ========================================================================

    /**
     * Discover movies using TMDB Discover API
     */
    public function discover_movies($params = array()) {
        $default_params = array(
            'language' => 'en-US',
            'page' => 1,
            'region' => 'US',
            'sort_by' => 'popularity.desc',
            'include_adult' => false,
            'watch_region' => 'US'
        );
        
        $params = array_merge($default_params, $params);
        
        return $this->make_request('/discover/movie', $params);
    }

    /**
     * Search movies
     */
    public function search_movies($query, $page = 1) {
        if (empty(trim($query))) {
            return new WP_Error('empty_query', 'Search query cannot be empty');
        }

        return $this->make_request('/search/movie', array(
            'query' => trim($query),
            'page' => max(1, intval($page)),
            'include_adult' => false
        ));
    }

    /**
     * Get comprehensive movie details
     */
    public function get_movie_details($movie_id) {
        if (empty($movie_id) || !is_numeric($movie_id)) {
            return new WP_Error('invalid_movie_id', 'Invalid movie ID provided');
        }

        $endpoint = "/movie/{$movie_id}";
        $params = array(
            'append_to_response' => 'videos,credits,images,release_dates,recommendations,similar',
            'include_image_language' => 'en,null',
        );
        
        return $this->make_request($endpoint, $params);
    }

    // ========================================================================
    // TV SHOW METHODS
    // ========================================================================

    /**
     * Search TV shows
     */
    public function search_tv($query, $page = 1) {
        if (empty(trim($query))) {
            return new WP_Error('empty_query', 'Search query cannot be empty');
        }

        return $this->make_request('/search/tv', array(
            'query' => trim($query),
            'page' => max(1, intval($page)),
            'include_adult' => false
        ));
    }

    /**
     * Multi-search (searches both movies and TV shows)
     */
    public function search_multi($query, $page = 1) {
        if (empty(trim($query))) {
            return new WP_Error('empty_query', 'Search query cannot be empty');
        }

        return $this->make_request('/search/multi', array(
            'query' => trim($query),
            'page' => max(1, intval($page)),
            'include_adult' => false
        ));
    }

    /**
     * Smart search wrapper
     */
    public function smart_search($query, $content_type = 'multi', $page = 1) {
        switch (strtolower($content_type)) {
            case 'movie':
                return $this->search_movies($query, $page);
            case 'tv':
            case 'television':
            case 'show':
                return $this->search_tv($query, $page);
            case 'multi':
            case 'all':
            case 'both':
            default:
                return $this->search_multi($query, $page);
        }
    }

    /**
     * Get TV show details
     */
    public function get_tv_details($tv_id) {
        if (empty($tv_id) || !is_numeric($tv_id)) {
            return new WP_Error('invalid_tv_id', 'Invalid TV show ID provided');
        }

        $endpoint = "/tv/{$tv_id}";
        $params = array(
            'append_to_response' => 'videos,credits,images,content_ratings,recommendations,similar',
            'include_image_language' => 'en,null',
        );
        
        return $this->make_request($endpoint, $params);
    }

    /**
     * Discover TV shows
     */
    public function discover_tv($params = array()) {
        $default_params = array(
            'language' => 'en-US',
            'page' => 1,
            'sort_by' => 'popularity.desc',
            'include_adult' => false,
            'watch_region' => 'US'
        );
        
        $params = array_merge($default_params, $params);
        
        return $this->make_request('/discover/tv', $params);
    }

    // ========================================================================
    // UNIFIED CONTENT METHODS
    // ========================================================================

    /**
     * Detect content type from search result
     */
    public function detect_content_type_from_result($result) {
        if (isset($result['media_type'])) {
            return $result['media_type'] === 'tv' ? 'tv' : 'movie';
        }
        
        if (isset($result['name']) || isset($result['first_air_date']) || isset($result['original_name'])) {
            return 'tv';
        }
        
        if (isset($result['title']) || isset($result['release_date']) || isset($result['original_title'])) {
            return 'movie';
        }
        
        return 'movie';
    }

    /**
     * Get content details regardless of type
     */
    public function get_content_details($content_id, $content_type = null) {
        if (empty($content_id) || !is_numeric($content_id)) {
            return new WP_Error('invalid_content_id', 'Invalid content ID provided');
        }

        if (!$content_type) {
            // Auto-detect by trying both
            $movie_details = $this->get_movie_details($content_id);
            if (!is_wp_error($movie_details) && !empty($movie_details) && isset($movie_details['title'])) {
                return array(
                    'data' => $movie_details,
                    'type' => 'movie'
                );
            }
            
            $tv_details = $this->get_tv_details($content_id);
            if (!is_wp_error($tv_details) && !empty($tv_details) && isset($tv_details['name'])) {
                return array(
                    'data' => $tv_details,
                    'type' => 'tv'
                );
            }
            
            return new WP_Error('content_not_found', 'Content not found as movie or TV show');
        }
        
        if ($content_type === 'tv') {
            $details = $this->get_tv_details($content_id);
        } else {
            $details = $this->get_movie_details($content_id);
        }
        
        if (is_wp_error($details)) {
            return $details;
        }
        
        return array(
            'data' => $details,
            'type' => $content_type
        );
    }

    // ========================================================================
    // STREAMING PLATFORM METHODS
    // ========================================================================

    /**
     * Get streaming provider ID for platform name
     */
    public function get_provider_id($platform_name) {
        $provider_map = array(
            'netflix' => 8,
            'disney+' => 337,
            'disney plus' => 337,
            'max' => 1899,
            'hbo max' => 1899,
            'prime video' => 9,
            'amazon prime' => 9,
            'apple tv+' => 350,
            'apple tv plus' => 350,
            'paramount+' => 531,
            'paramount plus' => 531,
            'hulu' => 15,
            'theatrical release' => null,
            'theaters' => null,
            'cinema' => null
        );
        
        $key = strtolower(trim($platform_name));
        return isset($provider_map[$key]) ? $provider_map[$key] : null;
    }

    /**
     * Convert provider ID back to platform name
     */
    public function convert_provider_id_to_name($provider_id) {
        $provider_map = array(
            8 => 'Netflix',
            337 => 'Disney+',
            1899 => 'Max',
            9 => 'Prime Video',
            350 => 'Apple TV+',
            531 => 'Paramount+',
            15 => 'Hulu'
        );
        
        return isset($provider_map[$provider_id]) ? $provider_map[$provider_id] : 'Unknown Platform';
    }

    /**
     * Build discover parameters for both movies and TV shows
     */
    public function build_discover_params($platform, $type, $content_format = 'movie') {
        $params = array();
        $current_year = date('Y');
        $next_year = $current_year + 1;
        
        $provider_id = $this->get_provider_id($platform);
        
        if ($provider_id && $platform !== 'Theatrical Release') {
            $params['with_watch_providers'] = $provider_id;
            $params['watch_region'] = 'US';
        }
        
        $date_field = ($content_format === 'tv') ? 'first_air_date' : 'primary_release_date';
        
        switch ($type) {
            case 'popular':
                $params['sort_by'] = 'popularity.desc';
                $params[$date_field . '.gte'] = ($current_year - 3) . '-01-01';
                $params[$date_field . '.lte'] = $next_year . '-12-31';
                if ($content_format === 'movie') {
                    $params['vote_count.gte'] = 50;
                }
                break;
                
            case 'upcoming':
                $params['sort_by'] = 'popularity.desc';
                $params[$date_field . '.gte'] = date('Y-m-d');
                $params[$date_field . '.lte'] = ($next_year + 1) . '-12-31';
                break;
                
            case 'now_playing':
                $params['sort_by'] = 'popularity.desc';
                if ($content_format === 'tv') {
                    $params[$date_field . '.gte'] = date('Y-m-d', strtotime('-30 days'));
                    $params[$date_field . '.lte'] = date('Y-m-d', strtotime('+30 days'));
                } else {
                    $params[$date_field . '.gte'] = date('Y-m-d', strtotime('-90 days'));
                    $params[$date_field . '.lte'] = date('Y-m-d', strtotime('+90 days'));
                }
                break;
                
            case 'top_rated':
                $params['sort_by'] = 'vote_average.desc';
                $params['vote_count.gte'] = ($content_format === 'tv') ? 50 : 100;
                $params[$date_field . '.gte'] = ($current_year - 10) . '-01-01';
                break;
                
            default:
                $params['sort_by'] = 'popularity.desc';
                $params[$date_field . '.gte'] = ($current_year - 5) . '-01-01';
                $params['vote_count.gte'] = 10;
        }
        
        $params['include_adult'] = false;
        $params['language'] = 'en-US';
        $params['page'] = 1;
        
        return $params;
    }

    // ========================================================================
    // TRENDING AND POPULAR CONTENT
    // ========================================================================

    /**
     * Get trending content
     */
    public function get_trending($media_type = 'all', $time_window = 'week') {
        $valid_media_types = array('all', 'movie', 'tv', 'person');
        $valid_time_windows = array('day', 'week');
        
        if (!in_array($media_type, $valid_media_types)) {
            $media_type = 'all';
        }
        
        if (!in_array($time_window, $valid_time_windows)) {
            $time_window = 'week';
        }
        
        $endpoint = "/trending/{$media_type}/{$time_window}";
        return $this->make_request($endpoint, array(
            'language' => 'en-US'
        ));
    }

    /**
     * Get popular TV shows
     */
    public function get_popular_tv($page = 1) {
        return $this->make_request('/tv/popular', array(
            'page' => max(1, intval($page)),
            'language' => 'en-US'
        ));
    }

    /**
     * Get top-rated TV shows
     */
    public function get_top_rated_tv($page = 1) {
        return $this->make_request('/tv/top_rated', array(
            'page' => max(1, intval($page)),
            'language' => 'en-US'
        ));
    }

    // ========================================================================
    // VALIDATION AND UTILITY METHODS
    // ========================================================================

    /**
     * Validate TMDB ID exists and get its type
     */
    public function validate_tmdb_id($tmdb_id) {
        if (empty($tmdb_id) || !is_numeric($tmdb_id)) {
            return new WP_Error('invalid_id', 'Invalid TMDB ID format');
        }

        // Try as movie first
        $movie_details = $this->get_movie_details($tmdb_id);
        if (!is_wp_error($movie_details) && !empty($movie_details) && isset($movie_details['title'])) {
            return array(
                'exists' => true,
                'type' => 'movie',
                'title' => $movie_details['title'],
                'data' => $movie_details
            );
        }
        
        // Try as TV show
        $tv_details = $this->get_tv_details($tmdb_id);
        if (!is_wp_error($tv_details) && !empty($tv_details) && isset($tv_details['name'])) {
            return array(
                'exists' => true,
                'type' => 'tv',
                'title' => $tv_details['name'],
                'data' => $tv_details
            );
        }
        
        return array(
            'exists' => false,
            'type' => null,
            'title' => null,
            'data' => null
        );
    }

    /**
     * Get comprehensive content data with fallbacks
     */
    public function get_comprehensive_content_data($content_id, $preferred_type = null) {
        $validation = $this->validate_tmdb_id($content_id);
        
        if (!$validation['exists']) {
            return new WP_Error('content_not_found', 'Content not found in TMDB database');
        }
        
        if ($preferred_type && $validation['type'] === $preferred_type) {
            return array(
                'data' => $validation['data'],
                'type' => $validation['type'],
                'title' => $validation['title']
            );
        }
        
        return array(
            'data' => $validation['data'],
            'type' => $validation['type'],
            'title' => $validation['title']
        );
    }
}