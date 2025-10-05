<?php
/**
 * Plugin Name: KK Lite - Kurs Koordynatora (Werbekoordinator)
 * Description: Minimalna wtyczka: kurs/certyfikaty z własnymi trasami (/kk/*), bez shortcodów i bez WPML. REST API + szablony w wtyczce.
 * Version: 1.1.0
 * Author: Fundacja Werbekoordinator
 */
if (!defined('ABSPATH')) { exit; }

/* ===== Helpers ===== */
function kklt_base_dir()  { return plugin_dir_path(__FILE__); }
function kklt_base_url()  { return plugin_dir_url(__FILE__); }

/**
 * Get user's external System ID with precedence: kk_system_id -> promoter_id -> werbeko_id
 * 
 * @param int $user_id User ID
 * @return string|null External System ID or null if not found
 */
function kk_get_user_system_id($user_id) {
  $user_id = intval($user_id);
  if (!$user_id) return null;
  
  // Try kk_system_id first
  $id = get_user_meta($user_id, 'kk_system_id', true);
  if (!empty($id)) {
    return apply_filters('kk_get_user_system_id', $id, $user_id);
  }
  
  // Fallback to promoter_id
  $id = get_user_meta($user_id, 'promoter_id', true);
  if (!empty($id)) {
    return apply_filters('kk_get_user_system_id', $id, $user_id);
  }
  
  // Fallback to werbeko_id
  $id = get_user_meta($user_id, 'werbeko_id', true);
  if (!empty($id)) {
    return apply_filters('kk_get_user_system_id', $id, $user_id);
  }
  
  return apply_filters('kk_get_user_system_id', null, $user_id);
}

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
    external_id VARCHAR(64) NULL,
    issued_at DATETIME NOT NULL,
    valid_until DATETIME NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'valid',
    meta LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY user_idx (user_id),
    KEY role_idx (role),
    KEY role_ext_idx (role, external_id)
  ) $charset_collate;";

  dbDelta($sql1);
  dbDelta($sql2);

  kklt_register_routes();
  flush_rewrite_rules();
  
  // Run database upgrade
  kklt_db_upgrade();
}

/**
 * Database upgrade routine - adds external_id column if missing and backfills data
 */
function kklt_db_upgrade() {
  global $wpdb;
  $tbl_certs = $wpdb->prefix . 'kk_certificates';
  
  // Check if external_id column exists
  $column_exists = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
       WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'external_id'",
      DB_NAME,
      $tbl_certs
    )
  );
  
  if (empty($column_exists)) {
    // Add external_id column
    $wpdb->query("ALTER TABLE $tbl_certs ADD COLUMN external_id VARCHAR(64) NULL AFTER role");
    
    // Add index on (role, external_id)
    $wpdb->query("ALTER TABLE $tbl_certs ADD KEY role_ext_idx (role, external_id)");
    
    // Backfill external_id from cert_no where it matches ROLE-EXTERNAL_ID pattern
    $certs = $wpdb->get_results("SELECT id, cert_no, role FROM $tbl_certs WHERE external_id IS NULL", ARRAY_A);
    foreach ($certs as $cert) {
      // Try to extract external_id from cert_no (ROLE-EXTERNAL_ID format)
      if (preg_match('/^[A-Z]{2}-(\d+)$/', $cert['cert_no'], $matches)) {
        $external_id = $matches[1];
        $wpdb->update(
          $tbl_certs,
          array('external_id' => $external_id),
          array('id' => $cert['id']),
          array('%s'),
          array('%d')
        );
      }
    }
  }
  
  // Update version option to track upgrades
  update_option('kklt_db_version', '1.1.0');
}

// Run upgrade check on plugins_loaded
add_action('plugins_loaded', function() {
  $current_version = get_option('kklt_db_version', '0');
  if (version_compare($current_version, '1.1.0', '<')) {
    kklt_db_upgrade();
  }
});

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

/**
 * Generate certificate number in the new format: ROLE-EXTERNAL_ID
 * 
 * @param string $role Certificate role (KR, MR, RT)
 * @param int $user_id User ID to get external_id from
 * @param string|null $external_id Optional external_id (for admin override)
 * @return string|WP_Error Certificate number or error
 */
