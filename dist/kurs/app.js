(function(){
  const content = document.getElementById('content');
  const progressBar = document.getElementById('overall-progress');
  const progressText = document.getElementById('progress-text');
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggle-sidebar');

  // Limity odsłuchań lekcji (3 razy na lekcję)
  const MAX_PLAYS_PER_PAGE = 3;
  function getPlaysMap(){ try{return JSON.parse(localStorage.getItem('kk_page_plays')||'{}');}catch{return{}} }
  function setPlaysMap(m){ localStorage.setItem('kk_page_plays', JSON.stringify(m)); }
  function playsLeft(mod, pg){ const m=getPlaysMap(); const k=`${mod}-${pg}`; const used=m[k]||0; return Math.max(0, MAX_PLAYS_PER_PAGE - used); }
  function registerPlay(mod, pg){ const m=getPlaysMap(); const k=`${mod}-${pg}`; const used=m[k]||0; if(used>=MAX_PLAYS_PER_PAGE) return false; m[k]=used+1; setPlaysMap(m); return true; }
  function toast(msg, ms=2500){ let t=document.createElement('div'); t.style.cssText='position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#111a;backdrop-filter:blur(6px);color:#fff;padding:10px 14px;border-radius:10px;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.35);max-width:90%;'; t.textContent=msg; document.body.appendChild(t); setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .3s'; }, ms-300); setTimeout(()=> t.remove(), ms); }

  // Struktura stron (aktualny zakres)
  const PAGES = {
    '01': ['01','02','03','04','05','06','07','08','09','10','end'],
    '02': ['01','02','03','04','05','end'],
    'TEST': ['index']
  };

  const TOTAL_READABLE = PAGES['01'].length - 1 + PAGES['02'].length - 1; // bez end

  // Stan
  let state = loadState() || { module: '01', page: '01', visited: {} };
  let audioEl = null; // MP3 fallback
  let speaking = false; // TTS
  let playbackCountedForThisRun = false; // by nie liczyć wielokrotnie jednego odsłuchu

  // Sidebar toggle (mobile)
  toggleBtn && toggleBtn.addEventListener('click', ()=>{ sidebar.classList.toggle('open'); });

  // Klik w nawigacji: pokaż pozostałe odsłuchania
  document.addEventListener('click', (e)=>{
    const a = e.target.closest('a[data-module][data-page]');
    if(!a) return;
    const mod = a.getAttribute('data-module');
    const pg = a.getAttribute('data-page');
    const left = playsLeft(mod, pg);
    toast(`Pozostałe odsłuchania tej lekcji: ${left} z ${MAX_PLAYS_PER_PAGE}`);
    e.preventDefault();
    loadPage(mod, pg);
  });

  // Skróty klawiaturowe
  document.addEventListener('keydown', (e)=>{
    if(e.key===' '){ e.preventDefault(); togglePlay(); }
    if(e.key==='n' || e.key==='N') nextPage();
    if(e.key==='p' || e.key==='P') prevPage();
  });

  // Kontrolki
  content.addEventListener('click', (e)=>{
    const b = e.target.closest('[data-action]');
    if(!b) return;
    const action = b.getAttribute('data-action');
    if(action==='play') playCurrent();
    if(action==='pause'){
      if(state.module==='TEST' && window.TEST_TIMER_API && window.TEST_TIMER_API.pause10){ window.TEST_TIMER_API.pause10(); return; }
      pausePlayback();
    }
    if(action==='stop'){
      if(state.module==='TEST' && window.TEST_TIMER_API && window.TEST_TIMER_API.stopAttempt){ window.TEST_TIMER_API.stopAttempt(); return; }
      stopPlayback();
    }
    if(action==='prev') prevPage();
    if(action==='next') nextPage();
  });

  function pageUrl(mod, pg){ if(pg==='end') return `./modules/${mod}/end.html`; return `./modules/${mod}/${pg}.html`; }

  async function loadPage(mod, pg){
    stopPlayback(true);
    const url = pageUrl(mod, pg);
    const html = await fetch(url, {cache:'no-store'}).then(r=>r.text()).catch(()=>'<article><h2>Nie udało się wczytać strony</h2></article>');
    content.innerHTML = html + controlsHTML();

    document.querySelectorAll('.module-group a').forEach(a=>a.classList.remove('active'));
    const active = document.querySelector(`.module-group a[data-module="${mod}"][data-page="${pg}"]`);
    active && active.classList.add('active');

    state.module = mod; state.page = pg;
    markVisited(mod, pg);
    saveState();
    updateProgress();

    // Zablokuj „Odsłuchaj” jeśli limit wykorzystany
    const left = playsLeft(mod, pg);
    if(left<=0){ const btn = content.querySelector('[data-action="play"]'); if(btn){ btn.disabled=true; btn.title='Limit odsłuchań tej lekcji został wyczerpany'; } }

    window.scrollTo({top:0, behavior:'smooth'});
  }

  function controlsHTML(){
    return `\n<div class="controls" aria-label="Sterowanie odtwarzaniem">\n  <button class="btn primary" data-action="play">Odsłuchaj</button>\n  <button class="btn" data-action="pause">Pauza</button>\n  <button class="btn" data-action="stop">Stop</button>\n  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">\n    <button class="btn" data-action="prev">Wstecz</button>\n    <button class="btn primary" data-action="next">Dalej</button>\n  </div>\n</div>`;
  }

  function markVisited(mod, pg){ if(pg==='end') return; state.visited[`${mod}-${pg}`] = true; }

  function updateProgress(){ const visitedCount = Object.keys(state.visited).filter(k=>k.startsWith('01-')||k.startsWith('02-')).length; const pct = Math.max(0, Math.min(100, Math.round((visitedCount / TOTAL_READABLE)*100))); progressBar && (progressBar.style.width = pct + '%'); progressText && (progressText.textContent = `Postęp: ${pct}%`); }

  function saveState(){ localStorage.setItem('kk_course_state', JSON.stringify(state)); }
  function loadState(){ try{ return JSON.parse(localStorage.getItem('kk_course_state')); }catch(e){ return null; } }

  // Audio: MP3 jeśli istnieje, inaczej TTS
  async function playCurrent(){
    // Limit odsłuchań (poza testem i end)
    if(state.module!=='TEST' && state.page!=='end'){
      if(!playbackCountedForThisRun){
        const ok = registerPlay(state.module, state.page);
        if(!ok){ toast('Limit odsłuchań tej lekcji został wyczerpany.'); const btn=content.querySelector('[data-action="play"]'); if(btn) btn.disabled=true; return; }
        playbackCountedForThisRun = true; const left = playsLeft(state.module, state.page); toast(`Rozpoczęto odsłuch. Pozostało: ${left} z ${MAX_PLAYS_PER_PAGE}`);
      }
    }

    if(audioEl && audioEl.paused){ audioEl.play(); return; }

    const mp3 = `./audio/${state.module}-${state.page}.mp3`;
    const ok = await hasAudio(mp3);
    if(ok){ stopPlayback(true); audioEl = new Audio(mp3); audioEl.onended = ()=>{ playbackCountedForThisRun = false; }; audioEl.play().catch(()=>{}); return; }

    speakText(extractText(), ()=>{ playbackCountedForThisRun=false; });
  }

  function pausePlayback(){ if(audioEl && !audioEl.paused){ audioEl.pause(); return; } if(window.speechSynthesis && speaking) window.speechSynthesis.pause(); }
  function stopPlayback(silent){ if(audioEl){ try{ audioEl.pause(); audioEl.currentTime=0; }catch(_){} audioEl=null; } if(window.speechSynthesis){ try{ window.speechSynthesis.cancel(); }catch(_){} } speaking=false; playbackCountedForThisRun=false; if(!silent){} }

  function togglePlay(){ if(audioEl){ if(audioEl.paused) audioEl.play(); else audioEl.pause(); return; } if(window.speechSynthesis){ if(speaking) window.speechSynthesis.pause(); else speakText(extractText(), ()=>{ playbackCountedForThisRun=false; }); } }

  function extractText(){ const clone = content.cloneNode(true); clone.querySelectorAll('.controls').forEach(n=>n.remove()); return (clone.textContent||'').replace(/\s+/g,' ').trim(); }
  function speakText(text, onend){ if(!('speechSynthesis' in window)){ alert('Odtwarzanie lektora nie jest wspierane w tej przeglądarce.'); return; } stopPlayback(true); const u=new SpeechSynthesisUtterance(text); u.lang='pl-PL'; u.rate=1.0; u.pitch=1.0; u.volume=1.0; speaking=true; u.onend=()=>{ speaking=false; onend && onend(); }; try{ window.speechSynthesis.speak(u); }catch(_){ speaking=false; onend && onend(); } }
  async function hasAudio(url){ try{ const res=await fetch(url,{method:'HEAD',cache:'no-store'}); return res.ok; }catch{ return false; } }

  function nextPage(){ const list=PAGES[state.module]||[]; let idx=list.indexOf(state.page); if(idx<0) idx=0; if(idx<list.length-1){ loadPage(state.module, list[idx+1]); } else { if(state.module==='01') loadPage('01','end'); else if(state.module==='02') loadPage('02','end'); else if(state.module==='TEST'){} } }
  function prevPage(){ const list=PAGES[state.module]||[]; let idx=list.indexOf(state.page); if(idx>0) loadPage(state.module, list[idx-1]); }

  if(state && state.module && state.page){ loadPage(state.module, state.page); }
})();