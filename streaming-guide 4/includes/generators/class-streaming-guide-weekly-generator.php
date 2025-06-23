<?php
/**
 * Enhanced Weekly Generator for Streaming Guide Pro
 * Generates weekly new releases with trailers and comprehensive platform coverage
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-base-generator.php';

class Streaming_Guide_Weekly_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Generate weekly content article
     */
    public function generate_article($platform = 'all', $options = array()) {
        $defaults = array(
            'include_trailers' => true,
            'auto_publish' => false,
            'auto_featured_image' => true,
            'seo_optimize' => true,
            'min_items' => 6,
            'max_items' => 12,
            'date_range' => 7 // Days to look back/forward
        );
        
        $options = array_merge($defaults, $options);
        
        try {
            // Get weekly new releases
            $weekly_data = $this->get_weekly_releases($platform, $options);
            
            if (empty($weekly_data)) {
                return new WP_Error('no_weekly_data', 'No new releases found for this week');
            }
            
            // Enhance with trailer data
            if ($options['include_trailers']) {
                $weekly_data = $this->add_trailers_to_content($weekly_data);
            }
            
            // Generate article content
            $article_data = $this->create_weekly_article($weekly_data, $platform, $options);
            
            // Create WordPress post
            $post_id = $this->create_wordpress_post($article_data, $options);
            
            if ($post_id && !is_wp_error($post_id)) {
                // Add metadata
                $this->add_post_metadata($post_id, $weekly_data, $platform, 'weekly');
                
                // Set featured image
                if ($options['auto_featured_image']) {
                    $this->set_featured_image($post_id, $weekly_data[0]);
                }
                
                return $post_id;
            }
            
            return $post_id;
            
        } catch (Exception $e) {
            return new WP_Error('generation_failed', $e->getMessage());
        }
    }
    
    /**
 * Get weekly releases from TMDB - FILTERED BY MAIN PLATFORMS
 */
