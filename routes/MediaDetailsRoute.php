<?php

// Define the namespace for the MediaDetailsRoute class, indicating its location within the project structure.
namespace Altly\AltTextGenerator;

// Class MediaDetailsRoute is responsible for handling custom REST API endpoints related to media details in WordPress.
class MediaDetailsRoute {

  private $apiBaseUrl = 'https://api.altly.io/v1';

  // Constructor method, automatically called when an instance of the class is created.
  public function __construct() {
    // Hook into the WordPress REST API initialization to register custom endpoints.
    add_action('rest_api_init', array($this, 'register_get_media_details'));
    add_action('rest_api_init', array($this, 'register_bulk_generate'));
    add_action('rest_api_init', array($this, 'register_single_image_upload'));
    add_action('rest_api_init', array($this, 'register_caption_retrieval'));
  }

  // Registers a REST route for retrieving media details.
  public function register_caption_retrieval() {
    // Define a new route in the WordPress REST API namespace 'altly/v1' with the endpoint '/get-media-details'.
    register_rest_route('altly/v1', '/retrieve_caption', array(
      'methods' => 'POST', // Specify that this endpoint responds to HTTP GET requests.
      'callback' => array($this, 'handle_incoming_caption'), // Callback function to process the request.
      'permission_callback' => '__return_true', // Permission callback to control access; here, it allows all requests.
    ));
  }

  // Registers a REST route for retrieving media details.
  public function register_get_media_details() {
    // Define a new route in the WordPress REST API namespace 'altly/v1' with the endpoint '/get-media-details'.
    register_rest_route('altly/v1', '/get-media-details', array(
      'methods' => 'GET', // Specify that this endpoint responds to HTTP GET requests.
      'callback' => array($this, 'handle_get_media_details'), // Callback function to process the request.
      'permission_callback' => '__return_true', // Permission callback to control access; here, it allows all requests.
    ));
  }

  // Registers a REST route for bulk generating alt text for images.
  public function register_bulk_generate() {
    // Define a new route in the WordPress REST API for bulk generation with the endpoint '/bulk-generate'.
    register_rest_route('altly/v1', '/bulk-generate', array(
      'methods' => 'POST', // Specify that this endpoint responds to HTTP POST requests.
      'callback' => array($this, 'handle_bulk_generate'), // Callback function to process the request.
      'permission_callback' => '__return_true', // Permission callback to control access; here, it allows all requests.
    ));
  }

  // Registers a REST route for handling single image upload and alt text generation.
  public function register_single_image_upload() {
    // Define a new route in the WordPress REST API for single image upload with the endpoint '/handle-single-image-upload'.
    register_rest_route('altly/v1', '/handle-single-image-upload', array(
      'methods' => 'POST', // Specify that this endpoint responds to HTTP POST requests.
      'callback' => array($this, 'handle_single_image_upload'), // Callback function to process the request.
      'permission_callback' => '__return_true', // Permission callback to control access; here, it allows all requests.
    ));
  }

  // (Optional) A method to check user permissions before allowing access to certain endpoints.
  public function check_permission() {
    // Returns true if the user is logged in and has the 'manage_options' capability, ensuring administrative access.
    return is_user_logged_in() && current_user_can('manage_options');
  }

  public function handle_single_image_upload($request) {
    $utility = new \ImageUtility();

    $image_url = $request->get_param('image_url');
    if (!$image_url) {
        return new WP_Error('no_recent_upload', 'No recent image upload detected.', ['status' => 404]);
    }

    if ($utility->getUserCredits() <= 0) {
        return new \WP_REST_Response(['error' => 'No Credits Available'], 500);
    }

    $apiResponse = $utility->analyzeImage($this->apiBaseUrl, $image_url);
    if (is_wp_error($apiResponse)) {
        return $apiResponse;  // WP_Error is returned directly
    }

    $attachment_id = attachment_url_to_postid($image_url);
    if ($attachment_id) {
        $utility->updateImageAltText($attachment_id, $apiResponse['data'][0]);
        $utility->updateUserCredits($this->apiBaseUrl);
    }

    return new \WP_REST_Response(['message' => 'Processed image'] + $apiResponse, 200);
  }

