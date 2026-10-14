<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if(!installed()) exit("Önce kurulum yapılmalı.\n");
cms_migrate();
echo "CMS 3.0 tabloları ve ayarları hazır. Mevcut hesap korundu; demo vitrin ilk yükseltmede güncellendi.\n";
