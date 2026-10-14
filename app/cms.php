<?php
declare(strict_types=1);
function cms_defaults(): array {
    return studio_defaults()+['hero_kicker'=>'TASARIM. TEKNOLOJİ. BÜYÜME.','hero_line1'=>'Markanızın','hero_line2'=>'dijitaldeki','hero_line3'=>'yeni gücü.','hero_text'=>'İyi bir fikri; güçlü bir markaya, etkileyici bir web deneyimine ve ölçülebilir bir büyüme planına dönüştürüyoruz.','home_intro'=>'Dijitalin her alanında, aynı hedefe birlikte.','home_intro_text'=>'Web tasarımından özel yazılıma, marka kimliğinden arama görünürlüğüne kadar birbirini tamamlayan çözümler. İşinizi dinliyor, ihtiyacınızı netleştiriyor ve her adımı sizinle birlikte planlıyoruz.','cta_title'=>'Bir sonraki başarı hikâyenizi birlikte yazalım.','instagram'=>'','linkedin'=>'','youtube'=>'','search_console'=>'','site_noindex'=>'0','hero_media'=>'','logo_media'=>'','og_media'=>'','footer_note'=>'Stratejiden tasarıma, koddan içeriğe. Markanız için birlikte üretiyoruz.','pseo_title'=>'{{sehir}} {{hizmet}} | {{marka}}','pseo_intro'=>'{{sehir}} hedefli {{hizmet}} projenizi iş hedefleriniz ve müşterilerinizin ihtiyaçları etrafında planlıyoruz. {{ozet}}','pseo_body'=>"{{icerik}}\n\n## Yerel ihtiyaçlar ve çalışma kapsamı\n{{baglam}}\n\n## Hangi işletmeler için?\n{{sektorler}}\n\n## Projeye nasıl başlıyoruz?\nÖnce mevcut durumunuzu, hedeflerinizi ve içerik kaynaklarınızı değerlendiriyoruz. Kapsam, sorumluluklar ve teslimat takvimi bu görüşmeden sonra netleştirilir. Çevrim içi çalışma mümkündür; bu sayfa ilgili şehirde fiziksel ofisimiz olduğu anlamına gelmez."];
}
function cms_ready(): bool {
    static $ready=null;
    if($ready!==null) return $ready;
    if(!installed()) return $ready=false;
    return $ready=(bool)query("SELECT setting_value FROM settings WHERE setting_key='_cms_version'")->fetchColumn();
}
function cms_migrate(): void {
    $pdo=db(); $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $end=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    foreach([
      'content_meta (entity VARCHAR(30) NOT NULL, record_id INTEGER NOT NULL, payload TEXT NOT NULL, PRIMARY KEY(entity,record_id))',
      "categories (id $id, name VARCHAR(150) NOT NULL, slug VARCHAR(150) NOT NULL UNIQUE, description TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0)",
      "articles (id $id, title VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE, excerpt TEXT NOT NULL, body TEXT NOT NULL, author VARCHAR(150) NOT NULL, category VARCHAR(100) NOT NULL, sources TEXT NOT NULL, image_id INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'draft', published_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL)",
      "faqs (id $id, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL, service_id INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0)",
      "media (id $id, filename VARCHAR(190) NOT NULL, original_name VARCHAR(190) NOT NULL, alt VARCHAR(255) NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, bytes INTEGER NOT NULL, created_at VARCHAR(30) NOT NULL)",
      "redirects (id $id, source VARCHAR(190) NOT NULL UNIQUE, destination VARCHAR(500) NOT NULL, created_at VARCHAR(30) NOT NULL)",
      "revisions (id $id, entity VARCHAR(30) NOT NULL, record_id INTEGER NOT NULL, payload TEXT NOT NULL, actor VARCHAR(190) NOT NULL, created_at VARCHAR(30) NOT NULL)",
      "audit_log (id $id, actor VARCHAR(190) NOT NULL, action VARCHAR(120) NOT NULL, detail TEXT NOT NULL, created_at VARCHAR(30) NOT NULL)",
      "navigation (id $id, label VARCHAR(100) NOT NULL, path VARCHAR(190) NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0)",
      "lead_notes (id $id, lead_id INTEGER NOT NULL, note TEXT NOT NULL, actor VARCHAR(190) NOT NULL, created_at VARCHAR(30) NOT NULL)"
    ] as $table) $pdo->exec('CREATE TABLE IF NOT EXISTS '.$table.$end);
    foreach(cms_defaults() as $key=>$value) if(!query('SELECT setting_key FROM settings WHERE setting_key=?',[$key])->fetchColumn()) query('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)',[$key,$value]);
    if(!query('SELECT id FROM categories LIMIT 1')->fetchColumn()) {
      foreach([['Web & Yazılım','web-yazilim','Markanızın dijital merkezini tasarlıyor, işinizi destekleyen yazılımlar geliştiriyoruz.',1],['Marka & Tasarım','marka-tasarim','Her temas noktasında aynı karakteri taşıyan bir görsel kimlik.',2],['Dijital Büyüme','dijital-buyume','Arama görünürlüğü, içerik ve dijital stratejiyi ortak bir plana bağlıyoruz.',3]] as $row) query('INSERT INTO categories (name,slug,description,sort_order) VALUES (?,?,?,?)',$row);
      foreach(records('services') as $service) {
        $cat=match($service['slug']){'marka-tasarim'=>2,'seo','dijital-strateji'=>3,default=>1};
        meta_save('services',(int)$service['id'],['category_id'=>$cat,'published'=>1,'seo_title'=>$service['name'].' | '.settings()['brand'],'seo_description'=>$service['summary'],'process'=>"Keşif ve kapsam\nStrateji ve tasarım\nGeliştirme ve kontrol\nYayın ve teslim",'audience'=>'Yeni bir dijital deneyim oluşturmak veya mevcut yapısını geliştirmek isteyen işletmeler.','faq'=>'','image_id'=>0,'noindex'=>0]);
      }
      foreach([['Projeler','/projeler',1],['Ajans','/hakkimizda',2],['İçgörüler','/blog',3],['İletişim','/iletisim',4]] as $row) query('INSERT INTO navigation (label,path,sort_order) VALUES (?,?,?)',$row);
      foreach([['Proje süreci nasıl başlıyor?','İlk görüşmede hedeflerinizi, mevcut durumunuzu ve beklentilerinizi birlikte ele alıyoruz. Ardından kapsam, teslimatlar ve çalışma takvimi netleşiyor.',0,1],['Fiyat ve teslim süresi nasıl belirleniyor?','Sayfa sayısı, içerik hazırlığı, tasarım kapsamı ve entegrasyonlar fiyatı ve süreyi etkiler. Teklif, ihtiyaçlarınız netleştikten sonra hazırlanır.',0,2],['İçerikleri kendimiz güncelleyebilir miyiz?','Yönetim panelinden hizmet, proje, blog ve SEO içeriklerini düzenleyebilirsiniz. Teslimde kullanım adımları ve erişimler paylaşılır.',0,3],['Farklı şehirlerdeki işletmelerle çalışıyor musunuz?','Görüşme ve değerlendirmeler çevrim içi yürütülebilir. Yerinde çalışma ihtiyacı ve kapsamı proje başlangıcında ayrıca kararlaştırılır.',0,4]] as $row) query('INSERT INTO faqs (question,answer,service_id,sort_order) VALUES (?,?,?,?)',$row);
    }
    setting_write('_cms_version','2');
    require_once __DIR__.'/geography.php'; geo_schema();
    require_once __DIR__.'/catalog.php'; catalog_seed();
    studio_migrate();
}
function setting_write(string $key,string $value): void {
    if(query('SELECT setting_key FROM settings WHERE setting_key=?',[$key])->fetchColumn()) query('UPDATE settings SET setting_value=? WHERE setting_key=?',[$value,$key]);
    else query('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)',[$key,$value]);
}
function cms_tables(): array { return ['categories','articles','faqs','media','redirects','revisions','audit_log','navigation','lead_notes','showcases','partners','testimonials','films']; }
function cms_records(string $table): array {
    if(!in_array($table,cms_tables(),true)) throw new InvalidArgumentException('Geçersiz içerik türü.');
    if(!cms_ready()) return [];
    $order=in_array($table,['categories','faqs','navigation','showcases','partners','testimonials'],true)?'sort_order, id':'id DESC';
    return query("SELECT * FROM $table ORDER BY $order")->fetchAll();
}
function cms_record(string $table,int $id): ?array { if(!in_array($table,cms_tables(),true))throw new InvalidArgumentException('Geçersiz tablo');return cms_ready()?(query("SELECT * FROM $table WHERE id=?",[$id])->fetch()?:null):null; }
function meta(string $entity,int $id): array {
    if(!cms_ready()) return [];
    global $metaCache;
    if(!isset($metaCache[$entity][$id])){ $raw=query('SELECT payload FROM content_meta WHERE entity=? AND record_id=?',[$entity,$id])->fetchColumn();$metaCache[$entity][$id]=$raw?(json_decode($raw,true)?:[]):[]; }
    return $metaCache[$entity][$id]??[];
}
function meta_save(string $entity,int $id,array $payload): void {
    global $metaCache; unset($metaCache[$entity][$id]);
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if(query('SELECT record_id FROM content_meta WHERE entity=? AND record_id=?',[$entity,$id])->fetchColumn()!==false) query('UPDATE content_meta SET payload=? WHERE entity=? AND record_id=?',[$json,$entity,$id]);
    else query('INSERT INTO content_meta (entity,record_id,payload) VALUES (?,?,?)',[$entity,$id,$json]);
}
function actor(): string { return admin()?(string)(query('SELECT email FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetchColumn()?:'Yönetici'):'Sistem'; }
function audit(string $action,string $detail=''): void { if(cms_ready()) query('INSERT INTO audit_log (actor,action,detail,created_at) VALUES (?,?,?,?)',[actor(),$action,$detail,gmdate('c')]); }
function revision(string $entity,int $id): void {
    if(!cms_ready() || !$id) return;
    $row=in_array($entity,['services','cities','projects','pages'],true)?record($entity,$id):cms_record($entity,$id);
    if($row) query('INSERT INTO revisions (entity,record_id,payload,actor,created_at) VALUES (?,?,?,?,?)',[$entity,$id,json_encode(['row'=>$row,'meta'=>meta($entity,$id)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),actor(),gmdate('c')]);
}
function public_services(): array { return array_values(array_filter(records('services'),fn($s)=>(meta('services',(int)$s['id'])['published']??1))); }
function category_services(int $category): array { return array_values(array_filter(public_services(),fn($s)=>(int)(meta('services',(int)$s['id'])['category_id']??0)===$category)); }
function published_articles(): array { return array_values(array_filter(cms_records('articles'),fn($a)=>$a['status']==='published' && strtotime($a['published_at'])<=time())); }
function media_url(int $id): string { $m=cms_record('media',$id); return $m?bundled_url($m['filename']):''; }
function media_image(int $id,string $class='',bool $eager=false): string {
    $m=cms_record('media',$id); if(!$m) return '';
    return '<img class="'.e($class).'" src="'.e(bundled_url($m['filename'])).'" alt="'.e($m['alt']).'" width="'.(int)$m['width'].'" height="'.(int)$m['height'].'" loading="'.($eager?'eager':'lazy').'" decoding="async"'.($eager?' fetchpriority="high"':'').'>';
}
function internal_path(string $value): bool { return preg_match('~^/(?!/)[a-zA-Z0-9/_?=&.%#-]*$~D',$value)===1 && !str_contains(rawurldecode($value),'..') && !str_starts_with(rawurldecode($value),'//') && !str_contains(rawurldecode($value),'\\') && !preg_match('/[\r\n]/',rawurldecode($value)); }
function prose(string $text): string {
    $parts=preg_split('/\n\s*\n/',str_replace('\\n',"\n",trim($text)))?:[]; $out='';
    foreach($parts as $part) {
      if(preg_match('/^## (.+)\n?([\s\S]*)$/',$part,$m)) {$out.='<h2>'.e($m[1]).'</h2>';if(trim($m[2])) $out.='<p>'.nl2br(e($m[2])).'</p>';}
      elseif(str_starts_with($part,'- ')) {$out.='<ul>';foreach(explode("\n",$part) as $line)$out.='<li>'.e(preg_replace('/^- /','',$line)).'</li>';$out.='</ul>';}
      else $out.='<p>'.nl2br(e($part)).'</p>';
    }
    return $out;
}
function seo_checks(array $row,array $extra=[],string $entity='pages'): array {
    $checks=[]; $title=$row['title']??$extra['seo_title']??$row['name']??''; $description=$row['description']??$extra['seo_description']??$row['excerpt']??'';
    $checks['Başlık mevcut']=mb_strlen(trim($title))>=5;
    $checks['Meta açıklama mevcut']=mb_strlen(trim($description))>=30;
    $checks['Ana içerik mevcut']=mb_strlen(trim($row['body']??''))>=200;
    $checks['İçerik incelemesi']=$entity!=='pages'||(bool)($row['reviewed']??false);
    if($entity==='pages') $checks['Sayfaya özel bilgi']=mb_strlen(trim($extra['unique_value']??''))>=100;
    if($entity==='pages' && cms_ready()) {
      $checks['Tekil başlık']=!query('SELECT id FROM pages WHERE title=? AND id<>? LIMIT 1',[$title,$row['id']??0])->fetchColumn();
    }
    return $checks;
}
