(function () {
  const cfg = window.KURS_CONFIG;
  const elModulesNav = document.getElementById('modulesNav');
  const elToc = document.getElementById('toc');
  const elContent = document.getElementById('contentBody');
  const elTitle = document.getElementById('sectionTitle');
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');
  const btnPrint = document.getElementById('btnPrint');
  const btnTheme = document.getElementById('btnTheme');
  const elPct = document.getElementById('progressPct');
  const elFill = document.getElementById('progressFill');

  const ttsPlay = document.getElementById('ttsPlay');
  const ttsPause = document.getElementById('ttsPause');
  const ttsStop = document.getElementById('ttsStop');

  // Theme (default light)
  const saved = localStorage.getItem('kurs_theme');
  if (saved === 'dark') document.documentElement.classList.add('dark');
  btnTheme.addEventListener('click', () => {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('kurs_theme', isDark ? 'dark' : 'light');
  });

  // State
  let currentModule = cfg.modules.find(m => m.id === (location.hash.split('?')[0].slice(1) || cfg.defaultModuleId)) || cfg.modules[0];
  let sections = []; // [{title, html}]
  let step = 0;

  // TTS
  function speak(text) {
    try {
      window.speechSynthesis.cancel();
      if (!text) return;
      const u = new SpeechSynthesisUtterance(text);
      const voices = window.speechSynthesis.getVoices() || [];
      const pl = voices.find(v => /pl-PL|Polish/i.test(v.lang));
      if (pl) u.voice = pl;
      u.rate = 1.0;
      window.speechSynthesis.speak(u);
    } catch {}
  }
  function pause() {
    try { if (speechSynthesis.speaking && !speechSynthesis.paused) speechSynthesis.pause(); } catch {}
  }
  function stop() {
    try { speechSynthesis.cancel(); } catch {}
  }

  // Render modules list (active module + its sections)
  function renderModulesNav() {
    elModulesNav.innerHTML = '';
    cfg.modules.forEach(m => {
      const title = document.createElement('div');
      title.className = 'module-title';
      title.textContent = m.title;
      title.style.cursor = 'pointer';
      title.addEventListener('click', () => selectModule(m.id));
      elModulesNav.appendChild(title);

      if (m.id === currentModule.id) {
        sections.forEach((s, idx) => {
          const a = document.createElement('a');
          a.href = `#${m.id}?step=${idx}`;
          a.className = 'section' + (idx === step ? ' active' : '');
          a.textContent = s.title || `Sekcja ${idx + 1}`;
          a.addEventListener('click', (e) => { e.preventDefault(); goTo(idx); });
          elModulesNav.appendChild(a);
        });
      }
    });
  }

  // Build TOC from current content
  function buildToc() {
    elToc.innerHTML = '';
    const heads = elContent.querySelectorAll('h2, h3, h4');
    heads.forEach(h => {
      if (!h.id) {
        h.id = h.textContent.trim().toLowerCase()
          .replace(/\s+/g, '-')
          .replace(/[^
w\-ąćęłńóśżź]/gi, '');
      }
      const a = document.createElement('a');
      a.href = `#${h.id}`;
      a.textContent = h.textContent;
      a.className = `toc-${h.tagName.toLowerCase()}`;
      a.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById(h.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      elToc.appendChild(a);
    });
  }

  // Split Markdown HTML into H2 sections
  function splitIntoSections(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const out = [];
    let buf = document.createElement('div');
    let currentTitle = 'Wprowadzenie';

    Array.from(tmp.childNodes).forEach(node => {
      if (node.nodeType === 1 && node.tagName === 'H2') {
        if (buf.childNodes.length) out.push({ title: currentTitle, html: buf.innerHTML });
        currentTitle = node.textContent.trim();
        buf = document.createElement('div');
      } else {
        buf.appendChild(node.cloneNode(true));
      }
    });
    if (buf.childNodes.length) out.push({ title: currentTitle, html: buf.innerHTML });
    return out.length ? out : [{ title: currentTitle, html }];
  }

  async function loadModule(mod) {
    const res = await fetch(mod.localPath, { cache: 'no-cache' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const md = await res.text();
    if (typeof marked === 'undefined') throw new Error('Parser Markdown nie wczytany');
    const html = marked.parse(md);
    sections = splitIntoSections(html);
  }

  function updateProgress() {
    const pct = sections.length ? Math.round(((step + 1) / sections.length) * 100) : 0;
    elPct.textContent = `${pct}%`;
    elFill.style.width = `${pct}%`;
  }

  function renderStep() {
    const s = sections[step] || { title: 'Sekcja', html: '<p>Brak treści</p>' };
    elTitle.textContent = s.title;
    elContent.innerHTML = s.html;
    buildToc();
    updateProgress();
    renderModulesNav();
    history.replaceState(null, '', `#${currentModule.id}?step=${step}`);
    document.title = `${s.title} — ${cfg.siteTitle}`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function goTo(i) {
    if (i < 0 || i >= sections.length) return;
    step = i;
    renderStep();
  }

  async function selectModule(id) {
    stop();
    const mod = cfg.modules.find(m => m.id === id) || cfg.modules[0];
    if (!mod) return;
    currentModule = mod;
    step = 0;
    elContent.innerHTML = '<p>Ładowanie modułu…</p>';
    elToc.innerHTML = '';
    await loadModule(mod);
    renderStep();
  }

  // Controls
  btnPrev.addEventListener('click', () => goTo(step - 1));
  btnNext.addEventListener('click', () => goTo(step + 1));
  btnPrint.addEventListener('click', () => window.print());
  ttsPlay.addEventListener('click', () => speak(elContent.innerText || '')); 
  ttsPause.addEventListener('click', pause);
  ttsStop.addEventListener('click', stop);

  // Init
  (async () => {
    const hash = location.hash.slice(1);
    const [id, qs] = hash.split('?');
    const wanted = cfg.modules.find(m => m.id === id);
    if (wanted) currentModule = wanted;
    try {
      await loadModule(currentModule);
    } catch (e) {
      elTitle.textContent = 'Błąd ładowania';
      elContent.innerHTML = `<p>Nie udało się wczytać treści modułu: <code>${currentModule.localPath}</code></p>`;
      console.error(e);
      return;
    }
    const params = new URLSearchParams(qs || '');
    const s = parseInt(params.get('step') || '0', 10);
    step = Number.isFinite(s) && s >= 0 ? Math.min(s, sections.length - 1) : 0;
    renderModulesNav();
    renderStep();
  })();
})();