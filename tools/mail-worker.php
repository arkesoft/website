<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
try { echo json_encode(growth_mail_worker((int)($argv[1]??10)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL; }
catch(Throwable $error){fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