function kklt_generate_cert_no($role, $user_id = 0, $external_id = null) {
  $role = strtoupper(preg_replace('/[^A-Z]/', '', $role));
  
  // If external_id is provided, use it (admin override for MR/RT)
  if (!empty($external_id)) {
    $ext_id = preg_replace('/[^0-9]/', '', $external_id);
    if (empty($ext_id)) {
      return new WP_Error('kklt_invalid_external_id', 'Invalid external_id format', array('status' => 400));
    }
    return "{$role}-{$ext_id}";
  }
  
  // Otherwise, get external_id from user meta
  if (!$user_id) {
    $user_id = get_current_user_id();
  }
  
  $ext_id = kk_get_user_system_id($user_id);
  if (empty($ext_id)) {
    return new WP_Error('kklt_no_external_id', 'User does not have external_id (kk_system_id, promoter_id, or werbeko_id)', array('status' => 400));
  }
  
  // Ensure external_id is numeric only
  $ext_id = preg_replace('/[^0-9]/', '', $ext_id);
  return "{$role}-{$ext_id}";
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
  $external_id = sanitize_text_field($req->get_param('external_id')); // Optional external_id for MR/RT
  
  if (!$user_id) $user_id = $current;
  if (!in_array($role, array('KR','MR','RT'), true)) {
    return new WP_Error('kklt_bad_role', 'Niepoprawna rola', array('status'=>400));
  }
  
  // For KR: always use logged-in user's external_id (ignore provided external_id)
  // For MR/RT: if external_id is provided and user is admin, use it; otherwise use user's own external_id
  $use_external_id = null;
  if ($role === 'KR') {
    // KR always uses the logged-in user's ID
    $user_id = $current;
    $use_external_id = null; // Will be fetched by kklt_generate_cert_no
  } else {
    // MR or RT
    if (!empty($external_id) && current_user_can('manage_options')) {
      // Admin can issue for any external_id
      $use_external_id = $external_id;
      // For admin-issued certs, we still need a user_id - use current user or a system user
      // Let's use the current admin user as the owner
      $user_id = $current;
    } else {
      // Non-admin or no external_id provided: use their own
      $user_id = $current;
      $use_external_id = null;
    }
  }
  
  // Check permissions
  if ($current !== $user_id && !current_user_can('manage_options')) {
    return new WP_Error('kklt_forbidden', 'Brak uprawnień', array('status'=>403));
  }
  
  // Generate certificate number
  $cert_no = kklt_generate_cert_no($role, $user_id, $use_external_id);
  if (is_wp_error($cert_no)) {
    return $cert_no;
  }
  
  // Extract external_id from cert_no for storage
  preg_match('/^[A-Z]{2}-(.+)$/', $cert_no, $matches);
  $external_id_value = isset($matches[1]) ? $matches[1] : null;
  
  $tbl = $wpdb->prefix . 'kk_certificates';
  
  // Check for existing valid certificate with same role + external_id (idempotent issuance)
  if (!empty($external_id_value)) {
    $existing = $wpdb->get_row($wpdb->prepare(
      "SELECT cert_no, role, issued_at, valid_until, status FROM $tbl 
       WHERE role = %s AND external_id = %s AND status = 'valid' 
       ORDER BY issued_at DESC LIMIT 1",
      $role,
      $external_id_value
    ), ARRAY_A);
    
    if ($existing) {
      // Return existing certificate (idempotent)
      return new WP_REST_Response(array(
        'ok' => true,
        'data' => array(
          'role' => $existing['role'],
          'cert_no' => $existing['cert_no'],
          'external_id' => $external_id_value,
          'status' => $existing['status'],
          'issued_at' => $existing['issued_at']
        )
      ), 200);
    }
  }

  // Issue new certificate
  $ok = $wpdb->insert($tbl, array(
    'cert_no'   => $cert_no,
    'user_id'   => $user_id,
    'role'      => $role,
    'external_id' => $external_id_value,
    'issued_at' => current_time('mysql'),
    'valid_until'=> null,
    'status'    => 'valid',
    'meta'      => null
  ), array('%s','%d','%s','%s','%s','%s','%s','%s'));
  
  if (!$ok) {
    return new WP_Error('kklt_db', 'Błąd wystawienia certyfikatu', array('status'=>500));
  }
  
  return new WP_REST_Response(array(
    'ok' => true,
    'data' => array(
      'role' => $role,
      'cert_no' => $cert_no,
      'external_id' => $external_id_value,
      'status' => 'valid',
      'issued_at' => current_time('mysql')
    )
  ), 200);
}

function kklt_rest_my_certificates(WP_REST_Request $req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $external_id = sanitize_text_field($req->get_param('external_id'));
  $tbl = $wpdb->prefix . 'kk_certificates';
  
  // If external_id is provided, filter by it; otherwise show user's certificates (backwards compatible)
  if (!empty($external_id)) {
    // Filter by external_id
    $external_id_clean = preg_replace('/[^0-9]/', '', $external_id);
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT cert_no, role, issued_at, valid_until, status, external_id 
       FROM $tbl WHERE external_id=%s ORDER BY issued_at DESC",
      $external_id_clean
    ), ARRAY_A);
  } else {
    // Show user's own certificates (backwards compatible)
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT cert_no, role, issued_at, valid_until, status, external_id 
       FROM $tbl WHERE user_id=%d ORDER BY issued_at DESC",
      $user_id
    ), ARRAY_A);
  }
  
  return new WP_REST_Response(array('items' => $rows), 200);
}

function kklt_rest_verify_certificate(WP_REST_Request $req) {
  global $wpdb;
  $cert_no = sanitize_text_field($req->get_param('cert_no'));
  if (!$cert_no) return new WP_Error('kklt_bad_request', 'Brak cert_no', array('status'=>400));
  
  $tbl = $wpdb->prefix . 'kk_certificates';
  
  // Check if cert_no contains a dash (ROLE-EXTERNAL_ID format)
  if (strpos($cert_no, '-') !== false) {
    // Full certificate number format
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT c.cert_no, c.role, c.issued_at, c.valid_until, c.status, c.external_id, u.display_name as owner
       FROM $tbl c JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE c.cert_no=%s",
      $cert_no
    ), ARRAY_A);
    
    if (!$row) return new WP_REST_Response(array('found' => false), 200);
    return new WP_REST_Response(array('found' => true, 'data' => $row), 200);
  } else {
    // Raw external_id - return all certificates for this ID
    $external_id_clean = preg_replace('/[^0-9]/', '', $cert_no);
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT c.cert_no, c.role, c.issued_at, c.valid_until, c.status, c.external_id, u.display_name as owner
       FROM $tbl c JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE c.external_id=%s ORDER BY c.issued_at DESC",
      $external_id_clean
    ), ARRAY_A);
    
    if (empty($rows)) return new WP_REST_Response(array('found' => false), 200);
    return new WP_REST_Response(array('found' => true, 'data' => $rows), 200);
  }
}