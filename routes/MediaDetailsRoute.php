<?php

// Define the namespace for the MediaDetailsRoute class, indicating its location within the project structure.
namespace Altly\AltTextGenerator;

// Class MediaDetailsRoute is responsible for handling custom REST API endpoints related to media details in WordPress.
class MediaDetailsRoute {

  // Constructor method, automatically called when an instance of the class is created.
  public function __construct() {
    // Hook into the WordPress REST API initialization to register custom endpoints.
    add_action('rest_api_init', array($this, 'register_get_media_details'));
    add_action('rest_api_init', array($this, 'register_bulk_generate'));
    add_action('rest_api_init', array($this, 'register_single_image_upload'));
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

  // Handles the single image upload and initiates alt text generation.
  public function handle_single_image_upload($request) {
    // Retrieve the 'image_url' parameter from the REST request.
    $image_url = $request->get_param('image_url');

    // Check if the 'image_url' parameter is missing or empty.
    if (!$image_url) {
      // Return a WP_Error indicating that no image URL was provided.
      return new WP_Error('no_recent_upload', 'No recent image upload detected.', array('status' => 404));
    }

    // Retrieve the stored license key option from the WordPress database.
    $license_key = get_option('_altly_license_key');

    // Fetch the current user's available credits for using the service.
    $creditsAvailable = get_option('_altly_license_key_user_credits');

    // error_log('API Response: ' . print_r($creditsAvailable, true));

    // Check if the user has at least one credit available.
    if ($creditsAvailable > 0) {

      // Define the API URL for analyzing the image and generating alt text.
      $apiUrl = 'https://api.altly.io/v1/analyze/image';
      
      // Prepare the headers for the API request, including the Content-Type and license key.
      $headers = ['Content-Type' => 'application/json', 'license-key' => $license_key];

      // Encode the image URL into the JSON body of the request.
      $body = json_encode(['images' => $image_url]);
  
      // Perform a POST request to the external API with the prepared headers and body.
      $api_response = wp_remote_post($apiUrl, [
        'headers' => $headers,
        'body'    => $body,
      ]);

      // Check if the API response is a WordPress error.
      if (is_wp_error($api_response)) {
        // Extract the error message from the API response.
        return new \WP_REST_Response(['error' => $api_response->get_error_message()], 500);
      } else {
        // Retrieve the HTTP status code and the response body from the API response.
        $api_status = wp_remote_retrieve_response_code($api_response);
        $api_data = json_decode(wp_remote_retrieve_body($api_response), true);

        // This api_data needs to return the source as well.

        error_log('API Response: ' . print_r($api_data, true));
    
        // Check if the API response status code is 200 (OK).
        if ($api_status == 200) {
          // Retrieve the attachment ID of the Image
          $attachment_id = attachment_url_to_postid($image_url);
          if ($attachment_id) {
            // Update the alt text for the attachment in the WordPress database.
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($api_data['data'][0]['caption']));
            update_post_meta($attachment_id, 'confidence_score', sanitize_text_field($api_data['data'][0]['confidence']));
          }
          return new \WP_REST_Response(['message' => 'Processed image'] + $api_data['data'][0], 200);
        }

        return new \WP_REST_Response($api_data ?: ['error' => 'Invalid API response'], $api_status ?: 500);
      }
      
    } else {
      // Return an error response indicating that no credits are available for alt text generation.
      return new \WP_REST_Response(['error' => 'No Credits Available'], 500);
    }
  }

