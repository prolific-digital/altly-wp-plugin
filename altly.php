<?php

/**
 * Plugin Name: Altly - AI Text Generator
 * Description: Generates detailed alt text for images using AI.
 * Version: 1.0.0
 * Author: Prolific Digital
 * Author URI: https://altly.ai
 * Text Domain: altly
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * License: GPL-2.0-or-later
 * Tested up to: 6.7
 */

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

// Define plugin directory and API endpoints.
define('ALTLY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALTLY_PLUGIN_URL', plugin_dir_url(__FILE__));
// Single API base so every v2 endpoint stays on one host (keeps the API host in
// sync — never split hosts). Overridable by pre-defining ALTLY_API_BASE_URL
// (e.g. in wp-config.php for a local zero-spend E2E run); defaults to prod.
if (! defined('ALTLY_API_BASE_URL')) {
  define('ALTLY_API_BASE_URL', 'https://api.altly.io/v2');
}
define('ALTLY_API_VALIDATE_URL', ALTLY_API_BASE_URL . '/validate');
define('ALTLY_API_QUEUE_URL', ALTLY_API_BASE_URL . '/queue');
// Pull-model endpoints: this plugin polls for finished alt text and acks it,
// rather than relying on the API pushing back to receive-alt. See
// altly_sync_results() below.
define('ALTLY_API_RESULTS_URL', ALTLY_API_BASE_URL . '/results');
define('ALTLY_API_RESULTS_ACK_URL', ALTLY_API_BASE_URL . '/results/ack');

/**
 * The wp-cron hook name for the pull backstop. A recurring event runs
 * altly_sync_results() so low-traffic sites (and sites whose admin never clicks
 * "Sync results") still pull finished alt text.
 */
define('ALTLY_SYNC_CRON_HOOK', 'altly_sync_results_cron');

/**
 * Plugin activation hook.
 */
function altly_activate() {
  add_option('altly_license_key', '');
  altly_ensure_sync_cron();
}
register_activation_hook(__FILE__, 'altly_activate');

/**
 * Plugin deactivation hook: tear down the recurring pull event.
 */
function altly_deactivate() {
  wp_clear_scheduled_hook(ALTLY_SYNC_CRON_HOOK);
}
register_deactivation_hook(__FILE__, 'altly_deactivate');

/**
 * Register the recurring pull event if it isn't already scheduled. Guards
 * against double-registration via wp_next_scheduled(). Called on activation and
 * on init, so installs updated in place (activation hook doesn't re-run on a
 * plugin update) still get the cron scheduled.
 */
function altly_ensure_sync_cron() {
  if (! wp_next_scheduled(ALTLY_SYNC_CRON_HOOK)) {
    wp_schedule_event(time(), 'hourly', ALTLY_SYNC_CRON_HOOK);
  }
}
add_action('init', 'altly_ensure_sync_cron');

// The recurring event pulls finished alt text. altly_sync_results() no-ops
// cleanly when no license key is configured.
add_action(ALTLY_SYNC_CRON_HOOK, 'altly_sync_results');

/**
 * Canonical site host, derived server-side from home_url(). This is the single
 * source of truth for `platform_id` — it MUST be byte-identical on both the
 * browser enqueue (localized as AltlySettings.siteHost) and the server-side
 * pull/ack requests, because the API scopes queued rows by (user, platform_id).
 * If the two ever diverge, pull silently returns nothing.
 *
 * MULTISITE LIMITATION: on a WordPress *subdirectory* multisite, every subsite
 * shares one host, so this returns the same value for all of them. Subsites that
 * share one Altly license key therefore collide on (user_id, platform_id) — and
 * because the API normalizes platform_id to host-only (any path is stripped), a
 * path-based distinguisher cannot fix it. Such installs MUST use a SEPARATE
 * Altly license key per subsite. Subdomain multisite and single-site installs
 * are unaffected (each has a distinct host).
 */
function altly_site_host() {
  $host = wp_parse_url(home_url(), PHP_URL_HOST);
  return $host ? $host : '';
}

/**
 * Display admin notice after activation if no license key is set.
 */
