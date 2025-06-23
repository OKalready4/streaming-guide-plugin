<?php
/**
 * Updated Trending Generator with Simple SEO Integration
 * 
 * ONLY CHANGES MARKED WITH // SEO: comments
 * Everything else stays the same as your working version
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-base-generator.php';
// SEO: Load the simple SEO helper
require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-simple-seo-helper.php';

class Streaming_Guide_Trending_Generator extends Streaming_Guide_Base_Generator {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Generate article with SEO enhancements
     */
    public function generate_article($platform = 'all', $options = array()) {
        $defaults = array(
            'include_trailers' => true,
            'auto_publish' => true,
            'auto_featured_image' => true,
            'seo_optimize' => true,
            'min_items' => 5,
            'max_items' => 10
        );
        
        $options = array_merge($defaults, $options);
        
        try {
            if ($platform === 'all') {
                $platforms = array('netflix', 'hulu', 'disney', 'hbo', 'amazon', 'paramount', 'apple');
                $results = array();
                
                foreach ($platforms as $individual_platform) {
                    error_log("Generating trending article for {$individual_platform}");
                    
                    if (!empty($results)) {
                        sleep(30);
                    }
                    
                    $result = $this->generate_single_platform_article($individual_platform, $options);
                    if ($result && !is_wp_error($result)) {
                        $results[] = $result;
                    } else {
                        error_log("Failed to generate trending for {$individual_platform}");
                    }
                }
                
                return $results;
                
            } else {
                return $this->generate_single_platform_article($platform, $options);
            }
            
        } catch (Exception $e) {
            return new WP_Error('generation_failed', $e->getMessage());
        }
    }

    /**
     * Generate article for a single platform - NOW WITH SEO
     */
    private function generate_single_platform_article($platform, $options) {
        // Get trending content for this specific platform
        $trending_data = $this->get_trending_content_for_platform($platform, $options);
        
        if (empty($trending_data)) {
            error_log("No trending content found for {$platform}");
            return new WP_Error('no_trending_data', "No trending content found for {$platform}");
        }
        
        // Add trailers
        if ($options['include_trailers']) {
            $trending_data = $this->add_trailers_to_content($trending_data);
        }
        
        // SEO: Generate SEO data BEFORE creating article
        $focus_keyphrase = Streaming_Guide_Simple_SEO_Helper::generate_focus_keyphrase($platform, 'trending');
        $meta_description = Streaming_Guide_Simple_SEO_Helper::generate_meta_description($platform, 'trending', $focus_keyphrase, count($trending_data));
        
        // Generate article
        $article_data = $this->create_trending_article($trending_data, $platform, $options, $focus_keyphrase);
        
        // SEO: Add meta description to article data
        $article_data['meta_description'] = $meta_description;
        $article_data['focus_keyphrase'] = $focus_keyphrase;
        
        // Create WordPress post
        $post_id = $this->create_wordpress_post($article_data, $options);
        
        if ($post_id && !is_wp_error($post_id)) {
            $this->add_post_metadata($post_id, $trending_data, $platform, 'trending');
            
            // SEO: Add SEO metadata
            Streaming_Guide_Simple_SEO_Helper::add_seo_metadata($post_id, $focus_keyphrase, $meta_description, $platform, 'trending');
            
            if ($options['auto_featured_image'] && !empty($trending_data)) {
                // SEO: Set featured image with improved alt text
                $this->set_featured_image_with_seo($post_id, $trending_data[0], $focus_keyphrase, $platform);
            }
            
            error_log("Successfully created trending post for {$platform}: Post ID {$post_id}");
            return $post_id;
        }
        
        return $post_id;
    }

    /**
     * Get trending content for a specific platform - ENHANCED VERSION
     */
    private function get_trending_content_for_platform($platform, $options) {
        $trending_items = array();
        
        // Get platform provider ID
        $provider_id = Streaming_Guide_Platforms::get_provider_id($platform);
        if (!$provider_id) {
            error_log("Invalid platform: {$platform}");
            return array();
        }
        
        try {
            // Strategy 1: Get globally trending content and filter by platform
            $trending_items = $this->get_globally_trending_for_platform($provider_id, $options);
            
            // Strategy 2: If not enough, get popular content directly from the platform
            if (count($trending_items) < $options['min_items']) {
                error_log("Only found " . count($trending_items) . " globally trending for {$platform}, getting platform-specific popular content");
                $platform_popular = $this->get_platform_popular_content($provider_id, $options, $trending_items);
                $trending_items = array_merge($trending_items, $platform_popular);
            }
            
            // Strategy 3: If still not enough, get highly rated content from the platform
            if (count($trending_items) < $options['min_items']) {
                error_log("Still only " . count($trending_items) . " items, getting highly rated content for {$platform}");
                $highly_rated = $this->get_platform_highly_rated($provider_id, $options, $trending_items);
                $trending_items = array_merge($trending_items, $highly_rated);
            }
            
            // Remove duplicates and limit to max items
            $trending_items = $this->deduplicate_items($trending_items);
            $trending_items = array_slice($trending_items, 0, $options['max_items']);
            
            error_log("Final count: " . count($trending_items) . " trending items for {$platform}");
            return $trending_items;
            
        } catch (Exception $e) {
            error_log('Trending content fetch error for ' . $platform . ': ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get globally trending content available on platform
     */
    private function get_globally_trending_for_platform($provider_id, $options) {
        $items = array();
        
        // Get trending movies and TV shows (multiple pages)
        for ($page = 1; $page <= 3; $page++) {
            $trending_movies = $this->tmdb_api->make_request('trending/movie/week', array('page' => $page));
            $trending_tv = $this->tmdb_api->make_request('trending/tv/week', array('page' => $page));
            
            $all_trending = array();
            
            if (!is_wp_error($trending_movies) && isset($trending_movies['results'])) {
                foreach ($trending_movies['results'] as $movie) {
                    $movie['media_type'] = 'movie';
                    $all_trending[] = $movie;
                }
            }
            
            if (!is_wp_error($trending_tv) && isset($trending_tv['results'])) {
                foreach ($trending_tv['results'] as $show) {
                    $show['media_type'] = 'tv';
                    $all_trending[] = $show;
                }
            }
            
            // Check each item for platform availability
            foreach ($all_trending as $item) {
                if (count($items) >= $options['max_items']) {
                    break 2; // Break both loops
                }
                
                $providers = $this->tmdb_api->make_request(
                    "{$item['media_type']}/{$item['id']}/watch/providers"
                );
                
                if (!is_wp_error($providers) && isset($providers['results']['US']['flatrate'])) {
                    foreach ($providers['results']['US']['flatrate'] as $provider) {
                        if ($provider['provider_id'] == $provider_id) {
                            // Enhance item
                            $enhanced_item = $this->enhance_item($item);
                            if ($enhanced_item !== null) {
                                $items[] = $enhanced_item;
                            }
                            break;
                        }
                    }
                }
                
                usleep(100000); // 0.1 second delay
            }
        }
        
        return $items;
    }
    
    /**
     * Get popular content directly from the platform
     */
    private function get_platform_popular_content($provider_id, $options, $existing_items) {
        $items = array();
        $existing_ids = array_column($existing_items, 'id');
        
        // Calculate date 5 years ago
        $five_years_ago = date('Y-m-d', strtotime('-5 years'));
        
        // Get movies popular on this platform
        $movies = $this->tmdb_api->make_request('discover/movie', array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'sort_by' => 'popularity.desc',
            'vote_count.gte' => 10,
            'primary_release_date.gte' => $five_years_ago, // Add date filter
            'page' => 1
        ));
        
        if (!is_wp_error($movies) && isset($movies['results'])) {
            foreach ($movies['results'] as $movie) {
                if (!in_array($movie['id'], $existing_ids) && count($items) < 5) {
                    $movie['media_type'] = 'movie';
                    $enhanced_movie = $this->enhance_item($movie);
                    if ($enhanced_movie !== null) {
                        $items[] = $enhanced_movie;
                    }
                }
            }
        }
        
        // Get TV shows popular on this platform
        $shows = $this->tmdb_api->make_request('discover/tv', array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'sort_by' => 'popularity.desc',
            'vote_count.gte' => 5,
            'first_air_date.gte' => $five_years_ago, // Add date filter
            'page' => 1
        ));
        
        if (!is_wp_error($shows) && isset($shows['results'])) {
            foreach ($shows['results'] as $show) {
                if (!in_array($show['id'], $existing_ids) && count($items) < 5) {
                    $show['media_type'] = 'tv';
                    $enhanced_show = $this->enhance_item($show);
                    if ($enhanced_show !== null) {
                        $items[] = $enhanced_show;
                    }
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Get highly rated content from platform
     */
    private function get_platform_highly_rated($provider_id, $options, $existing_items) {
        $items = array();
        $existing_ids = array_column($existing_items, 'id');
        
        // Calculate date 5 years ago
        $five_years_ago = date('Y-m-d', strtotime('-5 years'));
        
        // Get highly rated movies
        $movies = $this->tmdb_api->make_request('discover/movie', array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'sort_by' => 'vote_average.desc',
            'vote_count.gte' => 50,
            'vote_average.gte' => 7.0,
            'primary_release_date.gte' => $five_years_ago, // Add date filter
            'page' => 1
        ));
        
        if (!is_wp_error($movies) && isset($movies['results'])) {
            foreach ($movies['results'] as $movie) {
                if (!in_array($movie['id'], $existing_ids) && count($items) < 3) {
                    $movie['media_type'] = 'movie';
                    $enhanced_movie = $this->enhance_item($movie);
                    if ($enhanced_movie !== null) {
                        $items[] = $enhanced_movie;
                    }
                }
            }
        }
        
        // Get highly rated TV shows
        $shows = $this->tmdb_api->make_request('discover/tv', array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'sort_by' => 'vote_average.desc',
            'vote_count.gte' => 30,
            'vote_average.gte' => 7.0,
            'first_air_date.gte' => $five_years_ago, // Add date filter
            'page' => 1
        ));
        
        if (!is_wp_error($shows) && isset($shows['results'])) {
            foreach ($shows['results'] as $show) {
                if (!in_array($show['id'], $existing_ids) && count($items) < 3) {
                    $show['media_type'] = 'tv';
                    $enhanced_show = $this->enhance_item($show);
                    if ($enhanced_show !== null) {
                        $items[] = $enhanced_show;
                    }
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Enhance item with additional details - NOW WITH 5-YEAR FILTER
     */
    private function enhance_item($item) {
        // CHECK IF CONTENT IS OLDER THAN 5 YEARS
        $date_field = isset($item['release_date']) ? 'release_date' : 'first_air_date';
        if (!empty($item[$date_field])) {
            $content_date = strtotime($item[$date_field]);
            $five_years_ago = strtotime('-5 years');
            if ($content_date < $five_years_ago) {
                return null; // Skip items older than 5 years
            }
        }
        
        $media_type = $item['media_type'];
        $id = $item['id'];
        
        // Get detailed information
        $details = $this->tmdb_api->make_request("{$media_type}/{$id}");
        if (!is_wp_error($details)) {
            $item = array_merge($item, $details);
        }
        
        // Get streaming providers
        $providers = $this->tmdb_api->make_request("{$media_type}/{$id}/watch/providers");
        if (!is_wp_error($providers) && isset($providers['results']['US'])) {
            $item['streaming_providers'] = $providers['results']['US'];
        }
        
        // Get credits
        $credits = $this->tmdb_api->make_request("{$media_type}/{$id}/credits");
        if (!is_wp_error($credits) && isset($credits['cast'])) {
            $item['cast'] = array_slice($credits['cast'], 0, 5);
        }
        
        return $item;
    }
    
    /**
     * Remove duplicate items
     */
    private function deduplicate_items($items) {
        $seen = array();
        $unique = array();
        
        foreach ($items as $item) {
            $key = $item['media_type'] . '_' . $item['id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }
        
        return $unique;
    }
    
    /**
     * Add trailers to content
     */
    private function add_trailers_to_content($trending_data) {
        foreach ($trending_data as &$item) {
            $videos = $this->tmdb_api->make_request("{$item['media_type']}/{$item['id']}/videos");
            
            if (!is_wp_error($videos) && isset($videos['results'])) {
                $trailer = $this->find_best_trailer($videos['results']);
                if ($trailer) {
                    $item['trailer'] = $trailer;
                }
            }
            
            // Add small delay to avoid rate limits
            usleep(100000); // 0.1 second
        }
        
        return $trending_data;
    }

    /**
     * Create trending article - UPDATED WITH SEO
     */
    private function create_trending_article($trending_data, $platform, $options, $focus_keyphrase) {
        $current_date = current_time('F Y');
        $platform_name = $this->get_platform_display_name($platform);
        
        // SEO: Create title with keyphrase
        $original_title = "Top {$platform_name} Trending: What Everyone's Watching ({$current_date})";
        $seo_title = Streaming_Guide_Simple_SEO_Helper::optimize_title($original_title, $focus_keyphrase);
        
        // Build content with integrated trailers
        $content = $this->build_integrated_trending_content($trending_data, $platform_name, $focus_keyphrase);
        
        // SEO: Add outbound link to platform
        $content = Streaming_Guide_Simple_SEO_Helper::add_platform_outbound_link($content, $platform);
        
        return array(
            'title' => $seo_title, // SEO: Use optimized title
            'content' => $content,
            'excerpt' => $this->create_trending_excerpt($trending_data, $platform_name),
            'trending_data' => $trending_data,
            'platform' => $platform
        );
    }
    
    /**
     * Build content with SEO-optimized introduction
     */
    private function build_integrated_trending_content($trending_data, $platform_name, $focus_keyphrase) {
        // SEO: Start with introduction that includes focus keyphrase
        $content = "<p>Looking for <strong>{$focus_keyphrase}</strong>? Discover what's capturing audiences on {$platform_name} right now. These are the movies and shows everyone's talking about, featuring the hottest trending content available for streaming today.</p>\n\n";
        
        $rank = 1;
        foreach ($trending_data as $item) {
            $title = $item['title'] ?? $item['name'] ?? 'Unknown';
            $type = $item['media_type'] === 'movie' ? 'Movie' : 'TV Show';
            $rating = !empty($item['vote_average']) ? number_format($item['vote_average'], 1) : 'N/A';
            $year = '';
            
            if (!empty($item['release_date'])) {
                $year = date('Y', strtotime($item['release_date']));
            } elseif (!empty($item['first_air_date'])) {
                $year = date('Y', strtotime($item['first_air_date']));
            }
            
            // Section header
            $content .= "<h2>#{$rank}. {$title} ({$year})</h2>\n\n";
            
            // Create two-column layout
            $content .= '<div class="trending-item-layout">';
            
            // Left column - Poster and details
            $content .= '<div class="trending-details">';
            
            if (!empty($item['poster_path'])) {
                $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
                // SEO: Improved alt text with keyphrase
                $alt_text = Streaming_Guide_Simple_SEO_Helper::generate_image_alt_text("{$title} poster", $focus_keyphrase, $platform_name);
                $content .= "<img src=\"{$poster_url}\" alt=\"{$alt_text}\" class=\"trending-poster\">\n\n";
            }
            
            $content .= "<p class=\"trending-meta\">";
            $content .= "<strong>Type:</strong> {$type}<br>\n";
            $content .= "<strong>Rating:</strong> {$rating}/10<br>\n";
            
            if (!empty($item['genres'])) {
                $genres = array_column(array_slice($item['genres'], 0, 3), 'name');
                $content .= "<strong>Genres:</strong> " . implode(', ', $genres) . "<br>\n";
            }
            
            $content .= "</p>\n";
            $content .= '</div>'; // End details column
            
            // Right column - Trailer first, then description
            $content .= '<div class="trending-content">';
            
            // Add trailer if available
            if (!empty($item['trailer'])) {
                $content .= '<div class="trailer-container">';
                $content .= '<iframe src="https://www.youtube.com/embed/' . $item['trailer']['key'] . 
                           '?rel=0&modestbranding=1" allowfullscreen></iframe>';
                $content .= '</div>';
            }
            
            if (!empty($item['overview'])) {
                $content .= "<p>{$item['overview']}</p>\n\n";
            }
            
            $content .= '</div>'; // End content column
            $content .= '</div>'; // End layout
            
            $content .= "\n<hr class=\"trending-divider\">\n\n";
            
            $rank++;
        }
        
        return $content;
    }
    
    /**
     * SEO: Set featured image with improved alt text
     */
    private function set_featured_image_with_seo($post_id, $content_item, $focus_keyphrase, $platform) {
        if (!$post_id || !is_array($content_item)) {
            return false;
        }
        
        $image_path = $content_item['poster_path'] ?? $content_item['backdrop_path'] ?? null;
        
        if (!$image_path) {
            return false;
        }
        
        try {
            $image_url = "https://image.tmdb.org/t/p/w780" . $image_path;
            $title = $content_item['title'] ?? $content_item['name'] ?? 'Featured Content';
            
            // SEO: Generate improved alt text
            $alt_text = Streaming_Guide_Simple_SEO_Helper::generate_image_alt_text($title, $focus_keyphrase, $platform);
            
            $upload_result = $this->download_and_attach_image($image_url, $post_id, $alt_text);
            
            if ($upload_result && !is_wp_error($upload_result)) {
                set_post_thumbnail($post_id, $upload_result);
                
                // SEO: Update the attachment's alt text
                update_post_meta($upload_result, '_wp_attachment_image_alt', $alt_text);
                
                return $upload_result;
            }
            
        } catch (Exception $e) {
            error_log("Failed to set featured image for post {$post_id}: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Create excerpt
     */
    private function create_trending_excerpt($trending_data, $platform_name) {
        $count = count($trending_data);
        $movie_count = count(array_filter($trending_data, function($item) {
            return $item['media_type'] === 'movie';
        }));
        $tv_count = $count - $movie_count;
        
        return "Discover the top {$count} trending titles on {$platform_name} right now, including {$movie_count} must-watch movies and {$tv_count} binge-worthy TV shows. See what everyone's streaming and find your next favorite.";
    }
}