private function get_weekly_releases($platform, $options) {
    $releases = array();
    
    // Define main platform provider IDs
    $main_platforms = array(
        'netflix' => 8,
        'hulu' => 15,
        'disney' => 337,
        'hbo' => 1899,
        'amazon' => 9,
        'paramount' => 531,
        'apple' => 350
    );
    
    try {
        // Calculate date range for this week
        $start_date = date('Y-m-d', strtotime('-' . $options['date_range'] . ' days'));
        $end_date = date('Y-m-d', strtotime('+' . $options['date_range'] . ' days'));
        
        // Get new releases
        $all_releases = array();
        
        // Fetch movies
        $movie_response = $this->tmdb_api->make_request('discover/movie', array(
            'primary_release_date.gte' => $start_date,
            'primary_release_date.lte' => $end_date,
            'sort_by' => 'popularity.desc',
            'page' => 1,
            'region' => 'US'
        ));
        
        if (!is_wp_error($movie_response) && isset($movie_response['results'])) {
            foreach ($movie_response['results'] as $movie) {
                $movie['media_type'] = 'movie';
                $all_releases[] = $movie;
            }
        }
        
        // Fetch TV shows
        $tv_response = $this->tmdb_api->make_request('discover/tv', array(
            'first_air_date.gte' => $start_date,
            'first_air_date.lte' => $end_date,
            'sort_by' => 'popularity.desc',
            'page' => 1
        ));
        
        if (!is_wp_error($tv_response) && isset($tv_response['results'])) {
            foreach ($tv_response['results'] as $show) {
                $show['media_type'] = 'tv';
                $all_releases[] = $show;
            }
        }
        
        // Filter by streaming providers
        $filtered_releases = array();
        
        foreach ($all_releases as $item) {
            // Get streaming providers for this item
            $providers = $this->tmdb_api->make_request(
                "{$item['media_type']}/{$item['id']}/watch/providers"
            );
            
            if (!is_wp_error($providers) && isset($providers['results']['US']['flatrate'])) {
                $available_providers = $providers['results']['US']['flatrate'];
                $on_main_platform = false;
                
                // Check if available on any of our main platforms
                foreach ($available_providers as $provider) {
                    if (in_array($provider['provider_id'], $main_platforms)) {
                        $on_main_platform = true;
                        break;
                    }
                }
                
                if ($on_main_platform) {
                    // Enhance with details
                    $item = $this->enhance_release_item($item);
                    $item['streaming_providers'] = $providers['results']['US'];
                    $filtered_releases[] = $item;
                }
            }
            
            // Stop if we have enough items
            if (count($filtered_releases) >= $options['max_items']) {
                break;
            }
        }
        
        // Sort by release date
        usort($filtered_releases, function($a, $b) {
            $date_a = strtotime($a['release_date'] ?? $a['first_air_date'] ?? '1970-01-01');
            $date_b = strtotime($b['release_date'] ?? $b['first_air_date'] ?? '1970-01-01');
            return $date_b <=> $date_a;
        });
        
        return array_slice($filtered_releases, 0, $options['max_items']);
        
    } catch (Exception $e) {
        error_log('Weekly releases fetch error: ' . $e->getMessage());
        return array();
    }
}
    
    /**
     * Get new movie releases
     */
    private function get_new_movie_releases($start_date, $end_date) {
        $movies = array();
        
        try {
            // Get movies released this week
            $response = $this->tmdb_api->make_request('discover/movie', array(
                'primary_release_date.gte' => $start_date,
                'primary_release_date.lte' => $end_date,
                'sort_by' => 'popularity.desc',
                'vote_count.gte' => 10, // Filter out movies with too few votes
                'page' => 1
            ));
            
            // Check for API errors
            if (is_wp_error($response)) {
                error_log('TMDB API error in get_new_movie_releases: ' . $response->get_error_message());
                return array();
            }
            
            if (isset($response['results']) && is_array($response['results'])) {
                foreach ($response['results'] as $movie) {
                    $movie['media_type'] = 'movie';
                    $movies[] = $movie;
                }
            }
            
        } catch (Exception $e) {
            error_log('Movie releases fetch error: ' . $e->getMessage());
        }
        
        return $movies;
    }
    
    /**
     * Get new TV releases
     */
    private function get_new_tv_releases($start_date, $end_date) {
        $shows = array();
        
        try {
            // Get TV shows that aired this week
            $response = $this->tmdb_api->make_request('discover/tv', array(
                'first_air_date.gte' => $start_date,
                'first_air_date.lte' => $end_date,
                'sort_by' => 'popularity.desc',
                'vote_count.gte' => 5, // Lower threshold for TV shows
                'page' => 1
            ));
            
            // Check for API errors
            if (is_wp_error($response)) {
                error_log('TMDB API error in get_new_tv_releases: ' . $response->get_error_message());
                return array();
            }
            
            if (isset($response['results']) && is_array($response['results'])) {
                foreach ($response['results'] as $show) {
                    $show['media_type'] = 'tv';
                    $shows[] = $show;
                }
            }
            
        } catch (Exception $e) {
            error_log('TV releases fetch error: ' . $e->getMessage());
        }
        
        return $shows;
    }
    
    /**
     * Enhance release item with additional details
     */
    private function enhance_release_item($item) {
        try {
            $media_type = $item['media_type'];
            $id = $item['id'];
            
            // Get detailed information
            $details = $this->tmdb_api->make_request("{$media_type}/{$id}");
            
            if ($details) {
                // Merge basic data with detailed data
                $item = array_merge($item, $details);
                
                // Get streaming providers
                $providers = $this->tmdb_api->make_request("{$media_type}/{$id}/watch/providers");
                if (isset($providers['results']['US'])) {
                    $item['streaming_providers'] = $providers['results']['US'];
                }
                
                // Get credits for cast information
                $credits = $this->tmdb_api->make_request("{$media_type}/{$id}/credits");
                if (isset($credits['cast'])) {
                    $item['cast'] = array_slice($credits['cast'], 0, 5);
                }
                
                // Get keywords for better categorization
                $keywords = $this->tmdb_api->make_request("{$media_type}/{$id}/keywords");
                if (isset($keywords['keywords'])) {
                    $item['keywords'] = array_slice($keywords['keywords'], 0, 10);
                } elseif (isset($keywords['results'])) {
                    $item['keywords'] = array_slice($keywords['results'], 0, 10);
                }
            }
            
        } catch (Exception $e) {
            error_log("Error enhancing release item {$item['id']}: " . $e->getMessage());
        }
        
        return $item;
    }
    
    /**
     * Add trailers to weekly content
     */
    private function add_trailers_to_content($weekly_data) {
        foreach ($weekly_data as &$item) {
            try {
                $media_type = $item['media_type'];
                $id = $item['id'];
                
                // Get videos/trailers
                $videos = $this->tmdb_api->make_request("{$media_type}/{$id}/videos");
                
                if (isset($videos['results'])) {
                    // Find the best trailer
                    $trailer = $this->find_best_trailer($videos['results']);
                    if ($trailer) {
                        $item['trailer'] = $trailer;
                        $item['trailer_embed'] = $this->create_trailer_embed($trailer);
                    }
                }
                
            } catch (Exception $e) {
                error_log("Error adding trailer for {$item['id']}: " . $e->getMessage());
            }
        }
        
        return $weekly_data;
    }
    
    /**
 * Create weekly article content - FIXED VERSION WITH HTML OUTPUT
 */
