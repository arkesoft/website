/* Runs before styles to avoid a flash of the wrong theme. */
(() => {
  const query = new URLSearchParams(location.search);
  let theme = query.get('theme'), language = query.get('lang');
  try {
    theme ||= localStorage.getItem('arkesoft-theme');
    language ||= localStorage.getItem('arkesoft-language');
  } catch { /* Storage is optional, links also carry explicit preferences. */ }
  document.documentElement.dataset.theme = ['dark', 'light'].includes(theme) ? theme : 'dark';
  window.ARKESOFT_LANGUAGE_PREFERENCE = ['tr', 'en'].includes(language) ? language : null;
  document.documentElement.lang = window.ARKESOFT_LANGUAGE_PREFERENCE || (navigator.language.toLowerCase().startsWith('tr') ? 'tr' : 'en');
})();
