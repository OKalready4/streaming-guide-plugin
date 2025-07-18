<?php
/**
 * Plugin Name: Upcoming Movies Feature - COMPLETE FIXED VERSION
 * Description: Complete streaming website system with TMDB integration, AI content generation, and comprehensive movie management
 * Version: 5.0.0
 * Author: Streaming Websites
 * Text Domain: upcoming-movies
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UPCOMING_MOVIES_VERSION', '5.0.0');
define('UPCOMING_MOVIES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UPCOMING_MOVIES_PLUGIN_URL', plugin_dir_url(__FILE__));

class Upcoming_Movies_Feature {
    private static $instance = null;
    private $tmdb_api = null;
    private $openai_api = null;
    private $admin_handler = null;
    private $frontend_handler = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor - initialize plugin
     */
    private function __construct() {
        $this->init_plugin();
    }

    /**
     * Initialize the plugin
     */
    private function init_plugin() {
        // Include required WordPress files
        $this->include_wordpress_files();
        
        // Include our class files
        $this->include_class_files();
        
        // Initialize core hooks EARLY
        $this->init_core_hooks();
        
        // Initialize classes
        $this->init_classes();
        
        // Setup theme compatibility
        $this->setup_theme_compatibility();

        // Initialize admin dashboard improvements
        $this->init_admin_improvements();
    }

    /**
     * Include required WordPress files
     */
    private function include_wordpress_files() {
        if (!function_exists('wp_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
    }

    /**
     * Include our class files
     */
    private function include_class_files() {
        // Include API classes
        require_once UPCOMING_MOVIES_PLUGIN_DIR . 'includes/class-tmdb-api.php';
        require_once UPCOMING_MOVIES_PLUGIN_DIR . 'includes/class-openai-api.php';
        
        // Include handler classes
        require_once UPCOMING_MOVIES_PLUGIN_DIR . 'includes/class-admin.php';
        require_once UPCOMING_MOVIES_PLUGIN_DIR . 'includes/class-frontend.php';
    }

    /**
     * FIXED: Initialize core hooks with proper order
     */
    private function init_core_hooks() {
        // CRITICAL: Register taxonomy FIRST, but check ACF availability
        add_action('init', array($this, 'register_streamer_taxonomy'), 1);
        
        // Then flush rewrite rules if needed
        add_action('init', array($this, 'check_and_flush_rewrite_rules'), 5);
        
        // Auto-run ACF cleanup
        add_action('init', array($this, 'auto_run_acf_cleanup'), 3);
        
        // Query integration
        add_action('pre_get_posts', array($this, 'integrate_movies_in_queries'));
        
        // FIXED: Template loading with higher priority
        add_filter('template_include', array($this, 'load_movie_template'), 99);
        
        // Theme compatibility
        add_filter('blocksy:post-types:archive-support', array($this, 'add_blocksy_archive_support'));
        add_filter('blocksy:post-types:single-support', array($this, 'add_blocksy_single_support'));
        add_filter('indexnow_post_types', array($this, 'add_indexnow_support'));
        add_filter('post_class', array($this, 'add_movie_post_classes'), 10, 3);
        
        // Post deletion safety - runs on ALL post deletions, not just from our admin pages
        add_action('before_delete_post', array($this, 'cleanup_movie_data_on_deletion'), 10);
        add_action('wp_trash_post', array($this, 'cleanup_movie_data_on_trash'), 10);
        
        // FIXED: Add proper taxonomy URL handling
        add_action('template_redirect', array($this, 'handle_streamer_redirects'));

        // FIXED: Remove trailer autoplay
        add_filter('oembed_result', array($this, 'fix_youtube_autoplay'), 10, 3);
        add_filter('the_content', array($this, 'clean_autoplay_from_content'));

        // Admin improvements
        add_filter('manage_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_posts_custom_column', array($this, 'populate_admin_columns'), 10, 2);
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }

    /**
     * FIXED: Register streamer taxonomy with ACF integration
     */
    public function register_streamer_taxonomy() {
        // Check if ACF is active and has streaming platform field
        if (function_exists('get_field_object')) {
            $acf_field = get_field_object('streaming_platform');
            if ($acf_field) {
                error_log('Upcoming Movies: ACF streaming platform field detected - using ACF instead of taxonomy');
                // Don't register taxonomy, use ACF field instead
                return;
            }
        }
        
        // Only register taxonomy if ACF field doesn't exist
        $labels = array(
            'name'              => _x('Streaming Platforms', 'taxonomy general name', 'upcoming-movies'),
            'singular_name'     => _x('Streaming Platform', 'taxonomy singular name', 'upcoming-movies'),
            'search_items'      => __('Search Platforms', 'upcoming-movies'),
            'all_items'         => __('All Platforms', 'upcoming-movies'),
            'edit_item'         => __('Edit Platform', 'upcoming-movies'),
            'update_item'       => __('Update Platform', 'upcoming-movies'),
            'add_new_item'      => __('Add New Platform', 'upcoming-movies'),
            'new_item_name'     => __('New Platform Name', 'upcoming-movies'),
            'menu_name'         => __('Platforms', 'upcoming-movies'),
        );

        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
            'rest_base'                  => 'streamers',
            'query_var'                  => 'streamer',
            'rewrite'                    => array(
                'slug'                   => '',
                'with_front'             => false,
                'hierarchical'           => false,
            ),
        );

        register_taxonomy('streamer', array('post'), $args);
        error_log('Upcoming Movies: Fallback streamer taxonomy registered');
    }

    /**
     * Auto-run ACF cleanup
     */
    public function auto_run_acf_cleanup() {
        if (is_admin() && current_user_can('manage_options')) {
            $acf_cleanup_version = get_option('upcoming_movies_acf_cleanup_version');
            if ($acf_cleanup_version !== '5.0.0') {
                $this->emergency_acf_cleanup();
                update_option('upcoming_movies_acf_cleanup_version', '5.0.0');
            }
        }
    }

    /**
     * Initialize API and handler classes
     */
    private function init_classes() {
        // Initialize API classes
        $this->tmdb_api = new Upcoming_Movies_TMDB_API();
        $this->openai_api = new Upcoming_Movies_OpenAI_API();
        
        // Initialize handler classes - they register their own hooks
        $this->admin_handler = new Upcoming_Movies_Admin($this);
        $this->frontend_handler = new Upcoming_Movies_Frontend($this);
        
        // Initialize Image Fetcher utility
        if (is_admin()) {
            if (file_exists(UPCOMING_MOVIES_PLUGIN_DIR . 'includes/class-image-fetcher.php')) {
                require_once UPCOMING_MOVIES_PLUGIN_DIR . 'includes/class-image-fetcher.php';
                new Upcoming_Movies_Image_Fetcher($this->tmdb_api);
            }
        }
    }

    /**
     * Initialize admin dashboard improvements
     */
    private function init_admin_improvements() {
        if (is_admin()) {
            // Clean up unused tools from dashboard
            add_action('admin_init', array($this, 'cleanup_admin_dashboard'));
            
            // Fix Movies dashboard issues
            add_action('admin_menu', array($this, 'fix_movies_dashboard'), 99);
        }
    }

    /**
     * FIXED: Clean up admin dashboard - remove/fix broken tools
     */
    public function cleanup_admin_dashboard() {
        // Remove broken database tools that aren't working
        if (isset($_GET['page']) && $_GET['page'] === 'upcoming-movies') {
            // Check if we have database issues that can't be auto-fixed
            global $wpdb;
            $orphaned_images = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_type = 'attachment' 
                 AND post_parent NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post')"
            );
            
            // If too many orphaned images, don't show the cleanup tool
            if ($orphaned_images > 1000) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning">';
                    echo '<p><strong>Movies Plugin:</strong> Large cleanup needed. Contact support for database optimization.</p>';
                    echo '</div>';
                });
            }
        }
    }

    /**
     * FIXED: Fix Movies dashboard page
     */
    public function fix_movies_dashboard() {
        // Make sure the Movies menu shows correct counts
        global $menu, $submenu;
        
        if (isset($menu)) {
            foreach ($menu as $key => $item) {
                if ($item[2] === 'upcoming-movies') {
                    // Get actual movie count
                    global $wpdb;
                    $movie_count = $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                         WHERE p.post_type = 'post'
                         AND p.post_status = 'publish'
                         AND pm.meta_key = 'is_movie_post'
                         AND pm.meta_value = '1'"
                    );
                    
                    if ($movie_count > 0) {
                        $menu[$key][0] = 'Movies <span class="awaiting-mod">' . $movie_count . '</span>';
                    }
                }
            }
        }
    }

    /**
     * Check and flush rewrite rules if needed
     */
    public function check_and_flush_rewrite_rules() {
        $version = get_option('upcoming_movies_rewrite_version');
        $flush_needed = get_option('upcoming_movies_flush_needed');
        
        if ($version != UPCOMING_MOVIES_VERSION || $flush_needed) {
            flush_rewrite_rules();
            update_option('upcoming_movies_rewrite_version', UPCOMING_MOVIES_VERSION);
            delete_option('upcoming_movies_flush_needed');
            error_log('Upcoming Movies: Rewrite rules flushed for version ' . UPCOMING_MOVIES_VERSION);
        }
    }

    /**
     * Setup theme compatibility
     */
    private function setup_theme_compatibility() {
        // Add theme support
        add_theme_support('post-thumbnails');
        
        // Blocksy theme specific compatibility
        add_filter('blocksy:post-types:support', function($post_types) {
            if (!in_array('upcoming_movie', $post_types)) {
                $post_types[] = 'upcoming_movie';
            }
            return $post_types;
        });
    }

    /**
     * Integrate movies in main queries
     */
    public function integrate_movies_in_queries($query) {
        // Don't modify admin queries or if explicitly querying specific post types
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Don't modify if post type is already set
        if ($query->get('post_type')) {
            return;
        }

        // Add movies to home, archive, and search pages
        if ($query->is_home() || $query->is_archive() || $query->is_search()) {
            $post_types = array('post');
            
            // Check for movie posts
            global $wpdb;
            $has_movies = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'post'
                 AND p.post_status = 'publish'
                 AND pm.meta_key = 'is_movie_post'
                 AND pm.meta_value = '1'"
            );
            
            if ($has_movies > 0) {
                // Movies are regular posts with is_movie_post flag
                $query->set('post_type', 'post');
            }
        }
    }

    /**
     * FIXED: Load movie template with ACF integration
     */
    public function load_movie_template($template) {
        // Check if this is a single post with movie flag
        if (is_singular('post')) {
            $post_id = get_the_ID();
            $is_movie_post = get_post_meta($post_id, 'is_movie_post', true);
            
            if ($is_movie_post) {
                // Look for template in theme first
                $theme_template = locate_template('single-movie.php');
                if ($theme_template) {
                    return $theme_template;
                }
                
                // Use our plugin template
                $plugin_template = UPCOMING_MOVIES_PLUGIN_DIR . 'templates/single-upcoming_movie.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }
        
        return $template;
    }
    
    /**
     * CRITICAL: Add missing discover_movies method for Mass Producer
     */
    public function discover_movies($discover_type = 'popular', $target_platform = '') {
        if (!$this->tmdb_api) {
            return new WP_Error('no_tmdb', 'TMDB API not initialized');
        }

        // Map discover types to TMDB endpoints
        $endpoint_map = array(
            'popular' => 'movie/popular',
            'upcoming' => 'movie/upcoming',
            'now_playing' => 'movie/now_playing',
            'top_rated' => 'movie/top_rated'
        );

        if (!isset($endpoint_map[$discover_type])) {
            return new WP_Error('invalid_type', 'Invalid discover type: ' . $discover_type);
        }

        $endpoint = $endpoint_map[$discover_type];
        
        // Make API call to TMDB
        $api_key = get_option('upcoming_movies_tmdb_api_key');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'TMDB API key not configured');
        }

        $url = "https://api.themoviedb.org/3/{$endpoint}?api_key={$api_key}&language=en-US&page=1";
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['results'])) {
            return new WP_Error('api_error', 'Invalid API response from TMDB');
        }

        error_log("TMDB API Success: Found " . count($data['results']) . " movies for type: {$discover_type}");
        
        return $data;
    }

    /**
     * CRITICAL: Add bulk movie creation method for Mass Producer
     */
    public function create_bulk_movies($tmdb_ids, $platform = '', $content_type = 'movie') {
        if (!is_array($tmdb_ids)) {
            $tmdb_ids = array($tmdb_ids);
        }

        $results = array();
        $success_count = 0;
        $error_count = 0;

        foreach ($tmdb_ids as $tmdb_id) {
            if (empty($tmdb_id)) {
                continue;
            }

            // Check if movie already exists
            if ($this->movie_exists_robust($tmdb_id)) {
                $results[] = array(
                    'tmdb_id' => $tmdb_id,
                    'status' => 'skipped',
                    'message' => 'Movie already exists',
                    'post_id' => $this->get_existing_movie_post_id($tmdb_id)
                );
                continue;
            }

            // Create the movie
            $post_id = $this->create_upcoming_movie($tmdb_id, $platform, $content_type);

            if (is_wp_error($post_id)) {
                $results[] = array(
                    'tmdb_id' => $tmdb_id,
                    'status' => 'error',
                    'message' => $post_id->get_error_message(),
                    'post_id' => null
                );
                $error_count++;
            } else {
                $results[] = array(
                    'tmdb_id' => $tmdb_id,
                    'status' => 'success',
                    'message' => 'Movie created successfully',
                    'post_id' => $post_id
                );
                $success_count++;
            }

            // Small delay to prevent API rate limiting
            usleep(250000); // 0.25 seconds
        }

        error_log("Bulk creation completed: {$success_count} success, {$error_count} errors");

        return array(
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'total_processed' => count($tmdb_ids)
        );
    }

    /**
     * CRITICAL: Add method to process direct TMDB IDs
     */
    public function process_direct_tmdb_ids($tmdb_ids_string, $platform = '', $content_type = 'movie') {
        // Parse the input string
        $tmdb_ids = array();
        
        // Split by common separators
        $raw_ids = preg_split('/[,\s\n\r]+/', trim($tmdb_ids_string));
        
        foreach ($raw_ids as $id) {
            $id = trim($id);
            if (is_numeric($id) && $id > 0) {
                $tmdb_ids[] = intval($id);
            }
        }

        if (empty($tmdb_ids)) {
            return new WP_Error('no_valid_ids', 'No valid TMDB IDs provided');
        }

        error_log("Processing direct TMDB IDs: " . implode(', ', $tmdb_ids));

        return $this->create_bulk_movies($tmdb_ids, $platform, $content_type);
    }

    /**
     * Get streaming platform logo URL
     */
    public function get_streaming_platform_logo($platform) {
        $platform_logos = array(
            'netflix' => 'netflix.png',
            'disney+' => 'disney.png',
            'disney plus' => 'disney.png',
            'hbo max' => 'max.png',
            'max' => 'max.png',
            'prime video' => 'primevideo.png',
            'amazon prime' => 'primevideo.png',
            'apple tv+' => 'appletv.png',
            'apple tv plus' => 'appletv.png',
            'paramount+' => 'paramount.png',
            'paramount plus' => 'paramount.png',
            'hulu' => 'hulu.png',
            'peacock' => 'peacock.png',
            'theatrical release' => 'theatrical.png',
            'theaters' => 'theatrical.png'
        );
        
        $platform_lower = strtolower(trim($platform));
        
        if (isset($platform_logos[$platform_lower])) {
            $logo_file = $platform_logos[$platform_lower];
            
            // Check in Streaming Guide Feature plugin first
            $streaming_guide_path = WP_PLUGIN_DIR . '/streaming-guide-feature/assets/images/' . $logo_file;
            if (file_exists($streaming_guide_path)) {
                return plugins_url('streaming-guide-feature/assets/images/' . $logo_file);
            }
            
            // Then check current plugin
            $plugin_path = UPCOMING_MOVIES_PLUGIN_DIR . 'assets/images/' . $logo_file;
            if (file_exists($plugin_path)) {
                return UPCOMING_MOVIES_PLUGIN_URL . 'assets/images/' . $logo_file;
            }
        }
        
        return '';
    }
    
    /**
     * FIXED: Handle streamer URL redirects with ACF integration
     */
    public function handle_streamer_redirects() {
        global $wp_query;
        
        // Check if we're on a potential streamer page
        $request_uri = trim($_SERVER['REQUEST_URI'], '/');
        $path_parts = explode('/', $request_uri);
        
        // List of known streamers
        $known_streamers = array(
            'netflix' => 'Netflix',
            'disney' => 'Disney+',
            'disney+' => 'Disney+',
            'max' => 'Max',
            'hbo-max' => 'Max',
            'prime-video' => 'Prime Video',
            'amazon-prime' => 'Prime Video',
            'apple-tv' => 'Apple TV+',
            'apple-tv+' => 'Apple TV+',
            'paramount+' => 'Paramount+',
            'paramount-plus' => 'Paramount+',
            'hulu' => 'Hulu',
            'peacock' => 'Peacock',
            'theatrical-release' => 'Theatrical Release',
            'theaters' => 'Theatrical Release'
        );
        
        // If the first path part matches a streamer
        if (!empty($path_parts[0]) && array_key_exists(strtolower($path_parts[0]), $known_streamers)) {
            $streamer_slug = strtolower($path_parts[0]);
            $streamer_name = $known_streamers[$streamer_slug];
            
            error_log("Looking for {$streamer_name} content...");
            
            // FIXED: Build meta query for ACF or regular meta fields
            $meta_query = array(
                'relation' => 'AND',
                array(
                    'key'     => 'is_movie_post',
                    'value'   => '1',
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'streaming_platform',
                        'value'   => $streamer_name,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'streaming_platform',
                        'value'   => $streamer_name,
                        'compare' => 'LIKE'
                    )
                )
            );
            
            // Set up the query
            $wp_query = new WP_Query(array(
                'post_type' => 'post',
                'posts_per_page' => 10,
                'meta_query' => $meta_query,
                'post_status' => 'publish'
            ));
            
            error_log("Found " . $wp_query->found_posts . " posts for {$streamer_name}");
            
            // Set query vars for template
            set_query_var('streamer_name', $streamer_name);
            set_query_var('streamer_slug', $streamer_slug);
            
            // Load archive template
            $template = locate_template('archive-streamer.php');
            if (!$template) {
                $template = UPCOMING_MOVIES_PLUGIN_DIR . 'templates/archive-streamer.php';
            }
            
            if (file_exists($template)) {
                include $template;
                exit;
            }
        }
    }

    /**
     * FIXED: Remove autoplay from YouTube embeds
     */
    public function fix_youtube_autoplay($html, $url, $args) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            // Remove any autoplay parameters
            $html = preg_replace('/(\?|&)autoplay=1/', '', $html);
            $html = preg_replace('/autoplay=1(&|$)/', '', $html);
            
            // Ensure no autoplay
            if (strpos($html, 'autoplay') === false) {
                $html = str_replace('?', '?autoplay=0&', $html);
            }
            
            // Add rel=0 for cleaner suggestions
            if (strpos($html, 'rel=0') === false) {
                $html = str_replace('?autoplay=0&', '?autoplay=0&rel=0&', $html);
            }
        }
        
        return $html;
    }

    /**
     * FIXED: Clean autoplay from existing content
     */
    public function clean_autoplay_from_content($content) {
        // Remove autoplay from any existing embeds
        $content = preg_replace('/autoplay=1[&]?/', 'autoplay=0&', $content);
        return $content;
    }

    /**
     * Add admin columns
     */
    public function add_admin_columns($columns) {
        if (get_post_type() === 'post') {
            $columns['streaming_platform'] = 'Streaming Platform';
            $columns['movie_type'] = 'Type';
        }
        return $columns;
    }

    /**
     * Populate admin columns
     */
    public function populate_admin_columns($column, $post_id) {
        if ($column === 'streaming_platform') {
            $is_movie = get_post_meta($post_id, 'is_movie_post', true);
            if ($is_movie) {
                $platform = $this->get_post_streaming_platform($post_id);
                if ($platform) {
                    echo esc_html($platform);
                } else {
                    echo '<em>Not set</em>';
                }
            } else {
                echo '—';
            }
        } elseif ($column === 'movie_type') {
            $is_movie = get_post_meta($post_id, 'is_movie_post', true);
            if ($is_movie) {
                $content_type = get_post_meta($post_id, 'content_type', true);
                echo '<span class="post-type-badge ' . esc_attr($content_type) . '">' . 
                     esc_html(ucfirst($content_type ?: 'movie')) . '</span>';
            } else {
                echo '<span class="post-type-badge post">Post</span>';
            }
        }
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if (current_user_can('manage_options')) {
            $acf_cleanup_version = get_option('upcoming_movies_acf_cleanup_version');
            if ($acf_cleanup_version === '5.0.0') {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Movies Plugin:</strong> ACF integration and platform assignments have been fixed!</p>';
                echo '</div>';
                
                // Clear the notice after showing it
                delete_option('upcoming_movies_acf_cleanup_version');
                update_option('upcoming_movies_acf_cleanup_done', '5.0.0');
            }
            
            // Check if ACF is active
            if (!function_exists('get_field')) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>Movies Plugin:</strong> ACF not detected. Platform assignments will use meta fields.</p>';
                echo '</div>';
            }
        }
    }

    /**
     * FIXED: Create a regular post with proper ACF field integration
     */
    public function create_upcoming_movie($tmdb_id, $platform_override = '', $content_type = 'movie') {
        // Check for existing movie
        if ($this->movie_exists_robust($tmdb_id)) {
            $existing_id = $this->get_existing_movie_post_id($tmdb_id);
            error_log("Movie with TMDB ID {$tmdb_id} already exists as post " . $existing_id);
            return $existing_id;
        }

        // Get content data based on type
        if ($content_type == 'tv') {
            $content_data = $this->tmdb_api->get_tv_details($tmdb_id);
            if (is_wp_error($content_data)) {
                return $content_data;
            }
            $content_data['is_tv_show'] = true;
        } else {
            $content_data = $this->tmdb_api->get_movie_details($tmdb_id);
            if (is_wp_error($content_data)) {
                return $content_data;
            }
            $content_data['is_tv_show'] = false;
        }

        // Generate article content with improved variation
        $content = $this->generate_enhanced_article_with_ai($content_data);
        if (is_wp_error($content) || empty($content)) {
            $content = $this->generate_enhanced_fallback_article($content_data);
        }

        // Extract data for post creation
        $is_tv_show = $content_data['is_tv_show'];
        $title = $is_tv_show ? $content_data['name'] : $content_data['title'];
        $overview = isset($content_data['overview']) ? $content_data['overview'] : '';
        $release_date = $is_tv_show ? (isset($content_data['first_air_date']) ? $content_data['first_air_date'] : '') : (isset($content_data['release_date']) ? $content_data['release_date'] : '');
        
        // Extract article title from generated content
        $article_title = $this->extract_article_title_from_content($content);
        
        // Create post title
        $release_year = !empty($release_date) ? date('Y', strtotime($release_date)) : '';
        $post_title = !empty($article_title) ? $article_title : ($release_year ? "$title ($release_year)" : $title);
        
        // Create unique slug
        $slug = sanitize_title($title);
        if ($release_year) {
            $slug .= '-' . $release_year;
        }
        $slug = $this->ensure_unique_slug_for_posts($slug);
        
        // Determine streaming platform
        $streaming_platform = !empty($platform_override) ? $platform_override : $this->detect_streaming_platform($content_data);
        
        // Extract metadata
        $genres = array();
        if (!empty($content_data['genres']) && is_array($content_data['genres'])) {
            foreach ($content_data['genres'] as $genre) {
                if (isset($genre['name'])) {
                    $genres[] = sanitize_text_field($genre['name']);
                }
            }
        }
        
        // FIXED: Create proper categories (NO random numbers!)
        $categories = array();
        
        // Always add Movies category
        $movie_category = $this->get_or_create_category('Movies');
        if ($movie_category && is_numeric($movie_category)) {
            $categories[] = $movie_category;
        }
        
        // Add content type category
        if ($is_tv_show) {
            $tv_category = $this->get_or_create_category('TV Shows');
            if ($tv_category && is_numeric($tv_category)) {
                $categories[] = $tv_category;
            }
        }
        
        // FIXED: Create proper tags (NO random numbers!)
        $tags = array();
        $tags[] = 'movie';
        
        if ($is_tv_show) {
            $tags[] = 'tv show';
            $tags[] = 'series';
            $tags[] = 'television';
        } else {
            $tags[] = 'film';
            $tags[] = 'cinema';
        }
        
        if ($streaming_platform) {
            $tags[] = strtolower($streaming_platform);
        }
        
        // Add genre tags
        if (!empty($genres)) {
            foreach (array_slice($genres, 0, 3) as $genre) { // Limit to 3 genres
                $tags[] = strtolower($genre);
            }
        }
        
        // FIXED: Create regular post with proper data
        $post_data = array(
            'post_title'    => $post_title,
            'post_name'     => $slug,
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_type'     => 'post', // Regular post
            'post_excerpt'  => wp_trim_words($overview, 30),
            'post_category' => $categories, // Proper categories
            'tags_input'    => $tags, // Proper tags
            'meta_input'    => array(
                'tmdb_id'            => $tmdb_id,
                'movie_title'        => $title,
                'release_date'       => $release_date,
                'overview'           => $overview,
                'runtime'            => isset($content_data['runtime']) ? $content_data['runtime'] : (isset($content_data['episode_run_time'][0]) ? $content_data['episode_run_time'][0] : 0),
                'genres'             => implode(', ', $genres),
                'maturity_rating'    => $this->extract_maturity_rating($content_data),
                'streaming_platform' => $streaming_platform, // This will be used for ACF
                'content_type'       => $is_tv_show ? 'tv' : 'movie',
                'adsense_ready'      => true,
                'is_movie_post'      => '1', // String value for consistency
                'original_post_type' => 'movie'
            )
        );
        
        // Add trailer if available
        $youtube_id = $this->extract_youtube_trailer($content_data);
        if ($youtube_id) {
            $post_data['meta_input']['youtube_id'] = $youtube_id;
            $post_data['meta_input']['trailer_url'] = 'https://www.youtube.com/watch?v=' . $youtube_id;
        }
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            error_log("Failed to create post for TMDB ID {$tmdb_id}: " . $post_id->get_error_message());
            return $post_id;
        }
        
        if ($post_id == 0) {
            error_log("wp_insert_post returned 0 for TMDB ID {$tmdb_id}");
            return new WP_Error('post_creation_failed', 'Failed to create post');
        }
        
        // CRITICAL: Handle ACF field assignment AFTER post creation
        if ($streaming_platform) {
            $this->assign_platform_properly($post_id, $streaming_platform);
        }
        
        // Process images
        $this->process_movie_images($post_id, $content_data);
        
        error_log("Successfully created regular post {$post_id} for TMDB ID {$tmdb_id}" . ($is_tv_show ? ' (TV Show)' : ' (Movie)'));
        return $post_id;
    }

    /**
     * CRITICAL: Assign platform properly - ACF first, taxonomy fallback
     */
    public function assign_platform_properly($post_id, $streaming_platform) {
        if (empty($streaming_platform)) {
            return false;
        }

        error_log("Attempting to assign platform: {$streaming_platform} to post {$post_id}");

        // METHOD 1: Try ACF field first (if ACF is active)
        if (function_exists('update_field')) {
            // Check if streaming_platform field exists
            $field_object = get_field_object('streaming_platform', $post_id);
            
            if ($field_object || function_exists('acf_get_field')) {
                // Update ACF field
                $acf_result = update_field('streaming_platform', $streaming_platform, $post_id);
                
                if ($acf_result) {
                    error_log("Successfully assigned {$streaming_platform} to ACF field for post {$post_id}");
                    return true;
                } else {
                    error_log("Failed to assign ACF field, trying meta field");
                }
            }
        }

        // METHOD 2: Update post meta as backup
        update_post_meta($post_id, 'streaming_platform', $streaming_platform);
        error_log("Assigned {$streaming_platform} to meta field for post {$post_id}");

        // METHOD 3: Try taxonomy as final fallback (only if no ACF)
        if (!function_exists('update_field')) {
            return $this->assign_platform_taxonomy($post_id, $streaming_platform);
        }

        return true;
    }

    /**
     * FIXED: Get streaming platform from post (ACF aware)
     */
    public function get_post_streaming_platform($post_id) {
        // METHOD 1: Try ACF field first
        if (function_exists('get_field')) {
            $acf_platform = get_field('streaming_platform', $post_id);
            if (!empty($acf_platform)) {
                return $acf_platform;
            }
        }
        
        // METHOD 2: Try post meta
        $meta_platform = get_post_meta($post_id, 'streaming_platform', true);
        if (!empty($meta_platform)) {
            return $meta_platform;
        }
        
        // METHOD 3: Try taxonomy as fallback
        $terms = get_the_terms($post_id, 'streamer');
        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->name;
        }
        
        return '';
    }

    /**
     * Assign platform using taxonomy (fallback method)
     */
    public function assign_platform_taxonomy($post_id, $streaming_platform) {
        if (empty($streaming_platform) || !taxonomy_exists('streamer')) {
            return false;
        }

        // Normalize platform name for consistent terms
        $platform_slug = sanitize_title($streaming_platform);
        
        // Check if term exists, create if not
        $term = term_exists($streaming_platform, 'streamer');

        if ($term !== 0 && $term !== null) {
            $term_id = $term['term_id'];
        } else {
            $new_term = wp_insert_term($streaming_platform, 'streamer', array(
                'slug' => $platform_slug
            ));
            if (is_wp_error($new_term)) {
                error_log('Failed to create streamer term for ' . $streaming_platform . ': ' . $new_term->get_error_message());
                return false;
            }
            $term_id = $new_term['term_id'];
        }

        // Assign the term to the post
        $result = wp_set_object_terms($post_id, $term_id, 'streamer', false);
        
        if (is_wp_error($result)) {
            error_log('Failed to assign streamer taxonomy: ' . $result->get_error_message());
            return false;
        } else {
            error_log("Successfully assigned {$streaming_platform} taxonomy to post {$post_id}");
            return true;
        }
    }

    /**
     * FIXED: Emergency cleanup function for existing posts
     */
    public function emergency_acf_cleanup() {
        global $wpdb;
        
        error_log('=== STARTING ACF PLATFORM CLEANUP ===');
        
        // Get all movie posts
        $movie_posts = $wpdb->get_results(
            "SELECT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'post'
             AND pm.meta_key = 'is_movie_post'
             AND pm.meta_value = '1'"
        );
        
        $fixed_count = 0;
        
        foreach ($movie_posts as $post) {
            $post_id = $post->ID;
            $platform = get_post_meta($post_id, 'streaming_platform', true);
            
            if (!empty($platform)) {
                // Remove from any numbered categories
                $categories = wp_get_post_categories($post_id);
                foreach ($categories as $cat_id) {
                    $category = get_category($cat_id);
                    if ($category && (is_numeric($category->name) || preg_match('/^\d+$/', $category->name))) {
                        wp_remove_object_terms($post_id, $cat_id, 'category');
                        error_log("Removed numeric category {$category->name} from post {$post_id}");
                    }
                }
                
                // Ensure Movies category
                $movie_cat = get_category_by_slug('movies');
                if (!$movie_cat) {
                    $movie_cat_id = wp_create_category('Movies');
                } else {
                    $movie_cat_id = $movie_cat->term_id;
                }
                
                if ($movie_cat_id) {
                    wp_set_post_categories($post_id, array($movie_cat_id), false);
                }
                
                // Fix platform assignment - prioritize ACF
                if (function_exists('update_field')) {
                    update_field('streaming_platform', $platform, $post_id);
                    error_log("Updated ACF field for post {$post_id} with platform {$platform}");
                }
                
                $fixed_count++;
            }
        }
        
        error_log("=== ACF CLEANUP COMPLETED: Fixed {$fixed_count} posts ===");
        return $fixed_count;
    }

    /**
     * Get or create category
     */
    public function get_or_create_category($name) {
        $category = get_category_by_slug(sanitize_title($name));
        
        if ($category) {
            return $category->term_id;
        }
        
        $result = wp_create_category($name);
        
        if (is_wp_error($result)) {
            error_log('Failed to create category ' . $name . ': ' . $result->get_error_message());
            return null;
        }
        
        return $result;
    }

    /**
     * Extract article title from content
     */
    private function extract_article_title_from_content($content) {
        // Look for H1 tag
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
            return wp_strip_all_tags($matches[1]);
        }
        
        // Look for first line before any HTML
        $lines = explode("\n", $content);
        if (!empty($lines[0])) {
            $first_line = trim(strip_tags($lines[0]));
            if (strlen($first_line) > 10 && strlen($first_line) < 200) {
                return $first_line;
            }
        }
        
        return '';
    }

    /**
     * Extract YouTube trailer ID
     */
    private function extract_youtube_trailer($content_data) {
        if (isset($content_data['videos']['results'])) {
            foreach ($content_data['videos']['results'] as $video) {
                if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                    return $video['key'];
                }
            }
        }
        return '';
    }

    /**
     * Extract maturity rating
     */
    private function extract_maturity_rating($content_data) {
        if (isset($content_data['release_dates']['results'])) {
            foreach ($content_data['release_dates']['results'] as $country) {
                if ($country['iso_3166_1'] === 'US' && !empty($country['release_dates'])) {
                    return $country['release_dates'][0]['certification'] ?? '';
                }
            }
        }
        return '';
    }

    /**
     * Ensure unique slug for posts
     */
    private function ensure_unique_slug_for_posts($slug) {
        global $wpdb;
        
        $original_slug = $slug;
        $counter = 1;
        
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post'",
            $slug
        )) > 0) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Check if movie exists (robust check)
     */
    private function movie_exists_robust($tmdb_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'tmdb_id' 
             AND meta_value = %s",
            $tmdb_id
        ));
        
        return $exists > 0;
    }

    /**
     * Get existing movie post ID
     */
    private function get_existing_movie_post_id($tmdb_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = 'tmdb_id' 
             AND meta_value = %s 
             LIMIT 1",
            $tmdb_id
        ));
    }

    /**
     * Process movie images
     */
    private function process_movie_images($post_id, $content_data) {
        // Handle poster
        if (isset($content_data['poster_path']) && !empty($content_data['poster_path'])) {
            $poster_url = 'https://image.tmdb.org/t/p/w500' . $content_data['poster_path'];
            $poster_id = $this->sideload_image($poster_url, $post_id, $content_data['title'] . ' Poster');
            
            if ($poster_id && !is_wp_error($poster_id)) {
                set_post_thumbnail($post_id, $poster_id);
            }
        }
        
        // Handle backdrop
        if (isset($content_data['backdrop_path']) && !empty($content_data['backdrop_path'])) {
            $backdrop_url = 'https://image.tmdb.org/t/p/w1280' . $content_data['backdrop_path'];
            $backdrop_id = $this->sideload_image($backdrop_url, $post_id, $content_data['title'] . ' Backdrop');
            
            if ($backdrop_id && !is_wp_error($backdrop_id)) {
                update_post_meta($post_id, 'backdrop_image_id', $backdrop_id);
            }
        }
    }

    /**
     * Sideload image from URL
     */
    private function sideload_image($url, $post_id, $desc) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        return media_sideload_image($url, $post_id, $desc, 'id');
    }

    /**
     * Generate enhanced article with AI
     */
    private function generate_enhanced_article_with_ai($content_data) {
        if (!$this->openai_api || !$this->openai_api->is_configured()) {
            return $this->generate_enhanced_fallback_article($content_data);
        }
        
        return $this->openai_api->generate_enhanced_article($content_data);
    }

    /**
     * Generate enhanced fallback article
     */
    private function generate_enhanced_fallback_article($content_data) {
        $title = $content_data['title'] ?? 'Untitled';
        $overview = $content_data['overview'] ?? 'No overview available.';
        $release_date = $content_data['release_date'] ?? '';
        $genres = isset($content_data['genres']) ? array_column($content_data['genres'], 'name') : array();
        
        $content = "<h1>Everything You Need to Know About {$title}</h1>\n\n";
        $content .= "<p>{$overview}</p>\n\n";
        
        if ($release_date) {
            $content .= "<h2>Release Information</h2>\n";
            $content .= "<p>Mark your calendars! {$title} is set to release on " . date('F j, Y', strtotime($release_date)) . ".</p>\n\n";
        }
        
        if (!empty($genres)) {
            $content .= "<h2>Genre</h2>\n";
            $content .= "<p>This " . implode(', ', $genres) . " film promises to deliver an unforgettable experience.</p>\n\n";
        }
        
        return $content;
    }

    /**
     * Detect streaming platform
     */
    private function detect_streaming_platform($content_data) {
        // This is a simplified version - you can enhance with actual API data
        if (isset($content_data['watch_providers'])) {
            // Parse watch providers data
            return 'Theatrical Release';
        }
        
        return 'Theatrical Release';
    }

    /**
     * Add Blocksy theme support
     */
    public function add_blocksy_archive_support($post_types) {
        if (!in_array('upcoming_movie', $post_types)) {
            $post_types[] = 'upcoming_movie';
        }
        return $post_types;
    }

    public function add_blocksy_single_support($post_types) {
        if (!in_array('upcoming_movie', $post_types)) {
            $post_types[] = 'upcoming_movie';
        }
        return $post_types;
    }

    /**
     * Add IndexNow support
     */
    public function add_indexnow_support($post_types) {
        if (!in_array('upcoming_movie', $post_types)) {
            $post_types[] = 'upcoming_movie';
        }
        return $post_types;
    }

    /**
     * Add movie post classes
     */
    public function add_movie_post_classes($classes, $class, $post_id) {
        $is_movie = get_post_meta($post_id, 'is_movie_post', true);
        
        if ($is_movie) {
            $classes[] = 'movie-post';
            $content_type = get_post_meta($post_id, 'content_type', true);
            if ($content_type) {
                $classes[] = 'movie-type-' . $content_type;
            }
        }
        
        return $classes;
    }

    /**
     * Cleanup on deletion
     */
    public function cleanup_movie_data_on_deletion($post_id) {
        // Add cleanup logic if needed
    }

    public function cleanup_movie_data_on_trash($post_id) {
        // Add cleanup logic if needed
    }

    // ========================================================================
    // GETTERS FOR API CLASSES
    // ========================================================================

    public function get_tmdb_api() {
        return $this->tmdb_api;
    }

    public function get_openai_api() {
        return $this->openai_api;
    }

    public function get_admin_handler() {
        return $this->admin_handler;
    }

    public function get_frontend_handler() {
        return $this->frontend_handler;
    }
}