private function create_weekly_article($weekly_data, $platform, $options) {
    $week_start = date('F j', strtotime('last Sunday'));
    $week_end = date('F j, Y', strtotime('next Saturday'));
    $platform_name = $this->get_platform_display_name($platform);
    
    // Create title
    if ($platform === 'all') {
        $title = "New This Week: {$week_start} - {$week_end} Streaming Guide";
    } else {
        $title = "New on {$platform_name}: {$week_start} - {$week_end}";
    }
    
    // Build complete HTML article with integrated trailers
    $content = $this->build_weekly_html_content($weekly_data, $platform_name, $options);
    
    return array(
        'title' => $title,
        'content' => $content,
        'excerpt' => $this->create_weekly_excerpt($weekly_data, $week_start, $week_end),
        'weekly_data' => $weekly_data,
        'platform' => $platform,
        'media_type' => 'mixed',
        'genres' => $this->extract_genres_from_content($weekly_data)
    );
}

/**
 * Build HTML content with integrated trailers
 */
private function build_weekly_html_content($weekly_data, $platform_name, $options) {
    $content = "<p>Discover what's new on " . ($platform_name === 'All Platforms' ? 'all major streaming platforms' : $platform_name) . " this week. From blockbuster movies to binge-worthy TV shows, here's everything arriving for your viewing pleasure.</p>\n\n";
    
    // Separate movies and TV shows
    $movies = array_filter($weekly_data, function($item) {
        return $item['media_type'] === 'movie';
    });
    
    $tv_shows = array_filter($weekly_data, function($item) {
        return $item['media_type'] === 'tv';
    });
    
    // Add movies section
    if (!empty($movies)) {
        $content .= "<h2>New Movies This Week</h2>\n\n";
        foreach ($movies as $movie) {
            $content .= $this->build_content_item_html($movie, $options);
        }
    }
    
    // Add TV shows section
    if (!empty($tv_shows)) {
        $content .= "<h2>New TV Shows This Week</h2>\n\n";
        foreach ($tv_shows as $show) {
            $content .= $this->build_content_item_html($show, $options);
        }
    }
    
    return $content;
}

/**
 * Build individual content item with integrated trailer
 */
