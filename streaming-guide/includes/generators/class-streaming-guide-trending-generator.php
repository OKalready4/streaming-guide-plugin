<?php
/**
 * Trending Generator for Streaming Guide
 * 
 * Focuses on currently trending content across streaming platforms
 * Properly inherits from base class with correct method signatures and access levels
 * UPDATED: Replaced old logging with new structured logging methods.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Trending_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Generate trending content
     * Must match exact signature: generate($platform, $param1 = null, $param2 = null, $param3 = null)
     */
    public function generate($platform, $param1 = null, $param2 = null, $param3 = null) {
        // Map parameters to meaningful names
        $content_type = $param1 ?? 'mixed'; // 'movies', 'tv', or 'mixed'
        $time_window = $param2 ?? 'day'; // 'day', 'week'
        $trend_type = $param3 ?? 'platform'; // 'platform', 'general', 'rising'
        
        $params = [
            'content_type' => $content_type,
            'time_window' => $time_window,
            'trend_type' => $trend_type
        ];

        try {
            $this->log_info("Starting trending generation for {$platform}", $params);
            
            // Get platform configuration
            $platform_name = $this->get_platform_name($platform);
            $provider_id = $this->get_provider_id($platform);
            
            if (!$platform_name || !$provider_id) {
                $this->log_generation_failure('trending', $platform, 'Invalid platform configuration', $params);
                return false;
            }
            
            // Get trending content
            $trending_content = $this->get_trending_content($provider_id, $content_type, $time_window, $trend_type);
            
            if (empty($trending_content)) {
                $this->log_generation_failure('trending', $platform, 'No trending content found', $params);
                return false;
            }
            
            // Create the article
            $title = $this->create_trending_title($platform_name, $content_type, $time_window, $trend_type);
            $content_blocks = $this->create_trending_content_blocks($trending_content, $platform_name, $content_type, $time_window, $trend_type);
            $tags = $this->create_trending_tags($platform_name, $content_type, $time_window, $trend_type);
            
            // Create post using parent method with correct signature
            $post_id = $this->create_post($title, $content_blocks, $platform, $tags, 'trending');
            
            if ($post_id) {
                // Set featured image using the top trending item
                if (!empty($trending_content[0])) {
                    $this->set_trending_featured_image($post_id, $trending_content[0]);
                }
                
                // Add trending specific metadata
                $this->add_trending_metadata($post_id, $content_type, $time_window, $trend_type, count($trending_content));
                
                $this->log_info("Successfully created trending post: {$title} (ID: {$post_id})");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log_generation_failure('trending', $platform, 'Trending generator error: ' . $e->getMessage(), $params);
            return false;
        }
    }
    
    /**
     * Get trending content based on parameters
     */
    private function get_trending_content($provider_id, $content_type, $time_window, $trend_type) {
        try {
            $all_content = array();
            
            // Get trending content from TMDB
            if ($content_type === 'movies' || $content_type === 'mixed') {
                $trending_movies = $this->get_trending_movies($time_window);
                if ($trend_type === 'platform') {
                    $trending_movies = $this->filter_by_platform($trending_movies, $provider_id, 'movie');
                }
                $all_content = array_merge($all_content, $trending_movies);
            }
            
            if ($content_type === 'tv' || $content_type === 'mixed') {
                $trending_tv = $this->get_trending_tv($time_window);
                if ($trend_type === 'platform') {
                    $trending_tv = $this->filter_by_platform($trending_tv, $provider_id, 'tv');
                }
                $all_content = array_merge($all_content, $trending_tv);
            }
            
            // Sort by trending score and take top results
            usort($all_content, function($a, $b) {
                return $this->calculate_trending_score($b) - $this->calculate_trending_score($a);
            });
            
            // Return appropriate number based on trend type
            $max_items = $trend_type === 'rising' ? 5 : 8;
            return array_slice($all_content, 0, $max_items);
            
        } catch (Exception $e) {
            $this->log_error('Error getting trending content', [
                'provider_id' => $provider_id,
                'content_type' => $content_type,
                'time_window' => $time_window,
                'trend_type' => $trend_type,
                'error' => $e->getMessage()
            ]);
            return array();
        }
    }
    
    /**
     * Get trending movies from TMDB
     */
    private function get_trending_movies($time_window) {
        try {
            $trending = $this->tmdb->get_trending_movies($time_window);
            
            if (is_wp_error($trending)) {
                $this->log_error('TMDB API error getting trending movies', [
                    'time_window' => $time_window,
                    'error' => $trending->get_error_message()
                ]);
                return array();
            }
            
            if (!isset($trending['results'])) {
                $this->log_error('Invalid TMDB response for trending movies', [
                    'time_window' => $time_window,
                    'response' => $trending
                ]);
                return array();
            }
            
            return array_map(function($movie) {
                $movie['media_type'] = 'movie';
                return $movie;
            }, $trending['results']);
            
        } catch (Exception $e) {
            $this->log_error('Error getting trending movies', [
                'time_window' => $time_window,
                'error' => $e->getMessage()
            ]);
            return array();
        }
    }
    
    /**
     * Get trending TV shows from TMDB
     */
    private function get_trending_tv($time_window) {
        try {
            $trending = $this->tmdb->get_trending_tv($time_window);
            
            if (is_wp_error($trending)) {
                $this->log_error('TMDB API error getting trending TV shows', [
                    'time_window' => $time_window,
                    'error' => $trending->get_error_message()
                ]);
                return array();
            }
            
            if (!isset($trending['results'])) {
                $this->log_error('Invalid TMDB response for trending TV shows', [
                    'time_window' => $time_window,
                    'response' => $trending
                ]);
                return array();
            }
            
            return array_map(function($show) {
                $show['media_type'] = 'tv';
                return $show;
            }, $trending['results']);
            
        } catch (Exception $e) {
            $this->log_error('Error getting trending TV shows', [
                'time_window' => $time_window,
                'error' => $e->getMessage()
            ]);
            return array();
        }
    }
    
    /**
     * Filter content by platform availability
     */
    private function filter_by_platform($content, $provider_id, $media_type) {
        $filtered = array();
        
        foreach ($content as $item) {
            try {
                // Check if item is available on the platform
                $providers = $this->tmdb->get_watch_providers($item['id'], $media_type);
                
                if (is_wp_error($providers)) {
                    continue;
                }
                
                $us_providers = $providers['results']['US'] ?? array();
                $flatrate_providers = $us_providers['flatrate'] ?? array();
                
                foreach ($flatrate_providers as $provider) {
                    if ($provider['provider_id'] == $provider_id) {
                        $filtered[] = $item;
                        break;
                    }
                }
                
            } catch (Exception $e) {
                $this->log_error('Error filtering item for platform', [
                    'item_id' => $item['id'] ?? 'N/A',
                    'media_type' => $media_type,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Calculate trending score for sorting
     */
    private function calculate_trending_score($item) {
        $popularity = $item['popularity'] ?? 0;
        $vote_average = $item['vote_average'] ?? 0;
        $vote_count = $item['vote_count'] ?? 0;
        
        // Weight recent popularity higher for trending
        $score = ($popularity * 0.6) + ($vote_average * 0.2) + (log($vote_count + 1) * 0.2);
        
        return $score;
    }
    
    /**
     * Create trending title
     */
    private function create_trending_title($platform_name, $content_type, $time_window, $trend_type) {
        $time_label = $time_window === 'day' ? 'Today' : 'This Week';
        
        switch ($trend_type) {
            case 'platform':
                switch ($content_type) {
                    case 'movies':
                        return "Trending Movies on {$platform_name} {$time_label}";
                    case 'tv':
                        return "Trending TV Shows on {$platform_name} {$time_label}";
                    default:
                        return "What's Trending on {$platform_name} {$time_label}";
                }
                break;
                
            case 'rising':
                return "Rising Stars: Breaking Content on {$platform_name}";
                
            default: // 'general'
                switch ($content_type) {
                    case 'movies':
                        return "Today's Hottest Movies (Available on {$platform_name})";
                    case 'tv':
                        return "Today's Hottest TV Shows (Available on {$platform_name})";
                    default:
                        return "What Everyone's Watching {$time_label} (On {$platform_name})";
                }
        }
    }
    
    /**
     * Create trending content blocks
     */
    private function create_trending_content_blocks($trending_content, $platform_name, $content_type, $time_window, $trend_type) {
        $content_blocks = array();
        
        // Introduction
        $intro = $this->create_trending_intro($platform_name, $content_type, $time_window, $trend_type);
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $intro
        );
        
        // Trending list
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => 'What\'s Trending Right Now'
        );
        
        $trending_items = array();
        foreach ($trending_content as $index => $item) {
            $rank = $index + 1;
            $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
            $type = $item['media_type'] === 'movie' ? 'Movie' : 'TV Series';
            $popularity = isset($item['popularity']) ? round($item['popularity'], 1) : 'N/A';
            
            $description = "";
            if (!empty($item['overview'])) {
                $description = " - " . wp_trim_words($item['overview'], 15);
            }
            
            $trending_items[] = "<strong>#{$rank} {$title}</strong> ({$type}) - Trending Score: {$popularity}{$description}";
        }
        
        $content_blocks[] = array(
            'type' => 'list',
            'items' => $trending_items
        );
        
        // Spotlight on top 3
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => 'Top Trending Highlights'
        );
        
        for ($i = 0; $i < min(3, count($trending_content)); $i++) {
            $item = $trending_content[$i];
            $rank = $i + 1;
            $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
            
            $content_blocks[] = array(
                'type' => 'heading',
                'level' => 3,
                'content' => "#{$rank} {$title}"
            );
            
            if (!empty($item['overview'])) {
                $content_blocks[] = array(
                    'type' => 'paragraph',
                    'content' => $item['overview']
                );
            }
            
            // Add trending context
            $trending_context = $this->create_trending_context($item, $rank);
            $content_blocks[] = array(
                'type' => 'paragraph',
                'content' => $trending_context
            );
        }
        
        // Analysis section
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => 'Trending Analysis'
        );
        
        $analysis = $this->create_trending_analysis($trending_content, $platform_name, $content_type, $trend_type);
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $analysis
        );
        
        // Call to action
        $cta = "Stay ahead of the curve by watching these trending titles on <strong>{$platform_name}</strong>. ";
        $cta .= "Trending content changes rapidly, so these recommendations represent the current moment in streaming culture. ";
        $cta .= "Whether you want to join the conversation or discover your next favorite ";
        $cta .= ($content_type === 'movies' ? 'film' : ($content_type === 'tv' ? 'series' : 'show'));
        $cta .= ", these trending selections offer the perfect starting point.";
        
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $cta
        );
        
        return $content_blocks;
    }
    
    /**
     * Create trending introduction
     */
    private function create_trending_intro($platform_name, $content_type, $time_window, $trend_type) {
        $time_label = $time_window === 'day' ? 'today' : 'this week';
        $content_label = $content_type === 'movies' ? 'movies' : ($content_type === 'tv' ? 'TV shows' : 'content');
        
        switch ($trend_type) {
            case 'platform':
                return "The streaming landscape on <strong>{$platform_name}</strong> is constantly evolving, and {$time_label} brings " .
                       "a fresh wave of trending {$content_label}. These selections represent what subscribers are watching most, " .
                       "talking about, and recommending to others right now.";
                       
            case 'rising':
                return "Some content takes time to find its audience, building momentum through word-of-mouth and social media buzz. " .
                       "These rising stars on <strong>{$platform_name}</strong> are experiencing a surge in popularity, " .
                       "representing the next wave of must-watch entertainment.";
                       
            default: // 'general'
                return "The global conversation around streaming {$content_label} is happening right now, and <strong>{$platform_name}</strong> " .
                       "is home to many of the titles driving that discussion. These trending selections reflect what's capturing " .
                       "audiences worldwide {$time_label}.";
        }
    }
    
    /**
     * Create trending context for individual items
     */
    private function create_trending_context($item, $rank) {
        $context = array();
        
        // Add trending momentum
        if (isset($item['trending_score'])) {
            $score = round($item['trending_score'], 1);
            $context[] = "Trending Score: {$score}/10";
        }
        
        // Add popularity context
        if (isset($item['popularity'])) {
            $popularity = round($item['popularity'], 1);
            $context[] = "Popularity: {$popularity}";
        }
        
        // Add rating context
        if (isset($item['vote_average']) && isset($item['vote_count'])) {
            $rating = round($item['vote_average'], 1);
            $votes = number_format($item['vote_count']);
            $context[] = "Rating: {$rating}/10 ({$votes} votes)";
        }
        
        // Add release context
        if ($item['media_type'] === 'movie' && isset($item['release_date'])) {
            $release_date = date('F j, Y', strtotime($item['release_date']));
            $context[] = "Released: {$release_date}";
        } elseif ($item['media_type'] === 'tv' && isset($item['first_air_date'])) {
            $air_date = date('F j, Y', strtotime($item['first_air_date']));
            $context[] = "First Aired: {$air_date}";
        }
        
        // Add genre context
        if (isset($item['genre_ids']) && !empty($item['genre_ids'])) {
            $genre_names = array();
            foreach ($item['genre_ids'] as $genre_id) {
                $genre_name = $this->get_genre_name($genre_id, $item['media_type']);
                if ($genre_name) {
                    $genre_names[] = $genre_name;
                }
            }
            if (!empty($genre_names)) {
                $context[] = "Genres: " . implode(', ', $genre_names);
            }
        }
        
        return implode(' | ', $context);
    }
    
    /**
     * Create trending analysis
     */
    private function create_trending_analysis($trending_content, $platform_name, $content_type, $trend_type) {
        $analysis = array();
        
        // Calculate average rating
        $total_rating = 0;
        $total_votes = 0;
        $genre_counts = array();
        $media_type_counts = array('movie' => 0, 'tv' => 0);
        
        foreach ($trending_content as $item) {
            if (isset($item['vote_average']) && isset($item['vote_count'])) {
                $total_rating += $item['vote_average'] * $item['vote_count'];
                $total_votes += $item['vote_count'];
            }
            
            $media_type_counts[$item['media_type']]++;
            
            if (isset($item['genre_ids'])) {
                foreach ($item['genre_ids'] as $genre_id) {
                    $genre_name = $this->get_genre_name($genre_id, $item['media_type']);
                    if ($genre_name) {
                        $genre_counts[$genre_name] = ($genre_counts[$genre_name] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Build analysis text
        $analysis[] = "Our analysis of the current trending content on {$platform_name} reveals:";
        
        // Add rating analysis
        if ($total_votes > 0) {
            $avg_rating = round($total_rating / $total_votes, 1);
            $analysis[] = "• Average rating: {$avg_rating}/10";
        }
        
        // Add content type analysis
        $content_breakdown = array();
        if ($media_type_counts['movie'] > 0) {
            $content_breakdown[] = "{$media_type_counts['movie']} movies";
        }
        if ($media_type_counts['tv'] > 0) {
            $content_breakdown[] = "{$media_type_counts['tv']} TV shows";
        }
        if (!empty($content_breakdown)) {
            $analysis[] = "• Content mix: " . implode(' and ', $content_breakdown);
        }
        
        // Add genre analysis
        if (!empty($genre_counts)) {
            arsort($genre_counts);
            $top_genres = array_slice(array_keys($genre_counts), 0, 3);
            $analysis[] = "• Top genres: " . implode(', ', $top_genres);
        }
        
        // Add trend type specific analysis
        switch ($trend_type) {
            case 'platform':
                $analysis[] = "• These titles are currently driving the most engagement on {$platform_name}";
                break;
            case 'rising':
                $analysis[] = "• These titles are experiencing significant growth in popularity";
                break;
            default: // 'general'
                $analysis[] = "• These titles are trending across multiple streaming platforms";
        }
        
        return implode("\n", $analysis);
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
     * Create trending tags
     */
    private function create_trending_tags($platform_name, $content_type, $time_window, $trend_type) {
        $tags = array(
            $platform_name,
            'trending',
            'popular',
            'streaming'
        );
        
        switch ($trend_type) {
            case 'platform':
                $tags[] = 'platform trending';
                break;
            case 'rising':
                $tags[] = 'rising stars';
                $tags[] = 'breaking content';
                break;
            default:
                $tags[] = 'global trending';
        }
        
        if ($content_type === 'movies') {
            $tags[] = 'movies';
            $tags[] = 'trending movies';
        } elseif ($content_type === 'tv') {
            $tags[] = 'tv shows';
            $tags[] = 'trending tv';
        } else {
            $tags[] = 'movies';
            $tags[] = 'tv shows';
        }
        
        $tags[] = $time_window === 'day' ? 'daily trends' : 'weekly trends';
        
        return array_map('sanitize_text_field', $tags);
    }
    
    /**
     * Set featured image for trending post
     */
    private function set_trending_featured_image($post_id, $top_trending_item) {
        $content_data = array(
            'title' => $top_trending_item['media_type'] === 'movie' ? $top_trending_item['title'] : $top_trending_item['name'],
            'backdrop_url' => !empty($top_trending_item['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $top_trending_item['backdrop_path'] : '',
            'poster_url' => !empty($top_trending_item['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $top_trending_item['poster_path'] : ''
        );
        
        return $this->set_featured_image_with_landscape_priority($post_id, $content_data, $content_data['title']);
    }
    
    /**
     * Add trending specific metadata
     */
    private function add_trending_metadata($post_id, $content_type, $time_window, $trend_type, $item_count) {
        update_post_meta($post_id, 'trending_type', $trend_type);
        update_post_meta($post_id, 'content_type', $content_type);
        update_post_meta($post_id, 'time_window', $time_window);
        update_post_meta($post_id, 'item_count', $item_count);
        update_post_meta($post_id, 'trending_date', current_time('mysql'));
        update_post_meta($post_id, 'generation_timestamp', time());
    }
}