<?php
declare(strict_types=1);
function page_selection(int $limit=60,int $offset=0,string $where='',array $params=[]): array {
    if(!installed())return [];$limit=max(1,min(1000,$limit));$offset=max(0,$offset);
    $rows=query("SELECT * FROM pages WHERE status='published'".($where?' AND '.$where:'')." ORDER BY id DESC LIMIT $limit OFFSET $offset",$params)->fetchAll();
    return array_values(array_filter($rows,fn($p)=>(meta('services',(int)$p['service_id'])['published']??1)));
}
function sitemap_output(string $path): bool {
    if(!preg_match('~^/sitemap(?:-(base|[0-9]+))?\.xml$~',$path,$matches))return false;
    header('Content-Type: application/xml; charset=UTF-8');echo '<?xml version="1.0" encoding="UTF-8"?>';
    $enabled=installed()&&(settings()['site_noindex']??'0')!=='1';$count=$enabled?(int)query("SELECT COUNT(*) FROM pages WHERE status='published'")->fetchColumn():0;
    $part=$matches[1]??'';
    if($part===''&&$count>1000){echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach(['base',...range(1,(int)ceil($count/1000))] as $n)echo '<sitemap><loc>'.e(url('sitemap-'.$n.'.xml')).'</loc></sitemap>';echo '</sitemapindex>';return true;}
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $emit=static function(string $route,string $date=''):void{echo '<url><loc>'.e(url($route)).'</loc>'.($date?'<lastmod>'.e($date).'</lastmod>':'').'</url>';};
    if($enabled){
        if($part===''||$part==='base'){
            foreach(['','hizmetler','projeler','hakkimizda','iletisim','cozumler','blog'] as $route)$emit($route);
            foreach(public_services() as $s)if(empty(meta('services',(int)$s['id'])['noindex']))$emit('hizmetler/'.$s['slug']);
            foreach(cms_records('categories') as $c)$emit('uzmanlik/'.$c['slug']);
            foreach(published_articles() as $a)if(empty(meta('articles',(int)$a['id'])['noindex']))$emit('blog/'.$a['slug'],$a['updated_at']);
            foreach(records('projects') as $p){$m=meta('projects',(int)$p['id']);if(($m['published']??1)&&empty($m['noindex']))$emit('projeler/'.$p['id'].'-'.slugify($p['name']));}
        }
        if($part!=='base')foreach(page_selection(1000,$part===''?0:(max(1,(int)$part)-1)*1000) as $p)if(empty(meta('pages',(int)$p['id'])['noindex']))$emit('cozumler/'.$p['slug'],$p['updated_at']);
    }
    echo '</urlset>';return true;
}
function admin_page_rows(string $table,int $size=100): array {
    if(!in_array($table,['cities','pages'],true))throw new InvalidArgumentException('Geçersiz tablo');
    $q=is_string($_GET['q']??null)?mb_substr($_GET['q'],0,100):'';$state=is_string($_GET['state']??null)?$_GET['state']:'';$params=[];$where='1=1';
    if($q!==''){$column=$table==='pages'?'heading':'name';$where.=" AND ($column LIKE ? OR slug LIKE ?)";$params=['%'.$q.'%','%'.$q.'%'];}
    if($table==='pages'&&in_array($state,['draft','published'],true)){$where.=' AND status=?';$params[]=$state;}
    $total=(int)query("SELECT COUNT(*) FROM $table WHERE $where",$params)->fetchColumn();$max=max(1,(int)ceil($total/$size));$page=min($max,max(1,(int)($_GET['page']??1)));$offset=($page-1)*$size;
    $GLOBALS['adminPager']=['total'=>$total,'page'=>$page,'max'=>$max,'q'=>$q,'state'=>$state];return query("SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT $size OFFSET $offset",$params)->fetchAll();
}
function admin_pager(string $tab): void {
    $p=$GLOBALS['adminPager']??null;if(!$p)return;
    echo '<form method="get" class="server-search"><input type="hidden" name="tab" value="'.e($tab).'"><label>Tüm kayıtlarda ara<input type="search" name="q" value="'.e($p['q']).'" placeholder="Lokasyon, başlık veya URL"></label>';
    if($tab==='pages')ui_select('Yayın durumu','state',[''=>'Tümü','draft'=>'Taslak','published'=>'Yayında'],$p['state']);
    echo '<button class="button dark">Ara / filtrele</button></form><div class="table-pagination"><span>'.$p['total'].' sonuç · '.$p['page'].' / '.$p['max'].'</span>';
    foreach(['← Önceki'=>max(1,$p['page']-1),'Sonraki →'=>min($p['max'],$p['page']+1)] as $label=>$page)echo '<a href="/yonetim?'.e(http_build_query(['tab'=>$tab,'q'=>$p['q'],'state'=>$p['state'],'page'=>$page])).'">'.$label.'</a>';
    echo '</div>';
}
