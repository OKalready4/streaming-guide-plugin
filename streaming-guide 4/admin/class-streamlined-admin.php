<?php
/**
 * Complete Integrated Admin Interface for Streaming Guide Pro
 * Includes content generation, social media integration, and automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Streamlined_Admin {
    private $tmdb;
    private $openai;
    private $facebook_admin;
    
    public function __construct($tmdb, $openai) {
        $this->tmdb = $tmdb;
        $this->openai = $openai;
        
        // Initialize Facebook admin
        if (class_exists('Streaming_Guide_Facebook_Admin')) {
            $this->facebook_admin = new Streaming_Guide_Facebook_Admin();
        }
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    /**
     * Add comprehensive admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Streaming Guide Pro', 'streaming-guide'),
            __('Streaming Guide', 'streaming-guide'),
            'manage_options',
            'streaming-guide-pro',
            array($this, 'render_main_page'),
            'dashicons-video-alt3',
            30
        );
        
        add_submenu_page(
            'streaming-guide-pro',
            __('Generate Content', 'streaming-guide'),
            __('Generate', 'streaming-guide'),
            'manage_options',
            'streaming-guide-pro',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'streaming-guide-pro',
            __('Social Media', 'streaming-guide'),
            __('Social Media', 'streaming-guide'),
            'manage_options',
            'streaming-guide-social',
            array($this, 'render_social_page')
        );
        
        add_submenu_page(
            'streaming-guide-pro',
            __('Automation Settings', 'streaming-guide'),
            __('Automation', 'streaming-guide'),
            'manage_options',
            'streaming-guide-automation',
            array($this, 'render_automation_page')
        );
        
        add_submenu_page(
            'streaming-guide-pro',
            __('API Settings', 'streaming-guide'),
            __('Settings', 'streaming-guide'),
            'manage_options',
            'streaming-guide-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'streaming-guide-pro',
            __('Credits & Attribution', 'streaming-guide'),
            __('Credits', 'streaming-guide'),
            'manage_options',
            'streaming-guide-credits',
            array($this, 'render_credits_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // API Settings
        register_setting('streaming_guide_api_settings', 'streaming_guide_tmdb_api_key');
        register_setting('streaming_guide_api_settings', 'streaming_guide_openai_api_key');
        
        // Automation Settings
        register_setting('streaming_guide_automation', 'streaming_guide_auto_weekly');
        register_setting('streaming_guide_automation', 'streaming_guide_auto_trending');
        register_setting('streaming_guide_automation', 'streaming_guide_auto_spotlight');
        register_setting('streaming_guide_automation', 'streaming_guide_trending_count');
        register_setting('streaming_guide_automation', 'streaming_guide_spotlight_count');
        register_setting('streaming_guide_automation', 'streaming_guide_include_trailers');
        
        // Social Media Settings
        register_setting('streaming_guide_social', 'streaming_guide_facebook_page_id');
        register_setting('streaming_guide_social', 'streaming_guide_facebook_access_token');
        register_setting('streaming_guide_social', 'streaming_guide_auto_share_facebook');
        register_setting('streaming_guide_social', 'streaming_guide_share_delay');
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['streaming_guide_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'streaming_guide_settings')) {
            wp_die('Security check failed');
        }
        
        switch ($_POST['streaming_guide_action']) {
            case 'save_api_settings':
                $this->save_api_settings();
                break;
            case 'save_automation_settings':
                $this->save_automation_settings();
                break;
            case 'save_social_settings':
                $this->save_social_settings();
                break;
        }
    }
    
    /**
     * Save API settings
     */
    private function save_api_settings() {
        if (isset($_POST['tmdb_api_key'])) {
            update_option('streaming_guide_tmdb_api_key', sanitize_text_field($_POST['tmdb_api_key']));
        }
        
        if (isset($_POST['openai_api_key'])) {
            update_option('streaming_guide_openai_api_key', sanitize_text_field($_POST['openai_api_key']));
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('API settings saved successfully!', 'streaming-guide') . '</p></div>';
        });
    }
    
    /**
     * Save automation settings
     */
    private function save_automation_settings() {
        update_option('streaming_guide_auto_weekly', isset($_POST['auto_weekly']) ? 1 : 0);
        update_option('streaming_guide_auto_trending', isset($_POST['auto_trending']) ? 1 : 0);
        update_option('streaming_guide_auto_spotlight', isset($_POST['auto_spotlight']) ? 1 : 0);
        update_option('streaming_guide_trending_count', intval($_POST['trending_count'] ?? 5));
        update_option('streaming_guide_spotlight_count', intval($_POST['spotlight_count'] ?? 3));
        update_option('streaming_guide_include_trailers', isset($_POST['include_trailers']) ? 1 : 0);
        
    // Reschedule cron events based on new settings
    $this->reschedule_automation_events();
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Automation settings saved successfully!', 'streaming-guide') . '</p></div>';
        });
    }
    
    /**
     * Save social media settings
     */
    private function save_social_settings() {
        update_option('streaming_guide_facebook_page_id', sanitize_text_field($_POST['facebook_page_id'] ?? ''));
        update_option('streaming_guide_facebook_access_token', sanitize_text_field($_POST['facebook_access_token'] ?? ''));
        update_option('streaming_guide_auto_share_facebook', isset($_POST['auto_share_facebook']) ? 1 : 0);
        update_option('streaming_guide_share_delay', intval($_POST['share_delay'] ?? 5));
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Social media settings saved successfully!', 'streaming-guide') . '</p></div>';
        });
    }
    
    /**
     * Render main generation page
     */
    public function render_main_page() {
        ?>
        <div class="wrap streaming-guide-admin">
            <div class="admin-header">
                <h1><?php _e('Streaming Guide Pro', 'streaming-guide'); ?></h1>
                <p class="subtitle"><?php _e('AI-powered content generation with social media integration', 'streaming-guide'); ?></p>
            </div>
            
            <!-- API Status Check -->
            <div class="api-status-bar">
                <div class="status-item tmdb-status">
                    <span class="status-label">TMDB API:</span>
                    <span class="status-indicator" id="tmdb-status">Checking...</span>
                </div>
                <div class="status-item openai-status">
                    <span class="status-label">OpenAI API:</span>
                    <span class="status-indicator" id="openai-status">Checking...</span>
                </div>
                <button type="button" class="button" id="test-apis"><?php _e('Test APIs', 'streaming-guide'); ?></button>
            </div>
            
            <!-- Content Generators -->
            <div class="generator-grid">
                
                <!-- Weekly Generator -->
                <div class="generator-card weekly-generator">
                    <div class="card-header">
                        <h2><?php _e('Weekly New Releases', 'streaming-guide'); ?></h2>
                        <span class="generator-type">Automated</span>
                    </div>
                    <div class="card-content">
                        <p><?php _e('Generate comprehensive weekly roundups of new releases across all major streaming platforms. Includes trailers, ratings, and viewing recommendations.', 'streaming-guide'); ?></p>
                        
                        <form class="generator-form" data-type="weekly">
                            <div class="form-group">
                                <label for="weekly-platform"><?php _e('Platform:', 'streaming-guide'); ?></label>
                                <select name="platform" id="weekly-platform" required>
                                    <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                                    <option value="all"><?php _e('All Platforms', 'streaming-guide'); ?></option>
                                    <option value="netflix">Netflix</option>
                                    <option value="hulu">Hulu</option>
                                    <option value="disney">Disney+</option>
                                    <option value="hbo">HBO Max</option>
                                    <option value="amazon">Amazon Prime Video</option>
                                    <option value="apple">Apple TV+</option>
                                    <option value="paramount">Paramount+</option>
                                </select>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="button button-primary generate-btn">
                                    <?php _e('Generate Weekly Content', 'streaming-guide'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="generation-result"></div>
                    </div>
                </div>
                
                <!-- Spotlight Generator -->
                <div class="generator-card spotlight-generator">
                    <div class="card-header">
                        <h2><?php _e('Content Spotlight', 'streaming-guide'); ?></h2>
                        <span class="generator-type">Manual + Auto</span>
                    </div>
                    <div class="card-content">
                        <p><?php _e('Create in-depth spotlight articles on specific movies or TV shows. Perfect for detailed reviews and analysis.', 'streaming-guide'); ?></p>
                        
                        <form class="generator-form" data-type="spotlight">
                            <div class="form-group">
                                <label for="spotlight-tmdb-id"><?php _e('TMDB ID:', 'streaming-guide'); ?></label>
                                <input type="number" name="tmdb_id" id="spotlight-tmdb-id" placeholder="Enter TMDB movie/show ID" required>
                                <small><?php _e('Find the ID in the TMDB URL (e.g., themoviedb.org/movie/12345)', 'streaming-guide'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="spotlight-media-type"><?php _e('Type:', 'streaming-guide'); ?></label>
                                <select name="media_type" id="spotlight-media-type" required>
                                    <option value="movie"><?php _e('Movie', 'streaming-guide'); ?></option>
                                    <option value="tv"><?php _e('TV Show', 'streaming-guide'); ?></option>
                                </select>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="button button-primary generate-btn">
                                    <?php _e('Generate Spotlight', 'streaming-guide'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="generation-result"></div>
                    </div>
                </div>
                
                <!-- Trending Generator -->
<div class="generator-card trending-generator">
    <div class="card-header">
        <h2><?php _e('Trending Analysis', 'streaming-guide'); ?></h2>
        <span class="generator-type">Automated</span>
    </div>
    <div class="card-content">
        <p><?php _e('Generate articles analyzing current trending movies and shows across platforms. Returns at least 5 trending items with detailed analysis.', 'streaming-guide'); ?></p>
        
        <form class="generator-form" data-type="trending">
            <div class="form-row">
                <label for="trending-platform"><?php _e('Platform:', 'streaming-guide'); ?></label>
                <select name="platform" id="trending-platform" required>
                    <option value="all"><?php _e('All Platforms', 'streaming-guide'); ?></option>
                    <option value="netflix">Netflix</option>
                    <option value="hulu">Hulu</option>
                    <option value="disney">Disney+</option>
                    <option value="hbo">HBO Max</option>
                    <option value="amazon">Amazon Prime Video</option>
                    <option value="apple">Apple TV+</option>
                    <option value="paramount">Paramount+</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button button-primary generate-btn">
                    <?php _e('Generate Trending Analysis', 'streaming-guide'); ?>
                </button>
            </div>
        </form>
        
        <div class="generation-result"></div>
            </div>
        </div>
                
            </div>
            
            <!-- Recent Generated Content -->
            <div class="recent-content-section">
                <h2><?php _e('Recent Generated Content', 'streaming-guide'); ?></h2>
                <?php $this->render_recent_content(); ?>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Render social media page
     */
    public function render_social_page() {
        ?>
        <div class="wrap streaming-guide-admin">
            <div class="admin-header">
                <h1><?php _e('Social Media Integration', 'streaming-guide'); ?></h1>
                <p class="subtitle"><?php _e('Configure automatic social media posting for your generated content', 'streaming-guide'); ?></p>
            </div>
            
            <!-- Facebook Integration -->
            <div class="card">
                <h2><?php _e('Facebook Page Integration', 'streaming-guide'); ?></h2>
                
                <!-- Connection Status -->
                <div class="connection-status">
                    <?php
                    $page_id = get_option('streaming_guide_facebook_page_id', '');
                    $access_token = get_option('streaming_guide_facebook_access_token', '');
                    ?>
                    
                    <?php if (!empty($page_id) && !empty($access_token)): ?>
                        <div class="status-connected">
                            <span class="status-indicator connected">✅</span>
                            <span><?php _e('Facebook credentials configured', 'streaming-guide'); ?></span>
                            <button type="button" id="test-facebook-connection" class="button test-api-btn" data-api-type="facebook">
                                <?php _e('Test Connection', 'streaming-guide'); ?>
                            </button>
                        </div>
                        <div id="facebook-test-result"></div>
                    <?php else: ?>
                        <div class="status-disconnected">
                            <span class="status-indicator error">❌</span>
                            <span><?php _e('Facebook not configured', 'streaming-guide'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Settings Form -->
                <form method="post" action="">
                    <?php wp_nonce_field('streaming_guide_settings'); ?>
                    <input type="hidden" name="streaming_guide_action" value="save_social_settings" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="facebook_page_id"><?php _e('Facebook Page ID', 'streaming-guide'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="facebook_page_id" 
                                       name="facebook_page_id" 
                                       value="<?php echo esc_attr($page_id); ?>" 
                                       class="regular-text" 
                                       placeholder="<?php _e('e.g., 123456789012345', 'streaming-guide'); ?>" />
                                <p class="description">
                                    <?php _e('Your Facebook Page ID. Find it in your Facebook Page settings.', 'streaming-guide'); ?>
                                    <a href="https://www.facebook.com/help/1503421039731588" target="_blank">
                                        <?php _e('How to find your Page ID', 'streaming-guide'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="facebook_access_token"><?php _e('Page Access Token', 'streaming-guide'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="facebook_access_token" 
                                       name="facebook_access_token" 
                                       value="<?php echo esc_attr($access_token); ?>" 
                                       class="large-text" 
                                       placeholder="<?php _e('Enter your Page Access Token', 'streaming-guide'); ?>" />
                                <p class="description">
                                    <?php _e('A Page Access Token with pages_manage_posts permission.', 'streaming-guide'); ?>
                                    <a href="https://developers.facebook.com/tools/explorer/" target="_blank">
                                        <?php _e('Generate token at Facebook Graph API Explorer', 'streaming-guide'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_share_facebook"><?php _e('Automatic Sharing', 'streaming-guide'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="auto_share_facebook" 
                                           name="auto_share_facebook" 
                                           value="1" 
                                           <?php checked(get_option('streaming_guide_auto_share_facebook', 0), 1); ?> />
                                    <?php _e('Automatically share new articles to Facebook', 'streaming-guide'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, new articles will be automatically posted to your Facebook page.', 'streaming-guide'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="share_delay"><?php _e('Sharing Delay', 'streaming-guide'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="share_delay" 
                                       name="share_delay" 
                                       value="<?php echo esc_attr(get_option('streaming_guide_share_delay', 5)); ?>" 
                                       min="0" 
                                       max="60" 
                                       class="small-text" />
                                <span><?php _e('minutes', 'streaming-guide'); ?></span>
                                <p class="description">
                                    <?php _e('How long to wait after article publication before sharing to Facebook. Set to 0 for immediate sharing.', 'streaming-guide'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Social Media Settings', 'streaming-guide')); ?>
                </form>
            </div>
            
            <!-- Setup Instructions -->
            <div class="card">
                <h2><?php _e('Facebook Setup Instructions', 'streaming-guide'); ?></h2>
                
                <div class="setup-steps">
                    <div class="step">
                        <h3><?php _e('Step 1: Create a Facebook App', 'streaming-guide'); ?></h3>
                        <ol>
                            <li><?php _e('Go to', 'streaming-guide'); ?> <a href="https://developers.facebook.com/apps" target="_blank">Facebook Developers</a></li>
                            <li><?php _e('Click "Create App" and select "Business" type', 'streaming-guide'); ?></li>
                            <li><?php _e('Add your app name and contact email', 'streaming-guide'); ?></li>
                            <li><?php _e('Go to App Settings > Basic and note your App ID', 'streaming-guide'); ?></li>
                        </ol>
                    </div>
                    
                    <div class="step">
                        <h3><?php _e('Step 2: Get a Page Access Token', 'streaming-guide'); ?></h3>
                        <ol>
                            <li><?php _e('Go to', 'streaming-guide'); ?> <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
                            <li><?php _e('Select your app from the dropdown', 'streaming-guide'); ?></li>
                            <li><?php _e('Click "Generate Access Token" and log in to Facebook', 'streaming-guide'); ?></li>
                            <li><?php _e('Select your page and grant permissions: pages_manage_posts, pages_read_engagement', 'streaming-guide'); ?></li>
                            <li><?php _e('Copy the Page Access Token and paste it above', 'streaming-guide'); ?></li>
                        </ol>
                    </div>
                    
                    <div class="step">
                        <h3><?php _e('Step 3: Find Your Page ID', 'streaming-guide'); ?></h3>
                        <ol>
                            <li><?php _e('Go to your Facebook page', 'streaming-guide'); ?></li>
                            <li><?php _e('Click "About" tab', 'streaming-guide'); ?></li>
                            <li><?php _e('Scroll down to find "Page ID" or check the URL', 'streaming-guide'); ?></li>
                            <li><?php _e('Copy the numeric Page ID and paste it above', 'streaming-guide'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
            
        </div>
        <?php
    }
    
   /**
 * Render automation settings page
 */
public function render_automation_page() {
    ?>
    <div class="wrap streaming-guide-admin">
        <div class="admin-header">
            <h1><?php _e('Automation Settings', 'streaming-guide'); ?></h1>
            <p class="subtitle"><?php _e('Configure automated content generation schedules', 'streaming-guide'); ?></p>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('streaming_guide_settings'); ?>
            <input type="hidden" name="streaming_guide_action" value="save_automation_settings">
            
            <div class="card">
                <h2><?php _e('Automation Settings', 'streaming-guide'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Weekly Automation', 'streaming-guide'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_weekly" value="1" <?php checked(get_option('streaming_guide_auto_weekly', 1)); ?>>
                                <?php _e('Automatically generate weekly content every Sunday at 6 AM', 'streaming-guide'); ?>
                            </label>
                            <p class="description"><?php _e('Generates new release roundups for major platforms', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Trending Automation', 'streaming-guide'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_trending" value="1" <?php checked(get_option('streaming_guide_auto_trending', 1)); ?>>
                                <?php _e('Automatically generate trending analysis twice per week', 'streaming-guide'); ?>
                            </label>
                            <p class="description"><?php _e('Analyzes current trending content across all platforms', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- NEW: Spotlight Automation -->
                    <tr>
                        <th scope="row"><?php _e('Spotlight Automation', 'streaming-guide'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_spotlight" value="1" <?php checked(get_option('streaming_guide_auto_spotlight', 1)); ?>>
                                <?php _e('Automatically generate spotlight articles for big new releases', 'streaming-guide'); ?>
                            </label>
                            <p class="description"><?php _e('Creates in-depth reviews for major movie and TV premieres twice per week', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Include Trailers', 'streaming-guide'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_trailers" value="1" <?php checked(get_option('streaming_guide_include_trailers', 1)); ?>>
                                <?php _e('Always include trailers in generated content', 'streaming-guide'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically embeds YouTube trailers when available', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Trending Count', 'streaming-guide'); ?></th>
                        <td>
                            <input type="number" name="trending_count" value="<?php echo esc_attr(get_option('streaming_guide_trending_count', 5)); ?>" min="3" max="10">
                            <p class="description"><?php _e('Minimum number of trending items to include (3-10)', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- NEW: Spotlight Count -->
                    <tr>
                        <th scope="row"><?php _e('Spotlight Count', 'streaming-guide'); ?></th>
                        <td>
                            <input type="number" name="spotlight_count" value="<?php echo esc_attr(get_option('streaming_guide_spotlight_count', 3)); ?>" min="1" max="5">
                            <p class="description"><?php _e('Number of spotlight articles to generate per automation run (1-5)', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Automation Settings', 'streaming-guide')); ?>
            </div>
        </form>
        
        <!-- Automation Status -->
        <div class="card">
            <h2><?php _e('Automation Status', 'streaming-guide'); ?></h2>
            <div class="status-grid">
                <div class="status-card">
                    <h3><?php _e('Next Weekly Generation', 'streaming-guide'); ?></h3>
                    <p><?php echo $this->get_next_scheduled('streaming_guide_weekly_auto'); ?></p>
                </div>
                <div class="status-card">
                    <h3><?php _e('Next Trending Analysis', 'streaming-guide'); ?></h3>
                    <p><?php echo $this->get_next_scheduled('streaming_guide_trending_auto'); ?></p>
                </div>
                <!-- NEW: Spotlight Status -->
                <div class="status-card">
                    <h3><?php _e('Next Spotlight Article', 'streaming-guide'); ?></h3>
                    <p><?php echo $this->get_next_scheduled('streaming_guide_spotlight_auto'); ?></p>
                </div>
            </div>
        </div>
        
    </div>
    <?php
}
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap streaming-guide-admin">
            <div class="admin-header">
                <h1><?php _e('API Settings', 'streaming-guide'); ?></h1>
                <p class="subtitle"><?php _e('Configure your API keys for content generation', 'streaming-guide'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('streaming_guide_settings'); ?>
                <input type="hidden" name="streaming_guide_action" value="save_api_settings">
                
                <div class="card">
                    <h2><?php _e('API Configuration', 'streaming-guide'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('TMDB API Key', 'streaming-guide'); ?></th>
                            <td>
                                <input type="password" name="tmdb_api_key" value="<?php echo esc_attr(get_option('streaming_guide_tmdb_api_key')); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Get your API key from', 'streaming-guide'); ?> 
                                    <a href="https://www.themoviedb.org/settings/api" target="_blank">TMDB</a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('OpenAI API Key', 'streaming-guide'); ?></th>
                            <td>
                                <input type="password" name="openai_api_key" value="<?php echo esc_attr(get_option('streaming_guide_openai_api_key')); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Get your API key from', 'streaming-guide'); ?> 
                                    <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save API Settings', 'streaming-guide')); ?>
                </div>
            </form>
            
        </div>
        <?php
    }
    
    /**
     * Render credits page
     */
    public function render_credits_page() {
        if (class_exists('Streaming_Guide_TMDB_Attribution')) {
            Streaming_Guide_TMDB_Attribution::render_attribution_page();
        } else {
            echo '<div class="wrap"><h1>Credits</h1><p>Attribution system not loaded.</p></div>';
        }
    }
    
    /**
     * Render recent generated content
     */
    private function render_recent_content() {
        $recent_posts = get_posts(array(
            'meta_key' => 'generated_by',
            'meta_value' => 'streaming_guide',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($recent_posts)) {
            echo '<p>' . __('No content generated yet. Use the generators above to create your first article!', 'streaming-guide') . '</p>';
            return;
        }
        
        echo '<div class="recent-content-grid">';
        foreach ($recent_posts as $post) {
            $generator_type = get_post_meta($post->ID, 'generator_type', true);
            $platform = get_post_meta($post->ID, 'platform', true);
            ?>
            <div class="content-item">
                <h3><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a></h3>
                <div class="content-meta">
                    <span class="type"><?php echo esc_html(ucfirst($generator_type)); ?></span>
                    <span class="platform"><?php echo esc_html(ucfirst($platform)); ?></span>
                    <span class="date"><?php echo get_the_date('M j, Y', $post); ?></span>
                    <span class="status <?php echo $post->post_status; ?>"><?php echo ucfirst($post->post_status); ?></span>
                </div>
                <div class="content-actions">
                    <a href="<?php echo get_edit_post_link($post->ID); ?>" class="button button-small"><?php _e('Edit', 'streaming-guide'); ?></a>
                    <a href="<?php echo get_permalink($post->ID); ?>" class="button button-small" target="_blank"><?php _e('View', 'streaming-guide'); ?></a>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
    
    /**
 * Reschedule automation events based on settings
 */
private function reschedule_automation_events() {
    // Clear existing schedules
    wp_clear_scheduled_hook('streaming_guide_weekly_auto');
    wp_clear_scheduled_hook('streaming_guide_trending_auto');
    wp_clear_scheduled_hook('streaming_guide_spotlight_auto');
    
    // Reschedule based on settings
    if (get_option('streaming_guide_auto_weekly', 1)) {
        wp_schedule_event(strtotime('next Sunday 6:00 AM'), 'weekly', 'streaming_guide_weekly_auto');
    }
    
    if (get_option('streaming_guide_auto_trending', 1)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'twiceweekly', 'streaming_guide_trending_auto');
    }
    
    if (get_option('streaming_guide_auto_spotlight', 1)) {
        // Schedule for Tuesday and Friday at 10 AM
        wp_schedule_event(strtotime('next Tuesday 10:00 AM'), 'twiceweekly', 'streaming_guide_spotlight_auto');
    }
}
    
    /**
     * Get next scheduled time for automation
     */
    private function get_next_scheduled($hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            return wp_date('M j, Y g:i A', $timestamp);
        }
        return __('Not scheduled', 'streaming-guide');
    }
}