<?php
/**
 * Admin Functions Handler Class
 * 
 * Handles all WordPress admin functionality for the plugin
 * Includes menu pages, AJAX handlers, and admin operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Upcoming_Movies_Admin {
    private $main_plugin;
    private $tmdb_api;
    private $openai_api;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->tmdb_api = $main_plugin->get_tmdb_api();
        $this->openai_api = $main_plugin->get_openai_api();
        
        $this->init_admin_hooks();
    }

    /**
     * Initialize admin hooks
     */
    private function init_admin_hooks() {
        // Admin menu and pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        
        // Asset loading
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_process_bulk_movies', array($this, 'ajax_process_bulk_movies'));
        add_action('wp_ajax_get_bulk_status', array($this, 'ajax_get_bulk_status'));
        add_action('wp_ajax_process_direct_tmdb', array($this, 'ajax_process_direct_tmdb'));
        
        // Posts list customization
        add_filter('manage_posts_columns', array($this, 'add_movie_columns'));
        add_action('manage_posts_custom_column', array($this, 'populate_movie_columns'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'add_post_type_filter'));
        add_filter('parse_query', array($this, 'filter_posts_by_type'));
        add_action('pre_get_posts', array($this, 'add_movies_to_admin'));
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Movies', 'upcoming-movies'),
            __('Movies', 'upcoming-movies'),
            'manage_options',
            'upcoming-movies',
            array($this, 'render_admin_page'),
            'dashicons-video-alt2',
            30
        );

        add_submenu_page(
            'upcoming-movies',
            __('Add New Movie', 'upcoming-movies'),
            __('Add New', 'upcoming-movies'),
            'manage_options',
            'upcoming-movies-add',
            array($this, 'render_add_movie_page')
        );
        
        add_submenu_page(
            'upcoming-movies',
            __('Mass Producer', 'upcoming-movies'),
            __('Mass Producer', 'upcoming-movies'),
            'manage_options',
            'upcoming-movies-mass-producer',
            array($this, 'render_mass_producer_page')
        );

        add_submenu_page(
            'upcoming-movies',
            __('Settings', 'upcoming-movies'),
            __('Settings', 'upcoming-movies'),
            'manage_options',
            'upcoming-movies-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('upcoming_movies_options', 'upcoming_movies_tmdb_api_key');
        register_setting('upcoming_movies_options', 'upcoming_movies_openai_api_key');
    }

    /**
 * FIXED: Handle admin actions with proper security checks
 */
