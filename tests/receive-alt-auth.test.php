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
    // A normal attachment Altly queued.
    42 => (object) array('post_type' => 'attachment'),
    // A non-Altly attachment (never queued) — the H-8 target.
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
// (c) Correct key but attachment was NOT queued by Altly -> reject.
// ============================================================================
reset_state($REAL_KEY);
$GLOBALS['__meta'][99] = array('_wp_attachment_image_alt' => 'legit human-authored alt');
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 99,
  'alt_text' => 'attacker overwrite',
  'api_key'  => $REAL_KEY,
)));
check('(c1) non-queued attachment write is rejected', $r instanceof WP_Error && $r->get_status() === 409);
check('(c2) non-queued attachment alt text is left untouched', get_post_meta(99, '_wp_attachment_image_alt', true) === 'legit human-authored alt');

// Non-existent / non-attachment id still rejected (unchanged behavior).
reset_state($REAL_KEY);
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 12345,
  'alt_text' => 'x',
  'api_key'  => $REAL_KEY,
)));
check('(c3) unknown image id rejected 400', $r instanceof WP_Error && $r->get_status() === 400);

// ============================================================================
// (d) Correct key + properly queued attachment -> success, alt written, flag cleared.
// ============================================================================
reset_state($REAL_KEY);
$GLOBALS['__meta'][42] = array('_altly_queued' => true); // set by mark-queued at enqueue
$r = altly_receive_alt_text(new WP_REST_Request(array(
  'image_id' => 42,
  'alt_text' => 'A red bicycle leaning on a brick wall',
  'api_key'  => $REAL_KEY,
)));
check('(d1) queued attachment write succeeds', is_array($r) && ! empty($r['success']));
check('(d2) alt text is written to _wp_attachment_image_alt', get_post_meta(42, '_wp_attachment_image_alt', true) === 'A red bicycle leaning on a brick wall');
check('(d3) _altly_queued flag is cleared after write', get_post_meta(42, '_altly_queued', true) === '');

// --- Result ----------------------------------------------------------------
echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
