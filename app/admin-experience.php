<?php
declare(strict_types=1);
function admin_navigation(array $tabs,string $tab,int $newLeads): void {
 $groups=['Çalışma alanı'=>['dashboard','leads'],'Site içeriği'=>['services','projects','articles','faqs','categories'],'Ana sayfa & medya'=>['branding','designs','studio','showcases','partners','testimonials','media','films','navigation'],'Arama & lokasyonlar'=>['pages','generate','locations-import','cities','seo','redirects'],'Yönetim'=>['users','revisions','backup','settings']]; ?>
 <aside class="admin-sidebar" aria-label="Yönetim gezintisi"><div class="admin-brand-row"><a class="logo" href="/yonetim" aria-label="ARKESOFT yönetim ana sayfası"><span class="admin-brand-icon" aria-hidden="true">a↗</span>arkesoft<span class="cms-label">YÖNETİM</span></a><button class="admin-menu-toggle" type="button" data-admin-menu aria-expanded="false" aria-controls="admin-navigation">Menü ☰</button></div><div class="admin-nav-region" id="admin-navigation"><label class="admin-nav-search">Menüde ara<input type="search" data-nav-search placeholder="Örn. projeler, video…" autocomplete="off"></label><nav aria-label="Yönetim menüsü"><?php foreach($groups as $label=>$keys):$allowed=array_filter($keys,fn($key)=>can_manage($key));if(!$allowed)continue; ?><details class="admin-nav-group" <?=in_array($tab,$keys,true)?'open':''?>><summary><?=e($label)?></summary><div><?php foreach($allowed as $key): ?><a href="/yonetim?tab=<?=$key?>" data-nav-item class="<?=$tab===$key?'active':''?>" <?=$tab===$key?'aria-current="page"':''?>><span><?=e($tabs[$key])?></span><?php if($key==='leads'&&$newLeads): ?><b aria-label="<?=$newLeads?> yeni talep"><?=$newLeads?></b><?php endif; ?></a><?php endforeach; ?></div></details><?php endforeach; ?></nav><p class="nav-search-result" data-nav-result role="status" aria-live="polite"></p><div class="sidebar-bottom"><a href="/" target="_blank" rel="noopener">Siteyi görüntüle ↗ <span class="sr-only">(yeni sekme)</span></a><form method="post"><?php action_fields('logout'); ?><button>Çıkış yap →</button></form></div></div></aside>
<?php }
function admin_quick_actions(): void { ?>
 <section class="admin-quick-actions" aria-labelledby="quick-title"><h2 id="quick-title">Bugün ne yapalım?</h2><div><?php foreach([['projects','/yonetim?tab=projects&new=1','Proje ekle','Yeni çalışmanızı görselleriyle anlatın.'],['articles','/yonetim?tab=articles&new=1','Yazı hazırlayın','Taslak oluşturun, hazır olunca yayınlayın.'],['leads','/yonetim?tab=leads','Taleplere bakın','Gelen mesajları ve sonraki adımları takip edin.'],['pages','/yonetim?tab=pages','SEO içeriklerini inceleyin','Lokasyona özel bilgi ekleyip yayınlayın.']] as [$area,$url,$title,$text]):if(!can_manage($area))continue; ?><a href="<?=e($url)?>"><span aria-hidden="true">↗</span><strong><?=e($title)?></strong><p><?=e($text)?></p></a><?php endforeach; ?></div></section>
<?php }
function admin_help(string $tab): string {
 return match($tab){
 'dashboard'=>'İçerikler, gelen talepler ve sıradaki işler. Başlamak için aşağıdan bir işlem seçin.',
 'designs'=>'Her sayfanın amacına uygun yerleşimi seçin. Başlık, giriş ve görseller bu sayfaya özel kaydedilir.',
 'branding'=>'Ana sayfanın açılış başlığını, tanıtım metnini ve marka bilgilerini değiştirin.',
 'studio'=>'Ana sayfa bölümlerinin başlıklarını ve kullanılacak videoları seçin.',
 'showcases'=>'Ana sayfadaki renkli hizmet bölümlerini düzenleyin; sıralama ve görünürlüğü belirleyin.',
 'partners'=>'Marka seçkisine isim veya logo ekleyin. Konsept markaları gerçek müşteri gibi sunmayın.',
 'testimonials'=>'Örnek ihtiyaç senaryolarını veya izinli, doğrulanmış müşteri yorumlarını yönetin.',
 'films'=>'MP4 veya WebM yükleyin. Ardından Vitrin & video ayarlarından nerede gösterileceğini seçin.',
 'media'=>'Görsel yükleyin ve görseli anlatan bir alt metin yazın. Sistem WebP kopyasını hazırlar.',
 'services'=>'Sunduğunuz hizmetleri, kapsamı, görselleri ve çalışma aşamalarını düzenleyin.',
 'projects'=>'Çalışmalarınızı ihtiyaç, yaklaşım ve sonuç bölümleriyle anlatın. Örneklerde konsept etiketini koruyun.',
 'articles'=>'Rehber ve haber hazırlayın. Taslak kaydedebilir veya ileri bir yayın tarihi seçebilirsiniz.',
 'faqs'=>'Soru ve yanıt ekleyin. Ana sayfa için Genel, hizmet sayfası için ilgili hizmeti seçin.',
 'categories'=>'Hizmetleri üst menüde ve liste sayfasında gösterilen gruplara ayırın.',
 'navigation'=>'Ana menüye site içi bağlantı ekleyin. Sıralamada küçük sayılar önce görünür.',
 'pages'=>'Önce sayfaya özel bilgi ekleyin, içeriği inceleyin, ardından yayınlayın. Taslaklar ziyaretçiye kapalıdır.',
 'generate'=>'1. Hizmetleri seçin. 2. Lokasyonları seçin. 3. Taslakları oluşturun. Oluşturulan içerikleri ayrıca inceleyin.',
 'locations-import'=>'Türkiye kataloğunu filtreleyin; bölgeleri etkinleştirin veya hizmetlerle eşleştirerek taslak üretin.',
 'cities'=>'Etkin il, ilçe ve mahalleleri yönetin. Yeni kaydı doğru üst lokasyona bağlayın.',
 'seo'=>'İndeks izinlerini ve içerik eksiklerini kontrol edin. Buradaki kontroller Google sıralama puanı değildir.',
 'redirects'=>'Değişen site adreslerini 301 ile yeni sayfaya yönlendirin. Her iki alan da / ile başlamalıdır.',
 'leads'=>'Yeni talepleri okuyun, takip durumunu değiştirin ve ekip için not ekleyin.',
 'users'=>'Yönetici tüm alanlara; editör içerik ve medya alanlarına erişir. Kişiye özel hesap oluşturun.',
 'revisions'=>'Önceki içerik sürümlerini inceleyin ve gerektiğinde geri alın.',
 'backup'=>'İçerik verilerini dışa aktarın ve işlem kaydını inceleyin. Tam yedek için hosting dosya ve veritabanı yedeğini kullanın.',
 'settings'=>'İletişim bilgilerini, gizlilik metnini ve hesap şifrenizi güncelleyin.',
 default=>'İçeriği düzenleyin ve değişikliklerinizi kaydedin.'};
}
