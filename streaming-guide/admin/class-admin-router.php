<?php
/**
 * Admin Router
 * 
 * Handles admin menu and asset loading
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Admin_Router {
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Initialize admin handler
        require_once plugin_dir_path(__FILE__) . 'class-admin-handler.php';
        new Streaming_Guide_Admin_Handler();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Streaming Guide', 'streaming-guide'),
            __('Streaming Guide', 'streaming-guide'),
            'manage_options',
            'streaming-guide',
            array($this, 'render_admin_page'),
            'dashicons-video-alt3',
            30
        );
        
        // Submenus
        add_submenu_page(
            'streaming-guide',
            __('Generate Content', 'streaming-guide'),
            __('Generate', 'streaming-guide'),
            'manage_options',
            'streaming-guide',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'streaming-guide',
            __('Content History', 'streaming-guide'),
            __('History', 'streaming-guide'),
            'manage_options',
            'streaming-guide-history',
            array($this, 'render_history_page')
        );
        
        add_submenu_page(
            'streaming-guide',
            __('Settings', 'streaming-guide'),
            __('Settings', 'streaming-guide'),
            'manage_options',
            'streaming-guide-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'streaming-guide',
            __('SEO Management', 'streaming-guide'),
            __('SEO Tools', 'streaming-guide'),
            'manage_options',
            'streaming-guide-seo',
            array($this, 'render_seo_settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'streaming-guide') === false) {
            return;
        }
        
        // Enqueue our custom admin CSS
        wp_enqueue_style(
            'streaming-guide-admin',
            STREAMING_GUIDE_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            STREAMING_GUIDE_VERSION
        );
        
        // Enqueue our custom admin JS
        wp_enqueue_script(
            'streaming-guide-admin',
            STREAMING_GUIDE_PLUGIN_URL . 'admin/assets/admin.js',
            array('jquery', 'wp-element', 'wp-components', 'wp-api-fetch'),
            STREAMING_GUIDE_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('streaming-guide-admin', 'streamingGuideAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'streaming_guide_nonce' => wp_create_nonce('streaming_guide_generate'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'nonce_action' => 'streaming_guide_generate',
            'nonce_field' => 'streaming_guide_nonce'
        ));
    }
    
    /**
     * Render main admin page
     */
    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'generate';
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Streaming Guide Generator', 'streaming-guide') . '</h1>';
        
        // Navigation tabs
        $this->render_navigation($current_tab);
        
        // Tab content
        switch ($current_tab) {
            case 'settings':
                $this->render_settings_tab();
                break;
            case 'status':
                $this->render_status_tab();
                break;
            case 'social':
                $this->render_social_tab();
                break;
            default:
                $this->render_generate_tab();
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render navigation tabs
     */
    private function render_navigation($current_tab) {
        $tabs = array(
            'generate' => __('Generate Content', 'streaming-guide'),
            'settings' => __('API Settings', 'streaming-guide'),
            'status' => __('Status & Testing', 'streaming-guide'),
            'social' => __('Social Media', 'streaming-guide')
        );
        
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $name) {
            $class = ($tab === $current_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = admin_url('admin.php?page=streaming-guide&tab=' . $tab);
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($name) . '</a>';
        }
        echo '</nav>';
    }
    
    /**
     * Render generate tab content
     */
    private function render_generate_tab() {
        ?>
        <div class="streaming-guide-generators">
            <div class="generator-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin:20px 0;">
                
                <!-- Weekly New Releases -->
                <div class="generator-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('Weekly New Releases', 'streaming-guide'); ?></h3>
                    <p><?php _e('Generate articles about the latest weekly additions to streaming platforms.', 'streaming-guide'); ?></p>
                    
                    <form class="generator-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Platform:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="platform" required>
                                        <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                                        <option value="netflix">Netflix</option>
                                        <option value="hulu">Hulu</option>
                                        <option value="disney">Disney+</option>
                                        <option value="amazon">Amazon Prime Video</option>
                                        <option value="apple">Apple TV+</option>
                                        <option value="hbo">HBO Max</option>
                                        <option value="paramount">Paramount+</option>
                                        <option value="all">All Platforms</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-primary generate-btn" data-type="weekly">
                            <?php _e('Generate Weekly Content', 'streaming-guide'); ?>
                        </button>
                        <div class="generation-status" style="display:none;margin-top:10px;"></div>
                        <div class="generation-result" style="display:none;margin-top:10px;"></div>
                    </form>
                </div>

                <!-- Monthly Roundups -->
                <div class="generator-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('Monthly Roundups', 'streaming-guide'); ?></h3>
                    <p><?php _e('Create comprehensive monthly guides with the best content.', 'streaming-guide'); ?></p>
                    
                    <form class="generator-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Platform:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="platform" required>
                                        <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                                        <option value="netflix">Netflix</option>
                                        <option value="hulu">Hulu</option>
                                        <option value="disney">Disney+</option>
                                        <option value="amazon">Amazon Prime Video</option>
                                        <option value="apple">Apple TV+</option>
                                        <option value="hbo">HBO Max</option>
                                        <option value="paramount">Paramount+</option>
                                        <option value="all">All Platforms</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Month:', 'streaming-guide'); ?></th>
                                <td>
                                    <input type="month" name="month" value="<?php echo date('Y-m'); ?>" />
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-primary generate-btn" data-type="monthly">
                            <?php _e('Generate Monthly Content', 'streaming-guide'); ?>
                        </button>
                        <div class="generation-status" style="display:none;margin-top:10px;"></div>
                        <div class="generation-result" style="display:none;margin-top:10px;"></div>
                    </form>
                </div>
                
                <!-- Content Spotlights -->
                <div class="generator-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('Content Spotlights', 'streaming-guide'); ?></h3>
                    <p><?php _e('Create in-depth spotlight articles on specific movies or shows.', 'streaming-guide'); ?></p>
                    
                    <form class="generator-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Platform:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="platform" required>
                                        <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                                        <option value="netflix">Netflix</option>
                                        <option value="hulu">Hulu</option>
                                        <option value="disney">Disney+</option>
                                        <option value="amazon">Amazon Prime Video</option>
                                        <option value="apple">Apple TV+</option>
                                        <option value="hbo">HBO Max</option>
                                        <option value="paramount">Paramount+</option>
                                        <option value="all">All Platforms</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Search Content:', 'streaming-guide'); ?></th>
                                <td>
                                    <input type="text" id="tmdb-search-query" placeholder="<?php _e('Search for movies/shows...', 'streaming-guide'); ?>" style="width:100%;margin-bottom:5px;" />
                                    <select id="tmdb-search-type">
                                        <option value="multi"><?php _e('Movies & TV Shows', 'streaming-guide'); ?></option>
                                        <option value="movie"><?php _e('Movies Only', 'streaming-guide'); ?></option>
                                        <option value="tv"><?php _e('TV Shows Only', 'streaming-guide'); ?></option>
                                    </select>
                                    <button type="button" id="tmdb-search-btn" class="button" style="margin-left:5px;">
                                        <?php _e('Search', 'streaming-guide'); ?>
                                    </button>
                                    <div id="tmdb-search-results" style="display:none;margin-top:10px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('TMDB ID:', 'streaming-guide'); ?></th>
                                <td>
                                    <input type="number" name="tmdb_id" id="spotlight-tmdb-id" placeholder="<?php _e('Enter TMDB ID or search above', 'streaming-guide'); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Media Type:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="media_type" id="spotlight-media-type">
                                        <option value="movie"><?php _e('Movie', 'streaming-guide'); ?></option>
                                        <option value="tv"><?php _e('TV Show', 'streaming-guide'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-primary generate-btn" data-type="spotlight">
                            <?php _e('Generate Spotlight', 'streaming-guide'); ?>
                        </button>
                        <div class="generation-status" style="display:none;margin-top:10px;"></div>
                        <div class="generation-result" style="display:none;margin-top:10px;"></div>
                    </form>
                </div>

                <!-- Trending Content -->
                <div class="generator-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('Trending Content', 'streaming-guide'); ?></h3>
                    <p><?php _e('Generate articles about currently trending movies and shows.', 'streaming-guide'); ?></p>
                    
                    <form class="generator-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Platform:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="platform" required>
                                        <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                                        <option value="netflix">Netflix</option>
                                        <option value="hulu">Hulu</option>
                                        <option value="disney">Disney+</option>
                                        <option value="amazon">Amazon Prime Video</option>
                                        <option value="apple">Apple TV+</option>
                                        <option value="hbo">HBO Max</option>
                                        <option value="paramount">Paramount+</option>
                                        <option value="all">All Platforms</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Content Type:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="content_type">
                                        <option value="mixed"><?php _e('Movies & TV Shows', 'streaming-guide'); ?></option>
                                        <option value="movie"><?php _e('Movies Only', 'streaming-guide'); ?></option>
                                        <option value="tv"><?php _e('TV Shows Only', 'streaming-guide'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-primary generate-btn" data-type="trending">
                            <?php _e('Generate Trending Content', 'streaming-guide'); ?>
                        </button>
                        <div class="generation-status" style="display:none;margin-top:10px;"></div>
                        <div class="generation-result" style="display:none;margin-top:10px;"></div>
                    </form>
                </div>

                <!-- Top 10 Lists -->
                <div class="generator-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('Top 10 Lists', 'streaming-guide'); ?></h3>
                    <p><?php _e('Create curated top 10 lists for different categories.', 'streaming-guide'); ?></p>
                    
                    <form class="generator-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Platform:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="platform" required>
                                        <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                                        <option value="netflix">Netflix</option>
                                        <option value="hulu">Hulu</option>
                                        <option value="disney">Disney+</option>
                                        <option value="amazon">Amazon Prime Video</option>
                                        <option value="apple">Apple TV+</option>
                                        <option value="hbo">HBO Max</option>
                                        <option value="paramount">Paramount+</option>
                                        <option value="all">All Platforms</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-primary generate-btn" data-type="top10">
                            <?php _e('Generate Top 10 List', 'streaming-guide'); ?>
                        </button>
                        <div class="generation-status" style="display:none;margin-top:10px;"></div>
                        <div class="generation-result" style="display:none;margin-top:10px;"></div>
                    </form>
                </div>

                <!-- Seasonal Content -->
                <div class="generator-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('Seasonal Content', 'streaming-guide'); ?></h3>
                    <p><?php _e('Generate seasonal and holiday-themed content recommendations.', 'streaming-guide'); ?></p>
                    
                    <form class="generator-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Platform:', 'streaming-guide'); ?></th>
                                <td>
                                    <select name="platform" required>
                                        <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                                        <option value="netflix">Netflix</option>
                                        <option value="hulu">Hulu</option>
                                        <option value="disney">Disney+</option>
                                        <option value="amazon">Amazon Prime Video</option>
                                        <option value="apple">Apple TV+</option>
                                        <option value="hbo">HBO Max</option>
                                        <option value="paramount">Paramount+</option>
                                        <option value="all">All Platforms</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-primary generate-btn" data-type="seasonal">
                            <?php _e('Generate Seasonal Content', 'streaming-guide'); ?>
                        </button>
                        <div class="generation-status" style="display:none;margin-top:10px;"></div>
                        <div class="generation-result" style="display:none;margin-top:10px;"></div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Generate Section -->
            <div class="quick-generate-section" style="margin-top:30px;padding:20px;background:#f9f9f9;border-radius:4px;">
                <h3><?php _e('Quick Generate', 'streaming-guide'); ?></h3>
                <p><?php _e('Generate content quickly without navigating through tabs.', 'streaming-guide'); ?></p>
                
                <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                    <div>
                        <label><?php _e('Type:', 'streaming-guide'); ?></label><br>
                        <select id="quick-type">
                            <option value="weekly"><?php _e('Weekly', 'streaming-guide'); ?></option>
                            <option value="monthly"><?php _e('Monthly', 'streaming-guide'); ?></option>
                            <option value="trending"><?php _e('Trending', 'streaming-guide'); ?></option>
                            <option value="top10"><?php _e('Top 10', 'streaming-guide'); ?></option>
                            <option value="seasonal"><?php _e('Seasonal', 'streaming-guide'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label><?php _e('Platform:', 'streaming-guide'); ?></label><br>
                        <select id="quick-platform">
                            <option value="netflix">Netflix</option>
                            <option value="hulu">Hulu</option>
                            <option value="disney">Disney+</option>
                            <option value="amazon">Amazon Prime Video</option>
                            <option value="apple">Apple TV+</option>
                            <option value="hbo">HBO Max</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" id="quick-generate-btn" class="button button-primary">
                            <?php _e('Generate Now', 'streaming-guide'); ?>
                        </button>
                    </div>
                </div>
                <div id="quick-generation-status" style="display:none;margin-top:10px;"></div>
                <div id="quick-generation-result" style="display:none;margin-top:10px;"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <div class="streaming-guide-settings">
            <h2><?php _e('API Configuration', 'streaming-guide'); ?></h2>
            <p><?php _e('Configure your API keys and plugin settings.', 'streaming-guide'); ?></p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('streaming_guide_settings');
                do_settings_sections('streaming_guide_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tmdb_api_key"><?php _e('TMDB API Key', 'streaming-guide'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="tmdb_api_key" 
                                   name="streaming_guide_tmdb_api_key" 
                                   value="<?php echo esc_attr(get_option('streaming_guide_tmdb_api_key', '')); ?>" 
                                   size="50" 
                                   placeholder="<?php _e('Enter your TMDB API key', 'streaming-guide'); ?>" />
                            <p class="description">
                                <?php _e('Get your free API key from', 'streaming-guide'); ?> 
                                <a href="https://www.themoviedb.org/settings/api" target="_blank">TMDB</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key"><?php _e('OpenAI API Key', 'streaming-guide'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="openai_api_key" 
                                   name="streaming_guide_openai_api_key" 
                                   value="<?php echo esc_attr(get_option('streaming_guide_openai_api_key', '')); ?>" 
                                   size="50" 
                                   placeholder="<?php _e('Enter your OpenAI API key', 'streaming-guide'); ?>" />
                            <p class="description">
                                <?php _e('Get your API key from', 'streaming-guide'); ?> 
                                <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_publish"><?php _e('Auto Publish', 'streaming-guide'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="auto_publish" 
                                       name="streaming_guide_auto_publish" 
                                       value="1" 
                                       <?php checked(get_option('streaming_guide_auto_publish', 1)); ?> />
                                <?php _e('Automatically publish generated articles', 'streaming-guide'); ?>
                            </label>
                            <p class="description">
                                <?php _e('If disabled, articles will be saved as drafts for review.', 'streaming-guide'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'streaming-guide')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render status tab
     */
    private function render_status_tab() {
        ?>
        <div class="streaming-guide-status">
            <h2><?php _e('System Status', 'streaming-guide'); ?></h2>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;">
                
                <!-- System Information -->
                <div class="status-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('System Information', 'streaming-guide'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Plugin Version:', 'streaming-guide'); ?></th>
                            <td><?php echo defined('STREAMING_GUIDE_VERSION') ? STREAMING_GUIDE_VERSION : '1.0.0'; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('WordPress Version:', 'streaming-guide'); ?></th>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('PHP Version:', 'streaming-guide'); ?></th>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Memory Limit:', 'streaming-guide'); ?></th>
                            <td><?php echo ini_get('memory_limit'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('cURL Enabled:', 'streaming-guide'); ?></th>
                            <td><?php echo function_exists('curl_version') ? '✅ Yes' : '❌ No'; ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- API Status -->
                <div class="status-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;">
                    <h3><?php _e('API Configuration', 'streaming-guide'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('TMDB API Key:', 'streaming-guide'); ?></th>
                            <td><?php echo get_option('streaming_guide_tmdb_api_key') ? '✅ Set' : '❌ Missing'; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('OpenAI API Key:', 'streaming-guide'); ?></th>
                            <td><?php echo get_option('streaming_guide_openai_api_key') ? '✅ Set' : '❌ Missing'; ?></td>
                        </tr>
                    </table>
                    
                    <div style="margin-top:20px;">
                        <button type="button" class="button test-api-btn" data-api-type="tmdb">
                            <?php _e('Test TMDB Connection', 'streaming-guide'); ?>
                        </button>
                        <div id="tmdb-test-result" style="margin-top:10px;"></div>
                        
                        <button type="button" class="button test-api-btn" data-api-type="openai" style="margin-top:10px;">
                            <?php _e('Test OpenAI Connection', 'streaming-guide'); ?>
                        </button>
                        <div id="openai-test-result" style="margin-top:10px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render social tab
     */
    private function render_social_tab() {
        ?>
        <div class="streaming-guide-social">
            <h2><?php _e('Social Media Integration', 'streaming-guide'); ?></h2>
            <p><?php _e('Social media features are available but require additional configuration.', 'streaming-guide'); ?></p>
            
            <div class="notice notice-info">
                <p><?php _e('Social media functionality requires additional setup. Check the documentation for configuration instructions.', 'streaming-guide'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render history page
     */
    public function render_history_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Content Generation History', 'streaming-guide') . '</h1>';
        $this->display_generation_history();
        echo '</div>';
    }
    
    /**
     * Display generation history
     */
    private function display_generation_history() {
        global $wpdb;
        $table = $wpdb->prefix . 'streaming_guide_history';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            echo '<div class="notice notice-warning"><p>' . __('History table not found. Please deactivate and reactivate the plugin.', 'streaming-guide') . '</p></div>';
            return;
        }
        
        // Get recent generations
        $generations = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT 50"
        );
        
        if (empty($generations)) {
            echo '<p>' . __('No generation history found.', 'streaming-guide') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Date', 'streaming-guide') . '</th>';
        echo '<th>' . __('Type', 'streaming-guide') . '</th>';
        echo '<th>' . __('Platform', 'streaming-guide') . '</th>';
        echo '<th>' . __('Status', 'streaming-guide') . '</th>';
        echo '<th>' . __('Post', 'streaming-guide') . '</th>';
        echo '<th>' . __('Actions', 'streaming-guide') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($generations as $gen) {
            echo '<tr>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gen->created_at))) . '</td>';
            echo '<td>' . esc_html(ucfirst($gen->generator_type)) . '</td>';
            echo '<td>' . esc_html(ucfirst(str_replace('_', ' ', $gen->platform))) . '</td>';
            echo '<td><span class="status-' . esc_attr($gen->status) . '">' . esc_html(ucfirst($gen->status)) . '</span></td>';
            echo '<td>';
            if ($gen->post_id && get_post($gen->post_id)) {
                echo '<a href="' . esc_url(get_permalink($gen->post_id)) . '" target="_blank">View</a> | ';
                echo '<a href="' . esc_url(get_edit_post_link($gen->post_id)) . '">Edit</a>';
            } else {
                echo '—';
            }
            echo '</td>';
            echo '<td>';
            if ($gen->post_id && get_post($gen->post_id)) {
                echo '<form method="post" style="display:inline;">';
                echo '<input type="hidden" name="action" value="delete_post" />';
                echo '<input type="hidden" name="post_id" value="' . esc_attr($gen->post_id) . '" />';
                echo '<input type="hidden" name="generation_id" value="' . esc_attr($gen->id) . '" />';
                echo '<button type="submit" class="button button-secondary button-small" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this post?', 'streaming-guide')) . '\')">' . __('Delete', 'streaming-guide') . '</button>';
                echo '</form>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Add CSS for status indicators
        echo '<style>
        .status-pending { color: #f56e28; }
        .status-processing { color: #0073aa; }
        .status-success { color: #00a32a; }
        .status-failed { color: #d63638; }
        .status-cancelled { color: #666; }
        .button-small { padding: 2px 8px; font-size: 11px; height: auto; }
        </style>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $this->render_admin_page();
    }
    
    /**
     * Render SEO settings page
     */
    public function render_seo_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('SEO Management', 'streaming-guide') . '</h1>';
        echo '<p>' . __('SEO tools and optimization features.', 'streaming-guide') . '</p>';
        echo '</div>';
    }
}