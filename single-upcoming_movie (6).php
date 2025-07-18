<?php
// templates/single-upcoming_movie.php - FIXED VERSION (NO AUTOPLAY)
get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php while (have_posts()): the_post(); 
            // FIXED: Get movie data properly for regular posts
            $is_movie_post = get_post_meta(get_the_ID(), 'is_movie_post', true);
            
            if (!$is_movie_post) {
                // If not a movie post, use default template
                get_template_part('template-parts/content', 'single');
                continue;
            }
            
            // Get movie metadata
            $movie_title = get_post_meta(get_the_ID(), 'movie_title', true);
            $release_date = get_post_meta(get_the_ID(), 'release_date', true);
            $runtime = get_post_meta(get_the_ID(), 'runtime', true);
            $genres = get_post_meta(get_the_ID(), 'genres', true);
            $maturity_rating = get_post_meta(get_the_ID(), 'maturity_rating', true);
            $youtube_id = get_post_meta(get_the_ID(), 'youtube_id', true);
            $overview = get_post_meta(get_the_ID(), 'overview', true);
            $content_type = get_post_meta(get_the_ID(), 'content_type', true);
            
            // FIXED: Get streaming platform (ACF aware)
            $streaming_platform = '';
            if (function_exists('get_field')) {
                $streaming_platform = get_field('streaming_platform');
            }
            if (empty($streaming_platform)) {
                $streaming_platform = get_post_meta(get_the_ID(), 'streaming_platform', true);
            }
            
            // Get platform logo
            $platform_logo_url = '';
            if (!empty($streaming_platform) && function_exists('upcoming_movies_get_platform_logo')) {
                $platform_logo_url = upcoming_movies_get_platform_logo($streaming_platform);
            }
            
            // Extract article title from content
            $article_title = get_the_title();
        ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('upcoming-movie-article movie-post-single'); ?>>
                
                <!-- FIXED: Article title with better styling -->
                <header class="entry-header">
                    <h1 class="entry-title"><?php echo esc_html($article_title); ?></h1>
                    
                    <?php if ($release_date || $streaming_platform): ?>
                        <div class="movie-release-info">
                            <?php if ($release_date): ?>
                                <span class="release-date">
                                    <?php echo esc_html(date_i18n('F j, Y', strtotime($release_date))); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($streaming_platform): ?>
                                <span class="streaming-info">
                                    <?php if ($platform_logo_url): ?>
                                        <img src="<?php echo esc_url($platform_logo_url); ?>" 
                                             alt="<?php echo esc_attr($streaming_platform); ?>" 
                                             class="platform-logo-inline">
                                    <?php endif; ?>
                                    Available on <?php echo esc_html($streaming_platform); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </header>

                <!-- FIXED: Featured image with better handling -->
                <?php if (has_post_thumbnail()): ?>
                    <div class="movie-featured-image">
                        <?php the_post_thumbnail('large', array('loading' => 'eager')); ?>
                    </div>
                <?php endif; ?>

                <!-- FIXED: Content with proper styling and NO autoplay embeds -->
                <div class="entry-content">
                    <?php 
                    // Get and display the content, removing any YouTube embeds to prevent autoplay
                    $content = get_the_content();
                    
                    // CRITICAL: Remove all YouTube embeds from content to prevent autoplay
                    $content = preg_replace('/<!-- wp:embed.*?youtube.*?<!-- \/wp:embed -->/s', '', $content);
                    $content = preg_replace('/<figure[^>]*wp-block-embed[^>]*youtube[^>]*>.*?<\/figure>/s', '', $content);
                    $content = preg_replace('/<iframe[^>]*youtube[^>]*>.*?<\/iframe>/s', '', $content);
                    
                    $content = apply_filters('the_content', $content);
                    echo $content;
                    ?>
                </div>
                
                <!-- FIXED: Safe YouTube trailer section (NO AUTOPLAY) -->
                <?php if ($youtube_id): ?>
                    <div class="movie-trailer-section">
                        <h3 class="trailer-heading">Watch the Trailer</h3>
                        <div class="trailer-video-container">
                            <!-- FIXED: Safe clickable trailer thumbnail -->
                            <div class="trailer-thumbnail" 
                                 data-trailer="<?php echo esc_attr($youtube_id); ?>" 
                                 role="button" 
                                 tabindex="0"
                                 aria-label="Play trailer for <?php echo esc_attr($movie_title); ?>">
                                
                                <!-- Thumbnail image -->
                                <img src="https://img.youtube.com/vi/<?php echo esc_attr($youtube_id); ?>/maxresdefault.jpg" 
                                     alt="<?php echo esc_attr($movie_title); ?> trailer thumbnail" 
                                     class="trailer-thumb-img"
                                     loading="lazy">
                                
                                <!-- Play button overlay -->
                                <div class="play-button-overlay">
                                    <svg class="play-icon" width="68" height="48" viewBox="0 0 68 48">
                                        <path d="M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z" fill="#f00"></path>
                                        <path d="M 45,24 27,14 27,34" fill="#fff"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- CRITICAL: Embed container WITHOUT autoplay - loads ONLY when clicked -->
                            <div class="trailer-embed" style="display: none;">
                                <iframe width="560" 
                                        height="315" 
                                        data-src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_id); ?>?rel=0&modestbranding=1" 
                                        title="<?php echo esc_attr($movie_title); ?> Trailer" 
                                        frameborder="0" 
                                        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen>
                                </iframe>
                            </div>
                        </div>
                        
                        <!-- Safe external link -->
                        <div class="trailer-links">
                            <a href="https://www.youtube.com/watch?v=<?php echo esc_attr($youtube_id); ?>" 
                               target="_blank" 
                               rel="noopener" 
                               class="watch-on-youtube">
                                <svg width="20" height="14" viewBox="0 0 20 14" fill="currentColor">
                                    <path d="M19.582 2.186c-.229-1.025-.905-1.832-1.746-2.045C16.246 0 10 0 10 0S3.754 0 2.164.141C1.323.354.647 1.161.418 2.186 0 3.956 0 7 0 7s0 3.044.418 4.814c.229 1.025.905 1.832 1.746 2.045C3.754 14 10 14 10 14s6.246 0 7.836-.141c.841-.213 1.517-1.02 1.746-2.045C20 10.044 20 7 20 7s0-3.044-.418-4.814zM8 10V4l5.2 3L8 10z"/>
                                </svg>
                                Watch on YouTube
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FIXED: Movie metadata section -->
                <div class="movie-metadata-section">
                    <h3>Movie Details</h3>
                    <div class="movie-details-grid">
                        <?php if ($movie_title): ?>
                            <div class="detail-item">
                                <span class="detail-label">Title:</span>
                                <span class="detail-value"><?php echo esc_html($movie_title); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($release_date): ?>
                            <div class="detail-item">
                                <span class="detail-label">Release Date:</span>
                                <span class="detail-value"><?php echo esc_html(date_i18n('F j, Y', strtotime($release_date))); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($runtime && $runtime > 0): ?>
                            <div class="detail-item">
                                <span class="detail-label">Runtime:</span>
                                <span class="detail-value">
                                    <?php 
                                    $hours = floor($runtime / 60);
                                    $minutes = $runtime % 60;
                                    if ($hours > 0) {
                                        echo $hours . 'h ' . $minutes . 'm';
                                    } else {
                                        echo $minutes . ' minutes';
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($genres): ?>
                            <div class="detail-item">
                                <span class="detail-label">Genres:</span>
                                <span class="detail-value"><?php echo esc_html($genres); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($maturity_rating && $maturity_rating !== 'NR'): ?>
                            <div class="detail-item">
                                <span class="detail-label">Rating:</span>
                                <span class="detail-value rating-badge rating-<?php echo esc_attr(strtolower($maturity_rating)); ?>">
                                    <?php echo esc_html($maturity_rating); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($streaming_platform): ?>
                            <div class="detail-item">
                                <span class="detail-label">Available On:</span>
                                <span class="detail-value">
                                    <?php if ($platform_logo_url): ?>
                                        <img src="<?php echo esc_url($platform_logo_url); ?>" 
                                             alt="<?php echo esc_attr($streaming_platform); ?>" 
                                             class="platform-logo-small">
                                    <?php endif; ?>
                                    <?php echo esc_html($streaming_platform); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($content_type): ?>
                            <div class="detail-item">
                                <span class="detail-label">Type:</span>
                                <span class="detail-value"><?php echo esc_html(ucfirst($content_type)); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- FIXED: Footer meta -->
                <footer class="entry-footer">
                    <?php
                    // Show categories and tags
                    $categories_list = get_the_category_list(', ');
                    $tags_list = get_the_tag_list('', ', ');
                    
                    if ($categories_list || $tags_list): ?>
                        <div class="entry-meta">
                            <?php if ($categories_list): ?>
                                <span class="cat-links">
                                    <strong>Categories:</strong> <?php echo $categories_list; ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($tags_list): ?>
                                <span class="tags-links">
                                    <strong>Tags:</strong> <?php echo $tags_list; ?>
                                </span>
                            <?php endif; ?>
                            
                            <span class="posted-on">
                                <strong>Published:</strong> <?php echo get_the_date(); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </footer>

            </article>

        <?php endwhile; // End of the loop. ?>
    </main><!-- #main -->
</div><!-- #primary -->

<style>
/* FIXED: Complete styling for movie posts with no autoplay issues */
.upcoming-movie-article {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.entry-header {
    text-align: center;
    margin-bottom: 30px;
}

.entry-title {
    font-size: 2.5em;
    margin-bottom: 15px;
    line-height: 1.2;
}

.movie-release-info {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 15px;
    font-size: 1.1em;
    color: #666;
}

.platform-logo-inline {
    height: 20px;
    width: auto;
    margin-right: 8px;
    vertical-align: middle;
}

.movie-featured-image {
    margin: 30px 0;
    text-align: center;
}

.movie-featured-image img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.entry-content {
    font-size: 1.1em;
    line-height: 1.7;
    margin-bottom: 40px;
}

.entry-content h2,
.entry-content h3 {
    margin-top: 40px;
    margin-bottom: 20px;
}

.entry-content h2 {
    font-size: 1.8em;
    color: #333;
}

.entry-content h3 {
    font-size: 1.4em;
    color: #444;
}

/* FIXED: Safe trailer section styling (no autoplay) */
.movie-trailer-section {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 12px;
    margin: 40px 0;
    text-align: center;
}

.trailer-heading {
    font-size: 1.6em;
    margin-bottom: 20px;
    color: #333;
}

.trailer-video-container {
    position: relative;
    max-width: 560px;
    margin: 0 auto 20px auto;
}

.trailer-thumbnail {
    position: relative;
    cursor: pointer;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.trailer-thumbnail:hover {
    transform: scale(1.02);
}

.trailer-thumbnail:focus {
    outline: 2px solid #007cba;
    outline-offset: 2px;
}

.trailer-thumb-img {
    width: 100%;
    height: auto;
    display: block;
}

.play-button-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0,0,0,0.8);
    border-radius: 50%;
    padding: 15px;
    transition: all 0.3s ease;
}

.trailer-thumbnail:hover .play-button-overlay {
    background: rgba(0,0,0,0.9);
    transform: translate(-50%, -50%) scale(1.1);
}

.play-icon {
    width: 48px;
    height: 48px;
}

/* CRITICAL: Safe embed styling - only shows when clicked */
.trailer-embed {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 56.25%; /* 16:9 aspect ratio */
    margin-top: 20px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.trailer-embed iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.trailer-links {
    margin-top: 15px;
}

.watch-on-youtube {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ff0000;
    color: white;
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: background 0.3s ease;
}

.watch-on-youtube:hover {
    background: #cc0000;
    color: white;
}

.watch-on-youtube svg {
    width: 20px;
    height: 14px;
}

/* Movie metadata styling */
.movie-metadata-section {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 25px;
    margin: 30px 0;
}

.movie-metadata-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 1.4em;
    color: #333;
}

.movie-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
}

