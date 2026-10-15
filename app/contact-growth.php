<?php
declare(strict_types=1);

function growth_context_service(): ?array {
    $data=$GLOBALS['growthPageData']??[];
    if(isset($data['service']))return $data['service'];
    if(isset($data['page']['service_id']))return record('services',(int)$data['page']['service_id']);
    $slug=get_text('hizmet');$service=$slug?by_slug('services',$slug):null;
    return $service&&(meta('services',(int)$service['id'])['published']??1)?$service:null;
}

function growth_source_path(string $value): string {
    $path=parse_url($value,PHP_URL_PATH);return is_string($path)&&strlen($path)<=250&&internal_path($path)?$path:'/';
}

function growth_contact_url(?array $service=null): string {
    $service??=growth_context_service();$params=['kaynak'=>growth_source_path($_SERVER['REQUEST_URI']??'/')];
    if($service)$params['hizmet']=$service['slug'];return '/iletisim?'.http_build_query($params);
}

function growth_whatsapp_url(): string {
    $number=preg_replace('/\D/','',settings()['whatsapp']);if(!$number)return '';
    $service=growth_context_service();$message=$service?'Merhaba, '.$service['name'].' hizmetiniz hakkında görüşmek istiyorum.':'Merhaba, projem hakkında görüşmek istiyorum.';
    if(!empty($GLOBALS['growthPageData']['project']['name']))$message='Merhaba, '.$GLOBALS['growthPageData']['project']['name'].' projenizi inceledim. Benzer bir çalışma hakkında görüşmek istiyorum.';
    return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
}

function growth_contact_bar(): void {
    $s=settings();echo '<nav class="growth-contact-bar" aria-label="Hızlı iletişim">';
    if($s['phone'])echo '<a href="tel:'.e(preg_replace('/[^+0-9]/','',$s['phone'])).'" data-contact-event="phone_click">Ara</a>';
    if($wa=growth_whatsapp_url())echo '<a href="'.e($wa).'" target="_blank" rel="noopener noreferrer" data-contact-event="whatsapp_click">WhatsApp <span class="sr-only">(yeni sekme)</span></a>';
    echo '<a href="'.e(growth_contact_url()).'" data-contact-event="quote_click">Görüşme iste ↗</a></nav>';
}

function growth_location_card(): void {
    $s=settings();if(!$s['legal_name']&&!$s['address']&&!$s['business_hours']&&!$s['maps_url']&&!$s['phone_secondary'])return;
    echo '<section class="growth-location"><h3>İletişim ve çalışma saatleri</h3>';
    if($s['legal_name'])echo '<p>'.e($s['legal_name']).'</p>';
    if($s['phone_secondary'])echo '<p>Diğer telefon: <a href="tel:'.e(preg_replace('/[^+0-9]/','',$s['phone_secondary'])).'" data-contact-event="phone_click">'.e($s['phone_secondary']).'</a></p>';
    if($s['address'])echo '<p data-office-address>'.nl2br(e($s['address'].($s['business_city']?' / '.$s['business_city']:''))).'</p><button type="button" class="outline-pill" data-copy-address>Adresi kopyala</button><span role="status" data-copy-status></span>';
    if($s['business_hours'])echo '<p>'.nl2br(e(growth_hours_label($s['business_hours']))).'</p>';
    if($s['maps_url'])echo '<a class="text-link" href="'.e($s['maps_url']).'" target="_blank" rel="noopener noreferrer" data-contact-event="map_click">Haritalarda aç / yol tarifi al ↗ <span class="sr-only">(yeni sekme)</span></a>';
    // An explicit link keeps third-party maps from loading before the visitor chooses them.
    if($s['business_lat']!==''&&$s['business_lng']!=='')echo '<div class="growth-map" data-map-lat="'.e($s['business_lat']).'" data-map-lng="'.e($s['business_lng']).'"><button type="button" class="outline-pill" data-load-map>Google haritasını göster</button><p>Haritayı açtığınızda Google ile bağlantı kurulur.</p></div>';
    echo '</section>';
}

function growth_hours_label(string $hours): string {return strtr($hours,['Mo'=>'Pazartesi','Tu'=>'Salı','We'=>'Çarşamba','Th'=>'Perşembe','Fr'=>'Cuma','Sa'=>'Cumartesi','Su'=>'Pazar']);}

