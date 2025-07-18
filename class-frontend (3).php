<?php
/**
 * Frontend Functions Handler Class
 * 
 * Handles all public-facing functionality for the plugin
 * Includes shortcodes, templates, and user interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Upcoming_Movies_Frontend {
    private $main_plugin;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_frontend_hooks();
    }

    /**
     * Initialize frontend hooks
     */
    private function init_frontend_hooks() {
        // Shortcodes
        add_shortcode('upcoming_movies', array($this, 'shortcode_upcoming_movies'));
        add_shortcode('latest_movies', array($this, 'shortcode_latest_movies'));
        add_shortcode('movies_by_platform', array($this, 'shortcode_movies_by_platform'));
        add_shortcode('movie_trailer', array($this, 'shortcode_movie_trailer'));
        
        // Template filters
        add_filter('the_content', array($this, 'enhance_movie_content'));
        add_filter('post_class', array($this, 'add_movie_post_classes'), 10, 3);
        
        // SEO and metadata
        add_action('wp_head', array($this, 'add_movie_schema_markup'));
        add_filter('wp_title', array($this, 'enhance_movie_title'), 10, 2);
        add_filter('document_title_parts', array($this, 'modify_movie_title_parts'));
        
        // RSS and feeds
        add_filter('the_excerpt_rss', array($this, 'enhance_movie_rss_excerpt'));
        add_filter('the_content_feed', array($this, 'enhance_movie_feed_content'));
        
        // AJAX handlers for public
        add_action('wp_ajax_nopriv_get_movie_details', array($this, 'ajax_get_movie_details'));
        add_action('wp_ajax_get_movie_details', array($this, 'ajax_get_movie_details'));
        
        // Footer actions
        add_action('wp_footer', array($this, 'add_trailer_modal_html'));
        // Asset loading
    add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    /**
     * Shortcode: Display upcoming movies
     * Usage: [upcoming_movies limit="5" platform="netflix"]
     */
    public function shortcode_upcoming_movies($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'platform' => '',
            'genre' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'show_trailer' => 'true',
            'show_platform' => 'true',
            'layout' => 'grid'
        ), $atts, 'upcoming_movies');

        $query_args = array(
            'post_type' => 'upcoming_movie',
            'posts_per_page' => intval($atts['limit']),
            'post_status' => 'publish',
            'orderby' => $atts['orderby'] === 'date' ? 'meta_value' : $atts['orderby'],
            'order' => $atts['order']
        );

        if ($atts['orderby'] === 'date') {
            $query_args['meta_key'] = 'release_date';
            $query_args['meta_type'] = 'DATE';
        }

        // Filter by platform
        if (!empty($atts['platform'])) {
            $query_args['meta_query'] = array(
                array(
                    'key' => 'streaming_platform',
                    'value' => sanitize_text_field($atts['platform']),
                    'compare' => 'LIKE'
                )
            );
        }

        // Filter by genre
        if (!empty($atts['genre'])) {
            if (!isset($query_args['meta_query'])) {
                $query_args['meta_query'] = array();
            }
            $query_args['meta_query'][] = array(
                'key' => 'genres',
                'value' => sanitize_text_field($atts['genre']),
                'compare' => 'LIKE'
            );
        }

        $movies = new WP_Query($query_args);
        
        if (!$movies->have_posts()) {
            return '<p>' . __('No movies found.', 'upcoming-movies') . '</p>';
        }

        ob_start();
        ?>
        <div class="upcoming-movies-shortcode layout-<?php echo esc_attr($atts['layout']); ?>">
            <?php while ($movies->have_posts()): $movies->the_post(); ?>
                <div class="movie-item" id="movie-<?php the_ID(); ?>">
                    <div class="movie-content">
                        
                        <?php if (has_post_thumbnail()): ?>
                            <div class="movie-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium'); ?>
                                </a>
                                
                                <?php if ($atts['show_trailer'] === 'true'): 
                                    $youtube_id = get_post_meta(get_the_ID(), 'youtube_id', true);
                                    if ($youtube_id): ?>
                                        <button class="trailer-overlay" 
                                                data-trailer="<?php echo esc_attr($youtube_id); ?>"
                                                data-movie-title="<?php echo esc_attr(get_the_title()); ?>">
                                            <span class="play-button">▶</span>
                                            <span class="trailer-text">Watch Trailer</span>
                                        </button>
                                    <?php endif;
                                endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="movie-info">
                            <h3 class="movie-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            
                            <div class="movie-meta">
                                <?php 
                                $release_date = get_post_meta(get_the_ID(), 'release_date', true);
                                if ($release_date): ?>
                                    <span class="release-date">
                                        <?php echo esc_html(date_i18n('M j, Y', strtotime($release_date))); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($atts['show_platform'] === 'true'): 
                                    $platform = get_post_meta(get_the_ID(), 'streaming_platform', true);
                                    if ($platform): ?>
                                        <span class="streaming-platform">
                                            <?php echo esc_html($platform); ?>
                                        </span>
                                    <?php endif;
                                endif; ?>
                            </div>
                            
                            <div class="movie-excerpt">
                                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                            </div>
                            
                            <a href="<?php the_permalink(); ?>" class="read-more">
                                <?php _e('Read More', 'upcoming-movies'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <style>
        .upcoming-movies-shortcode.layout-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .movie-item {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .movie-item:hover {
            transform: translateY(-5px);
        }
        .movie-thumbnail {
            position: relative;
            overflow: hidden;
        }
        .movie-thumbnail img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .trailer-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 20px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .movie-thumbnail:hover .trailer-overlay {
            opacity: 1;
        }
        .movie-info {
            padding: 15px;
        }
        .movie-title a {
            color: #333;
            text-decoration: none;
            font-weight: bold;
        }
        .movie-meta {
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }
        .movie-meta span {
            margin-right: 15px;
        }
        .read-more {
            color: #0073aa;
            text-decoration: none;
            font-weight: bold;
        }
        </style>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Shortcode: Latest movies
     * Usage: [latest_movies count="3"]
     */
    public function shortcode_latest_movies($atts) {
        $atts = shortcode_atts(array(
            'count' => 5,
            'layout' => 'list'
        ), $atts, 'latest_movies');

        return $this->shortcode_upcoming_movies(array(
            'limit' => $atts['count'],
            'orderby' => 'date',
            'order' => 'DESC',
            'layout' => $atts['layout']
        ));
    }

    /**
     * Shortcode: Movies by platform
     * Usage: [movies_by_platform platform="netflix" limit="5"]
     */
    public function shortcode_movies_by_platform($atts) {
        $atts = shortcode_atts(array(
            'platform' => 'netflix',
            'limit' => 5,
            'layout' => 'grid'
        ), $atts, 'movies_by_platform');

        return $this->shortcode_upcoming_movies($atts);
    }

    /**
     * Shortcode: Movie trailer
     * Usage: [movie_trailer id="123"]
     */
    public function shortcode_movie_trailer($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'width' => '100%',
            'height' => '400px'
        ), $atts, 'movie_trailer');

        if (empty($atts['id'])) {
            return '<p>' . __('Movie ID required.', 'upcoming-movies') . '</p>';
        }

        $youtube_id = get_post_meta(intval($atts['id']), 'youtube_id', true);
        
        if (empty($youtube_id)) {
            return '<p>' . __('No trailer available.', 'upcoming-movies') . '</p>';
        }

        $movie_title = get_the_title(intval($atts['id']));

        ob_start();
        ?>
        <div class="movie-trailer-embed" style="width: <?php echo esc_attr($atts['width']); ?>;">
            <div class="trailer-thumbnail" 
                 data-trailer="<?php echo esc_attr($youtube_id); ?>"
                 data-movie-title="<?php echo esc_attr($movie_title); ?>"
                 style="height: <?php echo esc_attr($atts['height']); ?>; position: relative; cursor: pointer; background: url('https://img.youtube.com/vi/<?php echo esc_attr($youtube_id); ?>/maxresdefault.jpg') center/cover;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 50%; font-size: 24px;">
                    ▶
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enhance movie content with additional metadata
     */
    public function enhance_movie_content($content) {
        if (!is_singular('upcoming_movie')) {
            return $content;
        }

        // Add structured data and additional info
        global $post;
        $movie_data = array(
            'release_date' => get_post_meta($post->ID, 'release_date', true),
            'runtime' => get_post_meta($post->ID, 'runtime', true),
            'genres' => get_post_meta($post->ID, 'genres', true),
            'platform' => get_post_meta($post->ID, 'streaming_platform', true),
            'rating' => get_post_meta($post->ID, 'maturity_rating', true)
        );

        return $content; // Content is already enhanced in template
    }

    /**
     * Add movie-specific CSS classes
     */
    public function add_movie_post_classes($classes, $class, $post_id) {
        if (get_post_type($post_id) === 'upcoming_movie') {
            $classes[] = 'upcoming-movie';
            $classes[] = 'movie-post';
            
            $platform = get_post_meta($post_id, 'streaming_platform', true);
            if ($platform) {
                $classes[] = 'platform-' . sanitize_html_class(strtolower($platform));
            }
        }
        
        return $classes;
    }

    /**
     * Add schema markup for movies
     */
    public function add_movie_schema_markup() {
    // Check if it's a single post AND has our movie flag
    if (!is_singular('post') || !get_post_meta(get_the_ID(), 'is_movie_post', true)) {
        return;
    }

        global $post;
        
        $movie_title = get_post_meta($post->ID, 'movie_title', true) ?: get_the_title();
        $overview = get_post_meta($post->ID, 'overview', true);
        $release_date = get_post_meta($post->ID, 'release_date', true);
        $runtime = get_post_meta($post->ID, 'runtime', true);
        $genres = get_post_meta($post->ID, 'genres', true);
        $rating = get_post_meta($post->ID, 'maturity_rating', true);
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Movie',
            'name' => $movie_title,
            'url' => get_permalink(),
            'description' => $overview ?: get_the_excerpt(),
            'datePublished' => get_the_date('c'),
        );

        if ($release_date) {
            $schema['dateCreated'] = date('c', strtotime($release_date));
        }

        if ($runtime && $runtime > 0) {
            $schema['duration'] = 'PT' . $runtime . 'M';
        }

        if ($genres) {
            $genre_array = array_map('trim', explode(',', $genres));
            $schema['genre'] = $genre_array;
        }

        if ($rating && $rating !== 'NR') {
            $schema['contentRating'] = $rating;
        }

        if (has_post_thumbnail()) {
            $schema['image'] = get_the_post_thumbnail_url($post->ID, 'large');
        }

        echo '<script type="application/ld+json">' . json_encode($schema) . '</script>' . "\n";
    }

    /**
     * Enhance movie title for SEO
     */
    public function modify_movie_title_parts($title_parts) {
        if (!is_singular('upcoming_movie')) {
            return $title_parts;
        }

        global $post;
        $release_date = get_post_meta($post->ID, 'release_date', true);
        $platform = get_post_meta($post->ID, 'streaming_platform', true);
        
        if ($release_date) {
            $year = date('Y', strtotime($release_date));
            $title_parts['title'] .= ' (' . $year . ')';
        }
        
        if ($platform) {
            $title_parts['title'] .= ' - ' . $platform;
        }

        return $title_parts;
    }

    /**
     * Enhance RSS excerpt for movies
     */
    public function enhance_movie_rss_excerpt($excerpt) {
        if (get_post_type() !== 'upcoming_movie') {
            return $excerpt;
        }

        $platform = get_post_meta(get_the_ID(), 'streaming_platform', true);
        $release_date = get_post_meta(get_the_ID(), 'release_date', true);
        
        $meta_info = array();
        if ($platform) $meta_info[] = 'Available on ' . $platform;
        if ($release_date) $meta_info[] = 'Release: ' . date('M j, Y', strtotime($release_date));
        
        if (!empty($meta_info)) {
            $excerpt .= ' [' . implode(' | ', $meta_info) . ']';
        }

        return $excerpt;
    }

    /**
     * Enhance feed content for movies
     */
    public function enhance_movie_feed_content($content) {
        if (get_post_type() !== 'upcoming_movie') {
            return $content;
        }

        // Add movie metadata to feed content
        $overview = get_post_meta(get_the_ID(), 'overview', true);
        $platform = get_post_meta(get_the_ID(), 'streaming_platform', true);
        
        if ($overview) {
            $content .= "\n\n" . $overview;
        }
        
        if ($platform) {
            $content .= "\n\nAvailable on: " . $platform;
        }

        return $content;
    }

    /**
     * AJAX: Get movie details
     */
    public function ajax_get_movie_details() {
        check_ajax_referer('upcoming-movies-trailer', 'nonce');
        
        $movie_id = intval($_POST['movie_id']);
        
        if (empty($movie_id)) {
            wp_die('Invalid movie ID');
        }

        $movie_data = array(
            'title' => get_the_title($movie_id),
            'overview' => get_post_meta($movie_id, 'overview', true),
            'release_date' => get_post_meta($movie_id, 'release_date', true),
            'runtime' => get_post_meta($movie_id, 'runtime', true),
            'genres' => get_post_meta($movie_id, 'genres', true),
            'platform' => get_post_meta($movie_id, 'streaming_platform', true),
            'youtube_id' => get_post_meta($movie_id, 'youtube_id', true),
            'permalink' => get_permalink($movie_id)
        );

        wp_send_json_success($movie_data);
    }

    /**
     * Add trailer modal HTML to footer
     */
    public function add_trailer_modal_html() {
        if (is_admin()) return;
        ?>
        <div class="trailer-modal" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <button class="close-modal" aria-label="Close trailer">&times;</button>
                <div class="trailer-embed">
                    <div class="trailer-loading">
                        <div class="loading-spinner"></div>
                        <span>Loading trailer...</span>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .trailer-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
        }
        .modal-content {
            position: relative;
            width: 90%;
            max-width: 1000px;
            max-height: 90%;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .trailer-embed {
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            position: relative;
        }
        .trailer-embed iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .trailer-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-align: center;
        }
        .loading-spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
    }

    /**
 * Enqueue frontend scripts and styles
 */
