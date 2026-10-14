<?php
if(PHP_SAPI!=='cli')exit;
foreach(['studio','coffee','architecture'] as $name){$im=imagecreatefrompng(__DIR__.'/../storage/source-images/'.$name.'-v3.png');imagewebp($im,__DIR__.'/../assets/'.$name.'-v3.webp',86);imagedestroy($im);echo $name." optimized\n";}
