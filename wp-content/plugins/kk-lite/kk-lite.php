<?php
/**
 * Plugin Name: KK Lite
 * Plugin URI: https://werbekoordinator.pl
 * Description: Certyfikaty koordynatorów - lekka wersja z pełnym REST API i panelem zarządzania
 * Version: 1.0.4
 * Author: Werbekoordinator.pl
 * Text Domain: kk-lite
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

class KK_Lite {
    private static $instance = null;
    private $plugin_dir;
    private $plugin_url;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Hooks
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_template_redirect'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('rest_authentication_errors', array($this, 'bypass_nonce_for_logged_in'), 99);
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Tabela wyników testów
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kk_course_results (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            module_id int(11) NOT NULL,
            score int(11) NOT NULL,
            passed tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY module_id (module_id)
        ) $charset_collate;";

        // Tabela certyfikatów
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}kk_certificates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            cert_no varchar(100) NOT NULL,
            role varchar(10) NOT NULL,
            issued_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            valid_until datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY cert_no (cert_no),
            KEY user_id (user_id),
            KEY role (role)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function add_rewrite_rules() {
        add_rewrite_rule('^kk/certyfikaty/?$', 'index.php?kk_view=certyfikaty', 'top');
        add_rewrite_rule('^kk/weryfikacja/?$', 'index.php?kk_view=weryfikacja', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'kk_view';
        return $vars;
    }

    public function handle_template_redirect() {
        $kk_view = get_query_var('kk_view');
        
        if (!$kk_view) {
            return;
        }

        nocache_headers();
        status_header(200);

        if ($kk_view === 'certyfikaty') {
            $this->render_template('templates/app.html');
            exit;
        } elseif ($kk_view === 'weryfikacja') {
            $this->render_template('templates/verify.html');
            exit;
        }
    }

    private function render_template($template_path) {
        $file = $this->plugin_dir . $template_path;
        
        if (!file_exists($file)) {
            wp_die('Template not found: ' . esc_html($template_path));
        }

        // Wczytaj zawartość i przekonwertuj na UTF-8 jeśli potrzeba
        $content = file_get_contents($file);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1250'], true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            if (function_exists('mb_convert_encoding')) {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            } elseif (function_exists('iconv')) {
                $content = iconv($encoding, 'UTF-8//TRANSLIT', $content);
            }
        }

        // Zamień relatywne ścieżki na absolutne URL pluginu
        $content = $this->rewrite_asset_paths($content);

        // Wstrzyknij konfigurację JS
        $rest_url = rest_url('kk/v1/');
        $nonce = wp_create_nonce('wp_rest');
        
        $config_script = sprintf(
            '<script>window.KK = {rest: "%s", nonce: "%s"};</script>',
            esc_js($rest_url),
            esc_js($nonce)
        );

        $content = str_replace('</head>', $config_script . '</head>', $content);

        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
    }

    private function rewrite_asset_paths($content) {
        $base_url = $this->plugin_url;
        
        // Zastąp względne ścieżki
        $patterns = array(
            '/(?<=src=")\.\//' => $base_url,
            '/(?<=href=")\.\//' => $base_url,
            '/(?<=src=")\.\.\//' => dirname($base_url) . '/',
            '/(?<=href=")\.\.\//' => dirname($base_url) . '/',
            '/(?<=[\'"(])assets\//' => $base_url . 'assets/',
            '/(?<=[\'"(])templates\//' => $base_url . 'templates/',
            '/(?<=[\'"(])certificates\//' => $base_url . 'certificates/',
            '/(?<=[\'"(])data\//' => $base_url . 'data/',
        );

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    public function register_rest_routes() {
        // POST /test-result
        register_rest_route('kk/v1', '/test-result', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_test_result'),
            'permission_callback' => array($this, 'check_user_logged_in'),
        ));

        // POST /certificate/issue
        register_rest_route('kk/v1', '/certificate/issue', array(
            'methods' => 'POST',
            'callback' => array($this, 'issue_certificate'),
            'permission_callback' => array($this, 'check_user_logged_in'),
        ));

        // GET /certificate/my
        register_rest_route('kk/v1', '/certificate/my', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_my_certificates'),
            'permission_callback' => array($this, 'check_user_logged_in'),
        ));

        // GET /certificate/verify
        register_rest_route('kk/v1', '/certificate/verify', array(
            'methods' => 'GET',
            'callback' => array($this, 'verify_certificate'),
            'permission_callback' => '__return_true', // Public
        ));
    }

    public function check_user_logged_in() {
        return is_user_logged_in();
    }

    public function bypass_nonce_for_logged_in($result) {
        // Jeśli już jest błąd, nie nadpisuj
        if (is_wp_error($result)) {
            return $result;
        }

        // Dla zalogowanych użytkowników omijamy weryfikację nonce
        if (is_user_logged_in()) {
            return true;
        }

        return $result;
    }

    public function save_test_result(WP_REST_Request $request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $module_id = absint($request->get_param('module_id'));
        $score = absint($request->get_param('score'));
        $passed = (bool) $request->get_param('passed');

        $result = $wpdb->insert(
            $wpdb->prefix . 'kk_course_results',
            array(
                'user_id' => $user_id,
                'module_id' => $module_id,
                'score' => $score,
                'passed' => $passed ? 1 : 0,
            ),
            array('%d', '%d', '%d', '%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to save result', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'id' => $wpdb->insert_id,
        ), 200);
    }

    public function issue_certificate(WP_REST_Request $request) {
        global $wpdb;
        
        $current_user_id = get_current_user_id();
        $role = sanitize_text_field($request->get_param('role'));
        $user_id = $request->get_param('user_id');

        // Walidacja roli
        if (!in_array($role, array('KR', 'MR', 'RT'))) {
            return new WP_Error('invalid_role', 'Invalid role', array('status' => 400));
        }

        // Jeśli user_id nie jest podane, użyj zalogowanego użytkownika
        if (!$user_id) {
            $user_id = $current_user_id;
        } else {
            $user_id = absint($user_id);
            // Tylko admin może wydawać certyfikaty dla innych
            if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Only admins can issue certificates for others', array('status' => 403));
            }
        }

        // Generuj numer certyfikatu: ROLA-YYYYMMDD-####
        $date = date('Ymd');
        
        // Znajdź ostatni numer dla tego dnia i roli
        $last_cert = $wpdb->get_var($wpdb->prepare(
            "SELECT cert_no FROM {$wpdb->prefix}kk_certificates 
            WHERE cert_no LIKE %s 
            ORDER BY cert_no DESC LIMIT 1",
            $wpdb->esc_like($role . '-' . $date) . '-%'
        ));

        if ($last_cert) {
            // Wyciągnij numer sekwencyjny
            $parts = explode('-', $last_cert);
            $seq = isset($parts[2]) ? intval($parts[2]) : 0;
            $seq++;
        } else {
            $seq = 1;
        }

        $cert_no = sprintf('%s-%s-%04d', $role, $date, $seq);

        // Data ważności: 2 lata od wystawienia
        $valid_until = date('Y-m-d H:i:s', strtotime('+2 years'));

        $result = $wpdb->insert(
            $wpdb->prefix . 'kk_certificates',
            array(
                'user_id' => $user_id,
                'cert_no' => $cert_no,
                'role' => $role,
                'valid_until' => $valid_until,
                'status' => 'active',
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Failed to issue certificate', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'cert_no' => $cert_no,
            'issued_at' => current_time('mysql'),
            'valid_until' => $valid_until,
        ), 201);
    }

    public function get_my_certificates(WP_REST_Request $request) {
        global $wpdb;
        
        $user_id = get_current_user_id();

        $certs = $wpdb->get_results($wpdb->prepare(
            "SELECT cert_no, role, issued_at, valid_until, status 
            FROM {$wpdb->prefix}kk_certificates 
            WHERE user_id = %d 
            ORDER BY issued_at DESC",
            $user_id
        ), ARRAY_A);

        return new WP_REST_Response($certs, 200);
    }

    public function verify_certificate(WP_REST_Request $request) {
        global $wpdb;
        
        $cert_no = sanitize_text_field($request->get_param('cert_no'));

        if (empty($cert_no)) {
            return new WP_REST_Response(array('found' => false), 200);
        }

        $cert = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.display_name as owner 
            FROM {$wpdb->prefix}kk_certificates c 
            LEFT JOIN {$wpdb->prefix}users u ON c.user_id = u.ID 
            WHERE c.cert_no = %s",
            $cert_no
        ), ARRAY_A);

        if (!$cert) {
            return new WP_REST_Response(array('found' => false), 200);
        }

        return new WP_REST_Response(array(
            'found' => true,
            'data' => $cert,
        ), 200);
    }
}

// Inicjalizuj plugin
KK_Lite::get_instance();
