<?php
/**
 * COMPLETE Fixed Weekly Content Generator
 * 
 * This version is fully compatible with the base class, fixes structural errors, and uses new logging.
 * CORRECTED: Replaced all PHP 7.4+ arrow functions with classic anonymous functions for backward compatibility.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Weekly_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Main generate method - handles weekly highlight content
     */
    public function generate($platform, $param1 = null, $param2 = null, $param3 = null) {
        $this->log_info("Starting weekly highlight generation for {$platform}");
        
        if ($this->has_recent_weekly_content($platform)) {
            $this->log_info("Weekly content already exists for {$platform} today - preventing duplicate.", ['platform' => $platform]);
            return false;
        }
        
        $provider_id = $this->get_provider_id($platform);
        if (!$provider_id) {
            $this->log_generation_failure('weekly', $platform, "Invalid platform configuration");
            return false;
        }
        
        $platform_name = $this->get_platform_name($platform);
        
        // Define date range for new content
        $two_weeks_ago = date('Y-m-d', strtotime('-14 days'));
        $one_week_ahead = date('Y-m-d', strtotime('+7 days'));
        
        $movie_params = ['primary_release_date.gte' => $two_weeks_ago, 'primary_release_date.lte' => $one_week_ahead];
        $tv_params = ['first_air_date.gte' => $two_weeks_ago, 'first_air_date.lte' => $one_week_ahead];
        
        $movies = $this->tmdb->discover_movies(array_merge(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'sort_by' => 'popularity.desc', 'vote_count.gte' => 2], $movie_params));
        $tv_shows = $this->tmdb->discover_tv(array_merge(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'sort_by' => 'popularity.desc', 'vote_count.gte' => 2], $tv_params));
        
        if (is_wp_error($movies) && is_wp_error($tv_shows)) {
            $this->log_generation_failure('weekly', $platform, "Error fetching content from TMDB.", ['movie_error' => $movies->get_error_message(), 'tv_error' => $tv_shows->get_error_message()]);
            return false;
        }
        
        $movie_results = !is_wp_error($movies) && isset($movies['results']) ? $movies['results'] : [];
        $tv_results = !is_wp_error($tv_shows) && isset($tv_shows['results']) ? $tv_shows['results'] : [];
        
        $this->log_info("Found " . count($movie_results) . " new movies and " . count($tv_results) . " new TV shows.", ['platform' => $platform]);
        
        if (empty($movie_results) && empty($tv_results)) {
            $this->log_info("No new releases found, trying fallback content for {$platform}");
            return $this->generate_fallback_content($platform, $provider_id, $platform_name);
        }
        
        $all_content = array_merge($movie_results, $tv_results);
        $filtered_content = $this->filter_content_for_weekly($all_content);
        
        if (empty($filtered_content)) {
            $this->log_generation_failure('weekly', $platform, 'No suitable content after filtering');
            return false;
        }
        
        $featured_content = $this->select_featured_content($filtered_content);
        if (!$featured_content) {
            $this->log_generation_failure('weekly', $platform, 'No suitable featured content selected');
            return false;
        }
        
        $featured_type = isset($featured_content['title']) ? 'movie' : 'tv';
        $content_id = $featured_content['id'];
        
        $details = $this->get_detailed_content_info($content_id, $featured_type);
        if (is_wp_error($details)) {
            $this->log_generation_failure('weekly', $platform, "Failed to get details for content ID: {$content_id}", ['error' => $details->get_error_message()]);
            return false;
        }
        
        $featured_data = $this->prepare_featured_content_data($featured_content, $details, $featured_type);
        
        $base_title = $this->generate_varied_weekly_title($featured_data, $platform_name, $featured_type);
        $title = $this->enhance_title_with_context($base_title);
        
        $article_content = $this->create_weekly_highlight_content_blocks($featured_data, $platform_name, $featured_type);
        
        $tags = ['whats-new', $platform, 'streaming', 'weekly-update', sanitize_title($featured_data['title'])];

        $post_id = $this->create_post($title, $article_content, $platform, $tags, 'weekly_whats_new');
        
        if ($post_id) {
            $this->set_featured_image_with_landscape_priority($post_id, $featured_data, 'Weekly Featured Content');
            
            update_post_meta($post_id, 'weekly_generation_date', date('Y-m-d'));
            update_post_meta($post_id, 'featured_content_id', $content_id);
            update_post_meta($post_id, 'featured_content_type', $featured_type);
            update_post_meta($post_id, 'content_title', $featured_data['title']);
            
            $this->log_info("Successfully created weekly article for {$platform}", ['post_id' => $post_id, 'title' => $title]);
        }
        
        return $post_id;
    }
    
    private function has_recent_weekly_content($platform) {
        $args = [
            'post_type' => 'post',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 1,
            'date_query' => [['after' => '12 hours ago']],
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'streaming_platform', 'value' => $platform, 'compare' => '='],
                ['key' => 'article_type', 'value' => 'weekly_whats_new', 'compare' => '='],
            ]
        ];
        return !empty(get_posts($args));
    }
    
    private function generate_fallback_content($platform, $provider_id, $platform_name) {
        $this->log_info("Generating trending fallback for {$platform}");
        
        $trending_movies = $this->tmdb->get_trending_movies('week');
        $trending_tv = $this->tmdb->get_trending_tv('week');
        
        $movie_results = !is_wp_error($trending_movies) ? ($trending_movies['results'] ?? []) : [];
        $tv_results = !is_wp_error($trending_tv) ? ($trending_tv['results'] ?? []) : [];
        
        $all_trending = array_merge($movie_results, $tv_results);
        if (empty($all_trending)) {
            $this->log_generation_failure('weekly_fallback', $platform, 'No trending content found for fallback');
            return false;
        }
        
        $featured_content = $this->select_featured_content($all_trending);
        if (!$featured_content) {
            $this->log_generation_failure('weekly_fallback', $platform, 'Could not select featured trending content');
            return false;
        }
        
        $featured_type = isset($featured_content['title']) ? 'movie' : 'tv';
        $content_id = $featured_content['id'];
        
        $details = $this->get_detailed_content_info($content_id, $featured_type);
        if (is_wp_error($details)) {
            $this->log_generation_failure('weekly_fallback', $platform, 'Failed to get details for fallback content');
            return false;
        }
        
        $featured_data = $this->prepare_featured_content_data($featured_content, $details, $featured_type);
        $title = $this->generate_trending_title($featured_data, $platform_name, $featured_type);
        $content_blocks = $this->create_weekly_highlight_content_blocks($featured_data, $platform_name, $featured_type, true);
        $tags = ['trending', $platform, 'streaming', 'weekly-update', sanitize_title($featured_data['title'])];

        $post_id = $this->create_post($title, $content_blocks, $platform, $tags, 'weekly_trending');
        
        if ($post_id) {
            $this->set_featured_image_with_landscape_priority($post_id, $featured_data, 'Weekly Trending Content');
            update_post_meta($post_id, 'weekly_generation_date', date('Y-m-d'));
            update_post_meta($post_id, 'content_source', 'trending');
            $this->log_info("Successfully created fallback weekly article for {$platform}", ['post_id' => $post_id, 'title' => $title]);
        }
        
        return $post_id;
    }
    
    private function filter_content_for_weekly($content_array) {
        return array_filter($content_array, function($item) {
            return (($item['vote_average'] ?? 0) >= 5.0 && ($item['popularity'] ?? 0) >= 5 && !empty($item['overview']));
        });
    }

    private function select_featured_content($content_array) {
        if (empty($content_array)) return false;
        usort($content_array, function($a, $b) {
            $score_a = ($a['vote_average'] ?? 0) * 2 + ($a['popularity'] ?? 0);
            $score_b = ($b['vote_average'] ?? 0) * 2 + ($b['popularity'] ?? 0);
            return $score_b <=> $score_a;
        });
        return $content_array[0];
    }
    
    private function get_detailed_content_info($content_id, $type) {
        $append = ['credits', 'videos'];
        return ($type === 'movie') ? $this->tmdb->get_movie_details($content_id, $append) : $this->tmdb->get_tv_details($content_id, $append);
    }
    
    private function prepare_featured_content_data($content, $details, $type) {
        $data = [
            'overview' => $content['overview'] ?? '',
            'rating' => $content['vote_average'] ?? 0,
            'poster_path' => $content['poster_path'] ?? '',
            'backdrop_path' => $content['backdrop_path'] ?? '',
            'genres' => $details['genres'] ?? [],
            'tagline' => $details['tagline'] ?? '',
            'cast' => isset($details['credits']['cast']) ? array_slice($details['credits']['cast'], 0, 5) : [],
            'poster_url' => !empty($content['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $content['poster_path'] : '',
            'backdrop_url' => !empty($content['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $content['backdrop_path'] : '',
        ];

        if ($type === 'movie') {
            $data['title'] = $content['title'] ?? 'Unknown Movie';
            $data['release_date'] = $content['release_date'] ?? '';
            $data['runtime'] = $details['runtime'] ?? null;
            $data['director'] = $this->get_director_from_credits($details['credits'] ?? []);
        } else {
            $data['title'] = $content['name'] ?? 'Unknown Show';
            $data['first_air_date'] = $content['first_air_date'] ?? '';
            $data['number_of_seasons'] = $details['number_of_seasons'] ?? null;
            $data['number_of_episodes'] = $details['number_of_episodes'] ?? null;
            $data['created_by'] = $details['created_by'] ?? [];
        }
        return $data;
    }
    
    private function get_director_from_credits($credits) {
        if (empty($credits['crew'])) return '';
        foreach ($credits['crew'] as $crew_member) {
            if (isset($crew_member['job']) && $crew_member['job'] === 'Director') {
                return $crew_member['name'];
            }
        }
        return '';
    }

    private function generate_varied_weekly_title($content_data, $platform_name, $content_type) {
        $title = esc_html($content_data['title']);
        $media_type = ($content_type === 'movie') ? 'movie' : 'series';
        $templates = [
            "This week on {$platform_name}: \"{$title}\" premieres as the standout {$media_type}",
            "New on {$platform_name}: \"{$title}\" leads this week's streaming lineup",
            "{$platform_name} this week: \"{$title}\" emerges as the top {$media_type} choice",
            "What to watch this week: \"{$title}\" is the {$media_type} everyone's talking about on {$platform_name}",
            "Just dropped: \"{$title}\" is already becoming {$platform_name}'s newest hit",
        ];
        return $templates[array_rand($templates)];
    }

    private function enhance_title_with_context($base_title) {
        if (in_array(date('w'), [5, 6]) && rand(1, 3) === 1) { // Friday or Saturday
            return str_replace('this week', 'this weekend', $base_title);
        }
        return $base_title;
    }

    private function generate_trending_title($content_data, $platform_name, $type) {
        $title = esc_html($content_data['title']);
        $templates = [
            "Trending Now: '{$title}' Dominates {$platform_name}",
            "Hot on {$platform_name}: '{$title}' Gaining Popularity",
            "Popular Pick: '{$title}' Trending This Week on {$platform_name}",
        ];
        return $templates[array_rand($templates)];
    }

    private function create_weekly_highlight_content_blocks($data, $platform_name, $type, $is_trending = false) {
        $blocks = [];
        if (!empty($data['backdrop_url'])) {
            $blocks[] = ['type' => 'image', 'url' => $data['backdrop_url'], 'alt' => $data['title'], 'caption' => ''];
        }
        
        $intro = $is_trending ? "This week brings exciting trending content to {$platform_name}" : "This week brings an exciting new addition to {$platform_name}";
        $intro .= " with the arrival of \"{$data['title']}\", " . (($type === 'movie') ? "a compelling new film" : "an engaging new series") . " that's already capturing viewer attention.";
        $blocks[] = ['type' => 'paragraph', 'content' => $intro];

        if (!empty($data['tagline'])) {
            $blocks[] = ['type' => 'paragraph', 'content' => '<em>' . esc_html($data['tagline']) . '</em>'];
        }

        $blocks[] = ['type' => 'paragraph', 'content' => '<div style="background-color:#f8f9fa;padding:15px;border-radius:5px;">' . $this->build_metadata_content($data, $type) . '</div>'];
        $blocks[] = ['type' => 'heading', 'level' => 2, 'content' => 'What to Expect'];
        if (!empty($data['overview'])) {
            $blocks[] = ['type' => 'paragraph', 'content' => '<strong>' . esc_html($data['overview']) . '</strong>'];
        }

        $blocks[] = ['type' => 'heading', 'level' => 2, 'content' => 'Streaming Information'];
        $watch_content = '<p><strong>' . esc_html($data['title']) . '</strong> is available to stream now on ' . esc_html($platform_name) . '.</p>';
        $blocks[] = ['type' => 'paragraph', 'content' => '<div style="background-color:#e8f5e8;padding:15px;border-radius:5px;border-left:4px solid #28a745;">' . $watch_content . '</div>'];

        $conclusion = $is_trending ? "Join the conversation and see what has viewers talking about \"{$data['title']}\" on {$platform_name}." : "Don't miss out on this exciting new addition to {$platform_name}'s growing library.";
        $blocks[] = ['type' => 'paragraph', 'content' => $conclusion];

        return $blocks;
    }

    private function build_metadata_content($data, $type) {
        $items = [];
        $items[] = '<strong>Type:</strong> ' . ($type === 'movie' ? 'Movie' : 'TV Series');
        
        $release_date_key = ($type === 'movie') ? 'release_date' : 'first_air_date';
        if (!empty($data[$release_date_key])) {
            $items[] = '<strong>Release Date:</strong> ' . date('F j, Y', strtotime($data[$release_date_key]));
        }

        if (!empty($data['genres'])) {
            $genre_names = array_map(function($g) { return $g['name']; }, $data['genres']);
            $items[] = '<strong>Genres:</strong> ' . esc_html(implode(', ', $genre_names));
        }
        if (!empty($data['rating'])) {
            $items[] = '<strong>Rating:</strong> ' . number_format($data['rating'], 1) . '/10';
        }

        if ($type === 'movie' && !empty($data['runtime'])) {
            $items[] = '<strong>Runtime:</strong> ' . floor($data['runtime'] / 60) . 'h ' . ($data['runtime'] % 60) . 'm';
        }
        if ($type === 'tv' && !empty($data['number_of_seasons'])) {
            $items[] = '<strong>Seasons:</strong> ' . $data['number_of_seasons'];
        }
        if ($type === 'movie' && !empty($data['director'])) {
            $items[] = '<strong>Director:</strong> ' . esc_html($data['director']);
        } elseif ($type === 'tv' && !empty($data['created_by'])) {
            $creator_names = array_map(function($c) { return $c['name']; }, $data['created_by']);
            $items[] = '<strong>Created by:</strong> ' . esc_html(implode(', ', $creator_names));
        }
        if (!empty($data['cast'])) {
            $cast_names = array_map(function($c) { return $c['name']; }, $data['cast']);
            $items[] = '<strong>Starring:</strong> ' . esc_html(implode(', ', array_slice($cast_names, 0, 5)));
        }
        
        $html_items = array_map(function($item) { return "<p>{$item}</p>"; }, $items);
        return implode('', $html_items);
    }
}