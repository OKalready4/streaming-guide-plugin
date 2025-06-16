<?php
/**
 * SEO Manager
 * Provides a UI and framework for managing SEO settings and optimizations.
 */
if (!defined('ABSPATH')) exit;

class Streaming_Guide_SEO_Manager {

    public function __construct() {
        // This class is instantiated by the main admin page.
        // It doesn't need its own menu hooks if called from there.
        add_action('admin_init', array($this, 'register_settings'));
        add_action('save_post', array($this, 'on_save_post'), 10, 2);
    }

    public function register_settings() {
        register_setting('streaming_guide_seo_settings', 'sg_seo_options', ['sanitize_callback' => array($this, 'sanitize_seo_options')]);
    }

    public function sanitize_seo_options($input) {
        $sanitized = [];
        $sanitized['add_schema'] = isset($input['add_schema']) ? '1' : '0';
        $sanitized['yoast_integration'] = isset($input['yoast_integration']) ? '1' : '0';
        // Add more settings as needed
        return $sanitized;
    }

    /**
     * This is the hook that runs when a post is created by our generators.
     * It's where we add our SEO magic.
     */
    public function on_save_post($post_id, $post) {
        // Check if this is a generated post and not a revision
        if (wp_is_post_revision($post_id) || get_post_meta($post_id, '_streaming_guide_generated', true) !== '1') {
            return;
        }

        // Avoid an infinite loop
        if (get_post_meta($post_id, '_seo_processed', true) === '1') {
            return;
        }
        
        $options = get_option('sg_seo_options', []);

        // 1. Add Schema Markup
        if (isset($options['add_schema']) && $options['add_schema'] === '1') {
            $this->add_schema_markup($post_id);
        }

        // 2. Integrate with Yoast/Rank Math
        if (isset($options['yoast_integration']) && $options['yoast_integration'] === '1') {
            $this->populate_seo_plugin_fields($post_id, $post);
        }
        
        // Mark as processed to prevent re-running
        update_post_meta($post_id, '_seo_processed', '1');
    }
    
    /**
     * Adds Movie or TVSeries Schema Markup to a post.
     */
    private function add_schema_markup($post_id) {
        $tmdb_id = get_post_meta($post_id, 'featured_content_id', true);
        $type = get_post_meta($post_id, 'featured_content_type', true);
        
        if (!$tmdb_id || !$type) return;

        // Fetch details needed for schema
        $tmdb_api = new Streaming_Guide_TMDB_API();
        $details = ($type === 'movie') ? $tmdb_api->get_movie_details($tmdb_id) : $tmdb_api->get_tv_details($tmdb_id);
        if (is_wp_error($details)) return;

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => ($type === 'movie') ? 'Movie' : 'TVSeries',
            'name'     => get_the_title($post_id),
            'url'      => get_permalink($post_id),
            'datePublished' => $details['release_date'] ?? $details['first_air_date'] ?? get_the_date('c', $post_id),
            'image'    => get_the_post_thumbnail_url($post_id, 'full'),
            'description' => get_the_excerpt($post_id),
        ];

        if (isset($details['vote_average']) && $details['vote_average'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($details['vote_average'], 1),
                'bestRating' => '10',
                'ratingCount' => $details['vote_count'] ?? 0,
            ];
        }

        // Save schema as post meta. Another plugin or theme function can inject this into the post's <head>.
        update_post_meta($post_id, '_schema_markup', json_encode($schema, JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * Populates fields for popular SEO plugins.
     */
    private function populate_seo_plugin_fields($post_id, $post) {
        // Generate a meta description from the post excerpt or content
        $meta_desc = has_excerpt($post->ID) ? get_the_excerpt($post->ID) : wp_trim_words($post->post_content, 25, '...');

        // For Yoast SEO
        if (is_plugin_active('wordpress-seo/wp-seo.php')) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
            // You can also set focus keyword, etc.
        }

        // For Rank Math
        if (is_plugin_active('seo-by-rank-math/rank-math.php')) {
            update_post_meta($post_id, 'rank_math_description', $meta_desc);
        }
    }

    public function render_seo_page() {
        $options = get_option('sg_seo_options', []);
        ?>
        <div class="card">
            <h2>SEO & Schema Settings</h2>
            <p>Automate SEO tasks for your generated content.</p>
            <form method="post" action="options.php">
                <?php settings_fields('streaming_guide_seo_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Add Schema Markup</th>
                        <td>
                            <label><input type="checkbox" name="sg_seo_options[add_schema]" value="1" <?php checked($options['add_schema'] ?? '', '1'); ?>>
                            Automatically add Movie/TVSeries JSON-LD Schema to generated posts.</label>
                            <p class="description">This is highly recommended for better search engine visibility.</p>
                        </td>
                    </tr>
                     <tr>
                        <th scope="row">SEO Plugin Integration</th>
                        <td>
                            <label><input type="checkbox" name="sg_seo_options[yoast_integration]" value="1" <?php checked($options['yoast_integration'] ?? '', '1'); ?>>
                            Automatically populate Meta Descriptions for Yoast & Rank Math.</label>
                             <p class="description">Requires a supported SEO plugin (Yoast or Rank Math) to be active.</p>
                        </td>
                    </tr>
                </table>
                 <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}