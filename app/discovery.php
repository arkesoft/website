<?php
declare(strict_types=1);

function get_text(string $key,int $max=100): string {return is_string($_GET[$key]??null)?mb_substr(trim($_GET[$key]),0,$max):'';}

function growth_article_author(array $article): array {
    $authorId=(int)(meta('articles',(int)$article['id'])['author_id']??0);
    $author=$authorId&&growth_ready()?query("SELECT * FROM authors WHERE id=? AND status='published'",[$authorId])->fetch():null;
    if($author)return growth_author_schema($author);
    // Existing editorial bylines are preserved without inventing a person/profile.
    return ['@type'=>str_contains(mb_strtolower($article['author']),mb_strtolower(settings()['brand']))?'Organization':'Person','name'=>$article['author']];
}

function growth_author_schema(array $author): array {
    $row=['@type'=>$author['kind']==='Organization'?'Organization':'Person','name'=>$author['name'],'url'=>url('yazar/'.$author['slug'])];
    if($author['profile_url'])$row['sameAs']=[$author['profile_url']];
    if($image=media_url((int)$author['image_id']))$row['image']=url($image);
    return $row;
}

function growth_article_query(array $filters=[],int $limit=12,int $offset=0,bool $count=false): array|int {
    if(!cms_ready())return $count?0:[];
    $where="a.status='published' AND a.published_at<=?";$params=[gmdate('c')];
    if(!empty($filters['q'])){$where.=' AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ?)';$q='%'.$filters['q'].'%';array_push($params,$q,$q,$q);}
    if(!empty($filters['category'])){$where.=' AND a.category=?';$params[]=$filters['category'];}
    if(!empty($filters['tag'])&&growth_ready()){$where.=' AND EXISTS (SELECT 1 FROM article_tags t WHERE t.article_id=a.id AND t.slug=?)';$params[]=$filters['tag'];}
    if(!empty($filters['author'])&&growth_ready()){
        // Author relationships are normalized into tags reserved for internal selection.
        $where.=' AND EXISTS (SELECT 1 FROM article_tags t WHERE t.article_id=a.id AND t.slug=?)';$params[]='author-'.$filters['author'];
    }
    if($count)return (int)query("SELECT COUNT(*) FROM articles a WHERE $where",$params)->fetchColumn();
    $limit=max(1,min(1000,$limit));$offset=max(0,$offset);
    return query("SELECT a.* FROM articles a WHERE $where ORDER BY a.published_at DESC,a.id DESC LIMIT $limit OFFSET $offset",$params)->fetchAll();
}

function growth_blog_data(array $fixed=[]): array {
    $filters=$fixed+['q'=>get_text('q'),'category'=>get_text('kategori'),'tag'=>get_text('etiket')];
    $raw=get_text('sayfa');if($raw!==''&&(!ctype_digit($raw)||(int)$raw<1))not_found();
    $page=max(1,(int)($raw?:1));$count=growth_article_query($filters,12,0,true);$last=max(1,(int)ceil($count/12));if($page>$last)not_found();
    return ['filters'=>$filters,'page'=>$page,'last'=>$last,'count'=>$count,'rows'=>growth_article_query($filters,12,($page-1)*12)];
}

function growth_blog_view(array $data=[]): void {
    $listing=$data['listing']??growth_blog_data();$filters=$listing['filters'];
    echo '<section class="inner-section inner-cream journal-list"><form method="get" action="/blog" class="growth-search"><label>Yazılarda ara<input type="search" name="q" value="'.e($filters['q']??'').'" maxlength="100"></label><label>Konu<select name="kategori"><option value="">Tüm konular</option>';
    if(cms_ready())foreach(query("SELECT DISTINCT category FROM articles WHERE status='published' AND published_at<=? ORDER BY category",[gmdate('c')])->fetchAll(PDO::FETCH_COLUMN) as $category)echo '<option '.(($filters['category']??'')===$category?'selected':'').'>'.e($category).'</option>';
    echo '</select></label><button class="studio-button">Ara</button><a href="/blog">Filtreleri temizle</a></form><p class="growth-result">'.(int)$listing['count'].' yazı</p>';
    if(!empty($filters['tag']))echo '<p>Etiket: '.e($filters['tag']).'</p>';
    agency_articles(0,$listing['rows']);
    if(!$listing['rows'])echo '<p>Bu aramada yazı bulunamadı. Farklı bir kelime deneyebilirsiniz.</p>';
    growth_pagination('/blog',$listing,['q'=>$filters['q']??'','kategori'=>$filters['category']??'','etiket'=>$filters['tag']??'']);echo '</section>';
}

