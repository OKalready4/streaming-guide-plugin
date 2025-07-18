<?php
// templates/add-movie.php - ENHANCED with search and direct TMDB input
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap upcoming-movies-admin">
    <h1><?php esc_html_e('Add New Movie or TV Show', 'upcoming-movies'); ?></h1>
    
    <?php if (isset($error_message) && !empty($error_message)): ?>
        <div class="notice notice-error">
            <p><?php echo wp_kses_post($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($tmdb_api_key)): ?>
        <div class="notice notice-error">
            <h3><?php esc_html_e('TMDB API Key Required', 'upcoming-movies'); ?></h3>
            <p><?php echo sprintf(
                __('Please configure your TMDB API Key in <a href="%s">Settings</a> to search and add movies/shows.', 'upcoming-movies'),
                admin_url('admin.php?page=upcoming-movies-settings')
            ); ?></p>
        </div>
    <?php else: ?>
    
    <!-- SEARCH METHOD -->
    <div class="card search-method">
        <h2><?php esc_html_e('Method 1: Search TMDB Database', 'upcoming-movies'); ?></h2>
        <p class="description"><?php esc_html_e('Search The Movie Database for movies and TV shows by title.', 'upcoming-movies'); ?></p>
        
        <form method="post" action="" id="movie-search-form">
            <?php wp_nonce_field('upcoming_movies_search'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="movie_title"><?php esc_html_e('Search Title', 'upcoming-movies'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="movie_title" name="movie_title" class="regular-text" 
                               placeholder="<?php esc_attr_e('e.g., The Last of Us, Avengers, Stranger Things', 'upcoming-movies'); ?>" 
                               value="<?php echo isset($_POST['movie_title']) ? esc_attr($_POST['movie_title']) : ''; ?>" required>
                        <p class="description"><?php esc_html_e('Enter the title of the movie or TV show you want to find.', 'upcoming-movies'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="search_type"><?php esc_html_e('Content Type', 'upcoming-movies'); ?></label>
                    </th>
                    <td>
                        <select name="search_type" id="search_type">
                            <option value="multi"><?php esc_html_e('Movies & TV Shows', 'upcoming-movies'); ?></option>
                            <option value="movie"><?php esc_html_e('Movies Only', 'upcoming-movies'); ?></option>
                            <option value="tv"><?php esc_html_e('TV Shows Only', 'upcoming-movies'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose what type of content to search for.', 'upcoming-movies'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="search_movie" class="button button-primary" 
                       value="<?php esc_attr_e('🔍 Search TMDB', 'upcoming-movies'); ?>">
            </p>
        </form>
    </div>
    
    <!-- DIRECT TMDB ID METHOD -->
    <div class="card direct-method">
        <h2><?php esc_html_e('Method 2: Direct TMDB ID Input', 'upcoming-movies'); ?></h2>
        <p class="description"><?php esc_html_e('Enter a TMDB ID directly. Perfect for specific content like "The Last of Us" (ID: 100088).', 'upcoming-movies'); ?></p>
        
        <form method="post" action="" id="direct-tmdb-form">
            <?php wp_nonce_field('upcoming_movies_search'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="direct_tmdb_id"><?php esc_html_e('TMDB ID', 'upcoming-movies'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="direct_tmdb_id" name="direct_tmdb_id" class="regular-text" 
                               placeholder="<?php esc_attr_e('e.g., 100088', 'upcoming-movies'); ?>" 
                               min="1" required>
                        <p class="description">
                            <?php esc_html_e('Find the TMDB ID in the URL on themoviedb.org', 'upcoming-movies'); ?><br>
                            <strong><?php esc_html_e('Examples:', 'upcoming-movies'); ?></strong><br>
                            • The Last of Us: <code>100088</code><br>
                            • Stranger Things: <code>66732</code><br>
                            • Top Gun: Maverick: <code>361743</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="direct_content_type"><?php esc_html_e('Content Type', 'upcoming-movies'); ?></label>
                    </th>
                    <td>
                        <select name="direct_content_type" id="direct_content_type">
                            <option value="auto"><?php esc_html_e('Auto-Detect', 'upcoming-movies'); ?></option>
                            <option value="movie"><?php esc_html_e('Movie', 'upcoming-movies'); ?></option>
                            <option value="tv"><?php esc_html_e('TV Show', 'upcoming-movies'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Let the system auto-detect or specify if you know the type.', 'upcoming-movies'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="direct_platform"><?php esc_html_e('Platform', 'upcoming-movies'); ?></label>
                    </th>
                    <td>
                        <select name="direct_platform" id="direct_platform" required>
                            <option value=""><?php esc_html_e('Select Platform', 'upcoming-movies'); ?></option>
                            <option value="Netflix">🔴 Netflix</option>
                            <option value="Disney+">✨ Disney+</option>
                            <option value="Max">🔵 Max (HBO Max)</option>
                            <option value="Prime Video">📦 Prime Video</option>
                            <option value="Apple TV+">🍎 Apple TV+</option>
                            <option value="Paramount+">⭐ Paramount+</option>
                            <option value="Hulu">🟢 Hulu</option>
                            <option value="Peacock">🦚 Peacock</option>
                            <option value="Theatrical Release">🎬 Theatrical Release</option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose which platform this content will be tagged with.', 'upcoming-movies'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="add_direct_tmdb" class="button button-secondary" 
                       value="<?php esc_attr_e('🎯 Create from TMDB ID', 'upcoming-movies'); ?>">
            </p>
        </form>
    </div>
    
    <?php endif; ?>
    
    <?php if (isset($search_results) && !empty($search_results)): ?>
    <div class="card search-results">
        <h2><?php esc_html_e('Search Results', 'upcoming-movies'); ?></h2>
        <p class="description">
            <?php printf(
                esc_html__('Found %d results for "%s". Click "Add This" to create an article.', 'upcoming-movies'),
                count($search_results['results']),
                esc_html($_POST['movie_title'])
            ); ?>
        </p>
        
        <div class="movie-search-results">
            <?php
            $results_shown = 0;
            foreach ($search_results['results'] as $result) {
                if ($results_shown >= 20) break; // Limit results
                
                // Determine if it's a movie or TV show
                $is_tv = isset($result['media_type']) && $result['media_type'] === 'tv';
                if (!isset($result['media_type'])) {
                    $is_tv = isset($result['name']) && !isset($result['title']);
                }
                
                $title = $is_tv ? ($result['name'] ?? 'Unknown Title') : ($result['title'] ?? 'Unknown Title');
                $release_date = $is_tv ? ($result['first_air_date'] ?? '') : ($result['release_date'] ?? '');
                $poster_path = $result['poster_path'] ?? '';
                $overview = $result['overview'] ?? '';
                $vote_average = $result['vote_average'] ?? 0;
                $tmdb_id = $result['id'] ?? 0;
                
                // Skip if no valid ID
                if (empty($tmdb_id)) continue;
                
                // Check if already exists
                $plugin_instance = Upcoming_Movies_Feature::get_instance();
                $already_exists = method_exists($plugin_instance, 'movie_exists') ? 
                                 $plugin_instance->movie_exists($tmdb_id) : false;
                
                $results_shown++;
                ?>
                <div class="movie-result-card <?php echo $already_exists ? 'already-exists' : ''; ?>">
                    <div class="movie-poster-container">
                        <?php if (!empty($poster_path)): ?>
                            <img src="https://image.tmdb.org/t/p/w300<?php echo esc_attr($poster_path); ?>" 
                                 alt="<?php echo esc_attr($title); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-poster-placeholder">
                                <span><?php echo $is_tv ? '📺' : '🎬'; ?></span>
                                <small>No Image</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="content-type-badge">
                            <?php echo $is_tv ? '📺 TV Show' : '🎬 Movie'; ?>
                        </div>
                        
                        <?php if ($already_exists): ?>
                            <div class="exists-overlay">
                                <span>✅ Already Added</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="movie-result-details">
                        <h3 class="movie-result-title"><?php echo esc_html($title); ?></h3>
                        
                        <?php if (!empty($release_date)): ?>
                            <p class="movie-result-date">
                                📅 <?php echo esc_html(date_i18n('M j, Y', strtotime($release_date))); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($vote_average > 0): ?>
                            <p class="movie-result-rating">
                                ⭐ <?php echo esc_html(number_format($vote_average, 1)); ?>/10
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($overview)): ?>
                            <p class="movie-result-overview"><?php echo esc_html(wp_trim_words($overview, 20)); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!$already_exists): ?>
                            <form method="post" action="" class="add-movie-form">
                                <?php wp_nonce_field('upcoming_movies_search'); ?>
                                <input type="hidden" name="movie_id" value="<?php echo esc_attr($tmdb_id); ?>">
                                <input type="hidden" name="content_type" value="<?php echo $is_tv ? 'tv' : 'movie'; ?>">
                                
                                <div class="platform-selection">
                                    <label for="platform_<?php echo esc_attr($tmdb_id); ?>">
                                        <strong><?php esc_html_e('Platform:', 'upcoming-movies'); ?></strong>
                                    </label>
                                    <select name="streaming_platform" id="platform_<?php echo esc_attr($tmdb_id); ?>" required>
                                        <option value=""><?php esc_html_e('Select Platform', 'upcoming-movies'); ?></option>
                                        <option value="Netflix">🔴 Netflix</option>
                                        <option value="Disney+">✨ Disney+</option>
                                        <option value="Max">🔵 Max (HBO Max)</option>
                                        <option value="Prime Video">📦 Prime Video</option>
                                        <option value="Apple TV+">🍎 Apple TV+</option>
                                        <option value="Paramount+">⭐ Paramount+</option>
                                        <option value="Hulu">🟢 Hulu</option>
                                        <option value="Peacock">🦚 Peacock</option>
                                        <option value="Theatrical Release">🎬 Theatrical Release</option>
                                    </select>
                                </div>
                                
                                <input type="submit" name="add_upcoming_movie" class="button button-primary" 
                                       value="<?php esc_attr_e('➕ Add This', 'upcoming-movies'); ?>">
                            </form>
                        <?php else: ?>
                            <p class="already-exists-message">
                                <span style="color: #00a32a;">✅ This content is already on your site</span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            
            if ($results_shown === 0): ?>
                <div class="no-results">
                    <h3><?php esc_html_e('No Results Found', 'upcoming-movies'); ?></h3>
                    <p><?php esc_html_e('Try a different search term or use the direct TMDB ID method above.', 'upcoming-movies'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- HELP SECTION -->
    <div class="card help-section">
        <h2><?php esc_html_e('Need Help?', 'upcoming-movies'); ?></h2>
        
        <div class="help-grid">
            <div class="help-item">
                <h4>🔍 Search Tips</h4>
                <ul>
                    <li>Use the original title for best results</li>
                    <li>Try both movie and TV show searches</li>
                    <li>Include release year if needed (e.g., "Top Gun 2022")</li>
                    <li>Check spelling and try variations</li>
                </ul>
            </div>
            
            <div class="help-item">
                <h4>🎯 Finding TMDB IDs</h4>
                <ul>
                    <li>Go to <a href="https://www.themoviedb.org" target="_blank">themoviedb.org</a></li>
                    <li>Search for your content</li>
                    <li>Look at the URL: /movie/123456 or /tv/123456</li>
                    <li>The number is your TMDB ID</li>
                </ul>
            </div>
            
            <div class="help-item">
                <h4>📺 TV Shows vs Movies</h4>
                <ul>
                    <li>TV shows have series-level TMDB IDs</li>
                    <li>Don't use season or episode IDs</li>
                    <li>Auto-detect works for most content</li>
                    <li>When in doubt, try both search methods</li>
                </ul>
            </div>
            
            <div class="help-item">
                <h4>🔧 Troubleshooting</h4>
                <ul>
                    <li>Make sure your TMDB API key is configured</li>
                    <li>Check if content already exists on your site</li>
                    <li>Try the <a href="<?php echo admin_url('admin.php?page=upcoming-movies-mass-producer'); ?>">Mass Producer</a> for bulk adding</li>
                    <li>Contact support if issues persist</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Add Movie Page Styles */
.upcoming-movies-admin .card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.direct-method {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: none !important;
}

.direct-method h2,
.direct-method .form-table th,
.direct-method .form-table td,
.direct-method .description {
    color: white;
}

.direct-method code {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
}

.search-method {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none !important;
}

.search-method h2,
.search-method .form-table th,
.search-method .form-table td,
.search-method .description {
    color: white;
}

.movie-search-results {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.movie-result-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
}

.movie-result-card:hover:not(.already-exists) {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.movie-result-card.already-exists {
    opacity: 0.7;
    background: #f9f9f9;
}

.movie-poster-container {
    position: relative;
    height: 0;
    padding-bottom: 150%; /* 2:3 aspect ratio */
    overflow: hidden;
    background: #f5f5f5;
}

.movie-poster-container img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-poster-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    color: #64748b;
    font-size: 3rem;
    gap: 0.5rem;
}

.content-type-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.exists-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 163, 42, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
}

.movie-result-details {
    padding: 15px;
}

.movie-result-title {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    font-weight: 600;
    line-height: 1.3;
    color: #1f2937;
}

.movie-result-date,
.movie-result-rating {
    margin: 5px 0;
    font-size: 0.9rem;
    color: #6b7280;
}

.movie-result-overview {
    margin: 10px 0;
    font-size: 0.9rem;
    line-height: 1.4;
    color: #4b5563;
}

.platform-selection {
    margin: 15px 0 10px 0;
}

.platform-selection label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #374151;
}

.platform-selection select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: white;
    font-size: 0.9rem;
}

.add-movie-form {
    margin-top: 15px;
}

.already-exists-message {
    margin: 15px 0 0 0;
    text-align: center;
    font-weight: 500;
}

.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px;
    background: #f9fafb;
    border-radius: 8px;
    border: 2px dashed #d1d5db;
}

.help-section {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.help-item {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.help-item h4 {
    margin: 0 0 10px 0;
    color: #1f2937;
    font-size: 1rem;
}

.help-item ul {
    margin: 0;
    padding-left: 20px;
}

.help-item li {
    margin: 5px 0;
    font-size: 0.9rem;
    color: #4b5563;
    line-height: 1.4;
}

.help-item a {
    color: #2563eb;
    text-decoration: none;
}

.help-item a:hover {
    text-decoration: underline;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .movie-search-results {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .help-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .movie-result-card {
        margin-bottom: 15px;
    }
}

@media (max-width: 480px) {
    .movie-search-results {
        grid-template-columns: 1fr;
    }
    
    .upcoming-movies-admin .card {
        margin: 15px 0;
        padding: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎬 Enhanced Add Movie Page Loaded');
    
    // Enhanced TMDB ID validation
    $('#direct_tmdb_id').on('input', function() {
        const value = $(this).val();
        const isValid = /^\d+$/.test(value) && parseInt(value) > 0;
        
        // Remove existing validation
        $('.tmdb-id-validation').remove();
        
        if (value.length > 0) {
            let message = '';
            let className = '';
            
            if (isValid) {
                message = '✅ Valid TMDB ID format';
                className = 'notice-success';
            } else {
                message = '❌ Invalid format. Use numbers only.';
                className = 'notice-error';
            }
            
            $(this).after(`<div class="tmdb-id-validation notice ${className} inline" style="margin-top: 5px; padding: 5px 10px;"><p style="margin: 0; font-size: 0.9em;">${message}</p></div>`);
        }
    });
    
    // Form validation for direct TMDB
    $('#direct-tmdb-form').on('submit', function(e) {
        const tmdbId = $('#direct_tmdb_id').val();
        const platform = $('#direct_platform').val();
        
        if (!tmdbId || !platform) {
            e.preventDefault();
            alert('Please fill in all required fields.');
            return;
        }
        
        if (!/^\d+$/.test(tmdbId) || parseInt(tmdbId) <= 0) {
            e.preventDefault();
            alert('Please enter a valid TMDB ID (numbers only).');
            return;
        }
        
        // Show confirmation
        if (!confirm(`Create article for TMDB ID ${tmdbId} on ${platform}?`)) {
            e.preventDefault();
            return;
        }
    });
    
    // Enhanced search form validation
    $('#movie-search-form').on('submit', function(e) {
        const searchTerm = $('#movie_title').val().trim();
        
        if (searchTerm.length < 2) {
            e.preventDefault();
            alert('Please enter at least 2 characters to search.');
            return;
        }
    });
    
    // Platform selection auto-focus
    $('.movie-result-card select[name="streaming_platform"]').on('change', function() {
        if ($(this).val()) {
            $(this).closest('.movie-result-card').find('input[type="submit"]').focus();
        }
    });
    
    console.log('✅ Enhanced Add Movie Page Ready');
});
</script>