<?php

/**
 * Plugin Name: Altly - AI Text Generator
 * Description: Generates detailed alt text for images using AI.
 * Version: 1.0.0
 * Author: Your Name
 */

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Define plugin directory and API endpoints.
define('ALTLY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALTLY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALTLY_API_VALIDATE_URL', 'https://api.altly.ai/v2/validate');
define('ALTLY_API_QUEUE_URL', 'https://api.altly.ai/v2/queue');

/**
 * Plugin activation hook.
 */
function altly_activate() {
  add_option('altly_license_key', '');
}
register_activation_hook(__FILE__, 'altly_activate');

/**
 * Display admin notice after activation if no license key is set.
 */
function altly_admin_notice() {
  if (! current_user_can('manage_options')) {
    return;
  }
  // Avoid displaying notice on the plugin’s own settings page.
  if (isset($_GET['page']) && $_GET['page'] === 'altly-settings') {
    return;
  }
  $license_key = get_option('altly_license_key', '');
  if (empty($license_key)) {
    $settings_url = admin_url('tools.php?page=altly-settings');
    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p>Altly plugin requires an API license key. Please <a href="' . esc_url($settings_url) . '">enter your API key here</a>.</p>';
    echo '</div>';
  }
}
add_action('admin_notices', 'altly_admin_notice');

/**
 * Register admin menu page under Tools.
 */
function altly_register_admin_page() {
  add_management_page(
    'Altly - AI Text Generator',
    'Altly - AI Text Generator',
    'manage_options',
    'altly-settings',
    'altly_render_admin_page'
  );
}
add_action('admin_menu', 'altly_register_admin_page');

/**
 * Render the admin page container (React will mount here).
 */
function altly_render_admin_page() {
?>
  <div class="wrap" id="altly-admin-app">
    <!-- React App will be rendered here -->
  </div>
<?php
}

/**
 * Enqueue admin scripts and styles on our settings page.
 */
function altly_enqueue_admin_scripts($hook) {
  if ('tools_page_altly-settings' !== $hook) {
    return;
  }

  // Enqueue the JS file.
  $script_path = ALTLY_PLUGIN_DIR . 'build/index.js';
  wp_enqueue_script(
    'altly-admin-script',
    ALTLY_PLUGIN_URL . 'build/index.js',
    array('wp-element', 'wp-components', 'wp-api'),
    file_exists($script_path) ? filemtime($script_path) : '1.0.0',
    true
  );

  // Enqueue the CSS file if it exists.
  $style_path = ALTLY_PLUGIN_DIR . 'build/index.css';
  if (file_exists($style_path)) {
    wp_enqueue_style(
      'altly-admin-style',
      ALTLY_PLUGIN_URL . 'build/index.css',
      array(),
      filemtime($style_path)
    );
  } else {
    // Optionally enqueue a fallback or simply skip it.
    wp_enqueue_style(
      'altly-admin-style',
      ALTLY_PLUGIN_URL . 'build/index.css',
      array(),
      '1.0.0'
    );
  }

  // Pass necessary settings to our React app.
  wp_localize_script('altly-admin-script', 'AltlySettings', array(
    'apiKey'  => get_option('altly_license_key', ''),
    'restUrl' => esc_url_raw(rest_url('altly/v1/')),
    'nonce'   => wp_create_nonce('wp_rest'),
  ));
}
add_action('admin_enqueue_scripts', 'altly_enqueue_admin_scripts');


/**
 * Register REST API endpoints.
 */
