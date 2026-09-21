(() => {
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  const root = document.documentElement;
  const t = value => window.ARKESOFT_I18N?.t(value) || value;
  const themeButtons = document.querySelectorAll('.theme-toggle');
  function setTheme(theme) {
    root.dataset.theme = theme;
    try { localStorage.setItem('arkesoft-theme', theme); } catch { /* Optional persistence. */ }
    themeButtons.forEach(button => {
      button.setAttribute('aria-pressed', String(theme === 'light'));
      button.setAttribute('aria-label', t(theme === 'dark' ? 'Açık temaya geç' : 'Koyu temaya geç'));
    });
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'light' ? '#f5f3ee' : '#0c1017');
  }
  themeButtons.forEach(button => button.addEventListener('click', () => setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark')));
  setTheme(root.dataset.theme);
  document.addEventListener('arkesoft:language', () => setTheme(root.dataset.theme));
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.getAttribute('href').startsWith('#')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !url.pathname.endsWith('.html')) return;
    url.searchParams.set('theme', root.dataset.theme);
    if (window.ARKESOFT_I18N?.explicit) url.searchParams.set('lang', root.lang);
    link.href = url.href;
  });

  const journey = document.querySelector('.digital-journey');
  const chapters = [...document.querySelectorAll('.journey-chapter')];
  const screens = [...document.querySelectorAll('.journey-screen')];
  const wordLine = document.querySelector('.word-reveal');
  const immersives = [...document.querySelectorAll('.immersive-scroll')];
  let words = [], frame = 0, active = -1;
  function splitWords() {
    if (!wordLine) return;
    // Keep locale text on the heading; decorative spans are rebuilt on language change.
    const source = t('İyi yazılım çalışır. İyi tasarım hissettirir. Biz ikisini birlikte düşünürüz.');
    wordLine.replaceChildren();
    words = source.split(' ').map((word, i) => {
      const span = document.createElement('span'); span.textContent = word; span.dataset.noTranslate = '';
      if (i) wordLine.append(' '); wordLine.append(span); return span;
    });
  }
  splitWords();
  document.addEventListener('arkesoft:language', () => { splitWords(); schedule(); });
  function renderScroll() {
    frame = 0;
    document.querySelector('.header')?.classList.toggle('is-scrolled', scrollY > 70);
    root.style.setProperty('--reading-progress', Math.min(1, scrollY / Math.max(1, document.documentElement.scrollHeight - innerHeight)).toFixed(4));
    immersives.forEach(section => {
      const rect = section.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > innerHeight) return;
      const progress = Math.max(0, Math.min(1, -rect.top / Math.max(1, section.offsetHeight - innerHeight)));
      const expand = Math.min(1, progress / .42);
      const leave = Math.max(0, (progress - .78) / .22);
      section.style.setProperty('--image-inset', motion.matches ? '0%' : `${18 * (1 - expand) + 6 * leave}%`);
      section.style.setProperty('--image-scale', motion.matches ? '1' : String(1.12 - .12 * expand + .035 * leave));
      section.style.setProperty('--caption-opacity', motion.matches ? '1' : String(Math.max(0, Math.min(1, (progress - .2) * 5))));
    });
    if (journey) {
      const rect = journey.getBoundingClientRect();
      const progress = Math.max(0, Math.min(1, -rect.top / Math.max(1, journey.offsetHeight - innerHeight)));
      const next = motion.matches ? 0 : Math.min(2, Math.floor(progress * 3));
      if (next !== active) {
        active = next;
        chapters.forEach((chapter, i) => { chapter.classList.toggle('active', i === active); chapter.inert = !motion.matches && i !== active; });
        screens.forEach((screen, i) => screen.classList.toggle('active', i === active));
        const counter = document.querySelector('.journey-counter'); if (counter) counter.textContent = `0${active + 1} / 03`;
      }
      journey.style.setProperty('--journey-progress', progress.toFixed(4));
      journey.style.setProperty('--device-turn', motion.matches ? '0deg' : `${-12 + progress * 19}deg`);
      journey.style.setProperty('--device-shift', motion.matches ? '0px' : `${36 - progress * 72}px`);
    }
    if (wordLine) {
      const rect = wordLine.getBoundingClientRect();
      const progress = motion.matches ? 1 : Math.max(0, Math.min(1, (innerHeight * .88 - rect.top) / (innerHeight * .5)));
      words.forEach((word, i) => word.classList.toggle('lit', i / words.length <= progress));
    }
  }
  function schedule() { if (!frame && !document.hidden) frame = requestAnimationFrame(renderScroll); }
  addEventListener('scroll', schedule, { passive: true });
  addEventListener('resize', schedule, { passive: true });
  motion.addEventListener('change', () => { active = -1; schedule(); });
  document.addEventListener('visibilitychange', schedule);
  schedule();
  const track = document.querySelector('.gallery-track');
  document.querySelectorAll('[data-gallery]').forEach(button => button.addEventListener('click', () => {
    track.scrollBy({ left: Number(button.dataset.gallery) * (track.querySelector('.gallery-card').offsetWidth + 24), behavior: motion.matches ? 'instant' : 'smooth' });
  }));
  if (track) {
    const updateGallery = () => document.querySelectorAll('[data-gallery]').forEach(button => {
      button.disabled = Number(button.dataset.gallery) < 0 ? track.scrollLeft <= 2 : track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
    });
    track.addEventListener('scroll', updateGallery, { passive: true });
    addEventListener('resize', updateGallery); updateGallery();
  }

  const search = document.querySelector('#project-search');
  if (search) {
    const buttons = [...document.querySelectorAll('[data-filter]')];
    const cards = [...document.querySelectorAll('.works-grid [data-category]')];
    const normalize = value => value.toLocaleLowerCase('tr').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ı/g, 'i');
    let selected = 'all';
    function readQuery() {
      const params = new URLSearchParams(location.search);
      selected = buttons.some(button => button.dataset.filter === params.get('filter')) ? params.get('filter') : 'all';
      search.value = (params.get('q') || '').slice(0, 100);
    }
    function filterProjects(writeURL = true) {
      const query = normalize(search.value.trim()); let count = 0;
      cards.forEach(card => {
        card.hidden = (selected !== 'all' && card.dataset.category !== selected) || !normalize(card.textContent + ' ' + card.dataset.search).includes(query);
        if (!card.hidden) { count++; card.classList.add('visible'); }
      });
      buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.filter === selected)));
      document.querySelector('.works-grid').classList.toggle('is-filtered', selected !== 'all' || !!query);
      document.querySelector('#project-count').textContent = `${String(count).padStart(2, '0')} / ${String(cards.length).padStart(2, '0')}`;
      document.querySelector('#project-empty').hidden = count > 0;
      if (writeURL) {
        const url = new URL(location.href);
        selected === 'all' ? url.searchParams.delete('filter') : url.searchParams.set('filter', selected);
        search.value ? url.searchParams.set('q', search.value) : url.searchParams.delete('q');
        history.replaceState(null, '', url);
      }
    }
    buttons.forEach(button => button.addEventListener('click', () => { selected = button.dataset.filter; filterProjects(); }));
    search.addEventListener('input', () => filterProjects());
    document.querySelector('[data-reset-projects]').addEventListener('click', () => { selected = 'all'; search.value = ''; filterProjects(); search.focus(); });
    addEventListener('popstate', () => { readQuery(); filterProjects(false); });
    document.addEventListener('arkesoft:language', () => filterProjects(false));
    readQuery(); filterProjects(false);
  }
})();
