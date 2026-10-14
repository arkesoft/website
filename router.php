<?php
// Yalnızca PHP'nin yerel geliştirme sunucusu için. Hostinger .htaccess kullanır.
declare(strict_types=1);
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if (preg_match('~^/assets/[a-zA-Z0-9._-]+\.(css|js|svg|png|webp|jpg|webm|mp4)$~',$path) && is_file(__DIR__.$path)) return false;
if (preg_match('~^/uploads/[a-f0-9]+\.(webp|jpg|png|webm|mp4)$~',$path) && is_file(__DIR__.$path)) return false;
require __DIR__.'/index.php';
