<?php

/**
 * Standalone unit coverage for the shared alt-text write path
 * (`altly_write_alt_text`, including the H-8 `_altly_managed` scope gate) and
 * the pull-model sync job (`altly_sync_results`) that is now its sole caller.
 *
 * No PHPUnit / WP test suite is wired into this repo, so this file stubs the
 * handful of WordPress functions the target functions touch, `require`s the
 * real `altly.php` (its load-time `add_action`/`register_activation_hook` calls
 * are no-ops here, so no routes are actually registered), and drives the shipped
 * functions directly. Run inside wp-env's php:
 *
 *   npx wp-env run cli php <container-path>/tests/alt-write-and-sync.test.php
 *
 * Exits non-zero on the first failed assertion.
 */

if (! defined('ABSPATH')) {
  define('ABSPATH', __DIR__ . '/');
}
// altly.php's bootstrap now wires up the vendored plugin-update-checker at
// require-time, which needs these two WP core constants to exist (real
// WordPress always defines them before any plugin loads).
if (! defined('WP_PLUGIN_DIR')) {
  define('WP_PLUGIN_DIR', dirname(__DIR__));
}
if (! defined('WPMU_PLUGIN_DIR')) {
  define('WPMU_PLUGIN_DIR', dirname(__DIR__) . '/mu-plugins');
}
if (! defined('WP_DEBUG')) {
  define('WP_DEBUG', false);
}

// --- Mutable fake WordPress state -----------------------------------------
$GLOBALS['__options'] = array();
$GLOBALS['__posts']   = array();
$GLOBALS['__meta']    = array();

// --- WordPress function stubs ---------------------------------------------
function plugin_dir_path($f) {
  return dirname($f) . '/';
}
function plugin_dir_url($f) {
  return 'http://example.test/';
}
function add_option($k, $v = '') {
  if (! array_key_exists($k, $GLOBALS['__options'])) {
    $GLOBALS['__options'][$k] = $v;
  }
}
function register_activation_hook($f, $cb) {}
function add_action($hook, $cb, $priority = 10, $args = 1) {}
function apply_filters($hook, $value) {
  return $value;
}
// The rest of these are pulled in transitively by the vendored
// plugin-update-checker's constructor (wired up at altly.php require-time as
// of the self-hosted auto-update feature) — not touched by the assertions
// below, just needed so `require`ing altly.php doesn't fatal.
function esc_html($s) {
  return $s;
}
function plugin_basename($f) {
  return basename(dirname($f)) . '/' . basename($f);
}
function add_filter($hook, $cb, $priority = 10, $args = 1) {}
function did_action($hook) {
  return 0;
}
function wp_installing() {
  return false;
}
function is_admin() {
  return false;
}
function wp_doing_ajax() {
  return false;
}
function get_bloginfo($key = '') {
  return '';
}
function wp_get_environment_type() {
  return 'production';
}
function is_multisite() {
  return false;
}
function wp_next_scheduled($hook, $args = array()) {
  return false;
}
function wp_schedule_single_event($timestamp, $hook, $args = array()) {
  return true;
}
function wp_schedule_event($timestamp, $recurrence, $hook, $args = array()) {
  return true;
}
function wp_clear_scheduled_hook($hook, $args = array()) {
  return true;
}
function get_site_transient($key) {
  return false;
}
function set_site_transient($key, $value, $expiration = 0) {
  return true;
}
function delete_site_transient($key) {
  return true;
}
function current_user_can($capability) {
  return false;
}
function get_option($k, $default = false) {
  return array_key_exists($k, $GLOBALS['__options']) ? $GLOBALS['__options'][$k] : $default;
}
function sanitize_text_field($s) {
  return trim((string) $s);
}
function wp_unslash($s) {
  return $s;
}
function __($t, $d = 'default') {
  return $t;
}
function rest_ensure_response($d) {
  return $d;
}
function get_post($id) {
  return isset($GLOBALS['__posts'][$id]) ? $GLOBALS['__posts'][$id] : null;
}
function get_post_meta($id, $key, $single = false) {
  if (isset($GLOBALS['__meta'][$id][$key])) {
    return $GLOBALS['__meta'][$id][$key];
  }
  return $single ? '' : array();
}
function update_post_meta($id, $key, $val) {
  $GLOBALS['__meta'][$id][$key] = $val;
  return true;
}
function add_post_meta($id, $key, $val, $unique = false) {
  $GLOBALS['__meta'][$id][$key] = $val;
  return true;
}
function delete_post_meta($id, $key) {
  unset($GLOBALS['__meta'][$id][$key]);
  return true;
}
function register_deactivation_hook($f, $cb) {}
function is_wp_error($thing) {
  return $thing instanceof WP_Error;
}
function home_url() {
  return 'http://example.test';
}
function wp_parse_url($url, $component = -1) {
  return parse_url($url, $component);
}
function wp_json_encode($data) {
  return json_encode($data);
}
function add_query_arg($args, $url) {
  $sep = (strpos($url, '?') === false) ? '?' : '&';
  return $url . $sep . http_build_query($args);
}
function wp_remote_retrieve_response_code($response) {
  return is_array($response) && isset($response['code']) ? $response['code'] : 0;
}
function wp_remote_retrieve_body($response) {
  return is_array($response) && isset($response['body']) ? $response['body'] : '';
}