.detail-label {
    font-weight: 600;
    margin-right: 10px;
    min-width: 100px;
    color: #555;
}

.detail-value {
    flex: 1;
    display: flex;
    align-items: center;
}

.platform-logo-small {
    height: 16px;
    width: auto;
    margin-right: 8px;
}

.rating-badge {
    background: #007cba;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.9em;
    font-weight: bold;
}

.rating-pg-13 { background: #f39c12; }
.rating-r { background: #e74c3c; }
.rating-nc-17 { background: #8e44ad; }
.rating-g { background: #27ae60; }
.rating-pg { background: #3498db; }

/* Entry footer */
.entry-footer {
    border-top: 1px solid #e1e5e9;
    padding-top: 20px;
    margin-top: 40px;
}

.entry-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 0.95em;
    color: #666;
}

.entry-meta span {
    display: flex;
    align-items: center;
}

.entry-meta a {
    color: #007cba;
    text-decoration: none;
}

.entry-meta a:hover {
    text-decoration: underline;
}

/* Responsive design */
@media (max-width: 768px) {
    .upcoming-movie-article {
        padding: 15px;
    }
    
    .entry-title {
        font-size: 2em;
    }
    
    .movie-release-info {
        flex-direction: column;
        gap: 10px;
    }
    
    .movie-details-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .detail-label {
        margin-bottom: 5px;
        min-width: auto;
    }
    
    .entry-meta {
        flex-direction: column;
        gap: 10px;
    }
    
    .trailer-video-container {
        max-width: 100%;
    }
}
</style>

<script>
// CRITICAL: Safe trailer JavaScript - NO AUTOPLAY, loads only when clicked
document.addEventListener('DOMContentLoaded', function() {
    const trailerThumbs = document.querySelectorAll('.trailer-thumbnail');
    
    trailerThumbs.forEach(function(thumb) {
        thumb.addEventListener('click', function() {
            const trailerId = this.dataset.trailer;
            const embedContainer = this.parentNode.querySelector('.trailer-embed');
            
            if (trailerId && embedContainer) {
                const iframe = embedContainer.querySelector('iframe');
                
                // CRITICAL: Load iframe source ONLY when clicked - NO AUTOPLAY
                if (!iframe.src) {
                    iframe.src = iframe.dataset.src;
                }
                
                // Show embed, hide thumbnail
                embedContainer.style.display = 'block';
                this.style.display = 'none';
                
                // Smooth scroll to trailer
                embedContainer.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                
                console.log('Trailer loaded safely without autoplay');
            }
        });
        
        // Keyboard accessibility
        thumb.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
    
    // Prevent any accidental autoplay from other sources
    setTimeout(function() {
        const allIframes = document.querySelectorAll('iframe[src*="youtube"]');
        allIframes.forEach(function(iframe) {
            if (iframe.src.includes('autoplay=1')) {
                iframe.src = iframe.src.replace('autoplay=1', 'autoplay=0');
                console.log('Prevented autoplay on iframe');
            }
        });
    }, 1000);
});
</script>

<?php
get_sidebar();
get_footer();
?>