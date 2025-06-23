<?php
/**
 * Plugin Name: Streaming Guide Pro
 * Description: AI-powered streaming guide with automated content generation, social media integration, and comprehensive admin interface
 * Version: 2.0.0
 * Author: Your Name
 * Text Domain: streaming-guide
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STREAMING_GUIDE_VERSION', '2.0.0');
define('STREAMING_GUIDE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STREAMING_GUIDE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Prevent class redeclaration errors
if (!class_exists('Streaming_Guide_Pro')) {

    // Load core files ONLY if they don't exist
    $core_files = array(
        'Streaming_Guide_TMDB_API' => 'includes/class-streaming-guide-tmdb-api.php',
        'Streaming_Guide_OpenAI_API' => 'includes/class-streaming-guide-openai-api.php',
        'Streaming_Guide_Platforms' => 'includes/class-streaming-guide-platforms.php',
        'Streaming_Guide_Base_Generator' => 'includes/class-streaming-guide-base-generator.php',
        'Streaming_Guide_Automation_Manager'=> 'includes/class-automation-manager.php'
    );
    
    require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-tmdb-attribution.php';

    foreach ($core_files as $class_name => $file_path) {
        if (!class_exists($class_name)) {
            $full_path = STREAMING_GUIDE_PLUGIN_DIR . $file_path;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }

    // Load generators ONLY if they don't exist
    $generators = array('weekly', 'spotlight', 'trending');
    foreach ($generators as $generator) {
        $class_name = 'Streaming_Guide_' . ucfirst($generator) . '_Generator';
        if (!class_exists($class_name)) {
            $file_path = STREAMING_GUIDE_PLUGIN_DIR . "includes/generators/class-streaming-guide-{$generator}-generator.php";
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    // Load admin system
    if (is_admin()) {
        // Load admin handler first
        if (!class_exists('Streaming_Guide_Admin_Handler')) {
            $admin_handler_file = STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-admin-handler.php';
            if (file_exists($admin_handler_file)) {
                require_once $admin_handler_file;
            }
        }
        
        // Load streamlined admin
        if (!class_exists('Streaming_Guide_Streamlined_Admin')) {
            $streamlined_admin_file = STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-streamlined-admin.php';
            if (file_exists($streamlined_admin_file)) {
                require_once $streamlined_admin_file;
            }
        }
        
        // Add this with your other includes in streaming-guide.php
if (!class_exists('Streaming_Guide_Simple_SEO_Helper')) {
    require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-simple-seo-helper.php';
}
        
        // Load Facebook admin
        if (!class_exists('Streaming_Guide_Facebook_Admin')) {
            $facebook_admin_file = STREAMING_GUIDE_PLUGIN_DIR . 'admin/class-facebook-admin.php';
            if (file_exists($facebook_admin_file)) {
                require_once $facebook_admin_file;
            }
        }
        
        // Load social media system
        if (!class_exists('Streaming_Guide_Social_Media')) {
            $social_media_file = STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-social-media.php';
            if (file_exists($social_media_file)) {
                require_once $social_media_file;
            }
        }
        
        // Load ACF integration if ACF is active
        if (class_exists('ACF')) {
             $acf_integration_file = STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-acf-integration.php';
            if (file_exists($acf_integration_file)) {
                require_once $acf_integration_file;
            }
        }
    }

    // Load frontend handler
    if (!class_exists('Streaming_Guide_Frontend')) {
        $frontend_file = STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-frontend.php';
        if (file_exists($frontend_file)) {
            require_once $frontend_file;
        }
    }

    /**
     * Main Plugin Class - COMPLETE WORKING VERSION
     */
    class Streaming_Guide_Pro {
        private static $instance = null;
        private $tmdb;
        private $openai;
        private $admin_handler;
        private $streamlined_admin;
        private $facebook_admin;
        private $social_media;
        
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
            add_action('plugins_loaded', array($this, 'init'));
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
            
            // AJAX handlers - FIXED NAMES AND PARAMETERS
            add_action('wp_ajax_streaming_guide_generate', array($this, 'handle_ajax_generation'));
            add_action('wp_ajax_streaming_guide_test_apis', array($this, 'handle_ajax_test_apis'));
            add_action('wp_ajax_streaming_guide_test_connection', array($this, 'handle_ajax_test_apis'));
            add_action('wp_ajax_streaming_guide_test_facebook', array($this, 'handle_ajax_test_facebook'));
            
            // Automation hooks
            add_action('streaming_guide_weekly_auto', array($this, 'run_weekly_automation'));
            add_action('streaming_guide_trending_auto', array($this, 'run_trending_automation'));
            add_action('streaming_guide_spotlight_auto', array($this, 'run_spotlight_automation'));

            // Custom cron schedules
            add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        }
        
        /**
         * Initialize plugin
         */
        public function init() {
            // Load translations
            load_plugin_textdomain('streaming-guide', false, dirname(plugin_basename(__FILE__)) . '/languages');
            
            // Initialize APIs
            $this->tmdb = new Streaming_Guide_TMDB_API();
            $this->openai = new Streaming_Guide_OpenAI_API();
            
            // Initialize admin components if in admin
            if (is_admin()) {
                if (class_exists('Streaming_Guide_Admin_Handler')) {
                    $this->admin_handler = new Streaming_Guide_Admin_Handler();
                }
                
                if (class_exists('Streaming_Guide_Streamlined_Admin')) {
                    $this->streamlined_admin = new Streaming_Guide_Streamlined_Admin($this->tmdb, $this->openai);
                }
                
                if (class_exists('Streaming_Guide_Facebook_Admin')) {
                    $this->facebook_admin = new Streaming_Guide_Facebook_Admin();
                }
                
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            }
            
            // Initialize social media system
            if (class_exists('Streaming_Guide_Social_Media')) {
                $this->social_media = Streaming_Guide_Social_Media::get_instance();
            }
            
            // Initialize frontend if class exists
            if (class_exists('Streaming_Guide_Frontend')) {
                Streaming_Guide_Frontend::init();
            }
            
            // Frontend assets
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
            
            // Content filter
            add_filter('the_content', array($this, 'enhance_article_content'));
        }
        
        /**
 * FIXED: Handle AJAX generation - Handle spotlight properly
 */
