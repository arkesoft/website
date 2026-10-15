<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/environment.php';
$dir=__DIR__.'/runtime/env-'.bin2hex(random_bytes(6));mkdir($dir.'/site',0777,true);
$checks=0;
function check(bool $condition,string $label):void {global $checks;if(!$condition)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function write_env(string $contents):string {global $dir;$file=$dir.'/.env';file_put_contents($file,$contents);return $file;}
function rejected(string $contents):bool {try{environment_read(write_env($contents));return false;}catch(RuntimeException $e){return !str_contains($e->getMessage(),'secret-marker');}}
$file=write_env(<<<'ENV'
# Comments and whitespace
BRIEF_TO=
BRIEF_FROM=sender@example.test # a comment
BRIEF_FROM_NAME="ARKESOFT # Tasarım"
SMTP_HOST=smtp.example.test
SMTP_PORT=
SMTP_USER=user@example.test
SMTP_PASS='p $HOME `whoami` # secret-marker'
PUBLIC_ORIGIN=https://arkesoft.com/, https://www.arkesoft.com
EMPTY= # blank with comment
ENV);
$read=environment_read($file);
check($read['SMTP_PASS']==='p $HOME `whoami` # secret-marker','Quoted secrets remain literal');
check($read['BRIEF_FROM']==='sender@example.test'&&$read['BRIEF_TO']===''&&$read['EMPTY']==='','Comments and empty fields');
check($read['BRIEF_FROM_NAME']==='ARKESOFT # Tasarım','Quoted hash and UTF-8');
$config=environment_config(['site_url'=>'https://old.example','db_name'=>'example'],$dir.'/site');
check($config['site_url']==='https://arkesoft.com'&&count($config['public_origins'])===2,'Production canonical uses first origin');
check($config['smtp_port']===465&&$config['smtp_encryption']==='ssl'&&$config['mail_transport']==='smtp','Default implicit TLS');
check(mail_configuration_issues($config)===[],'Complete mail configuration');
check(environment_origins($config)===['https://arkesoft.com','https://www.arkesoft.com'],'Production aliases accepted');
$local=environment_config(['site_url'=>'http://127.0.0.1:8127','test_sqlite'=>'unused'],$dir.'/site');
check($local['site_url']==='http://127.0.0.1:8127'&&environment_origins($local)===['http://127.0.0.1:8127'],'Local origin stays isolated');
check(environment_config([],$dir.'/site')['database_unconfigured']===true,'Env alone cannot enable an empty database');
putenv('SMTP_PORT=587');$tls=environment_config([],$dir.'/site');putenv('SMTP_PORT');
check($tls['smtp_port']==='587'&&$tls['smtp_encryption']==='tls','Process environment wins; port 587 chooses STARTTLS');
write_env("BRIEF_FROM=\nSMTP_HOST=smtp.example.test\n");$fallback=environment_config(['mail_from'=>'saved@example.test'],$dir.'/site');
check($fallback['mail_from']==='saved@example.test','Empty env field preserves config fallback');
check(count(mail_configuration_issues($fallback))===2,'Missing SMTP credentials reported');
$fallback['smtp_user']='user';$fallback['smtp_pass']='0';check(!mail_configuration_issues($fallback),'Nonempty zero password is allowed');
check(rejected('SMTP_PASS="secret-marker'),'Unclosed quotes rejected without secret leakage');
check(rejected('SMTP_PASS="secret-marker" trailing'),'Trailing data rejected without secret leakage');
check(rejected('secret-marker'),'Malformed assignment rejected without echoing line');
check(rejected("SMTP_PASS=secret-marker\0"),'NUL rejected without secret leakage');
write_env("\xEF\xBB\xBFexport BRIEF_FROM=hello@example.test\r\nSMTP_PASS=\"a\\\"b\\\\c\"\r\n");
$read=environment_read($file);check($read['BRIEF_FROM']==='hello@example.test'&&$read['SMTP_PASS']==='a"b\\c','BOM, CRLF, export and escapes');
foreach(['https://example.test/path','https://user:secret-marker@example.test','https://example.test?x=1','javascript:alert(1)'] as $invalid){
    write_env('PUBLIC_ORIGIN='.$invalid);$failed=false;try{environment_config([],$dir.'/site');}catch(RuntimeException $e){$failed=!str_contains($e->getMessage(),'secret-marker');}check($failed,'Invalid public origin rejected');
}
write_env('SMTP_HOST=smtp.example.test');file_put_contents($dir.'/site/.env','SMTP_HOST=wrong.example.test');
check(environment_config([],$dir.'/site')['smtp_host']==='smtp.example.test','Private parent file takes precedence');
check(environment_config([],$dir.'/site',$dir.'/site/.env')['smtp_host']==='wrong.example.test','Explicit env file can override discovery');
check(environment_read($dir.'/missing')===[],'Missing optional env is allowed');
$file=write_env(str_repeat('A',65537));check(rejected(file_get_contents($file)),'Oversized env rejected');
echo "$checks environment checks passed.\n";
