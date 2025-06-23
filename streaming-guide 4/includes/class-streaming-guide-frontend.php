<?php
/**
 * Streaming Guide Frontend Asset Handler
 * Properly loads CSS and JavaScript for frontend display
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Frontend {
    
    /**
     * Initialize frontend functionality
     */
    public static function init() {
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        
        // Filter article content to add wrapper classes
        add_filter('the_content', array(__CLASS__, 'filter_article_content'), 999);
        
        // Add body classes for streaming guide posts
        add_filter('body_class', array(__CLASS__, 'add_body_classes'));
        
        // Optimize for streaming guide posts
        add_action('wp_head', array(__CLASS__, 'add_meta_optimization'));
    }
    
    /**
     * Enqueue frontend CSS and JavaScript
     */
    public static function enqueue_frontend_assets() {
        // Only load on posts or if we detect streaming guide content
        if (is_singular('post') || self::has_streaming_guide_content()) {
            
            // Frontend CSS
            $css_file = STREAMING_GUIDE_PLUGIN_DIR . 'assets/css/frontend.css';
            if (file_exists($css_file)) {
                wp_enqueue_style(
                    'streaming-guide-frontend',
                    STREAMING_GUIDE_PLUGIN_URL . 'assets/css/frontend.css',
                    array(),
                    STREAMING_GUIDE_VERSION
                );
            }
            
            // Frontend JavaScript with proper jQuery dependency
            $js_file = STREAMING_GUIDE_PLUGIN_DIR . 'assets/js/frontend.js';
            if (file_exists($js_file)) {
                wp_enqueue_script(
                    'streaming-guide-frontend',
                    STREAMING_GUIDE_PLUGIN_URL . 'assets/js/frontend.js',
                    array('jquery'), // Proper jQuery dependency
                    STREAMING_GUIDE_VERSION,
                    true // Load in footer
                );
                
                // Localize script with data
                wp_localize_script('streaming-guide-frontend', 'streamingGuide', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('streaming_guide_frontend'),
                    'isStreamingPost' => self::is_streaming_guide_post(),
                    'debug' => defined('WP_DEBUG') && WP_DEBUG
                ));
            }
        }
    }
    
    /**
     * Filter article content to add proper wrapper and classes
     */
    public static function filter_article_content($content) {
        global $post;
        
        // Only process streaming guide posts
        if (!self::is_streaming_guide_post()) {
            return $content;
        }
        
        // Get article metadata
        $article_type = get_post_meta($post->ID, 'article_type', true);
        $platform = get_post_meta($post->ID, 'streaming_platform', true);
        
        // Build wrapper classes
        $wrapper_classes = array('streaming-guide-article');
        
        if ($article_type) {
            $wrapper_classes[] = esc_attr($article_type) . '-article';
        }
        
        if ($platform) {
            $wrapper_classes[] = esc_attr($platform) . '-platform';
        }
        
        // Wrap content with proper classes
        $wrapped_content = '<div class="' . implode(' ', $wrapper_classes) . '">' . $content . '</div>';
        
        return $wrapped_content;
    }
    
    /**
     * Add body classes for streaming guide posts
     */
    public static function add_body_classes($classes) {
        global $post;
        
        if (self::is_streaming_guide_post()) {
            $classes[] = 'streaming-guide-post';
            
            $article_type = get_post_meta($post->ID, 'article_type', true);
            $platform = get_post_meta($post->ID, 'streaming_platform', true);
            
            if ($article_type) {
                $classes[] = 'sg-type-' . esc_attr($article_type);
            }
            
            if ($platform) {
                $classes[] = 'sg-platform-' . esc_attr($platform);
            }
        }
        
        return $classes;
    }
    
    /**
     * Add meta optimization for streaming guide posts
     */
    public static function add_meta_optimization() {
        if (!self::is_streaming_guide_post()) {
            return;
        }
        
        global $post;
        
        // Add meta tags for better SEO and social sharing
        $platform = get_post_meta($post->ID, 'streaming_platform', true);
        $article_type = get_post_meta($post->ID, 'article_type', true);
        
        echo '<meta name="streaming-platform" content="' . esc_attr($platform) . '">' . "\n";
        echo '<meta name="article-type" content="' . esc_attr($article_type) . '">' . "\n";
        
        // Add preconnect for performance
        echo '<link rel="preconnect" href="https://image.tmdb.org">' . "\n";
        echo '<link rel="preconnect" href="https://www.youtube.com">' . "\n";
        
        // Add DNS prefetch for streaming platforms
        $streaming_domains = array(
            'netflix.com',
            'hulu.com',
            'disneyplus.com',
            'hbomax.com',
            'amazon.com',
            'paramountplus.com',
            'tv.apple.com'
        );
        
        foreach ($streaming_domains as $domain) {
            echo '<link rel="dns-prefetch" href="//' . esc_attr($domain) . '">' . "\n";
        }
    }
    
    /**
     * Check if current post is a streaming guide post
     */
    public static function is_streaming_guide_post() {
        global $post;
        
        if (!$post || !is_singular('post')) {
            return false;
        }
        
        return get_post_meta($post->ID, 'generated_by', true) === 'streaming_guide';
    }
    
    /**
     * Check if page has streaming guide content
     */
    public static function has_streaming_guide_content() {
        // Check if we're on a page that might have streaming content
        if (is_home() || is_front_page()) {
            // Check if recent posts are streaming guide posts
            $recent_posts = get_posts(array(
                'numberposts' => 5,
                'meta_key' => 'generated_by',
                'meta_value' => 'streaming_guide'
            ));
            
            return !empty($recent_posts);
        }
        
        if (is_category() || is_tag() || is_archive()) {
            // Check if any posts in the current query are streaming guide posts
            global $wp_query;
            
            if ($wp_query->have_posts()) {
                $posts = $wp_query->posts;
                foreach ($posts as $post) {
                    if (get_post_meta($post->ID, 'generated_by', true) === 'streaming_guide') {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * AJAX handler for frontend interactions
     */
    public static function ajax_frontend_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'streaming_guide_frontend')) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['sg_action'] ?? '');
        
        switch ($action) {
            case 'track_view':
                self::track_article_view();
                break;
            
            case 'share_count':
                self::increment_share_count();
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }
    
    /**
     * Track article views for analytics
     */
    private static function track_article_view() {
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !self::is_streaming_guide_post()) {
            wp_send_json_error('Invalid post');
        }
        
        // Increment view count
        $views = get_post_meta($post_id, 'streaming_guide_views', true);
        $views = intval($views) + 1;
        update_post_meta($post_id, 'streaming_guide_views', $views);
        
        wp_send_json_success(array('views' => $views));
    }
    
    /**
     * Increment share count
     */
    private static function increment_share_count() {
        $post_id = intval($_POST['post_id'] ?? 0);
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (!$post_id || !$platform) {
            wp_send_json_error('Missing data');
        }
        
        // Increment share count
        $share_key = 'streaming_guide_shares_' . $platform;
        $shares = get_post_meta($post_id, $share_key, true);
        $shares = intval($shares) + 1;
        update_post_meta($post_id, $share_key, $shares);
        
        // Increment total shares
        $total_shares = get_post_meta($post_id, 'streaming_guide_total_shares', true);
        $total_shares = intval($total_shares) + 1;
        update_post_meta($post_id, 'streaming_guide_total_shares', $total_shares);
        
        wp_send_json_success(array(
            'shares' => $shares,
            'total_shares' => $total_shares
        ));
    }
}

// Initialize the frontend handler
add_action('init', array('Streaming_Guide_Frontend', 'init'));

// Register AJAX handlers
add_action('wp_ajax_streaming_guide_frontend', array('Streaming_Guide_Frontend', 'ajax_frontend_action'));
add_action('wp_ajax_nopriv_streaming_guide_frontend', array('Streaming_Guide_Frontend', 'ajax_frontend_action'));