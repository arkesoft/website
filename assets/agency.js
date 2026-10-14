'use strict';
(() => {
  document.querySelector('[data-error-summary]')?.focus();
  const triggers=[...document.querySelectorAll('.mega-trigger')];
  const closeMenus=()=>triggers.forEach(b=>{b.setAttribute('aria-expanded','false');document.getElementById(b.getAttribute('aria-controls')).hidden=true;});
  triggers.forEach(button=>button.addEventListener('click',()=>{const open=button.getAttribute('aria-expanded')==='true';closeMenus();if(!open){button.setAttribute('aria-expanded','true');document.getElementById(button.getAttribute('aria-controls')).hidden=false;}}));
  document.addEventListener('click',e=>{if(!e.target.closest('.nav-dropdown'))closeMenus();});
  document.addEventListener('focusin',e=>{if(!e.target.closest('.nav-dropdown'))closeMenus();});
  triggers.forEach(b=>b.addEventListener('keydown',e=>{if(e.key==='ArrowDown'){e.preventDefault();closeMenus();b.setAttribute('aria-expanded','true');const panel=document.getElementById(b.getAttribute('aria-controls'));panel.hidden=false;panel.querySelector('a')?.focus();}}));
  const toggle=document.querySelector('.agency-menu-toggle'), mobile=document.querySelector('#agency-mobile');
  toggle?.addEventListener('click',()=>{mobile.hidden=!mobile.hidden;toggle.setAttribute('aria-expanded',String(!mobile.hidden));toggle.setAttribute('aria-label',mobile.hidden?'Menüyü aç':'Menüyü kapat');});
  document.addEventListener('keydown',e=>{if(e.key!=='Escape')return;const opened=triggers.find(b=>b.getAttribute('aria-expanded')==='true');closeMenus();opened?.focus();if(mobile&&!mobile.hidden){mobile.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.focus();}});
  document.querySelectorAll('[data-project-filter]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-project-filter]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',String(b===button));});document.querySelectorAll('[data-project-category]').forEach(card=>card.hidden=button.dataset.projectFilter!=='all'&&card.dataset.projectCategory!==button.dataset.projectFilter);const result=document.querySelector('[data-project-result]');if(result)result.textContent=document.querySelectorAll('[data-project-category]:not([hidden])').length+' çalışma gösteriliyor.';}));
  document.querySelector('[data-solution-search]')?.addEventListener('input',e=>{document.querySelectorAll('[data-solution]').forEach(a=>a.hidden=!a.textContent.toLocaleLowerCase('tr').includes(e.target.value.toLocaleLowerCase('tr')));const n=document.querySelectorAll('[data-solution]:not([hidden])').length;document.querySelector('[data-solution-result]').textContent=n?n+' çözüm gösteriliyor.':'Bu sayfada eşleşme bulunamadı. Aramayı temizleyebilir veya bize yazabilirsiniz.';});
})();
