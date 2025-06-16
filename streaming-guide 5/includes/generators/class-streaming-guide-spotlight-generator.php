<?php
/**
 * Spotlight Generator for Streaming Guide
 * 
 * Focuses on in-depth coverage of a single piece of content.
 * Supports both manual (by TMDB ID) and automatic content discovery.
 * CORRECTED: Fixed fatal error from missing function and updated all logging.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Spotlight_Generator extends Streaming_Guide_Base_Generator {
    
    /**
     * Generate spotlight content
     */
    public function generate($platform, $param1 = null, $param2 = null, $param3 = null) {
        // Map parameters to meaningful names
        $content_type = $param1 ?? 'auto'; // 'movie', 'tv', or 'auto'
        $tmdb_id = $param2 ?? null; // Specific TMDB ID if provided
        $spotlight_type = $param3 ?? 'featured'; // 'featured', 'hidden_gem', 'classic'
        
        $params = [
            'content_type' => $content_type,
            'tmdb_id' => $tmdb_id,
            'spotlight_type' => $spotlight_type
        ];

        try {
            $this->log_info("Starting spotlight generation for {$platform}", $params);
            
            $platform_name = $this->get_platform_name($platform);
            $provider_id = $this->get_provider_id($platform);
            
            if (!$platform_name || !$provider_id) {
                $this->log_generation_failure('spotlight', $platform, 'Invalid platform configuration', $params);
                return false;
            }
            
            $spotlight_content = $this->get_spotlight_content($provider_id, $content_type, $tmdb_id, $spotlight_type);
            
            if (!$spotlight_content) {
                $this->log_generation_failure('spotlight', $platform, 'No suitable spotlight content found', $params);
                return false;
            }
            
            $details = $this->get_detailed_content_info($spotlight_content['id'], $spotlight_content['media_type']);
            if (is_wp_error($details)) {
                $this->log_generation_failure('spotlight', $platform, 'Failed to get detailed content info', ['error' => $details->get_error_message()]);
                return false;
            }
            
            $title = $this->create_spotlight_title($spotlight_content, $platform_name, $spotlight_type);
            $content_blocks = $this->create_spotlight_content_blocks($spotlight_content, $details, $platform_name, $spotlight_type);
            $tags = $this->create_spotlight_tags($spotlight_content, $platform_name, $spotlight_type);
            
            $post_id = $this->create_post($title, $content_blocks, $platform, $tags, 'spotlight');
            
            if ($post_id) {
                $this->set_spotlight_featured_image($post_id, $spotlight_content);
                $this->add_spotlight_metadata($post_id, $spotlight_content, $spotlight_type, $details);
                $this->log_info("Successfully created spotlight post: {$title}", ['post_id' => $post_id]);
                return $post_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log_generation_failure('spotlight', $platform, 'Spotlight generator error: ' . $e->getMessage(), $params);
            return false;
        }
    }
    
    private function get_spotlight_content($provider_id, $content_type, $tmdb_id, $spotlight_type) {
        try {
            if ($tmdb_id) {
                $this->log_info("Attempting to fetch specific content by TMDB ID: {$tmdb_id}");
                return $this->get_specific_content($tmdb_id, $content_type, $provider_id);
            }
            
            $this->log_info("Searching for automatic content for spotlight type: {$spotlight_type}");
            switch ($spotlight_type) {
                case 'hidden_gem':
                    return $this->find_hidden_gem($provider_id, $content_type);
                case 'classic':
                    return $this->find_classic_content($provider_id, $content_type);
                default:
                    return $this->find_featured_content($provider_id, $content_type);
            }
        } catch (Exception $e) {
            $this->log_error("Error getting spotlight content", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    private function get_specific_content($tmdb_id, $content_type, $provider_id) {
        try {
            // If content type is 'auto' or 'movie', check for a movie first.
            if ($content_type === 'movie' || $content_type === 'auto') {
                $movie = $this->tmdb->get_movie_details($tmdb_id);
                if (!is_wp_error($movie) && $this->is_content_on_provider($movie['id'], 'movie', $provider_id)) {
                    $movie['media_type'] = 'movie';
                    return $movie;
                }
            }
            
            // If content type is 'auto' or 'tv', check for a TV show.
            if ($content_type === 'tv' || $content_type === 'auto') {
                $tv = $this->tmdb->get_tv_details($tmdb_id);
                if (!is_wp_error($tv) && $this->is_content_on_provider($tv['id'], 'tv', $provider_id)) {
                    $tv['media_type'] = 'tv';
                    return $tv;
                }
            }
            
            $this->log_error("Specific content not found or not on provider.", ['tmdb_id' => $tmdb_id, 'provider_id' => $provider_id]);
            return false;
        } catch (Exception $e) {
            $this->log_error("Error in get_specific_content", ['tmdb_id' => $tmdb_id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * CRITICAL FIX: Check if content is available on the specified provider.
     */
    private function is_content_on_provider($content_id, $media_type, $provider_id) {
        try {
            $providers = $this->tmdb->get_watch_providers($content_id, $media_type);
            if (is_wp_error($providers) || !isset($providers['results']['US']['flatrate'])) {
                return false;
            }
            
            foreach ($providers['results']['US']['flatrate'] as $provider) {
                if ($provider['provider_id'] == $provider_id) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            $this->log_error('Error checking provider availability', ['content_id' => $content_id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    private function find_hidden_gem($provider_id, $content_type) {
        $candidates = [];
        if ($content_type === 'movie' || $content_type === 'auto') {
            $movies = $this->tmdb->discover_movies(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'vote_average.gte' => 7.0, 'vote_count.gte' => 100, 'sort_by' => 'vote_average.desc', 'page' => rand(1, 3)]);
            if (!is_wp_error($movies) && !empty($movies['results'])) {
                foreach ($movies['results'] as $movie) {
                    if (($movie['popularity'] ?? 0) < 50) {
                        $movie['media_type'] = 'movie';
                        $candidates[] = $movie;
                    }
                }
            }
        }
        if ($content_type === 'tv' || $content_type === 'auto') {
            $tv_shows = $this->tmdb->discover_tv(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'vote_average.gte' => 7.0, 'vote_count.gte' => 50, 'sort_by' => 'vote_average.desc', 'page' => rand(1, 3)]);
            if (!is_wp_error($tv_shows) && !empty($tv_shows['results'])) {
                foreach ($tv_shows['results'] as $show) {
                    if (($show['popularity'] ?? 0) < 30) {
                        $show['media_type'] = 'tv';
                        $candidates[] = $show;
                    }
                }
            }
        }
        return !empty($candidates) ? $candidates[array_rand($candidates)] : false;
    }
    
    private function find_classic_content($provider_id, $content_type) {
        $classic_cutoff = date('Y') - 10;
        $candidates = [];
        if ($content_type === 'movie' || $content_type === 'auto') {
            $movies = $this->tmdb->discover_movies(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'primary_release_date.lte' => "{$classic_cutoff}-12-31", 'vote_average.gte' => 7.5, 'vote_count.gte' => 500, 'sort_by' => 'vote_average.desc']);
            if (!is_wp_error($movies) && !empty($movies['results'])) {
                foreach ($movies['results'] as $movie) {
                    $movie['media_type'] = 'movie';
                    $candidates[] = $movie;
                }
            }
        }
        if ($content_type === 'tv' || $content_type === 'auto') {
            $tv_shows = $this->tmdb->discover_tv(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'first_air_date.lte' => "{$classic_cutoff}-12-31", 'vote_average.gte' => 7.5, 'vote_count.gte' => 200, 'sort_by' => 'vote_average.desc']);
            if (!is_wp_error($tv_shows) && !empty($tv_shows['results'])) {
                foreach ($tv_shows['results'] as $show) {
                    $show['media_type'] = 'tv';
                    $candidates[] = $show;
                }
            }
        }
        return !empty($candidates) ? $candidates[array_rand($candidates)] : false;
    }
    
    private function find_featured_content($provider_id, $content_type) {
        $candidates = [];
        if ($content_type === 'movie' || $content_type === 'auto') {
            $movies = $this->tmdb->discover_movies(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'vote_average.gte' => 6.5, 'vote_count.gte' => 200, 'sort_by' => 'popularity.desc']);
            if (!is_wp_error($movies) && !empty($movies['results'])) {
                foreach (array_slice($movies['results'], 0, 5) as $movie) {
                    $movie['media_type'] = 'movie';
                    $candidates[] = $movie;
                }
            }
        }
        if ($content_type === 'tv' || $content_type === 'auto') {
            $tv_shows = $this->tmdb->discover_tv(['with_watch_providers' => $provider_id, 'watch_region' => 'US', 'vote_average.gte' => 6.5, 'vote_count.gte' => 100, 'sort_by' => 'popularity.desc']);
            if (!is_wp_error($tv_shows) && !empty($tv_shows['results'])) {
                foreach (array_slice($tv_shows['results'], 0, 5) as $show) {
                    $show['media_type'] = 'tv';
                    $candidates[] = $show;
                }
            }
        }
        return !empty($candidates) ? $candidates[array_rand($candidates)] : false;
    }
    
    private function get_detailed_content_info($content_id, $media_type) {
        try {
            return ($media_type === 'movie') ? $this->tmdb->get_movie_details($content_id, ['credits']) : $this->tmdb->get_tv_details($content_id, ['credits']);
        } catch (Exception $e) {
            $this->log_error("Error getting detailed content info", ['content_id' => $content_id, 'error' => $e->getMessage()]);
            return new WP_Error('api_error', 'Failed to get content details');
        }
    }

    private function create_spotlight_title($content, $platform_name, $spotlight_type) {
        $content_title = esc_html($content['media_type'] === 'movie' ? $content['title'] : $content['name']);
        switch ($spotlight_type) {
            case 'hidden_gem': return "Hidden Gem: '{$content_title}' on {$platform_name} Deserves Your Attention";
            case 'classic': return "Classic Spotlight: Why '{$content_title}' Remains Essential Viewing on {$platform_name}";
            default: return "Spotlight: '{$content_title}' - A Must-Watch on {$platform_name}";
        }
    }

    private function create_spotlight_content_blocks($content, $details, $platform_name, $spotlight_type) {
        $blocks = [];
        $content_title = esc_html($content['media_type'] === 'movie' ? $content['title'] : $content['name']);
        $type_label = $content['media_type'] === 'movie' ? 'film' : 'series';

        $blocks[] = ['type' => 'paragraph', 'content' => $this->create_spotlight_intro($content_title, $platform_name, $spotlight_type, $type_label)];
        
        if (!empty($content['overview'])) {
            $blocks[] = ['type' => 'heading', 'level' => 2, 'content' => 'The Story'];
            $blocks[] = ['type' => 'paragraph', 'content' => esc_html($content['overview'])];
        }

        if (!empty($details['genres'])) {
            $blocks[] = ['type' => 'heading', 'level' => 2, 'content' => 'What Makes It Special'];
            $blocks[] = ['type' => 'paragraph', 'content' => $this->create_special_content($content, $details)];
        }

        if (!empty($details['credits'])) {
            $blocks[] = ['type' => 'heading', 'level' => 2, 'content' => 'Key Talent'];
            $blocks[] = ['type' => 'paragraph', 'content' => $this->create_talent_content($details['credits'], $content['media_type'])];
        }

        $blocks[] = ['type' => 'heading', 'level' => 2, 'content' => "Why '{$content_title}' Should Be Your Next Watch"];
        $blocks[] = ['type' => 'paragraph', 'content' => $this->create_why_watch_content($content, $platform_name, $spotlight_type)];
        
        $blocks[] = ['type' => 'heading', 'level' => 2, 'content' => 'Where and How to Watch'];
        $viewing_info = "'{$content_title}' is currently available for streaming on <strong>{$platform_name}</strong>. Access it through the {$platform_name} app on your preferred device or stream directly from their website.";
        $blocks[] = ['type' => 'paragraph', 'content' => $viewing_info];

        return $blocks;
    }

    private function create_spotlight_intro($title, $platform_name, $spotlight_type, $type_label) {
        switch ($spotlight_type) {
            case 'hidden_gem': return "In the vast catalog of {$platform_name}, some truly exceptional content can get overlooked. Today's spotlight shines on '<strong>{$title}</strong>', a remarkable {$type_label} that deserves far more attention. This hidden gem offers viewers something truly special.";
            case 'classic': return "Some content transcends time, remaining as compelling today as when it first aired. '<strong>{$title}</strong>' is exactly that kind of {$type_label} - a classic that continues to captivate audiences on {$platform_name}.";
            default: return "When looking for standout content on {$platform_name}, '<strong>{$title}</strong>' immediately commands attention. This exceptional {$type_label} represents everything great about modern streaming entertainment.";
        }
    }

    private function create_special_content($content, $details) {
        $genre_names = array_map(function($g) { return $g['name']; }, $details['genres']);
        $special = "This " . esc_html(implode(', ', array_slice($genre_names, 0, 2))) . " " . ($content['media_type'] === 'movie' ? 'film' : 'series') . " stands out for several key reasons. ";
        if (($content['vote_average'] ?? 0) > 7.5) {
            $special .= "With an impressive <strong>" . round($content['vote_average'], 1) . "/10 rating</strong>, it has clearly resonated with audiences. ";
        }
        $special .= "The combination of strong writing, excellent performances, and skilled direction creates an experience that lingers long after viewing.";
        return $special;
    }

    private function create_talent_content($credits, $media_type) {
        $mentions = [];
        if (!empty($credits['cast'])) {
            $actors = array_map(function($a) { return $a['name']; }, array_slice($credits['cast'], 0, 3));
            $mentions[] = "starring " . esc_html(implode(', ', $actors));
        }
        if (!empty($credits['crew'])) {
            foreach ($credits['crew'] as $crew) {
                if ($crew['job'] === 'Director') {
                    $mentions[] = "directed by " . esc_html($crew['name']);
                    break;
                }
            }
        }
        if (empty($mentions)) return "The production benefits from a talented cast and crew who create something truly memorable.";
        return "The " . ($media_type === 'movie' ? 'film' : 'series') . " features exceptional talent, " . implode(' and ', $mentions) . ", who bring depth and authenticity to every scene.";
    }

    private function create_why_watch_content($content, $platform_name, $spotlight_type) {
        $title = esc_html($content['media_type'] === 'movie' ? $content['title'] : $content['name']);
        $why = "'{$title}' earns its spotlight for multiple compelling reasons. ";
        switch ($spotlight_type) {
            case 'hidden_gem': $why .= "As a hidden gem, it offers the satisfaction of discovering something special that others might miss. "; break;
            case 'classic': $why .= "As a classic, it provides the depth and craftsmanship that has stood the test of time. "; break;
            default: $why .= "As featured content, it represents the peak of what {$platform_name} offers. ";
        }
        $why .= "It delivers a complete, satisfying narrative that justifies every minute of its runtime.";
        return $why;
    }

    private function create_spotlight_tags($content, $platform_name, $spotlight_type) {
        $title = $content['media_type'] === 'movie' ? $content['title'] : $content['name'];
        $tags = [$platform_name, 'spotlight', 'featured content', ($content['media_type'] === 'movie' ? 'movies' : 'tv shows'), $title];
        switch ($spotlight_type) {
            case 'hidden_gem': $tags = array_merge($tags, ['hidden gem', 'underrated']); break;
            case 'classic': $tags = array_merge($tags, ['classic', 'timeless']); break;
            default: $tags = array_merge($tags, ['must watch', 'recommended']);
        }
        return array_map('sanitize_text_field', $tags);
    }

    private function set_spotlight_featured_image($post_id, $content) {
        $data = [
            'title' => $content['media_type'] === 'movie' ? $content['title'] : $content['name'],
            'backdrop_url' => !empty($content['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $content['backdrop_path'] : '',
            'poster_url' => !empty($content['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $content['poster_path'] : ''
        ];
        return $this->set_featured_image_with_landscape_priority($post_id, $data, $data['title']);
    }

    private function add_spotlight_metadata($post_id, $content, $spotlight_type, $details) {
        $title = $content['media_type'] === 'movie' ? $content['title'] : $content['name'];
        update_post_meta($post_id, 'spotlight_type', $spotlight_type);
        update_post_meta($post_id, 'content_type', $content['media_type']);
        update_post_meta($post_id, 'tmdb_id', $content['id']);
        update_post_meta($post_id, 'content_title', $title);

        if (isset($content['vote_average'])) {
            update_post_meta($post_id, 'tmdb_rating', $content['vote_average']);
        }
        if (isset($details['genres'])) {
            $genre_names = array_map(function($g) { return $g['name']; }, $details['genres']);
            update_post_meta($post_id, 'genres', implode(', ', $genre_names));
        }
    }
}