// --- Mock HTTP layer for the pull client ----------------------------------
// The API's /v2/results returns every not-yet-delivered row; acking flips rows
// to delivered so the next no-cursor pull returns the remainder. We model that
// stateful behavior so a re-pull is a genuine no-op (not a canned empty page).
$GLOBALS['__api_rows']       = array(); // id => alt_text (undelivered)
$GLOBALS['__api_delivered']  = array(); // id => true
$GLOBALS['__ack_calls']      = 0;
$GLOBALS['__api_page_limit'] = PHP_INT_MAX; // rows served per GET (oldest-first)

function wp_remote_get($url, $args = array()) {
  // Serve undelivered rows oldest-id-first, capped at the page limit — mirrors
  // the real API's oldest-first, limited pages so head-of-line behavior is
  // observable across pages.
  $ids = array_keys($GLOBALS['__api_rows']);
  sort($ids, SORT_NUMERIC);
  $rows = array();
  foreach ($ids as $id) {
    if (! empty($GLOBALS['__api_delivered'][$id])) {
      continue;
    }
    $rows[] = array('wp_attachment_id' => $id, 'alt_text' => $GLOBALS['__api_rows'][$id]);
    if (count($rows) >= $GLOBALS['__api_page_limit']) {
      break;
    }
  }
  return array('code' => 200, 'body' => json_encode(array('results' => $rows, 'next_cursor' => null)));
}

function wp_remote_post($url, $args = array()) {
  // Only the ack endpoint is exercised by the pull tests.
  if (strpos($url, 'results/ack') !== false) {
    $GLOBALS['__ack_calls']++;
    $body    = isset($args['body']) ? json_decode($args['body'], true) : array();
    $ids     = isset($body['wp_attachment_ids']) ? $body['wp_attachment_ids'] : array();
    $n       = 0;
    foreach ($ids as $id) {
      $id = intval($id);
      // array_key_exists (not ! empty) so an empty-string alt row still counts.
      if (array_key_exists($id, $GLOBALS['__api_rows']) && empty($GLOBALS['__api_delivered'][$id])) {
        $GLOBALS['__api_delivered'][$id] = true;
        $n++;
      }
    }
    return array('code' => 200, 'body' => json_encode(array('acked' => $n)));
  }
  return array('code' => 200, 'body' => json_encode(array('success' => true)));
}

// Minimal WP_Error / WP_REST_Request stand-ins.
class WP_Error {
  public $code;
  public $message;
  public $data;
  public function __construct($code = '', $message = '', $data = array()) {
    $this->code    = $code;
    $this->message = $message;
    $this->data    = $data;
  }
  public function get_status() {
    return isset($this->data['status']) ? $this->data['status'] : null;
  }
  public function get_error_code() {
    return $this->code;
  }
  public function get_error_message() {
    return $this->message;
  }
}

class WP_REST_Request {
  private $params;
  public function __construct($params) {
    $this->params = $params;
  }
  public function get_json_params() {
    return $this->params;
  }
}

// Pull in the real plugin (load-time hooks are no-ops via the stubs above).
require __DIR__ . '/../altly.php';

// --- Tiny assertion harness ------------------------------------------------
$failures = 0;
$passes   = 0;
function check($label, $cond) {
  global $failures, $passes;
  if ($cond) {
    $passes++;
    echo "PASS: {$label}\n";
  } else {
    $failures++;
    echo "FAIL: {$label}\n";
  }
}

