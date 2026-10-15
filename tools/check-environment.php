<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$issues=mail_configuration_issues($config);
echo json_encode([
    'site_url'=>origin(),
    'database_configured'=>empty($config['database_unconfigured']),
    'mail_transport'=>$config['mail_transport']??'mail',
    'smtp_port'=>isset($config['smtp_port'])?(int)$config['smtp_port']:null,
    'smtp_encryption'=>$config['smtp_encryption']??null,
    'recipient_source'=>!empty($config['mail_to'])?'BRIEF_TO':'panel',
    'automatic_dispatch'=>'Yeni talepte ve site/panel ziyaretlerinde; zamanlanmış görev isteğe bağlı.',
    'mail_configuration_issues'=>$issues,
    'connection_tested'=>false,
    'mail_sent'=>false,
],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($issues?1:0);
