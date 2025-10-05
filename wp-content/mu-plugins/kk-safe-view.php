<?php
/**
 * Plugin Name: KK Safe View (MU)
 * Description: Enhanced certificate panel with external_id filtering support
 * Version: 1.0.0
 * Author: Fundacja Werbekoordinator
 */
if (!defined('ABSPATH')) { exit; }

/**
 * Register custom routes for the safe view
 */
function kk_safe_view_register_routes() {
  add_rewrite_rule('^kk/certyfikaty/?$', 'index.php?kk_safe_cert_view=1', 'top');
  add_rewrite_rule('^kk/weryfikacja/?$', 'index.php?kk_safe_verify_view=1', 'top');
}
add_action('init', 'kk_safe_view_register_routes');

add_filter('query_vars', function($vars){
  $vars[] = 'kk_safe_cert_view';
  $vars[] = 'kk_safe_verify_view';
  return $vars;
});

/**
 * Serve the enhanced certificate view
 */
add_action('template_redirect', function(){
  if (is_admin()) return;

  $is_cert = get_query_var('kk_safe_cert_view');
  $is_ver  = get_query_var('kk_safe_verify_view');

  if ($is_cert) {
    nocache_headers();
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');

    // REST URL and nonce (keep existing NONCE injection)
    $rest_url = esc_url_raw( rest_url('kk/v1') );
    $nonce    = wp_create_nonce('wp_rest');

    ?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <title>Moje certyfikaty – Werbekoordinator</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:24px;color:#1b1f24;background:#f6f7f9}
    .card{border:1px solid #e5e7eb;border-radius:10px;padding:16px;background:#fff;margin-bottom:16px}
    .muted{color:#6b7280}
    iframe{width:100%; height:1120px; border:0; background:#f3f4f6}
    .list{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}
    .pill{border:1px solid #e5e7eb;border-radius:18px;padding:6px 10px;background:#f8fafc;font-size:14px;cursor:pointer}
    button{background:#0b5cab;color:#fff;border:none;border-radius:6px;padding:8px 12px;cursor:pointer}
    button.secondary{background:#6b7280}
    input[type="text"]{flex:1;padding:8px;border:1px solid #e5e7eb;border-radius:6px}
  </style>
  <script>
    // Keep existing NONCE injection
    window.KK = { 
      rest: <?php echo json_encode($rest_url); ?>, 
      nonce: <?php echo json_encode($nonce); ?> 
    };

    function rest(path, opts = {}) {
      const base = window.KK.rest.replace(/\/$/,'');
      const isGet = !opts.method || opts.method.toUpperCase() === 'GET';
      let url = `${base}${path}`;
      if (isGet && window.KK.nonce) url += (url.includes('?') ? '&' : '?') + '_wpnonce=' + encodeURIComponent(window.KK.nonce);
      const headers = Object.assign({}, opts.headers || {});
      if (window.KK.nonce) headers['X-WP-Nonce'] = window.KK.nonce;
      return fetch(url, Object.assign({ credentials:'include' }, opts, { headers }));
    }

    async function fetchMyCertificates(){
      // Enhanced: pass typed external_id as query param when present
      const externalIdInput = document.getElementById('externalIdInput');
      const externalId = externalIdInput ? externalIdInput.value.trim() : '';
      
      let queryString = '';
      if (externalId) {
        queryString = '?external_id=' + encodeURIComponent(externalId);
      }
      
      const res = await rest('/certificate/my' + queryString, { method:'GET' });
      if(!res.ok){ throw new Error('Brak dostępu – zaloguj się na stronie Moje konto.'); }
      return res.json();
    }

    function renderList(items){
      const list = document.getElementById('list'); 
      list.innerHTML = '';
      if(!items || !items.items || items.items.length===0){
        list.innerHTML = '<div class="muted">Nie znaleziono certyfikatów.</div>'; 
        return;
      }
      for(const it of items.items){
        const el = document.createElement('div');
        el.className = 'pill';
        el.textContent = `${it.role} • ${it.cert_no} • ${it.status}`;
        el.onclick = () => loadCert(it);
        list.appendChild(el);
      }
      if (items.items.length > 0) {
        loadCert(items.items[0]);
      }
    }

    function loadCert(rec){
      const frame = document.getElementById("certFrame");
      const pluginUrl = '<?php echo esc_js(plugins_url('kk-lite')); ?>';
      const data = {
        role: rec.role, 
        personName: '', 
        userId: '',
        issuedAt: rec.issued_at ? rec.issued_at.slice(0,10) : '',
        validUntil: rec.valid_until || 'bezterminowo',
        certNo: rec.cert_no,
        verifyBase: `${location.origin}/wp-json/kk/v1/certificate/verify?cert_no=`
      };
      frame.onload = () => frame.contentWindow.postMessage({ type:"CERT_DATA", payload:data }, "*");
      frame.src = pluginUrl + "/certificates/certificate.html";
    }

    async function issueFor(role){
      const externalIdInput = document.getElementById('issueExternalId');
      const externalId = externalIdInput ? externalIdInput.value.trim() : '';
      
      const body = { role };
      if ((role === 'MR' || role === 'RT') && externalId) {
        body.external_id = externalId;
      }
      
      const res = await rest('/certificate/issue', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(body)
      });
      let data = {};
      try { data = await res.json(); } catch(e){}
      if(!res.ok || !data.ok){
        const msg = data.message || 'Nie udało się wystawić certyfikatu';
        alert(msg); 
        return;
      }
      
      if (externalIdInput) externalIdInput.value = '';
      
      const items = await fetchMyCertificates(); 
      renderList(items);
    }
    
    async function searchCertificates(){
      try { 
        const items = await fetchMyCertificates(); 
        renderList(items); 
      }
      catch(e) { 
        document.getElementById('status').textContent = e.message; 
      }
    }

    document.addEventListener('DOMContentLoaded', async ()=>{
      try { 
        const items = await fetchMyCertificates(); 
        renderList(items); 
      }
      catch(e) { 
        document.getElementById('status').textContent = e.message; 
      }
    });
  </script>
</head>
<body>
  <h1>Moje certyfikaty</h1>
  <p class="muted" id="status">Panel certyfikatów Werbekoordinator</p>

  <div class="card">
    <h2>Wyszukaj certyfikaty</h2>
    <p class="muted">Wpisz external_id (np. 00000011005), aby zobaczyć certyfikaty dla tego ID:</p>
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <input type="text" id="externalIdInput" placeholder="00000011005">
      <button onclick="searchCertificates()">Szukaj</button>
    </div>
    <div id="list" class="list"></div>
  </div>

  <div class="card">
    <h2>Wystaw nowy certyfikat</h2>
    <p class="muted">Dla MR/RT: administratorzy mogą podać external_id. Dla KR: zawsze używany jest Twój własny ID.</p>
    <div style="margin-bottom:8px">
      <input type="text" id="issueExternalId" placeholder="External ID dla MR/RT (opcjonalnie)" style="width:100%">
    </div>
    <div style="display:flex;gap:8px">
      <button onclick="issueFor('KR')">Wystaw certyfikat KR</button>
      <button class="secondary" onclick="issueFor('MR')">Wystaw certyfikat MR</button>
      <button class="secondary" onclick="issueFor('RT')">Wystaw certyfikat RT</button>
    </div>
  </div>

  <div class="card">
    <h2>Podgląd certyfikatu</h2>
    <iframe id="certFrame" title="Certyfikat"></iframe>
  </div>
</body>
</html>
    <?php
    exit;
  }

  if ($is_ver) {
    nocache_headers();
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');
    
    ?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <title>Weryfikacja certyfikatu – Werbekoordinator</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:24px;color:#111827}
    .ok{color:#065f46;background:#ecfdf5;border:1px solid #34d399;padding:12px;border-radius:8px;margin-bottom:12px}
    .fail{color:#7f1d1d;background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px;margin-bottom:12px}
    .muted{color:#6b7280}
  </style>
  <script>
    function getParam(n){ return new URLSearchParams(location.search).get(n) }
    async function verify(){
      const id = getParam("cert_no") || getParam("id");
      const box = document.getElementById('box');
      if(!id){ box.innerHTML = '<div class="fail">Brak numeru certyfikatu.</div>'; return; }
      try{
        const res = await fetch(`${location.origin}/wp-json/kk/v1/certificate/verify?cert_no=${encodeURIComponent(id)}`);
        const data = await res.json();
        if(!data.found){ box.innerHTML = '<div class="fail">Nie znaleziono certyfikatu.</div>'; return; }
        
        if (Array.isArray(data.data)) {
          let html = '<div class="ok"><strong>Znaleziono certyfikaty dla external_id: ' + id + '</strong><br><br>';
          for (const d of data.data) {
            html += `<div style="margin-bottom:12px;padding:8px;border-left:3px solid #34d399">
              <strong>Numer:</strong> ${d.cert_no}<br>
              <strong>Rola:</strong> ${d.role}<br>
              <strong>Status:</strong> ${d.status}<br>
              <strong>Właściciel:</strong> ${d.owner}<br>
              <strong>Wystawiono:</strong> ${d.issued_at}
            </div>`;
          }
          html += '</div><p class="muted">Dane pochodzą z bazy Werbekoordinator.</p>';
          box.innerHTML = html;
        } else {
          const d = data.data;
          box.innerHTML = `<div class="ok">
            <strong>Status:</strong> ${d.status}<br>
            <strong>Numer:</strong> ${d.cert_no}<br>
            <strong>Właściciel:</strong> ${d.owner}<br>
            <strong>Rola:</strong> ${d.role}<br>
            <strong>Wystawiono:</strong> ${d.issued_at}
          </div><p class="muted">Dane pochodzą z bazy Werbekoordinator.</p>`;
        }
      }catch(e){
        box.innerHTML = '<div class="fail">Błąd połączenia z API. Spróbuj ponownie.</div>';
      }
    }
    document.addEventListener('DOMContentLoaded', verify);
  </script>
</head>
<body>
  <h1>Weryfikacja certyfikatu</h1>
  <div id="box"><div class="muted">Trwa weryfikacja…</div></div>
</body>
</html>
    <?php
    exit;
  }
});