function reset_state($stored_key) {
  $GLOBALS['__options'] = array();
  if ($stored_key !== null) {
    $GLOBALS['__options']['altly_license_key'] = $stored_key;
  }
  $GLOBALS['__posts'] = array(
    // A normal attachment Altly manages.
    42 => (object) array('post_type' => 'attachment'),
    // A non-Altly attachment (never managed) — the H-8 target.
    99 => (object) array('post_type' => 'attachment'),
  );
  $GLOBALS['__meta'] = array();
}

$REAL_KEY = 'live-key-abc123';

// ============================================================================
// (f) Shared write fn altly_write_alt_text: gate + clear behavior directly.
// ============================================================================
// Non-managed attachment -> rejected, alt untouched.
reset_state($REAL_KEY);
$GLOBALS['__meta'][99] = array('_wp_attachment_image_alt' => 'human alt');
$r = altly_write_alt_text(99, 'should not land');
check('(f1) shared fn rejects non-managed attachment (WP_Error 403)', $r instanceof WP_Error && $r->get_status() === 403);
check('(f2) shared fn leaves non-managed alt untouched', get_post_meta(99, '_wp_attachment_image_alt', true) === 'human alt');

// Managed attachment -> writes alt and clears the transient queued flag.
reset_state($REAL_KEY);
$GLOBALS['__meta'][42] = array('_altly_managed' => true, '_altly_queued' => true);
$r = altly_write_alt_text(42, 'A red bicycle');
check('(f3) shared fn returns true on managed write', $r === true);
check('(f4) shared fn writes _wp_attachment_image_alt', get_post_meta(42, '_wp_attachment_image_alt', true) === 'A red bicycle');
check('(f5) shared fn clears the transient _altly_queued flag', get_post_meta(42, '_altly_queued', true) === '');
check('(f6) shared fn retains the persistent _altly_managed marker', get_post_meta(42, '_altly_managed', true) === true);

// ============================================================================
// (g) Pull job altly_sync_results: writes THEN acks; a re-pull is a no-op.
// ============================================================================
reset_state($REAL_KEY);
// Two managed+queued attachments the API has finished alt text for.
$GLOBALS['__meta'][42] = array('_altly_managed' => true, '_altly_queued' => true);
$GLOBALS['__meta'][43] = array('_altly_managed' => true, '_altly_queued' => true);
$GLOBALS['__posts'][43] = (object) array('post_type' => 'attachment');
$GLOBALS['__api_rows']      = array(42 => 'alt for 42', 43 => 'alt for 43');
$GLOBALS['__api_delivered'] = array();
$GLOBALS['__ack_calls']     = 0;

$summary = altly_sync_results();
check('(g1) pull returns a summary array', is_array($summary) && ! empty($summary['success']));
check('(g2) pull wrote both rows', isset($summary['written']) && $summary['written'] === 2);
check('(g3) pull acked both rows', isset($summary['acked']) && $summary['acked'] === 2);
check('(g4) alt text written locally for 42', get_post_meta(42, '_wp_attachment_image_alt', true) === 'alt for 42');
check('(g5) alt text written locally for 43', get_post_meta(43, '_wp_attachment_image_alt', true) === 'alt for 43');
check('(g6) transient _altly_queued cleared for 42', get_post_meta(42, '_altly_queued', true) === '');
check('(g7) transient _altly_queued cleared for 43', get_post_meta(43, '_altly_queued', true) === '');
check('(g8) ack endpoint was called exactly once', $GLOBALS['__ack_calls'] === 1);

// Re-pull: everything is delivered server-side, so results are empty -> no-op.
$ack_before = $GLOBALS['__ack_calls'];
$summary2   = altly_sync_results();
check('(g9) re-pull writes nothing', is_array($summary2) && $summary2['written'] === 0);
check('(g10) re-pull acks nothing', $summary2['acked'] === 0);
check('(g11) re-pull makes no ack call (empty page short-circuits before ack)', $GLOBALS['__ack_calls'] === $ack_before);

// Unconfigured site: pull refuses cleanly (never fatals, no HTTP).
reset_state('');
$r = altly_sync_results();
check('(g12) pull with no license key returns WP_Error (no crash)', $r instanceof WP_Error);