function growth_form_token(): string {
    $tokens=$_SESSION['lead_tokens']??[];$tokens=array_filter($tokens,fn($time)=>$time>time()-7200);
    if(count($tokens)>12)$tokens=array_slice($tokens,-12,null,true);
    $token=bin2hex(random_bytes(32));$tokens[$token]=time();$_SESSION['lead_tokens']=$tokens;return $token;
}

function growth_discovery_summary(): string {
    $parts=[];foreach(['durum'=>'Başlangıç','hedef'=>'Hedef','zaman'=>'Zamanlama'] as $key=>$label)if($value=get_text($key,150))$parts[]=$label.': '.$value;
    return implode("\n",$parts);
}

function growth_contact_fields(): void {
    $old=$_SESSION['contact_context']??[];unset($_SESSION['contact_context']);$summary=$old['discovery_summary']??growth_discovery_summary();$source=$old['source_path']??(get_text('kaynak',250)?:'/iletisim');
    echo '<input type="hidden" name="short_form" value="1"><input type="hidden" name="submission_id" value="'.e(growth_form_token()).'">';
    echo '<input type="hidden" name="source_path" value="'.e(growth_source_path($source)).'" data-source-path>';
    foreach(['utm_source','utm_medium','utm_campaign'] as $field)echo '<input type="hidden" name="'.$field.'" value="'.e($old[$field]??get_text($field,100)).'" data-attribution="'.$field.'">';
    if($summary)echo '<div class="growth-summary"><h3>Görüşme özetiniz</h3><p>'.nl2br(e($summary)).'</p><input type="hidden" name="discovery_summary" value="'.e($summary).'"></div>';
}

