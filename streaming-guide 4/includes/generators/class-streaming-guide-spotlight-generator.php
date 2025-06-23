<?php
/**
 * FINAL (v6) Enhanced Spotlight Generator
 * Corrects the 'media_type' undefined key warning, which was the final root cause
 * for the image insertion failure. This is the definitive version.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('parse_blocks') || !function_exists('serialize_blocks')) {
    require_once ABSPATH . 'wp-includes/blocks.php';
}

require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-streaming-guide-base-generator.php';
require_once STREAMING_GUIDE_PLUGIN_DIR . 'includes/class-simple-seo-helper.php';

class Streaming_Guide_Spotlight_Generator extends Streaming_Guide_Base_Generator {

    public function generate_article($platform = '', $options = array()) {
        $defaults = [
            'include_trailers'        => true,
            'auto_publish'            => true,
            'auto_featured_image'     => true,
            'seo_optimize'            => true,
            'tmdb_id'                 => null,
            'media_type'              => 'movie',
            'include_landscape_images'=> true
        ];
        $options = array_merge($defaults, $options);
        if (empty($options['tmdb_id'])) {
            return new WP_Error('missing_tmdb_id', 'TMDB ID is required.');
        }

        try {
            // STEP 1: Get ALL data in one efficient API call.
            $content_data = $this->get_spotlight_content($options['tmdb_id'], $options['media_type']);
            if (!$content_data) {
                return new WP_Error('content_not_found', 'Content not found on TMDB.');
            }
            $content_data['media_type'] = $options['media_type'];

            // STEP 2: Process the now-complete data.
            $processed_content_data = $this->process_prefetched_data($content_data);

            // STEP 3: Generate SEO data & initial post content.
            $platform_for_seo = $this->determine_platform_from_providers($processed_content_data) ?: 'streaming';
            $focus_keyphrase = Streaming_Guide_Simple_SEO_Helper::generate_focus_keyphrase($platform_for_seo, 'spotlight');
            
            $article_data = $this->create_initial_spotlight_article($processed_content_data, $focus_keyphrase);
            if (is_wp_error($article_data)) {
                return $article_data;
            }

            // STEP 4: Create the WordPress post.
            $post_id = $this->create_wordpress_post($article_data, $options);
            if (is_wp_error($post_id)) {
                return $post_id;
            }

            // STEP 5: Sideload images and insert them.
            if ($options['include_landscape_images']) {
                $this->sideload_and_insert_images($post_id, $processed_content_data);
            }

            // STEP 6: Finalize post.
            $meta_description = Streaming_Guide_Simple_SEO_Helper::generate_meta_description($platform_for_seo, 'spotlight', $focus_keyphrase);
            $this->add_essential_metadata($post_id, $processed_content_data, $platform_for_seo);
            Streaming_Guide_Simple_SEO_Helper::add_seo_metadata($post_id, $focus_keyphrase, $meta_description, $platform_for_seo, 'spotlight');
            
            if ($options['auto_featured_image']) {
                $this->set_featured_image_with_seo($post_id, $processed_content_data, $focus_keyphrase, $platform_for_seo);
            }
                
            error_log("Successfully created and enhanced spotlight post: Post ID {$post_id}");
            return $post_id;

        } catch (Exception $e) {
            error_log("Spotlight generation error: " . $e->getMessage());
            return new WP_Error('generation_failed', $e->getMessage());
        }
    }
    
    private function get_spotlight_content($tmdb_id, $media_type) {
        $endpoint = "{$media_type}/{$tmdb_id}";
        $params = ['append_to_response' => 'credits,watch/providers,images,videos'];
        return $this->tmdb_api->make_request($endpoint, $params);
    }

    private function process_prefetched_data($content) {
        if (isset($content['credits']['crew'])) $content['director'] = $this->extract_directors($content['credits']['crew']);
        if (isset($content['credits']['cast'])) $content['main_cast'] = array_slice($content['credits']['cast'], 0, 5);
        if (isset($content['watch/providers']['results']['US'])) $content['streaming_providers'] = $content['watch/providers']['results']['US'];
        if (isset($content['images']['backdrops'])) {
            usort($content['images']['backdrops'], function($a, $b) {
                return ($b['vote_average'] ?? 0) <=> ($a['vote_average'] ?? 0);
            });
            $content['backdrop_images'] = $content['images']['backdrops'];
        }
        if (isset($content['videos']['results'])) $content['main_trailer'] = $this->find_best_trailer($content['videos']['results']);
        return $content;
    }
    
    private function create_initial_spotlight_article($content, $focus_keyphrase) {
        $title = $content['title'] ?? $content['name'] ?? 'Unknown';
        $original_title = "{$title} - " . ($content['media_type'] === 'movie' ? 'Movie' : 'TV Show') . " Review & Analysis";
        $seo_title = Streaming_Guide_Simple_SEO_Helper::optimize_title($original_title, $focus_keyphrase);
        $generated_content = $this->openai_api->generate_spotlight_article('', $title, $content);
        if (is_wp_error($generated_content)) return $generated_content;
        $article_content = wpautop($generated_content);
        if (!empty($content['main_trailer'])) $article_content .= "<h2>Watch the Official Trailer</h2>" . $this->create_clean_trailer_embed($content['main_trailer']);
        $article_content .= $this->create_clean_details_box($content);
        if ($platform = $this->determine_platform_from_providers($content)) $article_content = Streaming_Guide_Simple_SEO_Helper::add_platform_outbound_link($article_content, $platform);
        return ['title' => $seo_title, 'content' => $article_content, 'excerpt' => $this->create_clean_excerpt($content, $focus_keyphrase)];
    }

    private function sideload_and_insert_images($post_id, $content_data) {
        $images_to_insert = array_slice($content_data['backdrop_images'] ?? [], 0, 3);
        if (empty($images_to_insert)) {
            error_log("No backdrop images to insert for post ID {$post_id}.");
            return;
        }
        $uploaded_attachments = [];
        foreach ($images_to_insert as $index => $image_data) {
            $image_url = "https://image.tmdb.org/t/p/original" . $image_data['file_path'];
            $alt_text = "Scene from " . ($content_data['title'] ?? 'movie') . " " . ($index + 1);
            $attachment_id = $this->download_and_attach_image($image_url, $post_id, $alt_text);
            if ($attachment_id && !is_wp_error($attachment_id)) {
                $uploaded_attachments[] = ['id' => $attachment_id, 'url' => wp_get_attachment_url($attachment_id), 'alt' => $alt_text];
            }
        }
        if (empty($uploaded_attachments)) return;
        $post_to_update = get_post($post_id);
        $blocks = parse_blocks($post_to_update->post_content);
        $hero_image_data = array_shift($uploaded_attachments);
        $hero_image_block = $this->create_image_block($hero_image_data, 'spotlight-hero-image');
        array_unshift($blocks, $hero_image_block);
        $this->insert_images_into_blocks($blocks, $uploaded_attachments);
        $final_content = serialize_blocks($blocks);
        wp_update_post(['ID' => $post_id, 'post_content' => $final_content]);
    }
    
    private function create_image_block($attachment, $class_name = 'article-image') {
        return ['blockName' => 'core/image', 'attrs' => ['id' => $attachment['id'], 'url' => $attachment['url'], 'alt' => $attachment['alt'], 'className' => $class_name]];
    }

    private function insert_images_into_blocks(&$blocks, $attachments) {
        if (empty($attachments)) return;
        $paragraph_keys = array_keys(array_filter($blocks, function($b) { return isset($b['blockName']) && $b['blockName'] === 'core/paragraph'; }));
        if (count($paragraph_keys) < 2) return;
        $offset = 0;
        for ($i = 0; $i < count($attachments); $i++) {
            $insert_key_index = floor(count($paragraph_keys) * (($i + 1) / (count($attachments) + 1)));
            if (isset($paragraph_keys[$insert_key_index])) {
                $target_block_key = $paragraph_keys[$insert_key_index];
                array_splice($blocks, $target_block_key + 1 + $offset, 0, [$this->create_image_block($attachments[$i])]);
                $offset++;
            }
        }
    }
    
    private function create_clean_trailer_embed($trailer) {
        if (!$trailer || $trailer['site'] !== 'YouTube' || empty($trailer['key'])) return '';
        return '<div style="position: relative; padding-bottom: 56.25%; height: 0; margin: 2rem 0;"><iframe src="https://www.youtube.com/embed/' . $trailer['key'] . '?rel=0&modestbranding=1&showinfo=0" title="' . esc_attr($trailer['name'] ?? 'Official Trailer') . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 8px;" allowfullscreen></iframe></div>';
    }
    
    private function create_clean_details_box($content) {
        $box = '<div class="spotlight-info-box" style="background: #f8f9fa; border-left: 4px solid #007cba; padding: 1.5rem; margin: 2rem 0; border-radius: 8px;">';
        $box .= '<h3 style="margin-top: 0; color: #333;">Content Details</h3>';
        $box .= '<div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">';
        if (!empty($content['vote_average'])) $box .= '<div><strong>Rating:</strong> ' . number_format($content['vote_average'], 1) . '/10</div>';
        if (!empty($content['release_date'])) $box .= '<div><strong>Release Date:</strong> ' . date('F j, Y', strtotime($content['release_date'])) . '</div>';
        if ($content['media_type'] === 'movie' && !empty($content['runtime'])) {
            $h = floor($content['runtime'] / 60); $m = $content['runtime'] % 60;
            $box .= '<div><strong>Runtime:</strong> ' . ($h > 0 ? "{$h}h {$m}m" : "{$m}m") . '</div>';
        }
        if (!empty($content['genres'])) $box .= '<div><strong>Genres:</strong> ' . implode(', ', array_column($content['genres'], 'name')) . '</div>';
        if (!empty($content['director'])) $box .= '<div><strong>Director:</strong> ' . implode(', ', array_column($content['director'], 'name')) . '</div>';
        if (!empty($content['main_cast'])) $box .= '<div><strong>Starring:</strong> ' . implode(', ', array_column($content['main_cast'], 'name')) . '</div>';
        if (!empty($content['streaming_providers']['flatrate'])) $box .= '<div><strong>Streaming on:</strong> ' . implode(', ', array_column($content['streaming_providers']['flatrate'], 'provider_name')) . '</div>';
        $box .= '</div></div>';
        return $box;
    }
    
    private function create_clean_excerpt($content, $keyphrase) {
        $title = $content['title'] ?? $content['name'] ?? 'Unknown';
        $rating = !empty($content['vote_average']) ? ", rated " . number_format($content['vote_average'], 1) . '/10' : '';
        return "Complete {$keyphrase} for {$title}{$rating}. In-depth analysis covering plot, performances, and production quality.";
    }
    
    protected function set_featured_image_with_seo($id, $content, $keyphrase, $platform) {
        if (!$id || !($path = $content['poster_path'] ?? null)) return false;
        try {
            $url = "https://image.tmdb.org/t/p/w780" . $path;
            $alt = Streaming_Guide_Simple_SEO_Helper::generate_image_alt_text($content['title'] ?? 'Featured', $keyphrase, $platform);
            $res = $this->download_and_attach_image($url, $id, $alt);
            if ($res && !is_wp_error($res)) {
                set_post_thumbnail($id, $res);
                update_post_meta($res, '_wp_attachment_image_alt', $alt);
                return $res;
            }
        } catch (Exception $e) {}
        return false;
    }
    
    private function add_essential_metadata($id, $content, $platform) {
        update_post_meta($id, 'generator_type', 'spotlight');
        update_post_meta($id, 'tmdb_id', $content['id']);
        update_post_meta($id, 'platform', $platform);
        if (!empty($content['media_type'])) update_post_meta($id, 'media_type', $content['media_type']);
        if (!empty($content['vote_average'])) update_post_meta($id, 'tmdb_rating', $content['vote_average']);
        if (!empty($content['genres'])) update_post_meta($id, 'content_genres', array_column($content['genres'], 'name'));
    }
    
    private function extract_directors($crew) {
        $directors = [];
        foreach ($crew as $member) {
            if (isset($member['job']) && $member['job'] === 'Director') $directors[] = $member;
            if (count($directors) >= 2) break;
        }
        return $directors;
    }
    
    private function determine_platform_from_providers($content) {
        if (empty($content['streaming_providers']['flatrate'])) return null;
        $map = [8 => 'netflix', 15 => 'hulu', 337 => 'disney', 1899 => 'hbo', 384 => 'hbo', 9 => 'amazon', 531 => 'paramount', 350 => 'apple'];
        foreach ($content['streaming_providers']['flatrate'] as $p) {
            if (isset($map[$p['provider_id']])) return $map[$p['provider_id']];
        }
        return 'streaming';
    }
}