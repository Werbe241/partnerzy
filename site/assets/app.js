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
    const usedIds = new Set();
    heads.forEach(h => {
      if (!h.id) {
        let baseId = h.textContent.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^\w\-ąćęłńóśżź]/gi, '');
        let id = baseId;
        let counter = 1;
        while (usedIds.has(id)) {
          id = `${baseId}-${counter}`;
          counter++;
        }
        h.id = id;
        usedIds.add(id);
      } else {
        usedIds.add(h.id);
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
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'loading';
    loadingDiv.textContent = `Ładowanie: ${mod.title}…`;
    elContent.innerHTML = '';
    elContent.appendChild(loadingDiv);
    elToc.innerHTML = '';
    try {
      const md = await fetchMarkdown(mod);
      renderMarkdown(md);
      history.replaceState(null, '', `#${mod.id}`);
      document.title = `${mod.title} — ${cfg.title}`;
    } catch (e) {
      console.error(e);
      const errorDiv = document.createElement('div');
      errorDiv.className = 'loading';
      errorDiv.textContent = '⚠️ Nie udało się wczytać modułu z pliku lokalnego. Sprawdź ścieżkę: ';
      const code = document.createElement('code');
      code.textContent = mod.localPath;
      errorDiv.appendChild(code);
      elContent.innerHTML = '';
      elContent.appendChild(errorDiv);
    }
  }

  btnPrint.addEventListener('click', () => window.print());

  renderModuleList();
  const hash = location.hash?.slice(1);
  const startId = (hash && cfg.modules.find(m => m.id === hash)) ? hash : (cfg.defaultModuleId || cfg.modules[0]?.id);
  selectModule(startId);
})();
