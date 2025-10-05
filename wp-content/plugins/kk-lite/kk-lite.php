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
 * Get external system ID for a user.
 * Priority: kk_system_id > promoter_id > werbeko_id
 * Filterable via 'kk_get_user_system_id'.
 */
function kk_get_user_system_id($user_id) {
  $user_id = intval($user_id);
  if (!$user_id) return null;
  
  $system_id = get_user_meta($user_id, 'kk_system_id', true);
  if (empty($system_id)) {
    $system_id = get_user_meta($user_id, 'promoter_id', true);
  }
  if (empty($system_id)) {
    $system_id = get_user_meta($user_id, 'werbeko_id', true);
  }
  
  // Filter hook to allow custom integration
  return apply_filters('kk_get_user_system_id', $system_id, $user_id);
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

/* ===== Shortcodes ===== */
add_shortcode('kk_course', function() {
  // Inject REST URL and nonce
  $rest_url = esc_url_raw( rest_url('kk/v1') );
  $nonce    = wp_create_nonce('wp_rest');
  
  $script = '<script>window.KK = { rest: "'.esc_js($rest_url).'", nonce: "'.esc_js($nonce).'" };</script>';
  
  return $script . kklt_render_file('templates/course.html');
});

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
    $is_admin = current_user_can('manage_options') ? 'true' : 'false';

    echo '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Moje certyfikaty – Werbekoordinator</title>';
    echo '<script>window.KK = { rest: "'.esc_js($rest_url).'", nonce: "'.esc_js($nonce).'", isAdmin: '.$is_admin.' };</script>';
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
    external_id VARCHAR(64) NULL,
    role VARCHAR(8) NOT NULL,
    issued_at DATETIME NOT NULL,
    valid_until DATETIME NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'valid',
    meta LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY user_idx (user_id),
    KEY external_id_idx (external_id),
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
  register_rest_route('kk/v1', '/certificate/check-kr', array(
    'methods'  => 'GET',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => 'kklt_rest_check_kr_certificate'
  ));
});

function kklt_generate_cert_no($role, $external_id = null) {
  $role = strtoupper(preg_replace('/[^A-Z]/', '', $role));
  if ($external_id) {
    // Format: ROLE-EXTERNALID (e.g., KR-00000011005)
    return "{$role}-{$external_id}";
  }
  // Fallback to old format if no external_id
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
  $external_id_param = sanitize_text_field($req->get_param('external_id'));
  
  if (!$user_id) $user_id = $current;
  if (!in_array($role, array('KR','MR','RT'), true)) return new WP_Error('kklt_bad_role', 'Niepoprawna rola', array('status'=>400));
  
  // Admin can issue for any user or by external_id only
  $is_admin = current_user_can('manage_options');
  if ($external_id_param && !$is_admin) {
    return new WP_Error('kklt_forbidden', 'Tylko admin może wydawać certyfikaty po external_id', array('status'=>403));
  }
  if ($current !== $user_id && !$is_admin) {
    return new WP_Error('kklt_forbidden', 'Brak uprawnień', array('status'=>403));
  }
  
  // Get or use provided external_id
  $external_id = $external_id_param;
  if (!$external_id && $user_id) {
    $external_id = kk_get_user_system_id($user_id);
  }
  
  // Check if certificate already exists for this external_id and role
  $tbl = $wpdb->prefix . 'kk_certificates';
  if ($external_id) {
    $existing = $wpdb->get_var($wpdb->prepare(
      "SELECT cert_no FROM $tbl WHERE external_id=%s AND role=%s AND status='valid'",
      $external_id, $role
    ));
    if ($existing) {
      return new WP_Error('kklt_duplicate', 'Certyfikat już istnieje dla tego ID: ' . $existing, array('status'=>400));
    }
  }
  
  $cert_no = kklt_generate_cert_no($role, $external_id);
  
  // Unikalność numeru (do 5 prób)
  $tries = 0;
  do {
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl WHERE cert_no=%s", $cert_no));
    if ($exists) { 
      $cert_no = kklt_generate_cert_no($role, $external_id ? $external_id . '-' . wp_rand(100,999) : null);
    }
    $tries++;
  } while (!empty($exists) && $tries < 5);

  $ok = $wpdb->insert($tbl, array(
    'cert_no'   => $cert_no,
    'user_id'   => $user_id,
    'external_id' => $external_id,
    'role'      => $role,
    'issued_at' => current_time('mysql'),
    'valid_until'=> null,
    'status'    => 'valid',
    'meta'      => null
  ), array('%s','%d','%s','%s','%s','%s','%s','%s'));
  if (!$ok) return new WP_Error('kklt_db', 'Błąd wystawienia certyfikatu', array('status'=>500));
  return new WP_REST_Response(array('ok' => true, 'cert_no' => $cert_no), 200);
}