public function enqueue_frontend_scripts() {
    // Only load on movie pages or if movie posts are present
    if (is_singular('upcoming_movie') || is_post_type_archive('upcoming_movie') || 
        (is_home() || is_search() || is_category() || is_tag()) && $this->has_movie_posts_in_query()) {
        
        // Enqueue frontend CSS with high priority
        wp_enqueue_style(
            'upcoming-movies-frontend', 
            UPCOMING_MOVIES_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            UPCOMING_MOVIES_VERSION, 
            'all'
        );
        
        // Enqueue frontend JavaScript
        wp_enqueue_script(
            'upcoming-movies-frontend', 
            UPCOMING_MOVIES_PLUGIN_URL . 'assets/js/frontend.js', 
            array('jquery'), 
            UPCOMING_MOVIES_VERSION, 
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('upcoming-movies-frontend', 'upcomingMoviesTrailer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('upcoming-movies-trailer'),
            'trackingEnabled' => false,
            'pluginUrl' => UPCOMING_MOVIES_PLUGIN_URL
        ));
    }
}

/**
 * Check if current query has movie posts
 */
private function has_movie_posts_in_query() {
    global $wp_query;
    
    if (!isset($wp_query->posts) || empty($wp_query->posts)) {
        return false;
    }
    
    foreach ($wp_query->posts as $post) {
        if ($post->post_type == 'upcoming_movie') {
            return true;
        }
    }
    
    return false;
    }
}    
