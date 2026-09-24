/* Apply preferences before styles, then normalize old bookmarked URLs. */
(() => {
  const allowed = { theme: ['dark', 'light'], language: ['tr', 'en'] };
  const read = key => {
    for (const storage of ['localStorage', 'sessionStorage']) {
      try {
        const value = window[storage].getItem(`arkesoft-${key}`);
        if (allowed[key].includes(value)) return value;
      } catch { /* A blocked storage backend must not break navigation. */ }
    }
    return null;
  };
  const save = (key, value) => {
    if (!allowed[key]?.includes(value)) return;
    for (const storage of ['localStorage', 'sessionStorage']) {
      try { window[storage].setItem(`arkesoft-${key}`, value); } catch { /* Optional. */ }
    }
  };
  window.ARKESOFT_PREFERENCES = { read, save };
  const url = new URL(location.href);
  const fromQuery = (parameter, key) => {
    const value = url.searchParams.get(parameter);
    if (allowed[key].includes(value)) { save(key, value); return value; }
    return read(key);
  };
  const theme = fromQuery('theme', 'theme');
  const pairs = Object.values(window.ARKESOFT_ROUTES || {});
  const path = url.pathname.replace(/\/index\.html$|\/$/g, '');
  const pathLanguage = pairs.some(pair => pair.en === path) ? 'en' : null;
  const language = allowed.language.includes(url.searchParams.get('lang')) ? fromQuery('lang', 'language') : pathLanguage || read('language');
  document.documentElement.dataset.theme = theme || 'dark';
  window.ARKESOFT_LANGUAGE_PREFERENCE = language;
  document.documentElement.lang = language || (navigator.language.toLowerCase().startsWith('tr') ? 'tr' : 'en');
  if (/^https?:$/.test(url.protocol)) {
    const canonical = document.querySelector('link[rel="canonical"]');
    // Never turn an unknown/404 address into a valid page address.
    if (canonical && !document.querySelector('meta[name="robots"][content*="noindex"]')) {
      const canonicalPath = new URL(canonical.href).pathname;
      const pair = pairs.find(pair => Object.values(pair).includes(canonicalPath));
      url.pathname = pair?.[document.documentElement.lang] || canonicalPath;
    }
    url.searchParams.delete('theme');
    url.searchParams.delete('lang');
    if (url.href !== location.href) history.replaceState(history.state, '', url.pathname + url.search + url.hash);
  }
})();
