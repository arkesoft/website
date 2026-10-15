<?php
declare(strict_types=1);
function admin_role(): string {
    if(!admin()) return 'guest';
    return meta('admins',(int)$_SESSION['admin_id'])['role']??((int)$_SESSION['admin_id']===(int)query('SELECT MIN(id) FROM admins')->fetchColumn()?'owner':'editor');
}
function can_manage(string $area): bool {
    return admin_role()==='owner' || (admin_role()==='editor' && in_array($area,['dashboard','locations-import','services','projects','cities','pages','generate','articles','categories','faqs','media','films','showcases','partners','testimonials','revisions','seo','authors'],true));
}
function authorize_area(string $area): void { require_admin(); if(!can_manage($area)) { http_response_code(403); exit('Bu işlem için yetkiniz yok.'); } }
function protect_action(): void {
    $action=post('action');
    if(in_array($action,['login','logout','password'],true)) return;
    $area=match($action){'save','delete'=>post('table'),'generate','bulk'=>'pages','settings'=>'settings','lead_status'=>'leads',default=>'dashboard'};
    authorize_area($area);
}
function cms_schema(string $table): array {
    if(in_array($table,['showcases','partners','testimonials'],true))return studio_schema($table);
    return match($table) {
      'categories'=>['name'=>'Kategori adı','slug'=>'URL adı','description'=>'Açıklama','sort_order'=>'Sıralama'],
      'articles'=>['title'=>'Yazı başlığı','slug'=>'URL adı','excerpt'=>'Özet / meta açıklaması','body'=>'İçerik','author'=>'Yazar / editör','category'=>'Konu','sources'=>'Kaynaklar (her satıra bir https adresi)','image_id'=>'Kapak görseli','status'=>'Yayın durumu','published_at'=>'Yayın tarihi'],
      'faqs'=>['question'=>'Soru','answer'=>'Yanıt','service_id'=>'İlgili hizmet','sort_order'=>'Sıralama'],
      'redirects'=>['source'=>'Eski yol (ör. /eski-hizmet)','destination'=>'Yeni yol (ör. /hizmetler/web-tasarim)'],
      'navigation'=>['label'=>'Menü adı','path'=>'Site içi yol','sort_order'=>'Sıralama'],
      default=>throw new InvalidArgumentException('Geçersiz kayıt türü.')
    };
}
function csv_safe(mixed $value): string { $v=(string)$value;return preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v; }
function cms_action(): bool {
    $action=post('action');
    $supported=['cms_save','cms_delete','upload','media_save','media_delete','templates','branding','seo_settings','export_csv','export_backup','import_cities','restore_revision','user_save','user_delete','lead_note','cms_upgrade'];
    if(!in_array($action,$supported,true)) return false;
    check_csrf(); require_admin();
    if($action==='cms_upgrade') {authorize_area('settings');cms_migrate();flash('Yönetim sistemi güncellendi.');redirect('/yonetim');}
    if($action==='cms_save') {
      $table=post('table'); authorize_area($table); $schema=cms_schema($table);$id=(int)post('id');$row=[];
      foreach($schema as $field=>$label) $row[$field]=post($field,in_array($field,['body','sources','answer','description','quote'],true)?40000:($field==='excerpt'?1000:190));
      if(isset($row['slug'])) {$row['slug']=slugify($row['slug']?:($row['title']??$row['name']));if(!$row['slug']||strlen($row['slug'])>160)throw new InvalidArgumentException('Geçerli bir URL adı girin.');}
      foreach(['sort_order','service_id','image_id'] as $field) if(isset($row[$field])) $row[$field]=(int)$row[$field];
      if(in_array($table,['showcases','partners','testimonials'],true)){
        if(!in_array($row['status'],['draft','published'],true)||!$row['name'])throw new InvalidArgumentException('İsim ve görünürlük gerekli.');
        if($row['image_id']&&!cms_record('media',$row['image_id']))throw new InvalidArgumentException('Görsel bulunamadı.');
        if($table==='showcases'&&(!internal_path($row['link'])||!in_array($row['theme'],['red','cream','yellow','orange','dark'],true)||!in_array($row['visual'],['web','brand','mobile','social'],true)))throw new InvalidArgumentException('Bağlantı, tema ve görsel düzenini kontrol edin.');
      }
      if($table==='articles') {
        if(!in_array($row['status'],['draft','published'],true))throw new InvalidArgumentException('Geçersiz durum.');
        if(!$row['title']||!$row['author'])throw new InvalidArgumentException('Başlık ve yazar gereklidir.');
        if($row['status']==='published' && (mb_strlen($row['body'])<200 || mb_strlen($row['excerpt'])<30))throw new InvalidArgumentException('Yazı içeriği veya özeti eksik.');
        foreach(lines($row['sources']) as $source)if(!safe_web_url($source))throw new InvalidArgumentException('Kaynaklar geçerli HTTP/HTTPS adresleri olmalı.');
        $date=strtotime($row['published_at']);if(!$date)throw new InvalidArgumentException('Geçerli yayın tarihi girin.');
        $row['published_at']=gmdate('c',$date);$row['updated_at']=gmdate('c');
        if($row['image_id'] && !cms_record('media',$row['image_id']))throw new InvalidArgumentException('Görsel bulunamadı.');
      }
      if($table==='faqs' && (!$row['question'] || !$row['answer'] || ($row['service_id'] && !record('services',$row['service_id']))))throw new InvalidArgumentException('Soru, yanıt ve ilgili hizmeti kontrol edin.');
      if($table==='categories' && !$row['name'])throw new InvalidArgumentException('Kategori adı gereklidir.');
      if($table==='navigation' && (!$row['label'] || !internal_path($row['path'])))throw new InvalidArgumentException('Menü adı ve geçerli site içi yol girin.');
      if($table==='redirects') {
        if(!internal_path($row['source'])||!internal_path($row['destination'])||$row['source']===$row['destination']||str_contains($row['source'],'?')||preg_match('~^/(yonetim|kurulum|assets|uploads|app|storage|sitemap|robots)~',$row['source']))throw new InvalidArgumentException('Yönlendirme yolları geçersiz veya ayrılmış bir adres kullanılıyor.');
        $cursor=$row['destination'];$seen=[$row['source']];
        for($i=0;$i<50;$i++){if(in_array($cursor,$seen,true))throw new InvalidArgumentException('Yönlendirme döngüsü oluşuyor.');$seen[]=$cursor;$next=query('SELECT destination FROM redirects WHERE source=? AND id<>?',[$cursor,$id])->fetchColumn();if(!$next)break;$cursor=$next;}
        $row['created_at']=gmdate('c');
      }
      $growthExtra=growth_content_extra($table,$id);
      $old=$id?cms_record($table,$id):null;db()->beginTransaction();try {revision($table,$id);
      if($id){if(!$old)throw new InvalidArgumentException('Kayıt bulunamadı.');$sets=implode(',',array_map(fn($f)=>$f.'=?',array_keys($row)));query("UPDATE $table SET $sets WHERE id=?",[...array_values($row),$id]);}
      else{$cols=implode(',',array_keys($row));$marks=implode(',',array_fill(0,count($row),'?'));query("INSERT INTO $table ($cols) VALUES ($marks)",array_values($row));$id=(int)db()->lastInsertId();}
      if($table==='articles') {
        $extra=meta('articles',$id);$extra['noindex']=isset($_POST['noindex'])?1:0;meta_save('articles',$id,$extra);
        if($old && $old['slug']!==$row['slug'])auto_redirect('/blog/'.$old['slug'],'/blog/'.$row['slug']);
      }
      growth_save_extra($table,$id,$growthExtra);audit('İçerik kaydedildi',"$table #$id");db()->commit();
      }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
      flash('İçerik kaydedildi.');redirect('/yonetim?tab='.$table);
    }
    if($action==='cms_delete') {
      $table=post('table');authorize_area($table);cms_schema($table);$id=(int)post('id');
      if($table==='categories')foreach(records('services') as $s)if((int)(meta('services',(int)$s['id'])['category_id']??0)===$id)throw new InvalidArgumentException('Bu kategoriye bağlı hizmetler var. Önce kategorilerini değiştirin.');
      revision($table,$id);query("DELETE FROM $table WHERE id=?",[$id]);audit('İçerik silindi',"$table #$id");flash('Kayıt silindi.');redirect('/yonetim?tab='.$table);
    }
    if($action==='upload') {
      if(!function_exists('imagewebp')||!class_exists('finfo'))throw new InvalidArgumentException('Görsel yükleme için PHP GD (WebP) ve fileinfo uzantılarını etkinleştirin.');
      authorize_area('media');$file=$_FILES['image']??null;
      if(!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024)throw new InvalidArgumentException('En fazla 5 MB JPEG, PNG veya WebP yükleyin.');
      $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
      if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new InvalidArgumentException('Dosya türü desteklenmiyor. SVG ve çalıştırılabilir dosyalar alınmaz.');
      $size=getimagesize($file['tmp_name']);if(!$size || $size[0]*$size[1]>25000000)throw new InvalidArgumentException('Görsel en fazla 25 megapiksel olabilir.');
      $image=imagecreatefromstring(file_get_contents($file['tmp_name']));if(!$image)throw new InvalidArgumentException('Görsel okunamadı.');
      $ratio=min(1,2400/max($size[0],$size[1]));$width=max(1,(int)round($size[0]*$ratio));$height=max(1,(int)round($size[1]*$ratio));
      $canvas=imagecreatetruecolor($width,$height);imagealphablending($canvas,false);imagesavealpha($canvas,true);imagecopyresampled($canvas,$image,0,0,0,0,$width,$height,$size[0],$size[1]);
      $name=bin2hex(random_bytes(16)).'.webp';$target=PUBLIC_ROOT.'/uploads/'.$name;
      if(!is_dir(PUBLIC_ROOT.'/uploads'))mkdir(PUBLIC_ROOT.'/uploads',0755,true);
      if(!imagewebp($canvas,$target,84))throw new RuntimeException('Görsel yazılamadı.');imagedestroy($image);imagedestroy($canvas);
      query('INSERT INTO media (filename,original_name,alt,width,height,bytes,created_at) VALUES (?,?,?,?,?,?,?)',[$name,mb_substr(basename($file['name']),0,190),post('alt',255),$width,$height,filesize($target),gmdate('c')]);responsive_media_generate((int)db()->lastInsertId());audit('Görsel yüklendi',$name);flash('Görsel optimize edilip WebP olarak kaydedildi.');redirect('/yonetim?tab=media');
    }
    if($action==='media_save'){authorize_area('media');query('UPDATE media SET alt=? WHERE id=?',[post('alt',255),(int)post('id')]);audit('Görsel alt metni güncellendi',post('id'));flash('Alt metin kaydedildi.');redirect('/yonetim?tab=media');}
    if($action==='media_delete') {
      authorize_area('media');$id=(int)post('id');$m=cms_record('media',$id);if(!$m)throw new InvalidArgumentException('Görsel bulunamadı.');
      if(growth_media_referenced($id))throw new InvalidArgumentException('Görsel bir galeri, yazar veya SEO alanında kullanılıyor.');
      foreach(['hero_media','logo_media','og_media','home_about_media'] as $key)if((int)(settings()[$key]??0)===$id)throw new InvalidArgumentException('Görsel site ayarlarında kullanılıyor.');
      foreach(array_keys(page_design_catalog()) as $key)if((int)page_design((string)$key)['image_id']===$id)throw new InvalidArgumentException('Görsel bir sayfa tasarımında kullanılıyor. Önce sayfadan kaldırın.');
      foreach(['showcases','partners','testimonials','films'] as $source)foreach(cms_records($source) as $r)if((int)($r['image_id']??$r['poster_id']??0)===$id)throw new InvalidArgumentException('Görsel bir vitrin içeriğinde veya videoda kullanılıyor.');
      foreach(cms_records('articles') as $a)if((int)$a['image_id']===$id)throw new InvalidArgumentException('Görsel bir yazıda kullanılıyor.');
      foreach(query('SELECT payload FROM content_meta')->fetchAll() as $mrow)if((int)((json_decode($mrow['payload'],true)?:[])['image_id']??0)===$id)throw new InvalidArgumentException('Görsel içerikte kullanılıyor.');
      if(preg_match('/^[a-f0-9]+\.webp$/',$m['filename'])){ $file=PUBLIC_ROOT.'/uploads/'.$m['filename'];if(is_file($file))unlink($file); }
      responsive_media_delete($id);query('DELETE FROM media WHERE id=?',[$id]);audit('Görsel silindi',$m['original_name']);flash('Görsel silindi.');redirect('/yonetim?tab=media');
    }
    if(in_array($action,['templates','branding','seo_settings'],true)) {
      authorize_area($action==='templates'?'generate':'settings');
      $keys=match($action){'templates'=>['pseo_title','pseo_intro','pseo_body'],'seo_settings'=>['search_console','site_noindex','og_media'],default=>['hero_kicker','hero_line1','hero_line2','hero_line3','hero_text','home_intro','home_intro_text','cta_title','footer_note','hero_media','logo_media','instagram','linkedin','youtube']};
      $values=[];foreach($keys as $key){$v=post($key,20000);if($key==='site_noindex')$v=isset($_POST[$key])?'1':'0';if(in_array($key,['instagram','linkedin','youtube'],true) && !safe_web_url($v))throw new InvalidArgumentException('Sosyal bağlantılar geçerli HTTP/HTTPS adresleri olmalı.');if($key==='search_console' && $v!=='' && !preg_match('/^[a-zA-Z0-9_-]+$/',$v))throw new InvalidArgumentException('Yalnızca doğrulama kodunu girin; HTML etiketi yapıştırmayın.');$values[$key]=$v;}
      if($action==='templates')foreach($values as $v){preg_match_all('/{{[^}]+}}/',$v,$matches);foreach($matches[0] as $var)if(!in_array($var,['{{sehir}}','{{hizmet}}','{{marka}}','{{ozet}}','{{icerik}}','{{baglam}}','{{sektorler}}'],true))throw new InvalidArgumentException('Bilinmeyen değişken: '.$var);if(!$v)throw new InvalidArgumentException('Şablon boş bırakılamaz.');}
      foreach($values as $key=>$v)setting_write($key,$v);audit('Ayar güncellendi',$action);flash('Ayarlar kaydedildi.');redirect('/yonetim?tab='.($action==='templates'?'generate':($action==='branding'?'branding':'seo')));
    }
    if($action==='export_csv') {
      $table=post('table');if(!in_array($table,['cities','pages','leads'],true))throw new InvalidArgumentException('Geçersiz dışa aktarma.');authorize_area($table);$rows=records($table);audit('CSV dışa aktarıldı',$table);
      header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="arkesoft-'.$table.'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");if($rows){fputcsv($out,array_keys($rows[0]),',','"','');foreach($rows as $row)fputcsv($out,array_map('csv_safe',array_values($row)),',','"','');}fclose($out);exit;
    }
    if($action==='export_backup') {
      authorize_area('backup');$data=['version'=>2,'exported_at'=>gmdate('c'),'site'=>origin(),'tables'=>[]];
      foreach(['settings','services','cities','projects','pages','leads','content_meta',...cms_tables()] as $table)$data['tables'][$table]=query("SELECT * FROM $table")->fetchAll();
      audit('İçerik yedeği indirildi');header('Content-Type: application/json; charset=UTF-8');header('Content-Disposition: attachment; filename="arkesoft-content-'.date('Ymd-His').'.json"');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);exit;
    }
    if($action==='import_cities') {
      authorize_area('cities');$file=$_FILES['csv']??null;if(!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>1024*1024)throw new InvalidArgumentException('En fazla 1 MB CSV yükleyin.');
      $f=fopen($file['tmp_name'],'r');$head=fgetcsv($f,0,',','"','');if(!$head)throw new InvalidArgumentException('CSV boş.');$head[0]=preg_replace('/^\xEF\xBB\xBF/','',$head[0]);
      if(array_slice($head,0,4)!==['name','slug','context','sectors'])throw new InvalidArgumentException('Başlık sırası name,slug,context,sectors olmalı. UTF-8 ve virgülle ayrılmış CSV kullanın.');
      $rows=[];while(($row=fgetcsv($f,0,',','"',''))!==false){if(count($row)<4)throw new InvalidArgumentException('Eksik CSV alanı.');if(count($rows)>=300)throw new InvalidArgumentException('Tek seferde en fazla 300 satır.');$slug=slugify($row[1]?:$row[0]);if(!$slug||strlen($slug)>120||mb_strlen($row[0])>100||mb_strlen($row[2])<30)throw new InvalidArgumentException('Şehir adı, URL veya bağlam eksik.');$rows[]=[$row[0],$slug,mb_substr($row[2],0,20000),mb_substr($row[3],0,1000)];}fclose($f);
      $count=0;db()->beginTransaction();try{foreach($rows as $row){if(query('SELECT id FROM cities WHERE slug=?',[$row[1]])->fetchColumn())continue;query('INSERT INTO cities (name,slug,context,sectors) VALUES (?,?,?,?)',$row);$count++;}db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
      audit('Şehir CSV içe aktarıldı',(string)$count);flash("$count yeni şehir eklendi. Mevcut şehirler korundu.");redirect('/yonetim?tab=cities');
    }
    if($action==='restore_revision') {
      authorize_area('revisions');$rev=cms_record('revisions',(int)post('id'));if(!$rev)throw new InvalidArgumentException('Sürüm bulunamadı.');$table=$rev['entity'];authorize_area($table);
      if(!in_array($table,['services','cities','projects','pages','articles','faqs','categories','navigation','redirects','showcases','partners','testimonials'],true))throw new InvalidArgumentException('Bu içerik geri alınamaz.');
      $data=json_decode($rev['payload'],true,512,JSON_THROW_ON_ERROR);$row=$data['row'];$id=(int)$row['id'];unset($row['id']);
      if(!query("SELECT id FROM $table WHERE id=?",[$id])->fetchColumn())throw new InvalidArgumentException('Silinen kayıt geri yüklenemez. Bu ekran mevcut kaydın eski sürümüne döner.');
      if(isset($row['status']))$row['status']='draft';if(isset($row['reviewed']))$row['reviewed']=0;if(isset($row['updated_at']))$row['updated_at']=gmdate('c');
      $current=query("SELECT * FROM $table WHERE id=?",[$id])->fetch();
      db()->beginTransaction();try {revision($table,$id);$sets=implode(',',array_map(fn($f)=>$f.'=?',array_keys($row)));query("UPDATE $table SET $sets WHERE id=?",[...array_values($row),$id]);meta_save($table,$id,$data['meta']??[]);
      if(in_array($table,['services','articles'],true)&&$current['slug']!==$row['slug']){$prefix=$table==='services'?'/hizmetler/':'/blog/';auto_redirect($prefix.$current['slug'],$prefix.$row['slug']);}
      growth_save_extra($table,$id,$data['meta']??[]);db()->commit();}catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
      audit('Sürüm geri alındı',"$table #$id");flash('Önceki içerik geri alındı. Yayın durumunu kontrol edin.');redirect('/yonetim?tab='.$table.'&edit='.$id);
    }
    if($action==='user_save') {
      authorize_area('users');$id=(int)post('id');$email=mb_strtolower(post('email',190));$role=post('role');$password=post('password',200);
      if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['owner','editor'],true)||(!$id&&strlen($password)<12)||($password!==''&&(strlen($password)<12||strlen($password)>72)))throw new InvalidArgumentException('E-posta, rol ve en az 12 karakterlik şifreyi kontrol edin.');
      if($id===(int)$_SESSION['admin_id']&&$role!=='owner')throw new InvalidArgumentException('Kendi yönetici yetkinizi kaldıramazsınız.');
      if($id){if(!query('SELECT id FROM admins WHERE id=?',[$id])->fetchColumn())throw new InvalidArgumentException('Kullanıcı bulunamadı.');query('UPDATE admins SET email=? WHERE id=?',[$email,$id]);if($password)query('UPDATE admins SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);}
      else{query('INSERT INTO admins (email,password) VALUES (?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();}
      meta_save('admins',$id,['role'=>$role]);audit('Kullanıcı kaydedildi',$email.' / '.$role);flash('Kullanıcı kaydedildi.');redirect('/yonetim?tab=users');
    }
    if($action==='user_delete'){authorize_area('users');$id=(int)post('id');if($id===(int)$_SESSION['admin_id'])throw new InvalidArgumentException('Kendi hesabınızı silemezsiniz.');query('DELETE FROM admins WHERE id=?',[$id]);query("DELETE FROM content_meta WHERE entity='admins' AND record_id=?",[$id]);audit('Kullanıcı silindi',(string)$id);flash('Kullanıcı kaldırıldı.');redirect('/yonetim?tab=users');}
    if($action==='lead_note'){authorize_area('leads');$id=(int)post('id');$note=post('note',5000);if(!$note||!record('leads',$id))throw new InvalidArgumentException('Talep veya not eksik.');query('INSERT INTO lead_notes (lead_id,note,actor,created_at) VALUES (?,?,?,?)',[$id,$note,actor(),gmdate('c')]);audit('Talebe not eklendi',(string)$id);flash('Not eklendi.');redirect('/yonetim?tab=leads');}
    return true;
}
function auto_redirect(string $source,string $destination): void {
    if($source===$destination)return;
    query('DELETE FROM redirects WHERE source=?',[$destination]);
    query('UPDATE redirects SET destination=? WHERE destination=?',[$destination,$source]);
    if(query('SELECT id FROM redirects WHERE source=?',[$source])->fetchColumn())query('UPDATE redirects SET destination=? WHERE source=?',[$destination,$source]);
    else query('INSERT INTO redirects (source,destination,created_at) VALUES (?,?,?)',[$source,$destination,gmdate('c')]);
}