public function handle_ajax_generation() {
    // Verify nonce and permissions
    $nonce = $_POST['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'streaming_guide_generate') || !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    // Get and validate parameters
    $type = sanitize_text_field($_POST['type'] ?? '');
    $platform = sanitize_text_field($_POST['platform'] ?? '');
    
    // Additional parameters for different generators
    $tmdb_id = sanitize_text_field($_POST['tmdb_id'] ?? '');
    $media_type = sanitize_text_field($_POST['media_type'] ?? 'movie');
    
    // Validation based on generator type
    if (empty($type)) {
        wp_send_json_error(array('message' => 'Missing required parameter: type'));
        return;
    }
    
    // Spotlight doesn't need platform, but needs TMDB ID
    if ($type === 'spotlight') {
        if (empty($tmdb_id)) {
            wp_send_json_error(array('message' => 'TMDB ID is required for spotlight articles'));
            return;
        }
        $platform = 'all'; // Default platform for spotlight
    } else {
        // Other generators need platform
        if (empty($platform)) {
            wp_send_json_error(array('message' => 'Missing required parameter: platform'));
            return;
        }
    }
    
    try {
        // Prepare options based on generator type
        $options = array(
            'include_trailers' => true,
            'auto_publish' => false,
            'auto_featured_image' => true,
            'seo_optimize' => true
        );
        
        // Add specific options for spotlight generator
        if ($type === 'spotlight') {
            $options['tmdb_id'] = intval($tmdb_id);
            $options['media_type'] = $media_type;
        }
        
        // Generate content
        $result = $this->generate_content($type, $platform, $options);
        
        if (is_wp_error($result)) {
    wp_send_json_error(array('message' => $result->get_error_message()));
    return;
    }

        // Handle multiple posts (for trending all platforms)
        if (is_array($result)) {
        wp_send_json_success(array(
        'message' => 'Multiple articles generated successfully!',
        'post_ids' => $result,
        'count' => count($result)
     ));
    } else if ($result) {
     wp_send_json_success(array(
        'message' => 'Article generated successfully!',
        'post_id' => $result,
        'edit_url' => get_edit_post_link($result, 'raw'),
        'view_url' => get_permalink($result)
     ));
        } else {
        wp_send_json_error(array('message' => 'Generation failed - no result returned'));
    }
        
        // Success response
        wp_send_json_success(array(
            'message' => 'Article generated successfully! Check your posts for review.',
            'post_id' => $result,
            'edit_url' => get_edit_post_link($result, 'raw'),
            'view_url' => get_permalink($result)
        ));
        
    } catch (Exception $e) {
        error_log('Streaming Guide Generation Error: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Generation error: ' . $e->getMessage()));
    }
}
        
        /**
         * FIXED: Handle API testing - PROPER PARAMETER HANDLING
         */
        public function handle_ajax_test_apis() {
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'streaming_guide_test') || !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Security check failed'));
                return;
            }
            
            // FIXED: Handle different parameter names from different calls
            $api_type = sanitize_text_field($_POST['api'] ?? $_POST['api_type'] ?? 'all');
            $results = array();
            
            try {
                // Test TMDB API
                if ($api_type === 'tmdb' || $api_type === 'all') {
                    $tmdb_test = $this->test_tmdb_connection();
                    $results['tmdb'] = array(
                        'status' => !is_wp_error($tmdb_test),
                        'message' => is_wp_error($tmdb_test) ? $tmdb_test->get_error_message() : 'TMDB connection successful'
                    );
                }
                
                // Test OpenAI API
                if ($api_type === 'openai' || $api_type === 'all') {
                    $openai_test = $this->test_openai_connection();
                    $results['openai'] = array(
                        'status' => !is_wp_error($openai_test),
                        'message' => is_wp_error($openai_test) ? $openai_test->get_error_message() : 'OpenAI connection successful'
                    );
                }
                
                wp_send_json_success($results);
                
            } catch (Exception $e) {
                wp_send_json_error(array('message' => 'Test failed: ' . $e->getMessage()));
            }
        }
        
        /**
         * Handle Facebook connection testing
         */
        public function handle_ajax_test_facebook() {
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'streaming_guide_test') || !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Security check failed'));
                return;
            }
            
            if ($this->social_media && method_exists($this->social_media, 'test_facebook_connection')) {
                $result = $this->social_media->test_facebook_connection();
                
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                } else {
                    wp_send_json_success(array(
                        'message' => 'Facebook connection successful',
                        'data' => $result
                    ));
                }
            } else {
                wp_send_json_error(array('message' => 'Facebook integration not available'));
            }
        }
        
        /**
         * Test TMDB connection
         */
        private function test_tmdb_connection() {
            if (!$this->tmdb) {
                return new WP_Error('tmdb_not_initialized', 'TMDB API not initialized');
            }
            
            // Test with a simple API call
            $test_result = $this->tmdb->make_request('/configuration');
            
            if (is_wp_error($test_result)) {
                return $test_result;
            }
            
            if (isset($test_result['images'])) {
                return true;
            }
            
            return new WP_Error('tmdb_invalid_response', 'Invalid TMDB API response');
        }
        
        /**
         * Test OpenAI connection
         */
        private function test_openai_connection() {
            if (!$this->openai) {
                return new WP_Error('openai_not_initialized', 'OpenAI API not initialized');
            }
            
            // Test with a simple request
            $test_messages = array(
                array(
                    'role' => 'user',
                    'content' => 'Hello, this is a test. Please respond with "Test successful".'
                )
            );
            
            $test_result = $this->openai->make_request($test_messages, 0.1, 10);
            
            if (is_wp_error($test_result)) {
                return $test_result;
            }
            
            if (is_string($test_result) && !empty($test_result)) {
                return true;
            }
            
            return new WP_Error('openai_invalid_response', 'Invalid OpenAI API response');
        }
        
        /**
         * Generate content - ENHANCED WITH PROPER OPTIONS HANDLING
         */
        private function generate_content($type, $platform, $options = array()) {
            $generator_class = 'Streaming_Guide_' . ucfirst($type) . '_Generator';
            
            if (!class_exists($generator_class)) {
                return new WP_Error('missing_generator', "Generator class not found: {$generator_class}");
            }
            
            try {
                $generator = new $generator_class($this->tmdb, $this->openai);
                
                // Call the appropriate method based on generator type
                if ($type === 'spotlight') {
                    // Spotlight needs special handling
                    return $generator->generate_article($platform, $options);
                } else {
                    // Weekly and trending generators
                    return $generator->generate_article($platform, $options);
                }
                
            } catch (Exception $e) {
                error_log("Generator instantiation error for {$generator_class}: " . $e->getMessage());
                return new WP_Error('generation_error', $e->getMessage());
            }
        }
        
        /**
         * Enqueue admin assets - ENHANCED WITH YOUR ADVANCED VERSIONS
         */
        public function enqueue_admin_assets($hook) {
            if (strpos($hook, 'streaming-guide') === false) {
                return;
            }
            
            // Admin CSS - Your advanced version
            wp_enqueue_style(
                'streaming-guide-admin',
                STREAMING_GUIDE_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                STREAMING_GUIDE_VERSION
            );
            
            // Admin JS - Your advanced version
            wp_enqueue_script(
                'streaming-guide-admin',
                STREAMING_GUIDE_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                STREAMING_GUIDE_VERSION,
                true
            );
            
            // Localize script - COMPLETE WITH ALL NONCES
            wp_localize_script('streaming-guide-admin', 'streamingGuideAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonces' => array(
                    'generate' => wp_create_nonce('streaming_guide_generate'),
                    'test' => wp_create_nonce('streaming_guide_test'),
                    'test_connection' => wp_create_nonce('streaming_guide_test'),
                    'facebook' => wp_create_nonce('streaming_guide_test')
                ),
                'strings' => array(
                    'generating' => __('Generating...', 'streaming-guide'),
                    'success' => __('Success!', 'streaming-guide'),
                    'error' => __('Error occurred', 'streaming-guide'),
                    'testing' => __('Testing...', 'streaming-guide')
                ),
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ));
        }
        
        /**
         * Enqueue frontend assets
         */
        public function enqueue_frontend_assets() {
            global $post;
            
            if ($post && get_post_meta($post->ID, 'generated_by', true) === 'streaming_guide') {
                wp_enqueue_style(
                    'streaming-guide-frontend',
                    STREAMING_GUIDE_PLUGIN_URL . 'assets/css/frontend.css',
                    array(),
                    STREAMING_GUIDE_VERSION
                );
                
                // Enqueue frontend JS if it exists
                $frontend_js = STREAMING_GUIDE_PLUGIN_DIR . 'assets/js/frontend.js';
                if (file_exists($frontend_js)) {
                    wp_enqueue_script(
                        'streaming-guide-frontend',
                        STREAMING_GUIDE_PLUGIN_URL . 'assets/js/frontend.js',
                        array('jquery'),
                        STREAMING_GUIDE_VERSION,
                        true
                    );
                }
            }
        }
        
        /**
         * Enhance article content
         */
        public function enhance_article_content($content) {
            global $post;
            
            if (!$post || get_post_meta($post->ID, 'generated_by', true) !== 'streaming_guide') {
                return $content;
            }
            
            return '<div class="streaming-guide-article">' . $content . '</div>';
        }
        
        /**
         * Add custom schedules
         */
        public function add_custom_schedules($schedules) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Weekly', 'streaming-guide')
            );
            
            $schedules['twiceweekly'] = array(
                'interval' => 3.5 * DAY_IN_SECONDS,
                'display' => __('Twice Weekly', 'streaming-guide')
            );
            
            return $schedules;
        }
        
        /**
         * Run weekly automation
         */
        public function run_weekly_automation() {
            if (!get_option('streaming_guide_auto_weekly', 0)) {
                return;
            }
            
            $platforms = array('netflix', 'hulu', 'disney', 'hbo', 'amazon');
            
            foreach ($platforms as $platform) {
                try {
                    $this->generate_content('weekly', $platform, array('auto_publish' => true));
                    sleep(60); // Delay between generations
                } catch (Exception $e) {
                    error_log('Weekly automation failed for ' . $platform . ': ' . $e->getMessage());
                }
            }
        }
        
        /**
         * Run trending automation
         */
        public function run_trending_automation() {
            if (!get_option('streaming_guide_auto_trending', 0)) {
                return;
            }
            
            try {
                $this->generate_content('trending', 'all', array('auto_publish' => true));
            } catch (Exception $e) {
                error_log('Trending automation failed: ' . $e->getMessage());
            }
        }
        
        /**
 * Run spotlight automation
 */
