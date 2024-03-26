<?php

// Define the namespace for the MediaDetailsRoute class, indicating its location within the project structure.
namespace Altly\AltTextGenerator;

// Class MediaDetailsRoute is responsible for handling custom REST API endpoints related to media details in WordPress.
class MediaDetailsRoute {

  private $utility;
  private $apiBaseUrl = 'https://api.altly.io/v1';

  // Constructor method, automatically called when an instance of the class is created.
  public function __construct() {
    // Hook into the WordPress REST API initialization to register custom endpoints.
    add_action('rest_api_init', array($this, 'register_get_media_details'));
    add_action('rest_api_init', array($this, 'register_bulk_generate'));
    add_action('rest_api_init', array($this, 'register_single_image_upload'));
    add_action('rest_api_init', array($this, 'register_caption_retrieval'));
    $this->utility = new \Altly\AltTextGenerator\Utils();
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
    $image_url = $request->get_param('image_url');
    if (!$image_url) {
        return new WP_Error('no_recent_upload', 'No recent image upload detected.', ['status' => 404]);
    }

    if ($this->utility->getUserCredits() <= 0) {
        return new \WP_REST_Response(['error' => 'No Credits Available'], 500);
    }

    $apiResponse = $this->utility->analyzeImage($this->apiBaseUrl, $image_url);
    if (is_wp_error($apiResponse)) {
        return $apiResponse;  // WP_Error is returned directly
    }

    $attachment_id = attachment_url_to_postid($image_url);
    if ($attachment_id) {
        $this->utility->updateImageAltText($attachment_id, $apiResponse['data'][0]);
        $this->utility->updateUserCredits($this->apiBaseUrl);
    }

    return new \WP_REST_Response(['message' => 'Processed image'] + $apiResponse, 200);
  }

  public function handle_get_media_details($request) {
    $args = $this->utility->defineMediaArgs();
    $media_query = new \WP_Query($args);

    if (!$media_query->have_posts()) {
        return rest_ensure_response(['message' => 'No media found.']);
    }

    $media_details = $this->utility->compileMediaDetails($media_query);
    wp_reset_postdata();

    $response_data = [
        'total_images' => $media_query->found_posts,
        'images_missing_alt_text' => count($this->utility->getImagesMissingAltText()),
        'media_details' => $media_details,
    ];

    return rest_ensure_response($response_data);
  }

  public function handle_bulk_generate($request) {
    $apiUrl = 'https://api.altly.io/v1/batch/queue';

    if ('POST' !== $request->get_method()) {
        return new \WP_REST_Response(['error' => 'Invalid request method'], 405);
    }

    $image_data = $request->get_param('image_data');
    if (empty($image_data)) {
        return new \WP_REST_Response(['error' => 'No image data provided'], 400);
    }

    $imageUrls = array_column($image_data, 'url');
    $response = $this->utility->queueImages($imageUrls, $apiUrl);

    return $response;
  }

  public function handle_incoming_caption($request) {
    $data = $request->get_json_params(); // get the images data
    $headers = $request->get_headers(); // get headers
    $license_key = $headers['license_key'][0]; // extract license-key from headers

    $isLicenseKeyValid = $this->utility->checkLicenseKey($license_key); // check if license key is valid

    if ($isLicenseKeyValid) {
      // if license key matches
      $images_missing_alt_text_arr = $this->utility->getImagesMissingAltText(); // retrieve all missing alt text from the wordpress media library
      $this->utility->addAltTextToImage($data, $images_missing_alt_text_arr);
    }

    return new \WP_REST_Response('Success', 200);

  }

}
