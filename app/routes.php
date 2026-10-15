<?php
declare(strict_types=1);
require __DIR__ . '/public.php';
require __DIR__ . '/agency.php';
require __DIR__ . '/seo.php';
require __DIR__ . '/actions.php';
require __DIR__ . '/cms-actions.php';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/');
if ($path !== '/' && str_ends_with($path,'/') && $_SERVER['REQUEST_METHOD'] === 'GET') { header('Location: /'.ltrim(rtrim($path,'/'),'/'),true,301); exit; }
$path = rtrim($path,'/') ?: '/';
if (preg_match('~^/(app|storage|tests|tools|arkesoft-private)(/|$)|^/env$|^/config|/\.~i',$path)) { http_response_code(403); exit('Erişim kapalı.'); }
if ($path === '/kurulum') { require __DIR__.'/setup.php'; exit; }
if ($path === '/yonetim' || str_starts_with($path,'/yonetim/')) {
    header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
    require __DIR__.'/admin-v2.php'; exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($path === '/olcum') growth_measure_endpoint();
    if ($path === '/iletisim') { contact_action(); exit; }
    http_response_code(405); header('Allow: GET'); exit('İşlem desteklenmiyor.');
}
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)) { http_response_code(405); exit; }
if(cms_ready()) {
    $destination=query('SELECT destination FROM redirects WHERE source=?',[$path])->fetchColumn();
    if($destination && internal_path($destination)){header('Location: '.$destination,true,301);exit;}
}
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\n".(!installed() ? "Disallow: /\n" : "Disallow: /yonetim\nDisallow: /kurulum\nDisallow: /olcum\nDisallow: /app/\nDisallow: /storage/\nDisallow: /tests/\nDisallow: /tools/\nSitemap: ".url('sitemap.xml')."\n"); exit;
}
if(sitemap_output($path))exit;
switch($path) {
    case '/blog': $listing=growth_blog_data();render_public('blog',['title'=>'İçgörüler & rehberler','listing'=>$listing,'noindex'=>(bool)array_filter($listing['filters'])]); break;
    case '/ihtiyacini-belirle': render_public('discovery',['title'=>'İhtiyacınızı belirleyin']);break;
    case '/': render_public('home'); break;
    case '/hizmetler': render_public('services',['title'=>'Hizmetler']); break;
    case '/projeler': render_public('projects',['title'=>'Çalışmalar']); break;
    case '/hakkimizda': render_public('about',['title'=>'Stüdyoyu tanıyın']); break;
    case '/iletisim': render_public('contact',['title'=>'Birlikte başlayalım']); break;
    case '/gizlilik': render_public('privacy',['title'=>'Gizlilik ve aydınlatma']); break;
    case '/cozumler': if(installed()&&(int)($_GET['sayfa']??1)>max(1,(int)ceil((int)query("SELECT COUNT(*) FROM pages WHERE status='published'")->fetchColumn()/100)))not_found();render_public('solutions',['title'=>'Şehriniz için dijital çözümler']); break;
    default:
        if(preg_match('~^/yazar/([a-z0-9-]+)$~',$path,$m)){if(!growth_ready())not_found();$author=query("SELECT * FROM authors WHERE slug=? AND status='published'",[$m[1]])->fetch();if(!$author)not_found();$listing=growth_blog_data(['author'=>(int)$author['id']]);render_public('author',['title'=>$author['name'],'description'=>mb_substr($author['bio'],0,180),'author'=>$author,'listing'=>$listing]);break;}
        if(preg_match('~^/uzmanlik/([a-z0-9-]+)$~',$path,$m)) { $category=null;foreach(cms_records('categories') as $c)if($c['slug']===$m[1])$category=$c;if(!$category)not_found();render_public('category',['title'=>$category['name'],'description'=>$category['description'],'category'=>$category]);break; }
        if(preg_match('~^/blog/([a-z0-9-]+)$~',$path,$m)) { $article=cms_ready()?query('SELECT * FROM articles WHERE slug=?',[$m[1]])->fetch():null;$preview=admin()&&isset($_GET['onizleme']);if(!$article||(!$preview&&($article['status']!=='published'||strtotime($article['published_at'])>time())))not_found();render_public('article',['title'=>$article['title'],'description'=>$article['excerpt'],'article'=>$article,'preview'=>$preview]);break; }
        if(preg_match('~^/projeler/(\d+)-([a-z0-9-]+)$~',$path,$m)){$project=record('projects',(int)$m[1]);if(!$project||!(meta('projects',(int)$project['id'])['published']??1))not_found();$canonicalPath='/projeler/'.$project['id'].'-'.slugify($project['name']);if($path!==$canonicalPath){header('Location: '.$canonicalPath,true,301);exit;}render_public('project',['title'=>$project['name'],'description'=>$project['description'],'project'=>$project]);break;}
        if (preg_match('~^/hizmetler/([a-z0-9-]+)$~',$path,$match)) {
            $service = by_slug('services',$match[1]); if (!$service) not_found();
            if(!(meta('services',(int)$service['id'])['published']??1))not_found();
            render_public('service',['title'=>$service['name'],'description'=>$service['summary'],'service'=>$service]);
        } elseif (preg_match('~^/cozumler/([a-z0-9-]+)$~',$path,$match)) {
            $page = by_slug('pages',$match[1]);
            $preview = admin() && isset($_GET['onizleme']);
            if (!$page || ($page['status']!=='published' && !$preview) || (!$preview && !(meta('services',(int)$page['service_id'])['published']??1))) not_found();
            if ($preview) { header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); }
            render_public('landing',['title'=>$page['title'],'description'=>$page['description'],'page'=>$page,'preview'=>$preview]);
        } else not_found();
}
