(function () {
  const cfg = window.KURS_CONFIG;
  const elList = document.getElementById('moduleList');
  const elContent = document.getElementById('content');
  const elToc = document.getElementById('toc');
  const btnPrint = document.getElementById('btnPrint');
  const btnTheme = document.getElementById('btnTheme');

  // TTS global
  const voiceSelect = document.getElementById('voiceSelect');
  const rateInput = document.getElementById('rate');
  const btnPlayAll = document.getElementById('btnPlayAll');
  const btnPause = document.getElementById('btnPause');
  const btnResume = document.getElementById('btnResume');
  const btnStop = document.getElementById('btnStop');

  let voices = [];
  let currentUtterance = null;

  function loadVoices() {
    voices = window.speechSynthesis.getVoices() || [];
    voiceSelect.innerHTML = '';
    const preferred = voices.filter(v => /Polish|pl-PL/i.test(v.lang));
    const list = preferred.length ? preferred : voices;
    list.forEach(v => {
      const opt = document.createElement('option');
      opt.value = v.name;
      opt.textContent = `${v.name} (${v.lang})`;
      voiceSelect.appendChild(opt);
    });
  }
  loadVoices();
  if (typeof window.speechSynthesis !== 'undefined') {
    window.speechSynthesis.onvoiceschanged = loadVoices;
  }

  function ttsStop() {
    if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
      window.speechSynthesis.cancel();
    }
    currentUtterance = null;
  }
  function ttsPause() {
    if (window.speechSynthesis.speaking && !window.speechSynthesis.paused) {
      window.speechSynthesis.pause();
    }
  }
  function ttsResume() {
    if (window.speechSynthesis.paused) {
      window.speechSynthesis.resume();
    }
  }
  function ttsSpeak(text) {
    ttsStop();
    if (!text || !text.trim()) return;
    const u = new SpeechSynthesisUtterance(text);
    const chosen = voices.find(v => v.name === voiceSelect.value);
    if (chosen) u.voice = chosen;
    const rate = parseFloat(rateInput.value || '1.0');
    u.rate = Math.max(0.1, Math.min(2.0, rate));
    currentUtterance = u;
    window.speechSynthesis.speak(u);
  }

  // Motyw: domyślnie jasny
  const savedTheme = localStorage.getItem('kurs_theme');
  if (savedTheme === 'dark') document.documentElement.classList.add('dark');
  btnTheme.addEventListener('click', () => {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('kurs_theme', isDark ? 'dark' : 'light');
  });

  // Moduły
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

  // Pobieranie Markdown
  async function fetchMarkdown(mod) {
    const url = mod.localPath;
    const res = await fetch(url, { cache: 'no-cache' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.text();
  }

  // Podział treści: każdy H2 => osobna karta
  function groupIntoSectionCards(container) {
    const nodes = Array.from(container.childNodes);
    const grouped = document.createDocumentFragment();

    let currentCard = null;
    function makeCard() {
      const card = document.createElement('section');
      card.className = 'section-card markdown-body';
      return card;
    }

    nodes.forEach(node => {
      if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'H2') {
        currentCard = makeCard();
        grouped.appendChild(currentCard);
        currentCard.appendChild(node);

        // Akcje sekcji (odtwarzanie tej sekcji)
        const actions = document.createElement('div');
        actions.className = 'section-actions';
        const bPlay = document.createElement('button');
        bPlay.textContent = '▶ Odtwórz sekcję';
        bPlay.addEventListener('click', () => {
          const text = currentCard.innerText || '';
          ttsSpeak(text);
        });
        const bPause = document.createElement('button');
        bPause.textContent = '⏸ Pauza';
        bPause.addEventListener('click', ttsPause);
        const bResume = document.createElement('button');
        bResume.textContent = '⏵ Wznów';
        bResume.addEventListener('click', ttsResume);
        const bStop = document.createElement('button');
        bStop.textContent = '⏹ Stop';
        bStop.addEventListener('click', ttsStop);

        actions.appendChild(bPlay);
        actions.appendChild(bPause);
        actions.appendChild(bResume);
        actions.appendChild(bStop);
        currentCard.appendChild(actions);

      } else {
        if (!currentCard) {
          currentCard = makeCard();
          grouped.appendChild(currentCard);
        }
        currentCard.appendChild(node);
      }
    });

    container.innerHTML = '';
    container.appendChild(grouped);
  }

  // Spis treści
  function buildToc(container) {
    elToc.innerHTML = '';
    const heads = container.querySelectorAll('h2, h3, h4');
    heads.forEach(h => {
      if (!h.id) {
        h.id = h.textContent.trim().toLowerCase()
          .replace(/\s+/g, '-')
          .replace(/[^
\w\-ąćęłńóśżź]/gi, '');
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

  function renderMarkdown(mdText, modTitle) {
    if (typeof marked === 'undefined') {
      elContent.innerHTML = '<div class="loading">⚠️ Błąd: parser Markdown nie został załadowany.</div>';
      return;
    }
    marked.setOptions({ breaks: false, gfm: true });
    const html = marked.parse(mdText);
    elContent.innerHTML = `<section class="section-card markdown-body"><h2>${modTitle}</h2>${html}</section>`;
    // Podziel po wstawieniu — tak, by pierwszy H2 modułu też tworzył kartę
    const tmp = document.createElement('div');
    tmp.innerHTML = elContent.querySelector('.section-card').innerHTML;
    elContent.innerHTML = tmp.innerHTML;

    groupIntoSectionCards(elContent);
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
      renderMarkdown(md, mod.title);
      history.replaceState(null, '', `#${mod.id}`);
      document.title = `${mod.title} — ${cfg.title}`;
    } catch (e) {
      console.error(e);
      elContent.innerHTML = `<div class="loading">⚠️ Nie udało się wczytać modułu. Sprawdź plik: <code>${mod.localPath}</code></div>`;
    }
  }

  // Akcje globalne TTS
  btnPlayAll.addEventListener('click', () => {
    const text = (elContent.innerText || '').trim();
    ttsSpeak(text);
  });
  btnPause.addEventListener('click', ttsPause);
  btnResume.addEventListener('click', ttsResume);
  btnStop.addEventListener('click', ttsStop);

  btnPrint.addEventListener('click', () => window.print());

  renderModuleList();
  const startId = location.hash?.slice(1) || cfg.defaultModuleId || cfg.modules[0]?.id;
  selectModule(startId);
})();
