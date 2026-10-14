<?php
declare(strict_types=1);
require_once __DIR__.'/page-openings.php';
function studio_cover(string $name): int {
 if(!cms_ready())return 0;
 return (int)query('SELECT id FROM media WHERE filename=? LIMIT 1',['asset:'.$name.'-v3.'.(in_array($name,['flow','mobile'],true)?'svg':'webp')])->fetchColumn();
}
function inner_hero(string $label,string $title,string $text='',int $image=0,string $caption='',string $parent='/hizmetler',string $parentLabel='Hizmetler'): void { ?>
 <section class="inner-hero <?=$image?'has-art':''?>"><div class="inner-hero-copy"><nav class="breadcrumbs" aria-label="İçerik yolu"><a href="/">Ana sayfa</a><span aria-hidden="true">/</span><a href="<?=e($parent)?>"><?=e($parentLabel)?></a></nav><span class="studio-eyebrow"><?=e($label)?></span><h1><?=e($title)?></h1><?php if($text): ?><p><?=e($text)?></p><?php endif; ?></div><?php if($image): ?><figure class="inner-hero-art"><?=media_image($image,'',true)?><figcaption><?=e($caption?:'ARKESOFT / KONSEPT SEÇKİSİ')?></figcaption></figure><?php endif; ?></section>
<?php }
function inner_links(array $pages,?array $service=null): void { ?>
 <section class="inner-section related-section"><div class="inner-section-title"><span class="studio-eyebrow">BİR SONRAKİ ADIM</span><h2>İlgili çözümler.</h2></div><div class="location-links"><?php foreach($pages as $p): ?><a href="/cozumler/<?=e($p['slug'])?>"><?=e($p['heading'])?><span aria-hidden="true">↗</span></a><?php endforeach; ?><?php if(!$pages): ?><a href="<?=e($service?'/hizmetler/'.$service['slug']:'/hizmetler')?>"><?=e($service?$service['name'].' kapsamını inceleyin':'Tüm hizmetleri inceleyin')?><span aria-hidden="true">↗</span></a><a href="/iletisim">İhtiyacınızı birlikte netleştirelim <span aria-hidden="true">↗</span></a><?php endif; ?></div></section>
<?php }
function studio_inner(string $view,array $data,array $s,array $extra): void {
 if(in_array($view,['services','category'],true)) {
  $category=$data['category']??null;
  varied_opening($view,$data,$s,$extra);
  $groups=$category?[$category]:cms_records('categories');
  foreach($groups as $i=>$group): ?><section id="expertise-<?=$group['id']?>" class="expertise-band expertise-<?=['red','cream','yellow'][$i%3]?>"><div class="expertise-heading"><span class="studio-eyebrow"><?=sprintf('%02d',$i+1)?> / UZMANLIK</span><h2><?=e($group['name'])?></h2><p><?=e($group['description'])?></p></div><div class="expertise-list"><?php foreach(category_services((int)$group['id']) as $service): ?><a href="/hizmetler/<?=e($service['slug'])?>"><div><h3><?=e($service['name'])?></h3><p><?=e($service['summary'])?></p></div><span aria-hidden="true">↗</span></a><?php endforeach; ?></div></section><?php endforeach;
  agency_process();
 } elseif($view==='service') {
  $service=$data['service'];varied_opening($view,$data,$s,$extra); ?>
  <div class="service-body service-body-<?=e(service_layout($service,$extra))?>"><nav class="inner-anchor-nav" aria-label="Bu sayfada"><a href="#yaklasim">Yaklaşım</a><a href="#kapsam">Hizmet kapsamı</a><a href="#surec">Süreç</a><a href="/iletisim">Projenizi konuşalım ↗</a></nav>
  <section class="inner-section editorial-split" id="yaklasim"><aside><span class="studio-eyebrow">ÖNCE İHTİYACI ANLAYALIM</span><h2>İşinize göre.<br>Size göre.</h2><p><?=e($extra['audience']??'Kapsamı, işinizin gerçek ihtiyaçlarına göre birlikte belirliyoruz.')?></p></aside><article class="prose"><?=prose($service['body'])?></article></section>
  <section class="inner-section inner-cream" id="kapsam"><div class="inner-section-title"><span class="studio-eyebrow">HİZMET KAPSAMI</span><h2>Birlikte neler yapabiliriz?</h2><p>Kesin kapsamı, öncelikleri ve teslimleri proje başlangıcında netleştiriyoruz.</p></div><div class="deliverable-grid"><?php foreach(lines($service['deliverables']) as $i=>$line): ?><article><span><?=sprintf('%02d',$i+1)?></span><h3><?=e($line)?></h3><b aria-hidden="true">↗</b></article><?php endforeach; ?></div></section>
  <section class="inner-section" id="surec"><div class="inner-section-title"><span class="studio-eyebrow">ADIM ADIM</span><h2>Ne yaptığımız belli olsun.</h2></div><div class="process-rows"><?php foreach(lines($extra['process']??"Keşif ve kapsam\nPlanlama ve tasarım\nGeliştirme ve kontrol\nYayın ve teslim") as $i=>$step): ?><div><span><?=sprintf('%02d',$i+1)?></span><h3><?=e($step)?></h3><i aria-hidden="true">↗</i></div><?php endforeach; ?></div></section>
  <?php agency_faq((int)$service['id']);inner_links(page_selection(12,0,'service_id=?',[$service['id']]),$service);echo '</div>';
 } elseif($view==='projects') {
  varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section portfolio-section"><h2 class="sr-only">Proje seçkisi</h2><div class="project-filter" role="group" aria-label="Projeleri kategoriye göre filtrele"><button class="active" type="button" aria-pressed="true" data-project-filter="all">Tüm çalışmalar</button><?php foreach(array_unique(array_column(records('projects'),'category')) as $cat): ?><button type="button" aria-pressed="false" data-project-filter="<?=e($cat)?>"><?=e($cat)?></button><?php endforeach; ?></div><p class="filter-result" data-project-result role="status" aria-live="polite"></p><?php agency_projects(); ?></section>
 <?php } elseif($view==='project') {
  $p=$data['project'];varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section project-narrative"><?php foreach(['challenge'=>'İhtiyaç','solution'=>'Yaklaşım','result'=>'Sonuç'] as $key=>$label): ?><article><h2><?=e($label)?></h2><div class="prose"><?=prose($extra[$key]??$p['description'])?></div></article><?php endforeach; ?><?php if($p['url']): ?><a class="outline-pill" href="<?=e($p['url'])?>" target="_blank" rel="noopener">Projeyi ziyaret et <span class="sr-only">(yeni sekme)</span> ↗</a><?php endif; ?><a class="outline-pill" href="/projeler">Diğer projelere dön ↗</a></section>
 <?php } elseif($view==='about') {
  varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section editorial-split"><aside><span class="studio-eyebrow">YAKLAŞIMIMIZ</span><h2>İyi bir iş,<br>karşılıklı anlayış.</h2></aside><article class="prose"><?=prose($s['about'])?></article></section>
  <?php agency_process();agency_faq();
 } elseif($view==='contact') {
  varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section contact-layout"><div class="contact-copy"><span class="studio-eyebrow">BİZE ULAŞIN</span><h2>İlk adım<br>bir merhaba.</h2><p>İhtiyacınızı okur, kapsamı ve sonraki adımları sizinle netleştiririz.</p><?php foreach(['email','phone','address'] as $key):if(!$s[$key])continue; ?><div class="contact-line"><small><?=e(['email'=>'E-POSTA','phone'=>'TELEFON','address'=>'ÇALIŞMA BİÇİMİ / ADRES'][$key])?></small><?php if($key==='email'): ?><a href="mailto:<?=e($s[$key])?>"><?=e($s[$key])?> ↗</a><?php elseif($key==='phone'): ?><a href="tel:<?=e(preg_replace('/[^+0-9]/','',$s[$key]))?>"><?=e($s[$key])?></a><?php else: ?><p><?=e($s[$key])?></p><?php endif; ?></div><?php endforeach; ?><ol class="contact-next"><li>İhtiyacınızı paylaşın.</li><li>Kapsamı birlikte netleştirelim.</li><li>Teslimleri ve takvimi planlayalım.</li></ol></div><?php contact_form(); ?></section>
 <?php } elseif($view==='blog') {
  varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section inner-cream journal-list"><h2 class="sr-only">Rehberler ve yazılar</h2><?php agency_articles();if(!published_articles()): ?><p>Yeni rehberler hazırlanıyor. Projenizle ilgili sorular için <a href="/iletisim">bize yazabilirsiniz</a>.</p><?php endif; ?></section>
 <?php } elseif($view==='article') {
  $a=$data['article'];varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section reading-layout inner-cream"><aside class="reading-meta"><strong><?=e($a['author'])?></strong><span>Yayın: <?=e(date('d.m.Y',strtotime($a['published_at'])))?></span><span>Güncelleme: <?=e(date('d.m.Y',strtotime($a['updated_at'])))?></span><a href="/blog">Tüm yazılar ↗</a></aside><article class="prose"><?=prose($a['body'])?><?php if($a['sources']): ?><h2>Kaynaklar</h2><ul><?php foreach(lines($a['sources']) as $source):if(!safe_web_url($source))continue; ?><li><a href="<?=e($source)?>" target="_blank" rel="noopener noreferrer"><?=e(parse_url($source,PHP_URL_HOST).parse_url($source,PHP_URL_PATH))?><span class="sr-only"> (yeni sekme)</span></a></li><?php endforeach; ?></ul><?php endif; ?></article></section>
 <?php } elseif($view==='landing') {
  $p=$data['page'];$svc=record('services',(int)$p['service_id']);$city=record('cities',(int)$p['city_id']);varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section editorial-split"><aside class="landing-aside"><span class="studio-eyebrow">KAPSAMI BİRLİKTE BELİRLEYELİM</span><h2>İşinize uyan<br>bir başlangıç.</h2><a href="/iletisim" class="studio-button">Projenizi konuşalım ↗</a><a href="/hizmetler/<?=e($svc['slug']??'')?>" class="text-link"><?=e($svc['name']??'Hizmet')?> ayrıntıları ↗</a><?php if(!empty($extra['demo'])): ?><p class="concept-note">Örnek kapsam sayfasıdır. Gerçek müşteri işi veya bu lokasyonda fiziksel ofis iddiası içermez.</p><?php endif; ?></aside><article class="prose"><?=prose($p['body'])?><?php if(!empty($extra['unique_value'])): ?><h2>Bu lokasyona özel yaklaşım</h2><?=prose($extra['unique_value'])?><?php endif; ?><h2>Merak edilenler</h2><details open><summary><?=e($p['faq_question'])?></summary><p><?=e($p['faq_answer'])?></p></details></article></section>
  <?php inner_links(page_selection(12,0,'id<>? AND (city_id=? OR service_id=?)',[$p['id'],$p['city_id'],$p['service_id']]),$svc);
 } elseif($view==='solutions') {
  varied_opening($view,$data,$s,$extra);
  $page=max(1,(int)($_GET['sayfa']??1));$count=installed()?(int)query("SELECT COUNT(*) FROM pages WHERE status='published'")->fetchColumn():0;$last=max(1,(int)ceil($count/100)); ?>
  <section class="inner-section" id="local-directory"><label class="solution-search-label">Bu sayfadaki çözümlerde ara<input class="solution-search" type="search" data-solution-search placeholder="Hizmet veya lokasyon adı…"></label><p class="filter-result" data-solution-result role="status" aria-live="polite"></p><div class="location-links"><?php foreach(page_selection(100,($page-1)*100) as $p): ?><a data-solution href="/cozumler/<?=e($p['slug'])?>"><?=e($p['heading'])?> <span aria-hidden="true">↗</span></a><?php endforeach; ?></div><nav class="directory-pagination" aria-label="Çözüm sayfaları"><?php if($page>1): ?><a href="/cozumler?sayfa=<?=$page-1?>">← Önceki</a><?php endif; ?><span aria-current="page">Sayfa <?=$page?> / <?=$last?></span><?php if($page<$last): ?><a href="/cozumler?sayfa=<?=$page+1?>">Sonraki →</a><?php endif; ?></nav><p>İhtiyacınızı bulamadıysanız <a class="text-link" href="/iletisim">birlikte konuşalım ↗</a></p></section>
 <?php } elseif($view==='privacy') {
  varied_opening($view,$data,$s,$extra); ?>
  <article class="inner-section legal-body inner-cream"><div class="prose"><?=prose($s['privacy'])?></div></article>
 <?php } else {
  varied_opening($view,$data,$s,$extra); ?>
  <section class="inner-section error-links"><a href="/" class="studio-button">Ana sayfaya dön ↗</a><a href="/hizmetler" class="outline-pill">Hizmetleri incele ↗</a><a href="/iletisim" class="outline-pill">Bize ulaş ↗</a></section>
 <?php }
}
