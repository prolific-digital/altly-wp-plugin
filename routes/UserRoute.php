<?php

namespace Altly\AltTextGenerator;

class UserRoute
{
  private $helper;

  public function __construct()
  {
    add_action('rest_api_init', array($this, 'register_user_route'));
    $this->helper = new \Altly\AltTextGenerator\Helpers();
  }

  public function register_user_route()
  {
    register_rest_route('altly/v1', 'get-user-credits', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_user_credits'),
      'permission_callback' => '__return_true', // Custom permission callback
    ));
  }

  public function get_user_credits($request)
  {
    return $this->helper->getUserCredits();
  }
}