  // Handles the request to retrieve media details.
  public function handle_get_media_details($request) {
    $args = $this->defineMediaArgs();

    // Execute the query to retrieve media attachments based on the specified arguments.
    $media_query = new \WP_Query($args);
    $media_details = array(); // Initialize an array to hold the media details.

    // Check if the query returned any posts (media attachments).
    if ($media_query->have_posts()) {
      // Loop through the media attachments.
      while ($media_query->have_posts()) {
        $media_query->the_post(); // Set up the global post data.
        $attachment_id = get_the_ID(); // Retrieve the ID of the current attachment.
        $image_url = wp_get_attachment_url($attachment_id); // Get the URL of the attachment.
        $file_path = get_attached_file($attachment_id); // Retrieve the file path of the attachment.

        // Retrieve additional metadata associated with the attachment.
        $attachment_metadata = wp_get_attachment_metadata($attachment_id);

        // Retrieve the alt text & confidence_score assigned to the attachment.
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $confidence_score = get_post_meta($attachment_id, 'confidence_score', true);

        // update_post_meta($attachment_id, 'confidence_score', sanitize_text_field($confidence_score));

        // Compile the details for the current attachment.
        $current_media_details = array(
          'id' => $attachment_id,
          'alt_text' => esc_attr($alt_text),
          'confidence_score' => esc_attr($confidence_score),
          'url' => esc_url($image_url),
          'file_path' => esc_url($file_path), // Note: Consider a different method for sanitizing file paths.
          'metadata' => $attachment_metadata,
        );

        // Add to the main media details array.
        $media_details[] = $current_media_details;
      }
      
      wp_reset_postdata(); // Reset the global post data to avoid conflicts with other queries.

    } else {
      // Return a response indicating that no media was found if the query returned no posts.
      return rest_ensure_response(array('message' => 'No media found.'));
    }

    // Retrieve the total number of images found by the query.
    $total_images = $media_query->found_posts;

    wp_reset_postdata(); // Reset the global post data again for good measure.

    // Prepare the response data including the total number of images, the count of images missing alt text, and the detailed media information.
    $response_data = array(
      'total_images' => $total_images, // Include the total number of images.
      'images_missing_alt_text' => count($this->getImagesMissingAltText()), // Include the count of images missing alt text.
      'media_details' => $media_details, // Include the array of media details.
    );

    // Return the prepared response data wrapped in a rest_ensure_response() for consistency with the REST API response format.
    return rest_ensure_response($response_data);
  }

  public function handle_bulk_generate($request) {
    $method = $request->get_method();

    if ('POST' === $method) {
      $image_data = $request->get_param('image_data');

      if (!empty($image_data)) {
        // Retrieve the stored license key option from the WordPress database.
        $license_key = get_option('_altly_license_key');

        // Initialize an empty array to store image URLs.
        $imageUrls = [];
        
        foreach ($image_data as $item) {
          $imageUrls[] = $item['url'];
        }
        
        // Define the API URL
        $apiUrl = 'https://api.altly.io/v1/batch/queue';

        // Prepare the headers for the API request, including the Content-Type and license key.
        $headers = ['Content-Type' => 'application/json', 'license-key' => $license_key];

        // Encode the current item's image URL into the JSON body of the request.
        $body = json_encode([
          'images' => $imageUrls
        ]);
    
        // Perform a POST request to the external API with the prepared headers and body.
        $api_response = wp_remote_post($apiUrl, [
          'headers' => $headers,
          'body'    => $body,
        ]);

        // Check if the API response is a WordPress error.
        if (is_wp_error($api_response)) {
          // Extract the error message from the API response.
          $error_message = $api_response->get_error_message();

          error_log('error_message: ' . print_r($error_message, true));
          return new \WP_REST_Response(['error' => $error_message], 500);
        }

        $api_status = wp_remote_retrieve_response_code($api_response);
        $api_data = json_decode(wp_remote_retrieve_body($api_response), true);

        error_log('API Response: ' . print_r($api_data, true));

        if ($api_status == 200) {
          return new \WP_REST_Response(['message' => 'Images queued successfully'] + $api_data, 200);
        }
    
        return new \WP_REST_Response($api_data ?: ['error' => 'Invalid API response'], $api_status ?: 500);

        // error_log('Image Data: ' . print_r($imageUrls, true));

      }
    }
  }

