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

    $credits = get_option('_altly_license_key_user_credits');

    return $credits;
  }
  
}
