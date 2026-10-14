<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
date_default_timezone_set('Europe/Istanbul');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/error.log');
$configFile = (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') && getenv('ARKESOFT_CONFIG') ? getenv('ARKESOFT_CONFIG') : ROOT . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; media-src 'self'; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
session_name('arkesoft_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode', '1');
session_start();
require __DIR__ . '/seed.php';
require __DIR__ . '/database.php';
require __DIR__ . '/cms.php';
require __DIR__ . '/studio-cms.php';
require __DIR__ . '/page-designs.php';
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function origin(): string { global $config; return rtrim($config['site_url'] ?? 'http://127.0.0.1:8127', '/'); }
function url(string $path = ''): string { return origin() . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function check_csrf(): void { $value=$_POST['csrf']??'';if (!is_string($value)||!hash_equals(csrf(),$value)) { http_response_code(419); exit('Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.'); } }
function flash(string $text, string $type = 'success'): void { $_SESSION['flash'] = [$text, $type]; }
function flash_html(): string { if (!isset($_SESSION['flash'])) return ''; [$text,$type] = $_SESSION['flash']; unset($_SESSION['flash']); return '<div class="notice '.e($type).'" role="status">'.e($text).'</div>'; }
function admin(): bool {
    if(!isset($_SESSION['admin_id']) || ($_SESSION['last_active']??0)<=time()-7200) return false;
    if(!installed())return false;
    $hash=query('SELECT password FROM admins WHERE id=?',[$_SESSION['admin_id']])->fetchColumn();
    return $hash && isset($_SESSION['password_stamp']) && hash_equals(hash('sha256',$hash),$_SESSION['password_stamp']);
}
function require_admin(): void { if (!admin()) redirect('/yonetim'); $_SESSION['last_active'] = time(); }
function post(string $key, int $max = 10000): string { $value = $_POST[$key] ?? ''; return is_string($value) ? mb_substr(trim($value),0,$max) : ''; }
function slugify(string $value): string { $value = strtr($value,['İ'=>'i','ı'=>'i','Ş'=>'s','ş'=>'s','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c']); return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($value)) ?? '', '-'); }
function paragraphs(string $text): string { return implode('',array_map(fn($p)=>'<p>'.nl2br(e(trim($p))).'</p>',preg_split('/\n\s*\n/',str_replace('\\n',"\n",$text)) ?: [])); }
function lines(string $text): array { return array_values(array_filter(array_map('trim',explode("\n",str_replace('\\n',"\n",$text))))); }
function arrow(): string { return '<span aria-hidden="true">↗</span>'; }
function safe_web_url(string $url): bool { return $url === '' || (filter_var($url,FILTER_VALIDATE_URL) && in_array(parse_url($url,PHP_URL_SCHEME),['https','http'],true)); }
function not_found(): never { http_response_code(404); render_public('404', ['title'=>'Sayfa bulunamadı']); exit; }
set_exception_handler(function(Throwable $error): void { error_log((string)$error); http_response_code(500); echo '<!doctype html><html lang="tr"><meta charset="utf-8"><title>Geçici bağlantı sorunu</title><body><h1>Bir bağlantı sorunu oluştu.</h1><p>Lütfen daha sonra yeniden deneyin. Kurulum yapıyorsanız config.php içindeki veritabanı bilgilerini kontrol edin.</p><a href="/">Ana sayfaya dön</a></body></html>'; });
