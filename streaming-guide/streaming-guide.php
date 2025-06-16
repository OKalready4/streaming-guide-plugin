<?php
/**
 * Plugin Name: Streaming Guide Generator
 * Description: Automatically generates high-quality articles about movies and shows on major streaming platforms
 * Version: 2.1.0
 * Author: Your Name
 * Text Domain: streaming-guide
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STREAMING_GUIDE_VERSION', '2.1.0');
define('STREAMING_GUIDE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STREAMING_GUIDE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
final class Streaming_Guide {
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core API classes
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-platforms.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-tmdb-api.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-openai-api.php';
        
        // Base generator (must be loaded before specific generators)
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-base-generator.php';
        
        // Load generators
        $generators = array('weekly', 'monthly', 'top10', 'spotlight', 'trending', 'seasonal');
        foreach ($generators as $generator) {
            $file = STREAMING_GUIDE_PLUGIN_DIR . "includes/generators/class-streaming-guide-{$generator}-generator.php";
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // Admin components
        if (is_admin()) {
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-error-handler.php';
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-state-manager.php';
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-ajax-handler.php';
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-admin-router.php';
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-form-processor.php';
        }
        
        // Optional components
        if (file_exists(STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-social-media.php')) {
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-social-media.php';
        }
        
        if (file_exists(STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-cron-handler.php')) {
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-cron-handler.php';
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        if (is_admin()) {
            new Streaming_Guide_Admin_Router();
            new Streaming_Guide_AJAX_Handler();
        }
        
        // Initialize cron handler
        if (class_exists('Streaming_Guide_Cron_Handler')) {
            new Streaming_Guide_Cron_Handler();
        }
        
        // Initialize social media
        if (class_exists('Streaming_Guide_Social_Media')) {
            Streaming_Guide_Social_Media::get_instance();
        }
        
        // Add filters
        add_filter('the_content', array($this, 'maybe_add_featured_image'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        if (class_exists('Streaming_Guide_State_Manager')) {
            new Streaming_Guide_State_Manager();
        }
        
        // Create default options
        $this->create_default_options();
        
        // Schedule cron events
        if (class_exists('Streaming_Guide_Cron_Handler')) {
            $cron_handler = new Streaming_Guide_Cron_Handler();
            $cron_handler->schedule_events();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        if (class_exists('Streaming_Guide_Cron_Handler')) {
            $cron_handler = new Streaming_Guide_Cron_Handler();
            $cron_handler->unschedule_events();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create default options
     */
    private function create_default_options() {
        // API keys
        add_option('streaming_guide_tmdb_api_key', '');
        add_option('streaming_guide_openai_api_key', '');
        
        // Generation settings
        add_option('streaming_guide_auto_publish', true);
        add_option('streaming_guide_featured_image_source', 'backdrop');
        add_option('streaming_guide_auto_generate_weekly', true);
        add_option('streaming_guide_auto_generate_monthly', true);
        
        // Social media settings
        add_option('streaming_guide_auto_share_facebook', false);
        add_option('streaming_guide_auto_share_instagram', false);
        add_option('streaming_guide_share_delay', 5);
        add_option('streaming_guide_facebook_page_id', '');
        add_option('streaming_guide_facebook_access_token', '');
        
        // Platform settings
        $platforms = array('netflix', 'amazon-prime', 'disney-plus', 'hulu', 'max', 'paramount-plus', 'apple-tv');
        foreach ($platforms as $platform) {
            add_option("streaming_guide_enable_{$platform}", true);
        }
    }
    
    /**
     * Maybe add featured image to content
     */
    public function maybe_add_featured_image($content) {
        // Only on single posts
        if (!is_singular('post')) {
            return $content;
        }
        
        // Check if it's a streaming guide post
        $post_id = get_the_ID();
        if (!get_post_meta($post_id, '_streaming_guide_generated', true)) {
            return $content;
        }
        
        // Check if content already has an image
        if (strpos($content, '<img') !== false) {
            return $content;
        }
        
        // Check if post has featured image
        if (!has_post_thumbnail($post_id)) {
            return $content;
        }
        
        // Add featured image at the beginning
        $featured_image = get_the_post_thumbnail($post_id, 'large', array(
            'class' => 'streaming-guide-featured-image aligncenter',
            'style' => 'width: 100%; height: auto; margin-bottom: 30px;'
        ));
        
        if ($featured_image) {
            $content = $featured_image . "\n\n" . $content;
        }
        
        return $content;
    }
}

// Initialize plugin
Streaming_Guide::get_instance();