add_action('rest_api_init', function () {

  // Endpoint: Retrieve images missing alt text.
  register_rest_route('altly/v1', '/images', array(
    'methods'  => 'GET',
    'callback' => 'altly_get_images_missing_alt',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));

  // Endpoint: Validate license key.
  register_rest_route('altly/v1', '/validate-key', array(
    'methods'  => 'POST',
    'callback' => 'altly_validate_license_key',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));

  // Endpoint: Save license key.
  register_rest_route('altly/v1', '/save-key', array(
    'methods' => 'POST',
    'callback' => 'altly_save_license_key',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    }
  ));

  // Endpoint: Bulk generate alt text.
  register_rest_route('altly/v1', '/bulk-generate', array(
    'methods'  => 'POST',
    'callback' => 'altly_bulk_generate',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

/**
 * Callback to retrieve paginated images missing alt text along with overall stats.
 */
function altly_get_images_missing_alt($request) {
  // Use intval instead of absint.
  $per_page = isset($request['per_page']) ? intval($request['per_page']) : 12;
  // If a negative number is passed (like -1), we want all images.
  if ($per_page < 0) {
    $per_page = -1;
  }

  $page = isset($request['page']) ? absint($request['page']) : 1;

  // Only allow JPEG and PNG.
  $allowed_mime_types = array('image/jpeg', 'image/png');

  $args_missing = array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => $allowed_mime_types,
    'posts_per_page' => $per_page,
    'paged'          => $page,
    'meta_query'     => array(
      'relation' => 'OR',
      array(
        'key'     => '_wp_attachment_image_alt',
        'compare' => 'NOT EXISTS',
      ),
      array(
        'key'     => '_wp_attachment_image_alt',
        'value'   => '',
        'compare' => '='
      )
    ),
  );
  $query_missing = new WP_Query($args_missing);
  $missing_images = array();

  foreach ($query_missing->posts as $post) {
    $filePath = get_attached_file($post->ID);
    $missing_images[] = array(
      'id'       => $post->ID,
      'link'     => admin_url('upload.php?item=' . $post->ID),
      'src'      => wp_get_attachment_url($post->ID),
      'filePath' => $filePath,
      'size'     => size_format(filesize($filePath)),
      'queued'   => get_post_meta($post->ID, '_altly_queued', true) ? true : false,
    );
  }

  // Query for total images – only include allowed MIME types.
  $args_total = array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => $allowed_mime_types,
    'posts_per_page' => -1,
  );
  $query_total = new WP_Query($args_total);
  $total_images = $query_total->found_posts;

  $response_data = array(
    'images' => $missing_images,
    'stats'  => array(
      'total_images'      => $total_images,
      'missing_alt_count' => $query_missing->found_posts,
    ),
  );
  $response = rest_ensure_response($response_data);
  $response->header('X-WP-TotalPages', $query_missing->max_num_pages);
  return $response;
}



// In your plugin file (e.g. altly-ai-text-generator.php)
add_action('rest_api_init', function () {
  register_rest_route('altly/v1', '/mark-queued', array(
    'methods' => 'POST',
    'callback' => 'altly_mark_queued',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

function altly_mark_queued($request) {
  $params = $request->get_json_params();
  if (empty($params['image_id'])) {
    return new WP_Error('missing_image_id', 'Missing image ID', array('status' => 400));
  }
  $image_id = intval($params['image_id']);
  // Mark the image as queued by setting a custom meta field.
  update_post_meta($image_id, '_altly_queued', true);
  return rest_ensure_response(array('success' => true, 'message' => 'Image marked as queued.'));
}

add_action('rest_api_init', function () {
  register_rest_route('altly/v1', '/receive-alt', array(
    'methods'             => 'POST',
    'callback'            => 'altly_receive_alt_text',
    'permission_callback' => 'altly_validate_api_key_for_receive_alt',
  ));
});

function altly_validate_api_key_for_receive_alt(WP_REST_Request $request) {
  $params = $request->get_json_params();
  if (empty($params)) {
    $raw = wp_unslash(file_get_contents('php://input'));
    error_log("altly_validate_api_key_for_receive_alt raw body: " . $raw);
    $params = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON decode error: " . json_last_error_msg());
    }
  }
  if (!isset($params['api_key'])) {
    return new WP_Error('missing_api_key', 'Missing API key', array('status' => 401));
  }
  $provided_key = sanitize_text_field($params['api_key']);
  $stored_key   = get_option('altly_license_key');
  return ($provided_key === $stored_key);
}

function altly_receive_alt_text(WP_REST_Request $request) {
  $params = $request->get_json_params();
  if (empty($params)) {
    $raw = wp_unslash(file_get_contents('php://input'));
    error_log("altly_receive_alt_text raw body: " . $raw);
    $params = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON decode error in receive_alt: " . json_last_error_msg());
    }
  }

  error_log("Decoded params: " . print_r($params, true));

  if (!isset($params['image_id']) || !isset($params['alt_text']) || !isset($params['api_key'])) {
    error_log("Missing parameters: " . print_r($params, true));
    return new WP_Error('missing_params', 'Missing parameters', array('status' => 400));
  }

  $image_id = intval($params['image_id']);
  $alt_text = sanitize_text_field($params['alt_text']);

  // Verify that the image exists and is an attachment.
  $image_post = get_post($image_id);
  if (!$image_post || $image_post->post_type !== 'attachment') {
    error_log("Image not found or not an attachment: ID $image_id");
    return new WP_Error('invalid_image', 'Invalid image ID', array('status' => 400));
  }

  $updated = update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);
  if (!$updated) {
    error_log("Failed to update alt text for image ID $image_id");
    return new WP_Error('update_failed', 'Failed to update alt text', array('status' => 500));
  }

  return rest_ensure_response(array('success' => true, 'message' => 'Alt text updated.'));
}


add_action('rest_api_init', function () {
  register_rest_route('altly/v1', '/clear-alt-text', array(
    'methods' => 'POST',
    'callback' => 'altly_clear_all_alt_text',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

function altly_clear_all_alt_text() {
  global $wpdb;

  // Remove alt text from all images in the media library
  $wpdb->query("
        DELETE FROM $wpdb->postmeta
        WHERE meta_key = '_wp_attachment_image_alt'
    ");

  return rest_ensure_response(array(
    'success' => true,
    'message' => 'All image alt text has been cleared.',
  ));
}

add_action('rest_api_init', function () {
  register_rest_route('altly/v1', '/clear-queue', array(
    'methods' => 'POST',
    'callback' => 'altly_clear_queue',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

function altly_clear_queue($request) {
  $params = $request->get_json_params();
  if (empty($params['image_ids']) || !is_array($params['image_ids'])) {
    return new WP_Error('no_images', 'No image IDs provided', array('status' => 400));
  }
  foreach ($params['image_ids'] as $image_id) {
    delete_post_meta(intval($image_id), '_altly_queued');
  }
  return rest_ensure_response(array('success' => true, 'message' => 'Queue cleared for provided images.'));
}

/**
 * Callback to validate the license key using the /v2/validate endpoint.
 */
function altly_validate_license_key($request) {
  $params = $request->get_json_params();
  $license_key = isset($params['licenseKey']) ? sanitize_text_field($params['licenseKey']) : '';

  if (empty($license_key)) {
    return new WP_Error('no_license', 'License key is required', array('status' => 400));
  }

  $response = wp_remote_post(ALTLY_API_VALIDATE_URL, array(
    'headers' => array(
      'Authorization' => 'Bearer ' . $license_key,
    ),
    'body'    => array(),
    'timeout' => 15,
  ));

  if (is_wp_error($response)) {
    return new WP_Error('api_error', 'Failed to connect to validation API', array('status' => 500));
  }

  $body = json_decode(wp_remote_retrieve_body($response), true);

  if (isset($body['error'])) {
    return new WP_Error('invalid_license', $body['error'], array('status' => 401));
  }

  return rest_ensure_response($body);
}

/**
 * Callback to save the license key.
 */
function altly_save_license_key($request) {
  $params = $request->get_json_params();
  if (empty($params['licenseKey'])) {
    return new WP_Error('no_license', 'License key is required', array('status' => 400));
  }

  // (Optional) You can perform additional validation here.

  update_option('altly_license_key', sanitize_text_field($params['licenseKey']));
  return rest_ensure_response(array('success' => true, 'message' => 'License key saved.'));
}

/**
 * Callback for bulk generating alt text by sending images to the /v2/queue endpoint.
 */
function altly_bulk_generate($request) {
  $license_key = get_option('altly_license_key', '');
  if (empty($license_key)) {
    return new WP_Error('no_license', 'No license key found', array('status' => 400));
  }

  // Query images missing alt text.
  $args = array(
    'post_type'      => 'attachment',
    'post_mime_type' => 'image',
    'posts_per_page' => -1,
    'meta_query'     => array(
      array(
        'key'     => '_wp_attachment_image_alt',
        'compare' => 'NOT EXISTS',
      ),
    ),
  );
  $query = new WP_Query($args);
  $images = $query->posts;
  $results = array();

  foreach ($images as $image) {
    $file_path = get_attached_file($image->ID);
    $filetype = wp_check_filetype($file_path);
    $allowed_types = array('image/jpeg', 'image/png', 'image/jpg');

    if (! in_array($filetype['type'], $allowed_types)) {
      $results[] = array('id' => $image->ID, 'status' => 'skipped', 'reason' => 'Unsupported file type');
      continue;
    }

    if (filesize($file_path) > 4 * 1024 * 1024) {
      $results[] = array('id' => $image->ID, 'status' => 'skipped', 'reason' => 'File size exceeds 4MB limit');
      continue;
    }

    // Prepare file for upload.
    $file = new CURLFile($file_path, $filetype['type'], basename($file_path));
    $post_fields = array(
      'file'         => $file,
      'platform_id'  => get_bloginfo('name'),
      'platform_url' => home_url(),
    );

    // Use cURL for the multipart/form-data POST.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, ALTLY_API_QUEUE_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer ' . $license_key,
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
      $results[] = array('id' => $image->ID, 'status' => 'failed', 'reason' => $err);
    } else {
      $response_data = json_decode($response, true);
      if (isset($response_data['success']) && $response_data['success'] === true) {
        $results[] = array('id' => $image->ID, 'status' => 'queued', 'message' => $response_data['message']);
      } else {
        $results[] = array('id' => $image->ID, 'status' => 'failed', 'reason' => isset($response_data['error']) ? $response_data['error'] : 'Unknown error');
      }
    }
  }

  return rest_ensure_response($results);
}
?>