  // Handles the request to retrieve media details.
  public function handle_get_media_details($request) {
    // Set up query arguments to retrieve media attachments of type 'image/jpeg' and 'image/png'.
    $args = array(
      'post_type' => 'attachment', // Target media attachments.
      'post_status' => 'inherit', // Include all attachments regardless of status.
      'post_mime_type' => array('image/jpeg', 'image/png'), // Filter by JPEG and PNG image formats.
      'posts_per_page' => -1, // Retrieve all matching media items.
    );

    // Execute the query to retrieve media attachments based on the specified arguments.
    $media_query = new \WP_Query($args);
    $media_details = array(); // Initialize an array to hold the media details.
    $images_missing_alt_text = 0; // Initialize a counter for images lacking alt text.
    $images_missing_alt_text_arr = array(); // Initialize an array to hold details of images lacking alt text.

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

    // Retrieve the total number of images found by the query.
    $total_images = $media_query->found_posts;
    wp_reset_postdata(); // Reset the global post data again for good measure.
    
    // Process each media item
    $successful_updates = 0; // Initialize a counter for successfully updated images
      
    // Retrieve the user ID associated with the license key from WordPress options. This ID is used for API calls related to the user.
    $user_id = get_option('_altly_license_key_user_id');

    if ($user_id) {

      // Convert your list of image URLs into an associative array with URLs as keys for faster lookup
      $imageUrlsAssoc = array_flip(array_column($images_missing_alt_text_arr, 'url'));
  
      // error_log('API Data: ' . print_r($imageUrls, true));
  
      // Define the API URL for batch processing
      $apiUrlBatch = 'https://api.altly.io/api/image/retrieve-batch';
  
      // Prepare the headers for the API request
      $headers = ['Content-Type' => 'application/json'];

      // Encode the user ID and the chunk of image source URLs into the JSON body of the request
      $body = json_encode([
          'userId' => $user_id
      ]);
  
      // Perform a POST request to the external API with each chunk
      $api_response = wp_remote_post($apiUrlBatch, [
          'headers' => $headers,
          'body'    => $body,
      ]);
  
      // Handle the response for each chunk
      if (!is_wp_error($api_response)) {
          $api_status = wp_remote_retrieve_response_code($api_response);
          $api_data = json_decode(wp_remote_retrieve_body($api_response), true);
          if ($api_status == 200 && !empty($api_data)) {
            foreach ($api_data as $imageData) {
                // Check if the URL from the API response exists as a key in your associative array
                if (isset($imageUrlsAssoc[$imageData['source']])) {
                    $attachment_id = attachment_url_to_postid($imageData['source']);
                    if ($attachment_id) {
                        $alt_text = $imageData['caption']; // Adjust according to actual API response structure
                        $confidence_score = $imageData['confidence']; // Adjust according to actual API response structure
    
                        // Update the alt text and confidence score for the matched image
                        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
                        update_post_meta($attachment_id, 'confidence_score', sanitize_text_field($confidence_score));
    
                        error_log("Success: Updated attachment ID {$attachment_id} with new alt text.");
                    } else {
                        error_log('Error: Unable to find attachment ID for URL ' . $imageData['image_url']);
                    }
                }
            }
          } else {
              // Handle non-200 responses or empty API data for the chunk
              if ($api_status != 200) {
                  error_log("API Error for chunk: Received status code {$api_status}");
              } elseif (empty($api_data)) {
                  error_log("API Error for chunk: Data is empty or invalid JSON");
              }
              error_log('API Response for chunk: ' . print_r($api_response, true));
          }
      } else {
          // Handle WP_Error for the chunk
          $error_message = $api_response->get_error_message();
          error_log("WP_Error for chunk: {$error_message}");
      }
    }

    // Prepare the response data including the total number of images, the count of images missing alt text, and the detailed media information.
    $response_data = array(
      'total_images' => $total_images, // Include the total number of images.
      'images_missing_alt_text' => count($images_missing_alt_text_arr), // Include the count of images missing alt text.
      'media_details' => $media_details, // Include the array of media details.
      'api_data' => $api_data,
      'error' => $api_response
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
          'images' => $imageUrls,
          'licenseKey' => $license_key
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
          return new \WP_REST_Response(['error' => $error_message], 500);
        }

        $api_status = wp_remote_retrieve_response_code($api_response);
        $api_data = json_decode(wp_remote_retrieve_body($api_response), true);

        if ($api_status == 200) {
          return new \WP_REST_Response(['message' => 'Images queued successfully'] + $api_data, 200);
        }
    
        return new \WP_REST_Response($api_data ?: ['error' => 'Invalid API response'], $api_status ?: 500);

        // error_log('Image Data: ' . print_r($imageUrls, true));

      }
    }
  }

}
