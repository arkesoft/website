(() => {
  const normal = value => value.replace(/\s+/g, ' ').trim();
  const dictionary = window.ARKESOFT_LOCALES;
  const reverse = new Map(Object.entries(dictionary.en).map(([key, value]) => [value, key]));
  const originals = new WeakMap();
  let language = document.documentElement.lang;
  let explicit = !!window.ARKESOFT_LANGUAGE_PREFERENCE;
  let revision = 0;
  const attributeNames = ['aria-label', 'placeholder', 'alt', 'title'];
  const countryEndpoint = new URL('api/country', document.currentScript.src);
  function original(value) { const key = normal(value); return reverse.get(key) || key; }
  function t(value) { const key = original(value); return (dictionary[language] || dictionary.tr)[key] || value; }
  function translateText(node) {
    if (!node.parentElement || node.parentElement.closest('script,style,code,pre,[data-no-translate],textarea')) return;
    const raw = node.nodeValue;
    if (!raw.trim()) return;
    const memo = originals.get(node);
    const key = memo && raw === memo.output ? memo.key : original(raw);
    const translated = language === 'en' ? dictionary.en[key] : (dictionary.tr[key] || key);
    if (translated === undefined) return;
    const next = raw.match(/^\s*/)[0] + translated + raw.match(/\s*$/)[0];
    originals.set(node, { key, output: next });
    if (next !== raw) node.nodeValue = next;
  }
  function translate(root = document.body) {
    observer.disconnect();
    if (root.nodeType === Node.TEXT_NODE) translateText(root);
    else {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      while (walker.nextNode()) translateText(walker.currentNode);
      const elements = root.nodeType === Node.ELEMENT_NODE ? [root, ...root.querySelectorAll('*')] : [];
      elements.forEach(element => {
        if (element.closest('[data-no-translate]')) return;
        for (const name of attributeNames) {
          if (!element.hasAttribute(name)) continue;
          const before = element.getAttribute(name), after = t(before);
          if (before !== after) element.setAttribute(name, after);
        }
      });
    }
    observer.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: attributeNames });
  }
  const observer = new MutationObserver(records => {
    const roots = new Set();
    for (const record of records) {
      if (record.type === 'childList') record.addedNodes.forEach(node => roots.add(node));
      else roots.add(record.target);
    }
    roots.forEach(node => { if (node.isConnected) translate(node); });
  });
  const titleSource = document.title;
  const description = document.querySelector('meta[name="description"]');
  const descriptionSource = description?.content;
  function setLanguage(next, manual = false) {
    if (!['tr', 'en'].includes(next)) return;
    language = next;
    if (manual) {
      explicit = true; revision++;
      try { localStorage.setItem('arkesoft-language', next); } catch { /* Optional storage. */ }
    }
    document.documentElement.lang = language;
    document.title = t(titleSource);
    if (description) description.content = t(descriptionSource);
    translate();
    document.querySelectorAll('[data-language]').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.language === language));
    });
    document.dispatchEvent(new CustomEvent('arkesoft:language', { detail: { language } }));
  }
  window.ARKESOFT_I18N = { t, setLanguage, get language() { return language; }, get explicit() { return explicit; } };
  document.querySelectorAll('[data-language]').forEach(button => button.addEventListener('click', () => setLanguage(button.dataset.language, true)));
  setLanguage(language);
  async function resolveCountry() {
    if (explicit) return;
    const initialRevision = revision;
    const injectedCountry = document.querySelector('meta[name="visitor-country"]')?.content;
    let country = /^[A-Z]{2}$/.test(injectedCountry || '') ? injectedCountry : null;
    try {
      const cached = JSON.parse(sessionStorage.getItem('arkesoft-country') || 'null');
      if (!country && cached && Date.now() - cached.time < 1800000 && /^[A-Z]{2}$/.test(cached.country)) country = cached.country;
    } catch { /* Invalid or blocked cache is harmless. */ }
    if (!country && /^https?:$/.test(location.protocol)) {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 2500);
      try {
        // Country comes from our own hosting edge. No external IP lookup is made.
        const response = await fetch(countryEndpoint, { signal: controller.signal, credentials: 'same-origin', cache: 'no-store' });
        if (response.ok) {
          const data = await response.json();
          if (/^[A-Z]{2}$/.test(data.country || '')) country = data.country;
        }
      } catch { /* When country is unknown, use browser language; never block the page. */ }
      finally { clearTimeout(timeout); }
    }
    if (country) {
      try { sessionStorage.setItem('arkesoft-country', JSON.stringify({ country, time: Date.now() })); } catch { /* Optional cache. */ }
      if (!explicit && revision === initialRevision) setLanguage(country === 'TR' ? 'tr' : 'en');
    }
    document.documentElement.dataset.countryStatus = country ? 'resolved' : 'fallback';
  }
  resolveCountry();
})();
