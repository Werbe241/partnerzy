<?php
/*
Plugin Name: KK – Safe View (hotfix)
Description: Jednoplikiowy hotfix: stabilny widok /kk/certyfikaty/ i /kk/weryfikacja/ + REST bez nonce dla zalogowanych. Dodatkowo dla administratorów wydawanie MR/RT po wpisaniu zewnętrznego ID.
Author: Copilot
Version: 1.0.1
*/
if (!defined('ABSPATH')) exit;
// REST: zalogowani mogą bez X-WP-Nonce (tymczasowo/bypass w Safe View)
add_filter('rest_authentication_errors', function($result){
  if (!empty($result)) return $result;
  if (is_user_logged_in()) return true;
  return $result;
}, 0);
// Prosty helper: czy admin
function kk_sv_is_admin(){ return current_user_can('manage_options'); }
// Safe rendering — przechwyt /kk/* zanim zadziała motyw/wtyczki
add_action('template_redirect', function(){
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $isCert = preg_match('#/kk/certyfikaty/?($|\?)#', $uri);
  $isVer  = preg_match('#/kk/weryfikacja/?($|\?)#', $uri);
  if (!$isCert && !$isVer) return; // nic nie robimy dla innych URL-i
  nocache_headers(); status_header(200);
  header('Content-Type: text/html; charset=UTF-8');
  if ($isCert) {
    $rest = esc_url_raw( rest_url('kk/v1') );
    $admin = kk_sv_is_admin() ? 'true' : 'false';
    echo '<!doctype html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Panel Certyfikatów (Safe View)</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:24px;background:#f6f7f9;color:#111} .wrap{max-width:960px;margin:0 auto} .card{border:1px solid #e5e7eb;border-radius:12px;padding:16px;background:#fff;margin-bottom:16px;box-shadow:0 1px 2px rgba(0,0,0,.04)} .muted{color:#6b7280} .list{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0} .pill{border:1px solid #e5e7eb;border-radius:18px;padding:6px 10px;background:#f8fafc;font-size:14px;cursor:pointer} button{background:#0b5cab;color:#fff;border:none;border-radius:6px;padding:10px 14px;cursor:pointer} button.secondary{background:#6b7280} button:disabled{opacity:.5;cursor:not-allowed} input[type=text]{border:1px solid #d1d5db;border-radius:6px;padding:8px 10px;min-width:220px} .row{display:flex;gap:8px;align-items:center;flex-wrap:wrap} .error{background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;padding:12px;border-radius:8px} iframe{width:100%;height:1120px;border:0;background:#f3f4f6}</style></head><body><div class="wrap">';
    echo '<h1>Panel Certyfikatów Koordynatora (Safe View)</h1>';
    echo '<div id="msg" class="muted">Ładuję…</div>';
    echo '<div class="card">';
    echo '<div class="row" style="margin-bottom:8px">';
    echo '<button onclick="issueKR()">Wydaj KR</button>';
    echo '<button class="secondary" onclick="refreshList()">Odśwież</button>';
    echo '</div>';
    echo '<div class="row" style="margin-bottom:8px">';
    echo '<input id="extId" type="text" placeholder="ID z systemu (np. 00000011005)" />';
    echo '<button onclick="issueMR()" ' . ($admin==='true' ? '' : 'disabled title="Tylko administrator"') . '>Wydaj MR</button>';
    echo '<button onclick="issueRT()" ' . ($admin==='true' ? '' : 'disabled title="Tylko administrator"') . '>Wydaj RT</button>';
    echo '</div>';
    echo '<div id="err" class="error" style="display:none"></div>';
    echo '<h2>Twoje certyfikaty</h2><div id="list" class="list"></div>';
    echo '</div>';
    echo '<div class="card"><h2>Podgląd</h2><iframe id="certFrame" title="Certyfikat"></iframe></div>';
    echo "<script>
      const BASE = '{$rest}'.replace(/\\/,'');
      function rest(p, o={}){ return fetch(BASE+p, Object.assign({credentials:'include'}, o)); }
      const msg = document.getElementById('msg');
      const err = document.getElementById('err');
      function showError(e, status){ err.style.display='block'; err.textContent = 'Błąd: ' + (status?('HTTP '+status+': '):'') + (e?.message||e||'nieznany'); }
      function clearError(){ err.style.display='none'; err.textContent=''; }
      async function fetchMy(){ clearError(); msg.textContent='Ładuję…';
        try { const r = await rest('/certificate/my'); if(!r.ok){ let t=await r.text(); throw new Error(t||('HTTP '+r.status)); }
          const j = await r.json(); msg.textContent='Gotowe.'; return j; }
        catch(e){ showError(e); msg.textContent='Błąd ładowania'; return {items:[]}; }
      }
      function renderList(data){ const list=document.getElementById('list'); list.innerHTML=''; const items=(data&&data.items)||[]; if(!items.length){ list.innerHTML='<div class=\"muted\">Brak certyfikatów.</div>'; return; } for(const it of items){ const el=document.createElement('div'); el.className='pill'; el.textContent=`${it.role} • ${it.cert_no} • ${it.status}`; el.onclick=()=>loadCert(it); list.appendChild(el); } loadCert(items[0]); }
      function loadCert(rec){ const f=document.getElementById('certFrame'); const data={ role:rec.role, personName:'', userId:'', issuedAt:(rec.issued_at||'').slice(0,10), validUntil:rec.valid_until||'bezterminowo', certNo:rec.cert_no, verifyBase: location.origin + '/wp-json/kk/v1/certificate/verify?cert_no=' }; f.onload=()=>f.contentWindow.postMessage({type:'CERT_DATA', payload:data}, '*'); f.src='/wp-content/plugins/kk-lite/certificates/certificate.html'; }
      async function issueKR(){ clearError(); try{ const r=await rest('/certificate/issue', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ role:'KR' })}); const j=await r.json().catch(()=>({})); if(!r.ok||!j.ok){ throw new Error(j?.message||('HTTP '+r.status)); } await refreshList(); }catch(e){ showError(e); }}
      async function issueById(role){ clearError(); const ext=document.getElementById('extId').value.trim(); if(!ext){ showError('Wpisz ID'); return; } try{ const r=await rest('/certificate/issue', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ role, external_id: ext })}); const j=await r.json().catch(()=>({})); if(!r.ok||!j.ok){ throw new Error(j?.message||('HTTP '+r.status)); } alert('Wydano certyfikat '+role+' dla ID '+ext); await refreshList(); }catch(e){ showError(e); }}
      function issueMR(){ issueById('MR'); }
      function issueRT(){ issueById('RT'); }
      async function refreshList(){ const d=await fetchMy(); renderList(d); }
      document.addEventListener('DOMContentLoaded', refreshList);
    </script>";
    echo '</div></body></html>'; exit;
  }
  // /kk/weryfikacja/
  echo '<!doctype html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Weryfikacja certyfikatu</title><style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:24px;color:#111;background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);min-height:100vh} .box{max-width:640px;margin:10vh auto;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.15);padding:24px} .row{display:flex;gap:8px} input[type=text]{flex:1;border:1px solid #d1d5db;border-radius:8px;padding:10px 12px} button{background:#0b5cab;color:#fff;border:none;border-radius:8px;padding:10px 14px;cursor:pointer} .ok{color:#065f46;background:#ecfdf5;border:1px solid #34d399;padding:12px;border-radius:8px;margin-top:12px} .fail{color:#7f1d1d;background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px;margin-top:12px} .muted{color:#6b7280}</style></head><body>';
  echo '<div class="box">';
  echo '<h1>Weryfikacja Certyfikatu</h1>';
  echo '<div class="row"><input id="nr" type="text" placeholder="KR-00000011005 lub 00000011005"><button onclick="go()">Weryfikuj</button></div>';
  echo '<div id="out" class="muted" style="margin-top:12px">Podaj numer certyfikatu lub sam ID.</div>';
  $rest = esc_url_raw( rest_url('kk/v1') );
  echo "<script>
    const BASE = '{$rest}'.replace(/\\/,'');
    function qs(n){return new URLSearchParams(location.search).get(n)}
    async function verifyOne(no){ const r=await fetch(BASE+'/certificate/verify?cert_no='+encodeURIComponent(no)); if(!r.ok){ return {error:true,status:r.status,text: await r.text().catch(()=>'' )}; } try{return await r.json();}catch(e){return {error:true,status:r.status};} }
    function isRawID(v){ return v && !/-/.test(v); }
    async function go(){ const v=document.getElementById('nr').value.trim(); const out=document.getElementById('out'); out.textContent='Sprawdzam…'; if(!v){ out.textContent='Wpisz numer'; return; } if(isRawID(v)){ // pokaż wszystkie role dla ID
      const roles=['KR','MR','RT']; const results=[]; for(const r of roles){ const j=await verifyOne(r+'-'+v); if(j && j.found) results.push(j.data); }
      if(!results.length){ out.className='fail'; out.textContent='Nie znaleziono'; return; }
      out.className='ok'; out.innerHTML = results.map(x=>('<div><strong>'+x.role+':</strong> '+x.cert_no+' • '+x.status+' • '+(x.issued_at||'')+'</div>')).join(''); return; }
      const j = await verifyOne(v); if(j && j.found){ out.className='ok'; const x=j.data; out.innerHTML='<strong>Status:</strong> '+x.status+'<br><strong>Numer:</strong> '+x.cert_no+'<br><strong>Właściciel:</strong> '+x.owner+'<br><strong>Rola:</strong> '+x.role+'<br><strong>Wystawiono:</strong> '+x.issued_at; } else { out.className='fail'; out.textContent='Nie znaleziono'; }
    }
    document.addEventListener('DOMContentLoaded', ()=>{ const q=qs('cert_no'); if(q){ document.getElementById('nr').value=q; go(); }});
  </script>";
  echo '</div></body></html>'; exit;
}, 0);