<?php
declare(strict_types=1);

function growth_selected_ids(string $field,string $table,int $max=24): array {
    $raw=$_POST[$field]??[];if(!is_array($raw)||count($raw)>$max)throw new InvalidArgumentException('Seçim sayısını kontrol edin.');
    $ids=array_values(array_unique(array_filter(array_map('intval',$raw),fn($v)=>$v>0)));
    foreach($ids as $id){$row=in_array($table,['services','projects'],true)?record($table,$id):cms_record($table,$id);if(!$row)throw new InvalidArgumentException('Seçilen içerik bulunamadı.');}
    return $ids;
}

function growth_content_extra(string $table,int $id): ?array {
    if(!isset($_POST['growth_extra']))return null;
    if(!in_array($table,['services','projects','articles','testimonials'],true))return null;
    $extra=[];
    if($table!=='services')$extra['service_ids']=growth_selected_ids('service_ids','services');
    if($table==='projects'){
        $extra['gallery_ids']=growth_selected_ids('gallery_ids','media');$extra['evidence_url']=post('evidence_url',500);
        if(!safe_web_url($extra['evidence_url']))throw new InvalidArgumentException('Sonuç kaynağı geçerli HTTP/HTTPS adresi olmalı.');
        $extra['publication_consent']=isset($_POST['publication_consent'])?1:0;
        if(!isset($_POST['is_demo'])&&!$extra['publication_consent'])throw new InvalidArgumentException('Gerçek müşteri işi için yayın iznini doğrulayın.');
    }
    if($table==='articles'){
        $extra['author_id']=(int)post('author_id');if($extra['author_id']&&!cms_record('authors',$extra['author_id']))throw new InvalidArgumentException('Yazar bulunamadı.');
        $extra['tags']=[];foreach(array_slice(explode(',',post('tags',1000)),0,12) as $label){$label=mb_substr(trim($label),0,100);$slug=slugify($label);if(!$slug)continue;if(str_starts_with($slug,'author-'))throw new InvalidArgumentException('author- ile başlayan etiketler ayrılmıştır.');$extra['tags'][$slug]=$label;}
    }
    if($table==='testimonials'){
        $extra['verified']=isset($_POST['verified'])?1:0;$extra['evidence_url']=post('evidence_url',500);
        if(!safe_web_url($extra['evidence_url']))throw new InvalidArgumentException('Yorum kaynağı geçerli HTTP/HTTPS adresi olmalı.');
    }
    if($table==='services')$extra['support_scope']=post('support_scope',5000);
    return $extra;
}

function growth_save_extra(string $table,int $id,?array $extra): void {
    if($extra===null)return;
    // Merge with core editor metadata written earlier in this same transaction.
    meta_save($table,$id,array_merge(meta($table,$id),$extra));
    if($table==='articles'&&growth_ready()){
        query('DELETE FROM article_tags WHERE article_id=?',[$id]);
        foreach($extra['tags']??[] as $slug=>$label)query('INSERT INTO article_tags (article_id,slug,label) VALUES (?,?,?)',[$id,$slug,$label]);
        if(!empty($extra['author_id']))query('INSERT INTO article_tags (article_id,slug,label) VALUES (?,?,?)',[$id,'author-'.$extra['author_id'],'']);
    }
}

function growth_multi_select(string $label,string $name,array $rows,array $selected): void {
    echo '<label>'.e($label).'<select name="'.e($name).'[]" multiple size="6">';
    foreach($rows as $row)echo '<option value="'.(int)$row['id'].'" '.(in_array((int)$row['id'],array_map('intval',$selected),true)?'selected':'').'>'.e($row['name']??$row['original_name']??$row['title']).'</option>';
    echo '</select><small class="field-hint">Birden çok seçim için Ctrl / Command tuşunu kullanabilirsiniz.</small></label>';
}

