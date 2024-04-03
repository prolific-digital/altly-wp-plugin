<?php

// Define the namespace for the MediaDetailsRoute class, indicating its location within the project structure.
namespace Altly\AltTextGenerator;

// Class MediaDetailsRoute is responsible for handling custom REST API endpoints related to media details in WordPress.
class MediaDetailsRoute {

  private $helper;

  // Constructor method, automatically called when an instance of the class is created.
  public function __construct() {
    // Hook into the WordPress REST API initialization to register custom endpoints.
    add_action('rest_api_init', array($this, 'register_get_media_details'));
    add_action('rest_api_init', array($this, 'register_bulk_generate'));
    add_action('rest_api_init', array($this, 'register_caption_retrieval'));
    $this->helper = new \Altly\AltTextGenerator\Helpers();
  }

  // Registers a REST route for retrieving media details.
  public function register_caption_retrieval() {
    // Define a new route in the WordPress REST API namespace 'altly/v1' with the endpoint '/get-media-details'.
    register_rest_route('altly/v1', '/process-response', array(
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

  // (Optional) A method to check user permissions before allowing access to certain endpoints.
  public function check_permission() {
    // Returns true if the user is logged in and has the 'manage_options' capability, ensuring administrative access.
    return is_user_logged_in() && current_user_can('manage_options');
  }

  public function handle_get_media_details($request) {
    $args = $this->helper->defineMediaArgs();
    $media_query = new \WP_Query($args);

    if (!$media_query->have_posts()) {
        return rest_ensure_response(['message' => 'No media found.']);
    }

    $media_details = $this->helper->compileMediaDetails($media_query);
    wp_reset_postdata();

    $response_data = [
        'total_images' => $media_query->found_posts,
        'images_missing_alt_text' => count($this->helper->getImagesMissingAltText()),
        'media_details' => $media_details,
    ];

    return rest_ensure_response($response_data);
  }

  public function handle_bulk_generate($request) {

    if ('POST' !== $request->get_method()) {
        return new \WP_REST_Response(['error' => 'Invalid request method'], 405);
    }

    // get all media missing alt text
    $attachments_missing_alt = $this->helper->getImagesMissingAltText();

    foreach ($attachments_missing_alt as $attachment) {
      $response = $this->helper->queueImages($attachment['id']);
    }

    return $response;
  }

  public function handle_incoming_caption($request) {
    $data = $request->get_json_params(); // Get the data from the request

    error_log('API Response: ' . print_r($data, true));

    // Check if 'data' key exists and it's an array
    if (isset($data['data']) && is_array($data['data'])) {
      // Directly access 'metadata' and 'platform' without iterating
      $meta_data = $data['data']['metadata'] ?? null; // Using null coalescing operator
      $platform = $data['data']['platform'] ?? null; // Using null coalescing operator

      if ($meta_data && $platform) {
        $attachment_id = $platform['asset_id'] ?? null; // Check for 'asset_id'
        $processing_id = $platform['transaction_id'] ?? null; // Check for 'transaction_id'
        // The 'timestamp' key doesn't seem to be provided in your data structure
        // $timestamp = $platform['timestamp'] ?? null; // Assuming 'timestamp' exists

        $generated_alt_text = $meta_data['alt_text'] ?? ''; // Check for 'alt_text'

        if ($attachment_id && $processing_id) {
            $attachment = get_post($attachment_id);

          // Validate if image exists and is an attachment
          if ($attachment && $attachment->post_type === 'attachment') {
              $attachment_processing_id = get_post_meta($attachment_id, 'altly_processing_id', true);

            // Validate if processing_id matches
            if ($processing_id === $attachment_processing_id) {
                $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

              // Validate if alt_text is missing
              if (empty($alt_text)) {
                  update_post_meta($attachment_id, '_wp_attachment_image_alt', $generated_alt_text);
                  // Assuming you have a way to get the correct timestamp since it's not provided in the data
                  // update_post_meta($attachment_id, 'altly_processing_timestamp', $timestamp);
                  update_post_meta($attachment_id, 'altly_processing_status', 'processed');

                  // Return a result or perform additional actions
              }
            }
          }
        }
      }
    }

    return new \WP_REST_Response('success', 200);
  }


}


// error_log('API Response: ' . print_r($alt_text, true));