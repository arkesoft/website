<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$target=dirname($root).'/arkesoft-hostinger.zip';
$zip=new ZipArchive();
if ($zip->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('ZIP oluşturulamadı.');
$files=['index.php','.htaccess','config.example.php','KURULUM.md','SEO-ARASTIRMA.md'];
foreach(['app','assets'] as $dir) {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) if($file->isFile()) $files[]=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
}
$files[]='storage/.htaccess';
$files[]='uploads/.htaccess';
foreach($files as $file) $zip->addFile($root.'/'.$file,$file);
$zip->close();
$check=new ZipArchive();
if ($check->open($target)!==true) throw new RuntimeException('ZIP doğrulanamadı.');
foreach($files as $file) if($check->locateName($file)===false) throw new RuntimeException('Eksik dosya: '.$file);
for($i=0;$i<$check->numFiles;$i++) {
    $name=$check->getNameIndex($i);
    if ($name==='config.php' || str_starts_with($name,'tests/') || str_starts_with($name,'tools/') || str_ends_with($name,'.log')) throw new RuntimeException('Pakette geliştirme verisi bulundu.');
}
$check->close();
echo "Hostinger paketi hazır: $target\n".count($files)." dosya; config.php, test verileri ve geliştirme araçları dahil edilmedi.\n";
