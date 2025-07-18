<?php
// templates/admin-page.php - CLEAN VERSION WITHOUT REGEX ERRORS
if (!defined('ABSPATH')) {
    exit;
}

// Check for success/error messages
if (isset($_GET['movie_added'])) {
    $movie_id = intval($_GET['movie_added']);
    $movie_url = get_permalink($movie_id);
    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php
            if ($movie_url && !is_wp_error($movie_url)) {
                printf(
                    __('Movie created successfully! <a href="%s" target="_blank">View Movie</a>', 'upcoming-movies'),
                    esc_url($movie_url)
                );
            } else {
                esc_html_e('Movie created successfully!', 'upcoming-movies');
            }
            ?>
        </p>
    </div>
    <?php
}

if (isset($_GET['movie_deleted']) && $_GET['movie_deleted'] === 'success') {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Movie deleted successfully.', 'upcoming-movies'); ?></p>
    </div>
    <?php
}

if (isset($_GET['movie_delete_error'])) {
    $error_type = sanitize_key($_GET['movie_delete_error']);
    $message = __('Failed to delete the movie.', 'upcoming-movies');
    switch ($error_type) {
        case 'invalid_id':
            $message = __('Failed to delete movie: Invalid movie ID.', 'upcoming-movies');
            break;
        case 'failed':
            $message = __('Failed to delete movie: Unknown error occurred.', 'upcoming-movies');
            break;
    }
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}

if (isset($_GET['cleanup_success'])) {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><strong><?php esc_html_e('Cleanup Complete!', 'upcoming-movies'); ?></strong><br>
        <?php echo esc_html(urldecode($_GET['cleanup_success'])); ?></p>
    </div>
    <?php
}

// Function to check if movie rewrite rules exist
function check_movie_rewrite_rules() {
    $rules = get_option('rewrite_rules');
    if (!is_array($rules)) {
        return false;
    }
    
    foreach ($rules as $rule => $rewrite) {
        if (strpos($rule, 'movie/') === 0) {
            return true;
        }
    }
    return false;
}
?>

