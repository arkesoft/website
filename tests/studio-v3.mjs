import {readFile} from 'node:fs/promises';
export async function checkStudio({owner,visitor,token,ok}){
 const save=(tab,values)=>owner.request('/yonetim?tab='+tab,{csrf:token,...values});
 for(const tab of ['studio','showcases','partners','testimonials','films'])ok((await owner.request('/yonetim?tab='+tab)).status===200,'Studio module '+tab+' renders');
 const exported=JSON.parse((await save('backup',{action:'export_backup'})).body);
 const settings=Object.fromEntries(exported.tables.settings.map(s=>[s.setting_key,s.setting_value]));
 let settingsResult=await save('studio',{...settings,action:'studio_settings',home_work_title:'Yönetimden güncellenen proje seçkisi'});
 ok(settingsResult.status===303&&(await visitor.request('/')).body.includes('Yönetimden güncellenen proje seçkisi'),'Studio settings save and render');
 ok(exported.tables.showcases.length===4&&exported.tables.partners.length===6&&exported.tables.testimonials.length===3&&exported.tables.films.length===2,'Demo studio sections and films included in backup');
 const block=exported.tables.showcases[0];
 let r=await save('showcases',{...block,action:'cms_save',table:'showcases',title:'Düzenlenen vitrin başlığı'});
 ok(r.status===303&&(await visitor.request('/')).body.includes('Düzenlenen vitrin başlığı'),'Showcase edits reach homepage');
 r=await save('showcases',{...block,action:'cms_save',table:'showcases',link:'https://evil.example'});ok(r.status===200&&r.body.includes('Bağlantı, tema'),'Showcase external link rejected');
 const partner=exported.tables.partners[0];await save('partners',{...partner,action:'cms_save',table:'partners',status:'draft'});
 ok(!(await visitor.request('/')).body.includes('<strong>'+partner.name+'</strong>'),'Draft partner hidden from logo wall');
 const quote=exported.tables.testimonials[0];await save('testimonials',{...quote,action:'cms_save',table:'testimonials',quote:'<script>studioAttack()</script> örnek metin'});
 r=await visitor.request('/');ok(r.body.includes('&lt;script&gt;studioAttack()')&&!r.body.includes('<script>studioAttack()'),'Testimonial content safely escaped');
 r=await save('films',{action:'film_delete',id:exported.tables.films[0].id});ok(r.status===200&&r.body.includes('vitrinde kullanılıyor'),'Active hero film cannot be deleted');
 const bad=new FormData();bad.set('csrf',token);bad.set('action','upload_video');bad.set('video',new Blob(['<?php echo 1;'],{type:'video/mp4'}),'fake.mp4');
 r=await owner.request('/yonetim?tab=films',bad);ok(r.status===200&&r.body.includes('Geçerli MP4'),'Executable disguised as video rejected');
 const form=new FormData();form.set('csrf',token);form.set('action','upload_video');form.set('name','Test film');form.set('video',new Blob([await readFile('assets/hero-loop-v3.webm')],{type:'video/webm'}),'demo.webm');
 r=await owner.request('/yonetim?tab=films',form);ok(r.status===303,'Valid WebM upload accepted');
 const updated=JSON.parse((await save('backup',{action:'export_backup'})).body);const film=updated.tables.films.find(f=>f.name==='Test film');
 ok(film&&(await visitor.request('/uploads/'+film.filename)).status===200,'Uploaded video is served');
 r=await save('films',{action:'film_save',id:film.id,name:'Yeni film adı',description:'Örnek erişilebilir özet',poster_id:block.image_id});ok(r.status===303,'Film name description and poster saved');
 r=await save('films',{action:'film_delete',id:film.id});ok(r.status===303&&(await visitor.request('/uploads/'+film.filename)).status===404,'Unused film deletion removes physical file');
 const demo=exported.tables.pages.find(p=>p.status==='published');
 r=await visitor.request('/cozumler/'+demo.slug);ok(r.status===200&&r.headers.get('x-robots-tag')?.includes('noindex'),'Published demo PSEO page stays noindex');
}
