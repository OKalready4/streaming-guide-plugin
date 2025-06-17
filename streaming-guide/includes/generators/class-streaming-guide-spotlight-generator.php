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
                return true;
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
        try {
            $candidates = [];
            if ($content_type === 'movie' || $content_type === 'auto') {
                $movies = $this->tmdb->discover_movies([
                    'with_watch_providers' => $provider_id,
                    'watch_region' => 'US',
                    'vote_average.gte' => 7.0,
                    'vote_count.gte' => 100,
                    'sort_by' => 'vote_average.desc',
                    'page' => rand(1, 3)
                ]);
                
                if (is_wp_error($movies)) {
                    $this->log_error('TMDB API error getting movies for hidden gem', [
                        'provider_id' => $provider_id,
                        'error' => $movies->get_error_message()
                    ]);
                } elseif (!empty($movies['results'])) {
                    foreach ($movies['results'] as $movie) {
                        if (($movie['popularity'] ?? 0) < 50) {
                            $movie['media_type'] = 'movie';
                            $candidates[] = $movie;
                        }
                    }
                }
            }
            
            if ($content_type === 'tv' || $content_type === 'auto') {
                $tv_shows = $this->tmdb->discover_tv([
                    'with_watch_providers' => $provider_id,
                    'watch_region' => 'US',
                    'vote_average.gte' => 7.0,
                    'vote_count.gte' => 50,
                    'sort_by' => 'vote_average.desc',
                    'page' => rand(1, 3)
                ]);
                
                if (is_wp_error($tv_shows)) {
                    $this->log_error('TMDB API error getting TV shows for hidden gem', [
                        'provider_id' => $provider_id,
                        'error' => $tv_shows->get_error_message()
                    ]);
                } elseif (!empty($tv_shows['results'])) {
                    foreach ($tv_shows['results'] as $show) {
                        if (($show['popularity'] ?? 0) < 30) {
                            $show['media_type'] = 'tv';
                            $candidates[] = $show;
                        }
                    }
                }
            }
            
            return !empty($candidates) ? $candidates[array_rand($candidates)] : false;
            
        } catch (Exception $e) {
            $this->log_error('Error finding hidden gem', [
                'provider_id' => $provider_id,
                'content_type' => $content_type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function find_classic_content($provider_id, $content_type) {
        try {
            $classic_cutoff = date('Y') - 10;
            $candidates = [];
            
            if ($content_type === 'movie' || $content_type === 'auto') {
                $movies = $this->tmdb->discover_movies([
                    'with_watch_providers' => $provider_id,
                    'watch_region' => 'US',
                    'primary_release_date.lte' => "{$classic_cutoff}-12-31",
                    'vote_average.gte' => 7.5,
                    'vote_count.gte' => 500,
                    'sort_by' => 'vote_average.desc'
                ]);
                
                if (is_wp_error($movies)) {
                    $this->log_error('TMDB API error getting classic movies', [
                        'provider_id' => $provider_id,
                        'error' => $movies->get_error_message()
                    ]);
                } elseif (!empty($movies['results'])) {
                    foreach ($movies['results'] as $movie) {
                        $movie['media_type'] = 'movie';
                        $candidates[] = $movie;
                    }
                }
            }
            
            if ($content_type === 'tv' || $content_type === 'auto') {
                $tv_shows = $this->tmdb->discover_tv([
                    'with_watch_providers' => $provider_id,
                    'watch_region' => 'US',
                    'first_air_date.lte' => "{$classic_cutoff}-12-31",
                    'vote_average.gte' => 7.5,
                    'vote_count.gte' => 200,
                    'sort_by' => 'vote_average.desc'
                ]);
                
                if (is_wp_error($tv_shows)) {
                    $this->log_error('TMDB API error getting classic TV shows', [
                        'provider_id' => $provider_id,
                        'error' => $tv_shows->get_error_message()
                    ]);
                } elseif (!empty($tv_shows['results'])) {
                    foreach ($tv_shows['results'] as $show) {
                        $show['media_type'] = 'tv';
                        $candidates[] = $show;
                    }
                }
            }
            
            return !empty($candidates) ? $candidates[array_rand($candidates)] : false;
            
        } catch (Exception $e) {
            $this->log_error('Error finding classic content', [
                'provider_id' => $provider_id,
                'content_type' => $content_type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    private function find_featured_content($provider_id, $content_type) {
        try {
            $candidates = [];
            
            if ($content_type === 'movie' || $content_type === 'auto') {
                $movies = $this->tmdb->discover_movies([
                    'with_watch_providers' => $provider_id,
                    'watch_region' => 'US',
                    'vote_average.gte' => 6.5,
                    'vote_count.gte' => 200,
                    'sort_by' => 'popularity.desc'
                ]);
                
                if (is_wp_error($movies)) {
                    $this->log_error('TMDB API error getting featured movies', [
                        'provider_id' => $provider_id,
                        'error' => $movies->get_error_message()
                    ]);
                } elseif (!empty($movies['results'])) {
                    foreach (array_slice($movies['results'], 0, 5) as $movie) {
                        $movie['media_type'] = 'movie';
                        $candidates[] = $movie;
                    }
                }
            }
            
            if ($content_type === 'tv' || $content_type === 'auto') {
                $tv_shows = $this->tmdb->discover_tv([
                    'with_watch_providers' => $provider_id,
                    'watch_region' => 'US',
                    'vote_average.gte' => 6.5,
                    'vote_count.gte' => 100,
                    'sort_by' => 'popularity.desc'
                ]);
                
                if (is_wp_error($tv_shows)) {
                    $this->log_error('TMDB API error getting featured TV shows', [
                        'provider_id' => $provider_id,
                        'error' => $tv_shows->get_error_message()
                    ]);
                } elseif (!empty($tv_shows['results'])) {
                    foreach (array_slice($tv_shows['results'], 0, 5) as $show) {
                        $show['media_type'] = 'tv';
                        $candidates[] = $show;
                    }
                }
            }
            
            return !empty($candidates) ? $candidates[array_rand($candidates)] : false;
            
        } catch (Exception $e) {
            $this->log_error('Error finding featured content', [
                'provider_id' => $provider_id,
                'content_type' => $content_type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
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

    /**
     * Create spotlight introduction
     */
    private function create_spotlight_intro($content_title, $platform_name, $spotlight_type, $type_label) {
        switch ($spotlight_type) {
            case 'hidden_gem':
                return "In the vast library of <strong>{$platform_name}</strong>, some exceptional content doesn't get the attention it deserves. " .
                       "Today, we're shining a spotlight on '{$content_title}', a {$type_label} that stands out for its quality and unique appeal. " .
                       "This hidden gem offers a viewing experience that's both distinctive and rewarding.";
                       
            case 'classic':
                return "Some content transcends time, becoming essential viewing for generations of audiences. " .
                       "On <strong>{$platform_name}</strong>, '{$content_title}' represents exactly this kind of enduring quality. " .
                       "This {$type_label} has earned its place as a classic, and we're exploring why it remains relevant and compelling today.";
                       
            default: // 'featured'
                return "Every streaming platform has its standout content, and <strong>{$platform_name}</strong> is no exception. " .
                       "Today, we're highlighting '{$content_title}', a {$type_label} that exemplifies the quality and diversity of content available. " .
                       "This featured title offers something special that makes it worth your attention.";
        }
    }

    /**
     * Create special content section
     */
    private function create_special_content($content, $details) {
        $special_elements = array();
        
        // Add genre context
        if (!empty($details['genres'])) {
            $genre_names = array_map(function($g) { return $g['name']; }, $details['genres']);
            $special_elements[] = "As a " . implode('/', $genre_names) . " " . 
                                ($content['media_type'] === 'movie' ? 'film' : 'series') . ", it delivers a unique blend of storytelling elements.";
        }
        
        // Add rating context
        if (!empty($content['vote_average']) && !empty($content['vote_count'])) {
            $rating = round($content['vote_average'], 1);
            $votes = number_format($content['vote_count']);
            $special_elements[] = "With a {$rating}/10 rating from {$votes} viewers, it has clearly resonated with audiences.";
        }
        
        // Add release context
        if ($content['media_type'] === 'movie' && !empty($content['release_date'])) {
            $year = date('Y', strtotime($content['release_date']));
            $special_elements[] = "Released in {$year}, it represents a significant moment in " . 
                                ($year >= date('Y') - 5 ? 'contemporary' : 'classic') . " cinema.";
        } elseif ($content['media_type'] === 'tv' && !empty($content['first_air_date'])) {
            $year = date('Y', strtotime($content['first_air_date']));
            $special_elements[] = "First airing in {$year}, it has " . 
                                ($year >= date('Y') - 5 ? 'quickly established itself' : 'stood the test of time') . " as quality television.";
        }
        
        // Add popularity context
        if (!empty($content['popularity'])) {
            $popularity = round($content['popularity'], 1);
            $special_elements[] = "Its popularity score of {$popularity} indicates strong audience engagement and cultural impact.";
        }
        
        return implode(' ', $special_elements);
    }

    /**
     * Create talent content section
     */
    private function create_talent_content($credits, $media_type) {
        $talent_sections = array();
        
        // Director section for movies
        if ($media_type === 'movie' && !empty($credits['director'])) {
            $director = $credits['director'];
            $talent_sections[] = "<strong>Director:</strong> {$director['name']}" . 
                               (!empty($director['known_for']) ? " (known for " . implode(', ', $director['known_for']) . ")" : "");
        }
        
        // Creator section for TV
        if ($media_type === 'tv' && !empty($credits['creator'])) {
            $creator = $credits['creator'];
            $talent_sections[] = "<strong>Creator:</strong> {$creator['name']}" . 
                               (!empty($creator['known_for']) ? " (known for " . implode(', ', $creator['known_for']) . ")" : "");
        }
        
        // Cast section
        if (!empty($credits['cast'])) {
            $cast_list = array();
            foreach (array_slice($credits['cast'], 0, 5) as $actor) {
                $cast_list[] = $actor['name'] . 
                             (!empty($actor['character']) ? " as " . $actor['character'] : "");
            }
            $talent_sections[] = "<strong>Cast:</strong> " . implode(', ', $cast_list) . 
                               (count($credits['cast']) > 5 ? " and more" : "");
        }
        
        return implode('<br>', $talent_sections);
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

    /**
     * Create spotlight tags
     */
    private function create_spotlight_tags($content, $platform_name, $spotlight_type) {
        $tags = array(
            $platform_name,
            'spotlight',
            $spotlight_type
        );
        
        // Add content type tag
        if ($content['media_type'] === 'movie') {
            $tags[] = 'movies';
        } else {
            $tags[] = 'tv shows';
        }
        
        // Add genre tags
        if (!empty($content['genre_ids'])) {
            foreach ($content['genre_ids'] as $genre_id) {
                $genre_name = $this->get_genre_name($genre_id, $content['media_type']);
                if ($genre_name) {
                    $tags[] = strtolower($genre_name);
                }
            }
        }
        
        // Add year tag
        if ($content['media_type'] === 'movie' && !empty($content['release_date'])) {
            $year = date('Y', strtotime($content['release_date']));
            $tags[] = $year;
        } elseif ($content['media_type'] === 'tv' && !empty($content['first_air_date'])) {
            $year = date('Y', strtotime($content['first_air_date']));
            $tags[] = $year;
        }
        
        return array_map('sanitize_text_field', array_unique($tags));
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

    private function set_spotlight_featured_image($post_id, $content) {
        $data = [
            'title' => $content['media_type'] === 'movie' ? $content['title'] : $content['name'],
            'backdrop_url' => !empty($content['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $content['backdrop_path'] : '',
            'poster_url' => !empty($content['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $content['poster_path'] : ''
        ];
        return $this->set_featured_image($post_id, $data['backdrop_url'], $data['title']);
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

    /**
     * Set featured image for spotlight post
     * Ensures only landscape images are used
     */
    protected function set_featured_image($post_id, $image_url, $title = '') {
        if (empty($image_url)) {
            return false;
        }
        
        // Check if image already exists
        $existing_attachment = get_posts(array(
            'post_type' => 'attachment',
            'meta_key' => '_streaming_guide_image_url',
            'meta_value' => $image_url,
            'posts_per_page' => 1
        ));
        
        if (!empty($existing_attachment)) {
            set_post_thumbnail($post_id, $existing_attachment[0]->ID);
            return true;
        }
        
        // Download and create new attachment
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        
        if ($image_data === false) {
            $this->log_error('Failed to download image', array('url' => $image_url));
            return false;
        }
        
        // Create temporary file to check dimensions
        $temp_file = wp_tempnam();
        file_put_contents($temp_file, $image_data);
        
        // Get image dimensions
        $image_size = getimagesize($temp_file);
        if ($image_size === false) {
            $this->log_error('Failed to get image dimensions', array('url' => $image_url));
            unlink($temp_file);
            return false;
        }
        
        // Check if image is landscape (width > height)
        if ($image_size[0] <= $image_size[1]) {
            $this->log_error('Image is not landscape', array(
                'url' => $image_url,
                'width' => $image_size[0],
                'height' => $image_size[1]
            ));
            unlink($temp_file);
            return false;
        }
        
        // Ensure minimum dimensions for quality
        $min_width = 1200;
        $min_height = 675; // 16:9 aspect ratio minimum
        
        if ($image_size[0] < $min_width || $image_size[1] < $min_height) {
            $this->log_error('Image dimensions too small', array(
                'url' => $image_url,
                'width' => $image_size[0],
                'height' => $image_size[1],
                'min_width' => $min_width,
                'min_height' => $min_height
            ));
            unlink($temp_file);
            return false;
        }
        
        $filename = basename($image_url);
        $file = $upload_dir['path'] . '/' . $filename;
        
        // Move temp file to uploads directory
        if (!rename($temp_file, $file)) {
            $this->log_error('Failed to move image to uploads directory', array('file' => $file));
            unlink($temp_file);
            return false;
        }
        
        $wp_filetype = wp_check_filetype($filename, null);
        
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => !empty($title) ? sanitize_file_name($title) : sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        
        if (is_wp_error($attach_id)) {
            $this->log_error('Failed to create attachment', array('error' => $attach_id->get_error_message()));
            unlink($file);
            return false;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Store original URL and dimensions in attachment meta
        update_post_meta($attach_id, '_streaming_guide_image_url', $image_url);
        update_post_meta($attach_id, '_streaming_guide_image_width', $image_size[0]);
        update_post_meta($attach_id, '_streaming_guide_image_height', $image_size[1]);
        
        set_post_thumbnail($post_id, $attach_id);
        return true;
    }
}