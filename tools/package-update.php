<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/build-server-tree.php';
$destination=dirname($root).'/arkesoft-guncelleme-'.date('Ymd-His').'.zip';
$zip=new ZipArchive();
if($zip->open($destination,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('ZIP oluşturulamadı.');
$count=0;
foreach(['arkesoft-private/app','arkesoft-private/tools','public_html/assets'] as $folder){
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target.'/'.$folder,FilesystemIterator::SKIP_DOTS)) as $file){
        if(!$file->isFile())continue;
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($target)+1));
        if(is_link($file->getPathname())||(basename($relative)!=='.htaccess'&&preg_match('~(^|/)(\.|config\.php$)|\.(?:log|sql|sqlite|zip|pem|key)$~i',$relative)))throw new RuntimeException('Beklenmeyen güncelleme dosyası.');
        if(!$zip->addFile($file->getPathname(),$relative))throw new RuntimeException('Dosya arşivlenemedi.');
        $count++;
    }
}
foreach(['public_html/index.php','public_html/.htaccess'] as $relative){
    if(!$zip->addFile($target.'/'.$relative,$relative))throw new RuntimeException('Giriş dosyası arşivlenemedi.');$count++;
}
$zip->addFromString('GUNCELLEME.txt',"ARKESOFT CANLI SITE GUNCELLEMESI\n\nOnce hosting dosya ve MySQL yedegi alin. Bu paket kurulu site icindir.\nZIP'i public_html klasorunun yanindaki ust dizinde acin.\nIki klasoru mevcut arkesoft-private ve public_html ile birlestirin;\nyalnizca paketteki kod dosyalarinin uzerine yazin. Mevcut klasorleri silmeyin.\nGecici klasore cikardiysaniz icindekileri ayni duzenle mevcut klasorlere tasiyin.\nKod aktarimi tamamlandiktan sonra siteyi acin. Bekleyen veritabani\nguncellemeleri otomatik uygulanir; /kurulum yeniden calistirilmaz.\nPanel: /yonetim?tab=health - Veritabani guncellemeleri.\n\nPaket config.php, .env, storage veya uploads icermez. Canli ayarlariniz,\nyuklemeleriniz ve veritabaniniz korunur. deployment.php de degistirilmez.\nBir hata olursa 503 bakim ekrani gorunur; basarisiz adim tamamlandi sayilmaz.\nBir dakika sonra tekrar denenir. Hata kaydi arkesoft-private/storage/error.log.\nSorun devam ederse kaydi inceleyin; veritabanini silmeyin veya tekrar kurmayin.\nIslem bitince ZIP'i sunucudan kaldirin.\n");
if(!$zip->close())throw new RuntimeException('ZIP tamamlanamadı.');
if($zip->open($destination,ZipArchive::CHECKCONS)!==true)throw new RuntimeException('ZIP doğrulanamadı.');
for($i=0;$i<$zip->numFiles;$i++){
    $name=$zip->getNameIndex($i);
    if(preg_match('~(^|/)(config\.php|\.env(?:\..*)?|storage|uploads|\.git)(/|$)~',$name))throw new RuntimeException('Güncelleme arşivinde özel dosya bulundu.');
}
$zip->close();echo 'Güncelleme ZIP hazır: '.$destination."\n".$count." uygulama dosyası; canlı config/env/storage/uploads kapsam dışında.\n";
