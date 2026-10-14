'use strict';
const menuButton = document.querySelector('.menu-toggle');
const menu = document.querySelector('#mobile-menu');
menuButton?.addEventListener('click', () => {
  const expanded = menuButton.getAttribute('aria-expanded') === 'true';
  menuButton.setAttribute('aria-expanded', String(!expanded));
  menuButton.setAttribute('aria-label', expanded ? 'Menüyü aç' : 'Menüyü kapat');
  menu.hidden = expanded;
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && menu && !menu.hidden) {
    menu.hidden = true; menuButton.setAttribute('aria-expanded', 'false'); menuButton.focus();
  }
});
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));
document.querySelector('[data-select-all]')?.addEventListener('change', event => {
  document.querySelectorAll('input[name="ids[]"]').forEach(input => { if(!input.closest('tr')?.hidden)input.checked = event.target.checked; });
});
document.querySelectorAll('[data-check-group]').forEach(button => button.addEventListener('click', () => {
  const inputs = [...document.querySelectorAll(`input[name="${button.dataset.checkGroup}[]"]`)].filter(input=>!input.closest('label')?.hidden);
  const all = [...inputs].every(input => input.checked);
  inputs.forEach(input => { input.checked = !all; }); updateCount();
}));
function updateCount() {
  const count = document.querySelector('[data-generation-count]');
  if (count) count.textContent = String(document.querySelectorAll('input[name="services[]"]:checked').length * document.querySelectorAll('input[name="cities[]"]:checked').length);
}
document.querySelectorAll('.generator input').forEach(input => input.addEventListener('change', updateCount));
updateCount();
document.querySelector('[data-table-search]')?.addEventListener('input', event => {
  if(document.body.classList.contains('cms-v2'))return;
  const value = event.target.value.toLocaleLowerCase('tr');
  document.querySelectorAll('[data-search-row]').forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase('tr').includes(value); });
});
