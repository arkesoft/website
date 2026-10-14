<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$localConfig = $root . '/storage/local-config.php';
if (!is_file($localConfig)) {
    $settings = [
        'site_url' => 'http://127.0.0.1:8127',
        'test_sqlite' => $root . '/storage/local.sqlite',
        'setup_token' => bin2hex(random_bytes(32)),
    ];
    if (file_put_contents($localConfig, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX) === false) {
        throw new RuntimeException('Yerel yapılandırma yazılamadı.');
    }
}
putenv('ARKESOFT_CONFIG=' . $localConfig);
require $root . '/app/bootstrap.php';
if (installed()) {
    echo "Yerel yönetici hesabı zaten var; mevcut şifre korunuyor.\n";
    exit;
}
$email = 'admin@arkesoft.local';
$password = 'Arke-' . bin2hex(random_bytes(7)) . '!';
seed_database($email, $password);
echo json_encode(['url'=>'http://127.0.0.1:8127/yonetim','email'=>$email,'password'=>$password], JSON_UNESCAPED_SLASHES) . PHP_EOL;