  public function handle_incoming_caption($request) {
    
    $data = $request->get_json_params(); // get the images data
    $headers = $request->get_headers(); // get headers
    $license_key = $headers['license_key'][0]; // extract license-key from headers

    $isLicenseKeyValid = $this->checkLicenseKey($license_key); // check if license key is valid

    if ($isLicenseKeyValid) {
      // if license key matches
      $images_missing_alt_text_arr = $this->getImagesMissingAltText(); // retrieve all missing alt text from the wordpress media library
      $this->addAltTextToImage($data, $images_missing_alt_text_arr);
    }

    return new \WP_REST_Response('Success', 200);

  }

  protected function addAltTextToImage($data, $images_missing_alt_text_arr) {
    if (count($images_missing_alt_text_arr) > 0) {
      // loop over the data and get matching image IDs
      // Extract IDs from $images_missing_alt_text_arr
      $missingAltTextAttachmentIDs = array_column($images_missing_alt_text_arr, 'id');

      foreach ($data['images'] as $item) {
        $attachment_id = $item['cms_id'];
        if (in_array($attachment_id, $missingAltTextAttachmentIDs)) {
            $altText = $item['alt_text'];
            // $confidenceScore = $item['confidence'];

            // update alt text for all images missing alts
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($altText));
            // update_post_meta($attachment_id, 'confidence_score', sanitize_text_field($confidenceScore));
        }
      }
    }
  }

  protected function checkLicenseKey($license_key) {
    if ($license_key === get_option('_altly_license_key')) {
      // error_log('Valid license key: ' . print_r(get_option('_altly_license_key'), true));
      return true;
    }

    return false;
  }

  protected function getImagesMissingAltText() {

    // Set up query arguments to retrieve media attachments of type 'image/jpeg' and 'image/png'.
    $args = $this->defineMediaArgs();

    $media_query = new \WP_Query($args);
    $images_missing_alt_text_arr = array();

    // Check if the query returned any posts (media attachments).
    if ($media_query->have_posts()) {
      // Loop through the media attachments.
      while ($media_query->have_posts()) {
        $media_query->the_post(); // Set up the global post data.
        $attachment_id = get_the_ID(); // Retrieve the ID of the current attachment.
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $image_url = wp_get_attachment_url($attachment_id); // Get the URL of the attachment.
        $file_path = get_attached_file($attachment_id); // Retrieve the file path of the attachment.
        $attachment_metadata = wp_get_attachment_metadata($attachment_id);

        // Compile the details for the current attachment.
        $current_media_details = array(
          'id' => $attachment_id,
          'alt_text' => esc_attr($alt_text),
          'url' => esc_url($image_url),
          'file_path' => esc_url($file_path), // Note: Consider a different method for sanitizing file paths.
          'metadata' => $attachment_metadata,
        );

        // Check if the alt text is missing and add to the missing alt text array.
        if (empty($alt_text)) {
          $images_missing_alt_text_arr[] = $current_media_details;
        }
      }
      wp_reset_postdata(); // Reset the global post data to avoid conflicts with other queries.
    } else {
      // Return a response indicating that no media was found if the query returned no posts.
      return rest_ensure_response(array('message' => 'No media found.'));
    }
    wp_reset_postdata(); // Reset the global post data again for good measure.

    return $images_missing_alt_text_arr;
  }

  protected function defineMediaArgs() {
    // Set up query arguments to retrieve media attachments of type 'image/jpeg' and 'image/png'.
    $args = array(
      'post_type' => 'attachment', // Target media attachments.
      'post_status' => 'inherit', // Include all attachments regardless of status.
      'post_mime_type' => array('image/jpeg', 'image/png'), // Filter by JPEG and PNG image formats.
      'posts_per_page' => -1, // Retrieve all matching media items.
    );

    return $args;
  }


}
