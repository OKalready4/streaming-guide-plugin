<?php
/**
 * Facebook Admin Interface - NEW FILE
 * 
 * Create this file: admin/class-facebook-admin.php
 * Provides a complete Facebook integration admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Facebook_Admin {
    
    public function __construct() {
        // Hook into admin init to register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register Facebook settings
     */
    public function register_settings() {
        register_setting('streaming_guide_facebook_settings', 'streaming_guide_facebook_page_id');
        register_setting('streaming_guide_facebook_settings', 'streaming_guide_facebook_access_token');
        register_setting('streaming_guide_facebook_settings', 'streaming_guide_auto_share_facebook');
        register_setting('streaming_guide_facebook_settings', 'streaming_guide_share_delay');
    }
    
    /**
     * Render Facebook settings page
     */
    public function render_facebook_settings() {
        $page_id = get_option('streaming_guide_facebook_page_id', '');
        $access_token = get_option('streaming_guide_facebook_access_token', '');
        $auto_share = get_option('streaming_guide_auto_share_facebook', 0);
        $share_delay = get_option('streaming_guide_share_delay', 5);
        
        ?>
        <div class="streaming-guide-facebook-admin">
            <h2><?php _e('Facebook Integration', 'streaming-guide'); ?></h2>
            <p><?php _e('Configure automatic Facebook posting for your generated content.', 'streaming-guide'); ?></p>
            
            <!-- Connection Status -->
            <div class="connection-status-card" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;margin-bottom:20px;">
                <h3><?php _e('Connection Status', 'streaming-guide'); ?></h3>
                <div id="facebook-connection-status">
                    <?php if (!empty($page_id) && !empty($access_token)): ?>
                        <p style="color:#00a32a;">✅ <?php _e('Facebook credentials configured', 'streaming-guide'); ?></p>
                        <button type="button" id="test-facebook-connection" class="button">
                            <?php _e('Test Connection', 'streaming-guide'); ?>
                        </button>
                        <div id="facebook-test-result" style="margin-top:10px;"></div>
                    <?php else: ?>
                        <p style="color:#d63638;">❌ <?php _e('Facebook not configured', 'streaming-guide'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Settings Form -->
            <form method="post" action="">
                <?php wp_nonce_field('streaming_guide_settings', 'streaming_guide_nonce'); ?>
                <input type="hidden" name="streaming_guide_action" value="update_facebook_settings" />
                
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
                                       <?php checked($auto_share, 1); ?> />
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
                                   value="<?php echo esc_attr($share_delay); ?>" 
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
                
                <?php submit_button(__('Save Facebook Settings', 'streaming-guide')); ?>
            </form>
            
            <!-- Setup Instructions -->
            <div class="setup-instructions" style="border:1px solid #ccd0d4;padding:20px;background:#f9f9f9;border-radius:4px;margin-top:20px;">
                <h3><?php _e('Setup Instructions', 'streaming-guide'); ?></h3>
                
                <div class="instruction-steps">
                    <h4><?php _e('Step 1: Create a Facebook App', 'streaming-guide'); ?></h4>
                    <ol>
                        <li><?php _e('Go to', 'streaming-guide'); ?> <a href="https://developers.facebook.com/apps" target="_blank">Facebook Developers</a></li>
                        <li><?php _e('Click "Create App" and select "Business" type', 'streaming-guide'); ?></li>
                        <li><?php _e('Add your app name and contact email', 'streaming-guide'); ?></li>
                        <li><?php _e('Go to App Settings > Basic and note your App ID', 'streaming-guide'); ?></li>
                    </ol>
                    
                    <h4><?php _e('Step 2: Get a Page Access Token', 'streaming-guide'); ?></h4>
                    <ol>
                        <li><?php _e('Go to', 'streaming-guide'); ?> <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
                        <li><?php _e('Select your app from the dropdown', 'streaming-guide'); ?></li>
                        <li><?php _e('Click "Generate Access Token" and log in to Facebook', 'streaming-guide'); ?></li>
                        <li><?php _e('Select your page and grant permissions', 'streaming-guide'); ?></li>
                        <li><?php _e('Copy the Page Access Token and paste it above', 'streaming-guide'); ?></li>
                    </ol>
                    
                    <h4><?php _e('Step 3: Find Your Page ID', 'streaming-guide'); ?></h4>
                    <ol>
                        <li><?php _e('Go to your Facebook page', 'streaming-guide'); ?></li>
                        <li><?php _e('Click "About" tab', 'streaming-guide'); ?></li>
                        <li><?php _e('Scroll down to find "Page ID" or check the URL', 'streaming-guide'); ?></li>
                        <li><?php _e('Copy the numeric Page ID and paste it above', 'streaming-guide'); ?></li>
                    </ol>
                    
                    <h4><?php _e('Required Permissions', 'streaming-guide'); ?></h4>
                    <p><?php _e('Your Page Access Token must have these permissions:', 'streaming-guide'); ?></p>
                    <ul>
                        <li><code>pages_manage_posts</code> - <?php _e('To post content to your page', 'streaming-guide'); ?></li>
                        <li><code>pages_read_engagement</code> - <?php _e('To read page information', 'streaming-guide'); ?></li>
                    </ul>
                </div>
            </div>
            
            <!-- Recent Shares -->
            <div class="recent-shares" style="border:1px solid #ccd0d4;padding:20px;background:#fff;border-radius:4px;margin-top:20px;">
                <h3><?php _e('Recent Facebook Shares', 'streaming-guide'); ?></h3>
                <?php $this->display_recent_shares(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Test Facebook connection
            $('#test-facebook-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#facebook-test-result');
                
                $button.prop('disabled', true).text('Testing...');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'streaming_guide_test_facebook',
                        nonce: streamingGuideAdmin.streaming_guide_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div style="color:#00a32a;">✅ ' + response.data.message + '</div>');
                            if (response.data.data && response.data.data.page_name) {
                                $result.append('<p>Connected to page: <strong>' + response.data.data.page_name + '</strong></p>');
                            }
                        } else {
                            $result.html('<div style="color:#d63638;">❌ ' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $result.html('<div style="color:#d63638;">❌ Connection test failed</div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display recent Facebook shares
     */
    private function display_recent_shares() {
        if (!class_exists('Streaming_Guide_State_Manager')) {
            echo '<p>' . __('State manager not available.', 'streaming-guide') . '</p>';
            return;
        }
        
        $state_manager = new Streaming_Guide_State_Manager();
        $shares = $state_manager->get_social_share_history(null, 10);
        
        if (empty($shares)) {
            echo '<p>' . __('No Facebook shares yet.', 'streaming-guide') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Date', 'streaming-guide') . '</th>';
        echo '<th>' . __('Post', 'streaming-guide') . '</th>';
        echo '<th>' . __('Platform', 'streaming-guide') . '</th>';
        echo '<th>' . __('Status', 'streaming-guide') . '</th>';
        echo '<th>' . __('Facebook Post ID', 'streaming-guide') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($shares as $share) {
            echo '<tr>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($share->created_at))) . '</td>';
            echo '<td>';
            if (!empty($share->post_title)) {
                echo '<a href="' . esc_url(get_permalink($share->post_id)) . '" target="_blank">' . esc_html($share->post_title) . '</a>';
            } else {
                echo esc_html($share->post_id);
            }
            echo '</td>';
            echo '<td>' . esc_html(ucfirst($share->platform)) . '</td>';
            echo '<td>';
            if ($share->status === 'success') {
                echo '<span style="color:#00a32a;">✅ Success</span>';
            } else {
                echo '<span style="color:#d63638;">❌ ' . esc_html(ucfirst($share->status)) . '</span>';
                if (!empty($share->error_message)) {
                    echo '<br><small>' . esc_html($share->error_message) . '</small>';
                }
            }
            echo '</td>';
            echo '<td>';
            if (!empty($share->social_post_id)) {
                echo '<code>' . esc_html($share->social_post_id) . '</code>';
            } else {
                echo '—';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}