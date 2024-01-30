<?php

namespace Altly\AltTextGenerator;

class MediaDetailsRoute {
  public function __construct() {
    add_action('rest_api_init', array($this, 'register_get_media_details'));
  }

  public function register_get_media_details() {
    register_rest_route('altly/v1', '/get-media-details', array(
      'methods' => 'GET',
      'callback' => array($this, 'handle_get_media_details'),
      // 'permission_callback' => array($this, 'check_permission'), // Custom permission callback
      'permission_callback' => '__return_true', // Custom permission callback
    ));
  }

  public function check_permission() {
    // Check if the user is logged in and has the necessary capabilities
    return is_user_logged_in() && current_user_can('manage_options'); // Adjust the capability as needed
  }

  public function handle_get_media_details($request) {
    // Validate the request, retrieve data, and perform alt text generation
    // Ensure that the user is authenticated and has the required permissions

    $args = array(
      'post_type' => 'attachment', // Specifies media attachments
      'post_status' => 'inherit', // Retrieve all statuses
      'post_mime_type' => array('image/jpeg', 'image/png'), // Filter by JPEG and PNG MIME types
      'posts_per_page' => -1, // Retrieve all media files
    );

    $media_query = new \WP_Query($args);
    $media_details = array(); // Array to store media details
    $images_missing_alt_text = 0; // Counter for images missing alt text

    if ($media_query->have_posts()) {
      while ($media_query->have_posts()) {
        $media_query->the_post();
        $attachment_id = get_the_ID();
        $image_url = wp_get_attachment_url($attachment_id);
        $file_path = get_attached_file($attachment_id); // Get the file path

        // Get additional media data using wp_get_attachment_metadata
        $attachment_metadata = wp_get_attachment_metadata($attachment_id);

        // Get the alt text
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

        // Check if alt text is missing
        if (empty($alt_text)) {
          $images_missing_alt_text++;
        }

        // Store media details in an array, including image ID
        $media_details[] = array(
          'id' => $attachment_id, // Include the image ID
          'alt_text' => esc_attr($alt_text),
          'url' => esc_url($image_url),
          'file_path' => esc_url($file_path),
          'metadata' => $attachment_metadata,
        );
      }
      wp_reset_postdata();
    } else {
      // No media found.
      return rest_ensure_response(array('message' => 'No media found.'));
    }

    $total_images = $media_query->found_posts;
    wp_reset_postdata();

    // Output the total number of images and their details as JSON
    $response_data = array(
      'total_images' => $total_images,
      'images_missing_alt_text' => $images_missing_alt_text,
      'media_details' => $media_details,
    );

    return rest_ensure_response($response_data);
  }
}
