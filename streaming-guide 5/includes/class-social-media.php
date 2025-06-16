<?php
/**
 * FINAL - Social Media Sharing System
 *
 * This class combines a robust, crash-safe architecture with intelligent,
 * automatic posting capabilities for newly generated articles.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Streaming_Guide_Social_Media {
    private static $instance = null;
    const META_KEY_SHARED_STATUS = '_streaming_guide_social_shared';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // This is the hook our generators will call. It's better than publish_post.
        add_action('streaming_guide_post_generated', [$this, 'handle_automatic_share'], 10, 1);
        
        // You can keep AJAX for manual shares or testing if desired
        add_action('wp_ajax_manual_social_share', [$this, 'ajax_manual_share']);
    }

    /**
     * Entry point for the automatic sharing process.
     *
     * @param int $post_id The ID of the newly created post.
     */
    public function handle_automatic_share($post_id) {
        $options = get_option('sg_social_options', []);

        // Check if Facebook auto-sharing is enabled in settings
        if (!empty($options['auto_share_facebook'])) {
            $this->share_to_facebook($post_id);
        }
    }

    /**
     * Shares a post to Facebook, including all safety checks.
     *
     * @param int $post_id The ID of the post to share.
     * @return bool True on success, false on failure.
     */
    public function share_to_facebook($post_id) {
        // 1. Safety Check: Has this already been shared?
        $is_shared = get_post_meta($post_id, self::META_KEY_SHARED_STATUS, true);
        if ($is_shared) {
            // Optional: Log that we are skipping an already-shared post.
            error_log("[Social] Skipping post #{$post_id} - already shared to Facebook.");
            return false;
        }

        // 2. Get Settings
        $options = get_option('sg_social_options', []);
        $page_id = $options['facebook_page_id'] ?? '';
        $access_token = $options['facebook_access_token'] ?? '';

        if (empty($page_id) || empty($access_token)) {
            error_log("[Social] Cannot share post #{$post_id}: FB Page ID or Access Token is missing from settings.");
            return false;
        }

        // 3. Generate the Dynamic Message
        $message = $this->generate_facebook_message($post_id);
        $post_url = get_permalink($post_id);
        $featured_image_url = get_the_post_thumbnail_url($post_id, 'large');

        // 4. Prepare API Call
        $api_url = "https://graph.facebook.com/v19.0/{$page_id}/feed";
        $body = [
            'message' => $message,
            'link' => $post_url,
            'access_token' => $access_token,
        ];

        // Add picture if available
        if ($featured_image_url) {
            $api_url = "https://graph.facebook.com/v19.0/{$page_id}/photos";
            $body = [
                'caption' => $message . "\n\nRead more: " . $post_url,
                'url' => $featured_image_url,
                'access_token' => $access_token,
            ];
        }

        // 5. Make the API call
        $response = wp_remote_post($api_url, ['body' => $body, 'timeout' => 30]);

        // 6. Handle the Response
        if (is_wp_error($response)) {
            error_log("[Social] Failed to share post #{$post_id}. WP_Error: " . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code === 200 && !empty($response_body['id'])) {
            // SUCCESS! Mark as shared to prevent re-sharing.
            update_post_meta($post_id, self::META_KEY_SHARED_STATUS, 'facebook_success_' . time());
            error_log("[Social] Successfully shared post #{$post_id} to Facebook. Post ID: " . $response_body['id']);
            return true;
        } else {
            // FAILURE! Log the error from Facebook.
            $error_message = $response_body['error']['message'] ?? 'Unknown Facebook API error.';
            error_log("[Social] Failed to share post #{$post_id}. FB Error: " . $error_message);
            // Optionally add a transient to prevent immediate retries on failure
            set_transient('sg_social_share_failed_' . $post_id, true, HOUR_IN_SECONDS);
            return false;
        }
    }

    /**
     * Generates a varied, dynamic message for the Facebook post.
     *
     * @param int $post_id The ID of the post.
     * @return string The formatted message.
     */
    private function generate_facebook_message($post_id) {
        $title = get_the_title($post_id);
        $platform_slug = get_post_meta($post_id, 'streaming_platform', true);
        $platform_name = Streaming_Guide_Platforms::get_platform_name($platform_slug);
        
        // Fetch genre for more dynamic messages
        $tmdb_id = get_post_meta($post_id, 'featured_content_id', true);
        $media_type = get_post_meta($post_id, 'featured_content_type', true);
        $genre = 'new release';
        if ($tmdb_id && $media_type) {
            $tmdb_api = new Streaming_Guide_TMDB_API();
            $details = ($media_type === 'movie') ? $tmdb_api->get_movie_details($tmdb_id) : $tmdb_api->get_tv_details($tmdb_id);
            if (!is_wp_error($details) && !empty($details['genres'][0]['name'])) {
                $genre = strtolower($details['genres'][0]['name']);
            }
        }
        
        $templates = [
            "What to watch on {platform_name} this week? All eyes are on '{title}'. Here's what you need to know.",
            "Reviewers are in agreement: '{title}' on {platform_name} is worth a watch. Streaming now!",
            "{platform_name} just dropped the {genre} of the summer! '{title}' is streaming now.",
            "Our latest spotlight is here! We're diving deep into '{title}', the latest must-see on {platform_name}.",
            "Looking for your next binge-watch? '{title}' has landed on {platform_name} and it's getting all the buzz.",
            "From the big screen to your screen. '{title}' is now available to stream on {platform_name}.",
        ];

        // Pick a random template
        $template = $templates[array_rand($templates)];

        // Replace placeholders
        $message = str_replace(
            ['{platform_name}', '{title}', '{genre}'],
            [$platform_name, $title, $genre],
            $template
        );

        return $message;
    }
}