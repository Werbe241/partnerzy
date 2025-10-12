(function () {
  const cfg = window.KURS_CONFIG;
  const elList = document.getElementById('moduleList');
  const elContent = document.getElementById('content');
  const elToc = document.getElementById('toc');
  const btnPrint = document.getElementById('btnPrint');
  const btnTheme = document.getElementById('btnTheme');

  const savedTheme = localStorage.getItem('kurs_theme');
  if (savedTheme === 'light') document.documentElement.classList.add('light');
  btnTheme.addEventListener('click', () => {
    document.documentElement.classList.toggle('light');
    localStorage.setItem('kurs_theme', document.documentElement.classList.contains('light') ? 'light' : 'dark');
  });

  function renderModuleList() {
    elList.innerHTML = '';
    cfg.modules.forEach(m => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = `#${m.id}`;
      a.textContent = m.title;
      a.addEventListener('click', (e) => {
        e.preventDefault();
        selectModule(m.id);
      });
      li.appendChild(a);
      elList.appendChild(li);
    });
  }

  function highlightActive(id) {
    const links = elList.querySelectorAll('a');
    links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === `#${id}`));
  }

  async function fetchMarkdown(mod) {
    const url = mod.localPath;
    const res = await fetch(url, { cache: 'no-cache' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.text();
  }

  function buildToc(container) {
    elToc.innerHTML = '';
    const heads = container.querySelectorAll('h2, h3, h4');
    heads.forEach(h => {
      if (!h.id) {
        h.id = h.textContent.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^\w\-ąćęłńóśżź]/gi, '');
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

  function renderMarkdown(mdText) {
    marked.setOptions({ breaks: false, gfm: true });
    const html = marked.parse(mdText);
    elContent.innerHTML = html;
    buildToc(elContent);
    elContent.querySelectorAll('a[href^="http"]').forEach(a => { a.target = '_blank'; a.rel = 'noopener'; });
  }

  async function selectModule(id) {
    const mod = cfg.modules.find(m => m.id === id) || cfg.modules[0];
    if (!mod) return;
    highlightActive(mod.id);
    elContent.innerHTML = `<div class="loading">Ładowanie: ${mod.title}…</div>`;
    elToc.innerHTML = '';
    try {
      const md = await fetchMarkdown(mod);
      renderMarkdown(md);
      history.replaceState(null, '', `#${mod.id}`);
      document.title = `${mod.title} — ${cfg.title}`;
    } catch (e) {
      console.error(e);
      elContent.innerHTML = `<div class="loading">⚠️ Nie udało się wczytać modułu z pliku lokalnego. Sprawdź ścieżkę: <code>${mod.localPath}</code></div>`;
    }
  }

  btnPrint.addEventListener('click', () => window.print());

  renderModuleList();
  const startId = location.hash?.slice(1) || cfg.defaultModuleId || cfg.modules[0]?.id;
  selectModule(startId);
})();
