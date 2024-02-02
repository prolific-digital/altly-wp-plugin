<?php

namespace Altly\AltTextGenerator;

class LicenseRoute {
  public function __construct() {
    add_action('rest_api_init', array($this, 'register_license_key_route'));
  }

  public function register_license_key_route() {
    register_rest_route('altly/v1', 'license-key', array(
      'methods' => 'GET, POST', // Allow both GET and POST requests
      'callback' => array($this, 'handle_license_key'),
      'permission_callback' => '__return_true', // Custom permission callback
    ));
  }

  public function handle_license_key($request) {
    $method = $request->get_method();

    if ('GET' === $method) {
      return $this->handleGetRequest();
    } elseif ('POST' === $method) {
      return $this->handlePostRequest($request);
    }

    // Optionally, handle other methods or return a method not allowed response
    return new \WP_REST_Response(['error' => 'Method Not Allowed'], 405);
  }

  protected function handleGetRequest() {
    $license_key = get_option('_altly_license_key');
    if ($license_key) {
      return new \WP_REST_Response(['license_key' => $license_key], 200);
    }
    return new \WP_REST_Response(['error' => 'License key not found.'], 404);
  }

  protected function handlePostRequest($request) {
    $license_key = $request->get_param('license_key');
    if (!$license_key) {
      return new \WP_REST_Response(['error' => 'Missing license key.'], 400);
    }

    $saved_license_key = get_option('_altly_license_key');
    if ($saved_license_key === $license_key) {
      return new \WP_REST_Response(['message' => 'License key is already validated and saved.'], 200);
    }

    return $this->validateAndSaveLicenseKey($license_key);
  }

  protected function validateAndSaveLicenseKey($license_key) {
    $api_response = $this->callExternalApi($license_key);
    if (is_wp_error($api_response)) {
      return new \WP_REST_Response(['error' => $api_response->get_error_message()], 500);
    }

    $api_status = wp_remote_retrieve_response_code($api_response);
    $api_data = json_decode(wp_remote_retrieve_body($api_response), true);

    if ($api_status == 200 && isset($api_data['key']) && !empty($api_data['key'])) {
      update_option('_altly_license_key', $api_data['key']);
      update_option('_altly_license_key_user_id', $api_data['user_id']);
      return new \WP_REST_Response(['message' => 'License key updated successfully'] + $api_data, 200);
    }

    return new \WP_REST_Response($api_data ?: ['error' => 'Invalid API response'], $api_status ?: 500);
  }

  protected function callExternalApi($license_key) {
    $apiUrl = 'http://localhost:3000/api/validate/key';
    $headers = ['Content-Type' => 'application/json'];
    $body = json_encode(['key' => $license_key]);

    return wp_remote_post($apiUrl, [
      'headers' => $headers,
      'body'    => $body,
    ]);
  }

  
}