private function build_content_item_html($item, $options) {
    $title = esc_html($item['title'] ?? $item['name'] ?? 'Unknown');
    $type = $item['media_type'] === 'movie' ? 'Movie' : 'TV Show';
    
    $html = '<div class="content-item">';
    $html .= "<h3>{$title}</h3>\n";
    
    // Add poster image
    if (!empty($item['poster_path'])) {
        $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
        $html .= '<img src="' . esc_url($poster_url) . '" alt="' . esc_attr($title) . ' Poster" class="content-poster">';
    }
    
    // Add metadata
    $html .= '<div class="content-meta">';
    $html .= "<p><strong>Type:</strong> {$type}</p>";
    
    if (!empty($item['vote_average'])) {
        $rating = number_format($item['vote_average'], 1);
        $html .= "<p><strong>Rating:</strong> {$rating}/10</p>";
    }
    
    if (!empty($item['release_date']) || !empty($item['first_air_date'])) {
        $date = $item['release_date'] ?? $item['first_air_date'];
        $formatted_date = date('F j, Y', strtotime($date));
        $html .= "<p><strong>Release Date:</strong> {$formatted_date}</p>";
    }
    
    if (!empty($item['genres'])) {
        $genres = array_column(array_slice($item['genres'], 0, 3), 'name');
        $html .= "<p><strong>Genres:</strong> " . implode(', ', $genres) . "</p>";
    }
    
    // Add streaming providers
    if (!empty($item['streaming_providers']['flatrate'])) {
        $providers = array_column($item['streaming_providers']['flatrate'], 'provider_name');
        $html .= "<p><strong>Streaming on:</strong> " . implode(', ', array_slice($providers, 0, 3)) . "</p>";
    }
    $html .= '</div>';
    
    // Add description
    if (!empty($item['overview'])) {
        $html .= '<p>' . esc_html($item['overview']) . '</p>';
    }
    
    // Add trailer RIGHT HERE with the content
    if (!empty($item['trailer']) && $options['include_trailers']) {
        $html .= '<div class="trailer-container">';
        $html .= '<iframe src="https://www.youtube.com/embed/' . esc_attr($item['trailer']['key']) . 
                 '?rel=0&modestbranding=1" allowfullscreen></iframe>';
        $html .= '</div>';
    }
    
    // Add cast information
    if (!empty($item['cast'])) {
        $cast_names = array_column(array_slice($item['cast'], 0, 5), 'name');
        $html .= '<p><strong>Starring:</strong> ' . implode(', ', $cast_names) . '</p>';
    }
    
    $html .= '</div><hr>';
    
    return $html;
}

/**
 * Extract unique genres from content for categorization
 */