function altly_admin_notice() {
  if (! current_user_can('manage_options')) {
    return;
  }
  // Sanitize GET input.
  $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
  // Avoid displaying notice on the plugin’s own settings page.
  if ('altly' === $page) {
    return;
  }
  $license_key = get_option('altly_license_key', '');
  if (empty($license_key)) {
    // Updated settings URL: now under Media.
    $settings_url = admin_url('upload.php?page=altly');
    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p>Generate Alt Text plugin requires an API license key. Please <a href="' . esc_url($settings_url) . '">enter your API key here</a>.</p>';
    echo '</div>';
  }
}
add_action('admin_notices', 'altly_admin_notice');

/**
 * Register admin menu page under Media.
 */
function altly_register_admin_page() {
  add_submenu_page(
    'upload.php',         // Parent slug for Media
    'Generate Alt Text',  // Page title
    'Generate Alt Text',  // Menu title
    'manage_options',
    'altly',     // Page slug
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
  // The hook for a submenu under Media is "upload_page_altly"
  if ('media_page_altly' !== $hook) {
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
    'apiKey'      => get_option('altly_license_key', ''),
    'restUrl'     => esc_url_raw(rest_url('altly/v1/')),
    'nonce'       => wp_create_nonce('wp_rest'),
    // Account-level default speed tier ("instant" | "relaxed"). Sent as the
    // `mode` field on uploads; per-run toggle can override it.
    'defaultMode' => get_option('altly_default_mode', 'instant'),
    // Canonical, server-derived site host. The browser enqueue sends this as
    // `platform_id` so it is byte-identical to the value the PHP pull/ack path
    // uses (altly_site_host()). Do NOT swap this back to window.location.host —
    // that carries the port and would diverge from the server value, and the
    // API scopes queued rows by (user, platform_id).
    'siteHost'    => altly_site_host(),
  ));
}
add_action('admin_enqueue_scripts', 'altly_enqueue_admin_scripts');

/**
 * Register REST API endpoints.
 */
