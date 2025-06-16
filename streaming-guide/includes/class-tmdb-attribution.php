<?php
/**
 * Complete TMDB Attribution System for Streaming Guide
 * 
 * Implements proper TMDB attribution as required by their terms:
 * 1. Admin area credits section
 * 2. Frontend article attribution 
 * 3. Proper logo usage and placement
 * 4. Downloads and manages TMDB logos
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_TMDB_Attribution {
    
    private static $tmdb_logos = array(
        'primary' => 'https://www.themoviedb.org/assets/2/v4/logos/v2/blue_short-8e7b30f73a4020692ccca9c88bafe5dcb6f8a62a4c6bc55cd9ba82bb2cd95f6c.svg',
        'alternative' => 'https://www.themoviedb.org/assets/2/v4/logos/v2/blue_long_1-8ba2ac31f354005783fab473602c34c3f4fd207150182061e425d366e4f34596.svg'
    );
    
    /**
     * Initialize attribution system
     */
    public static function init() {
        // Setup logos on plugin activation
        register_activation_hook(STREAMING_GUIDE_PLUGIN_DIR . 'streaming-guide.php', array(__CLASS__, 'setup_tmdb_assets'));
        
        // Add admin attribution
        add_action('admin_menu', array(__CLASS__, 'add_attribution_page'), 99);
        
        // Add frontend attribution to generated articles
        add_filter('the_content', array(__CLASS__, 'add_frontend_attribution'));
        
        // Add attribution to admin footer on plugin pages
        add_filter('admin_footer_text', array(__CLASS__, 'add_admin_footer_attribution'));
        
        // Enqueue attribution styles
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_styles'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_styles'));
        
        // Add attribution to RSS feeds
        add_filter('the_content_feed', array(__CLASS__, 'add_feed_attribution'));
    }
    
    /**
     * Add Credits/About page to admin menu
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
     * Render the complete credits/attribution page
     */
    public static function render_attribution_page() {
        ?>
        <div class="wrap streaming-guide-admin">
            <div class="admin-header">
                <h1><?php esc_html_e('Credits & Attribution', 'streaming-guide'); ?></h1>
                <p class="subtitle"><?php esc_html_e('Data sources and acknowledgments for Streaming Guide', 'streaming-guide'); ?></p>
            </div>
            
            <!-- Primary TMDB Attribution Section (Required) -->
            <div class="card">
                <h2><?php esc_html_e('Data Sources', 'streaming-guide'); ?></h2>
                
                <div class="tmdb-attribution-primary">
                    <div class="tmdb-header">
                        <?php echo self::get_tmdb_logo('large'); ?>
                        <div class="tmdb-main-text">
                            <h3>The Movie Database (TMDB)</h3>
                            <p class="tmdb-required-notice">
                                <strong>This product uses the TMDB API but is not endorsed or certified by TMDB.</strong>
                            </p>
                        </div>
                    </div>
                    
                    <div class="tmdb-details">
                        <p>
                            All movie and TV show information displayed on this website, including but not limited to:
                        </p>
                        <ul>
                            <li>Movie and TV show titles, descriptions, and metadata</li>
                            <li>Cast and crew information</li>
                            <li>Images including posters and backdrop images</li>
                            <li>User ratings and review data</li>
                            <li>Streaming platform availability information</li>
                            <li>Release dates and production details</li>
                        </ul>
                        <p>
                            ...is provided by The Movie Database (TMDB), a community-driven movie and TV database. 
                            TMDB provides this data through their public API for use by applications like ours.
                        </p>
                        
                        <div class="tmdb-links">
                            <a href="https://www.themoviedb.org/" target="_blank" class="button button-primary">
                                Visit TMDB →
                            </a>
                            <a href="https://www.themoviedb.org/documentation/api" target="_blank" class="button">
                                API Documentation →
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- OpenAI Attribution -->
                <div class="attribution-section openai-section">
                    <h3><?php esc_html_e('Content Generation', 'streaming-guide'); ?></h3>
                    <div class="service-attribution">
                        <div class="service-info">
                            <strong>OpenAI GPT</strong>
                            <p>
                                Article content generation is powered by OpenAI's GPT models. 
                                All generated content is reviewed and curated to ensure quality and accuracy.
                                The AI assists in creating readable, engaging articles based on the factual data from TMDB.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Plugin Information -->
                <div class="attribution-section plugin-section">
                    <h3><?php esc_html_e('Plugin Information', 'streaming-guide'); ?></h3>
                    <div class="plugin-info">
                        <table class="form-table">
                            <tr>
                                <th>Plugin Version:</th>
                                <td><?php echo esc_html(STREAMING_GUIDE_VERSION); ?></td>
                            </tr>
                            <tr>
                                <th>WordPress Version:</th>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <th>Articles Generated:</th>
                                <td>
                                    <?php 
                                    $generated_count = wp_count_posts();
                                    $streaming_posts = get_posts(array(
                                        'meta_key' => 'generated_by',
                                        'meta_value' => 'streaming_guide',
                                        'posts_per_page' => -1
                                    ));
                                    echo count($streaming_posts);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td><?php echo date('F j, Y'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Legal and Usage Information -->
            <div class="card">
                <h2><?php esc_html_e('Legal Information & Data Usage', 'streaming-guide'); ?></h2>
                
                <div class="legal-section">
                    <h3>Terms of Use</h3>
                    <ul>
                        <li><strong>Informational Purpose:</strong> This website is for informational and entertainment purposes only</li>
                        <li><strong>Data Accuracy:</strong> While we strive for accuracy, streaming availability and content information can change frequently</li>
                        <li><strong>Verification Required:</strong> Users should verify current streaming availability on official platforms</li>
                        <li><strong>No Affiliation:</strong> We are not affiliated with any streaming platforms mentioned</li>
                        <li><strong>Fair Use:</strong> All content is used under fair use provisions for editorial and informational purposes</li>
                    </ul>
                    
                    <h3>Copyright Notice</h3>
                    <p>
                        All movie and TV show data, images, and related content are the property of their respective copyright holders. 
                        This includes but is not limited to:
                    </p>
                    <ul>
                        <li>Movie posters and promotional images</li>
                        <li>TV show artwork and promotional materials</li>
                        <li>Cast and crew photographs</li>
                        <li>Plot summaries and descriptions</li>
                        <li>Streaming platform logos and trademarks</li>
                    </ul>
                    <p>
                        This website provides information for editorial and informational purposes under fair use provisions. 
                        No copyright infringement is intended.
                    </p>
                    
                    <h3>TMDB Specific Terms</h3>
                    <div class="tmdb-terms">
                        <p>Our use of TMDB data is subject to TMDB's Terms of Use:</p>
                        <ul>
                            <li>We use TMDB's public API according to their terms of service</li>
                            <li>TMDB logos are used according to their brand guidelines</li>
                            <li>We do not claim endorsement by TMDB</li>
                            <li>TMDB data is used for informational purposes only</li>
                        </ul>
                        <p>
                            <a href="https://www.themoviedb.org/terms-of-use" target="_blank">
                                View TMDB's Terms of Use →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Attribution Status -->
            <div class="card">
                <h2><?php esc_html_e('Attribution Status', 'streaming-guide'); ?></h2>
                <div class="attribution-status">
                    <?php echo self::get_attribution_status(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add frontend attribution to streaming guide articles
     */
    public static function add_frontend_attribution($content) {
        // Only add to streaming guide generated posts
        if (!is_singular('post') || !is_main_query()) {
            return $content;
        }
        
        global $post;
        if (!$post || get_post_meta($post->ID, 'generated_by', true) !== 'streaming_guide') {
            return $content;
        }
        
        $attribution = self::get_frontend_attribution();
        return $content . $attribution;
    }
    
    /**
     * Get frontend attribution HTML - COMPLETE VERSION
     */
    private static function get_frontend_attribution() {
        ob_start();
        ?>
        <div class="streaming-guide-attribution">
            <div class="attribution-header">
                <h4>Data Sources</h4>
            </div>
            <div class="attribution-content">
                <div class="attribution-tmdb">
                    <div class="tmdb-logo-container">
                        <?php echo self::get_tmdb_logo('small'); ?>
                    </div>
                    <div class="attribution-text">
                        <p class="tmdb-notice">
                            <strong>This product uses the TMDB API but is not endorsed or certified by TMDB.</strong>
                        </p>
                        <p class="tmdb-description">
                            Movie and TV show information, images, and data provided by 
                            <a href="https://www.themoviedb.org/" target="_blank" rel="noopener">The Movie Database (TMDB)</a>.
                        </p>
                    </div>
                </div>
                <div class="attribution-disclaimer">
                    <p>
                        <em><strong>Please note:</strong> Streaming availability may change frequently. 
                        Verify current availability on official streaming platforms.</em>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add attribution to RSS feeds
     */
    public static function add_feed_attribution($content) {
        global $post;
        
        if ($post && get_post_meta($post->ID, 'generated_by', true) === 'streaming_guide') {
            $attribution = "\n\n" . 
                "---\n" .
                "Data provided by The Movie Database (TMDB). " .
                "This product uses the TMDB API but is not endorsed or certified by TMDB.\n" .
                "Visit: https://www.themoviedb.org/";
            
            return $content . $attribution;
        }
        
        return $content;
    }
    
    /**
     * Add attribution to admin footer on plugin pages
     */
    public static function add_admin_footer_attribution($text) {
        $screen = get_current_screen();
        
        if ($screen && strpos($screen->id, 'streaming-guide') !== false) {
            return $text . ' | ' . sprintf(
                __('Movie data powered by %s', 'streaming-guide'),
                '<a href="https://www.themoviedb.org/" target="_blank">TMDB</a>'
            );
        }
        
        return $text;
    }
    
    /**
     * Get TMDB logo HTML with proper fallbacks
     */
    private static function get_tmdb_logo($size = 'medium') {
        $sizes = array(
            'small' => array('width' => 60, 'height' => 'auto'),
            'medium' => array('width' => 100, 'height' => 'auto'),
            'large' => array('width' => 140, 'height' => 'auto')
        );
        
        $size_attrs = isset($sizes[$size]) ? $sizes[$size] : $sizes['medium'];
        
        // Check for local logo file first
        $logo_path = STREAMING_GUIDE_PLUGIN_DIR . 'assets/images/tmdb-logo.svg';
        $logo_url = STREAMING_GUIDE_PLUGIN_URL . 'assets/images/tmdb-logo.svg';
        
        if (file_exists($logo_path)) {
            return sprintf(
                '<img src="%s" alt="The Movie Database (TMDB)" class="tmdb-logo tmdb-logo-%s" style="width: %spx; height: %s;" />',
                esc_url($logo_url),
                esc_attr($size),
                esc_attr($size_attrs['width']),
                esc_attr($size_attrs['height'])
            );
        }
        
        // Fallback to styled text logo
        $text_size = array(
            'small' => '14px',
            'medium' => '18px', 
            'large' => '24px'
        );
        
        return sprintf(
            '<div class="tmdb-logo-text tmdb-logo-%s" style="background: #01b4e4; color: white; padding: 8px 12px; border-radius: 4px; font-weight: bold; font-size: %s; display: inline-block;">TMDB</div>',
            esc_attr($size),
            esc_attr($text_size[$size])
        );
    }
    
    /**
     * Setup TMDB assets - Download logos and create directories
     */
    public static function setup_tmdb_assets() {
        $assets_dir = STREAMING_GUIDE_PLUGIN_DIR . 'assets/images/';
        
        // Create directory if it doesn't exist
        if (!file_exists($assets_dir)) {
            wp_mkdir_p($assets_dir);
        }
        
        // Download TMDB logo if it doesn't exist
        $logo_file = $assets_dir . 'tmdb-logo.svg';
        
        if (!file_exists($logo_file)) {
            $logo_content = self::download_tmdb_logo();
            if ($logo_content) {
                file_put_contents($logo_file, $logo_content);
            }
        }
    }
    
    /**
     * Download TMDB logo content
     */
    private static function download_tmdb_logo() {
        $logo_url = self::$tmdb_logos['primary'];
        
        $response = wp_remote_get($logo_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress/Streaming-Guide-Plugin'
            )
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return wp_remote_retrieve_body($response);
        }
        
        return false;
    }
    
    /**
     * Get attribution status for admin display
     */
    private static function get_attribution_status() {
        $status = array();
        
        // Check if logo file exists
        $logo_exists = file_exists(STREAMING_GUIDE_PLUGIN_DIR . 'assets/images/tmdb-logo.svg');
        $status[] = array(
            'check' => 'TMDB Logo File',
            'status' => $logo_exists ? 'success' : 'warning',
            'message' => $logo_exists ? 'Logo file downloaded and available' : 'Using text fallback (logo download failed)'
        );
        
        // Check if attribution is being added to articles
        $recent_posts = get_posts(array(
            'meta_key' => 'generated_by',
            'meta_value' => 'streaming_guide',
            'posts_per_page' => 1
        ));
        
        $has_generated_content = !empty($recent_posts);
        $status[] = array(
            'check' => 'Generated Content',
            'status' => $has_generated_content ? 'success' : 'info',
            'message' => $has_generated_content ? 'Attribution being added to generated articles' : 'No generated content yet'
        );
        
        // Check TMDB API key
        $tmdb_key = get_option('streaming_guide_tmdb_api_key');
        $status[] = array(
            'check' => 'TMDB API Usage',
            'status' => !empty($tmdb_key) ? 'success' : 'error',
            'message' => !empty($tmdb_key) ? 'TMDB API configured and attribution required' : 'TMDB API not configured'
        );
        
        ob_start();
        ?>
        <div class="attribution-status-list">
            <?php foreach ($status as $item): ?>
                <div class="status-item status-<?php echo esc_attr($item['status']); ?>">
                    <span class="status-icon">
                        <?php if ($item['status'] === 'success'): ?>
                            ✅
                        <?php elseif ($item['status'] === 'warning'): ?>
                            ⚠️
                        <?php elseif ($item['status'] === 'error'): ?>
                            ❌
                        <?php else: ?>
                            ℹ️
                        <?php endif; ?>
                    </span>
                    <div class="status-content">
                        <strong><?php echo esc_html($item['check']); ?>:</strong>
                        <?php echo esc_html($item['message']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue frontend styles for attribution
     */
    public static function enqueue_frontend_styles() {
        if (!is_singular('post')) {
            return;
        }
        
        global $post;
        if (!$post || get_post_meta($post->ID, 'generated_by', true) !== 'streaming_guide') {
            return;
        }
        
        // Add inline CSS for attribution styling
        wp_add_inline_style('wp-block-library', self::get_frontend_attribution_css());
    }
    
    /**
     * Enqueue admin styles for attribution
     */
    public static function enqueue_admin_styles($hook) {
        if (strpos($hook, 'streaming-guide') === false) {
            return;
        }
        
        wp_add_inline_style('streaming-guide-admin', self::get_admin_attribution_css());
    }
    
    /**
     * Frontend attribution CSS - COMPLETE VERSION
     */
    private static function get_frontend_attribution_css() {
        return '
        .streaming-guide-attribution {
            margin: 40px 0 20px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border-left: 4px solid #01b4e4;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .attribution-header h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        
        .attribution-content {
            max-width: 100%;
        }
        
        .attribution-tmdb {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .tmdb-logo-container {
            flex-shrink: 0;
        }
        
        .attribution-text {
            flex: 1;
        }
        
        .attribution-text p {
            margin: 0 0 10px 0;
            font-size: 14px;
            line-height: 1.5;
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
        
        .attribution-disclaimer {
            padding: 15px;
            background: rgba(1, 180, 228, 0.1);
            border-radius: 6px;
            border-left: 3px solid #01b4e4;
        }
        
        .attribution-disclaimer p {
            margin: 0;
            font-size: 13px;
            color: #555;
            line-height: 1.4;
        }
        
        /* TMDB Logo Styling */
        .tmdb-logo,
        .tmdb-logo-text {
            max-width: 100%;
            height: auto;
            transition: opacity 0.3s ease;
        }
        
        .tmdb-logo:hover,
        .tmdb-logo-text:hover {
            opacity: 0.8;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .streaming-guide-attribution {
                padding: 20px;
                margin: 30px 0 15px;
            }
            
            .attribution-tmdb {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 15px;
                padding: 15px;
            }
            
            .attribution-header h4 {
                font-size: 15px;
            }
            
            .attribution-text p {
                font-size: 13px;
            }
            
            .attribution-disclaimer p {
                font-size: 12px;
            }
        }
        
        @media print {
            .streaming-guide-attribution {
                break-inside: avoid;
                background: white !important;
                border: 1px solid #ccc !important;
                box-shadow: none !important;
            }
        }
        ';
    }
    
    /**
     * Admin attribution CSS - COMPLETE VERSION
     */
    private static function get_admin_attribution_css() {
        return '
        .tmdb-attribution-primary {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .tmdb-header {
            display: flex;
            align-items: center;
            gap: 25px;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e0e0e0;
        }
        
        .tmdb-main-text h3 {
            margin: 0 0 10px 0;
            color: #01b4e4;
            font-size: 24px;
            font-weight: 700;
        }
        
        .tmdb-required-notice {
            margin: 0;
            padding: 12px 16px;
            background: #01b4e4;
            color: white;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .tmdb-details {
            padding: 30px;
        }
        
        .tmdb-details p {
            margin-bottom: 15px;
            color: #555;
            line-height: 1.6;
        }
        
        .tmdb-details ul {
            margin: 15px 0;
            padding-left: 25px;
            color: #666;
        }
        
        .tmdb-details li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .tmdb-links {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .attribution-section {
            margin: 30px 0;
            padding: 25px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .attribution-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .openai-section {
            border-left: 4px solid #10a37f;
        }
        
        .plugin-section {
            border-left: 4px solid #666;
        }
        
        .service-attribution {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }
        
        .service-info strong {
            color: #10a37f;
            font-size: 16px;
        }
        
        .service-info p {
            margin: 8px 0 0 0;
            color: #666;
            line-height: 1.6;
        }
        
        .plugin-info .form-table th {
            width: 200px;
            font-weight: 600;
            color: #333;
        }
        
        .plugin-info .form-table td {
            color: #666;
            font-family: monospace;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        .legal-section {
            max-width: none;
        }
        
        .legal-section h3 {
            color: #d63638;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .legal-section ul {
            margin: 15px 0;
            padding-left: 25px;
        }
        
        .legal-section li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .tmdb-terms {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #01b4e4;
            margin-top: 20px;
        }
        
        .attribution-status-list {
            display: grid;
            gap: 15px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        
        .status-item.status-success {
            background: #f0f9f0;
            border-left-color: #4caf50;
        }
        
        .status-item.status-warning {
            background: #fff8e1;
            border-left-color: #ff9800;
        }
        
        .status-item.status-error {
            background: #fef2f2;
            border-left-color: #f44336;
        }
        
        .status-item.status-info {
            background: #f0f8ff;
            border-left-color: #2196f3;
        }
        
        .status-icon {
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .status-content {
            flex: 1;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .status-content strong {
            color: #333;
        }
        
        /* Mobile responsive for admin */
        @media (max-width: 768px) {
            .tmdb-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
                padding: 20px;
            }
            
            .tmdb-details {
                padding: 20px;
            }
            
            .attribution-section {
                padding: 20px;
            }
            
            .tmdb-links {
                justify-content: center;
            }
            
            .status-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
        ';
    }
}

// Initialize the attribution system
add_action('init', array('Streaming_Guide_TMDB_Attribution', 'init'));

/**
 * Attribution Helper Class for Generator Integration
 */
class Streaming_Guide_Attribution_Helper {
    
    /**
     * Add TMDB attribution meta to generated posts
     */
    public static function add_article_attribution_meta($post_id) {
        if (!$post_id) {
            return false;
        }
        
        update_post_meta($post_id, '_tmdb_attribution_required', true);
        update_post_meta($post_id, '_data_sources', array(
            'tmdb' => 'The Movie Database (TMDB)',
            'openai' => 'OpenAI GPT'
        ));
        update_post_meta($post_id, '_attribution_timestamp', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Check if a post requires TMDB attribution
     */
    public static function requires_attribution($post_id) {
        if (!$post_id) {
            return false;
        }
        
        // Check if it's a streaming guide generated post
        $generated_by = get_post_meta($post_id, 'generated_by', true);
        
        return ($generated_by === 'streaming_guide');
    }
    
    /**
     * Get attribution compliance status
     */
    public static function get_compliance_status() {
        $status = array(
            'compliant' => true,
            'issues' => array(),
            'recommendations' => array()
        );
        
        // Check if TMDB API is being used
        $tmdb_key = get_option('streaming_guide_tmdb_api_key');
        if (empty($tmdb_key)) {
            $status['issues'][] = 'TMDB API key not configured';
            $status['compliant'] = false;
        }
        
        // Check for generated content without proper meta
        $posts_without_attribution = get_posts(array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'generated_by',
                    'value' => 'streaming_guide',
                    'compare' => '='
                ),
                array(
                    'key' => '_tmdb_attribution_required',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'posts_per_page' => 5
        ));
        
        if (!empty($posts_without_attribution)) {
            $status['issues'][] = count($posts_without_attribution) . ' posts missing attribution metadata';
            $status['recommendations'][] = 'Re-generate or update posts to include proper attribution';
        }
        
        // Check logo availability
        $logo_exists = file_exists(STREAMING_GUIDE_PLUGIN_DIR . 'assets/images/tmdb-logo.svg');
        if (!$logo_exists) {
            $status['recommendations'][] = 'TMDB logo file not found - using text fallback';
        }
        
        return $status;
    }
}