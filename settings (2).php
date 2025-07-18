<?php
// templates/settings.php - Clean version without slider code
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap upcoming-movies-settings">
    <h1><?php esc_html_e('Upcoming Movies Settings', 'upcoming-movies'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('upcoming_movies_options'); ?>
        <?php do_settings_sections('upcoming_movies_options'); ?>
        
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="upcoming_movies_tmdb_api_key"><?php esc_html_e('TMDB API Key', 'upcoming-movies'); ?></label></th>
                <td>
                    <input type="text" id="upcoming_movies_tmdb_api_key" name="upcoming_movies_tmdb_api_key" value="<?php echo esc_attr(get_option('upcoming_movies_tmdb_api_key')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Enter your TMDB API Key (v3) to enable movie search and data fetching.', 'upcoming-movies'); ?> <a href="https://www.themoviedb.org/settings/api" target="_blank"><?php esc_html_e('Get your TMDB API Key', 'upcoming-movies'); ?></a></p>
                    <?php if (empty(get_option('upcoming_movies_tmdb_api_key'))) {
                        echo '<p style="color: red; font-weight: bold;">' . esc_html__('TMDB API Key is required for movie search and data fetching.', 'upcoming-movies') . '</p>';
                    } ?>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row"><label for="upcoming_movies_openai_api_key"><?php esc_html_e('OpenAI API Key', 'upcoming-movies'); ?></label></th>
                <td>
                    <input type="text" id="upcoming_movies_openai_api_key" name="upcoming_movies_openai_api_key" value="<?php echo esc_attr(get_option('upcoming_movies_openai_api_key')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Enter your OpenAI API Key to enable automatic article generation.', 'upcoming-movies'); ?> <a href="https://platform.openai.com/account/api-keys" target="_blank"><?php esc_html_e('Get your OpenAI API Key', 'upcoming-movies'); ?></a></p>
                    <?php if (empty(get_option('upcoming_movies_openai_api_key'))) {
                         echo '<p style="color: red; font-weight: bold;">' . esc_html__('OpenAI API Key is required for automatic article generation.', 'upcoming-movies') . '</p>';
                    } ?>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <!-- Movie Statistics Section -->
    <div class="card" style="margin-top: 30px; padding: 20px;">
        <h2><?php esc_html_e('Movie Statistics', 'upcoming-movies'); ?></h2>
        
        <?php
        $total_movies = wp_count_posts('upcoming_movie');
        $movies_with_trailers = get_posts(array(
            'post_type' => 'upcoming_movie',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'youtube_id',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        ));
        
        // Get platform distribution
        global $wpdb;
        $platforms = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value as platform, COUNT(*) as count 
            FROM {$wpdb->postmeta} pm 
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE pm.meta_key = %s 
            AND p.post_type = %s 
            AND p.post_status = %s
            AND pm.meta_value != %s 
            GROUP BY pm.meta_value 
            ORDER BY count DESC 
            LIMIT 5
        ", 'streaming_platform', 'upcoming_movie', 'publish', ''));
        ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <h3><?php esc_html_e('Total Movies', 'upcoming-movies'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Published:', 'upcoming-movies'); ?></strong> <?php echo esc_html($total_movies->publish); ?></li>
                    <li><strong><?php esc_html_e('Drafts:', 'upcoming-movies'); ?></strong> <?php echo esc_html($total_movies->draft); ?></li>
                    <li><strong><?php esc_html_e('Pending:', 'upcoming-movies'); ?></strong> <?php echo esc_html($total_movies->pending); ?></li>
                    <li><strong><?php esc_html_e('With Trailers:', 'upcoming-movies'); ?></strong> <?php echo esc_html(count($movies_with_trailers)); ?></li>
                </ul>
            </div>
            
            <?php if (!empty($platforms)): ?>
            <div>
                <h3><?php esc_html_e('Top Platforms', 'upcoming-movies'); ?></h3>
                <ul>
                    <?php foreach ($platforms as $platform): ?>
                        <li><strong><?php echo esc_html($platform->platform); ?>:</strong> <?php echo esc_html($platform->count); ?> movies</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TMDB Logo Status -->
    <div class="card" style="margin-top: 20px; padding: 20px;">
        <h2><?php esc_html_e('TMDB Attribution', 'upcoming-movies'); ?></h2>
        
        <?php
        // Check for TMDB logo file
        $logo_files = array('tmdb-logo.svg', 'tmdb-logo.png', 'tmdb-logo.jpg', 'tmdb.svg', 'tmdb.png');
        $logo_found = false;
        $logo_file_found = '';
        
        foreach ($logo_files as $filename) {
            $file_path = UPCOMING_MOVIES_PLUGIN_DIR . 'assets/images/' . $filename;
            if (file_exists($file_path)) {
                $logo_found = true;
                $logo_file_found = $filename;
                break;
            }
        }
        ?>
        
        <?php if ($logo_found): ?>
            <p style="color: green; font-weight: bold;">
                ✓ <?php esc_html_e('TMDB logo found:', 'upcoming-movies'); ?> <?php echo esc_html($logo_file_found); ?> 
                <br><small><?php esc_html_e('Logo will appear in article attributions automatically.', 'upcoming-movies'); ?></small>
            </p>
        <?php else: ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 10px 0;">
                <p style="color: #856404; margin: 0;">
                    <strong>⚠ <?php esc_html_e('TMDB Logo Missing', 'upcoming-movies'); ?></strong><br>
                    <?php esc_html_e('Upload a TMDB logo to:', 'upcoming-movies'); ?> 
                    <code><?php echo esc_html(UPCOMING_MOVIES_PLUGIN_DIR . 'assets/images/'); ?></code><br>
                    <small>
                        <?php esc_html_e('Supported files: tmdb-logo.svg, tmdb-logo.png, tmdb-logo.jpg, tmdb.svg, or tmdb.png', 'upcoming-movies'); ?><br>
                        <a href="https://www.themoviedb.org/about/logos-attribution" target="_blank"><?php esc_html_e('Download official TMDB logos', 'upcoming-movies'); ?></a>
                    </small>
                </p>
            </div>
        <?php endif; ?>
        
        <h3><?php esc_html_e('Attribution Requirements', 'upcoming-movies'); ?></h3>
        <p><?php esc_html_e('This plugin automatically adds proper TMDB attribution to all generated articles, including:', 'upcoming-movies'); ?></p>
        <ul>
            <li><?php esc_html_e('TMDB logo display', 'upcoming-movies'); ?></li>
            <li><?php esc_html_e('Attribution text explaining data source', 'upcoming-movies'); ?></li>
            <li><?php esc_html_e('Links to TMDB website', 'upcoming-movies'); ?></li>
            <li><?php esc_html_e('Compliance with TMDB API terms', 'upcoming-movies'); ?></li>
        </ul>
    </div>

    <!-- System Status -->
    <div class="card" style="margin-top: 20px; padding: 20px;">
        <h2><?php esc_html_e('System Status', 'upcoming-movies'); ?></h2>
        
        <table class="widefat" style="background: white;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Component', 'upcoming-movies'); ?></th>
                    <th><?php esc_html_e('Status', 'upcoming-movies'); ?></th>
                    <th><?php esc_html_e('Details', 'upcoming-movies'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('TMDB API', 'upcoming-movies'); ?></strong></td>
                    <td>
                        <?php if (!empty(get_option('upcoming_movies_tmdb_api_key'))): ?>
                            <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Configured', 'upcoming-movies'); ?></span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('Not Configured', 'upcoming-movies'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e('Required for movie data and search', 'upcoming-movies'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('OpenAI API', 'upcoming-movies'); ?></strong></td>
                    <td>
                        <?php if (!empty(get_option('upcoming_movies_openai_api_key'))): ?>
                            <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Configured', 'upcoming-movies'); ?></span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('Not Configured', 'upcoming-movies'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php esc_html_e('Required for article generation', 'upcoming-movies'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('TMDB Logo', 'upcoming-movies'); ?></strong></td>
                    <td>
                        <?php if ($logo_found): ?>
                            <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Found', 'upcoming-movies'); ?></span>
                        <?php else: ?>
                            <span style="color: orange; font-weight: bold;">⚠ <?php esc_html_e('Missing', 'upcoming-movies'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($logo_found): ?>
                            <?php echo esc_html($logo_file_found); ?>
                        <?php else: ?>
                            <?php esc_html_e('Upload to assets/images/ folder', 'upcoming-movies'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Assets Directory', 'upcoming-movies'); ?></strong></td>
                    <td>
                        <?php if (is_dir(UPCOMING_MOVIES_PLUGIN_DIR . 'assets/images/')): ?>
                            <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Exists', 'upcoming-movies'); ?></span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('Missing', 'upcoming-movies'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(UPCOMING_MOVIES_PLUGIN_DIR . 'assets/images/'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Movie URLs', 'upcoming-movies'); ?></strong></td>
                    <td>
                        <?php 
                        $rules = get_option('rewrite_rules');
                        if (isset($rules['movie/([^/]+)/?$'])): ?>
                            <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Working', 'upcoming-movies'); ?></span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('Broken', 'upcoming-movies'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!isset($rules['movie/([^/]+)/?$'])): ?>
                            <a href="<?php echo admin_url('options-permalink.php'); ?>" class="button button-small"><?php esc_html_e('Fix Permalinks', 'upcoming-movies'); ?></a>
                        <?php else: ?>
                            <?php esc_html_e('URLs: /movie/movie-title/', 'upcoming-movies'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="margin-top: 20px; padding: 20px;">
        <h2><?php esc_html_e('Quick Actions', 'upcoming-movies'); ?></h2>
        <p>
            <a href="<?php echo admin_url('admin.php?page=upcoming-movies'); ?>" class="button button-primary">
                <?php esc_html_e('View All Movies', 'upcoming-movies'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=upcoming-movies-add'); ?>" class="button button-secondary">
                <?php esc_html_e('Add New Movie', 'upcoming-movies'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=upcoming-movies-mass-producer'); ?>" class="button button-secondary">
                <?php esc_html_e('Mass Producer', 'upcoming-movies'); ?>
            </a>
            <a href="<?php echo admin_url('options-permalink.php'); ?>" class="button button-secondary">
                <?php esc_html_e('Fix Permalinks', 'upcoming-movies'); ?>
            </a>
        </p>
    </div>

    <!-- Usage Information -->
    <div class="card" style="margin-top: 20px; padding: 20px;">
        <h2><?php esc_html_e('How to Use Upcoming Movies', 'upcoming-movies'); ?></h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                <h3><?php esc_html_e('1. Individual Movie Pages', 'upcoming-movies'); ?></h3>
                <p><?php esc_html_e('Each movie gets its own SEO-friendly page with detailed information, images, and trailer.', 'upcoming-movies'); ?></p>
                <p><strong><?php esc_html_e('URL Format:', 'upcoming-movies'); ?></strong></p>
                <code><?php echo esc_url(home_url('/movie/movie-title/')); ?></code>
                <p><small><?php esc_html_e('Perfect for linking from your main site content or menus.', 'upcoming-movies'); ?></small></p>
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                <h3><?php esc_html_e('2. Blog Integration', 'upcoming-movies'); ?></h3>
                <p><?php esc_html_e('Movies automatically appear in your main blog feed, search results, and category pages.', 'upcoming-movies'); ?></p>
                <p><strong><?php esc_html_e('Features:', 'upcoming-movies'); ?></strong></p>
                <ul>
                    <li><?php esc_html_e('RSS feed inclusion', 'upcoming-movies'); ?></li>
                    <li><?php esc_html_e('Search integration', 'upcoming-movies'); ?></li>
                    <li><?php esc_html_e('Category assignment', 'upcoming-movies'); ?></li>
                    <li><?php esc_html_e('SEO optimization', 'upcoming-movies'); ?></li>
                </ul>
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                <h3><?php esc_html_e('3. Mass Production', 'upcoming-movies'); ?></h3>
                <p><?php esc_html_e('Use the Mass Producer to generate multiple movie articles at once for any streaming platform.', 'upcoming-movies'); ?></p>
                <p><strong><?php esc_html_e('Process:', 'upcoming-movies'); ?></strong></p>
                <ol>
                    <li><?php esc_html_e('Select platform (Netflix, Disney+, etc.)', 'upcoming-movies'); ?></li>
                    <li><?php esc_html_e('Choose 5 movies from TMDB', 'upcoming-movies'); ?></li>
                    <li><?php esc_html_e('Generate complete articles automatically', 'upcoming-movies'); ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
.upcoming-movies-settings .card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.upcoming-movies-settings .widefat td,
.upcoming-movies-settings .widefat th {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.upcoming-movies-settings code {
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9em;
}

.upcoming-movies-settings ul {
    margin: 10px 0;
}

.upcoming-movies-settings li {
    margin: 5px 0;
}
</style>