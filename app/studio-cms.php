<?php
declare(strict_types=1);
function studio_defaults(): array { return ['hero_film'=>'0','showreel_film'=>'0','demo_content'=>'1','home_work_title'=>'Bir fikir. Bir dünya.','home_work_text'=>'Farklı sektörler için hazırladığımız konsept projeler. Tasarıma ve yazılıma nasıl yaklaştığımızı yakından görün.','home_partners_title'=>'Farklı işler. Aynı özen.','home_partners_text'=>'Örnek marka seçkisi · Aşağıdaki markalar bu demo için oluşturulmuştur.','home_quote_title'=>'İyi işin arkasında iyi iletişim var.','home_quote_text'=>'Örnek müşteri senaryoları · Gerçek müşteri yorumu değildir.','home_about_title'=>'Önce sizi dinliyoruz. Sonra işe koyuluyoruz.','home_about_text'=>'Bir web sitesi, bir uygulama, belki de işinizi kolaylaştıracak küçük bir entegrasyon. İhtiyacı doğru tarif ediyor, tasarım ve yazılımı birlikte ele alıyoruz.','home_about_media'=>'0','home_cta_note'=>'Kısaca anlatın. Birlikte nereden başlayacağımızı bulalım.']; }
function studio_migrate(): void {
 $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';$end=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
 foreach([
 "showcases (id $id, name VARCHAR(150) NOT NULL, title VARCHAR(190) NOT NULL, description TEXT NOT NULL, link VARCHAR(190) NOT NULL, label VARCHAR(100) NOT NULL, image_id INTEGER NOT NULL DEFAULT 0, theme VARCHAR(30) NOT NULL, visual VARCHAR(30) NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published')",
 "partners (id $id, name VARCHAR(100) NOT NULL, description VARCHAR(255) NOT NULL, image_id INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published')",
 "testimonials (id $id, name VARCHAR(100) NOT NULL, role VARCHAR(150) NOT NULL, quote TEXT NOT NULL, image_id INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published')",
 "films (id $id, name VARCHAR(150) NOT NULL, filename VARCHAR(190) NOT NULL, mime VARCHAR(50) NOT NULL, description TEXT NOT NULL, poster_id INTEGER NOT NULL DEFAULT 0, bytes INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(30) NOT NULL)"
 ] as $table)db()->exec('CREATE TABLE IF NOT EXISTS '.$table.$end);
 foreach(studio_defaults() as $key=>$value)if(!query('SELECT setting_key FROM settings WHERE setting_key=?',[$key])->fetchColumn())setting_write($key,$value);
 require_once __DIR__.'/studio-seed.php';studio_seed();
}
function studio_schema(string $table): array {
 return match($table){
 'showcases'=>['name'=>'Yönetimde görünen bölüm adı','title'=>'Başlık','description'=>'Açıklama','link'=>'Site içi bağlantı','label'=>'Bağlantı yazısı','image_id'=>'Ana görsel','theme'=>'Renk teması','visual'=>'Görsel düzeni','sort_order'=>'Sıralama','status'=>'Görünürlük'],
 'partners'=>['name'=>'Marka / proje adı','description'=>'Alt açıklama','image_id'=>'Logo (isteğe bağlı)','sort_order'=>'Sıralama','status'=>'Görünürlük'],
 'testimonials'=>['name'=>'İsim','role'=>'Rol / açıklama','quote'=>'Metin','image_id'=>'Görsel (isteğe bağlı)','sort_order'=>'Sıralama','status'=>'Görünürlük'],
 default=>throw new InvalidArgumentException('Geçersiz bölüm.')};
}
function bundled_url(string $filename,string $folder='uploads'): string {
 if(str_starts_with($filename,'asset:')){$name=substr($filename,6);return preg_match('/^[a-zA-Z0-9_-]+\.(webp|png|jpg|svg|webm|mp4)$/D',$name)?'/assets/'.$name:'';}
 return preg_match('/^[a-f0-9]+\.(webp|png|jpg|webm|mp4)$/D',$filename)?'/'.$folder.'/'.$filename:'';
}
function film_url(int $id): string {$film=cms_record('films',$id);return $film?bundled_url($film['filename']):'';}
function studio_items(string $table): array {return array_values(array_filter(cms_records($table),fn($r)=>$r['status']==='published'));}
function film_select(string $name,int $value=0): void {$options=[0=>'Video yok'];foreach(cms_records('films') as $film)$options[$film['id']]=$film['name'];ui_select('Video / film kütüphanesi',$name,$options,$value);}
function studio_action(): bool {
 $action=post('action');if(!in_array($action,['studio_settings','upload_video','film_save','film_delete'],true))return false;
 check_csrf();authorize_area($action==='studio_settings'?'branding':'films');
 if($action==='studio_settings'){
    $updates=[];foreach(studio_defaults() as $key=>$default){$value=post($key,3000);if($key==='demo_content')$value=isset($_POST[$key])?'1':'0';if(in_array($key,['hero_film','showreel_film'],true)&&$value&& !cms_record('films',(int)$value))throw new InvalidArgumentException('Video bulunamadı.');if($key==='home_about_media'&&$value&&!cms_record('media',(int)$value))throw new InvalidArgumentException('Görsel bulunamadı.');$updates[$key]=$value;}
   foreach($updates as $key=>$value)setting_write($key,$value);
   audit('Vitrin ayarları güncellendi');flash('Vitrin ayarları kaydedildi.');redirect('/yonetim?tab=studio');
 }
 if($action==='upload_video'){
   $f=$_FILES['video']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>30*1024*1024)throw new InvalidArgumentException('En fazla 30 MB MP4 veya WebM yükleyin. PHP yükleme sınırını da kontrol edin.');
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$head=file_get_contents($f['tmp_name'],false,null,0,32);
   $webm=in_array($mime,['video/webm','application/octet-stream'],true)&&substr($head,0,4)==="\x1a\x45\xdf\xa3";
   $mp4=in_array($mime,['video/mp4','application/mp4'],true)&&substr($head,4,4)==='ftyp';
   if(!$webm&&!$mp4)throw new InvalidArgumentException('Geçerli MP4 veya WebM video seçin.');$ext=$webm?'webm':'mp4';$name=bin2hex(random_bytes(16)).'.'.$ext;
   if(!move_uploaded_file($f['tmp_name'],PUBLIC_ROOT.'/uploads/'.$name))throw new RuntimeException('Video kaydedilemedi.');
   query('INSERT INTO films (name,filename,mime,description,poster_id,bytes,created_at) VALUES (?,?,?,?,?,?,?)',[mb_substr(post('name')?:pathinfo($f['name'],PATHINFO_FILENAME),0,150),$name,'video/'.$ext,post('description',3000),0,(int)$f['size'],gmdate('c')]);
   audit('Video yüklendi',$name);flash('Video yüklendi. Vitrin ayarlarından seçebilirsiniz.');redirect('/yonetim?tab=films');
 }
 $id=(int)post('id');$film=cms_record('films',$id);if(!$film)throw new InvalidArgumentException('Video bulunamadı.');
 if($action==='film_save'){$poster=(int)post('poster_id');if($poster&&!cms_record('media',$poster))throw new InvalidArgumentException('Kapak görseli bulunamadı.');query('UPDATE films SET name=?,description=?,poster_id=? WHERE id=?',[post('name',150),post('description',3000),$poster,$id]);audit('Video bilgileri güncellendi',(string)$id);flash('Video bilgileri kaydedildi.');redirect('/yonetim?tab=films');}
 if($action==='film_delete'){foreach(['hero_film','showreel_film'] as $key)if((int)settings()[$key]===$id)throw new InvalidArgumentException('Video vitrinde kullanılıyor. Önce vitrin ayarından kaldırın.');if(preg_match('/^[a-f0-9]+\.(webm|mp4)$/D',$film['filename'])&&is_file(PUBLIC_ROOT.'/uploads/'.$film['filename']))unlink(PUBLIC_ROOT.'/uploads/'.$film['filename']);query('DELETE FROM films WHERE id=?',[$id]);audit('Video silindi',(string)$id);flash('Video silindi.');redirect('/yonetim?tab=films');}
 return true;
}
function studio_admin(string $tab): void {
 $s=settings();if($tab==='studio'): ?><form method="post" class="panel admin-form"><?php action_fields('studio_settings'); ?><h2>Açılış & tanıtım videosu</h2><?php film_select('hero_film',(int)$s['hero_film']);film_select('showreel_film',(int)$s['showreel_film']); ?><p class="help">Açılış videosu sessiz oynar; ziyaretçi durdurabilir. Hareket azaltma tercihi veya veri tasarrufu varsa kapak görseli gösterilir. Tanıtım videosu düğmeyle açılır.</p><h2>Vitrin metinleri</h2><?php foreach(['home_work_title'=>'Projeler başlığı','home_work_text'=>'Projeler açıklaması','home_partners_title'=>'Markalar başlığı','home_partners_text'=>'Markalar açıklaması','home_quote_title'=>'Yorumlar başlığı','home_quote_text'=>'Yorumlar açıklaması','home_about_title'=>'Ajans başlığı','home_about_text'=>'Ajans açıklaması','home_cta_note'=>'İletişim çağrısı açıklaması'] as $key=>$label)ui_input($label,$key,$s[$key],str_ends_with($key,'title')?'text':'textarea');media_select('home_about_media',(int)$s['home_about_media']);ui_check('Konsept markalar, örnek yorumlar ve örnek iletişim bilgileri için demo açıklamasını göster','demo_content',$s['demo_content']==='1'); ?><p class="help">Demo açıklamasını ancak örnek içerikleri kendi doğrulanmış bilgilerinizle değiştirdikten sonra kaldırın. İçerik blokları, markalar ve yorumlar ayrı menülerden düzenlenir.</p><button class="button dark">Vitrini kaydet</button></form>
 <?php else: ?><form method="post" enctype="multipart/form-data" class="panel admin-form"><?php action_fields('upload_video'); ?><h2>Video yükle</h2><?php ui_input('Video adı','name','','text',true);ui_input('Açıklama / erişilebilir özet','description','','textarea'); ?><label>MP4 / WebM · en fazla 30 MB<input type="file" name="video" accept="video/mp4,video/webm" required></label><button class="button dark">Videoyu yükle ↑</button><p class="help">Videolar yeniden kodlanmaz. Web için H.264 MP4 veya VP8/VP9 WebM kullanın. Büyük dosyalarda PHP upload_max_filesize ve post_max_size sınırlarını artırın.</p></form><div class="media-grid"><?php foreach(cms_records('films') as $film): ?><article class="panel"><video controls playsinline preload="metadata" class="library-video" src="<?=e(film_url((int)$film['id']))?>" <?php if($film['poster_id']): ?>poster="<?=e(media_url((int)$film['poster_id']))?>"<?php endif; ?>></video><form method="post"><?php action_fields('film_save',['id'=>$film['id']]);ui_input('Ad','name',$film['name']);ui_input('Açıklama','description',$film['description'],'textarea');media_select('poster_id',(int)$film['poster_id']); ?><button class="text-link">Bilgileri kaydet</button></form><form method="post" data-confirm="Video silinsin mi?"><?php action_fields('film_delete',['id'=>$film['id']]); ?><button class="link-danger">Sil</button></form></article><?php endforeach; ?></div><?php endif;
}