// ============================================================================
// (h) Head-of-line: a page of ONLY permanent failures (not_managed +
//     invalid_image) must be DRAINED (acked) so a following page of writable
//     rows is not blocked. Page limit 2 forces the unwritable rows onto their
//     own page ahead of the writable one (oldest-id-first).
// ============================================================================
reset_state($REAL_KEY);
// 100 exists but is NOT managed -> not_managed (permanent).
$GLOBALS['__posts'][100] = (object) array('post_type' => 'attachment');
// 101 has no post at all -> invalid_image (permanent). (left absent)
// 200 exists + managed + queued -> writable, but sorts AFTER 100/101.
$GLOBALS['__posts'][200] = (object) array('post_type' => 'attachment');
$GLOBALS['__meta'][200]  = array('_altly_managed' => true, '_altly_queued' => true);
$GLOBALS['__api_rows']      = array(100 => 'x', 101 => 'y', 200 => 'alt-200');
$GLOBALS['__api_delivered'] = array();
$GLOBALS['__ack_calls']     = 0;
$GLOBALS['__api_page_limit'] = 2; // page1 = [100,101] (unwritable), page2 = [200]

$summary = altly_sync_results();
check('(h1) writable row behind a permanent-failure page still gets written', get_post_meta(200, '_wp_attachment_image_alt', true) === 'alt-200');
check('(h2) only the writable row counts as written', $summary['written'] === 1);
check('(h3) permanent failures + the write are all acked/drained (3 total)', $summary['acked'] === 3);
check('(h4) the not_managed row was drained (delivered)', ! empty($GLOBALS['__api_delivered'][100]));
check('(h5) the invalid_image row was drained (delivered)', ! empty($GLOBALS['__api_delivered'][101]));
check('(h6) two pages consumed (unwritable page did NOT stop the run)', $summary['pages'] === 2);
$GLOBALS['__api_page_limit'] = PHP_INT_MAX; // restore default for later cases

// ============================================================================
// (i) Empty / non-string alt_text: must NOT be written (never blank existing,
//     possibly human-authored, alt) and must be drained so it can't clog.
// ============================================================================
reset_state($REAL_KEY);
// 300: managed, already has human-authored alt; API row carries empty string.
$GLOBALS['__posts'][300] = (object) array('post_type' => 'attachment');
$GLOBALS['__meta'][300]  = array('_altly_managed' => true, '_wp_attachment_image_alt' => 'human alt');
// 301: managed, whitespace-only alt.
$GLOBALS['__posts'][301] = (object) array('post_type' => 'attachment');
$GLOBALS['__meta'][301]  = array('_altly_managed' => true);
// 302: managed, non-string alt (int).
$GLOBALS['__posts'][302] = (object) array('post_type' => 'attachment');
$GLOBALS['__meta'][302]  = array('_altly_managed' => true);
// 303: managed, good alt -> the one that should actually be written.
$GLOBALS['__posts'][303] = (object) array('post_type' => 'attachment');
$GLOBALS['__meta'][303]  = array('_altly_managed' => true, '_altly_queued' => true);
$GLOBALS['__api_rows']      = array(300 => '', 301 => '   ', 302 => 12345, 303 => 'a good description');
$GLOBALS['__api_delivered'] = array();
$GLOBALS['__ack_calls']     = 0;

$summary = altly_sync_results();
check('(i1) empty alt_text does NOT overwrite existing human alt', get_post_meta(300, '_wp_attachment_image_alt', true) === 'human alt');
check('(i2) whitespace-only alt_text is not written', get_post_meta(301, '_wp_attachment_image_alt', true) === '');
check('(i3) non-string alt_text is not written', get_post_meta(302, '_wp_attachment_image_alt', true) === '');
check('(i4) valid alt_text on the same page is still written', get_post_meta(303, '_wp_attachment_image_alt', true) === 'a good description');
check('(i5) only the one valid row counts as written', $summary['written'] === 1);
check('(i6) all four rows are drained/acked (invalid ones do not clog)', $summary['acked'] === 4);
check('(i7) every invalid row was drained (delivered)', ! empty($GLOBALS['__api_delivered'][300]) && ! empty($GLOBALS['__api_delivered'][301]) && ! empty($GLOBALS['__api_delivered'][302]));

// --- Result ----------------------------------------------------------------
echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
