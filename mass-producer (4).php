<?php
// templates/mass-producer.php - FIXED Mass Producer Interface
if (!defined('ABSPATH')) {
    exit;
}

// Get plugin instance for API access
$plugin_instance = Upcoming_Movies_Feature::get_instance();
?>
<div class="wrap upcoming-movies-admin">
    <h1><?php esc_html_e('Mass Producer - Generate 5 Movies per Platform', 'upcoming-movies'); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php esc_html_e('How it works:', 'upcoming-movies'); ?></strong> 
        <?php esc_html_e('Select a streaming platform, discover popular movies, then generate 5 complete articles with images and metadata automatically.', 'upcoming-movies'); ?></p>
    </div>
    
    <?php
    // Check API configuration
    $tmdb_configured = !empty(get_option('upcoming_movies_tmdb_api_key'));
    $openai_configured = !empty(get_option('upcoming_movies_openai_api_key'));
    
    if (!$tmdb_configured || !$openai_configured):
    ?>
        <div class="notice notice-error">
            <h3><?php esc_html_e('API Configuration Required', 'upcoming-movies'); ?></h3>
            <p><?php esc_html_e('Please configure your API keys before using the Mass Producer:', 'upcoming-movies'); ?></p>
            <ul>
                <?php if (!$tmdb_configured): ?>
                    <li>❌ <strong>TMDB API Key</strong> - Required for movie data</li>
                <?php else: ?>
                    <li>✅ <strong>TMDB API Key</strong> - Configured</li>
                <?php endif; ?>
                
                <?php if (!$openai_configured): ?>
                    <li>❌ <strong>OpenAI API Key</strong> - Required for article generation</li>
                <?php else: ?>
                    <li>✅ <strong>OpenAI API Key</strong> - Configured</li>
                <?php endif; ?>
            </ul>
            <p>
                <a href="<?php echo admin_url('admin.php?page=upcoming-movies-settings'); ?>" class="button button-primary">
                    <?php esc_html_e('Configure API Keys', 'upcoming-movies'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if ($tmdb_configured && $openai_configured): ?>
    <div class="card">
        <h2><?php esc_html_e('Step 1: Select Platform & Discover Movies', 'upcoming-movies'); ?></h2>
        <form method="post" action="" id="discovery-form">
            <?php wp_nonce_field('discover_movies'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="target_platform"><?php esc_html_e('Target Platform', 'upcoming-movies'); ?></label></th>
                    <td>
                        <select name="target_platform" id="target_platform" required>
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
                        <p class="description"><?php esc_html_e('Choose which platform these movies will be tagged with', 'upcoming-movies'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="discover_type"><?php esc_html_e('Movie Discovery Type', 'upcoming-movies'); ?></label></th>
                    <td>
                        <select name="discover_type" id="discover_type">
                            <option value="popular"><?php esc_html_e('Popular Movies', 'upcoming-movies'); ?></option>
                            <option value="upcoming"><?php esc_html_e('Upcoming Releases', 'upcoming-movies'); ?></option>
                            <option value="now_playing"><?php esc_html_e('Now Playing', 'upcoming-movies'); ?></option>
                            <option value="top_rated"><?php esc_html_e('Top Rated', 'upcoming-movies'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Type of movies to discover from TMDB', 'upcoming-movies'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="discover_movies" class="button button-primary" 
                       value="<?php esc_attr_e('🔍 Discover Movies', 'upcoming-movies'); ?>">
            </p>
        </form>
    </div>
    <?php endif; ?>
    
    <?php
    // Handle movie discovery
    if (isset($_POST['discover_movies']) && $tmdb_configured && $openai_configured) {
        check_admin_referer('discover_movies');
        
        $discover_type = sanitize_text_field($_POST['discover_type']);
        $target_platform = sanitize_text_field($_POST['target_platform']);
        
        echo '<div class="discovery-debug" style="background:#f0f0f0; padding:10px; margin:10px 0; border-radius:5px;">';
        echo '<h4>🔍 Discovery Debug Information:</h4>';
        echo '<p><strong>Platform:</strong> ' . esc_html($target_platform) . '</p>';
        echo '<p><strong>Type:</strong> ' . esc_html($discover_type) . '</p>';
        
        // FIXED: Check if discover_movies method exists
        if (!method_exists($plugin_instance, 'discover_movies')) {
            echo '<p style="color:red;"><strong>ERROR:</strong> discover_movies method not found in plugin instance!</p>';
            echo '</div>';
        } else {
            echo '<p><strong>Method found:</strong> ✅ discover_movies exists</p>';
            
            // Get movies from TMDB using the plugin's method
            $movies_result = $plugin_instance->get_tmdb_api()->discover_movies($plugin_instance->get_tmdb_api()->build_discover_params($target_platform, $discover_type));
            
            echo '<p><strong>API Response:</strong> ';
            if (is_wp_error($movies_result)) {
                echo '<span style="color:red;">❌ Error: ' . esc_html($movies_result->get_error_message()) . '</span>';
                echo '</div>';
            } else {
                echo '<span style="color:green;">✅ Success</span></p>';
                echo '<p><strong>Movies Found:</strong> ' . (isset($movies_result['results']) ? count($movies_result['results']) : 0) . '</p>';
                echo '</div>';
                
                if (!empty($movies_result['results'])) {
                    ?>
                    <div class="card">
                        <h2>
                            <?php esc_html_e('Step 2: Select 5 Movies for', 'upcoming-movies'); ?> 
                            <span class="platform-highlight"><?php echo esc_html($target_platform); ?></span>
                        </h2>
                        <p class="description">
                            <?php esc_html_e('Select exactly 5 movies to generate complete articles. Processing happens in the background.', 'upcoming-movies'); ?>
                        </p>
                        
                        <div class="movie-selection-interface">
                            <div class="selection-stats">
                                <span class="selected-count">0</span> / 5 movies selected
                                <div class="selection-actions">
                                    <button type="button" class="button" id="select-all-btn"><?php esc_html_e('Select First 5', 'upcoming-movies'); ?></button>
                                    <button type="button" class="button" id="clear-selection-btn"><?php esc_html_e('Clear All', 'upcoming-movies'); ?></button>
                                </div>
                            </div>
                            
                            <div class="movie-selection-grid">
                                <?php
                                $available_count = 0;
                                foreach ($movies_result['results'] as $movie) {
                                    if ($available_count >= 20) break; // Limit options
                                    
                                    // FIXED: Check if movie already exists using the plugin's method
                                    $exists = method_exists($plugin_instance, 'movie_exists') ? 
                                             $plugin_instance->movie_exists($movie['id']) : false;
                                    
                                    if ($exists) {
                                        echo '<!-- Movie ' . esc_html($movie['title']) . ' already exists, skipping -->';
                                        continue;
                                    }
                                    
                                    $available_count++;
                                    $poster_url = !empty($movie['poster_path']) ? 
                                        'https://image.tmdb.org/t/p/w300' . $movie['poster_path'] : '';
                                    ?>
                                    <div class="movie-select-card" data-movie-id="<?php echo esc_attr($movie['id']); ?>">
                                        <label>
                                            <input type="checkbox" name="selected_movies[]" value="<?php echo esc_attr($movie['id']); ?>" 
                                                   class="movie-checkbox" data-title="<?php echo esc_attr($movie['title']); ?>">
                                            <div class="movie-select-info">
                                                <div class="movie-poster">
                                                    <?php if ($poster_url): ?>
                                                        <img src="<?php echo esc_url($poster_url); ?>" 
                                                             alt="<?php echo esc_attr($movie['title']); ?>" loading="lazy">
                                                    <?php else: ?>
                                                        <div class="no-poster">
                                                            <span>🎬</span>
                                                            <small>No Image</small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="selection-overlay">
                                                        <div class="check-mark">✓</div>
                                                    </div>
                                                </div>
                                                <div class="movie-details">
                                                    <h4><?php echo esc_html($movie['title']); ?></h4>
                                                    <?php if (!empty($movie['release_date'])): ?>
                                                        <p class="release-date">
                                                            📅 <?php echo esc_html(date_i18n('M j, Y', strtotime($movie['release_date']))); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($movie['vote_average'])): ?>
                                                        <p class="rating">
                                                            ⭐ <?php echo esc_html(number_format($movie['vote_average'], 1)); ?>/10
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($movie['overview'])): ?>
                                                        <p class="overview">
                                                            <?php echo esc_html(wp_trim_words($movie['overview'], 15)); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <?php
                                }
                                
                                if ($available_count === 0): ?>
                                    <div class="no-movies-message">
                                        <h3><?php esc_html_e('No New Movies Found', 'upcoming-movies'); ?></h3>
                                        <p><?php esc_html_e('All movies from this search already exist on your site. Try a different discovery type or check back later.', 'upcoming-movies'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($available_count > 0): ?>
                            <div class="mass-generation-controls">
                                <div class="generation-info">
                                    <h3><?php esc_html_e('Ready to Generate?', 'upcoming-movies'); ?></h3>
                                    <p><?php esc_html_e('Each movie will get:', 'upcoming-movies'); ?></p>
                                    <ul class="feature-list">
                                        <li>✅ <?php esc_html_e('Complete SEO-optimized article (600-800 words)', 'upcoming-movies'); ?></li>
                                        <li>✅ <?php esc_html_e('Featured image + 2-3 additional scene images', 'upcoming-movies'); ?></li>
                                        <li>✅ <?php esc_html_e('Movie metadata (runtime, genres, rating)', 'upcoming-movies'); ?></li>
                                        <li>✅ <?php esc_html_e('Trailer embed (if available)', 'upcoming-movies'); ?></li>
                                        <li>✅ <?php esc_html_e('TMDB attribution', 'upcoming-movies'); ?></li>
                                        <li>✅ <?php esc_html_e('Platform assignment', 'upcoming-movies'); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="generation-button-area">
                                    <button type="button" id="mass-generate-btn" class="button button-primary button-hero" disabled>
                                        🚀 <?php esc_html_e('Generate 5 Articles for', 'upcoming-movies'); ?> 
                                        <span class="platform-name"><?php echo esc_html($target_platform); ?></span>
                                    </button>
                                    <input type="hidden" id="target-platform" value="<?php echo esc_attr($target_platform); ?>">
                                    <p class="generation-note">
                                        <?php esc_html_e('⏱️ Processing takes 2-3 minutes. You can leave this page during generation.', 'upcoming-movies'); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="notice notice-warning">
                        <p><?php esc_html_e('No movies found for the selected criteria. Try a different discovery type or platform.', 'upcoming-movies'); ?></p>
                    </div>
                    <?php
                }
            }
        }
    }
    ?>
    
    <!-- Progress Section (Hidden by default) -->
    <div id="mass-producer-progress" style="display: none;">
        <div class="card progress-card">
            <h2><?php esc_html_e('🎬 Generating Movie Articles...', 'upcoming-movies'); ?></h2>
            <div class="progress-container">
                <div class="progress-bar-wrapper">
                    <div class="progress-bar" id="progress-bar"></div>
                    <div class="progress-text" id="progress-text"><?php esc_html_e('Starting generation...', 'upcoming-movies'); ?></div>
                </div>
                <div class="progress-details">
                    <div class="current-movie" id="current-movie"></div>
                    <div class="progress-stats" id="progress-stats"></div>
                </div>
            </div>
            <div class="progress-log" id="progress-log"></div>
        </div>
    </div>
    
    <!-- Recent Generations -->
    <?php
    $recent_movies = get_posts(array(
        'post_type' => 'upcoming_movie',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => 'streaming_platform',
                'compare' => 'EXISTS'
            )
        )
    ));
    
    if (!empty($recent_movies)):
    ?>
    <div class="card">
        <h2><?php esc_html_e('📚 Recently Generated Movies', 'upcoming-movies'); ?></h2>
        <div class="recent-movies-grid">
            <?php foreach ($recent_movies as $movie): 
                $platform = get_post_meta($movie->ID, 'streaming_platform', true);
                $release_date = get_post_meta($movie->ID, 'release_date', true);
            ?>
                <div class="recent-movie-card">
                    <div class="movie-thumb">
                        <?php if (has_post_thumbnail($movie->ID)): ?>
                            <?php echo get_the_post_thumbnail($movie->ID, 'thumbnail'); ?>
                        <?php else: ?>
                            <div class="no-thumb">🎬</div>
                        <?php endif; ?>
                    </div>
                    <div class="movie-info">
                        <h4><a href="<?php echo get_edit_post_link($movie->ID); ?>"><?php echo esc_html($movie->post_title); ?></a></h4>
                        <?php if ($platform): ?>
                            <span class="platform-tag"><?php echo esc_html($platform); ?></span>
                        <?php endif; ?>
                        <?php if ($release_date): ?>
                            <span class="date-tag"><?php echo esc_html(date_i18n('M j', strtotime($release_date))); ?></span>
                        <?php endif; ?>
                        <div class="movie-actions">
                            <a href="<?php echo get_edit_post_link($movie->ID); ?>" class="button button-small"><?php esc_html_e('Edit', 'upcoming-movies'); ?></a>
                            <a href="<?php echo get_permalink($movie->ID); ?>" class="button button-small" target="_blank"><?php esc_html_e('View', 'upcoming-movies'); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Mass Producer Specific Styles */
.platform-highlight {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-weight: 600;
}

.discovery-debug {
    font-family: monospace;
    font-size: 0.9em;
}

.movie-selection-interface {
    margin-top: 1.5rem;
}

.selection-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 2px solid #e2e8f0;
}

