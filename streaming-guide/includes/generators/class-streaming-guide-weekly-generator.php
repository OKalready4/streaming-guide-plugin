<?php
/**
 * Weekly Content Generator
 * Fixed version with better content selection and featured image handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Weekly_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Generate weekly content for a platform
     */
    public function generate($platform, $param1 = null, $param2 = null, $param3 = null) {
        try {
            $this->log_info("Starting weekly content generation for platform: {$platform}");
            
            // Validate platform
            if (!$this->validate_platform($platform)) {
                $this->log_generation_failure('weekly', $platform, "Invalid platform: {$platform}");
                return false;
            }
            
            // Get platform configuration
            $platform_config = $this->get_platform_config($platform);
            if (!$platform_config) {
                $this->log_generation_failure('weekly', $platform, "Invalid platform configuration for: {$platform}");
                return false;
            }
            
            // Handle all platforms case
            if ($platform === 'all') {
                return $this->generate_for_all_platforms();
            }
            
            // Get provider ID from platform config
            $provider_id = $platform_config['provider_id'] ?? null;
            if (!$provider_id) {
                $this->log_generation_failure('weekly', $platform, "No provider ID found for platform: {$platform}");
                return false;
            }
            
            // Get weekly content
            $content = $this->get_platform_new_releases($provider_id, $platform);
            if (empty($content)) {
                $this->log_generation_failure('weekly', $platform, "No suitable content found for {$platform}");
                return false;
            }
            
            // Create article
            $title = $this->generate_weekly_title($content[0], $platform, $content[0]['media_type']);
            $content_blocks = $this->create_content_blocks($content);
            $tags = $this->create_weekly_tags($content[0], $platform);
            
            // Create post
            $post_id = $this->create_post($title, $content_blocks, $platform, $tags, 'weekly');
            if (!$post_id) {
                $this->log_generation_failure('weekly', $platform, "Failed to create post for {$platform}");
                return false;
            }
            
            // Set featured image
            if (!empty($content[0]['poster_path'])) {
                $this->set_featured_image($post_id, $content[0]['poster_path']);
            }
            
            // Add weekly-specific metadata
            update_post_meta($post_id, '_streaming_guide_platform', $platform);
            update_post_meta($post_id, '_streaming_guide_content_type', 'weekly');
            update_post_meta($post_id, '_streaming_guide_generation_date', current_time('mysql'));
            
            $this->log_info("Successfully generated weekly content for {$platform}");
            return true;
            
        } catch (Exception $e) {
            $this->log_generation_failure('weekly', $platform, "Error generating weekly content: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate content for all platforms
     */
    private function generate_for_all_platforms() {
        $platforms = Streaming_Guide_Platforms::get_enabled_platforms();
        $success = true;
        
        foreach ($platforms as $platform => $config) {
            $result = $this->generate(array('platform' => $platform));
            if (!$result) {
                $this->log_generation_failure('weekly', $platform, "Failed to generate content for {$platform}");
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get new releases for platform
     */
    private function get_platform_new_releases($provider_id, $platform) {
        try {
            // Get current date range (last 14 days)
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime('-14 days'));
            
            // Get new movies
            $movies_response = $this->tmdb->discover_movies([
                'with_watch_providers' => $provider_id,
                'watch_region' => 'US',
                'primary_release_date.gte' => $start_date,
                'primary_release_date.lte' => $end_date,
                'sort_by' => 'popularity.desc',
                'vote_count.gte' => 10
            ]);
            
            if (is_wp_error($movies_response)) {
                $this->log_error('TMDB API error getting new movies', [
                    'provider_id' => $provider_id,
                    'platform' => $platform,
                    'error' => $movies_response->get_error_message()
                ]);
                $movies_response = ['results' => []];
            }
            
            // Get new TV shows
            $tv_response = $this->tmdb->discover_tv([
                'with_watch_providers' => $provider_id,
                'watch_region' => 'US',
                'first_air_date.gte' => $start_date,
                'first_air_date.lte' => $end_date,
                'sort_by' => 'popularity.desc',
                'vote_count.gte' => 5
            ]);
            
            if (is_wp_error($tv_response)) {
                $this->log_error('TMDB API error getting new TV shows', [
                    'provider_id' => $provider_id,
                    'platform' => $platform,
                    'error' => $tv_response->get_error_message()
                ]);
                $tv_response = ['results' => []];
            }
            
            $all_content = array();
            
            if (!empty($movies_response['results'])) {
                foreach ($movies_response['results'] as $movie) {
                    $movie['media_type'] = 'movie';
                    $all_content[] = $movie;
                }
            }
            
            if (!empty($tv_response['results'])) {
                foreach ($tv_response['results'] as $show) {
                    $show['media_type'] = 'tv';
                    $all_content[] = $show;
                }
            }
            
            // Filter and sort by quality and popularity
            $filtered_content = $this->filter_quality_content($all_content);
            
            return $filtered_content;
            
        } catch (Exception $e) {
            $this->log_error('Error getting new releases', [
                'provider_id' => $provider_id,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);
            return array();
        }
    }
    
    /**
     * Filter content by quality criteria
     */
    private function filter_quality_content($content_array) {
        return array_filter($content_array, function($item) {
            $vote_average = $item['vote_average'] ?? 0;
            $vote_count = $item['vote_count'] ?? 0;
            $popularity = $item['popularity'] ?? 0;
            
            // Quality thresholds
            return $vote_average >= 6.0 && 
                   $vote_count >= 10 && 
                   $popularity >= 20 &&
                   !empty($item['overview']) &&
                   !empty($item['backdrop_path']);
        });
    }
    
    /**
     * Select featured content
     */
    private function select_featured_content($content_array) {
        if (empty($content_array)) {
            return null;
        }
        
        // Sort by weighted score
        usort($content_array, function($a, $b) {
            $score_a = $this->calculate_content_score($a);
            $score_b = $this->calculate_content_score($b);
            return $score_b <=> $score_a;
        });
        
        // Return top item
        return $content_array[0];
    }
    
    /**
     * Calculate content score for selection
     */
    private function calculate_content_score($item) {
        $vote_average = $item['vote_average'] ?? 0;
        $vote_count = $item['vote_count'] ?? 0;
        $popularity = $item['popularity'] ?? 0;
        
        // Weighted score calculation
        $rating_weight = $vote_average * 2;
        $votes_weight = min($vote_count / 100, 10);
        $popularity_weight = min($popularity / 100, 10);
        
        return $rating_weight + $votes_weight + $popularity_weight;
    }
    
    /**
     * Create weekly article
     */
    private function create_weekly_article($featured_content, $platform, $platform_name) {
        try {
            // Determine content type
            $content_type = $featured_content['media_type'];
            $content_id = $featured_content['id'];
            
            // Get detailed information
            $details = $this->get_detailed_content_info($content_id, $content_type);
            if (is_wp_error($details)) {
                $this->log_error('Failed to get detailed content info', [
                    'content_id' => $content_id,
                    'content_type' => $content_type,
                    'error' => $details->get_error_message()
                ]);
                return false;
            }
            
            // Prepare content data
            $content_data = $this->prepare_content_data($featured_content, $details, $content_type);
            
            // Generate title
            $title = $this->generate_weekly_title($content_data, $platform_name, $content_type);
            
            // Generate article content
            $article_content = $this->generate_article_content($content_data, $platform_name, $content_type);
            
            // Create tags
            $tags = $this->create_weekly_tags($content_data, $platform);
            
            // Create the post
            $post_id = $this->create_post(
                $title,
                $article_content,
                $platform,
                $tags,
                'weekly'
            );
            
            if (!$post_id) {
                $this->log_error('Failed to create post', [
                    'title' => $title,
                    'platform' => $platform
                ]);
                return false;
            }
            
            // Set featured image (prioritize backdrop)
            $this->set_featured_image_with_landscape_priority($post_id, $content_data, $content_data['title']);
            
            // Add metadata
            $this->add_weekly_metadata($post_id, $content_data, $content_id, $content_type);
            
            return true;
            
        } catch (Exception $e) {
            $this->log_error('Error creating weekly article', [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Generate article content
     */
    private function generate_article_content($content_data, $platform_name, $content_type) {
        $title = $content_data['title'];
        $is_movie = ($content_type === 'movie');
        
        // Build the prompt for OpenAI
        $prompt = $this->build_content_prompt($content_data, $platform_name, $is_movie);
        
        // Generate content
        $generated_content = $this->openai->generate_content($prompt, 'gpt-4o-mini', 0.7, 1200);
        
        if (is_wp_error($generated_content)) {
            // Fallback to structured content
            return $this->create_fallback_article_content($content_data, $platform_name, $is_movie);
        }
        
        // Add structured data
        $final_content = $this->enhance_article_content($generated_content, $content_data, $platform_name);
        
        return $final_content;
    }
    
    /**
     * Build content generation prompt
     */
    private function build_content_prompt($content_data, $platform_name, $is_movie) {
        $media_type = $is_movie ? 'movie' : 'TV series';
        $title = $content_data['title'];
        
        $prompt = "Write an engaging, in-depth article about '{$title}', a new {$media_type} now available on {$platform_name}.\n\n";
        
        $prompt .= "Context:\n";
        $prompt .= "- Rating: {$content_data['rating']}/10\n";
        $prompt .= "- Overview: {$content_data['overview']}\n";
        
        if (!empty($content_data['genres'])) {
            $genres = array_map(function($g) { return $g['name']; }, $content_data['genres']);
            $prompt .= "- Genres: " . implode(', ', $genres) . "\n";
        }
        
        if ($is_movie && !empty($content_data['runtime'])) {
            $prompt .= "- Runtime: {$content_data['runtime']} minutes\n";
        }
        
        $prompt .= "\nRequirements:\n";
        $prompt .= "1. Start with a compelling hook that grabs attention\n";
        $prompt .= "2. Include a brief but insightful plot summary (avoid major spoilers)\n";
        $prompt .= "3. Discuss what makes this content special or worth watching\n";
        $prompt .= "4. Mention standout performances or technical aspects\n";
        $prompt .= "5. Compare to similar content when relevant\n";
        $prompt .= "6. Include viewing recommendations (who would enjoy this)\n";
        $prompt .= "7. End with a clear verdict\n\n";
        
        $prompt .= "Write in an enthusiastic but honest tone. Make it feel like a recommendation from a knowledgeable friend who has actually watched the content. ";
        $prompt .= "Use short paragraphs for easy reading. Minimum 800 words.";
        
        return $prompt;
    }
    
    /**
     * Enhance article content with additional elements
     */
    private function enhance_article_content($generated_content, $content_data, $platform_name) {
        $enhanced = '';
        
        // Add key information box
        $enhanced .= $this->create_info_box($content_data, $platform_name);
        
        // Add the main content
        $enhanced .= "\n" . $generated_content . "\n";
        
        // Add cast information if available
        if (!empty($content_data['cast'])) {
            $enhanced .= $this->create_cast_section($content_data['cast']);
        }
        
        // Add where to watch section
        $enhanced .= $this->create_watch_section($platform_name);
        
        return $enhanced;
    }
    
    /**
     * Create information box
     */
    private function create_info_box($content_data, $platform_name) {
        $info_box = '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">';
        $info_box .= '<h3 style="margin-top: 0;">Quick Info</h3>';
        $info_box .= '<ul style="list-style: none; padding: 0;">';
        
        $info_box .= '<li><strong>Title:</strong> ' . esc_html($content_data['title']) . '</li>';
        $info_box .= '<li><strong>Platform:</strong> ' . esc_html($platform_name) . '</li>';
        
        if (!empty($content_data['rating'])) {
            $info_box .= '<li><strong>Rating:</strong> ⭐ ' . number_format($content_data['rating'], 1) . '/10</li>';
        }
        
        if (!empty($content_data['genres'])) {
            $genres = array_map(function($g) { return $g['name']; }, $content_data['genres']);
            $info_box .= '<li><strong>Genres:</strong> ' . esc_html(implode(', ', $genres)) . '</li>';
        }
        
        if (!empty($content_data['runtime'])) {
            $hours = floor($content_data['runtime'] / 60);
            $minutes = $content_data['runtime'] % 60;
            $runtime_str = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
            $info_box .= '<li><strong>Runtime:</strong> ' . $runtime_str . '</li>';
        }
        
        if (!empty($content_data['release_date'])) {
            $info_box .= '<li><strong>Released:</strong> ' . date('F j, Y', strtotime($content_data['release_date'])) . '</li>';
        }
        
        $info_box .= '</ul>';
        $info_box .= '</div>';
        
        return $info_box;
    }
    
    /**
     * Create cast section
     */
    private function create_cast_section($cast) {
        if (empty($cast)) {
            return '';
        }
        
        $section = "\n<h2>Cast</h2>\n";
        $section .= "<p>Featuring performances by ";
        
        $cast_names = array();
        foreach (array_slice($cast, 0, 5) as $actor) {
            $cast_names[] = $actor['name'];
        }
        
        $section .= implode(', ', $cast_names);
        
        if (count($cast) > 5) {
            $section .= " and more";
        }
        
        $section .= ".</p>\n";
        
        return $section;
    }
    
    /**
     * Create watch section
     */
    private function create_watch_section($platform_name) {
        $section = "\n<h2>Where to Watch</h2>\n";
        $section .= "<p>You can stream this title now on <strong>{$platform_name}</strong>. ";
        $section .= "Make sure you have an active subscription to enjoy this and other great content on the platform.</p>\n";
        
        return $section;
    }
    
    /**
     * Create fallback article content
     */
    private function create_fallback_article_content($content_data, $platform_name, $is_movie) {
        $title = $content_data['title'];
        $overview = $content_data['overview'];
        $rating = $content_data['rating'];
        
        $content = $this->create_info_box($content_data, $platform_name);
        
        $content .= "<p><strong>{$title}</strong> has just arrived on {$platform_name}, and it's already generating buzz among viewers.</p>\n\n";
        
        $content .= "<h2>What It's About</h2>\n";
        $content .= "<p>{$overview}</p>\n\n";
        
        if ($rating >= 7.5) {
            $content .= "<h2>Why You Should Watch</h2>\n";
            $content .= "<p>With an impressive rating of {$rating}/10, this " . ($is_movie ? "film" : "series") . " has clearly resonated with audiences. ";
            $content .= "The combination of compelling storytelling and strong performances makes it a must-watch for anyone looking for quality entertainment.</p>\n\n";
        }
        
        if (!empty($content_data['cast'])) {
            $content .= $this->create_cast_section($content_data['cast']);
        }
        
        $content .= $this->create_watch_section($platform_name);
        
        return $content;
    }
    
    /**
     * Prepare content data
     */
    private function prepare_content_data($content, $details, $content_type) {
        $data = array(
            'title' => $content_type === 'movie' ? $content['title'] : $content['name'],
            'overview' => $content['overview'] ?? '',
            'rating' => $content['vote_average'] ?? 0,
            'vote_count' => $content['vote_count'] ?? 0,
            'popularity' => $content['popularity'] ?? 0,
            'poster_path' => $content['poster_path'] ?? '',
            'backdrop_path' => $content['backdrop_path'] ?? '',
            'poster_url' => !empty($content['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $content['poster_path'] : '',
            'backdrop_url' => !empty($content['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $content['backdrop_path'] : '',
            'genres' => $details['genres'] ?? array(),
            'tagline' => $details['tagline'] ?? '',
            'cast' => isset($details['credits']['cast']) ? array_slice($details['credits']['cast'], 0, 10) : array(),
        );
        
        if ($content_type === 'movie') {
            $data['release_date'] = $content['release_date'] ?? '';
            $data['runtime'] = $details['runtime'] ?? null;
            $data['director'] = $this->get_director_from_credits($details['credits'] ?? array());
        } else {
            $data['first_air_date'] = $content['first_air_date'] ?? '';
            $data['number_of_seasons'] = $details['number_of_seasons'] ?? null;
            $data['number_of_episodes'] = $details['number_of_episodes'] ?? null;
            $data['created_by'] = $details['created_by'] ?? array();
        }
        
        return $data;
    }
    
    /**
     * Get director from credits
     */
    private function get_director_from_credits($credits) {
        if (empty($credits['crew'])) {
            return '';
        }
        
        foreach ($credits['crew'] as $crew_member) {
            if (isset($crew_member['job']) && $crew_member['job'] === 'Director') {
                return $crew_member['name'];
            }
        }
        
        return '';
    }
    
    /**
     * Get detailed content info
     */
    private function get_detailed_content_info($content_id, $content_type) {
        $append_params = array('credits', 'videos', 'keywords');
        
        if ($content_type === 'movie') {
            return $this->tmdb->get_movie_details($content_id, $append_params);
        } else {
            return $this->tmdb->get_tv_details($content_id, $append_params);
        }
    }
    
    /**
     * Generate weekly title
     */
    private function generate_weekly_title($content, $platform, $content_type) {
        $platform_name = Streaming_Guide_Platforms::get_platform_name($platform);
        $date_range = date('F j', strtotime('-14 days')) . ' - ' . date('F j');
        
        if ($content_type === 'movie') {
            $title = $content['title'] ?? 'New Movies';
            return sprintf('New on %s: %s and More (%s)', $platform_name, $title, $date_range);
        } else {
            $title = $content['name'] ?? 'New Shows';
            return sprintf('New on %s: %s and More (%s)', $platform_name, $title, $date_range);
        }
    }
    
    /**
     * Create weekly tags
     */
    private function create_weekly_tags($content, $platform) {
        $tags = array();
        
        // Add platform tag
        $tags[] = Streaming_Guide_Platforms::get_platform_name($platform);
        
        // Add content type tags
        if ($content['media_type'] === 'movie') {
            $tags[] = 'Movies';
            if (!empty($content['genre_ids'])) {
                foreach ($content['genre_ids'] as $genre_id) {
                    $genre_name = $this->get_genre_name($genre_id, 'movie');
                    if ($genre_name) {
                        $tags[] = $genre_name;
                    }
                }
            }
        } else {
            $tags[] = 'TV Shows';
            if (!empty($content['genre_ids'])) {
                foreach ($content['genre_ids'] as $genre_id) {
                    $genre_name = $this->get_genre_name($genre_id, 'tv');
                    if ($genre_name) {
                        $tags[] = $genre_name;
                    }
                }
            }
        }
        
        // Add weekly tag
        $tags[] = 'Weekly Roundup';
        
        return array_unique($tags);
    }
    
    /**
     * Get genre name from ID
     */
    private function get_genre_name($genre_id, $type) {
        static $movie_genres = null;
        static $tv_genres = null;
        
        if ($movie_genres === null) {
            $movie_genres = $this->tmdb->get_genres('movie');
        }
        
        if ($tv_genres === null) {
            $tv_genres = $this->tmdb->get_genres('tv');
        }
        
        $genres = $type === 'movie' ? $movie_genres : $tv_genres;
        
        if (is_wp_error($genres)) {
            return null;
        }
        
        foreach ($genres['genres'] as $genre) {
            if ($genre['id'] === $genre_id) {
                return $genre['name'];
            }
        }
        
        return null;
    }
    
    /**
     * Add weekly metadata
     */
    private function add_weekly_metadata($post_id, $content_data, $content_id, $content_type) {
        update_post_meta($post_id, 'weekly_generation_date', date('Y-m-d'));
        update_post_meta($post_id, 'featured_content_id', $content_id);
        update_post_meta($post_id, 'featured_content_type', $content_type);
        update_post_meta($post_id, 'content_title', $content_data['title']);
        update_post_meta($post_id, 'content_rating', $content_data['rating']);
        update_post_meta($post_id, 'tmdb_backdrop_url', $content_data['backdrop_url']);
        update_post_meta($post_id, 'tmdb_poster_url', $content_data['poster_url']);
        
        // Add structured data for SEO
        if (!empty($content_data['genres'])) {
            $genre_names = array_map(function($g) { return $g['name']; }, $content_data['genres']);
            update_post_meta($post_id, 'content_genres', implode(', ', $genre_names));
        }
    }
    
    /**
     * Check for recent weekly content
     */
    private function has_recent_weekly_content($platform) {
        $args = array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'streaming_platform',
                    'value' => $platform,
                    'compare' => '='
                ),
                array(
                    'key' => 'article_type',
                    'value' => 'weekly_whats_new',
                    'compare' => '='
                ),
                array(
                    'key' => 'weekly_generation_date',
                    'value' => date('Y-m-d', strtotime('-7 days')),
                    'compare' => '>',
                    'type' => 'DATE'
                )
            )
        );
        
        $posts = get_posts($args);
        return !empty($posts);
    }
    
    /**
     * Generate fallback content using trending
     */
    private function generate_fallback_content($platform, $provider_id, $platform_name) {
        $this->log_info("Generating trending fallback for {$platform}");
        
        // Get trending content
        $trending_movies = $this->tmdb->get_trending_movies('week');
        $trending_tv = $this->tmdb->get_trending_tv('week');
        
        $all_trending = array();
        
        if (!is_wp_error($trending_movies) && !empty($trending_movies['results'])) {
            foreach ($trending_movies['results'] as $movie) {
                $movie['media_type'] = 'movie';
                $all_trending[] = $movie;
            }
        }
        
        if (!is_wp_error($trending_tv) && !empty($trending_tv['results'])) {
            foreach ($trending_tv['results'] as $show) {
                $show['media_type'] = 'tv';
                $all_trending[] = $show;
            }
        }
        
        if (empty($all_trending)) {
            return new WP_Error('no_content', 'No trending content available.');
        }
        
        // Filter for quality and select featured
        $filtered = $this->filter_quality_content($all_trending);
        $featured_content = $this->select_featured_content($filtered);
        
        if (!$featured_content) {
            return new WP_Error('no_content', 'No suitable content found.');
        }
        
        // Create article with trending focus
        return $this->create_weekly_article($featured_content, $platform, $platform_name);
    }

    /**
     * Create content blocks for the article
     */
    private function create_content_blocks($content) {
        $blocks = array();
        
        // Add introduction
        $blocks[] = $this->create_introduction_block($content);
        
        // Add featured content section
        if (!empty($content[0])) {
            $blocks[] = $this->create_featured_content_block($content[0]);
        }
        
        // Add other content sections
        $blocks[] = $this->create_other_content_block(array_slice($content, 1));
        
        // Add conclusion
        $blocks[] = $this->create_conclusion_block();
        
        return $blocks;
    }

    /**
     * Create introduction block
     */
    private function create_introduction_block($content) {
        $total_items = count($content);
        $date_range = date('F j', strtotime('-14 days')) . ' - ' . date('F j');
        
        return sprintf(
            '<h2>New This Week</h2>' .
            '<p>Welcome to our weekly roundup of new streaming content! ' .
            'We\'ve found %d exciting new additions from the past two weeks (%s). ' .
            'Here\'s what\'s worth watching:</p>',
            $total_items,
            $date_range
        );
    }

    /**
     * Create featured content block
     */
    private function create_featured_content_block($content) {
        $title = $content['title'] ?? $content['name'] ?? 'Unknown Title';
        $overview = $content['overview'] ?? '';
        $release_date = $content['release_date'] ?? $content['first_air_date'] ?? '';
        $rating = $content['vote_average'] ?? '';
        
        $html = '<div class="featured-content">';
        $html .= '<h3>' . esc_html($title) . '</h3>';
        
        if ($release_date) {
            $html .= '<p class="release-date">Released: ' . date('F j, Y', strtotime($release_date)) . '</p>';
        }
        
        if ($rating) {
            $html .= '<p class="rating">Rating: ' . number_format($rating, 1) . '/10</p>';
        }
        
        $html .= '<p>' . esc_html($overview) . '</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Create other content block
     */
    private function create_other_content_block($content) {
        if (empty($content)) {
            return '';
        }
        
        $html = '<h3>More New Additions</h3><ul>';
        
        foreach ($content as $item) {
            $title = $item['title'] ?? $item['name'] ?? 'Unknown Title';
            $release_date = $item['release_date'] ?? $item['first_air_date'] ?? '';
            $rating = $item['vote_average'] ?? '';
            
            $html .= '<li>';
            $html .= '<strong>' . esc_html($title) . '</strong>';
            
            if ($release_date) {
                $html .= ' - Released: ' . date('F j, Y', strtotime($release_date));
            }
            
            if ($rating) {
                $html .= ' (Rating: ' . number_format($rating, 1) . '/10)';
            }
            
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        return $html;
    }

    /**
     * Create conclusion block
     */
    private function create_conclusion_block() {
        return '<h3>Stay Tuned</h3>' .
               '<p>Check back next week for more new streaming content recommendations. ' .
               'Don\'t forget to follow us for the latest updates on what\'s new to watch!</p>';
    }
}