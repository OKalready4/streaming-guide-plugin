<?php
/**
 * TMDB Image Fetcher Utility
 * 
 * Allows fetching additional images for movies and TV shows
 */

if (!defined('ABSPATH')) {
    exit;
}

class Upcoming_Movies_Image_Fetcher {
    private $tmdb_api;
    
    public function __construct($tmdb_api) {
        $this->tmdb_api = $tmdb_api;
        
        // Add admin menu item
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_fetch_tmdb_images', array($this, 'ajax_fetch_tmdb_images'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'upcoming-movies',
            __('Fetch Images', 'upcoming-movies'),
            __('Fetch Images', 'upcoming-movies'),
            'manage_options',
            'upcoming-movies-fetch-images',
            array($this, 'render_fetch_images_page')
        );
    }
    
    /**
     * Render the fetch images page
     */
    public function render_fetch_images_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Fetch TMDB Images', 'upcoming-movies'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Fetch Additional Images from TMDB', 'upcoming-movies'); ?></h2>
                <p><?php _e('Enter a TMDB ID to fetch additional backdrop images to your media library.', 'upcoming-movies'); ?></p>
                
                <form id="fetch-images-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="tmdb_id"><?php _e('TMDB ID', 'upcoming-movies'); ?></label></th>
                            <td>
                                <input type="number" id="tmdb_id" name="tmdb_id" class="regular-text" required>
                                <p class="description"><?php _e('Enter the TMDB ID for a movie or TV show', 'upcoming-movies'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="content_type"><?php _e('Content Type', 'upcoming-movies'); ?></label></th>
                            <td>
                                <select id="content_type" name="content_type">
                                    <option value="movie"><?php _e('Movie', 'upcoming-movies'); ?></option>
                                    <option value="tv"><?php _e('TV Show', 'upcoming-movies'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="image_count"><?php _e('Number of Images', 'upcoming-movies'); ?></label></th>
                            <td>
                                <input type="number" id="image_count" name="image_count" value="5" min="1" max="20" class="small-text">
                                <p class="description"><?php _e('How many backdrop images to fetch (max 20)', 'upcoming-movies'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Fetch Images', 'upcoming-movies'); ?></button>
                        <span class="spinner" style="float: none;"></span>
                    </p>
                </form>
                
                <div id="fetch-results" style="display: none;">
                    <h3><?php _e('Fetched Images', 'upcoming-movies'); ?></h3>
                    <div id="fetched-images-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#fetch-images-form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $spinner = $form.find('.spinner');
                const $results = $('#fetch-results');
                const $grid = $('#fetched-images-grid');
                
                $spinner.addClass('is-active');
                $grid.empty();
                $results.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fetch_tmdb_images',
                        tmdb_id: $('#tmdb_id').val(),
                        content_type: $('#content_type').val(),
                        image_count: $('#image_count').val(),
                        nonce: '<?php echo wp_create_nonce('fetch_tmdb_images'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.show();
                            
                            response.data.images.forEach(function(image) {
                                const $item = $('<div class="image-item" style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">');
                                $item.append('<img src="' + image.url + '" style="width: 100%; height: auto; border-radius: 3px;">');
                                $item.append('<p style="margin: 10px 0 5px; font-size: 12px;"><strong>ID:</strong> ' + image.attachment_id + '</p>');
                                $item.append('<p style="margin: 0; font-size: 12px;"><a href="' + image.edit_url + '" target="_blank">Edit in Media Library</a></p>');
                                $grid.append($item);
                            });
                            
                            alert(response.data.message);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Network error occurred');
                    },
                    complete: function() {
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for fetching images
     */
    public function ajax_fetch_tmdb_images() {
        check_ajax_referer('fetch_tmdb_images', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $tmdb_id = intval($_POST['tmdb_id']);
        $content_type = sanitize_text_field($_POST['content_type']);
        $image_count = min(20, max(1, intval($_POST['image_count'])));
        
        if (empty($tmdb_id)) {
            wp_send_json_error('Invalid TMDB ID');
        }
        
        // Get images from TMDB
        $endpoint = $content_type === 'tv' ? "/tv/{$tmdb_id}/images" : "/movie/{$tmdb_id}/images";
        $images_data = $this->tmdb_api->make_request($endpoint, array(
            'include_image_language' => 'en,null'
        ));
        
        if (is_wp_error($images_data)) {
            wp_send_json_error($images_data->get_error_message());
        }
        
        if (!isset($images_data['backdrops']) || empty($images_data['backdrops'])) {
            wp_send_json_error('No backdrop images found');
        }
        
        // Get title for the content
        $title = 'TMDB ' . $tmdb_id;
        if ($content_type === 'tv') {
            $details = $this->tmdb_api->get_tv_details($tmdb_id);
            if (!is_wp_error($details) && isset($details['name'])) {
                $title = $details['name'];
            }
        } else {
            $details = $this->tmdb_api->get_movie_details($tmdb_id);
            if (!is_wp_error($details) && isset($details['title'])) {
                $title = $details['title'];
            }
        }
        
        // Download images
        $downloaded_images = array();
        $backdrops = array_slice($images_data['backdrops'], 0, $image_count);
        
        foreach ($backdrops as $index => $backdrop) {
            if (isset($backdrop['file_path'])) {
                $image_url = 'https://image.tmdb.org/t/p/original' . $backdrop['file_path'];
                $description = $title . ' - Backdrop ' . ($index + 1);
                
                // Use the main plugin's sideload method
                $attachment_id = $this->sideload_image($image_url, 0, $description);
                
                if (!is_wp_error($attachment_id)) {
                    // Add metadata
                    update_post_meta($attachment_id, '_tmdb_id', $tmdb_id);
                    update_post_meta($attachment_id, '_tmdb_content_type', $content_type);
                    update_post_meta($attachment_id, '_upcoming_movies_image_type', 'backdrop');
                    update_post_meta($attachment_id, '_upcoming_movies_image_orientation', 'landscape');
                    
                    $downloaded_images[] = array(
                        'attachment_id' => $attachment_id,
                        'url' => wp_get_attachment_url($attachment_id),
                        'edit_url' => get_edit_post_link($attachment_id)
                    );
                }
            }
        }
        
        if (empty($downloaded_images)) {
            wp_send_json_error('Failed to download any images');
        }
        
        wp_send_json_success(array(
            'message' => sprintf('Successfully downloaded %d images to your media library', count($downloaded_images)),
            'images' => $downloaded_images
        ));
    }
    
    /**
     * Sideload image helper
     */
    private function sideload_image($url, $post_id = 0, $description = '') {
        $tmp = download_url($url, 30);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $file_array = array(
            'name' => basename($url),
            'tmp_name' => $tmp
        );
        
        $id = media_handle_sideload($file_array, $post_id, $description);
        @unlink($tmp);
        
        return $id;
    }
}