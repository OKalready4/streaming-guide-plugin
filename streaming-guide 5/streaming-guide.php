<?php
/**
 * Plugin Name:       Streaming Guide Generator
 * Description:       A powerful content generation engine for creating articles about streaming movies and TV shows.
 * Version:           2.1.0
 * Author:            Your Name
 * Text Domain:       streaming-guide
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants for easy access
define('STREAMING_GUIDE_VERSION', '2.1.0');
define('STREAMING_GUIDE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STREAMING_GUIDE_PLUGIN_URL', plugin_dir_url(__FILE__));

final class Streaming_Guide_Plugin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load all files immediately. This makes all classes available everywhere.
        $this->load_dependencies();
        
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize the rest of the plugin on the 'plugins_loaded' hook
        add_action('plugins_loaded', array($this, 'initialize_plugin'));
    }

    /**
     * Load all necessary files for the plugin to function.
     * By loading these upfront, we prevent "Class not found" errors.
     */
    private function load_dependencies() {
        // Core Functionality (from /includes/)
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-platforms.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-tmdb-api.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-openai-api.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-tmdb-attribution.php';
        
        // Check for optional files before requiring them
        $optional_files = [
            'includes/class-streaming-guide-frontend.php',
            'includes/class-streaming-guide-seo-enhancer.php',
            'includes/class-social-media.php',
            'includes/class-cron-handler.php'
        ];
        
        foreach ($optional_files as $file) {
            if (file_exists(STREAMING_GUIDE_PLUGIN_DIR . $file)) {
                require_once STREAMING_GUIDE_PLUGIN_DIR . $file;
            }
        }

        // Admin Functionality (from /admin/)
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-error-handler.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-state-manager.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-ajax-handler.php';
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-admin-router.php';
        
        // Check for optional admin files
        if (file_exists(STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-seo-manager.php')) {
            require_once STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-seo-manager.php';
        }
        
        // Base Generator (must be loaded before specific generators)
        require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-base-generator.php';

        // All Generators
        $generators = ['weekly', 'monthly', 'top10', 'spotlight', 'trending', 'seasonal'];
        foreach ($generators as $gen) {
            $generator_file = STREAMING_GUIDE_PLUGIN_DIR . "includes/generators/class-streaming-guide-{$gen}-generator.php";
            if (file_exists($generator_file)) {
                require_once $generator_file;
            }
        }
    }
    
    /**
     * Initialize all the plugin's components.
     * This runs after all plugins are loaded.
     */
    public function initialize_plugin() {
        // Instantiate handlers that need to add hooks
        if (class_exists('Streaming_Guide_Frontend')) {
            new Streaming_Guide_Frontend();
        }
        
        if (class_exists('Streaming_Guide_Cron_Handler')) {
            new Streaming_Guide_Cron_Handler();
        }
        
        new Streaming_Guide_AJAX_Handler();
        
        if (class_exists('TMDB_Attribution') || class_exists('Streaming_Guide_TMDB_Attribution')) {
            if (class_exists('Streaming_Guide_TMDB_Attribution')) {
                Streaming_Guide_TMDB_Attribution::init();
            } else {
                TMDB_Attribution::init();
            }
        }
        
        if (class_exists('Streaming_Guide_Social_Media')) {
            Streaming_Guide_Social_Media::get_instance();
        }
        
        // The SEO Enhancer needs the OpenAI API instance
        if (class_exists('Streaming_Guide_SEO_Enhancer')) {
            new Streaming_Guide_SEO_Enhancer(new Streaming_Guide_OpenAI_API());
        }
        
        // Admin-only components
        if (is_admin()) {
            // Use the Admin Router instead of Streaming_Guide_Admin
            new Streaming_Guide_Admin_Router();
        }
    }

    /**
     * Fired on plugin activation.
     */
    public function activate() {
        // Just instantiate State Manager - it creates tables automatically in its constructor
        $state_manager = new Streaming_Guide_State_Manager();
        // No need to call create_tables() - it's private and called automatically

        // Setup TMDB assets if the class exists
        if (class_exists('Streaming_Guide_TMDB_Attribution')) {
            if (method_exists('Streaming_Guide_TMDB_Attribution', 'setup_tmdb_assets')) {
                Streaming_Guide_TMDB_Attribution::setup_tmdb_assets();
            }
        } elseif (class_exists('TMDB_Attribution')) {
            if (method_exists('TMDB_Attribution', 'setup_tmdb_assets')) {
                TMDB_Attribution::setup_tmdb_assets();
            }
        }

        // Set default options
        $this->set_default_options();
        
        // Register settings
        $this->register_settings();
        
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        // API Keys
        if (false === get_option('streaming_guide_tmdb_api_key')) {
            add_option('streaming_guide_tmdb_api_key', '');
        }
        
        if (false === get_option('streaming_guide_openai_api_key')) {
            add_option('streaming_guide_openai_api_key', '');
        }
        
        // General settings
        if (false === get_option('streaming_guide_default_author')) {
            add_option('streaming_guide_default_author', get_current_user_id());
        }
        
        if (false === get_option('streaming_guide_auto_publish')) {
            add_option('streaming_guide_auto_publish', 1);
        }
        
        if (false === get_option('streaming_guide_featured_image_source')) {
            add_option('streaming_guide_featured_image_source', 'backdrop');
        }
        
        // SEO settings
        if (false === get_option('streaming_guide_auto_internal_links')) {
            add_option('streaming_guide_auto_internal_links', 1);
        }
        
        if (false === get_option('streaming_guide_auto_outbound_links')) {
            add_option('streaming_guide_auto_outbound_links', 1);
        }
        
        if (false === get_option('streaming_guide_optimize_titles')) {
            add_option('streaming_guide_optimize_titles', 1);
        }
        
        if (false === get_option('streaming_guide_enable_schema')) {
            add_option('streaming_guide_enable_schema', 1);
        }
        
        // Social media settings
        if (false === get_option('streaming_guide_auto_share_facebook')) {
            add_option('streaming_guide_auto_share_facebook', 0);
        }
        
        // Legacy options structure (for compatibility)
        if (false === get_option('streaming_guide_options')) {
            $defaults = [
                'tmdb_api_key' => get_option('streaming_guide_tmdb_api_key', ''),
                'openai_api_key' => get_option('streaming_guide_openai_api_key', ''),
                'auto_share_facebook' => '0',
                'facebook_page_id' => '',
                'facebook_access_token' => '',
            ];
            update_option('streaming_guide_options', $defaults);
        }
    }
    
    /**
     * Register plugin settings
     */
    private function register_settings() {
        // API Settings
        register_setting('streaming_guide_settings', 'streaming_guide_tmdb_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('streaming_guide_settings', 'streaming_guide_openai_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // General Settings
        register_setting('streaming_guide_settings', 'streaming_guide_default_author', array(
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('streaming_guide_settings', 'streaming_guide_auto_publish', array(
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('streaming_guide_settings', 'streaming_guide_featured_image_source', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // SEO Settings
        register_setting('streaming_guide_seo_settings', 'streaming_guide_auto_internal_links', array(
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('streaming_guide_seo_settings', 'streaming_guide_auto_outbound_links', array(
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('streaming_guide_seo_settings', 'streaming_guide_optimize_titles', array(
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('streaming_guide_seo_settings', 'streaming_guide_enable_schema', array(
            'sanitize_callback' => 'absint'
        ));
    }

    /**
     * Fired on plugin deactivation.
     */
    public function deactivate() {
        // Clear all scheduled cron events to prevent errors
        $cron_hooks = [
            'streaming_guide_weekly_cron',
            'streaming_guide_monthly_cron',
            'streaming_guide_trending_cron',
            'streaming_guide_run_generator',
            'streaming_guide_post_generated',
            'streaming_guide_process_generation',
            'streaming_guide_share_to_social',
            'streaming_guide_cleanup_history'
        ];
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        flush_rewrite_rules();
    }
}

/**
 * Ensures the main plugin class is only loaded once.
 * @return Streaming_Guide_Plugin The singleton instance.
 */
function streaming_guide_run() {
    return Streaming_Guide_Plugin::get_instance();
}

// Let's get this thing started!
streaming_guide_run();