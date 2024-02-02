<?php

namespace Altly\AltTextGenerator;

class UserRoute {
  public function __construct() {
    add_action('rest_api_init', array($this, 'register_user_route'));
  }

  public function register_user_route() {
    register_rest_route('altly/v1', 'get-user-credits', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_user_credits'),
      'permission_callback' => '__return_true', // Custom permission callback
    ));
  }

  public function get_user_credits($request) {
    if ('GET' !== $request->get_method()) {
      // Optionally handle non-GET requests or return a method not allowed response
      return new \WP_REST_Response(['error' => 'Method Not Allowed'], 405);
    }

    return $this->fetchUserCredits();
  }

  protected function fetchUserCredits() {
    $user_id = get_option('_altly_license_key_user_id');
    if (!$user_id) {
      return new \WP_REST_Response(['error' => 'User ID not found.'], 400); // Bad Request
    }

    $api_response = $this->callUserCreditsApi($user_id);
    if (is_wp_error($api_response)) {
      return new \WP_REST_Response(['error' => $api_response->get_error_message()], 500); // Internal Server Error
    }

    $api_data = json_decode(wp_remote_retrieve_body($api_response), true);
    $api_status = wp_remote_retrieve_response_code($api_response);

    // error_log('API Data: ' . print_r($api_data, true));

    if ($api_status == 200 && isset($api_data['id']) && !empty($api_data['id'])) {
      return new \WP_REST_Response(['message' => 'Data returned successfully'] + $api_data, 200);
    }

    return new \WP_REST_Response($api_data ?: ['error' => 'Invalid API response'], $api_status ?: 500);
  }

  protected function callUserCreditsApi($user_id) {
    $apiUrl = 'http://localhost:3000/api/validate/user';
    $headers = ['Content-Type' => 'application/json'];
    $body = json_encode(['id' => $user_id]);

    return wp_remote_post($apiUrl, [
        'headers' => $headers,
        'body'    => $body,
    ]);
  }
  
}
