<?php
declare(strict_types=1);
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
if (installed()) redirect('/yonetim');
$error='';
$setupConfigured=$config && empty($config['database_unconfigured']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    if (!$setupConfigured || strlen($config['setup_token']??'')<32 || !hash_equals($config['setup_token'],post('token',255))) {
        $error='Kurulum anahtarı geçersiz. config.php dosyasına en az 32 karakterlik anahtar tanımlayın ve aynı değeri buraya girin.';
    } elseif (!filter_var(post('email',190),FILTER_VALIDATE_EMAIL) || strlen(post('password',200))<12 || strlen(post('password',200))>72 || post('password',200)!==post('password_confirm',200)) {
        $error='Geçerli bir e-posta ve birbiriyle eşleşen en az 12 karakterlik şifreler girin.';
    } elseif (!safe_web_url($config['site_url']??'') || !($config['site_url']??'') || str_contains($config['site_url'],'alanadiniz.com') || !in_array(parse_url($config['site_url'],PHP_URL_PATH),[null,'','/'],true)) {
        $error='config.php içindeki site_url değerini gerçek alan adınızla, alt klasör olmadan güncelleyin.';
    } else {
        seed_database(mb_strtolower(post('email',190)),post('password',200));
        flash('Kurulum tamamlandı. Oluşturduğunuz hesapla giriş yapabilirsiniz.'); redirect('/yonetim');
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>ARKESOFT · Kurulum</title><link rel="stylesheet" href="/assets/branding.css?v=2"><link rel="icon" href="/assets/arkesoft-logo-transparent.png"><link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/admin-experience.css?v=4"><link rel="stylesheet" href="/assets/accessibility.css?v=4"><script defer src="/assets/admin-experience.js?v=4"></script></head><body class="auth-body"><main class="auth-box"><a class="logo" href="/"><?=brand_logo()?></a><span class="eyebrow">İLK KURULUM</span><h1>Kontrol sizde.</h1><p>Veritabanını hazırlayın ve yönetici hesabınızı oluşturun. Bu ekran kurulumdan sonra otomatik kapanır.</p><?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?><?php if(!$setupConfigured): ?><div class="notice">Önce config.example.php dosyasını config.php adıyla kopyalayın; alan adınızı, MySQL bilgilerinizi ve kurulum anahtarınızı girin. Ardından bu sayfayı yenileyin.</div><?php else: ?><form method="post" class="auth-form"><?=csrf_field()?><label>Kurulum anahtarı<input type="password" name="token" required autocomplete="off" minlength="32"></label><label>Yönetici e-postası<input type="email" name="email" required autocomplete="username" maxlength="190"></label><label>Şifre <span>(en az 12 karakter)</span><input type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required></label><label>Şifre tekrar<input type="password" name="password_confirm" minlength="12" maxlength="200" autocomplete="new-password" required></label><button class="button dark" type="submit">Kurulumu tamamla ↗</button></form><?php endif; ?><p class="form-note">PHP 8.2+ · MySQL · Ücretli eklenti veya harici API gerekmez.</p></main></body></html>
