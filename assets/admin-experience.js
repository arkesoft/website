'use strict';
(()=>{
 document.body.classList.add('admin-enhanced');
 const menu=document.querySelector('[data-admin-menu]'),sidebar=document.querySelector('.admin-sidebar');
 menu?.addEventListener('click',()=>{const open=sidebar.classList.toggle('menu-open');menu.setAttribute('aria-expanded',String(open));menu.textContent=open?'Menüyü kapat ×':'Menü ☰';});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&sidebar?.classList.contains('menu-open')){sidebar.classList.remove('menu-open');menu.setAttribute('aria-expanded','false');menu.textContent='Menü ☰';menu.focus();}});
 const search=document.querySelector('[data-nav-search]'),groups=[...document.querySelectorAll('.admin-nav-group')],initial=groups.map(g=>g.open);
 search?.addEventListener('input',()=>{const q=search.value.trim().toLocaleLowerCase('tr');let count=0;groups.forEach((g,index)=>{let found=0;g.querySelectorAll('[data-nav-item]').forEach(a=>{a.hidden=!!q&&!a.textContent.toLocaleLowerCase('tr').includes(q);if(!a.hidden){found++;count++;}});g.hidden=!found;g.open=q?!!found:initial[index];});document.querySelector('[data-nav-result]').textContent=q?(count?`${count} menü bağlantısı bulundu.`:'Eşleşme bulunamadı. Başka bir kelime deneyin.'):'';});
 let dirty=false;const editor=document.querySelector('[data-content-editor]'),status=document.querySelector('[data-save-status]');
 editor?.addEventListener('input',()=>{dirty=true;if(status){status.textContent='Kaydedilmemiş değişiklikler var.';status.classList.add('dirty');}});
 editor?.addEventListener('change',()=>{dirty=true;if(status){status.textContent='Kaydedilmemiş değişiklikler var.';status.classList.add('dirty');}});
 editor?.addEventListener('submit',()=>{dirty=false;if(status)status.textContent='Kaydediliyor…';});
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
 document.querySelectorAll('input[type=password]').forEach((input,index)=>{input.id ||= 'password-'+index;const button=document.createElement('button');button.type='button';button.className='password-toggle';button.textContent='Şifreyi göster';button.setAttribute('aria-controls',input.id);button.setAttribute('aria-pressed','false');button.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Şifreyi gizle':'Şifreyi göster';button.setAttribute('aria-pressed',String(show));});input.closest('label').after(button);});
 document.querySelectorAll('.table-scroll').forEach((el,index)=>{el.tabIndex=0;el.setAttribute('role','region');el.setAttribute('aria-label','İçerik tablosu '+(index+1)+'; dar ekranda yatay kaydırılabilir');});
 document.querySelector('.notice.error')?.setAttribute('role','alert');
})();