add_action('rest_api_init', function () {

  // Endpoint: Retrieve images missing alt text.
  register_rest_route('altly/v1', '/images', array(
    'methods'             => 'GET',
    'callback'            => 'altly_get_images_missing_alt',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));

  // Endpoint: Validate license key.
  register_rest_route('altly/v1', '/validate-key', array(
    'methods'             => 'POST',
    'callback'            => 'altly_validate_license_key',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));

  // Endpoint: Save license key.
  register_rest_route('altly/v1', '/save-key', array(
    'methods'             => 'POST',
    'callback'            => 'altly_save_license_key',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));

  // Endpoint: Save the account default generation speed ("instant" | "relaxed").
  register_rest_route('altly/v1', '/save-mode', array(
    'methods'             => 'POST',
    'callback'            => 'altly_save_default_mode',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));

  // Endpoint: Pull finished alt text from the API and write it locally.
  register_rest_route('altly/v1', '/sync-results', array(
    'methods'             => 'POST',
    'callback'            => 'altly_sync_results_endpoint',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

/**
 * Nonce verification helper.
 */
function altly_verify_rest_nonce() {
  // Unsash and sanitize the nonce value.
  $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
  if (! $nonce || ! wp_verify_nonce($nonce, 'wp_rest')) {
    return false;
  }
  return true;
}

/**
 * Callback to retrieve paginated images missing alt text along with overall stats.
 */
function altly_get_images_missing_alt($request) {
  $per_page = isset($request['per_page']) ? intval($request['per_page']) : 12;
  if ($per_page < 0) {
    $per_page = -1;
  }

  $args_missing = array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => array('image/jpeg', 'image/png'),
    'posts_per_page' => $per_page,
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

  // If fetching all images, disable paging.
  if ($per_page == -1) {
    $args_missing['nopaging'] = true;
  } else {
    $page                  = isset($request['page']) ? absint($request['page']) : 1;
    $args_missing['paged'] = $page;
  }

  $query_missing  = new WP_Query($args_missing);
  $missing_images = array();

  foreach ($query_missing->posts as $post) {
    $filePath = get_attached_file($post->ID);
    $size     = 'N/A';
    if (file_exists($filePath)) {
      $size = size_format(filesize($filePath));
    }

    $missing_images[] = array(
      'id'       => $post->ID,
      'link'     => admin_url('upload.php?item=' . $post->ID),
      'src'      => wp_get_attachment_url($post->ID),
      'filePath' => $filePath,
      'size'     => $size,
      'queued'   => get_post_meta($post->ID, '_altly_queued', true) ? true : false,
    );
  }

  // Query for total images.
  $args_total  = array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => array('image/jpeg', 'image/png'),
    'posts_per_page' => -1,
    'nopaging'       => true,
  );
  $query_total = new WP_Query($args_total);
  $total_images = $query_total->found_posts;

  // Query for queued images.
  $args_queued  = array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => array('image/jpeg', 'image/png'),
    'posts_per_page' => -1,
    'nopaging'       => true,
    'meta_query'     => array(
      array(
        'key'     => '_altly_queued',
        'value'   => true,
        'compare' => '='
      )
    )
  );
  $query_queued = new WP_Query($args_queued);
  $queued_count = $query_queued->found_posts;

  $response_data = array(
    'images' => $missing_images,
    'stats'  => array(
      'total_images'      => $total_images,
      'missing_alt_count' => $query_missing->found_posts,
      'queued_count'      => $queued_count,
    ),
  );
  $response = rest_ensure_response($response_data);
  $response->header('X-WP-TotalPages', $query_missing->max_num_pages);
  return $response;
}

// Mark image as queued.
add_action('rest_api_init', function () {
  register_rest_route('altly/v1', '/mark-queued', array(
    'methods'             => 'POST',
    'callback'            => 'altly_mark_queued',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

function altly_mark_queued($request) {
  if (! altly_verify_rest_nonce()) {
    return new WP_Error('rest_forbidden', __('Nonce verification failed.', 'altly'), array('status' => 403));
  }
  $params = $request->get_json_params();
  if (empty($params['image_id'])) {
    return new WP_Error('missing_image_id', 'Missing image ID', array('status' => 400));
  }
  $image_id = intval($params['image_id']);
  // Transient in-flight flag: set here, cleared by receive-alt once alt lands.
  update_post_meta($image_id, '_altly_queued', true);
  // Persistent "Altly manages this attachment" marker: never deleted, so an
  // idempotent webhook redelivery of an already-processed image still passes the
  // receive-alt scope gate (see altly_receive_alt_text).
  update_post_meta($image_id, '_altly_managed', true);
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
    $raw    = wp_unslash(file_get_contents('php://input'));
    $params = json_decode($raw, true);
  }
  if (! isset($params['api_key'])) {
    return new WP_Error('missing_api_key', 'Missing API key', array('status' => 401));
  }
  $stored_key = get_option('altly_license_key');
  // Reject before any comparison when the site has no license key configured.
  // Otherwise an empty stored key would let an empty (or absent-but-set) key
  // through, accepting unauthenticated writes on a fresh/unconfigured site.
  if (empty($stored_key)) {
    return new WP_Error('not_configured', 'Site is not configured to accept alt text', array('status' => 403));
  }
  $provided_key = sanitize_text_field($params['api_key']);
  // Constant-time comparison to avoid leaking the key via timing.
  return hash_equals((string) $stored_key, (string) $provided_key);
}

/**
 * The single, shared alt-text write path used by BOTH the push webhook
 * (receive-alt) and the pull job (altly_sync_results). Enforces the persistent
 * `_altly_managed` scope gate, writes WordPress's standard
 * `_wp_attachment_image_alt` meta, and clears the transient `_altly_queued`
 * in-flight flag.
 *
 * Returns true on success (including the idempotent "alt already matches" case),
 * or a WP_Error on failure:
 *   - invalid_image (400): id is not an existing attachment.
 *   - not_managed   (403): attachment lacks the `_altly_managed` marker (H-8).
 *   - update_failed (500): the meta write failed.
 *
 * @param int    $attachment_id WordPress attachment id.
 * @param string $alt_text      Alt text to store.
 * @return true|WP_Error
 */
function altly_write_alt_text($attachment_id, $alt_text) {
  $attachment_id = intval($attachment_id);
  $alt_text      = sanitize_text_field($alt_text);

  // Verify that the image exists and is an attachment.
  $image_post = get_post($attachment_id);
  if (! $image_post || $image_post->post_type !== 'attachment') {
    return new WP_Error('invalid_image', 'Invalid image ID', array('status' => 400));
  }

  // Scope writes to attachments Altly manages. `_altly_managed` is set once at
  // enqueue time (altly/v1/mark-queued) and never deleted, so a caller past the
  // key gate cannot rewrite alt text on arbitrary media, while an idempotent
  // redelivery of an already-processed image still passes (the transient
  // `_altly_queued` flag may already be cleared — do not gate on it here).
  if (! get_post_meta($attachment_id, '_altly_managed', true)) {
    return new WP_Error('not_managed', 'Image is not managed by Altly', array('status' => 403));
  }

  $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
  if ($current_alt !== $alt_text) {
    $updated = update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
    if ($updated === false) {
      $added = add_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text, true);
      if (! $added) {
        return new WP_Error('update_failed', 'Failed to update alt text', array('status' => 500));
      }
    }
  }

  // Remove the transient queue meta now that the alt text is updated.
  delete_post_meta($attachment_id, '_altly_queued');
  return true;
}

function altly_receive_alt_text(WP_REST_Request $request) {
  $params = $request->get_json_params();
  if (empty($params)) {
    $raw    = wp_unslash(file_get_contents('php://input'));
    $params = json_decode($raw, true);
  }
  if (! isset($params['image_id']) || ! isset($params['alt_text']) || ! isset($params['api_key'])) {
    return new WP_Error('missing_params', 'Missing parameters', array('status' => 400));
  }

  // Delegate to the shared write path (same logic the pull job uses).
  $result = altly_write_alt_text($params['image_id'], $params['alt_text']);
  if (is_wp_error($result)) {
    return $result;
  }
  return rest_ensure_response(array('success' => true, 'message' => 'Alt text updated.'));
}

/**
 * REST callback for the admin "Sync results" button. Nonce + manage_options
 * gated (registered above). Runs the pull job and returns a summary.
 */
function altly_sync_results_endpoint(WP_REST_Request $request) {
  if (! altly_verify_rest_nonce()) {
    return new WP_Error('rest_forbidden', __('Nonce verification failed.', 'altly'), array('status' => 403));
  }
  $result = altly_sync_results();
  if (is_wp_error($result)) {
    return $result;
  }
  return rest_ensure_response($result);
}

/**
 * Pull-model sync: fetch finished alt text from the API and write it locally.
 *
 * Each cycle GETs a page from /v2/results with NO `after` cursor, writes every
 * row through the shared altly_write_alt_text(), then acks the ids it DRAINED
 * this page. Writing before acking gives at-least-once delivery: a crash between
 * the two just re-serves the row on the next pull (the shared write is
 * idempotent). Acked rows flip to delivered server-side and drop out of the
 * results filter, so the next no-cursor pull returns the next batch — we never
 * persist a cursor (which would risk skipping rows).
 *
 * A row is drained (acked) when it is WRITTEN or is a PERMANENT local failure —
 * `invalid_image` (attachment deleted), `not_managed`, or empty/non-string
 * alt_text. Draining permanent failures is essential: the pull is no-cursor and
 * the API serves oldest-first, so an un-acked permanent failure would pin the
 * head of every future page (and hard-block writable rows behind it once >=500
 * accumulate). Only the transient `update_failed` (500) is left un-acked, to be
 * retried on a later run. Stops when a page is empty or has no drainable rows;
 * capped at ALTLY_SYNC_MAX_PAGES to avoid a runaway loop.
 *
 * Never fatals: non-200 / transport errors are logged and end the run cleanly.
 *
 * @return array{success:bool,written:int,acked:int,pages:int,capped:bool}|WP_Error
 */
function altly_sync_results() {
  $license_key = get_option('altly_license_key', '');
  if (empty($license_key)) {
    return new WP_Error('no_license', 'No license key found', array('status' => 400));
  }

  $platform_id = altly_site_host();
  if (empty($platform_id)) {
    return new WP_Error('no_platform_id', 'Could not derive site host', array('status' => 500));
  }

  $max_pages = defined('ALTLY_SYNC_MAX_PAGES') ? ALTLY_SYNC_MAX_PAGES : 50;
  $written   = 0;
  $acked     = 0;
  $pages     = 0;
  $capped    = false;

  while (true) {
    if ($pages >= $max_pages) {
      $capped = true;
      error_log('[altly] sync-results hit the page cap (' . $max_pages . '); more results may remain — next run continues.');
      break;
    }

    $url = add_query_arg(
      array('platform_id' => $platform_id, 'limit' => 500),
      ALTLY_API_RESULTS_URL
    );
    $response = wp_remote_get($url, array(
      'headers' => array('Authorization' => 'Bearer ' . $license_key),
      'timeout' => 20,
    ));

    if (is_wp_error($response)) {
      error_log('[altly] sync-results GET failed: ' . $response->get_error_message());
      break;
    }
    $code = wp_remote_retrieve_response_code($response);
    if (200 !== (int) $code) {
      error_log('[altly] sync-results GET returned HTTP ' . $code . '; aborting run.');
      break;
    }

    $body    = json_decode(wp_remote_retrieve_body($response), true);
    $results = (is_array($body) && isset($body['results']) && is_array($body['results'])) ? $body['results'] : array();
    if (empty($results)) {
      break; // Nothing left to pull.
    }

    // Ids to ack this page. A row is DRAINED (acked so the API flips it
    // delivered and stops re-serving it) when it is either written OR a
    // PERMANENT local failure. Because the pull is no-cursor and the API serves
    // oldest-first, an un-acked permanent failure would pin the head of every
    // future page — wasting budget and, once >=500 accumulate, hard-blocking all
    // writable rows behind it. Only the transient `update_failed` (500) is left
    // un-acked so at-least-once retry can pick it up next run.
    $ack_ids = array();
    foreach ($results as $row) {
      if (! is_array($row) || ! isset($row['wp_attachment_id'])) {
        continue;
      }
      $aid = intval($row['wp_attachment_id']);
      $alt = isset($row['alt_text']) ? $row['alt_text'] : null;

      // Guard empty/non-string alt: never blank existing (possibly
      // human-authored) alt, and never hand a non-string to sanitize. Treat as
      // a permanent row for this pull — drain it so it can't clog the queue.
      if (! is_string($alt) || '' === trim($alt)) {
        error_log('[altly] sync-results draining attachment ' . $aid . ': empty/invalid alt_text');
        $ack_ids[] = $aid;
        continue;
      }

      $res = altly_write_alt_text($aid, $alt);
      if (true === $res) {
        $written++;
        $ack_ids[] = $aid;
      } elseif (in_array($res->get_error_code(), array('invalid_image', 'not_managed'), true)) {
        // Permanent: attachment gone locally, or not Altly-managed. Never
        // writable here — drain it so it stops pinning the head of the queue.
        error_log('[altly] sync-results draining attachment ' . $aid . ': ' . $res->get_error_code());
        $ack_ids[] = $aid;
      } else {
        // Transient (update_failed / 500): leave un-acked for next-run retry.
        error_log('[altly] sync-results transient write failure for attachment ' . $aid . ': ' . $res->get_error_code());
      }
    }

    // Nothing drainable on a non-empty page (only transient failures) means the
    // next no-cursor pull would return the identical page — stop to avoid
    // spinning; the transient rows retry on a later run.
    if (empty($ack_ids)) {
      error_log('[altly] sync-results page had no drainable rows; stopping to avoid a no-progress loop.');
      break;
    }

    // At-least-once: ack (written + permanently-drained) AFTER writing.
    $ack = altly_ack_results($ack_ids, $platform_id, $license_key);
    if (is_wp_error($ack)) {
      error_log('[altly] sync-results ack failed: ' . $ack->get_error_message() . '; stopping run.');
      break;
    }
    $acked += (int) $ack;
    $pages++;
  }

  return array(
    'success' => true,
    'written' => $written,
    'acked'   => $acked,
    'pages'   => $pages,
    'capped'  => $capped,
  );
}

/**
 * POST the ids we just wrote to /v2/results/ack so the API marks them delivered.
 * Body max is 500 ids (our page limit), matching the contract.
 *
 * @return int|WP_Error Number acked, or WP_Error on transport / non-200.
 */
function altly_ack_results($ids, $platform_id, $license_key) {
  $ids = array_values(array_map('intval', $ids));
  if (empty($ids)) {
    return 0;
  }
  $url      = add_query_arg(array('platform_id' => $platform_id), ALTLY_API_RESULTS_ACK_URL);
  $response = wp_remote_post($url, array(
    'headers' => array(
      'Authorization' => 'Bearer ' . $license_key,
      'Content-Type'  => 'application/json',
    ),
    'body'    => wp_json_encode(array('wp_attachment_ids' => $ids)),
    'timeout' => 20,
  ));

  if (is_wp_error($response)) {
    return $response;
  }
  $code = wp_remote_retrieve_response_code($response);
  if (200 !== (int) $code) {
    return new WP_Error('ack_failed', 'Ack returned HTTP ' . $code, array('status' => $code));
  }
  $body = json_decode(wp_remote_retrieve_body($response), true);
  return (is_array($body) && isset($body['acked'])) ? intval($body['acked']) : 0;
}

add_action('rest_api_init', function () {
  register_rest_route('altly/v1', '/clear-alt-text', array(
    'methods'             => 'POST',
    'callback'            => 'altly_clear_all_alt_text',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

function altly_clear_all_alt_text() {
  if (! altly_verify_rest_nonce()) {
    return new WP_Error('rest_forbidden', __('Nonce verification failed.', 'altly'), array('status' => 403));
  }

  // Get all attachments that have an alt text.
  $args = array(
    'post_type'      => 'attachment',
    'posts_per_page' => -1,
    'meta_key'       => '_wp_attachment_image_alt',
  );

  $attachments = get_posts($args);
  if ($attachments) {
    foreach ($attachments as $attachment) {
      delete_post_meta($attachment->ID, '_wp_attachment_image_alt');
    }
  }

  return rest_ensure_response(array(
    'success' => true,
    'message' => 'All image alt text has been cleared.',
  ));
}

add_action('rest_api_init', function () {
  register_rest_route('altly/v1', '/clear-queue', array(
    'methods'             => 'POST',
    'callback'            => 'altly_clear_queue',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
  ));
});

function altly_clear_queue($request) {
  if (! altly_verify_rest_nonce()) {
    return new WP_Error('rest_forbidden', __('Nonce verification failed.', 'altly'), array('status' => 403));
  }
  $params = $request->get_json_params();
  if (empty($params['image_ids']) || ! is_array($params['image_ids'])) {
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
  if (! altly_verify_rest_nonce()) {
    return new WP_Error('rest_forbidden', __('Nonce verification failed.', 'altly'), array('status' => 403));
  }
  $params      = $request->get_json_params();
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
  if (! altly_verify_rest_nonce()) {
    return new WP_Error('rest_forbidden', __('Nonce verification failed.', 'altly'), array('status' => 403));
  }
  $params = $request->get_json_params();
  if (empty($params['licenseKey'])) {
    return new WP_Error('no_license', 'License key is required', array('status' => 400));
  }

  update_option('altly_license_key', sanitize_text_field($params['licenseKey']));
  return rest_ensure_response(array('success' => true, 'message' => 'License key saved.'));
}

/**
 * Callback to save the account default generation speed.
 */
function altly_save_default_mode($request) {
  if (! altly_verify_rest_nonce()) {
    return new WP_Error('rest_forbidden', __('Nonce verification failed.', 'altly'), array('status' => 403));
  }
  $params = $request->get_json_params();
  $mode   = isset($params['mode']) ? sanitize_text_field($params['mode']) : 'instant';
  if (! in_array($mode, array('instant', 'relaxed'), true)) {
    $mode = 'instant';
  }
  update_option('altly_default_mode', $mode);
  return rest_ensure_response(array('success' => true, 'mode' => $mode));
}
?>