(() => {
  'use strict';
  document.querySelectorAll('[data-copy-address]').forEach(button => button.addEventListener('click', async () => {
    const section = button.closest('.growth-location');
    try { await navigator.clipboard.writeText(section.querySelector('[data-office-address]').textContent); section.querySelector('[data-copy-status]').textContent = ' Adres kopyalandı.'; }
    catch { section.querySelector('[data-copy-status]').textContent = ' Adresi seçip kopyalayabilirsiniz.'; }
  }));
  document.querySelectorAll('[data-load-map]').forEach(button => button.addEventListener('click', () => {
    const container = button.closest('[data-map-lat]');
    const lat = Number(container.dataset.mapLat), lng = Number(container.dataset.mapLng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat)>90 || Math.abs(lng)>180) return;
    const frame = document.createElement('iframe'); frame.title = 'İşletme konumu'; frame.loading = 'lazy';
    frame.referrerPolicy = 'no-referrer'; frame.src = `https://www.google.com/maps?q=${lat},${lng}&z=15&output=embed`;
    button.replaceWith(frame);
  }));
  const form = document.querySelector('.contact-form');
  if (form) {
    const email = form.elements.email, phone = form.elements.phone;
    const validate = () => email.setCustomValidity(email.value.trim() || phone.value.replace(/\D/g,'').length >= 10 ? '' : 'E-posta veya telefon bilgilerinden birini yazın.');
    email.addEventListener('input',validate); phone.addEventListener('input',validate); validate();
    form.addEventListener('submit', () => { const button=form.querySelector('button[type=submit]'); if(button){button.disabled=true;button.textContent='Gönderiliyor…';} });
    addEventListener('pageshow',()=>{const button=form.querySelector('button[type=submit]');if(button){button.disabled=false;button.textContent='Projemi konuşalım ↗';}});
  }
  // Keep footer links reachable without adding empty space below the entire site.
  const contactBar=document.querySelector('.growth-contact-bar'), footerEnd=document.querySelector('.agency-footer .footer-bottom');
  if(contactBar && footerEnd && 'IntersectionObserver' in window){
    let footerVisible=false;
    const updateBar=()=>{contactBar.hidden=footerVisible && !contactBar.contains(document.activeElement);};
    new IntersectionObserver(entries=>{footerVisible=entries[0].isIntersecting;updateBar();},{rootMargin:'0px 0px 90px 0px'}).observe(footerEnd);
    contactBar.addEventListener('focusout',()=>queueMicrotask(updateBar));
  }
  // Campaign details follow internal contact links without cookies or persistent identifiers.
  const params = new URLSearchParams(location.search);
  document.querySelectorAll('a[href^="/iletisim"]').forEach(link => {
    const target = new URL(link.href);
    for (const key of ['utm_source','utm_medium','utm_campaign']) if (params.get(key)) target.searchParams.set(key,params.get(key).slice(0,100));
    link.href = target.pathname+target.search;
  });
})();