public function run_spotlight_automation() {
    if (!get_option('streaming_guide_auto_spotlight', 0)) {
        return;
    }
    
    if (class_exists('Streaming_Guide_Automation_Manager')) {
        $automation = new Streaming_Guide_Automation_Manager($this->tmdb, $this->openai);
        $automation->run_spotlight_automation();
    }
}
        
        /**
         * Plugin activation
         */
        public function activate() {
            // Create necessary options
            add_option('streaming_guide_auto_weekly', 0);
            add_option('streaming_guide_auto_trending', 0);
            add_option('streaming_guide_include_trailers', 1);
            
            // Create directories
            wp_mkdir_p(STREAMING_GUIDE_PLUGIN_DIR . 'logs');
            
            // Schedule automation if enabled
            if (get_option('streaming_guide_auto_weekly')) {
                if (!wp_next_scheduled('streaming_guide_weekly_auto')) {
                    wp_schedule_event(time(), 'weekly', 'streaming_guide_weekly_auto');
                }
            }
            
            if (get_option('streaming_guide_auto_trending')) {
                if (!wp_next_scheduled('streaming_guide_trending_auto')) {
                    wp_schedule_event(time(), 'twiceweekly', 'streaming_guide_trending_auto');
                }
            }
            
            if (get_option('streaming_guide_auto_spotlight')) {
                if (!wp_next_scheduled('streaming_guide_spotlight_auto')) {
                // Run twice a week - Tuesday and Friday
                wp_schedule_event(strtotime('next Friday 10:00 AM'), 'twiceweekly', 'streaming_guide_spotlight_auto');
                }
            }
            flush_rewrite_rules();
        }
        
        /**
         * Plugin deactivation
         */
        public function deactivate() {
            wp_clear_scheduled_hook('streaming_guide_weekly_auto');
            wp_clear_scheduled_hook('streaming_guide_trending_auto');
            flush_rewrite_rules();
        }
    }
    
    // Initialize the plugin
    Streaming_Guide_Pro::get_instance();
}
?>