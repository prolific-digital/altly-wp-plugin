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
    $method = $request->get_method();
    $response = array();
    $status_code = 200; // Default status code for OK
    
    if ('GET' === $method) {
      $user_id = get_option('_altly_license_key_user_id');
      if (!$user_id) {
          return new \WP_REST_Response(['error' => 'User ID not found.'], 400); // Bad Request
      }

      // Supabase project details
      $supabaseUrl = 'https://lqhlpajntaewohpdqnwk.supabase.co';
      $supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImxxaGxwYWpudGFld29ocGRxbndrIiwicm9sZSI6ImFub24iLCJpYXQiOjE2OTQxMDcwODYsImV4cCI6MjAwOTY4MzA4Nn0.IAZhEOHtQaZbUDiJdrqCnRwFkSihcKXdZEVE5NUAZ3s'; // Keep this secure

      $apiUrl = $supabaseUrl . '/rest/v1/accounts?select=*&id=eq.' . urlencode($user_id);
      $headers = array(
          'Content-Type' => 'application/json',
          'apikey' => $supabaseKey,
          'Authorization' => 'Bearer ' . $supabaseKey,
      );

      $api_response = wp_remote_get($apiUrl, array('headers' => $headers));

      if (is_wp_error($api_response)) {
          return new \WP_REST_Response(['error' => $api_response->get_error_message()], 500); // Internal Server Error
      }

      $api_body = wp_remote_retrieve_body($api_response);
      $api_data = json_decode($api_body, true);
      $api_status = wp_remote_retrieve_response_code($api_response);
      error_log('API Data: ' . print_r($api_data, true));

  
      if ($api_status == 200 && isset($api_data[0]['id']) && !empty($api_data[0]['id'])) {
        $response['message'] = 'Data returned successfully';
        $response = array_merge($response, $api_data); // Merge the API data with the response
      } else {
        $response = $api_data ?: ['error' => 'Invalid API response'];
        $status_code = $api_status; // Use the API's response status code
      }

      // if (empty($api_data)) {
      //     return new \WP_REST_Response(['error' => 'No data found for the given user ID.'], 404); // Not Found
      // }

      // $response = $api_data; // Assuming the data structure from Supabase is what you want to return
  }
  
      return new \WP_REST_Response($response, $status_code);
  }

  
}
