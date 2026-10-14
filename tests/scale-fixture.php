<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';
if(!str_contains(str_replace('\\','/',getenv('ARKESOFT_CONFIG')?:''),'/tests/runtime/'))throw new RuntimeException('Only isolated test database allowed.');
$service=record('services',1);db()->beginTransaction();
try{for($i=0;$i<1005;$i++){
 query('INSERT INTO cities (name,slug,context,sectors) VALUES (?,?,?,?)',['Scale '.$i,'scale-'.$i,'Isolated test fixture','']);$city=record('cities',(int)db()->lastInsertId());
 $row=generate_page($service,$city);$row['status']='published';$row['reviewed']=1;$cols=implode(',',array_keys($row));$marks=implode(',',array_fill(0,count($row),'?'));query("INSERT INTO pages ($cols) VALUES ($marks)",array_values($row));
}db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
echo '1005 isolated scale records created';
