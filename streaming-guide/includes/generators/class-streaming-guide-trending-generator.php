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
                $this->set_trending_featured_image($post_id, $trending_content[0]);
                
                // Add trending specific metadata
                $this->add_trending_metadata($post_id, $content_type, $time_window, $trend_type, count($trending_content));
                
                $this->log_info("Successfully created trending post: {$title} (ID: {$post_id})");
                return $post_id;
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
        $trending = $this->tmdb->get_trending_movies($time_window);
        
        if (is_wp_error($trending) || !isset($trending['results'])) {
            return array();
        }
        
        return array_map(function($movie) {
            $movie['media_type'] = 'movie';
            return $movie;
        }, $trending['results']);
    }
    
    /**
     * Get trending TV shows from TMDB
     */
    private function get_trending_tv($time_window) {
        $trending = $this->tmdb->get_trending_tv($time_window);
        
        if (is_wp_error($trending) || !isset($trending['results'])) {
            return array();
        }
        
        return array_map(function($show) {
            $show['media_type'] = 'tv';
            return $show;
        }, $trending['results']);
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
        $title = $item['media_type'] === 'movie' ? $item['title'] : $item['name'];
        $type = $item['media_type'] === 'movie' ? 'film' : 'series';
        
        $context = "Currently ranking #{$rank} in trending, '<strong>{$title}</strong>' ";
        
        if (isset($item['vote_average']) && $item['vote_average'] > 7.0) {
            $rating = round($item['vote_average'], 1);
            $context .= "combines popularity with quality, earning a {$rating}/10 rating. ";
        }
        
        if (isset($item['popularity'])) {
            $popularity = round($item['popularity'], 0);
            if ($popularity > 100) {
                $context .= "Its exceptional trending score of {$popularity} indicates massive audience engagement. ";
            } elseif ($popularity > 50) {
                $context .= "With a strong trending score of {$popularity}, it's clearly resonating with viewers. ";
            }
        }
        
        $context .= "This {$type} represents exactly the kind of content that defines current streaming culture.";
        
        return $context;
    }
    
    /**
     * Create trending analysis
     */
    private function create_trending_analysis($trending_content, $platform_name, $content_type, $trend_type) {
        $total_items = count($trending_content);
        $movies_count = count(array_filter($trending_content, function($item) { return $item['media_type'] === 'movie'; }));
        $tv_count = $total_items - $movies_count;
        
        $analysis = "This trending analysis reveals interesting patterns in current viewing preferences. ";
        
        if ($content_type === 'mixed') {
            if ($movies_count > $tv_count) {
                $analysis .= "Movies are dominating the trending landscape with {$movies_count} out of {$total_items} spots, ";
                $analysis .= "suggesting audiences are currently favoring complete, contained narratives. ";
            } elseif ($tv_count > $movies_count) {
                $analysis .= "TV series are leading the trending conversation with {$tv_count} out of {$total_items} positions, ";
                $analysis .= "indicating a preference for ongoing, episodic storytelling. ";
            } else {
                $analysis .= "There's a perfect balance between movies and TV shows in the trending list, ";
                $analysis .= "showing diverse viewing preferences across {$platform_name} subscribers. ";
            }
        }
        
        // Analyze popularity scores
        $high_popularity = array_filter($trending_content, function($item) { 
            return isset($item['popularity']) && $item['popularity'] > 100; 
        });
        
        if (count($high_popularity) > 0) {
            $analysis .= "The presence of " . count($high_popularity) . " ultra-high trending titles ";
            $analysis .= "indicates some content is achieving viral-level popularity. ";
        }
        
        $analysis .= "These trending patterns on {$platform_name} reflect broader entertainment industry shifts ";
        $analysis .= "and provide valuable insights into what resonates with modern streaming audiences.";
        
        return $analysis;
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