.selected-count {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2563eb;
}

.selection-actions {
    display: flex;
    gap: 0.5rem;
}

.movie-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.movie-select-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
    position: relative;
}

.movie-select-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
}

.movie-select-card.selected {
    border-color: #10b981;
    background: #f0fdf4;
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
}

.movie-select-card label {
    display: block;
    cursor: pointer;
    height: 100%;
    position: relative;
}

.movie-select-card input[type="checkbox"] {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    transform: scale(1.5);
    z-index: 10;
}

.movie-poster {
    position: relative;
    height: 240px;
    overflow: hidden;
}

.movie-poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.movie-select-card:hover .movie-poster img {
    transform: scale(1.05);
}

.no-poster {
    height: 100%;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 2rem;
    gap: 0.5rem;
}

.selection-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(16, 185, 129, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.movie-select-card.selected .selection-overlay {
    opacity: 1;
}

.check-mark {
    width: 3rem;
    height: 3rem;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #10b981;
    font-weight: bold;
}

.movie-details {
    padding: 1rem;
}

.movie-details h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.95rem;
    line-height: 1.3;
    font-weight: 600;
    color: #1f2937;
}

.release-date, .rating {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0.25rem 0;
}

.overview {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.4;
    margin: 0.5rem 0 0 0;
}

