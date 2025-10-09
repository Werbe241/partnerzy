(function(){
  const content = document.getElementById('content');
  const progressBar = document.getElementById('overall-progress');
  const progressText = document.getElementById('progress-text');
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggle-sidebar');

  // Struktura stron
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

  // Sidebar toggle (mobile)
  toggleBtn && toggleBtn.addEventListener('click', ()=>{
    sidebar.classList.toggle('open');
  });

  // Obsługa kliknięć w nawigacji
  document.addEventListener('click', (e)=>{
    const a = e.target.closest('a[data-module][data-page]');
    if(!a) return;
    e.preventDefault();
    const mod = a.getAttribute('data-module');
    const pg = a.getAttribute('data-page');
    loadPage(mod, pg);
  });

  // Skróty klawiaturowe
  document.addEventListener('keydown', (e)=>{
    if(e.key===' '){ e.preventDefault(); togglePlay(); }
    if(e.key==='n' || e.key==='N') nextPage();
    if(e.key==='p' || e.key==='P') prevPage();
  });

  // Kontrolki intro
  content.addEventListener('click', (e)=>{
    const b = e.target.closest('[data-action]');
    if(!b) return;
    const action = b.getAttribute('data-action');
    if(action==='play') playCurrent();
    if(action==='pause') pausePlayback();
    if(action==='stop') stopPlayback();
    if(action==='prev') prevPage();
    if(action==='next') nextPage();
  });

  function pageUrl(mod, pg){
    if(pg==='end') return `./modules/${mod}/end.html`;
    return `./modules/${mod}/${pg}.html`;
  }

  async function loadPage(mod, pg){
    // zatrzymaj audio/TTS
    stopPlayback(true);

    // wczytaj HTML częściowy
    const url = pageUrl(mod, pg);
    const html = await fetch(url, {cache:'no-store'}).then(r=>r.text()).catch(()=>'<article><h2>Nie udało się wczytać strony</h2></article>');
    content.innerHTML = html + controlsHTML();

    // oznacz aktywne w menu
    document.querySelectorAll('.module-group a').forEach(a=>a.classList.remove('active'));
    const active = document.querySelector(`.module-group a[data-module="${mod}"][data-page="${pg}"]`);
    active && active.classList.add('active');

    // zapisz stan i postęp
    state.module = mod; state.page = pg;
    markVisited(mod, pg);
    saveState();
    updateProgress();

    // Auto-scroll do góry
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function controlsHTML(){
    return `\n<div class="controls" aria-label="Sterowanie odtwarzaniem">\n  <button class="btn primary" data-action="play">Odsłuchaj</button>\n  <button class="btn" data-action="pause">Pauza</button>\n  <button class="btn" data-action="stop">Stop</button>\n  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">\n    <button class="btn" data-action="prev">Wstecz</button>\n    <button class="btn primary" data-action="next">Dalej</button>\n  </div>\n</div>`;
  }

  function markVisited(mod, pg){
    if(pg==='end') return; // ekrany end nie liczą się do postępu
    const key = `${mod}-${pg}`;
    state.visited[key] = true;
  }

  function updateProgress(){
    const visitedCount = Object.keys(state.visited).filter(k=>k.startsWith('01-')||k.startsWith('02-')).length;
    const pct = Math.max(0, Math.min(100, Math.round((visitedCount / TOTAL_READABLE)*100)));
    progressBar && (progressBar.style.width = pct + '%');
    progressText && (progressText.textContent = `Postęp: ${pct}%`);
  }

  function saveState(){ localStorage.setItem('kk_course_state', JSON.stringify(state)); }
  function loadState(){ try{ return JSON.parse(localStorage.getItem('kk_course_state')); }catch(e){ return null; } }

  // Audio: MP3 jeśli istnieje, inaczej TTS
  async function playCurrent(){
    // jeśli jest audioEl i pauza -> wznowienie
    if(audioEl && audioEl.paused){ audioEl.play(); return; }

    // spróbuj MP3
    const mp3 = `./audio/${state.module}-${state.page}.mp3`;
    const ok = await hasAudio(mp3);
    if(ok){
      stopPlayback(true);
      audioEl = new Audio(mp3);
      audioEl.play().catch(()=>{});
      return;
    }

    // TTS fallback
    speakText(extractText());
  }

  function pausePlayback(){
    if(audioEl && !audioEl.paused){ audioEl.pause(); return; }
    if(window.speechSynthesis && speaking) window.speechSynthesis.pause();
  }

  function stopPlayback(silent){
    if(audioEl){ try{ audioEl.pause(); audioEl.currentTime=0; }catch(_){} audioEl=null; }
    if(window.speechSynthesis){ try{ window.speechSynthesis.cancel(); }catch(_){} }
    speaking=false;
    if(!silent){ /* no-op */ }
  }

  function togglePlay(){
    if(audioEl){ if(audioEl.paused) audioEl.play(); else audioEl.pause(); return; }
    if(window.speechSynthesis){ if(speaking) window.speechSynthesis.pause(); else speakText(extractText()); }
  }

  function extractText(){
    const clone = content.cloneNode(true);
    // usuń kontrolki
    clone.querySelectorAll('.controls').forEach(n=>n.remove());
    return (clone.textContent||'').replace(/\s+/g,' ').trim();
  }

  function speakText(text){
    if(!('speechSynthesis' in window)){
      alert('Odtwarzanie lektora nie jest wspierane w tej przeglądarce.');
      return;
    }
    stopPlayback(true);
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'pl-PL';
    u.rate = 1.0; u.pitch = 1.0; u.volume = 1.0;
    speaking = true;
    u.onend = ()=>{ speaking=false; };
    try { window.speechSynthesis.speak(u); } catch(_) { speaking=false; }
  }

  async function hasAudio(url){
    try {
      const res = await fetch(url, { method: 'HEAD', cache:'no-store' });
      return res.ok;
    } catch { return false; }
  }

  function nextPage(){
    const list = PAGES[state.module] || [];
    let idx = list.indexOf(state.page);
    if(idx<0) idx=0;
    if(idx < list.length-1){
      loadPage(state.module, list[idx+1]);
    } else {
      // jeśli koniec modułu 1 → pokaż end lub przejdź do kolejnego modułu
      if(state.module==='01') loadPage('01','end');
      else if(state.module==='02') loadPage('02','end');
      else if(state.module==='TEST') {/* nic */}
    }
  }

  function prevPage(){
    const list = PAGES[state.module] || [];
    let idx = list.indexOf(state.page);
    if(idx>0) loadPage(state.module, list[idx-1]);
  }

  // Start: wznów ostatni ekran albo pokaż intro
  if(state && state.module && state.page){ loadPage(state.module, state.page); }
})();