function kklt_rest_my_certificates(WP_REST_Request $req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $tbl = $wpdb->prefix . 'kk_certificates';
  $rows = $wpdb->get_results($wpdb->prepare("SELECT cert_no, role, external_id, issued_at, valid_until, status FROM $tbl WHERE user_id=%d ORDER BY issued_at DESC", $user_id), ARRAY_A);
  return new WP_REST_Response(array('items' => $rows), 200);
}

function kklt_rest_verify_certificate(WP_REST_Request $req) {
  global $wpdb;
  $cert_no = sanitize_text_field($req->get_param('cert_no'));
  if (!$cert_no) return new WP_Error('kklt_bad_request', 'Brak cert_no', array('status'=>400));
  $tbl = $wpdb->prefix . 'kk_certificates';
  
  // First try exact match
  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT c.cert_no, c.role, c.external_id, c.issued_at, c.valid_until, c.status, u.display_name as owner
     FROM $tbl c JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE c.cert_no=%s",
    $cert_no
  ), ARRAY_A);
  
  // If not found, try matching by external_id (raw ID without prefix)
  if (!$row) {
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT c.cert_no, c.role, c.external_id, c.issued_at, c.valid_until, c.status, u.display_name as owner
       FROM $tbl c JOIN {$wpdb->users} u ON u.ID = c.user_id WHERE c.external_id=%s",
      $cert_no
    ), ARRAY_A);
    
    if (!empty($rows)) {
      // Return all certificates for this external_id
      return new WP_REST_Response(array('found' => true, 'multiple' => true, 'data' => $rows), 200);
    }
    
    return new WP_REST_Response(array('found' => false), 200);
  }
  
  return new WP_REST_Response(array('found' => true, 'data' => $row), 200);
}

function kklt_rest_check_kr_certificate(WP_REST_Request $req) {
  global $wpdb;
  $user_id = get_current_user_id();
  $external_id = kk_get_user_system_id($user_id);
  
  if (!$external_id) {
    return new WP_REST_Response(array('has_kr' => false), 200);
  }
  
  $tbl = $wpdb->prefix . 'kk_certificates';
  $cert = $wpdb->get_row($wpdb->prepare(
    "SELECT cert_no, issued_at FROM $tbl WHERE external_id=%s AND role='KR' AND status='valid' LIMIT 1",
    $external_id
  ), ARRAY_A);
  
  if ($cert) {
    return new WP_REST_Response(array('has_kr' => true, 'cert_no' => $cert['cert_no'], 'issued_at' => $cert['issued_at']), 200);
  }
  
  return new WP_REST_Response(array('has_kr' => false), 200);
}

/* ===== WooCommerce Integration ===== */
function kklt_wc_is_active() {
  include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
  return function_exists('is_plugin_active') && is_plugin_active('woocommerce/woocommerce.php') && class_exists('WooCommerce');
}

add_action('plugins_loaded', function() {
  if (!kklt_wc_is_active()) return;
  
  // Add "Zostań Koordynatorem Reklamy" tab to My Account
  add_filter('woocommerce_account_menu_items', function($items) {
    $new = array();
    foreach ($items as $key => $label) {
      if ($key === 'customer-logout') {
        $new['kk-course'] = 'Zostań Koordynatorem Reklamy';
      }
      $new[$key] = $label;
    }
    if (!isset($new['kk-course'])) {
      $new['kk-course'] = 'Zostań Koordynatorem Reklamy';
    }
    return $new;
  }, 99, 1);
  
  // Register endpoint
  add_action('init', function() {
    add_rewrite_endpoint('kk-course', EP_ROOT | EP_PAGES);
  });
  
  // Add content to the endpoint
  add_action('woocommerce_account_kk-course_endpoint', function() {
    echo do_shortcode('[kk_course]');
  });
});

/* ===== Admin Settings Page ===== */
add_action('admin_menu', function() {
  add_menu_page(
    'KK Lite – Kurs',
    'KK Lite',
    'manage_options',
    'kk-lite-settings',
    'kklt_settings_page',
    'dashicons-welcome-learn-more',
    30
  );
});

