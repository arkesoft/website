<?php
declare(strict_types=1);

function growth_measure(string $event,string $path,string $device,int $valueBucket=0): void {
    if(!growth_ready())return;
    $day=gmdate('Y-m-d');$path=growth_source_path($path);$key=hash('sha256',implode('|',[$day,$path,$event,$device,$valueBucket]));
    $values=[$key,$day,$path,$event,$device,$valueBucket];
    if(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')query('INSERT INTO measurement_daily (bucket,day,path,event,device,value_bucket,hits) VALUES (?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE hits=hits+1',$values);
    else query('INSERT INTO measurement_daily (bucket,day,path,event,device,value_bucket,hits) VALUES (?,?,?,?,?,?,1) ON CONFLICT(bucket) DO UPDATE SET hits=hits+1',$values);
}

function growth_measure_endpoint(): never {
    header('Cache-Control: no-store');header('X-Robots-Tag: noindex');
    if(!growth_ready()||settings()['measurement_enabled']!=='1'){http_response_code(204);exit;}
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if(!in_array($origin,environment_origins($GLOBALS['config']),true)||($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin')!=='same-origin'){http_response_code(403);exit;}
    if((int)($_SERVER['CONTENT_LENGTH']??0)>2048){http_response_code(413);exit;}
    if(($_SERVER['HTTP_DNT']??'')==='1'||($_SERVER['HTTP_SEC_GPC']??'')==='1'){http_response_code(204);exit;}
    $raw=file_get_contents('php://input',false,null,0,2049);if(strlen($raw)>2048){http_response_code(413);exit;}
    $data=json_decode($raw,true);if(!is_array($data)||!is_string($data['event']??null)||!is_string($data['path']??null)){http_response_code(400);exit;}
    $event=$data['event'];$allowed=['phone_click','whatsapp_click','quote_click','map_click','LCP','INP','CLS'];
    if(!in_array($event,$allowed,true)||!in_array($data['device']??'', ['mobile','desktop'],true)){http_response_code(400);exit;}
    $path=growth_source_path($data['path']);
    if(!preg_match('~^/(?:$|hizmetler(?:/|$)|projeler(?:/|$)|blog(?:/|$)|yazar/|hakkimizda$|iletisim$|cozumler(?:/|$)|uzmanlik/|ihtiyacini-belirle$)~',$path)){http_response_code(400);exit;}
    if(!rate_limit('measurement',120,60)){http_response_code(429);exit;}
    $bucket=0;
    if(in_array($event,['LCP','INP','CLS'],true)){
        $value=$data['value']??null;if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>($event==='CLS'?10:120000)){http_response_code(400);exit;}
        $bucket=(int)(ceil((float)$value/($event==='CLS'?0.01:50))*($event==='CLS'?1:50));
    }
    growth_measure($event,$path,$data['device'],$bucket);http_response_code(204);exit;
}

function growth_percentile(array $rows): ?float {
    $count=array_sum(array_column($rows,'hits'));if(!$count)return null;
    usort($rows,fn($a,$b)=>(int)$a['value_bucket']<=>(int)$b['value_bucket']);$target=(int)ceil($count*.75);$sum=0;
    foreach($rows as $row){$sum+=(int)$row['hits'];if($sum>=$target)return (float)$row['value_bucket'];}return null;
}

function growth_health_checks(): array {
    $s=settings();$host=parse_url(origin(),PHP_URL_HOST)?:'';$local=in_array($host,['127.0.0.1','localhost','::1'],true);
    $checks=[
        'canli_alan_adi'=>['label'=>'Gerçek HTTPS alan adı','ok'=>!$local&&str_starts_with(origin(),'https://')&&!str_contains($host,'alanadiniz'),'hint'=>'Canlıda config.php içindeki site_url değerini kontrol edin. Yerel önizlemede bu kontrolün geçmemesi normaldir.'],
        'eposta'=>['label'=>'Gerçek iletişim e-postası','ok'=>(bool)filter_var($s['email'],FILTER_VALIDATE_EMAIL)&&!preg_match('/\.(example|test|invalid)$/i',$s['email']),'hint'=>'Genel ayarlardan iletişim adresinizi girin.'],
        'telefon'=>['label'=>'Telefon veya WhatsApp tanımlı','ok'=>(bool)($s['phone']||$s['whatsapp']),'hint'=>'Müşterinin size doğrudan ulaşabileceği numarayı girin.'],
        'indeks'=>['label'=>'Genel indeks izni açık','ok'=>$s['site_noindex']!=='1','hint'=>'Arama motorlarına açılacak canlı sitede genel noindex kapalı olmalı.'],
        'demo'=>['label'=>'Demo içerikler yayına hazırlanmış','ok'=>$s['demo_content']!=='1','hint'=>'Yalnızca örnek içerikler gerçek bilgilerle değiştirildikten sonra demo açıklamasını kaldırın.'],
        'logo'=>['label'=>'İşletme logosu tanımlı','ok'=>(bool)media_url((int)$s['logo_media']),'hint'=>'Ana sayfa & marka bölümünden logo seçin.'],
        'bildirim'=>['label'=>'Talep bildirimi etkin ve posta ayarları tamam','ok'=>growth_mail_enabled()&&!mail_configuration_issues($GLOBALS['config']),'hint'=>'İşletme & iletişim bölümünde otomatik gönderimi seçin ve .env posta alanlarını doldurun. Yeni talepler otomatik işlenir; zamanlanmış görev isteğe bağlıdır.'],
    ];
    $descriptions=[];foreach(growth_seo_catalog() as $view=>$page)$descriptions[]=growth_page_seo($view,$s)['description'];
    $checks['aciklamalar']=['label'=>'Ana sayfaların meta açıklamaları farklı','ok'=>count(array_unique($descriptions))===count($descriptions),'hint'=>'Sayfa SEO bölümünde her sayfaya özgü açıklama yazın.'];
    $checks['semalar']=['label'=>'Yazar/işletme altyapısı güncel','ok'=>growth_ready(),'hint'=>'CMS yükseltmesini çalıştırın.'];
    return $checks;
}
