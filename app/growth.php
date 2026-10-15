<?php
declare(strict_types=1);

require_once __DIR__.'/responsive-media.php';
require_once __DIR__.'/discovery.php';
require_once __DIR__.'/contact-growth.php';
require_once __DIR__.'/measurement.php';
require_once __DIR__.'/growth-admin.php';

function growth_defaults(): array {
    return ['business_kind'=>'Organization','legal_name'=>'','business_hours'=>'','maps_url'=>'','business_city'=>'',
        'business_postcode'=>'','business_country'=>'TR','business_lat'=>'','business_lng'=>'',
        'phone_secondary'=>'','contact_note'=>'','support_scope'=>'','measurement_enabled'=>'0','notification_email'=>'','mail_enabled'=>'auto'];
}

function growth_ready(): bool {
    return cms_ready() && (settings()['_growth_version']??'')==='1';
}

function growth_migrate(): void {
    $pdo=db();$mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $end=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    foreach([
        "authors (id $id, name VARCHAR(150) NOT NULL, slug VARCHAR(160) NOT NULL UNIQUE, kind VARCHAR(20) NOT NULL, role VARCHAR(190) NOT NULL, bio TEXT NOT NULL, profile_url VARCHAR(500) NOT NULL, image_id INTEGER NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'draft')",
        'article_tags (article_id INTEGER NOT NULL, slug VARCHAR(100) NOT NULL, label VARCHAR(100) NOT NULL, PRIMARY KEY(article_id,slug))',
        'lead_submissions (token VARCHAR(64) PRIMARY KEY, lead_id INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL)',
        "notification_queue (id $id, lead_id INTEGER NOT NULL UNIQUE, recipient VARCHAR(190) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, available_at INTEGER NOT NULL, locked_at INTEGER NOT NULL DEFAULT 0, last_error VARCHAR(500) NOT NULL DEFAULT '', created_at INTEGER NOT NULL)",
        'measurement_daily (bucket VARCHAR(64) PRIMARY KEY, day VARCHAR(10) NOT NULL, path VARCHAR(250) NOT NULL, event VARCHAR(30) NOT NULL, device VARCHAR(10) NOT NULL, value_bucket INTEGER NOT NULL DEFAULT 0, hits INTEGER NOT NULL DEFAULT 0)'
    ] as $table)$pdo->exec('CREATE TABLE IF NOT EXISTS '.$table.$end);
    foreach(growth_defaults() as $key=>$value)if(query('SELECT setting_key FROM settings WHERE setting_key=?',[$key])->fetchColumn()===false)setting_write($key,$value);
    foreach([
        ['articles','articles_publication','status,published_at,id'],['article_tags','article_tag_lookup','slug,article_id'],
        ['pages','pages_publication','status,id'],['leads','leads_status','status,id'],
        ['notification_queue','notification_due','status,available_at'],['measurement_daily','measurement_date','day,event']
    ] as [$table,$name,$columns]) {
        $exists=$mysql?query('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?',[$table,$name])->fetchColumn():query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name=?",[$name])->fetchColumn();
        if(!$exists)$pdo->exec("CREATE INDEX $name ON $table ($columns)");
    }
    setting_write('_growth_version','1');
}

