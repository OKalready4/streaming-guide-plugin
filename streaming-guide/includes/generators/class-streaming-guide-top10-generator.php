<?php
/**
 * Top 10 Generator for Streaming Guide
 * 
 * Properly inherits from base class with correct method signatures and access levels
 * UPDATED: Replaced old logging with new structured logging methods.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Top10_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Generate top 10 content
     * Must match exact signature: generate($platform, $param1 = null, $param2 = null, $param3 = null)
     */
    public function generate($platform, $param1 = null, $param2 = null, $param3 = null) {
        // Map parameters to meaningful names
        $content_type = $param1 ?? 'mixed'; // 'movies', 'tv', or 'mixed'
        $time_period = $param2 ?? 'month'; // 'week', 'month', 'year'
        $custom_title = $param3 ?? null;

        $params = [
            'content_type' => $content_type,
            'time_period' => $time_period,
            'custom_title' => $custom_title
        ];
        
        try {
            $this->log_info("Starting top 10 generation for {$platform}", $params);
            
            // Get platform configuration
            $platform_name = $this->get_platform_name($platform);
            $provider_id = $this->get_provider_id($platform);
            
            if (!$platform_name || !$provider_id) {
                $this->log_generation_failure('top_10', $platform, 'Invalid platform configuration', $params);
                return false;
            }
            
            // Get top content for the platform
            $top_content = $this->get_top_content($provider_id, $content_type, $time_period);
            
            if (empty($top_content)) {
                $this->log_generation_failure('top_10', $platform, 'No top content found', $params);
                return false;
            }
            
            // Create the article
            $title = $this->create_top10_title($platform_name, $content_type, $time_period, $custom_title);
            $content_blocks = $this->create_top10_content_blocks($top_content, $platform_name, $content_type, $time_period);
            $tags = $this->create_top10_tags($platform_name, $content_type, $time_period);
            
            // Create post using parent method with correct signature
            $post_id = $this->create_post($title, $content_blocks, $platform, $tags, 'top_10');
            
            if ($post_id) {
                // Set featured image using the first item's backdrop
                $this->set_top10_featured_image($post_id, $top_content[0]);
                
                // Add top 10 specific metadata
                $this->add_top10_metadata($post_id, $content_type, $time_period, count($top_content));
                
                $this->log_info("Successfully created top 10 post: {$title} (ID: {$post_id})");
                return $post_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log_generation_failure('top_10', $platform, 'Top 10 generator error: ' . $e->getMessage(), $params);
            return false;
        }
    }
    
    /**
     * Get top content for the platform
     */
    private function get_top_content($provider_id, $content_type, $time_period) {
        try {
            // Define date range based on time period
            $date_range = $this->get_date_range($time_period);
            
            $all_content = array();
            
            if ($content_type === 'movies' || $content_type === 'mixed') {
                $movies = $this->get_top_movies($provider_id, $date_range);
                $all_content = array_merge($all_content, $movies);
            }
            
            if ($content_type === 'tv' || $content_type === 'mixed') {
                $tv_shows = $this->get_top_tv_shows($provider_id, $date_range);
                $all_content = array_merge($all_content, $tv_shows);
            }
            
            // Sort by popularity and take top 10
            usort($all_content, function($a, $b) {
                $score_a = $this->calculate_content_score($a);
                $score_b = $this->calculate_content_score($b);
                return $score_b - $score_a;
            });
            
            return array_slice($all_content, 0, 10);
            
        } catch (Exception $e) {
            $this->log_error('Error getting top content', [
                'provider_id' => $provider_id,
                'content_type' => $content_type,
                'time_period' => $time_period,
                'error' => $e->getMessage()
            ]);
            return array();
        }
    }
    
    /**
     * Get date range for time period
     */
    private function get_date_range($time_period) {
        $today = new DateTime();
        $start_date = clone $today;
        
        switch ($time_period) {
            case 'week':
                $start_date->sub(new DateInterval('P7D'));
                break;
            case 'year':
                $start_date->sub(new DateInterval('P1Y'));
                break;
            default: // month
                $start_date->sub(new DateInterval('P1M'));
                break;
        }
        
        return array(
            'start' => $start_date->format('Y-m-d'),
            'end' => $today->format('Y-m-d')
        );
    }
    
    /**
     * Get top movies for provider
     */
    private function get_top_movies($provider_id, $date_range) {
        $movies = $this->tmdb->discover_movies(array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'primary_release_date.gte' => $date_range['start'],
            'primary_release_date.lte' => $date_range['end'],
            'sort_by' => 'popularity.desc',
            'vote_count.gte' => 50,
            'page' => 1
        ));
        
        if (is_wp_error($movies) || !isset($movies['results'])) {
            return array();
        }
        
        return array_map(function($movie) {
            $movie['media_type'] = 'movie';
            return $movie;
        }, array_slice($movies['results'], 0, 15)); // Get extra to filter later
    }
    
    /**
     * Get top TV shows for provider
     */
    private function get_top_tv_shows($provider_id, $date_range) {
        $tv_shows = $this->tmdb->discover_tv(array(
            'with_watch_providers' => $provider_id,
            'watch_region' => 'US',
            'first_air_date.gte' => $date_range['start'],
            'first_air_date.lte' => $date_range['end'],
            'sort_by' => 'popularity.desc',
            'vote_count.gte' => 30,
            'page' => 1
        ));
        
        if (is_wp_error($tv_shows) || !isset($tv_shows['results'])) {
            return array();
        }
        
        return array_map(function($show) {
            $show['media_type'] = 'tv';
            return $show;
        }, array_slice($tv_shows['results'], 0, 15)); // Get extra to filter later
    }
    
    /**
     * Calculate content score for ranking
     */
    private function calculate_content_score($item) {
        $popularity = $item['popularity'] ?? 0;
        $vote_average = $item['vote_average'] ?? 0;
        $vote_count = $item['vote_count'] ?? 0;
        
        // Weighted scoring system
        $score = ($popularity * 0.4) + ($vote_average * $vote_count * 0.6);
        
        return $score;
    }
    
    /**
     * Create top 10 title
     */
    private function create_top10_title($platform_name, $content_type, $time_period, $custom_title = null) {
        if ($custom_title) {
            return $custom_title;
        }
        
        $time_label = ucfirst($time_period);
        
        switch ($content_type) {
            case 'movies':
                return "Top 10 Movies This {$time_label} on {$platform_name}";
            case 'tv':
                return "Top 10 TV Shows This {$time_label} on {$platform_name}";
            default:
                return "Top 10 Must-Watch Content This {$time_label} on {$platform_name}";
        }
    }
    
    /**
     * Create top 10 content blocks
     */
    private function create_top10_content_blocks($top_content, $platform_name, $content_type, $time_period) {
        $content_blocks = array();
        
        // Introduction
        $type_label = $content_type === 'movies' ? 'movies' : ($content_type === 'tv' ? 'TV shows' : 'content');
        $time_label = strtolower($time_period);
        
        $intro = "Discover the most popular {$type_label} on <strong>{$platform_name}</strong> this {$time_label}. ";
        $intro .= "Our curated list showcases the top-performing content based on popularity, ratings, and viewer engagement. ";
        $intro .= "Whether you're looking for your next binge-watch or a weekend movie night selection, ";
        $intro .= "these trending titles represent the best {$platform_name} has to offer right now.";
        
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $intro
        );
        
        // Top 10 list
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => "The Top 10 List"
        );
        
        $list_items = array();
        foreach ($top_content as $index => $item) {
            $rank = $index + 1;
            $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
            $type = $item['media_type'] === 'movie' ? 'Movie' : 'TV Series';
            $rating = isset($item['vote_average']) ? round($item['vote_average'], 1) : 'N/A';
            
            $description = "";
            if (!empty($item['overview'])) {
                $description = " - " . wp_trim_words($item['overview'], 20);
            }
            
            $list_items[] = "<strong>#{$rank} {$title}</strong> ({$type}) - Rating: {$rating}/10{$description}";
        }
        
        $content_blocks[] = array(
            'type' => 'list',
            'items' => $list_items
        );
        
        // Detailed highlights for top 3
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => 'Top 3 Highlights'
        );
        
        for ($i = 0; $i < min(3, count($top_content)); $i++) {
            $item = $top_content[$i];
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
            
            // Add genres and additional info
            $meta_info = array();
            if (isset($item['genre_ids']) && !empty($item['genre_ids'])) {
                // You'd need to map genre IDs to names here
                $meta_info[] = "Genre: Multiple genres";
            }
            
            if ($item['media_type'] === 'movie' && isset($item['release_date'])) {
                $year = date('Y', strtotime($item['release_date']));
                $meta_info[] = "Released: {$year}";
            } elseif ($item['media_type'] === 'tv' && isset($item['first_air_date'])) {
                $year = date('Y', strtotime($item['first_air_date']));
                $meta_info[] = "First aired: {$year}";
            }
            
            if (!empty($meta_info)) {
                $content_blocks[] = array(
                    'type' => 'paragraph',
                    'content' => implode(' | ', $meta_info)
                );
            }
        }
        
        // Conclusion
        $conclusion = "This top 10 list represents the current trending landscape on {$platform_name}. ";
        $conclusion .= "These selections are updated regularly based on viewing patterns, ratings, and overall popularity. ";
        $conclusion .= "Each title offers something unique, ensuring there's something for every viewer's taste and mood.";
        
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $conclusion
        );
        
        return $content_blocks;
    }
    
    /**
     * Create top 10 tags
     */
    private function create_top10_tags($platform_name, $content_type, $time_period) {
        $tags = array(
            $platform_name,
            'top 10',
            'best of',
            'trending',
            'streaming'
        );
        
        if ($content_type === 'movies') {
            $tags[] = 'movies';
            $tags[] = 'best movies';
        } elseif ($content_type === 'tv') {
            $tags[] = 'tv shows';
            $tags[] = 'best series';
        } else {
            $tags[] = 'movies';
            $tags[] = 'tv shows';
        }
        
        $tags[] = "this {$time_period}";
        
        return array_map('sanitize_text_field', $tags);
    }
    
    /**
     * Set featured image for top 10 post
     */
    private function set_top10_featured_image($post_id, $featured_item) {
        $content_data = array(
            'title' => $featured_item['media_type'] === 'movie' ? $featured_item['title'] : $featured_item['name'],
            'backdrop_url' => !empty($featured_item['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $featured_item['backdrop_path'] : '',
            'poster_url' => !empty($featured_item['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $featured_item['poster_path'] : ''
        );
        
        return $this->set_featured_image_with_landscape_priority($post_id, $content_data, $content_data['title']);
    }
    
    /**
     * Add top 10 specific metadata
     */
    private function add_top10_metadata($post_id, $content_type, $time_period, $item_count) {
        update_post_meta($post_id, 'list_type', 'top_10');
        update_post_meta($post_id, 'content_type', $content_type);
        update_post_meta($post_id, 'time_period', $time_period);
        update_post_meta($post_id, 'item_count', $item_count);
        update_post_meta($post_id, 'generation_week', date('Y-W'));
    }
}