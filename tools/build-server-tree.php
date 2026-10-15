<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
$target=$argv[1]??dirname($root).'/arkesoft-sunucu';
$parent=realpath(dirname($target));
$allowed=[realpath(dirname($root)),realpath($root.'/tests/runtime')];
if(!$parent||!in_array($parent,$allowed,true)||!preg_match('/^(?:arkesoft-sunucu|deploy-[a-f0-9-]+)$/D',basename($target)))throw new RuntimeException('Hedef çalışma alanındaki arkesoft-sunucu veya tests/runtime/deploy-* olmalı.');
$target=$parent.'/'.basename($target);
if(is_link($target)||is_link($target.'/public_html')||is_link($target.'/arkesoft-private'))throw new RuntimeException('Bağlantılı hedef dizin desteklenmiyor.');
function tree_write(string $file,string $content,bool $preserve=false): void {
    if(is_link($file))throw new RuntimeException('Bağlantılı hedef dosya desteklenmiyor.');
    if($preserve&&is_file($file))return;
    if(!is_dir(dirname($file)))mkdir(dirname($file),0755,true);
    if(file_put_contents($file,$content,LOCK_EX)===false)throw new RuntimeException('Dosya yazılamadı.');
}
function tree_copy(string $source,string $destination): void {
    if(is_link($source))throw new RuntimeException('Bağlantılı kaynak desteklenmiyor.');
    tree_write($destination,(string)file_get_contents($source));
}
$private=$target.'/arkesoft-private';$public=$target.'/public_html';
foreach(['app'=>$private.'/app','assets'=>$public.'/assets'] as $folder=>$destination){
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS)) as $file){
        if(!$file->isFile())continue;
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root.'/'.$folder)+1));
        if(basename($relative)!=='.htaccess'&&preg_match('~(^|/)(\.|env$)|\.(?:bak|sql|sqlite|log|zip|key|pem)$~i',$relative))throw new RuntimeException('Özel veya yedek dosya kaynak ağacında bulundu.');
        tree_copy($file->getPathname(),$destination.'/'.$relative);
    }
}
foreach(['growth-upgrade.php','mail-worker.php','check-environment.php','measurement-prune.php','site-audit.php','install-vitals.mjs'] as $tool)tree_copy($root.'/tools/'.$tool,$private.'/tools/'.$tool);
tree_copy($root.'/.htaccess',$public.'/.htaccess');
tree_copy($root.'/uploads/.htaccess',$public.'/uploads/.htaccess');
tree_copy($root.'/storage/.htaccess',$private.'/storage/.htaccess');
tree_write($private.'/.htaccess',"Require all denied\n");
tree_write($private.'/deployment.php',"<?php\ndeclare(strict_types=1);\nreturn dirname(__DIR__).'/public_html';\n");
tree_write($public.'/index.php',"<?php\ndeclare(strict_types=1);\nrequire dirname(__DIR__).'/arkesoft-private/app/bootstrap.php';\nrequire dirname(__DIR__).'/arkesoft-private/app/routes.php';\n");
tree_copy($root.'/config.example.php',$private.'/config.example.php');
tree_copy($root.'/.env.example',$private.'/.env.example');
$config=require $root.'/config.example.php';
$config['site_url']='https://arkesoft.com';$config['db_name']='';$config['db_user']='';$config['db_pass']='';$config['setup_token']=bin2hex(random_bytes(32));
tree_write($private.'/config.php',"<?php\n// Sunucuya ait MySQL bilgilerini burada doldurun.\nreturn ".var_export($config,true).";\n",true);
$envSource=is_file(dirname($root).'/.env')?dirname($root).'/.env':$root.'/.env.example';
tree_write($private.'/.env',(string)file_get_contents($envSource),true);
if(PHP_OS_FAMILY!=='Windows')foreach([$private.'/config.php',$private.'/.env'] as $secret)chmod($secret,0600);
tree_copy($root.'/KURULUM.md',$private.'/KURULUM.md');
tree_write($target.'/DIZIN.txt',<<<'TXT'
ARKESOFT SUNUCU DÜZENİ

arkesoft-sunucu/
  arkesoft-private/          Web kökünün DIŞINDA tutulur.
    config.php              MySQL bilgileri; rastgele kurulum anahtarı hazır.
    .env                    SMTP ve alan adı. Sonraki üretimde üzerine yazılmaz.
    deployment.php          Açık dosyaların fiziksel yolunu belirler.
    app/                    PHP uygulaması ve özel veri dosyaları.
    storage/                Loglar, önbellek ve kuyruk kilitleri.
    tools/                  İsteğe bağlı CLI bakım komutları.
    KURULUM.md              Ayrıntılı kurulum açıklaması.
  public_html/              Alan adının belge kökü YALNIZCA bu klasör.
    index.php               Uygulama giriş noktası.
    .htaccess               Yönlendirme ve erişim kuralları.
    assets/                 Ziyaretçiye açık stil, JS ve marka görselleri.
    uploads/                Yayımlanan medya; betik çalıştırma engellenir.

SUNUCUYA AKTARMA
1. Bu iki klasörü sunucuda yan yana yerleştirin. arkesoft-private klasörünü
   public_html içine koymayın. Web kökünü üst klasöre yönlendirmeyin.
2. arkesoft-private/config.php içindeki MySQL bilgilerini doldurun.
3. arkesoft-private/.env içindeki SMTP hesabını doldurun.
4. /kurulum sayfasında config.php içindeki setup_token ile yönetici oluşturun.
5. /config.php, /.env, /arkesoft-private/config.php, /storage/error.log
   adresleri 403/404 vermeli. Site ve medya adresleri normal çalışmalı.

Bu klasör yeni sunucu kurulumu içindir. Yerel SQLite veritabanı, mevcut
müşteri kayıtları ve yüklenmiş özel medya kopyalanmaz. Mevcut canlı siteyi
güncelliyorsanız MySQL, uploads, config.php ve .env dosyalarınızı koruyun.
Üretici tekrar çalıştırıldığında mevcut config.php ve .env korunur.
Sunucuda sonradan değişen bilgileri bilgisayardaki eski dosyalarla ezmeyin.
Bu ağacı Git'e veya herkese açık bir ZIP bağlantısına eklemeyin.

.htaccess destekleyen PHP 8.2+ hosting gerekir. Nginx için eşdeğer yönlendirme
ve uploads betik engelleme kuralları sunucu yöneticisi tarafından tanımlanır.
config.php ve .env izinleri PHP'nin okuyabildiği sahiplik düzeninde 600;
genel dosyalar 644, klasörler 755. storage ve uploads PHP için yazılabilir
olmalı. 777 kullanmayın. Ana bilgisayarda gizli görünmeleri erişim koruması
anlamına gelmez; asıl ayrım belge kökünün dışında tutulmalarıdır.
TXT);
// Public deployment must never contain private runtime files.
foreach(['config.php','.env','app','storage','tools','arkesoft-private','.git'] as $forbidden)if(file_exists($public.'/'.$forbidden))throw new RuntimeException('public_html içinde özel dosya/klasör bulundu: '.$forbidden);
echo "Sunucu ağacı hazır: $target\nÖzel ayarlar korundu; ZIP oluşturulmadı.\n";