function growth_contact_action(): void {
    check_csrf();if(!installed()){flash('İletişim formu site kurulumu tamamlandıktan sonra kullanılabilir.','error');redirect('/iletisim');}
    if(!rate_limit('contact_attempt',25,3600)){flash('Çok fazla talep gönderdiniz. Lütfen daha sonra tekrar deneyin.','error');redirect('/iletisim');}
    if(post('website')!==''){flash('Talebiniz alındı.');redirect('/iletisim');}
    $name=post('name',120);$email=post('email',190);$phone=post('phone',40);$message=post('message',5000);$short=post('short_form')==='1';$errors=[];
    if(mb_strlen($name)<2)$errors['name']='Adınızı veya şirket adınızı en az 2 karakterle yazın.';
    if((!$short||$email!=='')&&!filter_var($email,FILTER_VALIDATE_EMAIL))$errors['email']='Geçerli bir e-posta adresi yazın.';
    if($phone!==''&&(!preg_match('/^\+?[0-9 ()-]{10,30}$/D',$phone)||strlen(preg_replace('/\D/','',$phone))<10||strlen(preg_replace('/\D/','',$phone))>15))$errors['phone']='Geçerli bir telefon numarası yazın.';
    if($short&&$email===''&&strlen(preg_replace('/\D/','',$phone))<10)$errors['email']='Size ulaşabilmemiz için e-posta veya telefon bilgisi bırakın.';
    if((!$short||$message!=='')&&mb_strlen($message)<20)$errors['message']='Projenizi en az 20 karakterle anlatın veya kısa formda bu alanı boş bırakın.';
    if(!isset($_POST['consent']))$errors['consent']='Devam etmek için aydınlatma metnini okuduğunuzu belirtin.';
    $token=post('submission_id',64);
    if($token!==''&&(!preg_match('/^[a-f0-9]{64}$/D',$token)||($_SESSION['lead_tokens'][$token]??0)<time()-7200))$errors['name']='Formun süresi doldu. Sayfayı yenileyerek tekrar deneyin.';
    $old=['name'=>$name,'email'=>$email,'phone'=>$phone,'message'=>$message,'service'=>post('service',150),'consent'=>isset($_POST['consent'])];
    if($errors){$_SESSION['contact_context']=[];foreach(['discovery_summary','source_path','utm_source','utm_medium','utm_campaign'] as $key)$_SESSION['contact_context'][$key]=post($key,$key==='discovery_summary'?1000:250);$_SESSION['contact_errors']=$errors;$_SESSION['contact_old']=$old;redirect('/iletisim');}
    $token=$token?:hash('sha256',csrf().'|'.$name.'|'.$email.'|'.$phone.'|'.$message.'|'.date('Y-m-d'));
    if(growth_ready()&&query('SELECT lead_id FROM lead_submissions WHERE token=?',[$token])->fetchColumn()){flash('Talebiniz daha önce kaydedildi. Teşekkürler!');redirect('/iletisim');}
    if(!rate_limit('contact',5,3600)){flash('Çok fazla talep gönderdiniz. Lütfen daha sonra tekrar deneyin.','error');redirect('/iletisim');}
    $summary=post('discovery_summary',1000);if($summary)$message=trim($summary."\n\n".$message);if($message==='')$message='Kısa görüşme talebi';
    try{growth_transaction(function()use($token,$name,$email,$phone,$message):void{
        if(growth_ready())query('INSERT INTO lead_submissions (token,lead_id,created_at) VALUES (?,0,?)',[$token,time()]);
        query('INSERT INTO leads (name,email,phone,service,message,status,created_at) VALUES (?,?,?,?,?,?,?)',[$name,$email,$phone,post('service',150),$message,'new',gmdate('c')]);$id=(int)db()->lastInsertId();
        if(growth_ready()){
            query('UPDATE lead_submissions SET lead_id=? WHERE token=?',[$id,$token]);
            $extra=['source_path'=>growth_source_path(post('source_path',250)?:'/iletisim'),'consent_at'=>gmdate('c')];
            foreach(['utm_source','utm_medium','utm_campaign'] as $field)$extra[$field]=post($field,100);
            meta_save('leads',$id,$extra);$s=settings();$recipient=$GLOBALS['config']['mail_to']??($s['notification_email']?:$s['email']);
            if(filter_var($recipient,FILTER_VALIDATE_EMAIL)){
                query('INSERT INTO notification_queue (lead_id,recipient,available_at,created_at) VALUES (?,?,?,?)',[$id,$recipient,time(),time()]);
                $GLOBALS['growthMailLeadId']=$id;
            }
            if($s['measurement_enabled']==='1')growth_measure('lead_saved',$extra['source_path'],'server',0);
        }
    });}catch(PDOException $error){
        if(!growth_ready()||!query('SELECT lead_id FROM lead_submissions WHERE token=?',[$token])->fetchColumn())throw $error;
    }
    unset($_SESSION['contact_old'],$_SESSION['contact_errors']);flash('Proje talebiniz kaydedildi. Teşekkürler!');redirect('/iletisim');
}

function growth_mail_enabled(): bool {
    $mode=settings()['mail_enabled']??'auto';
    return $mode==='1'||($mode==='auto'&&!mail_configuration_issues($GLOBALS['config']));
}

function growth_mail_status(): string {
    if((settings()['mail_enabled']??'auto')==='0')return 'Gönderim duraklatıldı';
    return mail_configuration_issues($GLOBALS['config'])?'Posta bilgileri bekleniyor':'Otomatik gönderim hazır';
}

function growth_mail_lock_path(): string {
    return growth_cache_dir().'/mail-dispatch.lock';
}

/** Bounded, request-driven fallback: cron remains optional for traffic-free retries. */
function growth_mail_dispatch(?int $leadId=null): void {
    if(!growth_ready()||!growth_mail_enabled()||mail_configuration_issues($GLOBALS['config']))return;
    $file=growth_mail_lock_path();$dir=dirname($file);
    if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))return;
    $lock=@fopen($file,'c+');if(!$lock)return;
    try{
        if(!flock($lock,LOCK_EX|LOCK_NB))return;
        $last=(int)stream_get_contents($lock);
        if($leadId===null&&$last>time()-60)return;
        $due=query("SELECT id FROM notification_queue WHERE ((status='pending' AND available_at<=?) OR (status='sending' AND locked_at<?))".($leadId!==null?' AND lead_id=?':'').' LIMIT 1',[$now=time(),$now-900,...($leadId!==null?[$leadId]:[])])->fetchColumn();
        if(!$due)return;
        rewind($lock);ftruncate($lock,0);fwrite($lock,(string)time());fflush($lock);
        growth_mail_worker(1,$leadId);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function growth_mail_schedule(): void {
    if(PHP_SAPI==='cli'||!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true))return;
    register_shutdown_function(static function():void{
        if(http_response_code()>=400||in_array(error_get_last()['type']??0,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true))return;
        try{
            if(!growth_ready()||!growth_mail_enabled()||mail_configuration_issues($GLOBALS['config']))return;
            // Release the visitor's session before SMTP; FastCGI can finish the response too.
            if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
            ignore_user_abort(true);
            if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();
            growth_mail_dispatch($GLOBALS['growthMailLeadId']??null);
        }catch(Throwable $error){error_log('Otomatik bildirim işlenemedi. Bildirim kuyruğunu ve veritabanı erişimini kontrol edin.');}
    });
}