function growth_editor_fields(string $table,array $extra): void {
    if(!in_array($table,['services','projects','articles','testimonials'],true)||!growth_ready())return;
    echo '<input type="hidden" name="growth_extra" value="1"><section class="growth-editor-fields"><h2>İlişkiler ve güven bilgileri</h2>';
    if($table!=='services')growth_multi_select('İlgili hizmetler','service_ids',records('services'),$extra['service_ids']??[]);
    if($table==='projects'){
        growth_multi_select('Proje galerisi','gallery_ids',cms_records('media'),$extra['gallery_ids']??[]);
        ui_input('Doğrulanabilir sonuç / referans bağlantısı','evidence_url',$extra['evidence_url']??'','url');
        ui_check('Gerçek müşteri işini yayınlama izni var','publication_consent',(bool)($extra['publication_consent']??false));
    }
    if($table==='articles'){
        $authors=[0=>'Mevcut yazar adını kullan'];foreach(cms_records('authors') as $a)$authors[$a['id']]=$a['name'];
        ui_select('Yazar profili','author_id',$authors,$extra['author_id']??0);ui_input('Etiketler (virgülle ayırın)','tags',implode(', ',array_values($extra['tags']??[])));
    }
    if($table==='testimonials'){ui_check('Gerçek deneyime dayanan, yayın izni alınmış yorum','verified',(bool)($extra['verified']??false));ui_input('Yorumun kaynak bağlantısı','evidence_url',$extra['evidence_url']??'','url');}
    if($table==='services')ui_input('Teslim sonrası destek / kapsam','support_scope',$extra['support_scope']??'','textarea');
    echo '</section>';
}

