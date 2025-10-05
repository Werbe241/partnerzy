<?php
/**
 * Plugin Name: KK Lite - Kurs Koordynatora (Werbekoordinator)
 * Description: Minimalna wtyczka: kurs/certyfikaty z własnymi trasami (/kk/*), bez shortcodów i bez WPML. REST API + szablony w wtyczce.
 * Version: 1.0.3
 * Author: Fundacja Werbekoordinator
 */
if (!defined('ABSPATH')) { exit; }

/* ===== Helpers ===== */
function kklt_base_dir()  { return plugin_dir_path(__FILE__); }
function kklt_base_url()  { return plugin_dir_url(__FILE__); }

/**
 * Render pliku HTML z wtyczki:
 * - twarde UTF-8
 * - zamiana ścieżek względnych na URL wtyczki
 */
function kklt_render_file($relPath) {
  $file = kklt_base_dir() . ltrim($relPath, '/');
  if (!file_exists($file)) return '<div style="color:#b91c1c">Brak pliku: '.esc_html($relPath).'</div>';

  $html = file_get_contents($file);

  // Konwersja do UTF-8, jeśli plik nie jest UTF-8
  if (function_exists('mb_detect_encoding')) {
    $enc = mb_detect_encoding($html, 'UTF-8, Windows-1250, ISO-8859-2, ISO-8859-1', true);
    if ($enc && strtoupper($enc) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
      $html = mb_convert_encoding($html, 'UTF-8', $enc);
    }
  } elseif (function_exists('iconv')) {
    $conv = @iconv('ISO-8859-2', 'UTF-8//IGNORE', $html);
    if ($conv !== false) $html = $conv;
  }

  $base = kklt_base_url();
  // Zamień ścieżki względne na URL wtyczki (assets/templates/certificates/data)
  $html = preg_replace('#(src|href)=["\']\./#i', '$1="'.$base.'"', $html);
  $html = preg_replace('#(src|href)=["\']\.\./#i', '$1="'.$base.'"', $html);
  $html = preg_replace('#(src|href)=["\'](templates|certificates|assets|data)/#i', '$1="'.$base.'$2/' , $html);

  return $html;
}

/* ===== Trasy /kk/certyfikaty/ i /kk/weryfikacja/ (omijają WPML) ===== */
function kklt_register_routes() {
  add_rewrite_rule('^kk/certyfikaty/?$', 'index.php?kklt_cert_view=1', 'top');
  add_rewrite_rule('^kk/weryfikacja/?$', 'index.php?kklt_verify_view=1', 'top');
}
add_action('init', 'kklt_register_routes');

add_filter('query_vars', function($vars){
  $vars[] = 'kklt_cert_view';
  $vars[] = 'kklt_verify_view';
  return $vars;
});

/**
 * Bypass wymogu X-WP-Nonce dla REST (tylko gdy użytkownik jest zalogowany).
 * Dzięki temu panel działa stabilnie nawet przy cache/CDN.
 */
add_filter('rest_authentication_errors', function($result){
  if (!empty($result)) return $result;
  if (is_user_logged_in()) return true;
  return $result;
});

/* ===== Frontend: serwowanie widoków jako pełne strony UTF-8 ===== */
add_action('template_redirect', function(){
  if (is_admin()) return;

  $is_cert = get_query_var('kklt_cert_view');
  $is_ver  = get_query_var('kklt_verify_view');

  if ($is_cert) {
    nocache_headers();
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');

    // REST URL i nonce (nonce opcjonalny, mamy też bypass)
    $rest_url = esc_url_raw( rest_url('kk/v1') );
    $nonce    = wp_create_nonce('wp_rest');

    echo '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Moje certyfikaty – Werbekoordinator</title>';
    echo '<script>window.KK = { rest: "'.esc_js($rest_url).'", nonce: "'.esc_js($nonce).'" };</script>';
    echo '</head><body style="margin:0">';
    echo kklt_render_file('templates/app.html');
    echo '</body></html>';
    exit;
  }

  if ($is_ver) {
    nocache_headers();
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Weryfikacja certyfikatu – Werbekoordinator</title>';
    echo '</head><body style="margin:0">';
    echo kklt_render_file('templates/verify.html');
    echo '</body></html>';
    exit;
  }
});

/* ===== Aktywacja: tabele + rewrite flush ===== */
register_activation_hook(__FILE__, 'kklt_activate');
function kklt_activate() {
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  $charset_collate = $wpdb->get_charset_collate();
  $tbl_results = $wpdb->prefix . 'kk_course_results';
  $tbl_certs   = $wpdb->prefix . 'kk_certificates';

  $sql1 = "CREATE TABLE $tbl_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    score INT UNSIGNED NOT NULL,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_idx (user_id),
    KEY module_idx (module_id)
  ) $charset_collate;";
  $sql2 = "CREATE TABLE $tbl_certs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cert_no VARCHAR(64) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(8) NOT NULL,
    issued_at DATETIME NOT NULL,
    valid_until DATETIME NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'valid',
    meta LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY user_idx (user_id),
    KEY role_idx (role)
  ) $charset_collate;";

  dbDelta($sql1);
  dbDelta($sql2);

  kklt_register_routes();
  flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, function(){ flush_rewrite_rules(); });

