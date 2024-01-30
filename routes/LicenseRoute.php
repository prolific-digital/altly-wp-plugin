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
    $response = array();
    $status_code = 200; // Default status code for OK

    if ('GET' === $method) {
      $license_key = get_option('_altly_license_key');
      if ($license_key) {
        $response['license_key'] = $license_key;
      } else {
        $response['error'] = 'License key not found.';
        $status_code = 404; // Not Found
      }
    } elseif ('POST' === $method) {
      $license_key = $request->get_param('license_key');
      if ($license_key) {
        $api_url = 'http://localhost:3000/validate/license-key';

        $headers = array(
          'license-key' => $license_key,
        );

        $api_response = wp_remote_post($api_url, array(
          'headers' => $headers,
        ));

        if (is_wp_error($api_response)) {
          $response['error'] = $api_response->get_error_message();
          $status_code = 500; // Internal Server Error
        } else {
          $api_status = wp_remote_retrieve_response_code($api_response);
          $api_body = wp_remote_retrieve_body($api_response);
          $api_data = json_decode($api_body, true);

          if ($api_status == 200 && isset($api_data['data']['licenseKey'])) {
            update_option('_altly_license_key', $api_data['data']['licenseKey']);
            $response['message'] = 'License key updated successfully';
            $response = array_merge($response, $api_data); // Merge the API data with the response
          } else {
            $response = $api_data ?: ['error' => 'Invalid API response'];
            $status_code = $api_status; // Use the API's response status code
          }
        }
      } else {
        $response['error'] = 'Missing license key.';
        $status_code = 400; // Bad Request
      }
    }

    return new \WP_REST_Response($response, $status_code);
  }
}
