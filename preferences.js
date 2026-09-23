/* Runs before styles to avoid a flash of the wrong theme. */
(() => {
  const query = new URLSearchParams(location.search);
  let theme = query.get('theme'), language = query.get('lang');
  try {
    theme ||= localStorage.getItem('arkesoft-theme');
    language ||= localStorage.getItem('arkesoft-language');
  } catch { /* Storage is optional, links also carry explicit preferences. */ }
  document.documentElement.dataset.theme = ['dark', 'light'].includes(theme) ? theme : 'dark';
  // Browser chrome follows the resolved theme from the first paint on.
  const themeColor = document.querySelector('meta[name="theme-color"]');
  if (themeColor) themeColor.content = document.documentElement.dataset.theme === 'light' ? '#f5f3ee' : '#0c1017';
  window.ARKESOFT_LANGUAGE_PREFERENCE = ['tr', 'en'].includes(language) ? language : null;
  document.documentElement.lang = window.ARKESOFT_LANGUAGE_PREFERENCE || (navigator.language.toLowerCase().startsWith('tr') ? 'tr' : 'en');
})();
