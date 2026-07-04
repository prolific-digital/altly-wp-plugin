<?php

/**
 * Standalone unit coverage for the `receive-alt` webhook auth + scope hardening
 * (CR-2 empty-key bypass, H-8 arbitrary-attachment overwrite).
 *
 * No PHPUnit / WP test suite is wired into this repo, so this file stubs the
 * handful of WordPress functions the two target callbacks touch, `require`s the
 * real `altly.php` (its load-time `add_action`/`register_activation_hook` calls
 * are no-ops here, so no routes are actually registered), and drives the shipped
 * functions directly. Run inside wp-env's php:
 *
 *   npx wp-env run cli php <container-path>/tests/receive-alt-auth.test.php
 *
 * Exits non-zero on the first failed assertion.
 */

if (! defined('ABSPATH')) {
  define('ABSPATH', __DIR__ . '/');
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
// (a) Unconfigured site: stored key empty or unset -> reject before compare.
// ============================================================================
reset_state('');
$r = altly_validate_api_key_for_receive_alt(new WP_REST_Request(array('api_key' => '')));
check('(a1) empty stored key + empty provided key is rejected (not silently allowed)', $r instanceof WP_Error);
check('(a1) empty stored key rejection uses a 4xx status', $r instanceof WP_Error && $r->get_status() === 403);

reset_state('');
$r = altly_validate_api_key_for_receive_alt(new WP_REST_Request(array('api_key' => $REAL_KEY)));
check('(a2) empty stored key rejects even a non-empty provided key', $r instanceof WP_Error && $r->get_status() === 403);

reset_state(null); // option entirely absent -> get_option returns false
$r = altly_validate_api_key_for_receive_alt(new WP_REST_Request(array('api_key' => $REAL_KEY)));
check('(a3) missing/unset stored key is rejected', $r instanceof WP_Error && $r->get_status() === 403);

// Missing api_key field still rejected (unchanged behavior).
reset_state($REAL_KEY);
$r = altly_validate_api_key_for_receive_alt(new WP_REST_Request(array()));
check('(a4) missing api_key field rejected 401', $r instanceof WP_Error && $r->get_status() === 401);

// ============================================================================
// (b) Wrong vs correct key via constant-time compare.
// ============================================================================
reset_state($REAL_KEY);
$r = altly_validate_api_key_for_receive_alt(new WP_REST_Request(array('api_key' => 'WRONG-KEY')));
check('(b1) wrong key is rejected (returns false, not WP_Error)', $r === false);

reset_state($REAL_KEY);
$r = altly_validate_api_key_for_receive_alt(new WP_REST_Request(array('api_key' => $REAL_KEY)));
check('(b2) correct key passes (returns true)', $r === true);

// ============================================================================
// (c) Correct key but attachment is NOT Altly-managed -> reject (H-8).
// ============================================================================
reset_state($REAL_KEY);
$GLOBALS['__meta'][99] = array('_wp_attachment_image_alt' => 'legit human-authored alt');
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 99,
  'alt_text' => 'attacker overwrite',
  'api_key'  => $REAL_KEY,
)));
check('(c1) non-managed attachment write is rejected 403', $r instanceof WP_Error && $r->get_status() === 403);
check('(c2) non-managed attachment alt text is left untouched', get_post_meta(99, '_wp_attachment_image_alt', true) === 'legit human-authored alt');

// A once-queued image whose transient flag was cleared but which was NEVER
// _altly_managed must still be rejected (H-8 keys off the persistent marker).
reset_state($REAL_KEY);
$GLOBALS['__meta'][99] = array('_altly_queued' => true); // transient only, no _altly_managed
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 99,
  'alt_text' => 'x',
  'api_key'  => $REAL_KEY,
)));
check('(c3) attachment lacking persistent _altly_managed is rejected 403', $r instanceof WP_Error && $r->get_status() === 403);

// Non-existent / non-attachment id still rejected (unchanged behavior).
reset_state($REAL_KEY);
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 12345,
  'alt_text' => 'x',
  'api_key'  => $REAL_KEY,
)));
check('(c4) unknown image id rejected 400', $r instanceof WP_Error && $r->get_status() === 400);

// ============================================================================
// (d) Correct key + Altly-managed attachment -> success, alt written, transient cleared.
// ============================================================================
reset_state($REAL_KEY);
// State after enqueue: both markers set by mark-queued.
$GLOBALS['__meta'][42] = array('_altly_managed' => true, '_altly_queued' => true);
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 42,
  'alt_text' => 'A red bicycle leaning on a brick wall',
  'api_key'  => $REAL_KEY,
)));
check('(d1) managed attachment write succeeds', is_array($r) && ! empty($r['success']));
check('(d2) alt text is written to _wp_attachment_image_alt', get_post_meta(42, '_wp_attachment_image_alt', true) === 'A red bicycle leaning on a brick wall');
check('(d3) transient _altly_queued flag is cleared after write', get_post_meta(42, '_altly_queued', true) === '');
check('(d4) persistent _altly_managed marker is retained after write', get_post_meta(42, '_altly_managed', true) === true);

// ============================================================================
// (e) Idempotent redelivery: _altly_queued already cleared, _altly_managed
//     retained -> must still return 2xx (no 409), the whole point of the fix.
// ============================================================================
reset_state($REAL_KEY);
// State after a prior successful delivery: managed retained, queued cleared, alt set.
$GLOBALS['__meta'][42] = array(
  '_altly_managed'            => true,
  '_wp_attachment_image_alt'  => 'A red bicycle leaning on a brick wall',
);
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 42,
  'alt_text' => 'A red bicycle leaning on a brick wall',
  'api_key'  => $REAL_KEY,
)));
check('(e1) duplicate/retried delivery of a managed image returns 2xx (not 409)', is_array($r) && ! empty($r['success']));
check('(e2) redelivery leaves alt text intact', get_post_meta(42, '_wp_attachment_image_alt', true) === 'A red bicycle leaning on a brick wall');

// --- Result ----------------------------------------------------------------
echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
