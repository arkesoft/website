(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  const saveData = navigator.connection?.saveData;
  const body = document.body;
  const t = value => window.ARKESOFT_I18N?.t(value) || value;
  body.classList.add('js');
  let overlay = null;
  let lastFocus = null;

  // All overlay content stays keyboard accessible; the page behind it is inert.
  function lockOverlay(element) {
    if (overlay) return false;
    lastFocus = document.activeElement;
    overlay = element;
    [...body.children].forEach(child => {
      if (child !== element && child.tagName !== 'SCRIPT') child.inert = true;
    });
    body.classList.add('locked');
    $$('video').forEach(video => video.pause());
    clearInterval(carouselTimer);
    return true;
  }
  function unlockOverlay(element) {
    if (overlay !== element) return;
    [...body.children].forEach(child => { child.inert = false; });
    body.classList.remove('locked');
    overlay = null;
    if (lastFocus && lastFocus !== body && lastFocus.isConnected) lastFocus.focus({ preventScroll: true });
    else { const main = $('#main'); main.tabIndex = -1; main.focus({ preventScroll: true }); }
    if (element === menu) { selectSlide(slideIndex); scheduleCarousel(); }
    $$('[data-auto-video]').forEach(video => {
      if (video.dataset.inView === 'true' && video.dataset.userPaused !== 'true' && !motion.matches && !saveData && !document.hidden) playVideo(video);
    });
  }
  document.addEventListener('keydown', event => {
    if (event.key !== 'Tab' || !overlay) return;
    const focusable = $$('a[href],button:not([disabled]),input,select,textarea', overlay).filter(el => el.getClientRects().length);
    if (!focusable.length) return;
    const first = focusable[0], last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  const menu = $('#mega-menu');
  const menuButton = $('.menu-toggle');
  function closeMenu() {
    if (!menu || menu.hidden) return;
    menu.hidden = true;
    menuButton.setAttribute('aria-expanded', 'false');
    unlockOverlay(menu);
  }
  menuButton?.addEventListener('click', () => {
    if (!lockOverlay(menu)) return;
    menu.hidden = false;
    menuButton.setAttribute('aria-expanded', 'true');
    $('.menu-close', menu).focus();
  });
  $('.menu-close')?.addEventListener('click', closeMenu);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });

  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('visible'); revealObserver.unobserve(entry.target); }
    }), { threshold: 0.05 });
    $$('.reveal').forEach(el => revealObserver.observe(el));
  } else $$('.reveal').forEach(el => el.classList.add('visible'));

  function attachVideo(video) {
    if (!video.getAttribute('src') && video.dataset.src) {
      video.src = video.dataset.src.endsWith('.webm') && !video.canPlayType('video/webm') && video.dataset.fallback ? video.dataset.fallback : video.dataset.src;
      video.load();
    }
  }
  function playVideo(video) {
    if (!video) return;
    attachVideo(video);
    const attempt = video.play();
    attempt?.catch(() => { /* Poster remains visible if autoplay is blocked. */ });
  }

  // Home visual sequence. A user selection stops automatic slide changes.
  const slides = $$('.hero-slide');
  const slideButtons = $$('[data-slide]');
  const motionToggle = $('.video-toggle');
  let slideIndex = 0;
  let heroVisible = true;
  let carouselTimer;
  let carouselPaused = motion.matches || !!saveData;
  let userChoseSlide = false;
  const captions = ['01 — FİKİRDEN DİJİTAL DÜNYAYA', '02 — BAKIŞ AÇISI HER ŞEYİ DEĞİŞTİRİR', '03 — HER DOKUNUŞTA AYNI ÖZEN'];
  function syncMotionButton() {
    if (!motionToggle) return;
    motionToggle.textContent = carouselPaused ? '▷' : 'Ⅱ';
    motionToggle.setAttribute('aria-pressed', String(carouselPaused));
    motionToggle.setAttribute('aria-label', carouselPaused ? 'Sahne hareketini oynat' : 'Sahne hareketini duraklat');
    slides.forEach(slide => { const img = $('img', slide); if (img) img.style.animationPlayState = carouselPaused ? 'paused' : 'running'; });
  }
  function selectSlide(index) {
    slideIndex = index;
    slides.forEach((slide, i) => {
      slide.classList.toggle('active', i === index);
      const video = $('video', slide);
      if (video) {
        if (i === index && heroVisible && !carouselPaused && !document.hidden && !overlay) playVideo(video);
        else video.pause();
      }
    });
    slideButtons.forEach((button, i) => button.setAttribute('aria-pressed', String(i === index)));
    if ($('#slide-caption')) $('#slide-caption').textContent = captions[index];
  }
  function scheduleCarousel() {
    clearInterval(carouselTimer);
    if (slides.length && heroVisible && !carouselPaused && !userChoseSlide && !document.hidden && !overlay) {
      carouselTimer = setInterval(() => selectSlide((slideIndex + 1) % slides.length), 18000);
    }
  }
  slideButtons.forEach(button => button.addEventListener('click', () => {
    userChoseSlide = true;
    clearInterval(carouselTimer);
    selectSlide(Number(button.dataset.slide));
  }));
  motionToggle?.addEventListener('click', () => {
    carouselPaused = !carouselPaused;
    syncMotionButton(); selectSlide(slideIndex); scheduleCarousel();
  });
  syncMotionButton();
  if (slides.length && 'IntersectionObserver' in window) {
    const heroObserver = new IntersectionObserver(entries => {
      heroVisible = entries[0].isIntersecting;
      if (!heroVisible) { clearInterval(carouselTimer); slides.forEach(slide => $('video', slide)?.pause()); }
      else if (!overlay && !filmDialog?.open) { selectSlide(slideIndex); scheduleCarousel(); }
    }, { threshold: 0.1 });
    heroObserver.observe($('.hero'));
  }

  // Timed introduction is decorative, never a fake network loading indicator.
  const intro = $('#intro');
  let introTimers = [];
  let introFrame;
  let introPlaying = false;
  function clearIntroTimers() { introTimers.forEach(clearTimeout); introTimers = []; cancelAnimationFrame(introFrame); }
  function finishIntro(instant = false) {
    if (!intro || !introPlaying) return;
    introPlaying = false;
    clearIntroTimers();
    const clean = () => {
      intro.hidden = true;
      intro.className = 'intro';
      unlockOverlay(intro);
      selectSlide(slideIndex);
      scheduleCarousel();
    };
    if (instant || motion.matches) clean();
    else {
      intro.classList.add('is-opening');
      introTimers.push(setTimeout(clean, 1500));
    }
  }
  function startIntro() {
    if (!intro || motion.matches || overlay) return false;
    clearIntroTimers();
    lockOverlay(intro);
    intro.className = 'intro';
    intro.hidden = false;
    introPlaying = true;
    clearInterval(carouselTimer);
    selectSlide(0);
    for (const selector of ['.intro-art', '.intro-rule']) {
      const el = $(selector, intro); el.replaceWith(el.cloneNode(true));
    }
    const shots = $$('.intro-montage img', intro);
    shots.forEach((shot, i) => shot.classList.toggle('active', i === 0));
    intro.tabIndex = -1;
    intro.focus({ preventScroll: true });
    const start = performance.now();
    const tick = now => {
      $('#intro-progress').textContent = `${String(Math.min(100, Math.round((now - start) / 49))).padStart(2, '0')} — 100`;
      if (introPlaying) introFrame = requestAnimationFrame(tick);
    };
    introFrame = requestAnimationFrame(tick);
    introTimers.push(setTimeout(() => intro.classList.add('is-montage'), 2600));
    introTimers.push(setTimeout(() => { shots[0].classList.remove('active'); shots[1].classList.add('active'); }, 3350));
    introTimers.push(setTimeout(() => { shots[1].classList.remove('active'); shots[2].classList.add('active'); }, 4100));
    introTimers.push(setTimeout(() => finishIntro(), 4900));
    return true;
  }
  $('.intro-skip')?.addEventListener('click', () => finishIntro());
  document.addEventListener('keydown', event => { if (event.key === 'Escape') finishIntro(true); });
  $$('[data-replay]').forEach(button => button.addEventListener('click', () => {
    if (motion.matches) {
      button.textContent = 'HAREKET AZALTMA TERCİHİ ETKİN';
      return;
    }
    if (intro) { window.scrollTo({ top: 0, behavior: 'instant' }); startIntro(); }
    else window.location.href = 'index.html?intro=1';
  }));
  const navigationType = performance.getEntriesByType('navigation')[0]?.type;
  let arrivedFromSite = false;
  try { arrivedFromSite = !!document.referrer && new URL(document.referrer).origin === location.origin && new URL(document.referrer).pathname.startsWith(new URL('.', location.href).pathname); } catch { /* Treat a missing referrer as a fresh arrival. */ }
  const forceIntro = new URLSearchParams(location.search).has('intro');
  if (!intro || motion.matches || (arrivedFromSite && navigationType !== 'reload' && !forceIntro)) scheduleCarousel();
  else startIntro();

  // Play video only in view; native controls are available in the film dialog.
  const ambientVideos = $$('[data-auto-video]');
  let videoObserver;
  if ('IntersectionObserver' in window) {
    videoObserver = new IntersectionObserver(entries => entries.forEach(entry => {
      entry.target.dataset.inView = String(entry.isIntersecting);
      if (entry.isIntersecting && !motion.matches && !saveData && entry.target.dataset.userPaused !== 'true' && !document.hidden && !overlay) playVideo(entry.target);
      else entry.target.pause();
    }), { threshold: 0.15 });
    ambientVideos.forEach(video => videoObserver.observe(video));
  }
  ambientVideos.forEach(video => {
    const toggle = $('.film-pause', video.parentElement);
    video.addEventListener('play', () => { toggle.textContent = 'Videoyu duraklat Ⅱ'; });
    video.addEventListener('pause', () => { toggle.textContent = 'Videoyu oynat ▷'; });
    toggle.addEventListener('click', () => {
      if (video.paused) { video.dataset.userPaused = 'false'; playVideo(video); }
      else { video.dataset.userPaused = 'true'; video.pause(); }
    });
  });
  const filmDialog = $('#film-dialog');
  const filmVideo = $('video', filmDialog);
  $$('[data-film]').forEach(button => button.addEventListener('click', () => {
    if (typeof filmDialog.showModal !== 'function') { window.open('assets/signature.svg', '_blank', 'noopener'); return; }
    filmDialog.showModal();
    $$('video').filter(video => video !== filmVideo).forEach(video => video.pause());
    clearInterval(carouselTimer);
  }));
  $('.film-close')?.addEventListener('click', () => filmDialog.close());
  filmDialog?.addEventListener('cancel', () => filmVideo?.pause());
  filmDialog?.addEventListener('close', () => { filmVideo?.pause(); selectSlide(slideIndex); scheduleCarousel(); });
  filmDialog?.addEventListener('click', event => {
    if (event.target !== filmDialog) return;
    const r = filmDialog.getBoundingClientRect();
    if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) filmDialog.close();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(carouselTimer); $$('video').forEach(video => video.pause()); }
    else {
      if (!introPlaying && !filmDialog?.open) { selectSlide(slideIndex); scheduleCarousel(); }
      ambientVideos.forEach(video => { if (video.dataset.inView === 'true' && video.dataset.userPaused !== 'true' && !motion.matches && !saveData && !filmDialog?.open) playVideo(video); });
    }
  });
  motion.addEventListener('change', () => {
    if (motion.matches) {
      finishIntro(true); carouselPaused = true; clearInterval(carouselTimer);
      $$('video').forEach(video => video.pause()); syncMotionButton();
    }
  });

  $$('[data-expertise-image]').forEach(link => {
    const change = () => { const img = $('#expertise-image'); if (img) img.src = `assets/${link.dataset.expertiseImage}`; };
    link.addEventListener('mouseenter', change); link.addEventListener('focus', change);
  });
  // The portfolio's search/filter logic lives in experience.js.

  // Local brief builder. Personal data is not stored or transmitted.
  const form = $('#brief-form');
  if (form) {
    const serviceNames = { 'web-sitesi': 'Web sitesi', 'mobil-uygulama': 'Mobil uygulama', otomasyon: 'Otomasyon', 'sosyal-medya': 'Sosyal medya', seo: 'SEO', setup: 'Setup & kurulum', bakim: 'Bakım & destek' };
    const serviceParam = new URLSearchParams(location.search).get('hizmet');
    $$('input[name=service]', form).forEach(input => { input.checked = input.value === serviceParam; });
    let step = 0;
    const steps = $$('[data-step]', form);
    const error = $('#form-error');
    const selectedServices = () => $$('input[name=service]:checked', form).map(input => t(serviceNames[input.value]));
    function validate() {
      error.textContent = '';
      if (step === 0 && !selectedServices().length) { error.textContent = 'Lütfen en az bir hizmet seçin.'; return false; }
      if (step === 1) {
        for (const name of ['name', 'email', 'message']) {
          const input = form.elements[name];
          if (!input.value.trim() || !input.checkValidity()) {
            error.textContent = name === 'email' ? 'Geçerli bir e-posta adresi yazın.' : 'Lütfen adınızı ve proje fikrinizi tamamlayın.';
            input.focus(); return false;
          }
        }
      }
      return true;
    }
    function summaryItems() {
      return [['MARKA / İSİM', form.elements.name.value.trim()], ['E-POSTA', form.elements.email.value.trim()], ['HİZMETLER', selectedServices().join(', ')], ...(form.elements.budget ? [['BÜTÇE ARALIĞI', form.elements.budget.selectedOptions[0].textContent]] : []), ['BAŞLANGIÇ', form.elements.timeline.selectedOptions[0].textContent], ['PROJE FİKRİ', form.elements.message.value.trim()]].map(([label, value]) => [t(label), value]);
    }
    function showStep(next) {
      step = next;
      steps.forEach((section, i) => { section.hidden = i !== step; });
      $$('.form-progress span', form).forEach((bar, i) => bar.classList.toggle('active', i <= step));
      $('.form-progress', form).setAttribute('aria-label', `Proje briefi, adım ${step + 1} / 3`);
      error.textContent = '';
      if (step === 2) {
        const summary = $('#brief-summary'); summary.replaceChildren();
        summaryItems().forEach(([label, value]) => {
          const p = document.createElement('p'), strong = document.createElement('strong');
          const valueElement = document.createElement('span'); valueElement.dataset.noTranslate = ''; valueElement.textContent = value;
          strong.textContent = label; p.append(strong, valueElement); summary.append(p);
        });
      }
      const heading = $('h2', steps[step]); heading.tabIndex = -1; heading.focus({ preventScroll: true });
      if (innerWidth < 761) form.scrollIntoView({ behavior: motion.matches ? 'instant' : 'smooth', block: 'start' });
    }
    $$('[data-next]', form).forEach(button => button.addEventListener('click', () => { if (validate()) showStep(step + 1); }));
    $$('[data-back]', form).forEach(button => button.addEventListener('click', () => showStep(step - 1)));
    document.addEventListener('arkesoft:language', () => { if (step === 2) showStep(2); });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (step < 2) { if (validate()) showStep(step + 1); return; }
      const sendButton = $('.form-download', form);
      sendButton.disabled = true;
      try {
        const response = await fetch('api/brief', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ title: t(form.dataset.docTitle || 'ARKESOFT — PROJE BRİEFİ'), page: location.pathname, items: summaryItems() }) });
        if (response.ok) {
          sendButton.disabled = false;
          $('#form-success').textContent = 'Talebiniz bize ulaştı. En geç 24 saat içinde dönüş yapacağız.';
          return;
        }
      } catch (error) { /* gönderim yoksa dosya indirme akışına düş */ }
      sendButton.disabled = false;
      const content = t(form.dataset.docTitle || 'ARKESOFT — PROJE BRİEFİ') + '\n\n' + summaryItems().map(([label, value]) => `${label}\n${value}`).join('\n\n');
      const url = URL.createObjectURL(new Blob(['\uFEFF', content], { type: 'text/plain;charset=utf-8' }));
      const download = document.createElement('a'); download.href = url; download.download = document.documentElement.lang === 'en' ? (form.dataset.fileEn || 'arkesoft-project-brief.txt') : (form.dataset.fileTr || 'arkesoft-proje-briefi.txt');
      body.append(download); download.click(); download.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      $('#form-success').textContent = 'Çevrimiçi gönderim şu anda kullanılamıyor; talebiniz dosya olarak indirildi. Dosyayı arkesoft.info@gmail.com adresine e-postayla iletebilirsiniz.';
    });
  }
  // Premium imleç: nokta + gecikmeli halka. Yalnızca hassas işaretçide ve
  // hareket azaltma kapalıyken; form alanlarında yerli imlece döner.
  const finePointer = matchMedia('(pointer: fine)');
  if (finePointer.matches && !motion.matches) {
    const html = document.documentElement;
    const dot = document.createElement('div'); dot.className = 'cursor-dot';
    const ring = document.createElement('div'); ring.className = 'cursor-ring';
    dot.setAttribute('aria-hidden', 'true'); ring.setAttribute('aria-hidden', 'true');
    body.append(ring, dot);
    html.classList.add('has-cursor');
    let x = innerWidth / 2, y = innerHeight / 2, ringX = x, ringY = y;
    addEventListener('mousemove', event => {
      x = event.clientX; y = event.clientY;
      dot.style.transform = `translate(${x}px, ${y}px)`;
      html.classList.add('cursor-on');
    }, { passive: true });
    (function follow() {
      ringX += (x - ringX) * 0.16; ringY += (y - ringY) * 0.16;
      ring.style.transform = `translate(${ringX}px, ${ringY}px)`;
      requestAnimationFrame(follow);
    })();
    addEventListener('mousedown', () => html.classList.add('cursor-press'));
    addEventListener('mouseup', () => html.classList.remove('cursor-press'));
    document.addEventListener('mouseleave', () => html.classList.remove('cursor-on'));
    document.addEventListener('mouseover', event => {
      if (event.target.closest('input, textarea, select')) {
        html.classList.add('cursor-native'); html.classList.remove('cursor-hot'); return;
      }
      html.classList.remove('cursor-native');
      html.classList.toggle('cursor-hot', !!event.target.closest('a, button, summary, label, [role=button]'));
    });
  }

  $$('[data-year]').forEach(el => { el.textContent = new Date().getFullYear(); });
  function updateClock() {
    $$('[data-clock]').forEach(el => { el.textContent = new Intl.DateTimeFormat('tr-TR', { timeZone: 'Europe/Istanbul', hour: '2-digit', minute: '2-digit' }).format(new Date()); });
  }
  updateClock(); setInterval(updateClock, 60000);
})();
