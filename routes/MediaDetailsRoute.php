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

    for ($i = 0; $i < count($attachments_missing_alt); $i++) {
      $attachment = $attachments_missing_alt[$i]['id'];
      // error_log('$attachment->ID: ' . print_r($attachment, true));
  
      $response = $this->helper->queueImages($attachment);
    }

    return $response;
  }

  public function handle_incoming_caption($request) {
    $data = $request->get_json_params(); // get the images data
    $headers = $request->get_headers(); // get headers

      // if license key matches
      // $images_missing_alt_text_arr = $this->helper->getImagesMissingAltText(); // retrieve all missing alt text from the wordpress media library
      // $this->helper->addAltTextToImage($data, $images_missing_alt_text_arr);

      // Validate if image exists

      // Validate if processing_id matches

      // Validate if alt_text is missing

      // If the above are true, update alt_text, status & timestamp

      // Return a result


    return new \WP_REST_Response('success', 200);

  }

}
