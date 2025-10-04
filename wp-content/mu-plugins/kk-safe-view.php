<?php
/**
 * Plugin Name: KK Safe View
 * Description: Hotfix renderer dla /kk/* - działa nawet jeśli rewrite rules nie są skonfigurowane lub plugin KK Lite jest wyłączony
 * Version: 1.0.0
 * Author: Werbekoordinator.pl
 */

defined('ABSPATH') || exit;

class KK_Safe_View {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('template_redirect', array($this, 'intercept_kk_routes'), 0);
        add_filter('rest_authentication_errors', array($this, 'bypass_nonce_for_logged_in'), 99);
    }

    public function intercept_kk_routes() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Sprawdź czy to /kk/certyfikaty/ lub /kk/weryfikacja/
        if (preg_match('#^/kk/certyfikaty/?(\?.*)?$#', $request_uri)) {
            $this->render_certyfikaty();
            exit;
        } elseif (preg_match('#^/kk/weryfikacja/?(\?.*)?$#', $request_uri)) {
            $this->render_weryfikacja();
            exit;
        }
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

    private function render_certyfikaty() {
        nocache_headers();
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');

        $rest_url = rest_url('kk/v1/');
        $nonce = wp_create_nonce('wp_rest');
        $plugin_url = $this->get_plugin_url();

        ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KK - Panel Certyfikatów</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 { color: #333; margin-bottom: 30px; }
        .actions { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary { background: #0073aa; color: white; }
        .btn-primary:hover { background: #005a87; }
        .btn-secondary { background: #f0f0f0; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f9f9f9; font-weight: 600; }
        .loading { text-align: center; padding: 20px; color: #666; }
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .preview-section { margin-top: 30px; border-top: 2px solid #e0e0e0; padding-top: 30px; }
        iframe { width: 100%; height: 800px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-view {
            background: #0073aa;
            color: white;
            padding: 6px 12px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
    </style>
    <script>window.KK = {rest: "<?php echo esc_js($rest_url); ?>", nonce: "<?php echo esc_js($nonce); ?>", pluginUrl: "<?php echo esc_js($plugin_url); ?>"};</script>
</head>
<body>
    <div class="container">
        <h1>Panel Certyfikatów Koordynatora (Safe View)</h1>
        <div id="message"></div>
        <div class="actions">
            <button class="btn-primary" onclick="issueCert('KR')">Wydaj KR</button>
            <button class="btn-primary" onclick="issueCert('MR')">Wydaj MR</button>
            <button class="btn-primary" onclick="issueCert('RT')">Wydaj RT</button>
            <button class="btn-secondary" onclick="refreshList()">Odśwież</button>
        </div>
        <div id="certs-container"><div class="loading">Ładowanie...</div></div>
        <div class="preview-section" id="preview-section" style="display: none;">
            <h2>Podgląd certyfikatu</h2>
            <iframe id="cert-preview" src=""></iframe>
        </div>
    </div>
    <script>
        let currentCerts = [];
        function apiCall(endpoint, options = {}) {
            const url = window.KK.rest + endpoint;
            const defaultOptions = {
                credentials: 'include',
                headers: {'Content-Type': 'application/json'}
            };
            if (window.KK.nonce) defaultOptions.headers['X-WP-Nonce'] = window.KK.nonce;
            return fetch(url, { ...defaultOptions, ...options });
        }
        function showMessage(text, type = 'success') {
            const msgDiv = document.getElementById('message');
            msgDiv.className = type;
            msgDiv.textContent = text;
            msgDiv.style.display = 'block';
            setTimeout(() => msgDiv.style.display = 'none', 5000);
        }
        function getRoleLabel(role) {
            return {'KR': 'Koordynator Relacji', 'MR': 'Manager Relacji', 'RT': 'Recruitment Team'}[role] || role;
        }
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('pl-PL');
        }
        async function loadCertificates() {
            try {
                const response = await apiCall('certificate/my');
                if (!response.ok) throw new Error('Błąd pobierania');
                currentCerts = await response.json();
                renderCertsList();
            } catch (error) {
                document.getElementById('certs-container').innerHTML = '<div class="error">Błąd ładowania certyfikatów</div>';
            }
        }
        function renderCertsList() {
            const container = document.getElementById('certs-container');
            if (currentCerts.length === 0) {
                container.innerHTML = '<p style="padding: 20px; text-align: center; color: #666;">Brak certyfikatów</p>';
                return;
            }
            let html = '<table><thead><tr><th>Rola</th><th>Numer</th><th>Wydany</th><th>Ważny do</th><th>Akcje</th></tr></thead><tbody>';
            currentCerts.forEach(cert => {
                html += '<tr>';
                html += `<td>${getRoleLabel(cert.role)}</td>`;
                html += `<td><strong>${cert.cert_no}</strong></td>`;
                html += `<td>${formatDate(cert.issued_at)}</td>`;
                html += `<td>${formatDate(cert.valid_until)}</td>`;
                html += `<td><button class="btn-view" onclick='previewCert(${JSON.stringify(cert)})'>Podgląd</button></td>`;
                html += '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        }
        async function issueCert(role) {
            if (!confirm('Wydać certyfikat ' + getRoleLabel(role) + '?')) return;
            try {
                const response = await apiCall('certificate/issue', {
                    method: 'POST',
                    body: JSON.stringify({ role })
                });
                if (!response.ok) throw new Error('Błąd wydawania');
                const result = await response.json();
                showMessage('Certyfikat ' + result.cert_no + ' wydany!', 'success');
                await loadCertificates();
            } catch (error) {
                showMessage('Błąd: ' + error.message, 'error');
            }
        }
        function previewCert(cert) {
            const iframe = document.getElementById('cert-preview');
            const certUrl = window.KK.pluginUrl + 'certificates/certificate.html';
            iframe.src = certUrl;
            iframe.onload = function() {
                iframe.contentWindow.postMessage({
                    type: 'CERT_DATA',
                    payload: {
                        certNo: cert.cert_no,
                        role: cert.role,
                        roleLabel: getRoleLabel(cert.role),
                        issueDate: formatDate(cert.issued_at),
                        validUntil: formatDate(cert.valid_until),
                        verifyUrl: window.location.origin + '/kk/weryfikacja/?cert_no=' + cert.cert_no,
                        personName: '',
                        userId: ''
                    }
                }, '*');
            };
            document.getElementById('preview-section').style.display = 'block';
        }
        function refreshList() {
            loadCertificates();
            showMessage('Odświeżono', 'success');
        }
        window.addEventListener('DOMContentLoaded', loadCertificates);
    </script>
</body>
</html>
        <?php
    }

    private function render_weryfikacja() {
        nocache_headers();
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');

        $rest_url = rest_url('kk/v1/');
        ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weryfikacja Certyfikatu - KK</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        h1 { color: #333; margin-bottom: 30px; text-align: center; }
        .search-box { display: flex; gap: 10px; margin-bottom: 30px; }
        input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
        }
        button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        .result { padding: 20px; border-radius: 8px; margin-top: 20px; }
        .result-found { background: #e8f5e9; border: 2px solid #4caf50; }
        .result-not-found { background: #ffebee; border: 2px solid #f44336; }
        .cert-info { margin-top: 15px; }
        .cert-row { display: flex; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .cert-label { font-weight: 600; color: #555; width: 140px; }
        .cert-value { color: #333; flex: 1; }
    </style>
    <script>window.KK = {rest: "<?php echo esc_js($rest_url); ?>"};</script>
</head>
<body>
    <div class="container">
        <h1>🎓 Weryfikacja Certyfikatu</h1>
        <div class="search-box">
            <input type="text" id="cert-input" placeholder="np. KR-20240101-0001" />
            <button onclick="verifyCertificate()">Weryfikuj</button>
        </div>
        <div id="result-container"></div>
    </div>
    <script>
        function getUrlParam(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('pl-PL', {year: 'numeric', month: 'long', day: 'numeric'});
        }
        function getRoleLabel(role) {
            return {'KR': 'Koordynator Relacji', 'MR': 'Manager Relacji', 'RT': 'Recruitment Team'}[role] || role;
        }
        async function verifyCertificate() {
            const certNo = document.getElementById('cert-input').value.trim();
            const resultContainer = document.getElementById('result-container');
            if (!certNo) {
                resultContainer.innerHTML = '<div class="result result-not-found">Wprowadź numer</div>';
                return;
            }
            resultContainer.innerHTML = '<div style="text-align:center;padding:20px;">Weryfikacja...</div>';
            try {
                const url = window.KK.rest + 'certificate/verify?cert_no=' + encodeURIComponent(certNo);
                const response = await fetch(url, {credentials: 'include'});
                if (!response.ok) throw new Error('Błąd');
                const result = await response.json();
                if (result.found) {
                    const cert = result.data;
                    const isExpired = new Date(cert.valid_until) < new Date();
                    let html = '<div class="result result-found">';
                    html += '<h3 style="color: #2e7d32; margin-bottom: 15px;">✓ Autentyczny</h3>';
                    html += '<div class="cert-info">';
                    html += `<div class="cert-row"><div class="cert-label">Numer:</div><div class="cert-value"><strong>${cert.cert_no}</strong></div></div>`;
                    html += `<div class="cert-row"><div class="cert-label">Rola:</div><div class="cert-value">${getRoleLabel(cert.role)}</div></div>`;
                    html += `<div class="cert-row"><div class="cert-label">Właściciel:</div><div class="cert-value">${cert.owner || 'Nie podano'}</div></div>`;
                    html += `<div class="cert-row"><div class="cert-label">Wydany:</div><div class="cert-value">${formatDate(cert.issued_at)}</div></div>`;
                    html += `<div class="cert-row"><div class="cert-label">Ważny do:</div><div class="cert-value">${formatDate(cert.valid_until)}</div></div>`;
                    html += `<div class="cert-row"><div class="cert-label">Status:</div><div class="cert-value">${isExpired ? 'Wygasł' : 'Aktywny'}</div></div>`;
                    html += '</div></div>';
                    resultContainer.innerHTML = html;
                } else {
                    resultContainer.innerHTML = '<div class="result result-not-found"><h3 style="color: #c62828;">✗ Nie znaleziono</h3></div>';
                }
            } catch (error) {
                resultContainer.innerHTML = '<div class="result result-not-found"><h3>Błąd weryfikacji</h3></div>';
            }
        }
        window.addEventListener('DOMContentLoaded', function() {
            const certNo = getUrlParam('cert_no');
            if (certNo) {
                document.getElementById('cert-input').value = certNo;
                verifyCertificate();
            }
        });
        document.getElementById('cert-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') verifyCertificate();
        });
    </script>
</body>
</html>
        <?php
    }

    private function get_plugin_url() {
        // Próbuj znaleźć URL pluginu KK Lite
        if (defined('WP_PLUGIN_URL')) {
            return WP_PLUGIN_URL . '/kk-lite/';
        }
        return plugins_url('kk-lite/');
    }
}

KK_Safe_View::get_instance();