<div class="wrap upcoming-movies-admin">
    <h1>
        <?php esc_html_e('Movies Dashboard', 'upcoming-movies'); ?>
        <span class="title-count"><?php echo count($upcoming_movies); ?> movies</span>
    </h1>

    <?php
    // COMPREHENSIVE DATABASE DIAGNOSTIC SYSTEM
    global $wpdb;
    
    // Get database statistics
    $total_movies = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'upcoming_movie'");
    $published_movies = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'upcoming_movie' AND post_status = 'publish'");
    
    $orphaned_meta = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->postmeta} pm 
        LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
        WHERE pm.meta_key IN ('tmdb_id', 'movie_title', 'trailer_url', 'youtube_id', 'streaming_platform') 
        AND p.ID IS NULL
    ");
    
    $orphaned_images = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} p 
        LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID 
        WHERE p.post_type = 'attachment' 
        AND p.post_mime_type LIKE 'image/%'
        AND (
            (p.post_parent > 0 AND parent.ID IS NULL) 
            OR p.post_title LIKE '%movie%' 
            OR p.post_title LIKE '%scene%'
            OR p.post_title LIKE '%poster%'
            OR p.post_title LIKE '%backdrop%'
        )
    ");
    
    $broken_posts = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} p 
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'tmdb_id'
        WHERE p.post_type = 'upcoming_movie' 
        AND pm.meta_id IS NULL
    ");
    
    $duplicate_tmdb_ids = $wpdb->get_results("
        SELECT pm.meta_value as tmdb_id, COUNT(*) as count
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = 'tmdb_id' 
        AND p.post_type = 'upcoming_movie'
        GROUP BY pm.meta_value
        HAVING COUNT(*) > 1
    ");
    
    $total_issues = $orphaned_meta + $orphaned_images + $broken_posts + count($duplicate_tmdb_ids);
    
    // URL Testing - FIXED: No more regex issues
    $sample_movie = $wpdb->get_row("
        SELECT p.ID, p.post_name, p.post_title 
        FROM {$wpdb->posts} p 
        WHERE p.post_type = 'upcoming_movie' 
        AND p.post_status = 'publish' 
        LIMIT 1
    ");
    
    $url_status = 'unknown';
    $test_url = '';
    $movie_rule_exists = check_movie_rewrite_rules();
    
    if ($sample_movie && $movie_rule_exists) {
        $test_url = home_url('/movie/' . $sample_movie->post_name . '/');
        $response = wp_remote_head($test_url, array('timeout' => 10));
        $url_status = is_wp_error($response) ? 'error' : wp_remote_retrieve_response_code($response);
    } else {
        $url_status = $movie_rule_exists ? 'no_movies' : 'no_rules';
    }
    ?>

    <!-- SYSTEM STATUS DASHBOARD -->
    <div class="system-status-dashboard">
        <div class="status-cards">
            <div class="status-card <?php echo $published_movies > 0 ? 'status-good' : 'status-warning'; ?>">
                <div class="status-icon">🎬</div>
                <div class="status-info">
                    <h3><?php echo esc_html($published_movies); ?></h3>
                    <p>Published Movies</p>
                </div>
            </div>
            
            <div class="status-card <?php echo $total_issues === 0 ? 'status-good' : 'status-error'; ?>">
                <div class="status-icon"><?php echo $total_issues === 0 ? '✅' : '⚠️'; ?></div>
                <div class="status-info">
                    <h3><?php echo esc_html($total_issues); ?></h3>
                    <p>Database Issues</p>
                </div>
            </div>
            
            <div class="status-card <?php echo ($url_status === 200) ? 'status-good' : 'status-error'; ?>">
                <div class="status-icon"><?php echo ($url_status === 200) ? '🔗' : '❌'; ?></div>
                <div class="status-info">
                    <h3><?php echo esc_html($url_status === 200 ? 'Working' : 'Broken'); ?></h3>
                    <p>Movie URLs</p>
                </div>
            </div>
            
            <div class="status-card">
                <div class="status-icon">📊</div>
                <div class="status-info">
                    <h3><?php echo esc_html($total_movies); ?></h3>
                    <p>Total Movies</p>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAILED DIAGNOSTIC SECTION -->
    <?php if ($total_issues > 0 || isset($_GET['show_diagnostic'])): ?>
        <div class="diagnostic-section">
            <div class="card">
                <h2>🔍 Database Diagnostic Report</h2>
                
                <?php if ($total_issues > 0): ?>
                    <div class="notice notice-warning" style="margin: 0 0 20px 0;">
                        <p><strong>⚠️ Issues Found:</strong> Your database has <?php echo esc_html($total_issues); ?> issues that may cause problems.</p>
                    </div>
                <?php endif; ?>
                
                <div class="diagnostic-grid">
                    <?php if ($orphaned_meta > 0): ?>
                        <div class="diagnostic-item error">
                            <h4>🗄️ Orphaned Metadata (<?php echo esc_html($orphaned_meta); ?>)</h4>
                            <p>Movie metadata without corresponding posts. This causes "already exists" errors.</p>
                            <details>
                                <summary>Show Details</summary>
                                <?php
                                $orphaned_details = $wpdb->get_results("
                                    SELECT pm.post_id, pm.meta_key, pm.meta_value
                                    FROM {$wpdb->postmeta} pm 
                                    LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                                    WHERE pm.meta_key = 'tmdb_id' AND p.ID IS NULL
                                    LIMIT 10
                                ");
                                ?>
                                <table class="wp-list-table widefat">
                                    <thead><tr><th>Post ID</th><th>TMDB ID</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($orphaned_details as $detail): ?>
                                            <tr>
                                                <td><?php echo esc_html($detail->post_id); ?></td>
                                                <td><?php echo esc_html($detail->meta_value); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($orphaned_images > 0): ?>
                        <div class="diagnostic-item warning">
                            <h4>🖼️ Orphaned Images (<?php echo esc_html($orphaned_images); ?>)</h4>
                            <p>Movie images without parent posts. Wasting disk space.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($broken_posts > 0): ?>
                        <div class="diagnostic-item error">
                            <h4>💔 Broken Posts (<?php echo esc_html($broken_posts); ?>)</h4>
                            <p>Movie posts missing required TMDB ID metadata.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($duplicate_tmdb_ids)): ?>
                        <div class="diagnostic-item warning">
                            <h4>🔄 Duplicate Movies (<?php echo count($duplicate_tmdb_ids); ?>)</h4>
                            <p>Multiple posts with the same TMDB ID.</p>
                            <details>
                                <summary>Show Duplicates</summary>
                                <table class="wp-list-table widefat">
                                    <thead><tr><th>TMDB ID</th><th>Count</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($duplicate_tmdb_ids as $duplicate): ?>
                                            <tr>
                                                <td><?php echo esc_html($duplicate->tmdb_id); ?></td>
                                                <td><?php echo esc_html($duplicate->count); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($url_status !== 200 && $url_status !== 'no_movies'): ?>
                        <div class="diagnostic-item error">
                            <h4>🔗 URL Problems</h4>
                            <p>Movie URLs are returning <?php echo esc_html($url_status); ?> errors.</p>
                            <?php if ($test_url): ?>
                                <p><strong>Test URL:</strong> <a href="<?php echo esc_url($test_url); ?>" target="_blank"><?php echo esc_html($test_url); ?></a></p>
                            <?php endif; ?>
                            <p><em>Solution: <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>">Flush Permalinks</a></em></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_issues > 0): ?>
                    <div class="cleanup-actions">
                        <h3>🧹 Cleanup Actions</h3>
                        <p>The comprehensive cleanup will safely remove all orphaned data and fix database issues.</p>
                        
                        <div class="cleanup-buttons">
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=upcoming-movies&comprehensive_cleanup=1'), 'comprehensive_cleanup', 'cleanup_nonce'); ?>" 
                               class="button button-primary cleanup-btn" 
                               onclick="return confirm('⚠️ This will permanently delete orphaned data and images. This action cannot be undone.\n\nWhat will be cleaned:\n• <?php echo esc_html($orphaned_meta); ?> orphaned metadata entries\n• <?php echo esc_html($orphaned_images); ?> orphaned images\n• <?php echo esc_html($broken_posts); ?> broken posts\n• Database optimization\n\nContinue?');">
                                🧹 Run Comprehensive Cleanup
                            </a>
                            
                            <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" class="button button-secondary">
                                🔗 Fix Permalinks
                            </a>
                            
                            <a href="<?php echo esc_url(add_query_arg('show_diagnostic', '1')); ?>" class="button button-secondary">
                                🔍 <?php echo isset($_GET['show_diagnostic']) ? 'Hide' : 'Show'; ?> Advanced Diagnostic
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="all-good">
                        <h3>✅ Database is Healthy</h3>
                        <p>No issues found. Your movies database is clean and optimized!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ADVANCED DIAGNOSTIC (Hidden by default) -->
    <?php if (isset($_GET['show_diagnostic'])): ?>
        <div class="advanced-diagnostic">
            <div class="card">
                <h2>🔬 Advanced Database Analysis</h2>
                
                <div class="diagnostic-tabs">
                    <button class="tab-button active" onclick="showTab('posts-analysis')">Posts Analysis</button>
                    <button class="tab-button" onclick="showTab('metadata-analysis')">Metadata Analysis</button>
                    <button class="tab-button" onclick="showTab('url-testing')">URL Testing</button>
                    <button class="tab-button" onclick="showTab('system-info')">System Info</button>
                </div>
                
                <!-- Posts Analysis Tab -->
                <div id="posts-analysis" class="tab-content active">
                    <h3>📊 Posts Analysis</h3>
                    <?php
                    $all_movies = $wpdb->get_results("
                        SELECT p.ID, p.post_title, p.post_status, p.post_date, pm.meta_value as tmdb_id
                        FROM {$wpdb->posts} p
                        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'tmdb_id'
                        WHERE p.post_type = 'upcoming_movie'
                        ORDER BY p.ID DESC
                        LIMIT 20
                    ");
                    ?>
                    
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Post ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>TMDB ID</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_movies)): ?>
                                <?php foreach ($all_movies as $movie): ?>
                                    <tr>
                                        <td><?php echo esc_html($movie->ID); ?></td>
                                        <td>
                                            <strong><?php echo esc_html($movie->post_title); ?></strong>
                                            <?php if (empty($movie->tmdb_id)): ?>
                                                <br><span style="color: red; font-size: 0.8em;">⚠️ Missing TMDB ID</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-<?php echo esc_attr($movie->post_status); ?>">
                                                <?php echo esc_html(ucfirst($movie->post_status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($movie->tmdb_id ?: '—'); ?></td>
                                        <td><?php echo esc_html(date_i18n('M j, Y', strtotime($movie->post_date))); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($movie->ID)); ?>" class="button button-small">Edit</a>
                                            <?php if ($movie->post_status === 'publish'): ?>
                                                <a href="<?php echo esc_url(get_permalink($movie->ID)); ?>" class="button button-small" target="_blank">View</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No movie posts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Metadata Analysis Tab -->
                <div id="metadata-analysis" class="tab-content">
                    <h3>🏷️ Metadata Analysis</h3>
                    <?php
                    $metadata_stats = $wpdb->get_results("
                        SELECT pm.meta_key, COUNT(*) as count
                        FROM {$wpdb->postmeta} pm
                        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                        WHERE p.post_type = 'upcoming_movie'
                        AND pm.meta_key IN ('tmdb_id', 'youtube_id', 'streaming_platform', 'release_date', 'overview')
                        GROUP BY pm.meta_key
                        ORDER BY count DESC
                    ");
                    ?>
                    
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th>Metadata Field</th>
                                <th>Count</th>
                                <th>Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metadata_stats as $stat): ?>
                                <?php $completion_rate = $total_movies > 0 ? round(($stat->count / $total_movies) * 100, 1) : 0; ?>
                                <tr>
                                    <td><?php echo esc_html($stat->meta_key); ?></td>
                                    <td><?php echo esc_html($stat->count); ?></td>
                                    <td>
                                        <div class="completion-bar">
                                            <div class="completion-fill" style="width: <?php echo esc_attr($completion_rate); ?>%"></div>
                                            <span><?php echo esc_html($completion_rate); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- URL Testing Tab -->
                <div id="url-testing" class="tab-content">
                    <h3>🔗 URL Testing</h3>
                    <?php
                    $test_movies = $wpdb->get_results("
                        SELECT p.ID, p.post_name, p.post_title 
                        FROM {$wpdb->posts} p 
                        WHERE p.post_type = 'upcoming_movie' 
                        AND p.post_status = 'publish' 
                        LIMIT 5
                    ");
                    ?>
                    
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th>Movie</th>
                                <th>URL</th>
                                <th>Status</th>
                                <th>Test</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($test_movies as $movie): ?>
                                <?php
                                $movie_url = home_url('/movie/' . $movie->post_name . '/');
                                $response = wp_remote_head($movie_url, array('timeout' => 5));
                                $status = is_wp_error($response) ? 'Error' : wp_remote_retrieve_response_code($response);
                                $status_class = ($status === 200) ? 'status-good' : 'status-error';
                                ?>
                                <tr>
                                    <td><?php echo esc_html($movie->post_title); ?></td>
                                    <td><code><?php echo esc_html($movie_url); ?></code></td>
                                    <td>
                                        <span class="<?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($movie_url); ?>" target="_blank" class="button button-small">Test</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- System Info Tab -->
                <div id="system-info" class="tab-content">
                    <h3>⚙️ System Information</h3>
                    
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Plugin Version</td>
                                <td><span class="status-good">✓</span></td>
                                <td><?php echo esc_html(UPCOMING_MOVIES_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td>WordPress Version</td>
                                <td><span class="status-good">✓</span></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td>TMDB API</td>
                                <td>
                                    <?php if (!empty(get_option('upcoming_movies_tmdb_api_key'))): ?>
                                        <span class="status-good">✓ Configured</span>
                                    <?php else: ?>
                                        <span class="status-error">✗ Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td>Required for movie data</td>
                            </tr>
                            <tr>
                                <td>OpenAI API</td>
                                <td>
                                    <?php if (!empty(get_option('upcoming_movies_openai_api_key'))): ?>
                                        <span class="status-good">✓ Configured</span>
                                    <?php else: ?>
                                        <span class="status-error">✗ Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td>Required for article generation</td>
                            </tr>
                            <tr>
                                <td>Rewrite Rules</td>
                                <td>
                                    <?php $rules_working = check_movie_rewrite_rules(); ?>
                                    <?php if ($rules_working): ?>
                                        <span class="status-good">✓ Working</span>
                                    <?php else: ?>
                                        <span class="status-error">✗ Broken</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$rules_working): ?>
                                        <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" class="button button-small">Fix</a>
                                    <?php else: ?>
                                        URLs: /movie/movie-title/
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- MOVIES LIST SECTION -->
    <div class="movies-section">
        <div class="section-header">
            <h2><?php esc_html_e('Your Movies', 'upcoming-movies'); ?></h2>
            <div class="header-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=upcoming-movies-add')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Add Movie', 'upcoming-movies'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=upcoming-movies-mass-producer')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-performance"></span>
                    <?php esc_html_e('Mass Producer', 'upcoming-movies'); ?>
                </a>
            </div>
        </div>

        <?php if (empty($upcoming_movies)): ?>
            <div class="empty-state">
                <div class="empty-icon">🎬</div>
                <h3><?php esc_html_e('No Movies Yet', 'upcoming-movies'); ?></h3>
                <p><?php esc_html_e('Start building your movie database by adding your first movie!', 'upcoming-movies'); ?></p>
                <div class="empty-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=upcoming-movies-add')); ?>" class="button button-primary button-large">
                        <?php esc_html_e('Add Your First Movie', 'upcoming-movies'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=upcoming-movies-mass-producer')); ?>" class="button button-secondary button-large">
                        <?php esc_html_e('Use Mass Producer', 'upcoming-movies'); ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Movies Table -->
            <div class="movies-table-container">
                <table class="wp-list-table widefat fixed striped movies-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;"><?php esc_html_e('Image', 'upcoming-movies'); ?></th>
                            <th><?php esc_html_e('Title', 'upcoming-movies'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Platform', 'upcoming-movies'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Release Date', 'upcoming-movies'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Trailer', 'upcoming-movies'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Status', 'upcoming-movies'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('Actions', 'upcoming-movies'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_movies as $movie):
                            $release_date = get_post_meta($movie->ID, 'release_date', true);
                            $formatted_date = (!empty($release_date) && strtotime($release_date)) ? 
                                date_i18n(get_option('date_format'), strtotime($release_date)) : 
                                __('Unknown', 'upcoming-movies');

                            $streaming_platform = get_post_meta($movie->ID, 'streaming_platform', true);
                            $youtube_id = get_post_meta($movie->ID, 'youtube_id', true);
                            $tmdb_id = get_post_meta($movie->ID, 'tmdb_id', true);
                            $has_trailer = !empty($youtube_id) ? '✓' : '—';

                            $edit_link = get_edit_post_link($movie->ID);
                            $view_link = get_permalink($movie->ID);
                            $post_status = get_post_status($movie->ID);

                            // Delete URL with nonce
                            $delete_url = admin_url('admin.php');
                            $delete_url = add_query_arg(array(
                                'page' => 'upcoming-movies',
                                'action' => 'delete_movie',
                                'movie_id' => $movie->ID,
                                '_wpnonce' => wp_create_nonce('upcoming_movies_delete_movie_' . $movie->ID)
                            ), $delete_url);
                            ?>
                            <tr class="movie-row">
                                <td>
                                    <div class="movie-thumbnail">
                                        <?php if (has_post_thumbnail($movie->ID)): ?>
                                            <?php echo get_the_post_thumbnail($movie->ID, array(60, 90), array(
                                                'alt' => esc_attr($movie->post_title) . ' Poster'
                                            )); ?>
                                        <?php else: ?>
                                            <div class="no-thumbnail">
                                                <span>🎬</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="movie-title-info">
                                        <strong class="movie-title">
                                            <?php if ($edit_link): ?>
                                                <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($movie->post_title); ?></a>
                                            <?php else: ?>
                                                <?php echo esc_html($movie->post_title); ?>
                                            <?php endif; ?>
                                        </strong>
                                        
                                        <div class="movie-meta">
                                            <?php if ($post_status !== 'publish'): ?>
                                                <span class="post-state"><?php echo esc_html(ucfirst($post_status)); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($tmdb_id): ?>
                                                <span class="tmdb-id">TMDB: <?php echo esc_html($tmdb_id); ?></span>
                                            <?php else: ?>
                                                <span class="tmdb-id missing">⚠️ No TMDB ID</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($streaming_platform): ?>
                                        <span class="platform-badge">
                                            <?php echo esc_html($streaming_platform); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-data">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="release-date"><?php echo esc_html($formatted_date); ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($has_trailer === '✓'): ?>
                                        <span class="has-trailer" title="Has trailer">✓</span>
                                    <?php else: ?>
                                        <span class="no-trailer" title="No trailer">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-indicator status-<?php echo esc_attr($post_status); ?>">
                                        <?php echo esc_html(ucfirst($post_status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($edit_link): ?>
                                            <a href="<?php echo esc_url($edit_link); ?>" class="button button-small" title="Edit movie">
                                                <?php esc_html_e('Edit', 'upcoming-movies'); ?>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($view_link && 'publish' === $post_status): ?>
                                            <a href="<?php echo esc_url($view_link); ?>" class="button button-small" target="_blank" title="View on site">
                                                <?php esc_html_e('View', 'upcoming-movies'); ?>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo esc_url($delete_url); ?>" 
                                           class="button button-small button-link-delete delete-movie-btn" 
                                           title="Delete movie"
                                           data-movie-title="<?php echo esc_attr($movie->post_title); ?>">
                                             <?php esc_html_e('Delete', 'upcoming-movies'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- QUICK ACTIONS SECTION -->
    <div class="quick-actions-section">
        <div class="card">
            <h3><?php esc_html_e('Quick Actions', 'upcoming-movies'); ?></h3>
            <div class="action-grid">
                <div class="action-item">
                    <h4>📝 Individual Movies</h4>
                    <p>Search TMDB and create detailed movie articles one at a time.</p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=upcoming-movies-add')); ?>" class="button button-primary">
                        Add Single Movie
                    </a>
                </div>
                
                <div class="action-item">
                    <h4>🚀 Mass Production</h4>
                    <p>Generate 5 complete movie articles at once for any streaming platform.</p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=upcoming-movies-mass-producer')); ?>" class="button button-secondary">
                        Mass Producer
                    </a>
                </div>
                
                <div class="action-item">
                    <h4>⚙️ Configuration</h4>
                    <p>Configure API keys and plugin settings.</p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=upcoming-movies-settings')); ?>" class="button button-secondary">
                        Settings
                    </a>
                </div>
                
                <div class="action-item">
                    <h4>🔗 Fix URLs</h4>
                    <p>If movie pages show 404 errors, flush the permalink structure.</p>
                    <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" class="button button-secondary">
                        Fix Permalinks
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Admin Styles */
.upcoming-movies-admin {
    max-width: 1400px;
}

.title-count {
    background: #72aee6;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
    margin-left: 1rem;
}

/* System Status Dashboard */
.system-status-dashboard {
    margin: 20px 0 30px 0;
}

.status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.status-card.status-good {
    border-left: 4px solid #00a32a;
}

.status-card.status-warning {
    border-left: 4px solid #dba617;
}

.status-card.status-error {
    border-left: 4px solid #dc3232;
}

.status-icon {
    font-size: 2rem;
    line-height: 1;
}

.status-info h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: bold;
}

.status-info p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

/* Diagnostic Section */
.diagnostic-section, .advanced-diagnostic {
    margin: 30px 0;
}

.diagnostic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.diagnostic-item {
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #ddd;
}

.diagnostic-item.error {
    background: #fef2f2;
    border-left-color: #dc3232;
}

.diagnostic-item.warning {
    background: #fffbf0;
    border-left-color: #dba617;
}

.diagnostic-item h4 {
    margin: 0 0 10px 0;
    font-size: 1rem;
}

.diagnostic-item p {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
}

.diagnostic-item details {
    margin-top: 10px;
}

.diagnostic-item summary {
    cursor: pointer;
    color: #2271b1;
    font-size: 0.9rem;
}

/* Cleanup Actions */
.cleanup-actions {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.cleanup-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.cleanup-btn {
    font-weight: 600;
}

.all-good {
    text-align: center;
    padding: 40px;
    background: #f0f9ff;
    border-radius: 8px;
    margin: 20px 0;
}

.all-good h3 {
    color: #00a32a;
    margin: 0 0 10px 0;
}

/* Advanced Diagnostic Tabs */
.diagnostic-tabs {
    display: flex;
    border-bottom: 1px solid #ddd;
    margin-bottom: 20px;
}

.tab-button {
    background: none;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    font-size: 0.9rem;
}

.tab-button.active {
    border-bottom-color: #2271b1;
    color: #2271b1;
    font-weight: 600;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.completion-bar {
    position: relative;
    background: #f0f0f0;
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
    min-width: 100px;
}

.completion-fill {
    height: 100%;
    background: linear-gradient(90deg, #00a32a, #00ba37);
    transition: width 0.3s ease;
}

.completion-bar span {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.7rem;
    font-weight: 600;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

/* Movies Section */
.movies-section {
    margin: 30px 0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-header h2 {
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.header-actions .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 15px 0;
    color: #1d2327;
}

.empty-state p {
    color: #646970;
    margin-bottom: 30px;
    font-size: 1.1rem;
}

.empty-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Movies Table */
.movies-table-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.movies-table {
    margin: 0;
    border: none;
}

.movies-table th {
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    font-weight: 600;
    padding: 15px 12px;
}

.movies-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}

.movie-row:hover {
    background: #f8f9fa;
}

.movie-thumbnail {
    width: 60px;
    height: 90px;
    overflow: hidden;
    border-radius: 4px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.movie-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-thumbnail {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #999;
}

.movie-title-info {
    min-width: 200px;
}

.movie-title {
    display: block;
    margin-bottom: 8px;
    font-size: 1rem;
    line-height: 1.3;
}

.movie-title a {
    text-decoration: none;
    color: #2271b1;
}

.movie-title a:hover {
    color: #135e96;
}

.movie-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.post-state {
    background: #dba617;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
}

.tmdb-id {
    font-size: 0.75rem;
    color: #666;
    font-family: monospace;
}

.tmdb-id.missing {
    color: #dc3232;
    font-weight: 600;
}

.platform-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
}

.no-data {
    color: #999;
    font-style: italic;
}

.release-date {
    font-weight: 500;
    color: #2271b1;
}

.has-trailer {
    color: #00a32a;
    font-size: 1.2rem;
    font-weight: bold;
}

.no-trailer {
    color: #ccc;
    font-size: 1.2rem;
}

.status-indicator {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-publish {
    background: #d4edda;
    color: #155724;
}

.status-draft {
    background: #fff3cd;
    color: #856404;
}

.status-pending {
    background: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.delete-movie-btn {
    color: #dc3232 !important;
}

.delete-movie-btn:hover {
    background: #dc3232 !important;
    color: white !important;
}

/* Quick Actions */
.quick-actions-section {
    margin: 40px 0;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.action-item {
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: white;
    text-align: center;
}

.action-item h4 {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
}

.action-item p {
    margin: 0 0 20px 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

.action-item .button {
    min-width: 140px;
}

/* Status Indicators */
.status-good {
    color: #00a32a;
    font-weight: 600;
}

.status-error {
    color: #dc3232;
    font-weight: 600;
}

.status-warning {
    color: #dba617;
    font-weight: 600;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .upcoming-movies-admin {
        max-width: 100%;
    }
    
    .status-cards {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    
    .diagnostic-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .section-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: center;
    }
    
    .status-cards {
        grid-template-columns: 1fr;
    }
    
    .movies-table {
        font-size: 0.9rem;
    }
    
    .movies-table th,
    .movies-table td {
        padding: 10px 8px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-grid {
        grid-template-columns: 1fr;
    }
    
    .cleanup-buttons {
        flex-direction: column;
    }
    
    .diagnostic-tabs {
        flex-wrap: wrap;
    }
    
    .tab-button {
        padding: 8px 12px;
        font-size: 0.8rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Enhanced delete confirmation
    $('.delete-movie-btn').on('click', function(e) {
        e.preventDefault();
        
        const movieTitle = $(this).data('movie-title') || 'this movie';
        const deleteUrl = $(this).attr('href');
        
        if (confirm(`⚠️ Are you sure you want to delete "${movieTitle}"?\n\nThis action cannot be undone.`)) {
            window.location.href = deleteUrl;
        }
    });
});

// Tab functionality for advanced diagnostics
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}
</script>