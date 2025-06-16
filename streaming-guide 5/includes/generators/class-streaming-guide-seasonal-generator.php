<?php
/**
 * Seasonal Generator for Streaming Guide
 * 
 * Creates content based on seasons, holidays, and seasonal themes
 * Properly inherits from base class with correct method signatures and access levels
 * UPDATED: Replaced old logging with new structured logging methods.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Seasonal_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Generate seasonal content
     * Must match exact signature: generate($platform, $param1 = null, $param2 = null, $param3 = null)
     */
    public function generate($platform, $param1 = null, $param2 = null, $param3 = null) {
        // Map parameters to meaningful names
        $season_type = $param1 ?? 'current'; // 'current', 'spring', 'summer', 'fall', 'winter', 'holiday'
        $content_type = $param2 ?? 'mixed'; // 'movies', 'tv', 'mixed'
        $specific_theme = $param3 ?? null; // 'christmas', 'halloween', 'valentines', 'thanksgiving', etc.
        
        $params = [
            'season_type' => $season_type,
            'content_type' => $content_type,
            'specific_theme' => $specific_theme
        ];

        try {
            $this->log_info("Starting seasonal generation for {$platform}", $params);
            
            // Get platform configuration
            $platform_name = $this->get_platform_name($platform);
            $provider_id = $this->get_provider_id($platform);
            
            if (!$platform_name || !$provider_id) {
                $this->log_generation_failure('seasonal', $platform, 'Invalid platform configuration', $params);
                return false;
            }
            
            // Determine actual season and themes to use
            $season_config = $this->get_season_config($season_type, $specific_theme);
            
            if (!$season_config) {
                $this->log_generation_failure('seasonal', $platform, 'Could not determine seasonal configuration', $params);
                return false;
            }
            
            // Get seasonal content
            $seasonal_content = $this->get_seasonal_content($provider_id, $content_type, $season_config);
            
            if (empty($seasonal_content)) {
                $this->log_generation_failure('seasonal', $platform, 'No seasonal content found', [
                    'season_name' => $season_config['name'],
                    'content_type' => $content_type
                ]);
                return false;
            }
            
            // Create the article
            $title = $this->create_seasonal_title($platform_name, $season_config, $content_type);
            $content_blocks = $this->create_seasonal_content_blocks($seasonal_content, $platform_name, $season_config, $content_type);
            $tags = $this->create_seasonal_tags($platform_name, $season_config, $content_type);
            
            // Create post using parent method with correct signature
            $post_id = $this->create_post($title, $content_blocks, $platform, $tags, 'seasonal');
            
            if ($post_id) {
                // Set featured image using the most relevant seasonal content
                $this->set_seasonal_featured_image($post_id, $seasonal_content[0]);
                
                // Add seasonal specific metadata
                $this->add_seasonal_metadata($post_id, $season_config, $content_type, count($seasonal_content));
                
                $this->log_info("Successfully created seasonal post: {$title} (ID: {$post_id})");
                return $post_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log_generation_failure('seasonal', $platform, 'Seasonal generator error: ' . $e->getMessage(), $params);
            return false;
        }
    }
    
    /**
     * Get season configuration based on parameters
     */
    private function get_season_config($season_type, $specific_theme) {
        $current_month = (int) date('n');
        $current_day = (int) date('j');
        
        // If specific theme provided, use that
        if ($specific_theme) {
            return $this->get_specific_theme_config($specific_theme);
        }
        
        // If current season requested, determine from date
        if ($season_type === 'current') {
            $season_type = $this->determine_current_season($current_month, $current_day);
        }
        
        // Return season configuration
        switch ($season_type) {
            case 'spring':
                return array(
                    'name' => 'Spring',
                    'keywords' => array('spring', 'renewal', 'fresh start', 'blooming', 'easter'),
                    'genres' => array(35, 10749, 10751), // Comedy, Romance, Family
                    'mood' => 'optimistic',
                    'description' => 'spring renewal and fresh beginnings'
                );
                
            case 'summer':
                return array(
                    'name' => 'Summer',
                    'keywords' => array('summer', 'vacation', 'beach', 'adventure', 'blockbuster'),
                    'genres' => array(28, 12, 878), // Action, Adventure, Sci-Fi
                    'mood' => 'energetic',
                    'description' => 'summer excitement and adventure'
                );
                
            case 'fall':
            case 'autumn':
                return array(
                    'name' => 'Fall',
                    'keywords' => array('autumn', 'fall', 'harvest', 'cozy', 'back to school'),
                    'genres' => array(18, 9648, 53), // Drama, Mystery, Thriller
                    'mood' => 'contemplative',
                    'description' => 'autumn atmosphere and cozy nights'
                );
                
            case 'winter':
                return array(
                    'name' => 'Winter',
                    'keywords' => array('winter', 'holiday', 'cozy', 'snow', 'fireplace'),
                    'genres' => array(10751, 35, 18), // Family, Comedy, Drama
                    'mood' => 'cozy',
                    'description' => 'winter warmth and holiday spirit'
                );
                
            case 'holiday':
                return $this->get_current_holiday_config($current_month, $current_day);
                
            default:
                return false;
        }
    }
    
    /**
     * Get specific theme configuration
     */
    private function get_specific_theme_config($theme) {
        $theme_configs = array(
            'christmas' => array(
                'name' => 'Christmas',
                'keywords' => array('christmas', 'santa', 'holiday', 'winter', 'family'),
                'genres' => array(10751, 35, 10749), // Family, Comedy, Romance
                'mood' => 'festive',
                'description' => 'Christmas magic and holiday joy'
            ),
            'halloween' => array(
                'name' => 'Halloween',
                'keywords' => array('halloween', 'horror', 'scary', 'monster', 'ghost'),
                'genres' => array(27, 53, 9648), // Horror, Thriller, Mystery
                'mood' => 'spooky',
                'description' => 'Halloween thrills and chills'
            ),
            'valentines' => array(
                'name' => "Valentine's Day",
                'keywords' => array('valentine', 'love', 'romance', 'dating', 'relationship'),
                'genres' => array(10749, 35, 18), // Romance, Comedy, Drama
                'mood' => 'romantic',
                'description' => 'love and romance'
            ),
            'thanksgiving' => array(
                'name' => 'Thanksgiving',
                'keywords' => array('thanksgiving', 'family', 'gratitude', 'feast', 'tradition'),
                'genres' => array(10751, 18, 35), // Family, Drama, Comedy
                'mood' => 'grateful',
                'description' => 'family gatherings and gratitude'
            ),
            'new_year' => array(
                'name' => 'New Year',
                'keywords' => array('new year', 'resolution', 'fresh start', 'celebration', 'party'),
                'genres' => array(35, 18, 10749), // Comedy, Drama, Romance
                'mood' => 'hopeful',
                'description' => 'new beginnings and fresh starts'
            )
        );
        
        return $theme_configs[$theme] ?? false;
    }
    
    /**
     * Determine current season from date
     */
    private function determine_current_season($month, $day) {
        // Spring: March 20 - June 20
        if (($month == 3 && $day >= 20) || $month == 4 || $month == 5 || ($month == 6 && $day < 21)) {
            return 'spring';
        }
        // Summer: June 21 - September 22
        elseif (($month == 6 && $day >= 21) || $month == 7 || $month == 8 || ($month == 9 && $day < 23)) {
            return 'summer';
        }
        // Fall: September 23 - December 20
        elseif (($month == 9 && $day >= 23) || $month == 10 || $month == 11 || ($month == 12 && $day < 21)) {
            return 'fall';
        }
        // Winter: December 21 - March 19
        else {
            return 'winter';
        }
    }
    
    /**
     * Get current holiday configuration if we're near a holiday
     */
    private function get_current_holiday_config($month, $day) {
        // Christmas season (December 1-31)
        if ($month == 12) {
            return $this->get_specific_theme_config('christmas');
        }
        // Halloween season (October)
        elseif ($month == 10) {
            return $this->get_specific_theme_config('halloween');
        }
        // Valentine's season (February 1-14)
        elseif ($month == 2 && $day <= 14) {
            return $this->get_specific_theme_config('valentines');
        }
        // Thanksgiving season (November)
        elseif ($month == 11) {
            return $this->get_specific_theme_config('thanksgiving');
        }
        // New Year season (January 1-15)
        elseif ($month == 1 && $day <= 15) {
            return $this->get_specific_theme_config('new_year');
        }
        
        // Fall back to current season
        $season = $this->determine_current_season($month, $day);
        return $this->get_season_config($season, null);
    }
    
    /**
     * Get seasonal content based on configuration
     */
    private function get_seasonal_content($provider_id, $content_type, $season_config) {
        try {
            $all_content = array();
            
            if ($content_type === 'movies' || $content_type === 'mixed') {
                $movies = $this->get_seasonal_movies($provider_id, $season_config);
                $all_content = array_merge($all_content, $movies);
            }
            
            if ($content_type === 'tv' || $content_type === 'mixed') {
                $tv_shows = $this->get_seasonal_tv($provider_id, $season_config);
                $all_content = array_merge($all_content, $tv_shows);
            }
            
            // Sort by seasonal relevance score
            usort($all_content, function($a, $b) use ($season_config) {
                return $this->calculate_seasonal_score($b, $season_config) - $this->calculate_seasonal_score($a, $season_config);
            });
            
            return array_slice($all_content, 0, 8);
            
        } catch (Exception $e) {
            $this->log_error('Error getting seasonal content', [
                'provider_id' => $provider_id,
                'season' => $season_config['name'],
                'error' => $e->getMessage()
            ]);
            return array();
        }
    }
    
    /**
     * Get seasonal movies
     */
    private function get_seasonal_movies($provider_id, $season_config) {
        $movies = array();
        
        // Search by genre
        foreach ($season_config['genres'] as $genre_id) {
            $genre_movies = $this->tmdb->discover_movies(array(
                'with_watch_providers' => $provider_id,
                'watch_region' => 'US',
                'with_genres' => $genre_id,
                'vote_average.gte' => 6.0,
                'sort_by' => 'popularity.desc',
                'page' => 1
            ));
            
            if (!is_wp_error($genre_movies) && isset($genre_movies['results'])) {
                foreach (array_slice($genre_movies['results'], 0, 5) as $movie) {
                    $movie['media_type'] = 'movie';
                    $movies[] = $movie;
                }
            }
        }
        
        // Search by keywords
        foreach ($season_config['keywords'] as $keyword) {
            $keyword_movies = $this->tmdb->search_movies($keyword);
            
            if (!is_wp_error($keyword_movies) && isset($keyword_movies['results'])) {
                foreach (array_slice($keyword_movies['results'], 0, 3) as $movie) {
                    // Check if available on platform
                    if ($this->is_content_on_platform($movie['id'], 'movie', $provider_id)) {
                        $movie['media_type'] = 'movie';
                        $movies[] = $movie;
                    }
                }
            }
        }
        
        return $movies;
    }
    
    /**
     * Get seasonal TV shows
     */
    private function get_seasonal_tv($provider_id, $season_config) {
        $tv_shows = array();
        
        // Search by genre
        foreach ($season_config['genres'] as $genre_id) {
            $genre_tv = $this->tmdb->discover_tv(array(
                'with_watch_providers' => $provider_id,
                'watch_region' => 'US',
                'with_genres' => $genre_id,
                'vote_average.gte' => 6.0,
                'sort_by' => 'popularity.desc',
                'page' => 1
            ));
            
            if (!is_wp_error($genre_tv) && isset($genre_tv['results'])) {
                foreach (array_slice($genre_tv['results'], 0, 5) as $show) {
                    $show['media_type'] = 'tv';
                    $tv_shows[] = $show;
                }
            }
        }
        
        // Search by keywords
        foreach ($season_config['keywords'] as $keyword) {
            $keyword_tv = $this->tmdb->search_tv($keyword);
            
            if (!is_wp_error($keyword_tv) && isset($keyword_tv['results'])) {
                foreach (array_slice($keyword_tv['results'], 0, 3) as $show) {
                    // Check if available on platform
                    if ($this->is_content_on_platform($show['id'], 'tv', $provider_id)) {
                        $show['media_type'] = 'tv';
                        $tv_shows[] = $show;
                    }
                }
            }
        }
        
        return $tv_shows;
    }
    
    /**
     * Check if content is available on platform
     */
    private function is_content_on_platform($content_id, $media_type, $provider_id) {
        try {
            $providers = $this->tmdb->get_watch_providers($content_id, $media_type);
            
            if (is_wp_error($providers)) {
                return false;
            }
            
            $us_providers = $providers['results']['US'] ?? array();
            $flatrate_providers = $us_providers['flatrate'] ?? array();
            
            foreach ($flatrate_providers as $provider) {
                if ($provider['provider_id'] == $provider_id) {
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log_error('API error checking platform availability', [
                'content_id' => $content_id, 
                'media_type' => $media_type, 
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Calculate seasonal relevance score
     */
    private function calculate_seasonal_score($item, $season_config) {
        $score = 0;
        
        // Base popularity score
        $score += ($item['popularity'] ?? 0) * 0.3;
        
        // Rating score
        $score += ($item['vote_average'] ?? 0) * 10;
        
        // Keyword matching in title and overview
        $text_to_search = strtolower(($item['title'] ?? $item['name'] ?? '') . ' ' . ($item['overview'] ?? ''));
        
        foreach ($season_config['keywords'] as $keyword) {
            if (strpos($text_to_search, strtolower($keyword)) !== false) {
                $score += 50; // High bonus for keyword match
            }
        }
        
        // Release date seasonality (for movies)
        if ($item['media_type'] === 'movie' && isset($item['release_date'])) {
            $release_month = (int) date('n', strtotime($item['release_date']));
            $current_month = (int) date('n');
            
            // Bonus if released in current season
            $season_months = $this->get_season_months($season_config['name']);
            if (in_array($release_month, $season_months) || in_array($current_month, $season_months)) {
                $score += 25;
            }
        }
        
        return $score;
    }
    
    /**
     * Get months associated with a season
     */
    private function get_season_months($season_name) {
        switch (strtolower($season_name)) {
            case 'spring':
                return array(3, 4, 5);
            case 'summer':
                return array(6, 7, 8);
            case 'fall':
                return array(9, 10, 11);
            case 'winter':
                return array(12, 1, 2);
            case 'christmas':
                return array(12);
            case 'halloween':
                return array(10);
            case "valentine's day":
                return array(2);
            case 'thanksgiving':
                return array(11);
            case 'new year':
                return array(1);
            default:
                return array();
        }
    }
    
    /**
     * Create seasonal title
     */
    private function create_seasonal_title($platform_name, $season_config, $content_type) {
        $season_name = $season_config['name'];
        
        switch ($content_type) {
            case 'movies':
                return "Perfect {$season_name} Movies to Stream on {$platform_name}";
            case 'tv':
                return "Cozy {$season_name} TV Shows for Your {$platform_name} Watchlist";
            default:
                return "Essential {$season_name} Streaming: What to Watch on {$platform_name}";
        }
    }
    
    /**
     * Create seasonal content blocks
     */
    private function create_seasonal_content_blocks($seasonal_content, $platform_name, $season_config, $content_type) {
        $content_blocks = array();
        $season_name = $season_config['name'];
        $mood = $season_config['mood'];
        $description = $season_config['description'];
        
        // Introduction
        $intro = "As we embrace the spirit of <strong>{$season_name}</strong>, there's no better time to curate the perfect watchlist on <strong>{$platform_name}</strong>. ";
        $intro .= "This carefully selected collection captures the essence of {$description}, offering content that perfectly matches the {$mood} mood of the season. ";
        $intro .= "Whether you're looking to fully immerse yourself in seasonal themes or simply want entertainment that feels right for this time of year, ";
        $intro .= "these selections will enhance your streaming experience.";
        
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $intro
        );
        
        // Seasonal picks list
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => "Our {$season_name} Picks"
        );
        
        $seasonal_items = array();
        foreach ($seasonal_content as $index => $item) {
            $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
            $type = $item['media_type'] === 'movie' ? 'Movie' : 'TV Series';
            $rating = isset($item['vote_average']) ? round($item['vote_average'], 1) : 'N/A';
            
            $seasonal_reason = $this->get_seasonal_reason($item, $season_config);
            
            $seasonal_items[] = "<strong>{$title}</strong> ({$type}) - Rating: {$rating}/10 - {$seasonal_reason}";
        }
        
        $content_blocks[] = array(
            'type' => 'list',
            'items' => $seasonal_items
        );
        
        // Featured seasonal highlights
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => "{$season_name} Highlights"
        );
        
        for ($i = 0; $i < min(3, count($seasonal_content)); $i++) {
            $item = $seasonal_content[$i];
            $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
            
            $content_blocks[] = array(
                'type' => 'heading',
                'level' => 3,
                'content' => $title
            );
            
            if (!empty($item['overview'])) {
                $content_blocks[] = array(
                    'type' => 'paragraph',
                    'content' => $item['overview']
                );
            }
            
            // Add seasonal context
            $seasonal_context = $this->create_seasonal_context($item, $season_config);
            $content_blocks[] = array(
                'type' => 'paragraph',
                'content' => $seasonal_context
            );
        }
        
        // Seasonal viewing guide
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => "The Perfect {$season_name} Viewing Experience"
        );
        
        $viewing_guide = $this->create_seasonal_viewing_guide($season_config, $platform_name);
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $viewing_guide
        );
        
        // Conclusion
        $conclusion = "This {$season_name} collection on <strong>{$platform_name}</strong> offers the perfect blend of seasonal atmosphere and quality entertainment. ";
        $conclusion .= "Each selection has been chosen not just for its individual merit, but for how well it captures and enhances the unique feeling of this time of year. ";
        $conclusion .= "Whether you're seeking comfort, excitement, or inspiration, these {$season_name} picks provide the ideal backdrop for your seasonal streaming experience.";
        
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $conclusion
        );
        
        return $content_blocks;
    }
    
    /**
     * Get seasonal reason for an item
     */
    private function get_seasonal_reason($item, $season_config) {
        $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
        $text = strtolower($title . ' ' . ($item['overview'] ?? ''));
        
        // Check for keyword matches
        foreach ($season_config['keywords'] as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                switch ($season_config['mood']) {
                    case 'festive':
                        return "Perfect for holiday celebrations and festive gatherings";
                    case 'spooky':
                        return "Ideal for thrilling Halloween entertainment";
                    case 'romantic':
                        return "Great for romantic evenings and date nights";
                    case 'cozy':
                        return "Perfect for cozy nights in during the season";
                    case 'energetic':
                        return "Captures the energetic spirit of the season";
                    case 'contemplative':
                        return "Matches the reflective mood of the season";
                    case 'optimistic':
                        return "Embodies the hopeful spirit of renewal";
                    default:
                        return "Perfectly captures the seasonal atmosphere";
                }
            }
        }
        
        // Generic seasonal reasons
        return "Great seasonal viewing for its genre and mood";
    }
    
    /**
     * Create seasonal context for items
     */
    private function create_seasonal_context($item, $season_config) {
        $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
        $type = $item['media_type'] === 'movie' ? 'film' : 'series';
        $season_name = $season_config['name'];
        $mood = $season_config['mood'];
        
        $context = "This {$type} exemplifies the {$mood} spirit of {$season_name}. ";
        
        if (isset($item['vote_average']) && $item['vote_average'] > 7.0) {
            $rating = round($item['vote_average'], 1);
            $context .= "With its {$rating}/10 rating, '<strong>{$title}</strong>' combines seasonal appeal with proven quality. ";
        }
        
        $context .= "It's the kind of content that doesn't just entertain, but actually enhances the seasonal experience, ";
        $context .= "making it feel like an integral part of your {$season_name} traditions.";
        
        return $context;
    }
    
    /**
     * Create seasonal viewing guide
     */
    private function create_seasonal_viewing_guide($season_config, $platform_name) {
        $season_name = $season_config['name'];
        $mood = $season_config['mood'];
        
        $guide = "To get the most out of your {$season_name} viewing on {$platform_name}, consider the timing and atmosphere. ";
        
        switch ($mood) {
            case 'cozy':
                $guide .= "These selections are perfect for quiet evenings with warm drinks and comfortable blankets. ";
                $guide .= "The shorter days make them ideal for afternoon or early evening viewing sessions.";
                break;
                
            case 'festive':
                $guide .= "These titles shine brightest when shared with family and friends during holiday gatherings. ";
                $guide .= "Consider making them part of your seasonal traditions and celebrations.";
                break;
                
            case 'energetic':
                $guide .= "Take advantage of longer days and vibrant energy by watching during peak daylight hours. ";
                $guide .= "These selections complement active, social viewing experiences.";
                break;
                
            case 'romantic':
                $guide .= "Set the mood with dim lighting and intimate settings for the perfect romantic viewing experience. ";
                $guide .= "These selections are designed for shared moments and connection.";
                break;
                
            case 'spooky':
                $guide .= "For maximum effect, watch these in the dark with minimal distractions. ";
                $guide .= "The atmospheric elements work best in immersive viewing conditions.";
                break;
                
            default:
                $guide .= "These selections work best when you can fully appreciate their seasonal themes and atmospheric elements. ";
                $guide .= "Consider the mood and setting that best matches the {$season_name} spirit.";
        }
        
        return $guide;
    }
    
    /**
     * Create seasonal tags
     */
    private function create_seasonal_tags($platform_name, $season_config, $content_type) {
        $tags = array(
            $platform_name,
            'seasonal',
            strtolower($season_config['name']),
            $season_config['mood']
        );
        
        // Add keywords as tags
        foreach ($season_config['keywords'] as $keyword) {
            $tags[] = $keyword;
        }
        
        if ($content_type === 'movies') {
            $tags[] = 'movies';
            $tags[] = 'seasonal movies';
        } elseif ($content_type === 'tv') {
            $tags[] = 'tv shows';
            $tags[] = 'seasonal tv';
        } else {
            $tags[] = 'movies';
            $tags[] = 'tv shows';
        }
        
        return array_map('sanitize_text_field', array_unique($tags));
    }
    
    /**
     * Set featured image for seasonal post
     */
    private function set_seasonal_featured_image($post_id, $featured_item) {
        $content_data = array(
            'title' => $featured_item['media_type'] === 'movie' ? $featured_item['title'] : $featured_item['name'],
            'backdrop_url' => !empty($featured_item['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $featured_item['backdrop_path'] : '',
            'poster_url' => !empty($featured_item['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $featured_item['poster_path'] : ''
        );
        
        return $this->set_featured_image_with_landscape_priority($post_id, $content_data, $content_data['title']);
    }
    
    /**
     * Add seasonal specific metadata
     */
    private function add_seasonal_metadata($post_id, $season_config, $content_type, $item_count) {
        update_post_meta($post_id, 'seasonal_type', strtolower($season_config['name']));
        update_post_meta($post_id, 'seasonal_mood', $season_config['mood']);
        update_post_meta($post_id, 'content_type', $content_type);
        update_post_meta($post_id, 'item_count', $item_count);
        update_post_meta($post_id, 'season_keywords', implode(',', $season_config['keywords']));
        update_post_meta($post_id, 'generation_month', date('n'));
        update_post_meta($post_id, 'generation_season', $this->determine_current_season(date('n'), date('j')));
    }
}