private function extract_genres_from_content($content_data) {
    $all_genres = array();
    $genre_names = array();
    
    foreach ($content_data as $item) {
        if (!empty($item['genres']) && is_array($item['genres'])) {
            foreach ($item['genres'] as $genre) {
                $genre_name = is_array($genre) ? $genre['name'] : $genre;
                if (!in_array($genre_name, $genre_names)) {
                    $genre_names[] = $genre_name;
                    $all_genres[] = array('name' => $genre_name);
                }
            }
        }
    }
    
    // Return top 5 most common genres
    return array_slice($all_genres, 0, 5);
}
    
    /**
     * Create OpenAI prompt for weekly content
     */
    private function create_weekly_prompt($weekly_data, $platform, $week_start, $week_end) {
        $platform_text = ($platform === 'all') ? 'streaming platforms' : $this->get_platform_display_name($platform);
        
        $prompt = "Write a comprehensive weekly new releases guide for {$week_start} - {$week_end}. ";
        $prompt .= "Focus on new content arriving on {$platform_text}. ";
        $prompt .= "The article should be engaging and help readers decide what to watch.\n\n";
        
        $prompt .= "Structure the article with:\n";
        $prompt .= "1. An engaging introduction about this week's new releases\n";
        $prompt .= "2. Individual sections for each new release\n";
        $prompt .= "3. Recommendations by category (movies vs TV shows)\n";
        $prompt .= "4. A conclusion with viewing suggestions\n\n";
        
        $prompt .= "New releases to cover:\n";
        
        foreach ($weekly_data as $index => $item) {
            $title = $item['title'] ?? $item['name'] ?? 'Unknown';
            $type = $item['media_type'] === 'movie' ? 'Movie' : 'TV Show';
            $rating = $item['vote_average'] ?? 'N/A';
            $release_date = $item['release_date'] ?? $item['first_air_date'] ?? '';
            $overview = $item['overview'] ?? '';
            
            $prompt .= "{$title} ({$type})\n";
            $prompt .= "Release Date: {$release_date}\n";
            $prompt .= "Rating: {$rating}/10\n";
            $prompt .= "Overview: {$overview}\n";
            
            if (!empty($item['cast'])) {
                $cast_names = array_column(array_slice($item['cast'], 0, 3), 'name');
                $prompt .= "Starring: " . implode(', ', $cast_names) . "\n";
            }
            
            if (!empty($item['genres'])) {
                $genres = array_column($item['genres'], 'name');
                $prompt .= "Genres: " . implode(', ', array_slice($genres, 0, 3)) . "\n";
            }
            
            if (!empty($item['streaming_providers']['flatrate'])) {
                $providers = array_column($item['streaming_providers']['flatrate'], 'provider_name');
                $prompt .= "Available on: " . implode(', ', array_slice($providers, 0, 3)) . "\n";
            }
            
            $prompt .= "\n";
        }
        
        $prompt .= "\nWrite in an engaging, helpful tone. ";
        $prompt .= "Include insights about what makes each release worth watching. ";
        $prompt .= "Don't include trailer information - that will be added separately.";
        
        return $prompt;
    }
    
    /**
     * Build complete article content with trailers and sections
     */
    private function build_weekly_article_content($weekly_data, $generated_content, $options) {
        $content = $generated_content . "\n\n";
        
        // Separate movies and TV shows
        $movies = array_filter($weekly_data, function($item) {
            return $item['media_type'] === 'movie';
        });
        
        $tv_shows = array_filter($weekly_data, function($item) {
            return $item['media_type'] === 'tv';
        });
        
        // Add movies section
        if (!empty($movies)) {
            $content .= "## New Movies This Week\n\n";
            foreach ($movies as $movie) {
                $content .= $this->build_release_section($movie, $options);
            }
        }
        
        // Add TV shows section
        if (!empty($tv_shows)) {
            $content .= "## New TV Shows This Week\n\n";
            foreach ($tv_shows as $show) {
                $content .= $this->build_release_section($show, $options);
            }
        }
        
        // Add weekly summary
        $content .= $this->build_weekly_summary($weekly_data);
        
        return $content;
    }
    
    /**
     * Build individual release section
     */
    private function build_release_section($item, $options) {
        $title = $item['title'] ?? $item['name'] ?? 'Unknown';
        $type = $item['media_type'] === 'movie' ? 'Movie' : 'TV Show';
        
        $section = "### {$title}\n\n";
        
        // Add poster image
        if (!empty($item['poster_path'])) {
            $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
            $section .= "![{$title} Poster]({$poster_url})\n\n";
        }
        
        // Add metadata table
        $section .= "**Type:** {$type}  \n";
        
        if (!empty($item['vote_average'])) {
            $rating = number_format($item['vote_average'], 1);
            $section .= "**Rating:** {$rating}/10  \n";
        }
        
        if (!empty($item['release_date']) || !empty($item['first_air_date'])) {
            $date = $item['release_date'] ?? $item['first_air_date'];
            $formatted_date = date('F j, Y', strtotime($date));
            $section .= "**Release Date:** {$formatted_date}  \n";
        }
        
        if (!empty($item['runtime'])) {
            $section .= "**Runtime:** {$item['runtime']} minutes  \n";
        } elseif (!empty($item['episode_run_time']) && !empty($item['episode_run_time'][0])) {
            $section .= "**Episode Runtime:** {$item['episode_run_time'][0]} minutes  \n";
        }
        
        if (!empty($item['genres'])) {
            $genres = array_column(array_slice($item['genres'], 0, 3), 'name');
            $section .= "**Genres:** " . implode(', ', $genres) . "  \n";
        }
        
        // Add streaming providers
        if (!empty($item['streaming_providers']['flatrate'])) {
            $providers = array_column($item['streaming_providers']['flatrate'], 'provider_name');
            $section .= "**Streaming on:** " . implode(', ', array_slice($providers, 0, 3)) . "  \n";
        }
        
        $section .= "\n";
        
        // Add trailer
        if (!empty($item['trailer_embed']) && $options['include_trailers']) {
            $section .= "#### Watch the Trailer\n\n";
            $section .= $item['trailer_embed'] . "\n\n";
        }
        
        // Add overview/description
        if (!empty($item['overview'])) {
            $section .= $item['overview'] . "\n\n";
        }
        
        // Add cast information
        if (!empty($item['cast'])) {
            $cast_names = array_column(array_slice($item['cast'], 0, 5), 'name');
            $section .= "**Starring:** " . implode(', ', $cast_names) . "\n\n";
        }
        
        $section .= "---\n\n";
        
        return $section;
    }
    
    /**
     * Build weekly summary section
     */
    private function build_weekly_summary($weekly_data) {
        $movie_count = count(array_filter($weekly_data, function($item) {
            return $item['media_type'] === 'movie';
        }));
        
        $tv_count = count(array_filter($weekly_data, function($item) {
            return $item['media_type'] === 'tv';
        }));
        
        $total_count = count($weekly_data);
        
        if ($total_count === 0) {
            return "";
        }
        
        $avg_rating = array_sum(array_column($weekly_data, 'vote_average')) / $total_count;
        
        $summary = "## This Week's Summary\n\n";
        $summary .= "This week brings {$total_count} new releases to streaming platforms:\n\n";
        $summary .= "• **{$movie_count} new movies** ready for your movie night\n";
        $summary .= "• **{$tv_count} new TV shows** perfect for binge-watching\n";
        $summary .= "• **Average rating:** " . number_format($avg_rating, 1) . "/10 across all new content\n\n";
        
        // Get most common genres this week
        $all_genres = array();
        foreach ($weekly_data as $item) {
            if (!empty($item['genres'])) {
                foreach ($item['genres'] as $genre) {
                    $all_genres[] = $genre['name'];
                }
            }
        }
        
        if (!empty($all_genres)) {
            $genre_counts = array_count_values($all_genres);
            arsort($genre_counts);
            $top_genres = array_keys(array_slice($genre_counts, 0, 3, true));
            $summary .= "**Trending genres this week:** " . implode(', ', $top_genres) . "\n\n";
        }
        
        $summary .= "Whether you're looking for weekend entertainment or planning your weekly viewing schedule, these new arrivals offer something for every streaming preference. Check availability on your preferred platforms and start exploring!\n\n";
        
        return $summary;
    }
    
    /**
     * Create weekly excerpt
     */
    private function create_weekly_excerpt($weekly_data, $week_start, $week_end) {
        $movie_count = count(array_filter($weekly_data, function($item) {
            return $item['media_type'] === 'movie';
        }));
        
        $tv_count = count(array_filter($weekly_data, function($item) {
            return $item['media_type'] === 'tv';
        }));
        
        $total_count = count($weekly_data);
        
        return "Your complete guide to new streaming content for {$week_start} - {$week_end}. This week features {$total_count} new releases: {$movie_count} movies and {$tv_count} TV shows. Discover what's new, find trailers, and plan your perfect viewing week with our comprehensive streaming guide.";
    }
}