<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/migrations.php';
$dir=__DIR__.'/runtime/migrations-'.bin2hex(random_bytes(6));mkdir($dir,0755,true);
ini_set('error_log',$dir.'/error.log');
$mysqlPort=(int)getenv('ARKESOFT_TEST_MYSQL_PORT');
if($mysqlPort){
    $admin=new PDO('mysql:host=127.0.0.1;port='.$mysqlPort,'root','');
    $database='arkesoft_migration_test_'.bin2hex(random_bytes(6));
    $admin->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4');
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$mysqlPort.';dbname='.$database,'root','');
}else $pdo=new PDO('sqlite:'.$dir.'/test.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE leads (id INTEGER PRIMARY KEY, message TEXT NOT NULL); INSERT INTO leads VALUES (1,'Existing customer request')");
$lock=$dir.'/schema.lock';$checks=0;
function check(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
check(schema_history($pdo)===[],'Existing database without history is supported');
$runs=0;
$migrations=[1=>['name'=>'Add budget','run'=>static function(PDO $db)use(&$runs):void{$runs++;$db->exec('ALTER TABLE leads ADD COLUMN budget INTEGER DEFAULT 0');}]];
schema_apply($pdo,$migrations,$lock);
check($pdo->query('SELECT message FROM leads WHERE id=1')->fetchColumn()==='Existing customer request','Customer data survives schema change');
check((int)$pdo->query('SELECT budget FROM leads WHERE id=1')->fetchColumn()===0,'New column is added to existing row');
check(count(schema_history($pdo))===1,'Successful migration recorded');
schema_apply($pdo,$migrations,$lock);check($runs===1,'Repeated request does not rerun completed SQL');
$migrations[2]=['name'=>'Fail midway','run'=>static function(PDO $db):void{$db->exec('CREATE TABLE partial_change (id INTEGER)');throw new RuntimeException('test migration failure');}];
try{schema_apply($pdo,$migrations,$lock);throw new LogicException('Expected failure');}catch(SchemaUpdateUnavailable $expected){}
check(count(schema_history($pdo))===1,'Failed migration is not recorded as complete');
if($mysqlPort)check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='partial_change'")->fetchColumn()===1,'MySQL partial DDL remains available for idempotent retry');
else check((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name='partial_change'")->fetchColumn()===0,'SQLite failed DDL transaction rolls back');
$retried=false;$migrations[2]=['name'=>'Retry safely','run'=>static function(PDO $db)use(&$retried):void{$retried=true;$db->exec('CREATE TABLE IF NOT EXISTS partial_change (id INTEGER)');}];
try{schema_apply($pdo,$migrations,$lock);throw new LogicException('Expected cooldown');}catch(SchemaUpdateUnavailable $expected){}
check(!$retried,'Failure cooldown avoids hammering database');
file_put_contents($lock,'0');schema_apply($pdo,$migrations,$lock);
check($retried&&count(schema_history($pdo))===2,'Fixed pending migration can resume');
$migrations[3]=['name'=>'Next update','run'=>static fn(PDO $db)=>$db->exec('CREATE TABLE next_change (id INTEGER)')];
$held=fopen($lock,'c');flock($held,LOCK_EX);
try{schema_apply($pdo,$migrations,$lock);throw new LogicException('Expected lock contention');}catch(SchemaUpdateUnavailable $expected){}
check(count(schema_history($pdo))===2,'Concurrent worker cannot run pending migration');
flock($held,LOCK_UN);fclose($held);
if($mysqlPort){
    $other=new PDO('mysql:host=127.0.0.1;port='.$mysqlPort.';dbname='.$database,'root','');
    $name='arkesoft-schema-'.substr(hash('sha256',$database),0,40);
    $stmt=$other->prepare('SELECT GET_LOCK(?,0)');$stmt->execute([$name]);
    try{schema_apply($pdo,$migrations,$dir.'/other-deployment.lock');throw new LogicException('Expected MySQL lock contention');}catch(SchemaUpdateUnavailable $expected){}
    check(count(schema_history($pdo))===2,'MySQL advisory lock coordinates different deployment paths');
    $stmt=$other->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$name]);
}
schema_apply($pdo,$migrations,$lock);
check(count(schema_history($pdo))===3,'Pending migration runs after lock release');
check((int)$pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn()===1,'Existing row count stays unchanged');
check(schema_pending($pdo,$migrations)===[],'All versions report current');
$pdo->exec('DROP TABLE schema_migrations');$pdo->exec('CREATE TABLE schema_migrations (invalid INTEGER)');
try{schema_history($pdo);throw new LogicException('Expected corrupt history failure');}catch(PDOException $expected){check(true,'Corrupt history is not mistaken for first installation');}
echo "$checks migration checks passed.\n";