.mass-generation-controls {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2rem;
    align-items: center;
    background: #f8fafc;
    padding: 2rem;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

.generation-info h3 {
    margin: 0 0 1rem 0;
    color: #1f2937;
    font-size: 1.25rem;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin: 1rem 0 0 0;
}

.feature-list li {
    padding: 0.25rem 0;
    font-size: 0.9rem;
    color: #374151;
}

.generation-button-area {
    text-align: center;
}

.button-hero {
    font-size: 1.1rem !important;
    padding: 1rem 2rem !important;
    height: auto !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
}

.generation-note {
    margin: 1rem 0 0 0;
    font-size: 0.85rem;
    color: #6b7280;
    font-style: italic;
}

.no-movies-message {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    background: #f9fafb;
    border-radius: 12px;
    border: 2px dashed #d1d5db;
}

/* Progress Styles */
.progress-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white;
}

.progress-card h2 {
    color: white !important;
    margin-top: 0;
}

.progress-container {
    background: rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.progress-bar-wrapper {
    position: relative;
    background: rgba(255, 255, 255, 0.2);
    height: 2rem;
    border-radius: 1rem;
    overflow: hidden;
    margin-bottom: 1rem;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #34d399);
    transition: width 0.5s ease;
    border-radius: 1rem;
    position: relative;
}

.progress-text {
    text-align: center;
    font-weight: 500;
    margin-bottom: 1rem;
}

.progress-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.current-movie {
    font-weight: 600;
}

.progress-stats {
    text-align: right;
    font-size: 0.9rem;
}

.progress-log {
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem;
    border-radius: 8px;
    max-height: 200px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 0.85rem;
}

/* Recent Movies */
.recent-movies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.recent-movie-card {
    display: flex;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s ease;
}

.recent-movie-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.movie-thumb {
    width: 60px;
    height: 90px;
    flex-shrink: 0;
    overflow: hidden;
}

.movie-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-thumb {
    width: 100%;
    height: 100%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #9ca3af;
}

.recent-movie-card .movie-info {
    padding: 0.75rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.recent-movie-card h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    line-height: 1.3;
}

.recent-movie-card h4 a {
    text-decoration: none;
    color: #1f2937;
}

.platform-tag, .date-tag {
    display: inline-block;
    background: #e5e7eb;
    color: #374151;
    padding: 0.15rem 0.5rem;
    border-radius: 0.75rem;
    font-size: 0.7rem;
    margin: 0.25rem 0.25rem 0.25rem 0;
}

.platform-tag {
    background: #dbeafe;
    color: #1e40af;
}

.movie-actions {
    margin-top: auto;
    display: flex;
    gap: 0.5rem;
}

.button-small {
    padding: 0.25rem 0.75rem !important;
    font-size: 0.8rem !important;
    height: auto !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .mass-generation-controls {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .movie-selection-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
    
    .selection-stats {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .progress-details {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .recent-movies-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Additional JavaScript for Mass Producer debugging
console.log('🎬 Mass Producer Template Loaded');

// Check if upcomingMovies object exists
if (typeof upcomingMovies !== 'undefined') {
    console.log('✅ upcomingMovies object found:', upcomingMovies);
} else {
    console.error('❌ upcomingMovies object not found! Admin scripts may not be loaded.');
}

// Check AJAX URL
jQuery(document).ready(function($) {
    $('#discovery-form').on('submit', function() {
        console.log('🔍 Discovery form submitted');
    });
});
</script>