// ========================================================================
// PLUGIN INITIALIZATION AND HOOKS
// ========================================================================

// Initialize plugin
add_action('plugins_loaded', function() {
    Upcoming_Movies_Feature::get_instance();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    $instance = Upcoming_Movies_Feature::get_instance();
    $instance->register_streamer_taxonomy();
    update_option('upcoming_movies_flush_needed', true);
    update_option('upcoming_movies_rewrite_version', UPCOMING_MOVIES_VERSION);
    
    // Schedule emergency cleanup for existing installations
    wp_schedule_single_event(time() + 5, 'upcoming_movies_emergency_cleanup');
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
    
    // Clear scheduled events
    wp_clear_scheduled_hook('upcoming_movies_assign_taxonomy');
    wp_clear_scheduled_hook('upcoming_movies_emergency_cleanup');
});

// ========================================================================
// HELPER FUNCTIONS FOR EXTERNAL USE
// ========================================================================

/**
 * Create a movie from external code
 */
function upcoming_movies_create_movie($tmdb_id, $platform = '', $content_type = 'movie') {
    $instance = Upcoming_Movies_Feature::get_instance();
    return $instance->create_upcoming_movie($tmdb_id, $platform, $content_type);
}

/**
 * FIXED: Get movie data for templates
 */
function upcoming_movies_get_movie_data($post_id) {
    $post = get_post($post_id);
    
    if (!$post || !get_post_meta($post_id, 'is_movie_post', true)) {
        return false;
    }
    
    $streaming_platform = get_post_meta($post_id, 'streaming_platform', true);
    $instance = Upcoming_Movies_Feature::get_instance();
    
    return array(
        'tmdb_id' => get_post_meta($post_id, 'tmdb_id', true),
        'movie_title' => get_post_meta($post_id, 'movie_title', true),
        'release_date' => get_post_meta($post_id, 'release_date', true),
        'overview' => get_post_meta($post_id, 'overview', true),
        'runtime' => get_post_meta($post_id, 'runtime', true),
        'genres' => get_post_meta($post_id, 'genres', true),
        'maturity_rating' => get_post_meta($post_id, 'maturity_rating', true),
        'streaming_platform' => $streaming_platform,
        'youtube_id' => get_post_meta($post_id, 'youtube_id', true),
        'trailer_url' => get_post_meta($post_id, 'trailer_url', true),
        'content_type' => get_post_meta($post_id, 'content_type', true),
        'platform_logo_url' => $instance->get_streaming_platform_logo($streaming_platform),
        'tmdb_logo_url' => UPCOMING_MOVIES_PLUGIN_URL . 'assets/images/tmdb-logo.svg'
    );
}

/**
 * Check if TMDB API is configured
 */
function upcoming_movies_tmdb_configured() {
    return !empty(get_option('upcoming_movies_tmdb_api_key'));
}

/**
 * Check if OpenAI API is configured
 */
function upcoming_movies_openai_configured() {
    return !empty(get_option('upcoming_movies_openai_api_key'));
}

/**
 * Get platform logo URL
 */
function upcoming_movies_get_platform_logo($platform) {
    $instance = Upcoming_Movies_Feature::get_instance();
    return $instance->get_streaming_platform_logo($platform);
}

/**
 * FIXED: Get streaming platform from post (ACF aware)
 */
function upcoming_movies_get_post_platform($post_id) {
    $instance = Upcoming_Movies_Feature::get_instance();
    return $instance->get_post_streaming_platform($post_id);
}



?>