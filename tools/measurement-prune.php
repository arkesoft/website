<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if(!growth_ready())exit(1);
$deleted=query('DELETE FROM measurement_daily WHERE day<?',[gmdate('Y-m-d',time()-90*86400)])->rowCount();
echo "$deleted eski toplu ölçüm satırı silindi.\n";
