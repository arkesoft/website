<?php
declare(strict_types=1);
function contact_action(): void {
    check_csrf();
    if (!installed()) { flash('İletişim formu site kurulumu tamamlandıktan sonra kullanılabilir.','error'); redirect('/iletisim'); }
    if (!rate_limit('contact_attempt',25,3600)) { flash('Çok fazla talep gönderdiniz. Lütfen daha sonra tekrar deneyin.','error'); redirect('/iletisim'); }
    if (post('website') !== '') { flash('Talebiniz alındı.'); redirect('/iletisim'); }
    $name=post('name',120); $email=post('email',190); $message=post('message',5000);
    $errors=[];
    if(mb_strlen($name)<2)$errors['name']='Adınızı veya şirket adınızı en az 2 karakterle yazın.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors['email']='Geçerli bir e-posta adresi yazın; örneğin ad@sirket.com.';
    if(mb_strlen($message)<20)$errors['message']='Projenizi en az 20 karakterle anlatın.';
    if(!isset($_POST['consent']))$errors['consent']='Devam etmek için aydınlatma metnini okuduğunuzu belirtin.';
    if($errors){$_SESSION['contact_errors']=$errors;$_SESSION['contact_old']=['name'=>$name,'email'=>$email,'phone'=>post('phone',40),'message'=>$message,'service'=>post('service',150),'consent'=>isset($_POST['consent'])];redirect('/iletisim');}
    if (!rate_limit('contact',5,3600)) { flash('Çok fazla talep gönderdiniz. Lütfen daha sonra tekrar deneyin.','error'); redirect('/iletisim'); }
    query('INSERT INTO leads (name,email,phone,service,message,status,created_at) VALUES (?,?,?,?,?,?,?)',[$name,$email,post('phone',40),post('service',150),$message,'new',gmdate('c')]);
    unset($_SESSION['contact_old'],$_SESSION['contact_errors']); flash('Proje talebiniz kaydedildi. Teşekkürler!'); redirect('/iletisim');
}
function admin_action(): void {
    check_csrf(); $action=post('action',40);
    if ($action==='login') {
        if (!rate_limit('login',8,900)) { flash('Çok fazla giriş denemesi. 15 dakika sonra tekrar deneyin.','error'); redirect('/yonetim'); }
        $user=query('SELECT * FROM admins WHERE email=?',[mb_strtolower(post('email',190))])->fetch();
        $hash=$user['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (!password_verify(post('password',200),$hash) || !$user) { flash('E-posta veya şifre hatalı.','error'); redirect('/yonetim'); }
        session_regenerate_id(true); $_SESSION['admin_id']=(int)$user['id']; $_SESSION['password_stamp']=hash('sha256',$user['password']);$_SESSION['last_active']=time(); $_SESSION['csrf']=bin2hex(random_bytes(32)); redirect('/yonetim');
    }
    require_admin();
    if(function_exists('protect_action')) protect_action();
    if ($action==='logout') { $_SESSION=[]; session_regenerate_id(true); redirect('/yonetim'); }
    if ($action==='settings') {
        $updates=[];foreach(seed_settings() as $key=>$default) {
            if(!array_key_exists($key,$_POST)) continue;
            $value=post($key,in_array($key,['about','privacy'],true)?10000:1000);
            if ($key==='email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Geçerli bir iletişim e-postası girin.');
            if ($key==='whatsapp') $value=preg_replace('/\D/','',$value);
            $updates[$key]=$value;
        }
        foreach($updates as $key=>$value)query('UPDATE settings SET setting_value=? WHERE setting_key=?',[$value,$key]);
        flash('Site ayarları kaydedildi.'); redirect('/yonetim?tab=settings');
    }
    if ($action==='password') {
        $user=query('SELECT * FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetch();
        if (!password_verify(post('current_password',200),$user['password']) || strlen(post('new_password',200))<12 || strlen(post('new_password',200))>72) throw new InvalidArgumentException('Mevcut şifrenizi doğrulayın. Yeni şifre 12–72 bayt uzunluğunda olmalı.');
        $newHash=password_hash(post('new_password',200),PASSWORD_DEFAULT);query('UPDATE admins SET password=? WHERE id=?',[$newHash,$user['id']]);$_SESSION['password_stamp']=hash('sha256',$newHash);
        session_regenerate_id(true); flash('Şifreniz güncellendi.'); redirect('/yonetim?tab=settings');
    }
    if ($action==='save') {
        $table=post('table',20); $id=(int)post('id');
        $fields=match($table) {
            'services'=>['name','slug','eyebrow','summary','body','deliverables','sort_order'],
            'cities'=>['name','slug','context','sectors'],
            'projects'=>['name','category','description','style','url','is_demo','sort_order'],
            'pages'=>['title','description','heading','intro','body','faq_question','faq_answer','status','reviewed'],
            default=>throw new InvalidArgumentException('Geçersiz kayıt türü.')
        };
        $data=[]; foreach($fields as $field) $data[$field]=post($field,in_array($field,['body','deliverables','context','intro'],true)?20000:($field==='description'?1000:255));
        $old=$id?record($table,$id):null;
        if($id&&!$old)throw new InvalidArgumentException('Kayıt bulunamadı.');
        $extra=$id?meta($table,$id):[];
        if($table==='cities' && isset($_POST['location_level'])) {
            $level=post('location_level');$parent=(int)post('parent_id');
            if(!in_array($level,['province','district','neighborhood'],true))throw new InvalidArgumentException('Geçersiz lokasyon seviyesi.');
            if($level!=='province' && (!$parent||$parent===$id||!record('cities',$parent)))throw new InvalidArgumentException('Geçerli bir üst lokasyon seçin.');
            if($level!=='province') { $parentLevel=meta('cities',$parent)['level']??'province';if(($level==='district'&&$parentLevel!=='province')||($level==='neighborhood'&&$parentLevel!=='district'))throw new InvalidArgumentException('İlçe bir ile, mahalle bir ilçeye bağlanmalıdır.'); }
            $extra['level']=$level;$extra['parent_id']=$level==='province'?0:$parent;
        }
        foreach(['seo_title','seo_description','audience','process','unique_value','challenge','solution','result'] as $field) if(array_key_exists($field,$_POST)) $extra[$field]=post($field,10000);
        foreach(['category_id','image_id'] as $field) if(array_key_exists($field,$_POST)) $extra[$field]=(int)post($field);
        if(isset($_POST['meta_present'])) {$extra['noindex']=isset($_POST['noindex'])?1:0;$extra['published']=isset($_POST['published'])?1:0;}
        if($table==='services'&&isset($_POST['page_layout'])){if(!isset(service_layouts()[post('page_layout')]))throw new InvalidArgumentException('Geçerli bir hizmet şablonu seçin.');$extra['page_layout']=post('page_layout');}
        if($table==='projects'&&isset($_POST['project_layout'])){if(!in_array(post('project_layout'),['auto','editorial','product','product-ui'],true))throw new InvalidArgumentException('Geçerli bir proje şablonu seçin.');$extra['project_layout']=post('project_layout');}
        if(!empty($extra['image_id']) && !cms_record('media',(int)$extra['image_id'])) throw new InvalidArgumentException('Seçilen görsel bulunamadı.');
        if($table==='services'&&!empty($extra['category_id'])&&!cms_record('categories',(int)$extra['category_id']))throw new InvalidArgumentException('Seçilen kategori bulunamadı.');
        if (isset($data['sort_order'])) $data['sort_order']=(int)$data['sort_order'];
        if (array_key_exists('is_demo',$data)) $data['is_demo']=isset($_POST['is_demo'])?1:0;
        if (isset($data['name']) && mb_strlen($data['name'])<2) throw new InvalidArgumentException('Ad alanı en az iki karakter olmalı.');
        if (isset($data['slug'])) { $data['slug']=slugify($data['slug'] ?: $data['name']); if (!$data['slug'] || strlen($data['slug'])>120) throw new InvalidArgumentException('Geçerli ve kısa bir URL adı girin.'); }
        if ($table==='projects' && (!safe_web_url($data['url']) || !in_array($data['style'],['forma','morrow','orbit'],true))) throw new InvalidArgumentException('Geçerli bir proje bağlantısı ve görsel stili seçin.');
        if ($table==='pages') {
            if (!$id || !record('pages',$id)) throw new InvalidArgumentException('Sayfa bulunamadı.');
            $data['reviewed']=isset($_POST['reviewed'])?1:0;
            if (!in_array($data['status'],['draft','published'],true)) throw new InvalidArgumentException('Geçersiz yayın durumu.');
            if ($data['status']==='published' && ($issues=page_issues($data+['id'=>$id,'_unique_value'=>$extra['unique_value']??'']))) throw new InvalidArgumentException(implode(' ',$issues));
            $data['updated_at']=gmdate('c');
        }
        revision($table,$id);
        if ($id) {
            $sets=implode(',',array_map(fn($field)=>$field.'=?',array_keys($data)));
            query("UPDATE $table SET $sets WHERE id=?",[...array_values($data),$id]);
        } else {
            $cols=implode(',',array_keys($data)); $marks=implode(',',array_fill(0,count($data),'?'));
            query("INSERT INTO $table ($cols) VALUES ($marks)",array_values($data));
            $id=(int)db()->lastInsertId();
        }
        if(cms_ready()) {
            meta_save($table,$id,$extra);
            if($table==='services' && $old && $old['slug']!==$data['slug']) auto_redirect('/hizmetler/'.$old['slug'],'/hizmetler/'.$data['slug']);
            audit('İçerik kaydedildi',"$table #$id");
        }
        flash('İçerik kaydedildi.'); redirect('/yonetim?tab='.$table);
    }
    if ($action==='delete') {
        $table=post('table',20); $id=(int)post('id');
        if (!in_array($table,['services','cities','projects','pages','leads'],true)) throw new InvalidArgumentException('Geçersiz kayıt türü.');
        if (in_array($table,['services','cities'],true)) {
            $column=$table==='services'?'service_id':'city_id';
            if ((int)query("SELECT COUNT(*) FROM pages WHERE $column=?",[$id])->fetchColumn()>0) throw new InvalidArgumentException('Önce bu kayda bağlı SEO sayfalarını kaldırın.');
        }
        if($table==='cities')foreach(records('cities') as $child)if((int)(meta('cities',(int)$child['id'])['parent_id']??0)===$id)throw new InvalidArgumentException('Önce alt lokasyonları kaldırın veya başka bir üst lokasyona bağlayın.');
        if($table!=='leads')revision($table,$id);
        query("DELETE FROM $table WHERE id=?",[$id]);
        query('DELETE FROM content_meta WHERE entity=? AND record_id=?',[$table,$id]);
        if($table==='cities')query('DELETE FROM geo_links WHERE city_id=?',[$id]);
        if($table==='services')query('DELETE FROM faqs WHERE service_id=?',[$id]);
        if($table==='leads')query('DELETE FROM lead_notes WHERE lead_id=?',[$id]);
        audit('Kayıt silindi',"$table #$id"); flash('Kayıt silindi.'); redirect('/yonetim?tab='.$table);
    }
    if ($action==='generate') {
        $services=array_map('intval',is_array($_POST['services'] ?? null)?$_POST['services']:[]);
        $cities=array_map('intval',is_array($_POST['cities'] ?? null)?$_POST['cities']:[]);
        if (!$services || !$cities || count($services)*count($cities)>300) throw new InvalidArgumentException('En az bir hizmet ve şehir seçin. Tek işlemde en fazla 300 kombinasyon üretilebilir.');
        $count=0; $skip=0; db()->beginTransaction();
        try {
            foreach(records('services') as $s) if (in_array((int)$s['id'],$services,true)) foreach(records('cities') as $c) if(in_array((int)$c['id'],$cities,true)) {
                if (query('SELECT id FROM pages WHERE service_id=? AND city_id=?',[$s['id'],$c['id']])->fetchColumn()) { $skip++; continue; }
                $row=generate_page($s,$c); $cols=implode(',',array_keys($row)); $marks=implode(',',array_fill(0,count($row),'?'));
                query("INSERT INTO pages ($cols) VALUES ($marks)",array_values($row)); $count++;
            }
            db()->commit();
        } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
        audit('PSEO taslakları üretildi',"$count yeni / $skip korunan"); flash("$count taslak oluşturuldu. $skip mevcut sayfa korundu. Yayından önce içerikleri düzenleyip inceleme onayı verin."); redirect('/yonetim?tab=pages');
    }
    if ($action==='bulk') {
        $ids=array_slice(array_map('intval',is_array($_POST['ids']??null)?$_POST['ids']:[]),0,300); $status=post('status');
        if (!in_array($status,['published','draft'],true) || !$ids) throw new InvalidArgumentException('Sayfa ve yayın işlemi seçin.');
        $done=0; $skip=0;
        foreach($ids as $id) { $page=record('pages',$id); if (!$page || ($status==='published' && page_issues($page))) { $skip++; continue; } query('UPDATE pages SET status=?,updated_at=? WHERE id=?',[$status,gmdate('c'),$id]); $done++; }
        flash("$done sayfa güncellendi. $skip sayfa inceleme veya içerik eksiği nedeniyle atlandı."); redirect('/yonetim?tab=pages');
    }
    if ($action==='lead_status') {
        $status=post('status'); if (!in_array($status,['new','contacted','closed'],true)) throw new InvalidArgumentException('Geçersiz durum.');
        query('UPDATE leads SET status=? WHERE id=?',[$status,(int)post('id')]); flash('Talep durumu güncellendi.'); redirect('/yonetim?tab=leads');
    }
    throw new InvalidArgumentException('İşlem bulunamadı.');
}
