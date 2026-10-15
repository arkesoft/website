<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if(!installed()){fwrite(STDERR,"Önce kurulum yapılmalı.\n");exit(1);}
growth_migrate();$count=0;
foreach(cms_records('media') as $media)$count+=responsive_media_generate((int)$media['id']);
echo "Görünürlük ve iletişim altyapısı hazır; $count uyarlanabilir görsel üretildi.\n";
