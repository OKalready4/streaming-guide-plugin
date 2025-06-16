<?php
/**
 * Admin Router - Handles page rendering and navigation only
 * 
 * This file ONLY handles the display layer. All processing happens
 * in separate handlers to prevent "headers already sent" errors.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Admin_Router {
    private $form_processor;
    private $state_manager;
    private $error_handler;
    
    public function __construct() {
        // Hook into admin_init for form processing BEFORE any output
        add_action('admin_init', array($this, 'early_form_processing'), 1);
        
        // Admin menu - happens later
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue scripts/styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Process forms before ANY output to prevent header errors
     */
    public function early_form_processing() {
        // Only process on our admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'streaming-guide') === false) {
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Process any POST data before headers are sent
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
            $this->form_processor->process();
        }
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        if (!$this->form_processor) {
            require_once plugin_dir_path(__FILE__) . 'class-form-processor.php';
            require_once plugin_dir_path(__FILE__) . 'class-state-manager.php';
            require_once plugin_dir_path(__FILE__) . 'class-error-handler.php';
            
            $this->error_handler = new Streaming_Guide_Error_Handler();
            $this->state_manager = new Streaming_Guide_State_Manager();
            $this->form_processor = new Streaming_Guide_Form_Processor($this->state_manager, $this->error_handler);
        }
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
        if (strpos($hook, 'streaming-guide') === false) {
            return;
        }
        
        // Enqueue our custom admin CSS
        wp_enqueue_style(
            'streaming-guide-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue admin JS for AJAX
        wp_enqueue_script(
            'streaming-guide-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('streaming-guide-admin', 'streamingGuideAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('streaming_guide_ajax'),
            'strings' => array(
                'generating' => __('Generating content...', 'streaming-guide'),
                'success' => __('Content generated successfully!', 'streaming-guide'),
                'error' => __('An error occurred. Please try again.', 'streaming-guide')
            )
        ));
    }
    
    /**
     * Render main admin page - DISPLAY ONLY, no processing
     */
    public function render_admin_page() {
        // Ensure dependencies are loaded
        $this->load_dependencies();
        
        ?>
        <div class="wrap streaming-guide-admin">
            <h1><?php _e('Streaming Guide Generator', 'streaming-guide'); ?></h1>
            
            <?php
            // Display any admin notices from form processing
            $this->display_admin_notices();
            ?>
            
            <div class="streaming-guide-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#generate" class="nav-tab nav-tab-active" data-tab="generate">
                        <?php _e('Generate Content', 'streaming-guide'); ?>
                    </a>
                    <a href="#schedule" class="nav-tab" data-tab="schedule">
                        <?php _e('Schedule', 'streaming-guide'); ?>
                    </a>
                    <a href="#quick-generate" class="nav-tab" data-tab="quick">
                        <?php _e('Quick Generate', 'streaming-guide'); ?>
                    </a>
                </h2>
                
                <!-- Generate Tab -->
                <div id="generate-tab" class="tab-content active">
                    <?php $this->render_generate_forms(); ?>
                </div>
                
                <!-- Schedule Tab -->
                <div id="schedule-tab" class="tab-content" style="display:none;">
                    <?php $this->render_schedule_settings(); ?>
                </div>
                
                <!-- Quick Generate Tab -->
                <div id="quick-tab" class="tab-content" style="display:none;">
                    <?php $this->render_quick_generate(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display admin notices from session
     */
    private function display_admin_notices() {
        // Get notices from transient (set by form processor)
        $notices = get_transient('streaming_guide_admin_notices');
        
        if ($notices && is_array($notices)) {
            foreach ($notices as $notice) {
                $type = $notice['type'] ?? 'info';
                $message = $notice['message'] ?? '';
                
                if ($message) {
                    printf(
                        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                        esc_attr($type),
                        esc_html($message)
                    );
                }
            }
            
            // Clear notices after display
            delete_transient('streaming_guide_admin_notices');
        }
    }
    
    /**
     * Render content generation forms
     */
    private function render_generate_forms() {
        ?>
        <div class="streaming-guide-forms">
            <!-- Weekly Generator -->
            <div class="generator-card">
                <h3><?php _e('Weekly New Releases', 'streaming-guide'); ?></h3>
                <form method="post" action="" class="generator-form">
                    <?php wp_nonce_field('streaming_guide_generate', 'streaming_guide_nonce'); ?>
                    <input type="hidden" name="generator_type" value="weekly">
                    
                    <div class="form-group">
                        <label for="platform"><?php _e('Platform:', 'streaming-guide'); ?></label>
                        <select name="platform" id="platform" required>
                            <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                            <option value="netflix">Netflix</option>
                            <option value="disney">Disney+</option>
                            <option value="max">Max</option>
                            <option value="prime">Prime Video</option>
                            <option value="hulu">Hulu</option>
                            <option value="apple">Apple TV+</option>
                            <option value="paramount">Paramount+</option>
                            <option value="peacock">Peacock</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="button button-primary">
                        <?php _e('Generate Weekly Content', 'streaming-guide'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Monthly Recap -->
            <div class="generator-card">
                <h3><?php _e('Monthly Recap', 'streaming-guide'); ?></h3>
                <form method="post" action="" class="generator-form">
                    <?php wp_nonce_field('streaming_guide_generate', 'streaming_guide_nonce'); ?>
                    <input type="hidden" name="generator_type" value="monthly">
                    
                    <div class="form-group">
                        <label for="platform-monthly"><?php _e('Platform:', 'streaming-guide'); ?></label>
                        <select name="platform" id="platform-monthly" required>
                            <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                            <option value="netflix">Netflix</option>
                            <option value="disney">Disney+</option>
                            <option value="max">Max</option>
                            <option value="prime">Prime Video</option>
                            <option value="hulu">Hulu</option>
                            <option value="apple">Apple TV+</option>
                            <option value="paramount">Paramount+</option>
                            <option value="peacock">Peacock</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="month"><?php _e('Month:', 'streaming-guide'); ?></label>
                        <input type="month" name="month" id="month" value="<?php echo date('Y-m'); ?>" required>
                    </div>
                    
                    <button type="submit" class="button button-primary">
                        <?php _e('Generate Monthly Recap', 'streaming-guide'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Trending Content -->
            <div class="generator-card">
                <h3><?php _e('Trending Content', 'streaming-guide'); ?></h3>
                <form method="post" action="" class="generator-form">
                    <?php wp_nonce_field('streaming_guide_generate', 'streaming_guide_nonce'); ?>
                    <input type="hidden" name="generator_type" value="trending">
                    
                    <div class="form-group">
                        <label for="platform-trending"><?php _e('Platform:', 'streaming-guide'); ?></label>
                        <select name="platform" id="platform-trending" required>
                            <option value=""><?php _e('Select Platform', 'streaming-guide'); ?></option>
                            <option value="netflix">Netflix</option>
                            <option value="disney">Disney+</option>
                            <option value="max">Max</option>
                            <option value="prime">Prime Video</option>
                            <option value="hulu">Hulu</option>
                            <option value="apple">Apple TV+</option>
                            <option value="paramount">Paramount+</option>
                            <option value="peacock">Peacock</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="content-type"><?php _e('Content Type:', 'streaming-guide'); ?></label>
                        <select name="content_type" id="content-type">
                            <option value="mixed"><?php _e('Movies & TV Shows', 'streaming-guide'); ?></option>
                            <option value="movies"><?php _e('Movies Only', 'streaming-guide'); ?></option>
                            <option value="tv"><?php _e('TV Shows Only', 'streaming-guide'); ?></option>
                        </select>
                    </div>
                    
                    <button type="submit" class="button button-primary">
                        <?php _e('Generate Trending Article', 'streaming-guide'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render schedule settings
     */
    private function render_schedule_settings() {
        $schedules = $this->state_manager->get_active_schedules();
        ?>
        <div class="schedule-settings">
            <h3><?php _e('Automated Content Generation', 'streaming-guide'); ?></h3>
            
            <div class="schedule-status">
                <h4><?php _e('Active Schedules:', 'streaming-guide'); ?></h4>
                <?php if (empty($schedules)): ?>
                    <p><?php _e('No active schedules.', 'streaming-guide'); ?></p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($schedules as $schedule): ?>
                            <li>
                                <?php echo esc_html($schedule['type']); ?> - 
                                <?php echo esc_html($schedule['frequency']); ?>
                                <a href="#" class="deactivate-schedule" data-schedule="<?php echo esc_attr($schedule['id']); ?>">
                                    <?php _e('Deactivate', 'streaming-guide'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <form method="post" action="" class="schedule-form">
                <?php wp_nonce_field('streaming_guide_schedule', 'streaming_guide_nonce'); ?>
                <input type="hidden" name="action" value="update_schedule">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Weekly Generation', 'streaming-guide'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_weekly" value="1" 
                                    <?php checked($this->state_manager->is_schedule_active('weekly')); ?>>
                                <?php _e('Generate weekly content every Monday', 'streaming-guide'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Monthly Generation', 'streaming-guide'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_monthly" value="1"
                                    <?php checked($this->state_manager->is_schedule_active('monthly')); ?>>
                                <?php _e('Generate monthly recap on the 1st', 'streaming-guide'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Trending Updates', 'streaming-guide'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_trending" value="1"
                                    <?php checked($this->state_manager->is_schedule_active('trending')); ?>>
                                <?php _e('Update trending content twice weekly', 'streaming-guide'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Update Schedule', 'streaming-guide'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render quick generate section
     */
    private function render_quick_generate() {
        ?>
        <div class="quick-generate">
            <h3><?php _e('Quick Content Generation', 'streaming-guide'); ?></h3>
            <p><?php _e('Use AJAX to generate content without page reload.', 'streaming-guide'); ?></p>
            
            <div class="quick-form">
                <div class="form-group">
                    <label for="quick-type"><?php _e('Content Type:', 'streaming-guide'); ?></label>
                    <select id="quick-type">
                        <option value="weekly"><?php _e('Weekly Releases', 'streaming-guide'); ?></option>
                        <option value="trending"><?php _e('Trending Now', 'streaming-guide'); ?></option>
                        <option value="spotlight"><?php _e('Spotlight Review', 'streaming-guide'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quick-platform"><?php _e('Platform:', 'streaming-guide'); ?></label>
                    <select id="quick-platform">
                        <option value="netflix">Netflix</option>
                        <option value="disney">Disney+</option>
                        <option value="max">Max</option>
                        <option value="prime">Prime Video</option>
                    </select>
                </div>
                
                <button type="button" id="quick-generate-btn" class="button button-primary">
                    <?php _e('Generate Now', 'streaming-guide'); ?>
                </button>
                
                <div id="generation-progress" style="display:none;">
                    <div class="spinner is-active"></div>
                    <span><?php _e('Generating content...', 'streaming-guide'); ?></span>
                </div>
                
                <div id="generation-result"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render history page
     */
    public function render_history_page() {
        $this->load_dependencies();
        $history = $this->state_manager->get_content_history(50);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Content Generation History', 'streaming-guide'); ?></h1>
            
            <?php if (empty($history)): ?>
                <p><?php _e('No content has been generated yet.', 'streaming-guide'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'streaming-guide'); ?></th>
                            <th><?php _e('Title', 'streaming-guide'); ?></th>
                            <th><?php _e('Type', 'streaming-guide'); ?></th>
                            <th><?php _e('Platform', 'streaming-guide'); ?></th>
                            <th><?php _e('Status', 'streaming-guide'); ?></th>
                            <th><?php _e('Actions', 'streaming-guide'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item['date']); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($item['post_id']); ?>">
                                        <?php echo esc_html($item['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($item['type']); ?></td>
                                <td><?php echo esc_html($item['platform']); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($item['status']); ?>">
                                        <?php echo esc_html(ucfirst($item['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo get_permalink($item['post_id']); ?>" target="_blank">
                                        <?php _e('View', 'streaming-guide'); ?>
                                    </a> |
                                    <a href="<?php echo get_edit_post_link($item['post_id']); ?>">
                                        <?php _e('Edit', 'streaming-guide'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Streaming Guide Settings', 'streaming-guide'); ?></h1>
            
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
                            <input type="password" id="tmdb_api_key" name="streaming_guide_tmdb_api_key" 
                                value="<?php echo esc_attr(get_option('streaming_guide_tmdb_api_key')); ?>" 
                                class="regular-text" />
                            <p class="description"><?php _e('Your TMDB API key for fetching movie data.', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key"><?php _e('OpenAI API Key', 'streaming-guide'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="openai_api_key" name="streaming_guide_openai_api_key" 
                                value="<?php echo esc_attr(get_option('streaming_guide_openai_api_key')); ?>" 
                                class="regular-text" />
                            <p class="description"><?php _e('Your OpenAI API key for content generation.', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_author"><?php _e('Default Author', 'streaming-guide'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_users(array(
                                'name' => 'streaming_guide_default_author',
                                'selected' => get_option('streaming_guide_default_author', get_current_user_id()),
                                'show_option_none' => __('— Select —', 'streaming-guide'),
                                'option_none_value' => '0'
                            ));
                            ?>
                            <p class="description"><?php _e('Default author for generated content.', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_publish"><?php _e('Auto Publish', 'streaming-guide'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_publish" name="streaming_guide_auto_publish" value="1"
                                    <?php checked(get_option('streaming_guide_auto_publish'), 1); ?> />
                                <?php _e('Automatically publish generated content', 'streaming-guide'); ?>
                            </label>
                            <p class="description"><?php _e('If unchecked, content will be saved as draft.', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="featured_image_source"><?php _e('Featured Image Source', 'streaming-guide'); ?></label>
                        </th>
                        <td>
                            <select id="featured_image_source" name="streaming_guide_featured_image_source">
                                <option value="backdrop" <?php selected(get_option('streaming_guide_featured_image_source'), 'backdrop'); ?>>
                                    <?php _e('Backdrop (Horizontal)', 'streaming-guide'); ?>
                                </option>
                                <option value="poster" <?php selected(get_option('streaming_guide_featured_image_source'), 'poster'); ?>>
                                    <?php _e('Poster (Vertical)', 'streaming-guide'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Choose the image type for featured images.', 'streaming-guide'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render SEO settings page
     */
    public function render_seo_settings_page() {
        // Get SEO statistics
        $stats = $this->get_seo_statistics();
        ?>
        <div class="wrap">
            <h1><?php _e('SEO Settings & Management', 'streaming-guide'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('SEO Enhancement System Active:', 'streaming-guide'); ?></strong> 
                <?php _e('All new articles will be automatically optimized for search engines.', 'streaming-guide'); ?></p>
            </div>
            
            <!-- SEO Statistics -->
            <div class="card">
                <h2><?php _e('SEO Statistics', 'streaming-guide'); ?></h2>
                <table class="wp-list-table widefat">
                    <tr>
                        <td><strong><?php _e('Total Articles:', 'streaming-guide'); ?></strong></td>
                        <td><?php echo esc_html($stats['total_posts']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('SEO Optimized:', 'streaming-guide'); ?></strong></td>
                        <td><?php echo esc_html($stats['optimized_posts']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Needs Optimization:', 'streaming-guide'); ?></strong></td>
                        <td><?php echo esc_html($stats['needs_optimization']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Unique Keyphrases Used:', 'streaming-guide'); ?></strong></td>
                        <td><?php echo esc_html($stats['keyphrases_used']); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- SEO Actions -->
            <div class="card">
                <h2><?php _e('SEO Actions', 'streaming-guide'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('streaming_guide_seo_actions'); ?>
                    
                    <p>
                        <button type="submit" name="optimize_existing" class="button button-primary">
                            <?php _e('Optimize Existing Posts', 'streaming-guide'); ?>
                        </button>
                        <span class="description"><?php _e('Optimizes up to 20 unoptimized posts', 'streaming-guide'); ?></span>
                    </p>
                    
                    <p>
                        <button type="submit" name="clear_keyphrases" class="button">
                            <?php _e('Clear Keyphrase History', 'streaming-guide'); ?>
                        </button>
                        <span class="description"><?php _e('Allows reuse of previously used keyphrases', 'streaming-guide'); ?></span>
                    </p>
                </form>
            </div>
            
            <!-- SEO Settings -->
            <div class="card">
                <h2><?php _e('SEO Settings', 'streaming-guide'); ?></h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('streaming_guide_seo_settings');
                    do_settings_sections('streaming_guide_seo_settings');
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Auto Internal Linking', 'streaming-guide'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="streaming_guide_auto_internal_links" value="1" 
                                        <?php checked(get_option('streaming_guide_auto_internal_links', 1)); ?>>
                                    <?php _e('Automatically add internal links between related articles', 'streaming-guide'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Auto Outbound Linking', 'streaming-guide'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="streaming_guide_auto_outbound_links" value="1" 
                                        <?php checked(get_option('streaming_guide_auto_outbound_links', 1)); ?>>
                                    <?php _e('Automatically add outbound links to official streaming sites', 'streaming-guide'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('SEO Title Optimization', 'streaming-guide'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="streaming_guide_optimize_titles" value="1" 
                                        <?php checked(get_option('streaming_guide_optimize_titles', 1)); ?>>
                                    <?php _e('Automatically optimize article titles for SEO', 'streaming-guide'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Enable Schema Markup', 'streaming-guide'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="streaming_guide_enable_schema" value="1" 
                                        <?php checked(get_option('streaming_guide_enable_schema', 1)); ?>>
                                    <?php _e('Add structured data for movies and TV shows', 'streaming-guide'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get SEO statistics
     */
    private function get_seo_statistics() {
        global $wpdb;
        
        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE pm.meta_key = '_streaming_guide_generated' 
            AND p.post_status = 'publish'
        ");
        
        $optimized = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
            WHERE pm1.meta_key = '_streaming_guide_generated' 
            AND pm2.meta_key = '_streaming_guide_seo_optimized' 
            AND p.post_status = 'publish'
        ");
        
        $keyphrases = get_option('streaming_guide_used_keyphrases', array());
        
        return array(
            'total_posts' => intval($total),
            'optimized_posts' => intval($optimized),
            'needs_optimization' => intval($total) - intval($optimized),
            'keyphrases_used' => count($keyphrases)
        );
    }
}

// Initialize the admin router
new Streaming_Guide_Admin_Router();