function growth_transaction(callable $callback): mixed {
    $pdo=db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
    try{$result=$callback();if($owns)$pdo->commit();return $result;}
    catch(Throwable $error){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function growth_cache_dir(): string {
    global $config;
    $key=hash('sha256',origin().'|'.($config['test_sqlite']??($config['db_host']??'').'/'.($config['db_name']??'')));
    return ROOT.'/storage/cache-'.substr($key,0,16);
}

function growth_content_changed(string $sql): void {
    if(!preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER)\s/i',$sql))return;
    unset($GLOBALS['settingsCache']);
    if(preg_match('/\b(rate_limits|leads|lead_notes|lead_submissions|notification_queue|measurement_daily|audit_log)\b/i',$sql))return;
    if(!empty($GLOBALS['growthDirty']))return;
    $GLOBALS['growthDirty']=true;
    register_shutdown_function(static function():void{
        $dir=growth_cache_dir();if(!is_dir($dir))mkdir($dir,0755,true);
        file_put_contents($dir.'/version',bin2hex(random_bytes(12)),LOCK_EX);
    });
}

function growth_cache_begin(string $view,array $data): void {
    if(PHP_SAPI==='cli'||!installed()||session_status()===PHP_SESSION_ACTIVE||isset($_COOKIE[session_name()])||
        !in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)||in_array($view,['contact','discovery','404'],true)||
        !empty($data['preview'])||!empty($data['noindex'])||!empty($GLOBALS['growthDirty'])) {
        if(PHP_SAPI!=='cli')header('Cache-Control: private, no-store');return;
    }
    $dir=growth_cache_dir();if(!is_dir($dir))mkdir($dir,0755,true);
    $version=is_file($dir.'/version')?file_get_contents($dir.'/version'):'initial';
    $request=$_SERVER['REQUEST_URI']??'/';
    // Arbitrary campaign/search parameters must not create unbounded disk cache entries.
    foreach(array_keys($_GET) as $key)if(!in_array($key,['sayfa'],true)){header('Cache-Control: private, no-cache');return;}
    $published=cms_ready()?query("SELECT MAX(published_at) FROM articles WHERE status='published' AND published_at<=?",[gmdate('c')])->fetchColumn():'';
    $file=$dir.'/'.hash('sha256',$version.'|'.$published.'|'.$request).'.html';
    header('Cache-Control: public, max-age=0, must-revalidate');header('Vary: Cookie');
    $ttl=60;
    if(cms_ready()){$next=query("SELECT MIN(published_at) FROM articles WHERE status='published' AND published_at>?",[gmdate('c')])->fetchColumn();if($next)$ttl=max(0,min($ttl,strtotime($next)-time()));}
    if(is_file($file)&&time()-filemtime($file)<$ttl){growth_cache_send((string)file_get_contents($file));exit;}
    // Bound cache storage independently of catalog size.
    $files=glob($dir.'/*.html')?:[];if(count($files)>400)foreach($files as $cached)if(filemtime($cached)<time()-120)@unlink($cached);
    ob_start(static function(string $html)use($file):string{
        if(http_response_code()===200||http_response_code()===false){
            $temp=$file.'.'.bin2hex(random_bytes(4)).'.tmp';file_put_contents($temp,$html,LOCK_EX);@rename($temp,$file);if(is_file($temp))@unlink($temp);
            $etag='"'.hash('sha256',$html).'"';header('ETag: '.$etag);
            if(($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){http_response_code(304);return '';}
        }
        return ($_SERVER['REQUEST_METHOD']??'GET')==='HEAD'?'':$html;
    });
}

function growth_cache_send(string $html): void {
    $etag='"'.hash('sha256',$html).'"';header('ETag: '.$etag);
    if(($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){http_response_code(304);return;}
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')echo $html;
}

function growth_breadcrumbs(string $view,array $data): array {
    $items=[['name'=>'Ana sayfa','path'=>'/']];
    $parent=match($view){'service','category'=>['Hizmetler','/hizmetler'],'project'=>['Projeler','/projeler'],'article','author'=>['Blog','/blog'],'landing'=>['Yerel çözümler','/cozumler'],default=>null};
    if($parent)$items[]=['name'=>$parent[0],'path'=>$parent[1]];
    if($view!=='home')$items[]=['name'=>$data['title']??'Sayfa','path'=>parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'];
    return $items;
}

function growth_breadcrumb_html(): void {
    $items=$GLOBALS['growthBreadcrumbs']??[];if(count($items)<2)return;
    echo '<nav class="breadcrumbs" aria-label="İçerik yolu"><ol>';
    foreach($items as $i=>$item)echo '<li>'.($i===count($items)-1?'<span aria-current="page">'.e($item['name']).'</span>':'<a href="'.e($item['path']).'">'.e($item['name']).'</a>').'</li>';
    echo '</ol></nav>';
}

function growth_seo_catalog(): array {
    return [
        'home'=>['Ana sayfa','/','Web Tasarım, Yazılım ve Dijital Büyüme','İşletmenize uygun web tasarım, özel yazılım ve dijital büyüme çözümlerini, çalışmalarımızı ve çalışma yaklaşımımızı keşfedin.'],
        'services'=>['Hizmetler','/hizmetler','Web Tasarım ve Yazılım Hizmetleri','Web tasarım, mobil uygulama, özel yazılım, marka tasarımı ve SEO hizmetlerinin kapsamını inceleyin; ihtiyacınıza uygun başlangıcı belirleyin.'],
        'projects'=>['Projeler','/projeler','Projeler ve Çalışmalar','Web, mobil ve marka projelerimizi inceleyin. Her çalışmanın ihtiyacını, tasarım yaklaşımını ve geliştirilen çözümü keşfedin.'],
        'about'=>['Hakkımızda','/hakkimizda','Hakkımızda ve Çalışma Yaklaşımımız','Tasarım ve yazılım projelerini nasıl ele aldığımızı, keşiften teslimata çalışma sürecimizi ve ekibimizin yaklaşımını tanıyın.'],
        'contact'=>['İletişim','/iletisim','İletişim ve Proje Görüşmesi','Web sitesi, yazılım veya tasarım ihtiyacınızı paylaşın. İletişim kanallarımızdan ulaşın; projenizin kapsamını ve sonraki adımlarını birlikte belirleyelim.'],
        'solutions'=>['Yerel çözümler','/cozumler','Şehrinize Uygun Dijital Çözümler','Şehriniz ve ihtiyacınız için hazırlanan hizmet kapsamlarını keşfedin. Web tasarım ve yazılım projelerinin yerel ihtiyaçlarla nasıl buluştuğunu inceleyin.'],
        'blog'=>['Blog','/blog','Web Tasarım ve Yazılım Rehberleri','Web sitesi hazırlığı, yazılım entegrasyonu ve dijital görünürlük hakkında rehberler. Projenizi planlarken yararlanabileceğiniz yazıları okuyun.'],
        'discovery'=>['İhtiyaç belirleme','/ihtiyacini-belirle','Projenizin İhtiyaçlarını Belirleyin','Birkaç kısa soruyla web sitesi veya yazılım ihtiyacınızı netleştirin; hazırlanan özeti proje görüşmenize taşıyın.']
    ];
}

function growth_page_seo(string $view,array $s): array {
    $row=growth_seo_catalog()[$view]??null;if(!$row)return [];
    $saved=json_decode($s['page_seo_'.$view]??'{}',true);if(!is_array($saved))$saved=[];
    return ['title'=>$saved['title']??($view==='home'?$s['meta_title']:$row[2].' | '.$s['brand']),
        'description'=>$saved['description']??($view==='home'?$s['meta_description']:$row[3]),'image_id'=>(int)($saved['image_id']??0)];
}

function growth_schema(string $view,array $data,array $s,array $extra,string $canonical,string $description): array {
    $type=$s['business_kind']==='ProfessionalService'&&$s['address']&&$s['business_city']?'ProfessionalService':'Organization';
    $org=['@type'=>$type,'@id'=>url('#organization'),'name'=>$s['brand'],'url'=>origin()];
    foreach(['email'=>'email','phone'=>'telephone','legal_name'=>'legalName'] as $field=>$property)if($s[$field])$org[$property]=$s[$field];
    $org['logo']=url(media_url((int)$s['logo_media'])?:'/assets/arkesoft-logo-transparent.png');
    $profiles=array_values(array_filter(array_map(fn($key)=>$s[$key]??'',['instagram','linkedin','youtube']),fn($value)=>$value&&safe_web_url($value)));
    if($profiles)$org['sameAs']=$profiles;
    if($s['phone']||$s['email'])$org['contactPoint']=array_filter(['@type'=>'ContactPoint','contactType'=>'customer service','telephone'=>$s['phone'],'email'=>$s['email'],'availableLanguage'=>'Turkish']);
    if($type==='ProfessionalService'){
        $org['address']=array_filter(['@type'=>'PostalAddress','streetAddress'=>$s['address'],'addressLocality'=>$s['business_city'],'postalCode'=>$s['business_postcode'],'addressCountry'=>$s['business_country']]);
        if($s['maps_url'])$org['hasMap']=$s['maps_url'];
        if($s['business_lat']!==''&&$s['business_lng']!=='')$org['geo']=['@type'=>'GeoCoordinates','latitude'=>(float)$s['business_lat'],'longitude'=>(float)$s['business_lng']];
        $hours=array_values(array_filter(lines($s['business_hours']),fn($line)=>preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))? \d{2}:\d{2}-\d{2}:\d{2}$/D',$line)));
        if($hours)$org['openingHours']=$hours;
    }
    $graph=[$org,['@type'=>'WebSite','@id'=>url('#website'),'name'=>$s['brand'],'url'=>origin(),'inLanguage'=>'tr-TR','publisher'=>['@id'=>url('#organization')]]];
    if($view!=='home')$graph[]=['@type'=>'BreadcrumbList','itemListElement'=>array_map(fn($item,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>$item['name'],'item'=>url($item['path'])],growth_breadcrumbs($view,$data),array_keys(growth_breadcrumbs($view,$data)))];
    if(in_array($view,['service','landing'],true)){
        $item=$data['service']??$data['page'];$service=['@type'=>'Service','name'=>$item['name']??$item['heading'],'description'=>$description,'url'=>$canonical,'provider'=>['@id'=>url('#organization')]];
        if($view==='landing')$service['areaServed']=['@type'=>'Place','name'=>record('cities',(int)$item['city_id'])['name']??''];$graph[]=$service;
    }
    if($view==='article'){
        $a=$data['article'];$author=growth_article_author($a);$node=['@type'=>'BlogPosting','headline'=>$a['title'],'description'=>$a['excerpt'],'datePublished'=>$a['published_at'],'dateModified'=>$a['updated_at'],'author'=>$author,'publisher'=>['@id'=>url('#organization')],'mainEntityOfPage'=>$canonical,'inLanguage'=>'tr-TR'];
        if($image=media_url((int)$a['image_id']))$node['image']=url($image);$graph[]=$node;
    }
    if($view==='author')$graph[]=['@type'=>'ProfilePage','mainEntity'=>growth_author_schema($data['author']),'url'=>$canonical];
    return $graph;
}

function growth_public_assets(string $view): void {
    $files=['site.css','agency.css','studio.css'];
    if($view!=='home')$files[]='inner.css';
    $files[]='accessibility.css';if($view!=='home')$files[]='page-designs.css';$files[]='growth.css';
    foreach($files as $file)echo '<link rel="stylesheet" href="/assets/'.e($file).'?v='.filemtime(PUBLIC_ROOT.'/assets/'.$file).'">';
    echo '<script defer src="/assets/agency.js?v=6"></script><script defer src="/assets/growth.js?v=7"></script>';
    if($view==='home')echo '<script defer src="/assets/studio.js?v=6"></script>';
    if((settings()['measurement_enabled']??'0')==='1')echo '<script type="module" data-web-vitals="'.(is_file(PUBLIC_ROOT.'/assets/web-vitals.js')?'1':'0').'" src="/assets/measurement.js?v=6"></script>';
}