function growth_pagination(string $path,array $listing,array $filters=[]): void {
    if($listing['last']<2)return;
    echo '<nav class="directory-pagination" aria-label="Sayfalama">';
    foreach(['Önceki'=>$listing['page']-1,'Sonraki'=>$listing['page']+1] as $label=>$page)if($page>=1&&$page<=$listing['last'])echo '<a href="'.e($path.'?'.http_build_query(array_filter($filters,fn($v)=>$v!=='')+['sayfa'=>$page])).'">'.e($label).'</a>';
    echo '<span aria-current="page">Sayfa '.(int)$listing['page'].' / '.(int)$listing['last'].'</span></nav>';
}

function growth_author_view(array $data): void {
    $a=$data['author'];echo '<section class="inner-section inner-cream author-profile">';growth_breadcrumb_html();
    if($a['image_id'])echo media_image((int)$a['image_id'],'author-portrait',true);
    echo '<h1>'.e($a['name']).'</h1><p>'.e($a['role']).'</p><div class="prose">'.prose($a['bio']).'</div>';
    if($a['profile_url'])echo '<a class="text-link" href="'.e($a['profile_url']).'" target="_blank" rel="noopener noreferrer">Resmî profili <span class="sr-only">(yeni sekme)</span> ↗</a>';
    echo '</section><section class="inner-section inner-cream journal-list"><h2>Yazıları</h2>';agency_articles(0,$data['listing']['rows']);growth_pagination('/yazar/'.$a['slug'],$data['listing']);echo '</section>';
}

function growth_article_details(array $a): void {
    $meta=meta('articles',(int)$a['id']);$author=growth_article_author($a);
    echo '<section class="inner-section inner-cream article-details"><h2>Yazar hakkında</h2><p>';
    if(!empty($author['url']))echo '<a href="'.e($author['url']).'">'.e($author['name']).'</a>';else echo e($author['name']);
    echo '</p>';
    if(!empty($meta['author_id'])&&growth_ready()){$row=query("SELECT bio,role FROM authors WHERE id=? AND status='published'",[(int)$meta['author_id']])->fetch();if($row)echo '<p>'.e($row['role']).'</p><div class="prose">'.prose($row['bio']).'</div>';}
    if(growth_ready())foreach(query("SELECT slug,label FROM article_tags WHERE article_id=? AND slug NOT LIKE 'author-%'",[$a['id']])->fetchAll() as $tag)echo '<a class="growth-tag" href="/blog?etiket='.e(rawurlencode($tag['slug'])).'">'.e($tag['label']).'</a>';
    echo '</section>';
    $related=array_values(array_filter(growth_article_query(['category'=>$a['category']],4),fn($row)=>(int)$row['id']!==(int)$a['id']));
    if($related){echo '<section class="inner-section inner-cream journal-list"><h2>İlgili rehberler</h2>';agency_articles(3,$related);echo '</section>';}
}

function growth_related_services(array $extra): void {
    $ids=$extra['service_ids']??[];if(!$ids)return;
    echo '<nav class="growth-tags" aria-label="İlgili hizmetler">';foreach(public_services() as $s)if(in_array((int)$s['id'],$ids,true))echo '<a class="growth-tag" href="/hizmetler/'.e($s['slug']).'">'.e($s['name']).' ↗</a>';echo '</nav>';
}

