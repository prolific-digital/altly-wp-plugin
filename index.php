<?php

// define('WP_ENV', 'development'); // Change this value based on your environment

/*
Plugin Name: Altly - Alt Text Generator
Version: 1.1.5
Description: A plugin to generate alt text for images using AI
Author: Prolific Digital
*/

require_once __DIR__ . '/vendor/autoload.php';

use Ramsey\Uuid\Uuid;

$license_key_route = new Altly\AltTextGenerator\LicenseRoute();
$media_details = new Altly\AltTextGenerator\MediaDetailsRoute();
$user_route = new Altly\AltTextGenerator\UserRoute();


function altly_root()
{
  // Add your settings content here
  echo '<div id="root"></div>';
}

function enqueue_ai_alt_text_script()
{
  $current_screen = get_current_screen();

  // Check if the current screen is your plugin's settings page
  if ($current_screen->id === 'media_page_altly') {

    // Enqueue your script with a unique handle, source URL, and any necessary dependencies
    wp_enqueue_script(
      'altly', // Unique handle
      plugin_dir_url(__FILE__) . '/app/dist/assets/index-e117008b.js', // Source URL
      array(), // Dependencies (if any)
      '5', // Version
      true // Load script in the footer
    );

    // Enqueue your CSS with a unique handle, source URL, and any necessary dependencies
    wp_enqueue_style(
      'altly', // Unique handle
      plugin_dir_url(__FILE__) . '/app/dist/assets/index-939e5311.css', // Source URL
      array(), // Dependencies (if any)
      '5' // Version
    );
  }
}

add_action('admin_enqueue_scripts', 'enqueue_ai_alt_text_script');

function add_prolific_tools_submenu()
{
  add_submenu_page(
    'upload.php', // Parent menu slug (Tools)
    'Altly', // Page title
    'Altly', // Menu title
    'manage_options', // Required capability
    'altly', // Menu slug
    'altly_root' // Callback function to display content
  );
}

add_action('admin_menu', 'add_prolific_tools_submenu');

function add_settings_script()
{
  echo '<script>
          var script = document.createElement("script");
          script.src = "http://localhost:3001/src/main.jsx";
          script.type = "module";
          document.body.appendChild(script);
        </script>';
}

// add_action('admin_footer', 'add_settings_script');


function disable_rest_authentication($access)
{
  return true; // Return true to disable authentication
}
add_filter('rest_authentication_errors', 'disable_rest_authentication');




function is_vite_running()
{
  // Replace this with the actual URL of your Vite development server
  $vite_server_url = 'http://localhost:3001';

  // Use cURL to check if the Vite server is running
  $response = wp_remote_request($vite_server_url);

  // Check if the response status code is 200 (OK)
  return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
}



function enqueue_hmr_client()
{
  if (is_vite_running()) {

    echo '
    <script type="module">
    import RefreshRuntime from "http://localhost:3001/@react-refresh"
    RefreshRuntime.injectIntoGlobalHook(window)
    window.$RefreshReg$ = () => {}
    window.$RefreshSig$ = () => (type) => type
    window.__vite_plugin_react_preamble_installed__ = true
</script>
';

    echo '<script type="module" src="http://localhost:3001/@vite/client"></script>';
    // echo '<script type="module" src="http://localhost:3001/@react-refresh"></script>';
  }
}


function add_script()
{
  if (is_vite_running()) {
    echo '<script type="module" src="http://localhost:3001/src/main.jsx"></script>';
  }
}


add_action('admin_head', 'enqueue_hmr_client');
add_action('admin_footer', 'add_script');


function analyze_image_on_upload($attachment_id)
{

  // send a request to altly to analyze the image
  $results = analyzeImagev2($attachment_id);

  if (!is_wp_error($results)) {
    // store the response back to the image
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $results['data'][0]['metadata']['alt_text']);
    update_post_meta($attachment_id, 'altly_processing_timestamp', $results['data'][0]['platform']['timestamp']);
    update_post_meta($attachment_id, 'altly_processing_status', 'processed');
  }
}

function analyzeImagev2($attachment_id)
{
  $helper = new Altly\AltTextGenerator\Helpers();


  $apiUrl = 'https://api.altly.io/v1/analyze/image';

  $image_url = wp_get_attachment_url($attachment_id);

  $license_key = get_option('_altly_license_key');

  if (!$license_key) {
    return new WP_Error('no_license_key', 'License key not found.');
  }

  $user_credits = $helper->getUserCredits();

  if ($user_credits < 1) {
    return new WP_Error('no_credits', 'Not enough credits to analyze image.');
  }

  $headers = ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $license_key];

  $processing_id = Uuid::uuid4()->toString();

  update_post_meta($attachment_id, 'altly_processing_id', sanitize_text_field($processing_id));

  $api_url = home_url() . '/wp-json/altly/v1/process-response';

  $images = [
    [
      "url" => $image_url,
      "api_endpoint" => $api_url, // this might change
      "asset_id" => $attachment_id,
      'transaction_id' => $processing_id,
      "platform" => "WordPress"
    ]
  ];

  $jsonBody = json_encode(['images' => $images]);

  $api_response = wp_remote_post($apiUrl, ['headers' => $headers, 'body' => $jsonBody]);

  if (is_wp_error($api_response)) {
    return new WP_Error('api_error', $api_response->get_error_message());
  }

  $api_status = wp_remote_retrieve_response_code($api_response);
  $api_data = json_decode(wp_remote_retrieve_body($api_response), true);

  if ($api_status != 200) {
    return new WP_Error('api_failure', 'API call failed', ['status' => $api_status]);
  }

  return $api_data;
}

add_action('add_attachment', 'analyze_image_on_upload');

// error_log('API Response: ' . print_r($api_response, true));
