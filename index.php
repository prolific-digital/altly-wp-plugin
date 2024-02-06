<?php

// define('WP_ENV', 'development'); // Change this value based on your environment

/*
Plugin Name: Altly - Alt Text Generator
Version: 1.0
Description: A plugin to generate alt text for images using AI
Author: Prolific Digital
*/

require_once __DIR__ . '/vendor/autoload.php';

$license_key_route = new Altly\AltTextGenerator\LicenseRoute();
$media_details = new Altly\AltTextGenerator\MediaDetailsRoute();
$user_route = new Altly\AltTextGenerator\UserRoute();


function altly_root() {
  // Add your settings content here
  echo '<div id="root"></div>';
}

function enqueue_ai_alt_text_script() {
  $current_screen = get_current_screen();

  // Check if the current screen is your plugin's settings page
  if ($current_screen->id === 'media_page_altly') {

    // Enqueue your script with a unique handle, source URL, and any necessary dependencies
    wp_enqueue_script(
      'altly', // Unique handle
      plugin_dir_url(__FILE__) . '/app/dist/assets/index.js', // Source URL
      array(), // Dependencies (if any)
      '6', // Version
      true // Load script in the footer
    );

    // Enqueue your CSS with a unique handle, source URL, and any necessary dependencies
    wp_enqueue_style(
      'altly', // Unique handle
      plugin_dir_url(__FILE__) . '/app/dist/assets/index.css', // Source URL
      array(), // Dependencies (if any)
      '6' // Version
    );
  }
}

add_action('admin_enqueue_scripts', 'enqueue_ai_alt_text_script');

function add_prolific_tools_submenu() {
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

// function enqueue_vite_hmr_client() {
//   echo '
//   <script type="module">
//     import RefreshRuntime from "http://localhost:3001/@react-refresh"
//     RefreshRuntime.injectIntoGlobalHook(window)
//     window.$RefreshReg$ = () => {}
//     window.$RefreshSig$ = () => (type) => type
//     window.__vite_plugin_react_preamble_installed__ = true
// </script>
//   <script type="module" src="http://localhost:3001/@vite/client"></script>';
// }

// add_action('admin_head', 'enqueue_vite_hmr_client');

function add_settings_script() {
  echo '<script>
          var script = document.createElement("script");
          script.src = "http://localhost:3001/src/main.jsx";
          script.type = "module";
          document.body.appendChild(script);
        </script>';
}

// add_action('admin_footer', 'add_settings_script');


function disable_rest_authentication($access) {
  return true; // Return true to disable authentication
}
add_filter('rest_authentication_errors', 'disable_rest_authentication');




function is_vite_running() {
  // Replace this with the actual URL of your Vite development server
  $vite_server_url = 'http://localhost:3001';

  // Use cURL to check if the Vite server is running
  $response = wp_remote_request($vite_server_url);

  // Check if the response status code is 200 (OK)
  return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
}



function enqueue_hmr_client() {
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


function add_script() {
  if (is_vite_running()) {
    echo '<script type="module" src="http://localhost:3001/src/main.jsx"></script>';
  }
}


add_action('admin_head', 'enqueue_hmr_client');
add_action('admin_footer', 'add_script');
