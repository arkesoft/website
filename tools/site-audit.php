<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=rtrim($argv[1]??'', '/');$parts=parse_url($base);
if(!filter_var($base,FILTER_VALIDATE_URL)||!in_array($parts['scheme']??'',['http','https'],true)||isset($parts['user'])||!empty($parts['path'])){fwrite(STDERR,"Kullanım: php tools/site-audit.php https://alanadiniz.com\n");exit(1);}
function read_url(string $url): array {
    $context=stream_context_create(['http'=>['timeout'=>15,'follow_location'=>0,'ignore_errors'=>true,'header'=>"User-Agent: ArkesoftSiteAudit/1.0\r\n"]]);
    $body=@file_get_contents($url,false,$context,0,4000000);$headers=$http_response_header??[];preg_match('/\s(\d{3})\s/',$headers[0]??'',$m);
    return ['status'=>(int)($m[1]??0),'body'=>$body===false?'':$body,'headers'=>$headers];
}
function document(string $text): DOMDocument {$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$text);libxml_clear_errors();return $doc;}
libxml_use_internal_errors(true);
$pending=[$base.'/sitemap.xml'];$seen=[];$urls=[];$issues=[];
while($pending&&count($seen)<100){$map=array_shift($pending);if(isset($seen[$map]))continue;$seen[$map]=true;$r=read_url($map);$xml=simplexml_load_string($r['body'],SimpleXMLElement::class,LIBXML_NONET);if($r['status']!==200||!$xml){$issues[]=['url'=>$map,'issue'=>'Sitemap okunamadı'];continue;}
    foreach($xml->xpath('//*[local-name()="loc"]') as $loc){$url=(string)$loc;if(!str_starts_with($url,$base.'/')){$issues[]=['url'=>$url,'issue'=>'Sitemap farklı alan adına işaret ediyor'];continue;}if($xml->getName()==='sitemapindex')$pending[]=$url;else $urls[$url]=true;}
}
$rows=[];$descriptions=[];$links=[];$statuses=[];
foreach(array_slice(array_keys($urls),0,500) as $url){$r=read_url($url);$doc=document($r['body']);$xpath=new DOMXPath($doc);$canonical=$xpath->evaluate('string(//link[@rel="canonical"]/@href)');$description=$xpath->evaluate('string(//meta[@name="description"]/@content)');$robots=$xpath->evaluate('string(//meta[@name="robots"]/@content)');$h1=$doc->getElementsByTagName('h1')->length;$errors=[];
    if($r['status']!==200)$errors[]='HTTP '.$r['status'];if($h1!==1)$errors[]='H1 sayısı '.$h1;if($canonical!==$url)$errors[]='Canonical farklı / eksik';if(!$description)$errors[]='Meta açıklama eksik';if(str_contains($robots,'noindex')||preg_grep('/^X-Robots-Tag:.*noindex/i',$r['headers']))$errors[]='Sitemap içinde noindex';
    if($description)$descriptions[$description][]=$url;
    foreach($xpath->query('//script[@type="application/ld+json"]') as $node){json_decode($node->textContent,true);if(json_last_error()!==JSON_ERROR_NONE)$errors[]='Geçersiz JSON-LD';}
    foreach($xpath->query('//a[@href]') as $node){$href=$node->getAttribute('href');if(str_starts_with($href,'/')&&!str_starts_with($href,'//'))$links[$base.explode('#',$href,2)[0]]=true;elseif(str_starts_with($href,$base.'/'))$links[explode('#',$href,2)[0]]=true;}
    $statuses[$url]=$r['status'];$rows[]=['url'=>$url,'status'=>$r['status'],'h1'=>$h1,'canonical'=>$canonical,'issues'=>$errors];
}
$duplicates=array_values(array_filter($descriptions,fn($items)=>count($items)>1));
$broken=[];foreach(array_slice(array_keys($links),0,1000) as $link){$status=$statuses[$link]??read_url($link)['status'];if($status===0||$status>=400)$broken[]=['url'=>$link,'status'=>$status];}
echo json_encode(['checked'=>count($rows),'sitemap_urls'=>count($urls),'limit'=>500,'links_checked'=>min(count($links),1000),'broken_links'=>$broken,'sitemap_issues'=>$issues,'duplicate_descriptions'=>$duplicates,'pages'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($issues||$duplicates||$broken||array_filter($rows,fn($r)=>$r['issues'])?1:0);