function kklt_settings_page() {
  if (!current_user_can('manage_options')) {
    return;
  }
  
  // Save settings
  if (isset($_POST['kklt_settings_submit'])) {
    check_admin_referer('kklt_settings');
    
    update_option('kklt_course_page_id', intval($_POST['kklt_course_page_id']));
    update_option('kklt_welcome_text', wp_kses_post($_POST['kklt_welcome_text']));
    update_option('kklt_completion_text', wp_kses_post($_POST['kklt_completion_text']));
    
    echo '<div class="notice notice-success"><p>Ustawienia zapisane!</p></div>';
  }
  
  $course_page_id = get_option('kklt_course_page_id', 0);
  $welcome_text = get_option('kklt_welcome_text', '');
  $completion_text = get_option('kklt_completion_text', '');
  
  $pages = get_pages();
  
  ?>
  <div class="wrap">
    <h1>KK Lite – Ustawienia kursu</h1>
    <p>Konfiguracja kursu Koordynatora Reklamy i certyfikatów.</p>
    
    <form method="post" action="">
      <?php wp_nonce_field('kklt_settings'); ?>
      
      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="kklt_course_page_id">Strona kursu</label>
          </th>
          <td>
            <select name="kklt_course_page_id" id="kklt_course_page_id" class="regular-text">
              <option value="0">-- Wybierz stronę --</option>
              <?php foreach ($pages as $page): ?>
                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($course_page_id, $page->ID); ?>>
                  <?php echo esc_html($page->post_title); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="description">
              Wybierz stronę, na której użyto shortcode [kk_course].<br>
              Jeśli nie masz takiej strony, utwórz nową i dodaj shortcode <code>[kk_course]</code> w treści.
            </p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="kklt_welcome_text">Tekst powitalny</label>
          </th>
          <td>
            <textarea name="kklt_welcome_text" id="kklt_welcome_text" rows="4" class="large-text"><?php echo esc_textarea($welcome_text); ?></textarea>
            <p class="description">Opcjonalny tekst wyświetlany na początku kursu (HTML dozwolony).</p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">
            <label for="kklt_completion_text">Tekst po ukończeniu</label>
          </th>
          <td>
            <textarea name="kklt_completion_text" id="kklt_completion_text" rows="4" class="large-text"><?php echo esc_textarea($completion_text); ?></textarea>
            <p class="description">Opcjonalny tekst wyświetlany po otrzymaniu certyfikatu (HTML dozwolony).</p>
          </td>
        </tr>
      </table>
      
      <h2>Informacje</h2>
      <table class="form-table">
        <tr>
          <th>Shortcody:</th>
          <td>
            <code>[kk_course]</code> - Panel kursu z modułami i testem końcowym
          </td>
        </tr>
        <tr>
          <th>Adresy URL:</th>
          <td>
            <a href="<?php echo home_url('/kk/certyfikaty/'); ?>" target="_blank">/kk/certyfikaty/</a> - Panel certyfikatów<br>
            <a href="<?php echo home_url('/kk/weryfikacja/'); ?>" target="_blank">/kk/weryfikacja/</a> - Weryfikacja certyfikatów<br>
            <a href="<?php echo home_url('/kk-safe/'); ?>" target="_blank">/kk-safe/</a> - Safe View (MU plugin)
          </td>
        </tr>
        <tr>
          <th>WooCommerce:</th>
          <td>
            <?php if (kklt_wc_is_active()): ?>
              <span style="color:#059669">✓ Aktywny</span> - Zakładka "Zostań Koordynatorem Reklamy" dostępna w Moje konto
            <?php else: ?>
              <span style="color:#dc2626">✗ Nieaktywny</span>
            <?php endif; ?>
          </td>
        </tr>
      </table>
      
      <?php submit_button('Zapisz ustawienia', 'primary', 'kklt_settings_submit'); ?>
    </form>
    
    <hr>
    
    <h2>Dokumentacja</h2>
    <p>Pełna dokumentacja dostępna w pliku: <code>dist/README.md</code></p>
    
    <h3>Szybki start:</h3>
    <ol>
      <li>Dodaj pole "ID systemowe" do profili użytkowników (patrz README.md)</li>
      <li>Utwórz stronę z shortcode <code>[kk_course]</code></li>
      <li>Wybierz tę stronę w ustawieniach powyżej</li>
      <li>Użytkownicy mogą teraz przejść kurs i otrzymać certyfikat KR</li>
      <li>Administratorzy mogą wystawiać certyfikaty MR/RT w panelu <a href="<?php echo home_url('/kk/certyfikaty/'); ?>">/kk/certyfikaty/</a></li>
    </ol>
  </div>
  <?php
}