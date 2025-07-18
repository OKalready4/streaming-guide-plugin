<?php
// templates/archive-streamer.php - COMPLETE FIXED VERSION
get_header(); 

// Get the current streamer info
$streamer_term = get_query_var('streamer_term');
$streamer_name = get_query_var('streamer_name');

if (!$streamer_term) {
    // Fallback: try to get from query
    $queried_object = get_queried_object();
    if ($queried_object && isset($queried_object->taxonomy) && $queried_object->taxonomy === 'streamer') {
        $streamer_term = $queried_object;
        $streamer_name = $streamer_term->name;
    }
}

// Final fallback
if (!$streamer_name) {
    $streamer_name = 'Streaming Platform';
}

// Get platform logo
$platform_logo_url = '';
if (function_exists('upcoming_movies_get_platform_logo')) {
    $platform_logo_url = upcoming_movies_get_platform_logo($streamer_name);
}
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        
        <!-- FIXED: Streamer page header -->
        <header class="page-header streamer-header">
            <div class="streamer-header-content">
                <?php if ($platform_logo_url): ?>
                    <div class="platform-logo-container">
                        <img src="<?php echo esc_url($platform_logo_url); ?>" 
                             alt="<?php echo esc_attr($streamer_name); ?> logo" 
                             class="platform-logo-large">
                    </div>
                <?php endif; ?>
                
                <div class="streamer-info">
                    <h1 class="page-title">
                        <?php echo esc_html($streamer_name); ?> Movies & Shows
                    </h1>
                    <p class="streamer-description">
                        Discover the latest movies and TV shows available on <?php echo esc_html($streamer_name); ?>. 
                        From blockbuster releases to hidden gems, find your next favorite watch.
                    </p>
                </div>
            </div>
        </header>

        <!-- FIXED: Movies grid -->
        <div class="streamer-content">
            <?php if (have_posts()): ?>
                <div class="movies-grid">
                    <?php while (have_posts()): the_post(); 
                        // Check if this is actually a movie post
                        $is_movie_post = get_post_meta(get_the_ID(), 'is_movie_post', true);
                        
                        if (!$is_movie_post) {
                            continue; // Skip non-movie posts
                        }
                        
                        // Get movie data
                        $movie_title = get_post_meta(get_the_ID(), 'movie_title', true);
                        $release_date = get_post_meta(get_the_ID(), 'release_date', true);
                        $overview = get_post_meta(get_the_ID(), 'overview', true);
                        $genres = get_post_meta(get_the_ID(), 'genres', true);
                        $maturity_rating = get_post_meta(get_the_ID(), 'maturity_rating', true);
                        $content_type = get_post_meta(get_the_ID(), 'content_type', true);
                        $youtube_id = get_post_meta(get_the_ID(), 'youtube_id', true);
                        
                        // Fallback to post title if no movie title
                        if (!$movie_title) {
                            $movie_title = get_the_title();
                        }
                        
                        // Get excerpt
                        $excerpt = get_the_excerpt();
                        if (!$excerpt && $overview) {
                            $excerpt = wp_trim_words($overview, 25);
                        }
                    ?>
                        <article class="movie-card">
                            <div class="movie-card-inner">
                                
                                <!-- Movie poster/thumbnail -->
                                <div class="movie-poster">
                                    <a href="<?php the_permalink(); ?>" class="movie-link">
                                        <?php if (has_post_thumbnail()): ?>
                                            <?php the_post_thumbnail('medium', array(
                                                'alt' => esc_attr($movie_title),
                                                'loading' => 'lazy'
                                            )); ?>
                                        <?php else: ?>
                                            <div class="movie-placeholder">
                                                <span class="movie-icon">🎬</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Play button overlay if trailer exists -->
                                        <?php if ($youtube_id): ?>
                                            <div class="play-overlay">
                                                <svg class="play-icon" width="48" height="48" viewBox="0 0 48 48">
                                                    <circle cx="24" cy="24" r="20" fill="rgba(0,0,0,0.8)"/>
                                                    <path d="M20 16L32 24L20 32V16Z" fill="#fff"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                
                                <!-- Movie info -->
                                <div class="movie-info">
                                    <h3 class="movie-title">
                                        <a href="<?php the_permalink(); ?>"><?php echo esc_html($movie_title); ?></a>
                                    </h3>
                                    
                                    <div class="movie-meta">
                                        <?php if ($release_date): ?>
                                            <span class="release-year">
                                                <?php echo esc_html(date('Y', strtotime($release_date))); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($content_type): ?>
                                            <span class="content-type">
                                                <?php echo esc_html(ucfirst($content_type)); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($maturity_rating && $maturity_rating !== 'NR'): ?>
                                            <span class="rating-badge rating-<?php echo esc_attr(strtolower($maturity_rating)); ?>">
                                                <?php echo esc_html($maturity_rating); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($genres): ?>
                                        <div class="movie-genres">
                                            <?php 
                                            $genre_list = explode(', ', $genres);
                                            foreach (array_slice($genre_list, 0, 3) as $genre): ?>
                                                <span class="genre-tag"><?php echo esc_html(trim($genre)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($excerpt): ?>
                                        <p class="movie-excerpt"><?php echo esc_html($excerpt); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="movie-actions">
                                        <a href="<?php the_permalink(); ?>" class="read-more-btn">
                                            Read More
                                        </a>
                                        
                                        <?php if ($youtube_id): ?>
                                            <a href="https://www.youtube.com/watch?v=<?php echo esc_attr($youtube_id); ?>" 
                                               target="_blank" 
                                               rel="noopener" 
                                               class="trailer-btn">
                                                Watch Trailer
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
                
                <!-- FIXED: Pagination -->
                <div class="streamer-pagination">
                    <?php
                    the_posts_pagination(array(
                        'mid_size' => 2,
                        'prev_text' => '← Previous',
                        'next_text' => 'Next →',
                    ));
                    ?>
                </div>
                
            <?php else: ?>
                
                <!-- FIXED: No posts found message -->
                <div class="no-movies-found">
                    <h2>No Content Found</h2>
                    <p>We haven't added any movies or shows for <?php echo esc_html($streamer_name); ?> yet.</p>
                    <p>Check back soon for updates!</p>
                    
                    <div class="back-to-home">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary">
                            Browse All Movies & Shows
                        </a>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<style>
/* FIXED: Complete styling for streamer archive pages */
.streamer-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 20px;
    margin-bottom: 40px;
    text-align: center;
}

