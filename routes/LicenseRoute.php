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

    register_rest_route('altly/v1', 'fetch-license-key', array(
      'methods' => 'GET',
      'callback' => 'get_license_key_from_options',
      'permission_callback' => '__return_true', // Adjust permissions as needed
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
        // $api_url = 'http://localhost:3000/validate/license-key';

        // Check if the license key is already saved and valid
        $saved_license_key = get_option('_altly_license_key');

        if ($saved_license_key === $license_key) {
          $response['message'] = 'License key is already validated and saved.';
        } else {

          $headers = array(
            'license-key' => $license_key,
          );
  
          // Supabase project details
          $supabaseUrl = 'https://lqhlpajntaewohpdqnwk.supabase.co';
          $supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxxaGxwYWpudGFld29ocGRxbndrIiwicm9sZSI6ImFub24iLCJpYXQiOjE2OTQxMDcwODYsImV4cCI6MjAwOTY4MzA4Nn0.IAZhEOHtQaZbUDiJdrqCnRwFkSihcKXdZEVE5NUAZ3s'; // Keep this secure
  
          // Supabase API endpoint to query the 'keys' table
          $apiUrl = $supabaseUrl . '/rest/v1/keys?select=*' . '&key=eq.' . urlencode($license_key);
  
          $headers = array(
              'Content-Type' => 'application/json',
              'apikey' => $supabaseKey,
              'Authorization' => 'Bearer ' . $supabaseKey,
          );
  
          $api_response = wp_remote_get($apiUrl, array(
            'headers' => $headers,
          ));
  
          if (is_wp_error($api_response)) {
            $response['error'] = $api_response->get_error_message();
            $status_code = 500; // Internal Server Error
          } else {
            $api_status = wp_remote_retrieve_response_code($api_response);
            $api_body = wp_remote_retrieve_body($api_response);
            $api_data = json_decode($api_body, true);

            // error_log('API Data: ' . print_r($api_data, true));

  
            if ($api_status == 200 && isset($api_data[0]['key']) && !empty($api_data[0]['key'])) {
              // error_log('Attempting to update license key: ' . $api_data[0]['key']);
              update_option('_altly_license_key', $api_data[0]['key']);
              update_option('_altly_license_key_user_id', $api_data[0]['user_id']);
              // error_log($updated ? 'Update successful' : 'Update failed');
              $response['message'] = 'License key updated successfully';
              $response = array_merge($response, $api_data); // Merge the API data with the response
            } else {
              $response = $api_data ?: ['error' => 'Invalid API response'];
              $status_code = $api_status; // Use the API's response status code
            }
          }
        }
      } else {
        $response['error'] = 'Missing license key.';
        $status_code = 400; // Bad Request
      }
    }

    return new \WP_REST_Response($response, $status_code);
  }

  public function get_license_key_from_options() {
    $license_key = get_option('_altly_license_key');
    error_log('License: ' . print_r($license_key));
    if (!empty($license_key)) {
        return new WP_REST_Response(array('license_key' => $license_key), 200);
    } else {
        return new WP_REST_Response(array('message' => 'License key not found'), 404);
    }
  }

  
}
