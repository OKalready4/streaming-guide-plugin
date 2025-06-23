<?php
/**
 * Simplified TMDB Attribution System for Streaming Guide
 * 
 * Much shorter, cleaner version that focuses on what actually works
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_TMDB_Attribution {
    
    /**
     * Initialize attribution system
     */
    public static function init() {
        // Add admin attribution page
        add_action('admin_menu', array(__CLASS__, 'add_attribution_page'), 99);
        
        // Add frontend attribution to generated articles
        add_filter('the_content', array(__CLASS__, 'add_frontend_attribution'));
        
        // Add attribution styles
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_styles'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_styles'));
    }
    
    /**
     * Add Credits page to admin menu
     */
    public static function add_attribution_page() {
        add_submenu_page(
            'streaming-guide',
            __('Credits & Attribution', 'streaming-guide'),
            __('Credits', 'streaming-guide'),
            'manage_options',
            'streaming-guide-credits',
            array(__CLASS__, 'render_attribution_page')
        );
    }
    
    /**
     * Render the credits page
     */
    public static function render_attribution_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Credits & Attribution', 'streaming-guide'); ?></h1>
            
            <div class="card">
                <h2>Data Sources</h2>
                
                <!-- TMDB Attribution -->
                <div class="tmdb-attribution-admin">
                    <?php echo self::get_tmdb_logo(); ?>
                    <div class="tmdb-info">
                        <h3>The Movie Database (TMDB)</h3>
                        <p><strong>This product uses the TMDB API but is not endorsed or certified by TMDB.</strong></p>
                        <p>All movie and TV show information, including titles, descriptions, images, cast/crew data, ratings, and streaming availability is provided by <a href="https://www.themoviedb.org/" target="_blank">The Movie Database (TMDB)</a>.</p>
                        <p><a href="https://www.themoviedb.org/" target="_blank" class="button button-primary">Visit TMDB →</a></p>
                    </div>
                </div>
                
                <!-- OpenAI Attribution -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <h3>Content Generation</h3>
                    <p><strong>OpenAI GPT:</strong> Article content generation is powered by OpenAI's GPT models. All generated content is reviewed and curated based on factual data from TMDB.</p>
                </div>
                
                <!-- Legal Notice -->
                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3>Legal Information</h3>
                    <ul>
                        <li><strong>Informational Purpose:</strong> This website is for informational and entertainment purposes only</li>
                        <li><strong>No Affiliation:</strong> We are not affiliated with any streaming platforms or TMDB</li>
                        <li><strong>Data Accuracy:</strong> Streaming availability can change frequently - verify on official platforms</li>
                        <li><strong>Fair Use:</strong> All content is used under fair use provisions for editorial purposes</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add frontend attribution to generated articles
     */
    public static function add_frontend_attribution($content) {
        global $post;
        
        // Only add to streaming guide generated posts
        if (!$post || get_post_meta($post->ID, 'generated_by', true) !== 'streaming_guide') {
            return $content;
        }
        
        $attribution = '<div class="tmdb-attribution">';
        $attribution .= '<div class="tmdb-attribution-content">';
        $attribution .= self::get_tmdb_logo();
        $attribution .= '<div class="tmdb-attribution-text">';
        $attribution .= '<p class="tmdb-notice">This product uses the TMDB API but is not endorsed or certified by TMDB.</p>';
        $attribution .= '<p class="tmdb-description">All movie and TV show information is provided by <a href="https://www.themoviedb.org/" target="_blank" rel="noopener">The Movie Database (TMDB)</a>.</p>';
        $attribution .= '</div>';
        $attribution .= '</div>';
        $attribution .= '</div>';
        
        return $content . $attribution;
    }
    
    /**
     * Get TMDB logo - simple, no cropping
     */
    private static function get_tmdb_logo() {
        $logo_url = plugins_url('streaming-guide/assets/images/tmdb-logo.svg');
        return '<img src="' . esc_url($logo_url) . '" alt="TMDB Logo" class="tmdb-logo" />';
    }
    
    /**
     * Enqueue frontend styles
     */
    public static function enqueue_frontend_styles() {
        if (!is_singular('post')) {
            return;
        }
        
        global $post;
        if (!$post || get_post_meta($post->ID, 'generated_by', true) !== 'streaming_guide') {
            return;
        }
        
        wp_add_inline_style('wp-block-library', self::get_attribution_css());
    }
    
    /**
     * Enqueue admin styles
     */
    public static function enqueue_admin_styles($hook) {
        if (strpos($hook, 'streaming-guide') === false) {
            return;
        }
        
        wp_add_inline_style('common', self::get_admin_css());
    }
    
    /**
     * Frontend attribution CSS - Override rounded corners
     */
    private static function get_attribution_css() {
        return '
        .tmdb-attribution {
            margin: 40px 0 20px !important;
            padding: 25px !important;
            background: #f8f9fa !important;
            border-left: 4px solid #01b4e4 !important;
            border-radius: 0 !important; /* Override frontend.css */
            box-shadow: none !important;
        }
        
        .tmdb-attribution-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .tmdb-attribution-text {
            flex: 1;
        }
        
        .tmdb-attribution-text p {
            margin: 0 0 10px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .tmdb-notice {
            color: #01b4e4;
            font-weight: 600;
        }
        
        .tmdb-description {
            color: #666;
        }
        
        .tmdb-description a {
            color: #01b4e4;
            text-decoration: none;
            font-weight: 500;
        }
        
        .tmdb-description a:hover {
            text-decoration: underline;
        }
        
        .tmdb-attribution .tmdb-logo {
            display: block !important;
            max-height: 60px !important;
            width: auto !important;
            border-radius: 0 !important; /* Override frontend.css */
            box-shadow: none !important; /* Remove shadow too */
            margin: 0 !important;
        }
        
        @media (max-width: 768px) {
            .tmdb-attribution-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }
        ';
    }
    
    /**
     * Admin CSS - No rounded corners
     */
    private static function get_admin_css() {
        return '
        .tmdb-attribution-admin {
            display: flex;
            align-items: center;
            gap: 25px;
            padding: 25px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 0 !important; /* Override any theme CSS */
        }
        
        .tmdb-attribution-admin .tmdb-logo {
            max-height: 80px;
            width: auto;
            border-radius: 0 !important; /* Override frontend.css */
            box-shadow: none !important; /* Remove shadow */
        }
        
        .tmdb-info h3 {
            margin: 0 0 15px 0;
            color: #01b4e4;
            font-size: 20px;
        }
        
        .tmdb-info p {
            margin: 0 0 12px 0;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .tmdb-attribution-admin {
                flex-direction: column;
                text-align: center;
            }
        }
        ';
    }
}

/**
 * Helper class for adding attribution to generated posts
 */
class Streaming_Guide_Attribution_Helper {
    
    /**
     * Add attribution meta to generated posts
     */
    public static function add_article_attribution_meta($post_id) {
        if (!$post_id) {
            return false;
        }
        
        update_post_meta($post_id, '_tmdb_attribution_required', true);
        update_post_meta($post_id, '_attribution_timestamp', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Check if post requires attribution
     */
    public static function requires_attribution($post_id) {
        if (!$post_id) {
            return false;
        }
        
        return (get_post_meta($post_id, 'generated_by', true) === 'streaming_guide');
    }
}

// Initialize the attribution system
add_action('init', array('Streaming_Guide_TMDB_Attribution', 'init'));