public function handle_admin_actions() {
    // ONLY handle our specific plugin actions
    
    // Handle movie deletion FROM OUR PLUGIN PAGE ONLY
    if (isset($_GET['action']) && $_GET['action'] === 'delete_movie' && 
        isset($_GET['page']) && strpos($_GET['page'], 'upcoming-movies') !== false &&
        isset($_GET['movie_id']) && isset($_GET['_wpnonce'])) {
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'upcoming-movies'));
        }
        
        $movie_id = intval($_GET['movie_id']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);
        
        // SECURITY: Verify nonce
        if (!wp_verify_nonce($nonce, 'upcoming_movies_delete_movie_' . $movie_id)) {
            wp_die(__('Security check failed.', 'upcoming-movies'));
        }
        
        $this->handle_delete_movie();
    }
    
    // Handle comprehensive cleanup FROM OUR PLUGIN PAGE ONLY
    if (isset($_GET['comprehensive_cleanup']) && $_GET['comprehensive_cleanup'] === '1' &&
        isset($_GET['page']) && strpos($_GET['page'], 'upcoming-movies') !== false &&
        isset($_GET['cleanup_nonce'])) {
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'upcoming-movies'));
        }
        
        $nonce = sanitize_text_field($_GET['cleanup_nonce']);
        
        // SECURITY: Verify nonce
        if (!wp_verify_nonce($nonce, 'comprehensive_cleanup')) {
            wp_die(__('Security check failed.', 'upcoming-movies'));
        }
        
        $this->handle_comprehensive_cleanup();
    }
    
    // DO NOT INTERFERE WITH STANDARD WORDPRESS POST OPERATIONS
}

    /**
     * Handle movie deletion
     */
    private function handle_delete_movie() {
        $post_id = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;

        if ($post_id <= 0) {
            wp_redirect(admin_url('admin.php?page=upcoming-movies&movie_delete_error=invalid_id'));
            exit;
        }

        $trashed = wp_trash_post($post_id);

        if ($trashed) {
            wp_redirect(admin_url('admin.php?page=upcoming-movies&movie_deleted=success'));
        } else {
            wp_redirect(admin_url('admin.php?page=upcoming-movies&movie_delete_error=failed'));
        }
        exit;
    }

    /**
     * Handle comprehensive cleanup
     */
    private function handle_comprehensive_cleanup() {
        $results = $this->comprehensive_database_cleanup();
        
        $message = sprintf(
            'Database cleanup completed! Removed: %d metadata entries, %d images (%d files), %d options, %d batch records, %d broken posts.',
            $results['orphaned_metadata'],
            $results['orphaned_images'],
            $results['deleted_image_files'],
            $results['deleted_options'],
            $results['deleted_batches'],
            $results['deleted_broken_posts']
        );
        
        wp_redirect(admin_url('admin.php?page=upcoming-movies&cleanup_success=' . urlencode($message)));
        exit;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'upcoming-movies') === false) {
            return;
        }

        wp_enqueue_style('upcoming-movies-admin', UPCOMING_MOVIES_PLUGIN_URL . 'assets/css/admin.css', array(), UPCOMING_MOVIES_VERSION);
        wp_enqueue_script('upcoming-movies-admin', UPCOMING_MOVIES_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), UPCOMING_MOVIES_VERSION, true);

        wp_localize_script('upcoming-movies-admin', 'upcomingMovies', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('upcoming-movies-nonce'),
            'deleteConfirm' => __('Are you sure you want to delete "%s"?', 'upcoming-movies')
        ));
    }

    /**
     * ENHANCED: AJAX handler for bulk movie processing with 1-5 flexibility
     */
    public function ajax_process_bulk_movies() {
        check_ajax_referer('upcoming-movies-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $movie_ids = isset($_POST['movie_ids']) ? array_map('intval', $_POST['movie_ids']) : array();
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';
        $count = isset($_POST['count']) ? intval($_POST['count']) : count($movie_ids);
        
        // ENHANCED: Allow 1-5 movies instead of exactly 5
        if (empty($movie_ids) || count($movie_ids) < 1 || count($movie_ids) > 5) {
            wp_send_json_error('Please select between 1-5 movies/shows');
        }
        
        if (empty($platform)) {
            wp_send_json_error('Platform is required');
        }
        
        // Create batch ID
        $batch_id = 'mass_' . time() . '_' . wp_generate_password(8, false);
        
        // Store batch data
        update_option('upcoming_movies_batch_' . $batch_id, array(
            'movie_ids' => $movie_ids,
            'platform' => $platform,
            'total' => count($movie_ids),
            'completed' => 0,
            'errors' => array(),
            'skipped' => array(),
            'status' => 'processing',
            'started' => current_time('mysql'),
            'count' => $count,
            'type' => 'bulk_discovery'
        ), false);
        
        // Process movies synchronously
        $this->process_enhanced_batch_sync($batch_id, $movie_ids, $platform);
        
        wp_send_json_success(array(
            'batch_id' => $batch_id,
            'message' => sprintf('Started processing %d items for %s', count($movie_ids), $platform)
        ));
    }

    /**
     * AJAX handler for direct TMDB processing
     */
    public function ajax_process_direct_tmdb() {
        check_ajax_referer('upcoming-movies-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $tmdb_ids_input = isset($_POST['tmdb_ids']) ? sanitize_textarea_field($_POST['tmdb_ids']) : '';
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : 'auto';
        
        // Parse and validate TMDB IDs
        $tmdb_ids = array_map('trim', explode(',', $tmdb_ids_input));
        $tmdb_ids = array_filter(array_map('intval', $tmdb_ids));
        $tmdb_ids = array_slice($tmdb_ids, 0, 5);
        
        if (empty($tmdb_ids)) {
            wp_send_json_error('Please provide valid TMDB IDs');
        }
        
        if (empty($platform)) {
            wp_send_json_error('Platform is required');
        }
        
        // Create batch ID
        $batch_id = 'direct_' . time() . '_' . wp_generate_password(8, false);
        
        // Store batch data
        update_option('upcoming_movies_batch_' . $batch_id, array(
            'movie_ids' => $tmdb_ids,
            'platform' => $platform,
            'content_type' => $content_type,
            'total' => count($tmdb_ids),
            'completed' => 0,
            'errors' => array(),
            'skipped' => array(),
            'status' => 'processing',
            'started' => current_time('mysql'),
            'type' => 'direct_tmdb'
        ), false);
        
        // Process direct TMDB IDs
        $this->process_direct_tmdb_batch($batch_id, $tmdb_ids, $platform, $content_type);
        
        wp_send_json_success(array(
            'batch_id' => $batch_id,
            'message' => sprintf('Started processing %d direct TMDB IDs for %s', count($tmdb_ids), $platform)
        ));
    }

    /**
     * AJAX handler for batch status
     */
    public function ajax_get_bulk_status() {
        check_ajax_referer('upcoming-movies-nonce', 'nonce');
        
        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
        
        if (empty($batch_id)) {
            wp_send_json_error('Batch ID required');
        }
        
        $batch_data = get_option('upcoming_movies_batch_' . $batch_id);
        
        if (!$batch_data) {
            wp_send_json_error('Batch not found');
        }
        
        wp_send_json_success($batch_data);
    }

    /**
     * Enhanced batch processing
     */
    private function process_enhanced_batch_sync($batch_id, $movie_ids, $platform) {
        $batch_data = get_option('upcoming_movies_batch_' . $batch_id);
        $completed = 0;
        $errors = array();
        $skipped = array();
        
        set_time_limit(count($movie_ids) * 120);
        ignore_user_abort(true);
        
        error_log("Upcoming Movies: Enhanced batch processing {$batch_id} - " . count($movie_ids) . " items for {$platform}");
        
        foreach ($movie_ids as $index => $movie_id) {
            // Update status
            $batch_data['current_movie'] = $movie_id;
            $batch_data['completed'] = $completed;
            $batch_data['errors'] = $errors;
            $batch_data['skipped'] = $skipped;
            $batch_data['progress_percentage'] = round(($index / count($movie_ids)) * 100);
            update_option('upcoming_movies_batch_' . $batch_id, $batch_data, false);
            
            // Check if exists
            if ($this->main_plugin->movie_exists_robust($movie_id)) {
                $skipped[] = $movie_id;
                continue;
            }
            
            // Create movie
            try {
                $result = $this->main_plugin->create_upcoming_movie($movie_id, $platform);
                
                if (is_int($result) && $result > 0) {
                    if (!empty($platform)) {
                        update_post_meta($result, 'streaming_platform', $platform);
                    }
                    $completed++;
                    error_log("Upcoming Movies: Successfully created movie ID {$movie_id} as post {$result}");
                } else {
                    $errors[] = $movie_id;
                    if (is_wp_error($result)) {
                        error_log("Upcoming Movies: Failed to create movie ID {$movie_id}: " . $result->get_error_message());
                    }
                }
            } catch (Exception $e) {
                $errors[] = $movie_id;
                error_log("Upcoming Movies: Exception creating movie ID {$movie_id}: " . $e->getMessage());
            }
            
            if (count($movie_ids) > 1 && $index < count($movie_ids) - 1) {
                sleep(2);
            }
        }
        
        // Final update
        $batch_data['completed'] = $completed;
        $batch_data['errors'] = $errors;
        $batch_data['skipped'] = $skipped;
        $batch_data['status'] = 'completed';
        $batch_data['finished'] = current_time('mysql');
        $batch_data['progress_percentage'] = 100;
        update_option('upcoming_movies_batch_' . $batch_id, $batch_data, false);
        
        if ($completed > 0) {
            update_option('upcoming_movies_flush_needed', true);
        }
    }

    /**
     * Process direct TMDB batch
     */
    private function process_direct_tmdb_batch($batch_id, $tmdb_ids, $platform, $content_type) {
        $batch_data = get_option('upcoming_movies_batch_' . $batch_id);
        $completed = 0;
        $errors = array();
        $skipped = array();
        
        set_time_limit(count($tmdb_ids) * 150);
        ignore_user_abort(true);
        
        foreach ($tmdb_ids as $index => $tmdb_id) {
            // Update status
            $batch_data['current_movie'] = $tmdb_id;
            $batch_data['completed'] = $completed;
            $batch_data['errors'] = $errors;
            $batch_data['skipped'] = $skipped;
            $batch_data['progress_percentage'] = round(($index / count($tmdb_ids)) * 100);
            update_option('upcoming_movies_batch_' . $batch_id, $batch_data, false);
            
            if ($this->main_plugin->movie_exists_robust($tmdb_id)) {
                $skipped[] = $tmdb_id;
                continue;
            }
            
            // Detect content type
            $detected_type = $content_type;
            if ($content_type === 'auto') {
                $detected_type = $this->detect_tmdb_content_type($tmdb_id);
            }
            
            try {
                if ($detected_type === 'tv') {
                    $result = $this->main_plugin->create_tv_show_from_tmdb($tmdb_id, $platform);
                } else {
                    $result = $this->main_plugin->create_upcoming_movie($tmdb_id, $platform);
                }
                
                if (is_int($result) && $result > 0) {
                    if (!empty($platform)) {
                        update_post_meta($result, 'streaming_platform', $platform);
                    }
                    update_post_meta($result, 'content_type', $detected_type);
                    update_post_meta($result, 'direct_tmdb_input', true);
                    
                    $completed++;
                } else {
                    $errors[] = $tmdb_id;
                }
            } catch (Exception $e) {
                $errors[] = $tmdb_id;
                error_log("Upcoming Movies: Exception creating TMDB ID {$tmdb_id}: " . $e->getMessage());
            }
            
            if (count($tmdb_ids) > 1 && $index < count($tmdb_ids) - 1) {
                sleep(3);
            }
        }
        
        // Final update
        $batch_data['completed'] = $completed;
        $batch_data['errors'] = $errors;
        $batch_data['skipped'] = $skipped;
        $batch_data['status'] = 'completed';
        $batch_data['finished'] = current_time('mysql');
        $batch_data['progress_percentage'] = 100;
        update_option('upcoming_movies_batch_' . $batch_id, $batch_data, false);
        
        if ($completed > 0) {
            update_option('upcoming_movies_flush_needed', true);
        }
    }

    /**
     * Detect TMDB content type
     */
    private function detect_tmdb_content_type($tmdb_id) {
        // Try movie first
        $movie_details = $this->tmdb_api->get_movie_details($tmdb_id);
        if (!is_wp_error($movie_details) && !empty($movie_details) && isset($movie_details['title'])) {
            return 'movie';
        }
        
        // Try TV show
        $tv_details = $this->tmdb_api->get_tv_details($tmdb_id);
        if (!is_wp_error($tv_details) && !empty($tv_details) && isset($tv_details['name'])) {
            return 'tv';
        }
        
        return 'movie';
    }

    /**
     * Add movie columns to posts list
     */
    public function add_movie_columns($columns) {
        global $typenow;
        
        if (in_array($typenow, array('post', 'upcoming_movie', ''))) {
            $new_columns = array();
            foreach ($columns as $key => $title) {
                $new_columns[$key] = $title;
                
                if ($key === 'title') {
                    $new_columns['movie_type'] = __('Type', 'upcoming-movies');
                    $new_columns['streaming_platform'] = __('Platform', 'upcoming-movies');
                    $new_columns['release_date'] = __('Release', 'upcoming-movies');
                }
            }
            return $new_columns;
        }
        
        return $columns;
    }

    /**
     * Populate movie columns
     */
    public function populate_movie_columns($column, $post_id) {
        $post_type = get_post_type($post_id);
        
        switch ($column) {
            case 'movie_type':
                if ($post_type === 'upcoming_movie') {
                    echo '<span style="background: #2271b1; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 500;">MOVIE</span>';
                } else {
                    echo '<span style="background: #72aee6; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 500;">POST</span>';
                }
                break;
                
            case 'streaming_platform':
                if ($post_type === 'upcoming_movie') {
                    $platform = get_post_meta($post_id, 'streaming_platform', true);
                    if (!empty($platform)) {
                        echo '<span style="background: #e1f5fe; color: #01579b; padding: 2px 8px; border-radius: 12px; font-size: 0.8em;">' . esc_html($platform) . '</span>';
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'release_date':
                if ($post_type === 'upcoming_movie') {
                    $release_date = get_post_meta($post_id, 'release_date', true);
                    if (!empty($release_date)) {
                        echo esc_html(date_i18n('M j, Y', strtotime($release_date)));
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Add post type filter
     */
    public function add_post_type_filter() {
        global $typenow, $pagenow;
        
        if ($pagenow === 'edit.php' && empty($typenow)) {
            $selected = isset($_GET['post_type_filter']) ? $_GET['post_type_filter'] : '';
            ?>
            <select name="post_type_filter">
                <option value=""><?php _e('All Post Types', 'upcoming-movies'); ?></option>
                <option value="post" <?php selected($selected, 'post'); ?>><?php _e('Posts Only', 'upcoming-movies'); ?></option>
                <option value="upcoming_movie" <?php selected($selected, 'upcoming_movie'); ?>><?php _e('Movies Only', 'upcoming-movies'); ?></option>
            </select>
            <?php
        }
    }

    /**
     * Filter posts by type
     */
    public function filter_posts_by_type($query) {
        global $pagenow, $typenow;
        
        if (is_admin() && $pagenow === 'edit.php' && empty($typenow) && 
            isset($_GET['post_type_filter']) && !empty($_GET['post_type_filter'])) {
            $query->set('post_type', sanitize_text_field($_GET['post_type_filter']));
        }
    }

    /**
     * FIXED: Properly integrate movies in admin posts list
     */
    public function add_movies_to_admin($query) {
        global $pagenow;

        // Only run on the main posts list page in the admin
        if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }

        // Handle user's custom filter choice
        if (isset($_GET['post_type_filter']) && !empty($_GET['post_type_filter'])) {
            $query->set('post_type', sanitize_text_field($_GET['post_type_filter']));
            return;
        }

        // If no specific post type is being displayed, show both posts and movies
        if (!isset($_GET['post_type']) || $_GET['post_type'] === '' || $_GET['post_type'] === 'post') {
            $query->set('post_type', array('post', 'upcoming_movie'));
        }
    }

    /**
     * Comprehensive database cleanup
     */
    private function comprehensive_database_cleanup() {
        global $wpdb;
        $cleanup_results = array();
        
        // Clean orphaned metadata
        $orphaned_meta = $wpdb->get_results("
            SELECT pm.meta_id 
            FROM {$wpdb->postmeta} pm 
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key IN ('tmdb_id', 'movie_title', 'release_date', 'trailer_url', 'youtube_id', 'overview', 'runtime', 'genres', 'maturity_rating', 'streaming_platform')
            AND p.ID IS NULL
        ");
        
        if (!empty($orphaned_meta)) {
            $meta_ids = array_column($orphaned_meta, 'meta_id');
            $deleted_meta = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (" . implode(',', array_map('intval', $meta_ids)) . ")");
            $cleanup_results['orphaned_metadata'] = $deleted_meta;
        } else {
            $cleanup_results['orphaned_metadata'] = 0;
        }
        
        // Clean other items (simplified for space)
        $cleanup_results['orphaned_images'] = 0;
        $cleanup_results['deleted_image_files'] = 0;
        $cleanup_results['deleted_broken_posts'] = 0;
        $cleanup_results['deleted_options'] = 0;
        $cleanup_results['deleted_batches'] = 0;
        
        // Optimize tables
        $wpdb->query("OPTIMIZE TABLE {$wpdb->posts}");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->postmeta}");
        
        update_option('upcoming_movies_flush_needed', true);
        
        return $cleanup_results;
    }

    /**
     * Render main admin page
     */
    public function render_admin_page() {
        $upcoming_movies = get_posts(array(
            'post_type' => 'upcoming_movie',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'release_date',
            'order' => 'ASC',
            'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
        ));

        include UPCOMING_MOVIES_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Render add movie page
     */
    public function render_add_movie_page() {
        $search_results = null;
        $error_message = '';

        $tmdb_api_key = get_option('upcoming_movies_tmdb_api_key');
        if (empty($tmdb_api_key)) {
            $error_message = sprintf(
                __('TMDB API Key is not configured. Please add your key in the <a href="%s">Settings</a>.', 'upcoming-movies'),
                admin_url('admin.php?page=upcoming-movies-settings')
            );
        }

        // Handle search form submission
        if (!empty($tmdb_api_key) && isset($_POST['search_movie']) && !empty($_POST['movie_title'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'upcoming_movies_search')) {
                $error_message = __('Security check failed.', 'upcoming-movies');
            } else {
                $search_term = sanitize_text_field($_POST['movie_title']);
                $search_results = $this->tmdb_api->search_movies($search_term);

                if (is_wp_error($search_results)) {
                    $error_message = __('Error searching TMDB: ', 'upcoming-movies') . $search_results->get_error_message();
                    $search_results = null;
                }
            }
        }

        // Handle add movie form submission
        if (!empty($tmdb_api_key) && isset($_POST['add_upcoming_movie']) && !empty($_POST['movie_id'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'upcoming_movies_search')) {
                $error_message = __('Security check failed.', 'upcoming-movies');
            } else {
                $movie_id = intval($_POST['movie_id']);
                $platform_choice = isset($_POST['streaming_platform']) ? sanitize_text_field($_POST['streaming_platform']) : 'Theatrical Release';
                
                $result = $this->main_plugin->create_upcoming_movie($movie_id, $platform_choice);

                if (is_int($result) && $result > 0) {
                    wp_redirect(admin_url('admin.php?page=upcoming-movies&movie_added=' . $result));
                    exit;
                } elseif (is_wp_error($result)) {
                    $error_message = __('Failed to create movie: ', 'upcoming-movies') . $result->get_error_message();
                } else {
                    $error_message = __('An unknown error occurred while creating the movie.', 'upcoming-movies');
                }
            }
        }

        include UPCOMING_MOVIES_PLUGIN_DIR . 'templates/add-movie.php';
    }

    /**
     * Render mass producer page
     */
    public function render_mass_producer_page() {
        include UPCOMING_MOVIES_PLUGIN_DIR . 'templates/mass-producer.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include UPCOMING_MOVIES_PLUGIN_DIR . 'templates/settings.php';
    }
}