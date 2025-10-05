<?php
/**
 * Plugin Name: KK Safe View (MU)
 * Description: Must-use plugin for safe certificate viewing and admin issuance by external_id
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

/**
 * Add safe route for certificate viewing at /kk-safe/
 */
function kk_safe_register_route() {
  add_rewrite_rule('^kk-safe/?$', 'index.php?kk_safe_view=1', 'top');
}
add_action('init', 'kk_safe_register_route');

add_filter('query_vars', function($vars) {
  $vars[] = 'kk_safe_view';
  return $vars;
});

/**
 * Render safe view
 */
add_action('template_redirect', function() {
  if (!get_query_var('kk_safe_view')) return;
  
  nocache_headers();
  status_header(200);
  header('Content-Type: text/html; charset=UTF-8');
  
  $rest_url = esc_url_raw(rest_url('kk/v1'));
  $nonce = wp_create_nonce('wp_rest');
  $is_admin = current_user_can('manage_options') ? 'true' : 'false';
  
  ?>
  <!doctype html>
  <html lang="pl">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KK Safe View – Certyfikaty</title>
    <style>
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:24px;color:#1b1f24;background:#f6f7f9}
      .card{border:1px solid #e5e7eb;border-radius:10px;padding:16px;background:#fff;margin-bottom:16px}
      .muted{color:#6b7280;font-size:14px}
      .list{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}
      .pill{border:1px solid #e5e7eb;border-radius:18px;padding:6px 10px;background:#f8fafc;font-size:14px;cursor:pointer}
      button{background:#0b5cab;color:#fff;border:none;border-radius:6px;padding:8px 12px;cursor:pointer;margin:4px}
      button.secondary{background:#6b7280}
      input{border:1px solid #d1d5db;border-radius:4px;padding:6px 10px;font-size:14px}
      .error{color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:12px;border-radius:8px;margin:8px 0}
      .success{color:#065f46;background:#d1fae5;border:1px solid #34d399;padding:12px;border-radius:8px;margin:8px 0}
    </style>
  </head>
  <body>
    <h1>KK Safe View – Certyfikaty</h1>
    <p class="muted">Bezpieczny widok certyfikatów z możliwością wystawiania przez admina.</p>

    <div class="card">
      <h2>Twoje certyfikaty</h2>
      <div id="status" class="muted">Ładowanie...</div>
      <div id="list" class="list"></div>
    </div>

    <div id="adminPanel" class="card" style="display:none">
      <h2>Panel administratora</h2>
      <p class="muted">Wydawanie certyfikatów MR i RT po EXTERNAL_ID</p>
      
      <div style="margin:12px 0">
        <strong>Wydaj MR:</strong><br>
        <input type="text" id="mrExternalId" placeholder="np. 00000011005" style="width:200px">
        <button onclick="issueByExternalId('MR', 'mrExternalId')">Wydaj MR</button>
      </div>
      
      <div style="margin:12px 0">
        <strong>Wydaj RT:</strong><br>
        <input type="text" id="rtExternalId" placeholder="np. 00000011005" style="width:200px">
        <button onclick="issueByExternalId('RT', 'rtExternalId')">Wydaj RT</button>
      </div>
      
      <div id="adminStatus"></div>
    </div>

    <script>
      const KK = { rest: '<?php echo esc_js($rest_url); ?>', nonce: '<?php echo esc_js($nonce); ?>', isAdmin: <?php echo $is_admin; ?> };

      function rest(path, opts = {}) {
        const base = KK.rest.replace(/\/$/,'');
        const isGet = !opts.method || opts.method.toUpperCase() === 'GET';
        let url = `${base}${path}`;
        if (isGet && KK.nonce) url += (url.includes('?') ? '&' : '?') + '_wpnonce=' + encodeURIComponent(KK.nonce);
        const headers = Object.assign({}, opts.headers || {});
        if (KK.nonce) headers['X-WP-Nonce'] = KK.nonce;
        return fetch(url, Object.assign({ credentials:'include' }, opts, { headers }));
      }

      async function loadCertificates() {
        try {
          const res = await rest('/certificate/my', { method: 'GET' });
          if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`HTTP ${res.status}: ${res.statusText}. ${errorText}`);
          }
          const data = await res.json();
          renderList(data);
        } catch(e) {
          document.getElementById('status').innerHTML = `<div class="error">Błąd ładowania: ${e.message}</div>`;
        }
      }

      function renderList(data) {
        const list = document.getElementById('list');
        const status = document.getElementById('status');
        
        if (!data.items || data.items.length === 0) {
          list.innerHTML = '';
          status.textContent = 'Nie masz jeszcze certyfikatów.';
          return;
        }
        
        status.textContent = `Znaleziono ${data.items.length} certyfikat(ów).`;
        list.innerHTML = '';
        
        data.items.forEach(it => {
          const pill = document.createElement('div');
          pill.className = 'pill';
          pill.textContent = `${it.role} • ${it.cert_no} • ${it.status}`;
          list.appendChild(pill);
        });
      }

      async function issueByExternalId(role, inputId) {
        const externalId = document.getElementById(inputId).value.trim();
        const statusEl = document.getElementById('adminStatus');
        
        if (!externalId) {
          statusEl.innerHTML = '<div class="error">Podaj EXTERNAL_ID</div>';
          return;
        }
        
        try {
          const res = await rest('/certificate/issue', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role, external_id: externalId })
          });
          
          const data = await res.json();
          
          if (!res.ok || !data.ok) {
            const msg = data.message || 'Błąd wystawiania certyfikatu';
            statusEl.innerHTML = `<div class="error">Błąd (${res.status}): ${msg}</div>`;
            return;
          }
          
          statusEl.innerHTML = `<div class="success">Certyfikat ${data.cert_no} wystawiony!</div>`;
          document.getElementById(inputId).value = '';
          
          // Reload certificates
          await loadCertificates();
        } catch(e) {
          statusEl.innerHTML = `<div class="error">Błąd połączenia: ${e.message}</div>`;
        }
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await loadCertificates();
        if (KK.isAdmin) {
          document.getElementById('adminPanel').style.display = 'block';
        }
      });
    </script>
  </body>
  </html>
  <?php
  exit;
});

/**
 * Flush rewrite rules on activation
 */
register_activation_hook(__FILE__, function() {
  kk_safe_register_route();
  flush_rewrite_rules();
});
