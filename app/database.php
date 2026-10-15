<?php
declare(strict_types=1);
function db(): ?PDO {
    global $config;
    static $connection = null;
    if ($connection) return $connection;
    if (!$config || !empty($config['database_unconfigured'])) return null;
    if (isset($config['test_sqlite']) && in_array(PHP_SAPI,['cli','cli-server'],true)) {
        $connection = new PDO('sqlite:'.$config['test_sqlite']);
    } else {
        $connection = new PDO('mysql:host='.($config['db_host'] ?? 'localhost').';port='.(int)($config['db_port'] ?? 3306).';dbname='.($config['db_name'] ?? '').';charset=utf8mb4',$config['db_user'] ?? '',$config['db_pass'] ?? '', [PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    $connection->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') $connection->exec('PRAGMA foreign_keys = ON');
    return $connection;
}
function installed(): bool {
    if (!db()) return false;
    try { return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0; }
    catch (PDOException $error) { if (in_array((string)$error->getCode(),['42S02','HY000'],true) && (str_contains($error->getMessage(),'no such table') || str_contains($error->getMessage(),"doesn't exist"))) return false; throw $error; }
}
function query(string $sql, array $params = []): PDOStatement { $stmt = db()->prepare($sql); $stmt->execute($params); if(function_exists('growth_content_changed'))growth_content_changed($sql); return $stmt; }
function settings(): array {
    if (!installed()) return array_merge(seed_settings(),function_exists('cms_defaults')?cms_defaults():[]);
    if(isset($GLOBALS['settingsCache']))return $GLOBALS['settingsCache'];
    $rows = query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $GLOBALS['settingsCache']=array_merge(seed_settings(), function_exists('cms_defaults')?cms_defaults():[], $rows);
}
function records(string $table): array {
    if (!in_array($table,['services','cities','projects','pages','leads'],true)) throw new InvalidArgumentException('Geçersiz tablo');
    if (!installed()) return match($table) { 'services'=>seed_services(),'cities'=>seed_cities(),'projects'=>seed_projects(),default=>[] };
    $order = in_array($table,['services','projects'],true) ? 'sort_order, id' : 'id DESC';
    return query("SELECT * FROM $table ORDER BY $order")->fetchAll();
}
function record(string $table, int $id): ?array { if(!in_array($table,['services','cities','projects','pages','leads'],true))throw new InvalidArgumentException('Geçersiz tablo'); if(installed()) return query("SELECT * FROM $table WHERE id=?",[$id])->fetch()?:null; foreach(records($table) as $row)if((int)$row['id']===$id)return $row;return null; }
function by_slug(string $table, string $slug): ?array { if(!in_array($table,['services','cities','pages'],true))throw new InvalidArgumentException('Geçersiz tablo');if(installed())return query("SELECT * FROM $table WHERE slug=?",[$slug])->fetch()?:null;foreach(records($table) as $row)if(($row['slug']??'')===$slug)return $row;return null; }
function install_schema(PDO $db): void {
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $end = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $tables = [
        "admins (id $id, email VARCHAR(190) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL)",
        'settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL)',
        "services (id $id, name VARCHAR(150) NOT NULL, slug VARCHAR(160) NOT NULL UNIQUE, eyebrow VARCHAR(100) NOT NULL, summary TEXT NOT NULL, body TEXT NOT NULL, deliverables TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0)",
        "cities (id $id, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL UNIQUE, context TEXT NOT NULL, sectors TEXT NOT NULL)",
        "projects (id $id, name VARCHAR(150) NOT NULL, category VARCHAR(190) NOT NULL, description TEXT NOT NULL, style VARCHAR(20) NOT NULL, url VARCHAR(500) NOT NULL, is_demo INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0)",
        "pages (id $id, service_id INTEGER NOT NULL, city_id INTEGER NOT NULL, slug VARCHAR(200) NOT NULL UNIQUE, title VARCHAR(190) NOT NULL, description TEXT NOT NULL, heading VARCHAR(190) NOT NULL, intro TEXT NOT NULL, body TEXT NOT NULL, faq_question VARCHAR(255) NOT NULL, faq_answer TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', reviewed INTEGER NOT NULL DEFAULT 0, updated_at VARCHAR(30) NOT NULL, UNIQUE(service_id, city_id))",
        "leads (id $id, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL, service VARCHAR(150) NOT NULL, message TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', created_at VARCHAR(30) NOT NULL)",
        'rate_limits (bucket VARCHAR(64) PRIMARY KEY, hits INTEGER NOT NULL, expires_at INTEGER NOT NULL)',
    ];
    foreach($tables as $table) $db->exec('CREATE TABLE IF NOT EXISTS '.$table.$end);
}
function seed_database(string $email, string $password): void {
    $db = db(); install_schema($db);
    if ((int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) throw new RuntimeException('Kurulum tamamlanmış.');
    $db->beginTransaction();
    try {
        foreach(seed_settings() as $key=>$value) query('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)',[$key,$value]);
        foreach(['services'=>seed_services(),'cities'=>seed_cities(),'projects'=>seed_projects()] as $table=>$rows) foreach($rows as $row) {
            $columns = implode(',',array_keys($row)); $marks = implode(',',array_fill(0,count($row),'?'));
            query("INSERT INTO $table ($columns) VALUES ($marks)",array_values($row));
        }
        query('INSERT INTO admins (email,password) VALUES (?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);
        $db->commit();
        if(function_exists('cms_migrate')) cms_migrate();
    } catch(Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
function rate_limit(string $action, int $max, int $seconds): bool {
    $now = time(); $window = (int)floor($now / $seconds);
    $bucket = hash('sha256',$action.'|'.($_SERVER['REMOTE_ADDR'] ?? 'local').'|'.$window);
    $expires = ($window+1)*$seconds;
    query('DELETE FROM rate_limits WHERE expires_at < ?',[$now]);
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') query('INSERT INTO rate_limits (bucket,hits,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1',[$bucket,$expires]);
    else query('INSERT INTO rate_limits (bucket,hits,expires_at) VALUES (?,1,?) ON CONFLICT(bucket) DO UPDATE SET hits=hits+1',[$bucket,$expires]);
    return (int)query('SELECT hits FROM rate_limits WHERE bucket=?',[$bucket])->fetchColumn() <= $max;
}
function generate_page(array $service, array $city): array {
    $name = $city['name'].' '.$service['name'];
    $row = ['service_id'=>$service['id'],'city_id'=>$city['id'],'slug'=>$city['slug'].'-'.$service['slug'],
        'title'=>$name.' | '.settings()['brand'],
        'description'=>mb_substr($city['name'].' için '.$service['name'].' hizmeti. '.$service['summary'].' Projenizin kapsamını birlikte belirleyelim.',0,170),
        'heading'=>$name,
        'intro'=>$city['name'].' hedefli '.$service['name'].' projenizi, markanızın ihtiyaçlarına ve müşterilerinizin beklentilerine göre birlikte planlıyoruz.',
        'body'=>str_replace('\\n',"\n",$service['body'])."\n\n".$city['context']."\n\nProje kapsamı ve çalışma biçimi\n".$city['sectors'].' alanındaki ihtiyaçlarınızı keşif görüşmesinde ele alıyoruz. Hedef kitleniz, içerik kaynaklarınız ve teknik gereksinimleriniz netleştikten sonra teslimatları ve zaman planını birlikte belirliyoruz. İletişim ve değerlendirme görüşmeleri çevrim içi yürütülebilir; bu sayfa ilgili şehirde fiziksel ofis bulunduğu anlamına gelmez.',
        'faq_question'=>$city['name'].' için '.$service['name'].' projesi nasıl başlar?',
        'faq_answer'=>'Kısa bir proje özeti ile başvurabilirsiniz. Mevcut siteniz, hedef kitleniz ve beklediğiniz teslimatlar değerlendirilir. Kapsam, çalışma takvimi ve teklif bu görüşmeden sonra netleştirilir.',
        'status'=>'draft','reviewed'=>0,'updated_at'=>gmdate('c')];
    if(function_exists('cms_ready') && cms_ready()) {
        $s=settings(); $vars=['{{sehir}}'=>$city['name'],'{{hizmet}}'=>$service['name'],'{{marka}}'=>$s['brand'],'{{ozet}}'=>$service['summary'],'{{icerik}}'=>str_replace('\\n',"\n",$service['body']),'{{baglam}}'=>$city['context'],'{{sektorler}}'=>$city['sectors']];
        $row['title']=mb_substr(strtr($s['pseo_title'],$vars),0,190); $row['intro']=strtr($s['pseo_intro'],$vars); $row['body']=strtr($s['pseo_body'],$vars);
    }
    if(strlen($row['slug'])>195)$row['slug']=substr($row['slug'],0,180).'-'.substr(hash('sha256',$row['slug']),0,12);
    $row['title']=mb_substr($row['title'],0,190);$row['heading']=mb_substr($row['heading'],0,190);
    return $row;
}
function page_issues(array $page): array {
    $issues = [];
    if (mb_strlen($page['heading'] ?? '') < 5) $issues[] = 'Sayfa başlığı en az 5 karakter olmalı.';
    if (mb_strlen($page['title']) < 15) $issues[] = 'SEO başlığı en az 15 karakter olmalı.';
    if (mb_strlen($page['description']) < 60) $issues[] = 'Meta açıklaması en az 60 karakter olmalı.';
    if (mb_strlen($page['intro']) < 40 || mb_strlen($page['body']) < 200) $issues[] = 'Giriş ve ana içerik eksik. Ziyaretçinin ihtiyacına yanıt veren açıklamalar ekleyin.';
    if(function_exists('cms_ready') && cms_ready() && !empty($page['id']) && mb_strlen($page['_unique_value']??meta('pages',(int)$page['id'])['unique_value']??'')<100) $issues[]='Bu sayfaya özel yerel bilgi veya proje kapsamı ekleyin (en az 100 karakter).';
    if(function_exists('cms_ready')&&cms_ready()&&!empty($page['id'])&&query('SELECT id FROM pages WHERE title=? AND id<>? LIMIT 1',[$page['title'],$page['id']])->fetchColumn())$issues[]='Aynı SEO başlığı başka bir sayfada kullanılıyor.';
    if (!$page['reviewed']) $issues[] = 'Yayın öncesi içerik incelemesini onaylayın.';
    if (!$page['faq_question'] || !$page['faq_answer']) $issues[] = 'Soru ve yanıt alanlarını doldurun.';
    return $issues;
}