function growth_mail_worker(int $limit=10,?int $leadId=null): array {
    global $config;$stats=['accepted'=>0,'failed'=>0,'disabled'=>false];
    if(!growth_ready()||!growth_mail_enabled()){$stats['disabled']=true;return $stats;}
    $from=$config['mail_from']??'';
    if($issues=mail_configuration_issues($config))throw new RuntimeException(implode(' ',$issues));
    $now=time();query("UPDATE notification_queue SET status='pending',locked_at=0 WHERE status='sending' AND locked_at<?",[$now-900]);
    $rows=query("SELECT * FROM notification_queue WHERE status='pending' AND available_at<=?".($leadId!==null?' AND lead_id=?':'')." ORDER BY id LIMIT ".max(1,min(50,$limit)),[$now,...($leadId!==null?[$leadId]:[])])->fetchAll();
    foreach($rows as $row){
        $claim=query("UPDATE notification_queue SET status='sending',locked_at=?,attempts=attempts+1 WHERE id=? AND status='pending'",[$now,$row['id']]);if($claim->rowCount()!==1)continue;
        try{
            $lead=record('leads',(int)$row['lead_id']);if(!$lead){query("UPDATE notification_queue SET status='cancelled',last_error='Talep silinmiş' WHERE id=?",[$row['id']]);continue;}
            if(!filter_var($row['recipient'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$row['recipient']))throw new RuntimeException('Geçersiz alıcı.');
            $body="Yeni proje talebi #".$lead['id']."\nAd: ".$lead['name']."\nE-posta: ".$lead['email']."\nTelefon: ".$lead['phone']."\nHizmet: ".$lead['service']."\n\n".$lead['message']."\n\n".url('yonetim?tab=leads');
            $headers=['From'=>$from,'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'8bit','Message-ID'=>'<arkesoft-lead-'.$lead['id'].'-'.hash('sha256',origin()).'@'.(parse_url(origin(),PHP_URL_HOST)?:'localhost').'>'];
            if(filter_var($lead['email'],FILTER_VALIDATE_EMAIL)&&!preg_match('/[\r\n]/',$lead['email']))$headers['Reply-To']=$lead['email'];
            if(($config['mail_transport']??'mail')==='test'&&isset($config['test_sqlite']))$accepted=true;
            elseif(($config['mail_transport']??'mail')==='test-fail'&&isset($config['test_sqlite']))$accepted=false;
            elseif(($config['mail_transport']??'mail')==='smtp')$accepted=(new ArkesoftSmtp())->send($config,$row['recipient'],'Yeni proje talebi #'.$lead['id'],$body,$headers['Reply-To']??null,$headers['Message-ID']);
            else $accepted=mail($row['recipient'],'=?UTF-8?B?'.base64_encode('Yeni proje talebi #'.$lead['id']).'?=',$body,$headers);
            if(!$accepted)throw new RuntimeException('Posta sunucusu gönderimi kabul etmedi.');
            query("UPDATE notification_queue SET status='accepted',last_error='',locked_at=0 WHERE id=?",[$row['id']]);$stats['accepted']++;
        }catch(Throwable $error){
            $attempt=(int)$row['attempts']+1;$status=$attempt>=5?'failed':'pending';
            query('UPDATE notification_queue SET status=?,available_at=?,locked_at=0,last_error=? WHERE id=?',[$status,time()+min(86400,60*(2**$attempt)),mb_substr($error->getMessage(),0,500),$row['id']]);$stats['failed']++;
        }
    }
    return $stats;
}