function growth_service_evidence(array $service): void {
    $id=(int)$service['id'];$projects=array_values(array_filter(records('projects'),fn($row)=>(meta('projects',(int)$row['id'])['published']??1)&&in_array($id,meta('projects',(int)$row['id'])['service_ids']??[],true)));
    $articles=array_values(array_filter(growth_article_query([],1000),fn($row)=>in_array($id,meta('articles',(int)$row['id'])['service_ids']??[],true)));
    $quotes=array_values(array_filter(studio_items('testimonials'),fn($row)=>in_array($id,meta('testimonials',(int)$row['id'])['service_ids']??[],true)));
    if($projects){echo '<section class="inner-section inner-cream journal-list"><h2>Bu alandaki çalışmalar</h2>';agency_projects(6,$projects);echo '</section>';}
    if($articles){echo '<section class="inner-section inner-cream journal-list"><h2>Projenizi planlarken</h2>';agency_articles(3,$articles);echo '</section>';}
    if($quotes){echo '<section class="inner-section inner-cream journal-list"><h2>Deneyimler ve senaryolar</h2><div class="growth-quotes">';foreach($quotes as $q){$m=meta('testimonials',(int)$q['id']);echo '<figure><blockquote>'.e($q['quote']).'</blockquote><figcaption>'.e($q['name']).' · '.e($q['role']).'</figcaption><p>'.(!empty($m['verified'])?'İzinli müşteri yorumu':'Örnek ihtiyaç senaryosu').'</p>';if(!empty($m['evidence_url']))echo '<a href="'.e($m['evidence_url']).'" target="_blank" rel="noopener noreferrer">Kaynağı incele ↗</a>';echo '</figure>';}echo '</div></section>';}
    $support=meta('services',$id)['support_scope']??settings()['support_scope'];
    if($support)echo '<section class="inner-section inner-cream journal-list"><h2>Teslimden sonra</h2><div class="prose">'.prose($support).'</div></section>';
    echo '<section class="inner-section inner-cream growth-service-cta"><h2>İhtiyacınızı birlikte netleştirelim.</h2><a class="studio-button" href="'.e(growth_contact_url($service)).'">'.e($service['name']).' için görüşelim ↗</a><a class="outline-pill" href="/ihtiyacini-belirle?hizmet='.e($service['slug']).'">İhtiyacımı belirleyeyim</a></section>';
}

function growth_project_gallery(array $project,array $extra): void {
    if(!empty($extra['gallery_ids'])){
        echo '<section class="inner-section inner-cream journal-list"><h2>Projeye yakından bakın</h2><div class="growth-gallery">';
        foreach($extra['gallery_ids'] as $id)if($image=cms_record('media',(int)$id))echo '<figure><a href="'.e(media_url((int)$id)).'" target="_blank" rel="noopener" aria-label="'.e(($image['alt']?:$project['name']).' — tam boyutu yeni sekmede aç').'">'.media_image((int)$id).'</a><figcaption>'.e($image['alt']).'</figcaption></figure>';
        echo '</div></section>';
    }
    if(!empty($extra['evidence_url'])&&empty($project['is_demo']))echo '<section class="inner-section inner-cream journal-list"><h2>Sonucun kaynağı</h2><a class="text-link" href="'.e($extra['evidence_url']).'" target="_blank" rel="noopener noreferrer">Sonucu incele ↗</a></section>';
    echo '<section class="inner-section inner-cream journal-list">';growth_related_services($extra);echo '</section>';
}

function growth_discovery_view(): void {
    $selected=get_text('hizmet');echo '<section class="inner-section inner-cream discovery-page">';growth_breadcrumb_html();
    echo '<h1>İhtiyacınızı netleştirelim.</h1><p>Birkaç kısa seçimle görüşme için bir başlangıç hazırlayın. Kesin kapsam ve teklif birlikte belirlenir.</p><form action="/iletisim" method="get" class="growth-discovery"><fieldset><legend>1. Neye ihtiyacınız var?</legend><label>Hizmet<select name="hizmet"><option value="">Birlikte belirleyelim</option>';
    foreach(public_services() as $s)echo '<option value="'.e($s['slug']).'" '.($selected===$s['slug']?'selected':'').'>'.e($s['name']).'</option>';
    echo '</select></label><label>Başlangıç noktanız<select name="durum"><option>Yeni bir proje</option><option>Mevcut yapıyı yenilemek</option><option>Bir süreci otomatikleştirmek</option></select></label></fieldset><fieldset><legend>2. Önceliğiniz nedir?</legend><label>Hedef<select name="hedef"><option>Daha fazla müşteriye ulaşmak</option><option>Güven veren bir görünüm</option><option>İş yükünü azaltmak</option><option>Çevrim içi satış yapmak</option></select></label></fieldset><fieldset><legend>3. Ne zaman başlamayı düşünüyorsunuz?</legend><label>Zamanlama<select name="zaman"><option>Birlikte planlayalım</option><option>Bu ay</option><option>1–3 ay içinde</option><option>Ön araştırma yapıyorum</option></select></label></fieldset><button class="studio-button">Özeti hazırla ve iletişime geç ↗</button></form></section>';
}
