<?php
/**
 * COMPLETELY FIXED Monthly Generator for Streaming Guide
 * 
 * This version properly inherits from the base class and eliminates all access level conflicts
 * UPDATED: Replaced old logging with new structured logging methods.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Monthly_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Generate a monthly content roundup
     */
    public function generate($platform, $param1 = null, $param2 = null, $param3 = null) {
        // Extract month and year from parameters
        $target_month = !empty($param1) ? absint($param1) : date('n'); // 1-12
        $target_year = !empty($param2) ? absint($param2) : date('Y');

        try {
            $this->log_info("Generating monthly article for {$platform} (Month: {$target_month}, Year: {$target_year})");

            // Validate platform
            if (!$this->validate_platform($platform)) {
                $this->log_generation_failure('monthly', $platform, 'Invalid platform');
                return false;
            }

            // Get platform configuration
            $platform_config = $this->get_platform_config($platform);
            if (!$platform_config) {
                $this->log_generation_failure('monthly', $platform, 'Invalid platform configuration');
                return false;
            }

            $provider_id = $platform_config['provider_id'];
            $platform_name = $platform_config['name'];

            // Handle all platforms case
            if ($platform === 'all') {
                return $this->generate_for_all_platforms($target_month, $target_year);
            }

            // Get monthly content analysis
            $monthly_content = $this->get_monthly_content_analysis($provider_id, $platform_name, $target_month, $target_year);
            
            if (empty($monthly_content) || empty($monthly_content['trending_content'])) {
                $this->log_generation_failure('monthly', $platform, 'No suitable content found for monthly analysis', ['month' => $target_month, 'year' => $target_year]);
                return false;
            }

            // Create article using base class methods
            $title = $this->create_monthly_title($platform_name, $target_month, $target_year);
            $content_blocks = $this->create_monthly_content_blocks($monthly_content, $platform_name, $target_month, $target_year);
            $tags = $this->create_monthly_tags($platform_name, $target_month, $target_year);
            
            // Create post using parent method with correct signature
            $post_id = $this->create_post($title, $content_blocks, $platform, $tags, 'monthly_roundup');
            
            if ($post_id) {
                // Set featured image using base class method
                $this->set_featured_image_for_monthly($post_id, $monthly_content);
                
                // Add monthly-specific metadata
                $this->add_monthly_metadata($post_id, $monthly_content, $platform_name, $target_month, $target_year);
                
                $this->log_info("Successfully created monthly article: {$title} (ID: {$post_id})");
                return $post_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log_generation_failure('monthly', $platform, 'Monthly generator error: ' . $e->getMessage(), [
                'month' => $target_month, 
                'year' => $target_year
            ]);
            return false;
        }
    }
    
    /**
     * Generate content for all platforms
     */
    private function generate_for_all_platforms($target_month, $target_year) {
        $platforms = $this->platforms->get_platforms();
        $generated = 0;
        $failed = 0;
        
        foreach ($platforms as $platform_key => $platform_data) {
            try {
                $post_id = $this->generate($platform_key, $target_month, $target_year);
                if ($post_id) {
                    $generated++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                $failed++;
                $this->log_error("Failed to generate for platform {$platform_key}", [
                    'error' => $e->getMessage(),
                    'month' => $target_month,
                    'year' => $target_year
                ]);
            }
        }
        
        $this->log_info("Completed all-platforms generation", [
            'generated' => $generated,
            'failed' => $failed,
            'month' => $target_month,
            'year' => $target_year
        ]);
        
        return $generated > 0;
    }
    
    /**
     * Get comprehensive monthly content analysis
     */
    private function get_monthly_content_analysis($provider_id, $platform_name, $target_month, $target_year) {
        // Calculate date range for the month
        $start_date = sprintf('%04d-%02d-01', $target_year, $target_month);
        $end_date = date('Y-m-t', strtotime($start_date)); // Last day of month

        $analysis = array(
            'new_releases' => array(),
            'featured_items' => array(),
            'trending_content' => array(),
            'hidden_gems' => array(),
            'month_stats' => array()
        );

        try {
            // Get trending content that's available on the platform
            $trending_movies = $this->tmdb->get_trending_movies('week');
            $trending_tv = $this->tmdb->get_trending_tv('week');
            
            if (!is_wp_error($trending_movies) && !is_wp_error($trending_tv)) {
                // Filter for platform availability
                $platform_movies = $this->filter_content_by_provider(
                    $trending_movies['results'] ?? array(), 
                    $provider_id, 
                    'movie'
                );
                
                $platform_tv = $this->filter_content_by_provider(
                    $trending_tv['results'] ?? array(), 
                    $provider_id, 
                    'tv'
                );
                
                // Process and categorize content
                $this->process_trending_content($analysis, $platform_movies, $platform_tv);
                $this->find_featured_items($analysis);
                $this->find_hidden_gems($analysis);
                $this->calculate_monthly_stats($analysis);
            }
            
        } catch (Exception $e) {
            $this->log_error('Error analyzing monthly content', [
                'platform' => $platform_name,
                'month' => $target_month,
                'year' => $target_year,
                'error' => $e->getMessage()
            ]);
        }

        return $analysis;
    }
    
    /**
     * Filter content by platform availability
     */
    private function filter_content_by_provider($content, $provider_id, $media_type) {
        $filtered = array();
        
        foreach ($content as $item) {
            try {
                // Check if content is streaming worthy
                if (!$this->is_streaming_worthy($item, $media_type)) {
                    continue;
                }
                
                // Get watch providers
                $providers = $this->tmdb->get_watch_providers($item['id'], $media_type);
                
                if (is_wp_error($providers)) {
                    continue;
                }
                
                $us_providers = $providers['results']['US'] ?? array();
                $flatrate_providers = $us_providers['flatrate'] ?? array();
                
                // Check if our target provider is available
                foreach ($flatrate_providers as $provider) {
                    if ($provider['provider_id'] == $provider_id) {
                        $item['media_type'] = $media_type;
                        $filtered[] = $item;
                        break;
                    }
                }
                
            } catch (Exception $e) {
                $this->log_error('Error filtering content item', [
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
     * Process trending content for analysis
     */
    private function process_trending_content(&$analysis, $movies, $tv_shows) {
        $all_content = array_merge($movies, $tv_shows);
        
        // Sort by popularity
        usort($all_content, function($a, $b) {
            return ($b['popularity'] ?? 0) - ($a['popularity'] ?? 0);
        });
        
        foreach ($all_content as $item) {
            $detailed_item = $this->get_detailed_content_info($item);
            if ($detailed_item) {
                $analysis['trending_content'][] = $detailed_item;
            }
        }
    }
    
    /**
     * Get detailed content information
     */
    private function get_detailed_content_info($item) {
        try {
            $media_type = $item['media_type'];
            $details = $media_type === 'movie' ? 
                $this->tmdb->get_movie_details($item['id']) : 
                $this->tmdb->get_tv_details($item['id']);
            
            if (is_wp_error($details)) {
                return null;
            }
            
            return array(
                'id' => $item['id'],
                'title' => $media_type === 'movie' ? ($item['title'] ?? '') : ($item['name'] ?? ''),
                'overview' => $item['overview'] ?? '',
                'poster_url' => !empty($item['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path'] : '',
                'backdrop_url' => !empty($item['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $item['backdrop_path'] : '',
                'rating' => $item['vote_average'] ?? 0,
                'popularity' => $item['popularity'] ?? 0,
                'release_date' => $media_type === 'movie' ? ($item['release_date'] ?? '') : ($item['first_air_date'] ?? ''),
                'media_type' => $media_type,
                'genres' => isset($details['genres']) ? array_map(function($g) { return $g['name']; }, $details['genres']) : array(),
                'runtime' => $media_type === 'movie' ? ($details['runtime'] ?? null) : null,
                'seasons' => $media_type === 'tv' ? ($details['number_of_seasons'] ?? null) : null
            );
            
        } catch (Exception $e) {
            $this->log_error('Error getting detailed content info', [
                'item_id' => $item['id'] ?? 'N/A',
                'media_type' => $item['media_type'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find featured items (top content)
     */
    private function find_featured_items(&$analysis) {
        if (empty($analysis['trending_content'])) {
            return;
        }
        
        // Get top 5 highest-rated items with good popularity
        $featured_candidates = array_filter($analysis['trending_content'], function($item) {
            return ($item['rating'] ?? 0) >= 6.5 && ($item['popularity'] ?? 0) >= 20;
        });
        
        // Sort by rating then popularity
        usort($featured_candidates, function($a, $b) {
            $rating_diff = ($b['rating'] ?? 0) - ($a['rating'] ?? 0);
            if (abs($rating_diff) < 0.1) {
                return ($b['popularity'] ?? 0) - ($a['popularity'] ?? 0);
            }
            return $rating_diff;
        });
        
        $analysis['featured_items'] = array_slice($featured_candidates, 0, 5);
    }
    
    /**
     * Find hidden gems (good ratings, lower popularity)
     */
    private function find_hidden_gems(&$analysis) {
        if (empty($analysis['trending_content'])) {
            return;
        }
        
        $gems = array_filter($analysis['trending_content'], function($item) {
            return ($item['rating'] ?? 0) >= 7.0 && 
                   ($item['popularity'] ?? 0) < 50 && 
                   ($item['popularity'] ?? 0) > 10;
        });
        
        // Sort by rating
        usort($gems, function($a, $b) {
            return ($b['rating'] ?? 0) - ($a['rating'] ?? 0);
        });
        
        $analysis['hidden_gems'] = array_slice($gems, 0, 3);
    }
    
    /**
     * Calculate monthly statistics
     */
    private function calculate_monthly_stats(&$analysis) {
        $stats = array(
            'total_content' => count($analysis['trending_content']),
            'featured_count' => count($analysis['featured_items']),
            'hidden_gems_count' => count($analysis['hidden_gems']),
            'avg_rating' => 0,
            'top_genres' => array()
        );
        
        if ($stats['total_content'] > 0) {
            $total_rating = array_sum(array_map(function($item) {
                return $item['rating'] ?? 0;
            }, $analysis['trending_content']));
            
            $stats['avg_rating'] = round($total_rating / $stats['total_content'], 1);
            
            // Count genres
            $genre_counts = array();
            foreach ($analysis['trending_content'] as $item) {
                foreach ($item['genres'] ?? array() as $genre) {
                    $genre_counts[$genre] = ($genre_counts[$genre] ?? 0) + 1;
                }
            }
            
            arsort($genre_counts);
            $stats['top_genres'] = array_slice(array_keys($genre_counts), 0, 3);
        }
        
        $analysis['month_stats'] = $stats;
    }
    
    /**
     * Create monthly content blocks
     */
    private function create_monthly_content_blocks($monthly_content, $platform_name, $target_month, $target_year) {
        $month_name = date('F', mktime(0, 0, 0, $target_month, 1));
        $is_current_month = ($target_month == date('n') && $target_year == date('Y'));
        $month_text = $is_current_month ? 'this month' : "{$month_name} {$target_year}";
        
        $content_blocks = array();
        
        // Hero image from top featured item
        $hero_item = !empty($monthly_content['featured_items']) ? $monthly_content['featured_items'][0] : null;
        if ($hero_item && !empty($hero_item['backdrop_url'])) {
            $content_blocks[] = array(
                'type' => 'image',
                'url' => $hero_item['backdrop_url'],
                'alt' => $hero_item['title'] . ' - Monthly Highlight',
                'caption' => "Highlighting the best of {$platform_name} in {$month_name}",
                'alignment' => 'center'
            );
        }

        // Opening paragraph
        $stats = $monthly_content['month_stats'];
        $opening = "<strong>Your complete {$platform_name} guide for {$month_name} {$target_year}</strong> breaks down everything worth watching {$month_text}. ";
        $opening .= "We've analyzed the platform's trending content, standout performers, and hidden treasures to bring you a comprehensive monthly overview. ";
        
        if ($stats['total_content'] > 0) {
            $opening .= "With {$stats['total_content']} quality titles analyzed and our top featured picks, there's plenty to keep your queue full.";
        } else {
            $opening .= "While content was limited, we've uncovered excellent titles that deserve your attention.";
        }

        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $opening
        );

        // Featured Content Section
        if (!empty($monthly_content['featured_items'])) {
            $content_blocks[] = array(
                'type' => 'heading',
                'level' => 2,
                'content' => 'Featured Content'
            );
            
            foreach ($monthly_content['featured_items'] as $item) {
                $content_blocks[] = array(
                    'type' => 'heading',
                    'level' => 3,
                    'content' => $item['title']
                );
                
                if (!empty($item['overview'])) {
                    $content_blocks[] = array(
                        'type' => 'paragraph',
                        'content' => $item['overview']
                    );
                }
                
                // Add metadata
                $meta_info = array();
                if (!empty($item['rating'])) {
                    $meta_info[] = "Rating: " . number_format($item['rating'], 1) . "/10";
                }
                if (!empty($item['genres'])) {
                    $meta_info[] = "Genres: " . implode(', ', $item['genres']);
                }
                if (!empty($item['release_date'])) {
                    $meta_info[] = "Released: " . date('F j, Y', strtotime($item['release_date']));
                }
                
                if (!empty($meta_info)) {
                    $content_blocks[] = array(
                        'type' => 'paragraph',
                        'content' => implode(' | ', $meta_info)
                    );
                }
            }
        }

        // Hidden Gems Section
        if (!empty($monthly_content['hidden_gems'])) {
            $content_blocks[] = array(
                'type' => 'heading',
                'level' => 2,
                'content' => 'Hidden Gems'
            );
            
            $gems_list = array();
            foreach ($monthly_content['hidden_gems'] as $gem) {
                $gems_list[] = "<strong>{$gem['title']}</strong> - " . wp_trim_words($gem['overview'], 20);
            }
            
            $content_blocks[] = array(
                'type' => 'list',
                'items' => $gems_list
            );
        }

        // Monthly Stats
        $content_blocks[] = array(
            'type' => 'heading',
            'level' => 2,
            'content' => 'Monthly Statistics'
        );
        
        $stats_text = "This month on {$platform_name}, we analyzed {$stats['total_content']} titles. ";
        $stats_text .= "The average rating across all content was {$stats['avg_rating']}/10. ";
        
        if (!empty($stats['top_genres'])) {
            $stats_text .= "The most popular genres were " . implode(', ', $stats['top_genres']) . ". ";
        }
        
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $stats_text
        );

        // Conclusion
        $conclusion = "As we wrap up our {$month_name} coverage of {$platform_name}, ";
        $conclusion .= "we hope this guide helps you discover your next favorite show or movie. ";
        $conclusion .= "Stay tuned for next month's roundup of the best streaming content.";
        
        $content_blocks[] = array(
            'type' => 'paragraph',
            'content' => $conclusion
        );
        
        return $content_blocks;
    }
    
    /**
     * Create monthly title
     */
    private function create_monthly_title($platform_name, $target_month, $target_year) {
        $month_name = date('F', mktime(0, 0, 0, $target_month, 1));
        $is_current_month = ($target_month == date('n') && $target_year == date('Y'));
        
        if ($is_current_month) {
            $title_templates = array(
                "{$platform_name} This Month: Complete Guide to {$month_name}'s Best Content",
                "What's Worth Watching on {$platform_name} in {$month_name}: Monthly Roundup",
                "{$month_name} on {$platform_name}: Top Picks, Trending Hits & Hidden Gems",
                "Your {$month_name} {$platform_name} Guide: Everything Worth Streaming This Month"
            );
        } else {
            $title_templates = array(
                "{$platform_name} {$month_name} {$target_year}: Complete Monthly Content Guide",
                "{$month_name} {$target_year} on {$platform_name}: The Complete Viewing Guide",
                "Best of {$platform_name}: {$month_name} {$target_year} Content Roundup",
                "{$platform_name} Monthly Review: {$month_name} {$target_year} Highlights"
            );
        }
        
        return $title_templates[array_rand($title_templates)];
    }
    
    /**
     * Create monthly tags
     */
    private function create_monthly_tags($platform_name, $target_month, $target_year) {
        $month_name = strtolower(date('F', mktime(0, 0, 0, $target_month, 1)));
        
        $tags = array(
            $platform_name,
            'monthly roundup',
            'streaming guide',
            $month_name,
            $target_year,
            'content guide',
            'what to watch'
        );
        
        return array_map('sanitize_text_field', $tags);
    }
    
    /**
     * Set featured image for monthly post
     */
    private function set_featured_image_for_monthly($post_id, $monthly_content) {
        if (!empty($monthly_content['featured_items'])) {
            $hero_item = $monthly_content['featured_items'][0];
            
            $content_data = array(
                'title' => $hero_item['title'] ?? 'Monthly Highlights',
                'backdrop_url' => $hero_item['backdrop_url'] ?? '',
                'poster_url' => $hero_item['poster_url'] ?? ''
            );
            
            $result = $this->set_featured_image_with_landscape_priority($post_id, $content_data, $content_data['title']);
            
            if (!$result && !empty($monthly_content['featured_items'])) {
                // Try other featured items
                foreach (array_slice($monthly_content['featured_items'], 1, 2) as $item) {
                    if (!empty($item['backdrop_url']) || !empty($item['poster_url'])) {
                        $fallback_data = array(
                            'title' => $item['title'] ?? 'Monthly Content',
                            'backdrop_url' => $item['backdrop_url'] ?? '',
                            'poster_url' => $item['poster_url'] ?? ''
                        );
                        
                        $result = $this->set_featured_image_with_landscape_priority($post_id, $fallback_data, $fallback_data['title']);
                        if ($result) {
                            break;
                        }
                    }
                }
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Add monthly-specific metadata
     */
    private function add_monthly_metadata($post_id, $monthly_content, $platform_name, $target_month, $target_year) {
        update_post_meta($post_id, 'target_month', $target_month);
        update_post_meta($post_id, 'target_year', $target_year);
        update_post_meta($post_id, 'content_analyzed', count($monthly_content['trending_content'] ?? array()));
        update_post_meta($post_id, 'featured_count', count($monthly_content['featured_items'] ?? array()));
        update_post_meta($post_id, 'hidden_gems_count', count($monthly_content['hidden_gems'] ?? array()));
        
        $stats = $monthly_content['month_stats'] ?? array();
        if (!empty($stats['avg_rating'])) {
            update_post_meta($post_id, 'avg_content_rating', $stats['avg_rating']);
        }
        
        if (!empty($stats['top_genres'])) {
            update_post_meta($post_id, 'top_genres', implode(',', $stats['top_genres']));
        }
    }
    
    /**
     * Get platform configuration
     */
    protected function get_platform_config($platform) {
        $platforms = Streaming_Guide_Platforms::get_platforms();
        return isset($platforms[$platform]) ? $platforms[$platform] : null;
    }
}