.streamer-header-content {
    max-width: 800px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.platform-logo-container {
    flex-shrink: 0;
}

.platform-logo-large {
    height: 80px;
    width: auto;
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.streamer-info {
    flex: 1;
    min-width: 300px;
}

.page-title {
    font-size: 2.5em;
    margin: 0 0 15px 0;
    font-weight: 700;
}

.streamer-description {
    font-size: 1.2em;
    opacity: 0.9;
    margin: 0;
    line-height: 1.5;
}

.streamer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Movies grid */
.movies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.movie-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 1px solid #e1e5e9;
}

.movie-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.movie-card-inner {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.movie-poster {
    position: relative;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: #f8f9fa;
}

.movie-poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.movie-card:hover .movie-poster img {
    transform: scale(1.05);
}

.movie-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
    color: #999;
}

.movie-icon {
    font-size: 3em;
}

.play-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.movie-card:hover .play-overlay {
    opacity: 1;
}

.play-icon {
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.movie-info {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.movie-title {
    margin: 0 0 12px 0;
    font-size: 1.3em;
    line-height: 1.3;
}

.movie-title a {
    color: #333;
    text-decoration: none;
    transition: color 0.3s ease;
}

.movie-title a:hover {
    color: #007cba;
}

.movie-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 0.9em;
    color: #666;
    flex-wrap: wrap;
}

.release-year,
.content-type {
    background: #f1f3f4;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.rating-badge {
    background: #007cba;
    color: white;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}

.rating-pg-13 { background: #f39c12; }
.rating-r { background: #e74c3c; }
.rating-nc-17 { background: #8e44ad; }
.rating-g { background: #27ae60; }
.rating-pg { background: #3498db; }

.movie-genres {
    margin-bottom: 12px;
}

.genre-tag {
    display: inline-block;
    background: #e9ecef;
    color: #495057;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    margin: 0 6px 6px 0;
}

.movie-excerpt {
    font-size: 0.95em;
    line-height: 1.5;
    color: #666;
    margin-bottom: 15px;
    flex: 1;
}

.movie-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.read-more-btn,
.trailer-btn {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.9em;
    font-weight: 500;
    transition: all 0.3s ease;
    text-align: center;
    flex: 1;
}

.read-more-btn {
    background: #007cba;
    color: white;
}

.read-more-btn:hover {
    background: #005a87;
    color: white;
}

.trailer-btn {
    background: #ff0000;
    color: white;
}

.trailer-btn:hover {
    background: #cc0000;
    color: white;
}

/* Pagination */
.streamer-pagination {
    text-align: center;
    margin: 50px 0;
}

.pagination {
    display: inline-flex;
    gap: 10px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.pagination .page-numbers {
    display: inline-block;
    padding: 12px 16px;
    background: #f8f9fa;
    color: #333;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.pagination .page-numbers:hover,
.pagination .page-numbers.current {
    background: #007cba;
    color: white;
}

/* No movies found */
.no-movies-found {
    text-align: center;
    padding: 80px 20px;
    background: #f8f9fa;
    border-radius: 12px;
    margin: 40px 0;
}

.no-movies-found h2 {
    font-size: 2em;
    margin-bottom: 15px;
    color: #333;
}

.no-movies-found p {
    font-size: 1.1em;
    color: #666;
    margin-bottom: 15px;
}

.back-to-home {
    margin-top: 30px;
}

.btn-primary {
    display: inline-block;
    background: #007cba;
    color: white;
    padding: 15px 30px;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.3s ease;
}

.btn-primary:hover {
    background: #005a87;
    color: white;
}

/* Responsive design */
@media (max-width: 768px) {
    .streamer-header {
        padding: 40px 15px;
    }
    
    .streamer-header-content {
        flex-direction: column;
        gap: 20px;
    }
    
    .platform-logo-large {
        height: 60px;
    }
    
    .page-title {
        font-size: 2em;
    }
    
    .streamer-description {
        font-size: 1.1em;
    }
    
    .movies-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .movie-actions {
        flex-direction: column;
    }
    
    .pagination .page-numbers {
        padding: 10px 12px;
        font-size: 0.9em;
    }
}

@media (max-width: 480px) {
    .movies-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .movie-info {
        padding: 15px;
    }
    
    .movie-title {
        font-size: 1.2em;
    }
}
</style>

<?php
get_sidebar();
get_footer();
?>