/* ===== REST API (kk/v1) ===== */
add_action('rest_api_init', function() {
  register_rest_route('kk/v1', '/test-result', array(
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => 'kklt_rest_save_test_result'
  ));
  register_rest_route('kk/v1', '/certificate/issue', array(
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => 'kklt_rest_issue_certificate'
  ));
  register_rest_route('kk/v1', '/certificate/my', array(
    'methods'  => 'GET',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => 'kklt_rest_my_certificates'
  ));
  register_rest_route('kk/v1', '/certificate/verify', array(
    'methods'  => 'GET',
    'permission_callback' => '__return_true',
    'callback' => 'kklt_rest_verify_certificate'
  ));
});

function kklt_generate_cert_no($role) {
  $role = strtoupper(preg_replace('/[^A-Z]/', '', $role));
  $d = current_time('mysql');
  $ymd = date_i18n('Ymd', strtotime($d));
  $rand = wp_rand(1000, 9999);
  return "{$role}-{$ymd}-{$rand}";
}

function kklt_rest_save_test_result(WP_REST_Request $req) {
  global $wpdb;
  $user_id   = get_current_user_id();
  $module_id = intval($req->get_param('module_id'));
  $score     = intval($req->get_param('score'));
  $passed    = intval($req->get_param('passed')) ? 1 : 0;
  if ($module_id <= 0) return new WP_Error('kklt_bad_request', 'Brak module_id', array('status' => 400));
  $tbl = $wpdb->prefix . 'kk_course_results';
  $ok = $wpdb->insert($tbl, array(
    'user_id'   => $user_id,
    'module_id' => $module_id,
    'score'     => $score,
    'passed'    => $passed,
    'created_at'=> current_time('mysql')
  ), array('%d','%d','%d','%d','%s'));
  if (!$ok) return new WP_Error('kklt_db', 'Błąd zapisu', array('status' => 500));
  return new WP_REST_Response(array('ok' => true, 'id' => $wpdb->insert_id), 200);
}

function kklt_rest_issue_certificate(WP_REST_Request $req) {
  global $wpdb;
  $current = get_current_user_id();
  $user_id = intval($req->get_param('user_id'));
  $role    = strtoupper(sanitize_text_field($req->get_param('role'))); // KR|MR|RT
  if (!$user_id) $user_id = $current;
  if (!in_array($role, array('KR','MR','RT'), true)) return new WP_Error('kklt_bad_role', 'Niepoprawna rola', array('status'=>400));
  if ($current !== $user_id && !current_user_can('manage_options')) {
    return new WP_Error('kklt_forbidden', 'Brak uprawnień', array('status'=>403));
  }
  $cert_no = kklt_generate_cert_no($role);
  $tbl = $wpdb->prefix . 'kk_certificates';

  // Unikalność numeru (do 5 prób)
  $tries = 0;
  do {
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl WHERE cert_no=%s", $cert_no));
    if ($exists) { $cert_no = kklt_generate_cert_no($role); }
    $tries++;
  } while (!empty($exists) && $tries < 5);

  $ok = $wpdb->insert($tbl, array(
    'cert_no'   => $cert_no,
    'user_id'   => $user_id,
    'role'      => $role,
    'issued_at' => current_time('mysql'),
    'valid_until'=> null,
    'status'    => 'valid',
    'meta'      => null
  ), array('%s','%d','%s','%s','%s','%s','%s'));
  if (!$ok) return new WP_Error('kklt_db', 'Błąd wystawienia certyfikatu', array('status'=>500));
  return new WP_REST_Response(array('ok' => true, 'cert_no' => $cert_no), 200);
}

function kklt_rest_my_certificates(WP_REST_Request $req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $tbl = $wpdb->prefix . 'kk_certificates';
  $rows = $wpdb->get_results($wpdb->prepare("SELECT cert_no, role, issued_at, valid_until, status FROM $tbl WHERE user_id=%d ORDER BY issued_at DESC", $user_id), ARRAY_A);
  return new WP_REST_Response(array('items' => $rows), 200);
}

function kklt_rest_verify_certificate(WP_REST_Request $req) {
  global $wpdb;
  $cert_no = sanitize_text_field($req->get_param('cert_no'));
  if (!$cert_no) return new WP_Error('kklt_bad_request', 'Brak cert_no', array('status'=>400));
  $tbl = $wpdb->prefix . 'kk_certificates';
  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT c.cert_no, c.role, c.issued_at, c.valid_until, c.status, u.display_name as owner
     FROM $tbl c JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE c.cert_no=%s",
    $cert_no
  ), ARRAY_A);
  if (!$row) return new WP_REST_Response(array('found' => false), 200);
  return new WP_REST_Response(array('found' => true, 'data' => $row), 200);
}