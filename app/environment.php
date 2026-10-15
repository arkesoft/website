<?php
declare(strict_types=1);

/** Read configuration as data only: no eval, interpolation, shell or process mutation. */
function environment_read(string $file): array {
    if(!is_file($file))return [];
    if(!is_readable($file)||filesize($file)>65536)throw new RuntimeException('Ortam ayar dosyası okunamadı veya çok büyük.');
    $text=(string)file_get_contents($file);
    if(str_contains($text,"\0"))throw new RuntimeException('Ortam dosyasında geçersiz karakter.');
    $text=preg_replace('/^\xEF\xBB\xBF/','',$text);$result=[];
    foreach(preg_split('/\r\n|\n|\r/',$text) as $index=>$line){
        $line=trim($line);if($line===''||str_starts_with($line,'#'))continue;
        if(!preg_match('/^(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/D',$line,$match))throw new RuntimeException('Ortam dosyası satır '.($index+1).': KEY=value bekleniyor.');
        $key=$match[1];$raw=trim($match[2]);$value='';
        if($raw!==''&&in_array($raw[0],['"',"'"],true)){
            $quote=$raw[0];$closed=false;
            for($i=1,$length=strlen($raw);$i<$length;$i++){
                if($quote==='"'&&$raw[$i]==='\\'&&$i+1<$length&&in_array($raw[$i+1],['"','\\'],true)){$value.=$raw[++$i];continue;}
                if($raw[$i]===$quote){$tail=trim(substr($raw,$i+1));if($tail!==''&&!str_starts_with($tail,'#'))throw new RuntimeException('Ortam dosyası satır '.($index+1).': tırnak sonrası içerik geçersiz.');$closed=true;break;}
                $value.=$raw[$i];
            }
            if(!$closed)throw new RuntimeException('Ortam dosyası satır '.($index+1).': kapanmayan tırnak.');
        }else $value=str_starts_with($raw,'#')?'':trim(preg_replace('/\s+#.*$/','',$raw));
        if(str_contains($value,"\0"))throw new RuntimeException('Ortam dosyasında geçersiz karakter.');
        $result[$key]=$value;
    }
    return $result;
}

function environment_config(array $base,string $root,?string $explicit=null): array {
    // A private file beside public_html takes precedence over a root-level fallback.
    $file=$explicit??(is_file(dirname($root).'/.env')?dirname($root).'/.env':$root.'/.env');
    $values=environment_read($file);
    $mapping=['BRIEF_TO'=>'mail_to','BRIEF_FROM'=>'mail_from','BRIEF_FROM_NAME'=>'mail_from_name',
        'SMTP_HOST'=>'smtp_host','SMTP_PORT'=>'smtp_port','SMTP_USER'=>'smtp_user','SMTP_PASS'=>'smtp_pass','SMTP_ENCRYPTION'=>'smtp_encryption'];
    foreach([...array_keys($mapping),'PUBLIC_ORIGIN'] as $key){$process=getenv($key);if($process!==false)$values[$key]=$process;}
    $result=$base;
    foreach($mapping as $key=>$target)if(isset($values[$key])&&$values[$key]!=='')$result[$target]=$values[$key];
    $origins=[];
    if(!empty($values['PUBLIC_ORIGIN']))foreach(explode(',',$values['PUBLIC_ORIGIN']) as $candidate){
        $candidate=rtrim(trim($candidate),'/');$parts=parse_url($candidate);
        if(!filter_var($candidate,FILTER_VALIDATE_URL)||!in_array($parts['scheme']??'',['http','https'],true)||isset($parts['user'])||isset($parts['query'])||isset($parts['fragment'])||!empty($parts['path']))throw new RuntimeException('PUBLIC_ORIGIN yalnızca virgülle ayrılmış HTTP/HTTPS site köklerini içermeli.');
        $origins[]=$candidate;
    }
    if($origins){
        $result['public_origins']=array_values(array_unique($origins));
        // Explicit local/test URLs retain their own canonical URL.
        $host=parse_url($base['site_url']??'',PHP_URL_HOST);
        if(!isset($base['test_sqlite'])&&!in_array($host,['localhost','127.0.0.1','[::1]','::1'],true))$result['site_url']=$origins[0];
    }
    if(!empty($result['smtp_host'])){
        $result['smtp_port']=$result['smtp_port']??465;
        $result['smtp_encryption']=$result['smtp_encryption']??((int)$result['smtp_port']===465?'ssl':'tls');
        $result['mail_transport']='smtp';
    }
    // An env-only configuration must not accidentally enable an unconfigured database.
    if(empty($result['db_name'])&&!isset($result['test_sqlite']))$result['database_unconfigured']=true;
    return $result;
}

function mail_configuration_issues(array $config): array {
    $issues=[];
    if(!filter_var($config['mail_from']??'',FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$config['mail_from']??''))$issues[]='BRIEF_FROM veya config.php mail_from alanını doldurun.';
    if(($config['mail_transport']??'mail')==='smtp'){
        if(!preg_match('/^(?=.{1,253}$)[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/D',$config['smtp_host']??''))$issues[]='SMTP_HOST geçerli bir sunucu adı olmalı.';
        if(!ctype_digit((string)($config['smtp_port']??''))||(int)$config['smtp_port']<1||(int)$config['smtp_port']>65535)$issues[]='SMTP_PORT geçerli bir port olmalı.';
        if(!in_array($config['smtp_encryption']??'',['ssl','tls'],true))$issues[]='SMTP_ENCRYPTION ssl veya tls olmalı; şifresiz bağlantı desteklenmez.';
        if(($config['smtp_user']??'')==='')$issues[]='SMTP_USER alanını doldurun.';
        if(($config['smtp_pass']??'')==='')$issues[]='SMTP_PASS alanını doldurun.';
    }
    if(!empty($config['mail_to'])&&!filter_var($config['mail_to'],FILTER_VALIDATE_EMAIL))$issues[]='BRIEF_TO tek bir geçerli e-posta adresi olmalı.';
    if(preg_match('/[\r\n\x00]/',$config['mail_from_name']??''))$issues[]='BRIEF_FROM_NAME tek satır olmalı.';
    return $issues;
}

function environment_origins(array $config): array {
    $canonical=rtrim($config['site_url']??'http://127.0.0.1:8127','/');
    $local=in_array(parse_url($canonical,PHP_URL_HOST),['localhost','127.0.0.1','[::1]','::1'],true)||isset($config['test_sqlite']);
    return array_values(array_unique([$canonical,...($local?[]:($config['public_origins']??[]))]));
}