function growth_admin_action(): bool {
    $action=post('action');if(!in_array($action,['identity_save','page_seo_save','author_save','notification_retry','growth_upgrade','schema_check'],true))return false;
    check_csrf();require_admin();
    if($action==='schema_check'){
        authorize_area('settings');
        $before=count(schema_history(db()));
        schema_update_if_needed();
        $applied=count(schema_history(db()))-$before;
        flash($applied>0?$applied.' veritabanı güncellemesi uygulandı. Veritabanı güncel.':'Veritabanı zaten güncel. Bekleyen güncelleme yok.');
        redirect('/yonetim?tab=health');
    }
    if($action==='growth_upgrade'){authorize_area('settings');growth_migrate();flash('Görünürlük ve iletişim altyapısı güncellendi.');redirect('/yonetim');}
    if($action==='identity_save'){
        authorize_area('identity');$values=[];
        foreach(['email','phone','phone_secondary','whatsapp','address',...array_keys(growth_defaults())] as $key){
            if($key==='measurement_enabled'){$values[$key]=isset($_POST[$key])?'1':'0';continue;}
            if($key==='mail_enabled'){$value=post($key,10);if(!in_array($value,['auto','1','0',''],true))throw new InvalidArgumentException('Geçerli bildirim tercihi seçin.');$values[$key]=$value===''?'0':$value;continue;}
            $value=post($key,in_array($key,['support_scope','contact_note'],true)?5000:1000);
            if(in_array($key,['email','notification_email'],true)&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Geçerli e-posta adresi girin.');
            if(in_array($key,['phone','phone_secondary','whatsapp'],true)&&$value!==''){$digits=preg_replace('/\D/','',$value);if(strlen($digits)===10&&str_starts_with($digits,'5'))$digits='90'.$digits;if(strlen($digits)===11&&str_starts_with($digits,'0'))$digits='90'.substr($digits,1);if(strlen($digits)<10||strlen($digits)>15)throw new InvalidArgumentException('Geçerli telefon numarası girin.');$value=$key==='whatsapp'?$digits:'+'.$digits;}
            if($key==='maps_url'&&!safe_web_url($value))throw new InvalidArgumentException('Geçerli harita bağlantısı girin.');
            if($key==='business_kind'&&!in_array($value,['Organization','ProfessionalService'],true))throw new InvalidArgumentException('İşletme türünü seçin.');
            if($key==='business_country'&&!preg_match('/^[A-Z]{2}$/D',$value))throw new InvalidArgumentException('İki harfli ülke kodu girin.');
            if(in_array($key,['business_lat','business_lng'],true)&&$value!==''&&(!is_numeric($value)||abs((float)$value)>($key==='business_lat'?90:180)))throw new InvalidArgumentException('Geçerli koordinat girin.');
            $values[$key]=$value;
        }
        if(($values['business_lat']==='')!==($values['business_lng']===''))throw new InvalidArgumentException('Enlem ve boylam birlikte girilmelidir.');
        growth_transaction(function()use($values):void{foreach($values as $key=>$value)setting_write($key,$value);audit('İşletme ve iletişim bilgileri güncellendi');});
        flash('İşletme bilgileri kaydedildi.');redirect('/yonetim?tab=identity');
    }
    if($action==='page_seo_save'){
        authorize_area('page-seo');$key=post('page_key');if(!isset(growth_seo_catalog()[$key]))throw new InvalidArgumentException('Geçerli sayfa seçin.');
        $row=['title'=>post('title',190),'description'=>post('description',500),'image_id'=>(int)post('image_id')];
        if(mb_strlen($row['title'])<3||mb_strlen($row['description'])<20)throw new InvalidArgumentException('Başlık ve sayfaya özgü açıklama girin.');
        if($row['image_id']&&!cms_record('media',$row['image_id']))throw new InvalidArgumentException('Görsel bulunamadı.');
        setting_write('page_seo_'.$key,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));audit('Sayfa SEO bilgileri kaydedildi',$key);flash('SEO bilgileri kaydedildi.');redirect('/yonetim?tab=page-seo&page_key='.$key);
    }
    if($action==='author_save'){
        authorize_area('authors');$id=(int)post('id');$row=[];foreach(['name','slug','kind','role','bio','profile_url','status'] as $key)$row[$key]=post($key,$key==='bio'?10000:($key==='profile_url'?500:190));$row['slug']=slugify($row['slug']?:$row['name']);$row['image_id']=(int)post('image_id');
        if(!$row['name']||!$row['slug']||strlen($row['slug'])>160||!in_array($row['kind'],['Person','Organization'],true)||!in_array($row['status'],['draft','published'],true)||!safe_web_url($row['profile_url']))throw new InvalidArgumentException('Yazar adı, URL, tür ve profil bağlantısını kontrol edin.');
        if($row['image_id']&&!cms_record('media',$row['image_id']))throw new InvalidArgumentException('Yazar görseli bulunamadı.');
        if($row['status']==='published'&&mb_strlen($row['bio'])<30)throw new InvalidArgumentException('Yayındaki profil için en az 30 karakterlik gerçek tanıtım ekleyin.');
        $old=$id?cms_record('authors',$id):null;if($id&&!$old)throw new InvalidArgumentException('Yazar bulunamadı.');
        growth_transaction(function()use($id,$row,$old):void{
            if($id){query('UPDATE authors SET '.implode(',',array_map(fn($key)=>$key.'=?',array_keys($row))).' WHERE id=?',[...array_values($row),$id]);if($old['slug']!==$row['slug'])auto_redirect('/yazar/'.$old['slug'],'/yazar/'.$row['slug']);}
            else query('INSERT INTO authors ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));audit('Yazar profili kaydedildi',$row['name']);
        });flash('Yazar profili kaydedildi.');redirect('/yonetim?tab=authors');
    }
    authorize_area('notifications');$id=(int)post('id');
    query("UPDATE notification_queue SET status='pending',available_at=?,attempts=0,last_error='' WHERE id=? AND status='failed'",[time(),$id]);
    flash('Başarısız bildirim tekrar sıraya alındı. Zamanlanmış görev göndermeyi deneyecek.');redirect('/yonetim?tab=notifications');
}

function growth_admin_view(string $tab): void {
    if(!growth_ready()){
        echo '<section class="panel"><h2>Altyapı güncellemesi</h2><p>Mevcut içerikleri koruyarak yeni modüllerin tablolarını oluşturun.</p>';
        if(can_manage('settings')){echo '<form method="post">';action_fields('growth_upgrade');echo '<button class="button dark">Altyapıyı güncelle</button></form>';}
        echo '</section>';return;
    }
    $s=settings();
    if($tab==='identity'){
        echo '<form method="post" class="panel admin-form">';action_fields('identity_save');echo '<h2>İşletme ve iletişim</h2>';
        foreach(['email'=>'İletişim e-postası','phone'=>'Telefon','phone_secondary'=>'İkinci telefon (isteğe bağlı)','whatsapp'=>'WhatsApp','legal_name'=>'İşletmenin resmî adı','address'=>'Müşteri kabul edilen açık adres','business_city'=>'İl / şehir','business_postcode'=>'Posta kodu','business_country'=>'Ülke kodu','maps_url'=>'Google Haritalar bağlantısı','business_lat'=>'Enlem (isteğe bağlı)','business_lng'=>'Boylam (isteğe bağlı)','business_hours'=>'Çalışma saatleri','contact_note'=>'İletişim sonrası açıklama / gerçek dönüş süresi','support_scope'=>'Genel teslim sonrası destek kapsamı'] as $key=>$label)ui_input($label,$key,$s[$key]??'',in_array($key,['address','business_hours','contact_note','support_scope'],true)?'textarea':($key==='email'?'email':($key==='maps_url'?'url':'text')),false,$key==='business_hours'?'Örnek: Mo-Fr 09:00-18:00; her zaman aralığı ayrı satır.':'');
        ui_select('İşletme modeli','business_kind',['Organization'=>'Kurum / çevrim içi çalışma','ProfessionalService'=>'Müşteri kabul edilen fiziksel hizmet işletmesi'],$s['business_kind']);
        echo '<h2>Bildirim ve ölçüm</h2>';ui_input('Yeni talep bildirim alıcısı','notification_email',$s['notification_email'],'email',false,'Boşsa iletişim e-postası kullanılır.');
        ui_select('Talep e-posta bildirimleri','mail_enabled',['auto'=>'Bilgiler tamamlanınca otomatik gönder','0'=>'Gönderimi duraklat'],$s['mail_enabled']==='0'?'0':'auto');
        echo '<p><strong>'.e(growth_mail_status()).'</strong></p><p class="help">Yeni talep kaydedildiğinde bildirim otomatik denenir. Eksik bilgiler tamamlandığında bekleyen bildirimler site veya panel açıldıkça işlenir. Geçici hatalar yeniden denenir; talepler panelde korunur. Zamanlanmış görev zorunlu değildir.</p>';
        echo '<p class="help">Gönderici .env içindeki BRIEF_FROM veya config.php içindeki mail_from alanından alınır. SMTP hesabı .env dosyasında tanımlanır. BRIEF_TO doluysa yukarıdaki bildirim alıcısının yerine kullanılır; boşsa paneldeki alıcı geçerlidir. Posta sunucusunun kabulü, gelen kutusuna teslim edildiğini kanıtlamaz.</p>';
        echo '<p>Posta yöntemi: '.e(($GLOBALS['config']['mail_transport']??'mail')==='smtp'?'SMTP (TLS)':'PHP mail').'</p>';
        if($issues=mail_configuration_issues($GLOBALS['config']))echo '<p class="notice error">E-posta gönderimi için: '.e(implode(' ',$issues)).'</p>';
        else echo '<p class="help">Posta yapılandırma alanları tamam. Bağlantı ve teslimat henüz doğrulanmış sayılmaz.</p>';
        ui_check('Kimliksiz, toplu iletişim ve performans ölçümünü etkinleştir','measurement_enabled',$s['measurement_enabled']==='1');
        echo '<p class="help">Ölçüm mesaj içeriği, e-posta veya telefon kaydetmez. Gizlilik metninizde ölçümü açıklayın. DNT ve Global Privacy Control tercihlerine uyulur.</p><button class="button dark">Kaydet</button></form>';return;
    }
    if($tab==='page-seo'){
        $catalog=growth_seo_catalog();$key=get_text('page_key')?:'home';if(!isset($catalog[$key]))$key='home';$row=growth_page_seo($key,$s);
        echo '<nav class="design-page-tabs" aria-label="SEO sayfası">';foreach($catalog as $name=>$page)echo '<a href="/yonetim?tab=page-seo&amp;page_key='.$name.'" '.($name===$key?'aria-current="page"':'').'>'.e($page[0]).'</a>';echo '</nav><form method="post" class="panel admin-form">';action_fields('page_seo_save',['page_key'=>$key]);
        ui_input('SEO başlığı','title',$row['title'],'text',true);ui_input('Sayfaya özel açıklama','description',$row['description'],'textarea',true);media_select('image_id',$row['image_id']);
        echo '<p class="help">Paylaşım görseli seçilmezse sayfanın kapağı veya genel paylaşım görseli kullanılır.</p><button class="button dark">SEO bilgilerini kaydet</button></form>';return;
    }
    if($tab==='authors'){
        echo '<section class="panel"><h2>Yazarlar ve ekip</h2><a href="/yonetim?tab=authors&amp;new=1">Yeni profil ekle</a>';
        foreach(cms_records('authors') as $author)echo '<p><a href="/yonetim?tab=authors&amp;edit='.(int)$author['id'].'">'.e($author['name']).'</a> · '.e($author['status']).'</p>';echo '</section>';
        $item=isset($_GET['edit'])?cms_record('authors',(int)$_GET['edit']):null;
        if($item||isset($_GET['new'])){echo '<form method="post" class="panel admin-form">';action_fields('author_save',['id'=>$item['id']??0]);
            foreach(['name'=>'İsim / kurum adı','slug'=>'Profil URL adı','role'=>'Görev / uzmanlık','bio'=>'Gerçek tanıtım ve deneyim','profile_url'=>'Resmî profil bağlantısı'] as $key=>$label)ui_input($label,$key,$item[$key]??'',$key==='bio'?'textarea':($key==='profile_url'?'url':'text'),in_array($key,['name','bio'],true));
            ui_select('Profil türü','kind',['Person'=>'Kişi','Organization'=>'Kurum'],$item['kind']??'Person');media_select('image_id',(int)($item['image_id']??0));ui_select('Yayın durumu','status',['draft'=>'Taslak','published'=>'Yayında'],$item['status']??'draft');echo '<button class="button dark">Profili kaydet</button></form>';}return;
    }
    if($tab==='health'){
        $history=schema_history(db());$latest=$history?end($history):null;
        echo '<section class="panel"><h2>Veritabanı güncellemeleri</h2><p>Veritabanı güncel. Yeni sürüm dosyaları yüklendiğinde gerekli güncellemeler otomatik uygulanır. Yeniden kurulum gerekmez.</p>';
        if($latest)echo '<p>Veritabanı sürümü: '.(int)$latest['version'].' · Son güncelleme: '.e($latest['applied_at']).'</p>';
        if(can_manage('settings')){
            echo '<form method="post">';action_fields('schema_check');echo '<button class="button dark">Güncellemeleri kontrol et</button></form><p class="help">Yüklediğiniz sürümde bekleyen veritabanı adımları varsa uygulanır; tamamlanan adımlar tekrar çalıştırılmaz.</p>';
        }
        echo '</section>';
        echo '<section class="panel"><h2>Yayın hazırlığı</h2><p>Yerel ortamda alan adı kontrolü geçmez. Bu liste sıralama puanı değildir.</p>';
        foreach(growth_health_checks() as $check)echo '<div class="growth-check"><strong>'.($check['ok']?'✓ ':'! ').e($check['label']).'</strong><p>'.e($check['hint']).'</p></div>';
        echo '<p>HTTP taraması için: <code>php tools/site-audit.php https://alanadiniz.com</code></p></section>';return;
    }
    if($tab==='notifications'){
        echo '<section class="panel"><h2>Talep bildirimleri</h2><p>'.e(growth_mail_status()).'. Yeni talepler otomatik işlenir; bekleyen bildirimler site veya panel ziyaretlerinde yeniden denenir. Posta sunucusunun kabulü gelen kutusuna teslim kanıtı değildir.</p><div class="table-scroll"><table><thead><tr><th>Talep</th><th>Durum</th><th>Deneme</th><th>Son hata</th><th>İşlem</th></tr></thead><tbody>';
        foreach(query('SELECT * FROM notification_queue ORDER BY id DESC LIMIT 100')->fetchAll() as $row){echo '<tr><td>#'.(int)$row['lead_id'].'</td><td>'.e(['pending'=>'Bekliyor','sending'=>'Gönderiliyor','accepted'=>'Posta sunucusu kabul etti','failed'=>'Başarısız','cancelled'=>'İptal'][$row['status']]??$row['status']).'</td><td>'.(int)$row['attempts'].'</td><td>'.e($row['last_error']).'</td><td>';if($row['status']==='failed'){echo '<form method="post">';action_fields('notification_retry',['id'=>$row['id']]);echo '<button>Tekrar sıraya al</button></form>';}echo '</td></tr>';}
        echo '</tbody></table></div></section>';return;
    }
    if($tab==='measurement'){
        if(!is_file(PUBLIC_ROOT.'/assets/web-vitals.js'))echo '<div class="notice">Hız ölçüm kütüphanesi henüz kurulmadı. İletişim tıklamaları ölçülebilir; Core Web Vitals için internet erişimi olan ortamda <code>node tools/install-vitals.mjs</code> çalıştırılmalı.</div>';
        $since=gmdate('Y-m-d',time()-29*86400);echo '<section class="panel"><h2>Son 30 gün: iletişim ve performans</h2><p>'.($s['measurement_enabled']==='1'?'Ölçüm açık.':'Ölçüm kapalı; İşletme & iletişim bölümünden açabilirsiniz.').' Tıklama, gerçekleşmiş görüşme veya satış değildir.</p><div class="table-scroll"><table><thead><tr><th>Sayfa</th><th>Olay</th><th>Adet</th></tr></thead><tbody>';
        foreach(query("SELECT path,event,SUM(hits) AS total FROM measurement_daily WHERE day>=? AND event NOT IN ('LCP','INP','CLS') GROUP BY path,event ORDER BY total DESC LIMIT 100",[$since])->fetchAll() as $row)echo '<tr><td>'.e($row['path']).'</td><td>'.e(['phone_click'=>'Telefon tıklaması','whatsapp_click'=>'WhatsApp tıklaması','quote_click'=>'Görüşme bağlantısı','map_click'=>'Harita tıklaması','lead_saved'=>'Kaydedilen talep'][$row['event']]??$row['event']).'</td><td>'.(int)$row['total'].'</td></tr>';
        echo '</tbody></table></div><h3>Gerçek kullanıcı performansı</h3><p>75. yüzdelik yaklaşık değerleri; süreler 50 ms, CLS 0,01 aralıklarında gruplanır. Az sayıda örnek genel sonucu temsil etmeyebilir.</p><div class="table-scroll"><table><thead><tr><th>Cihaz</th><th>Ölçüm</th><th>Örnek</th><th>p75</th><th>Hedef</th></tr></thead><tbody>';
        foreach(['mobile'=>'Mobil','desktop'=>'Masaüstü'] as $device=>$label)foreach(['LCP'=>2500,'INP'=>200,'CLS'=>0.1] as $metric=>$target){$rows=query('SELECT value_bucket,SUM(hits) AS hits FROM measurement_daily WHERE day>=? AND device=? AND event=? GROUP BY value_bucket',[$since,$device,$metric])->fetchAll();$value=growth_percentile($rows);if($metric==='CLS'&&$value!==null)$value/=100;echo '<tr><td>'.$label.'</td><td>'.$metric.'</td><td>'.array_sum(array_column($rows,'hits')).'</td><td>'.($value===null?'Henüz ölçüm yok':e($value).($metric==='CLS'?'':' ms')).'</td><td>≤ '.$target.($metric==='CLS'?'':' ms').'</td></tr>';}
        echo '</tbody></table></div></section>';return;
    }
}
