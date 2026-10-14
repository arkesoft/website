# ARKESOFT CMS 5 — kullanım ve Hostinger kurulumu

PHP 8.2+ ve MySQL/MariaDB ile çalışır. VPS, Node.js, npm, Composer veya SSH gerekmez. Yönetim paneli veri kaydettiği için yalnızca statik dosya kabul eden hosting yeterli değildir. Hostinger hesabında PHP ve MySQL bulunan Web Hosting kullanılmalıdır.

## Bilgisayarında aç

BASLAT.bat dosyasına çift tıkla. Açılan pencereyi açık tut.

- Site: http://localhost:8127/
- Yönetim: http://localhost:8127/yonetim
- Mevcut yerel hesabın korunmuştur. Canlı hosting kurulumu için yeni hesap oluşturulur.
- Sunucu zaten açıksa ikinci kez başlatma. Görünümü Ctrl+F5 ile yenile.

Yerel kayıtlar storage/local.sqlite içinde, yerel bağlantı storage/local-config.php içindedir. Bu dosyalar Hostinger paketine girmez. Yerel ortam SQLite, hosting MySQL kullanır.

## Hostinger'a yükle

1. hPanel'de PHP 8.2 veya üzerini seç. pdo_mysql, mbstring, session, fileinfo ve WebP destekli gd açık olmalı. Video yüklemek için upload_max_filesize en az 32 MB, post_max_size en az 40 MB olmalı.
2. Alan adını bağla, SSL'i etkinleştir ve HTTPS zorlamasını aç.
3. Boş bir MySQL veritabanı ve kullanıcı oluştur. hPanel'deki tam adları, sunucuyu ve şifreyi not et.
4. arkesoft-hostinger.zip dosyasını public_html içine yükleyip çıkar. index.php, .htaccess, app, assets, storage ve uploads doğrudan bu klasörde olmalı.
5. config.example.php dosyasını config.php adıyla kopyala. site_url alanına gerçek HTTPS alan adını, diğer alanlara MySQL bağlantısını yaz. Site kök alan adına kurulur; alt klasöre kurulmaz.
6. setup_token için en az 32 karakterlik rastgele özel bir değer belirle.
7. https://alanadiniz.com/kurulum adresini aç. Kurulum anahtarı, yönetici e-postası ve en az 12 karakterlik şifreyle kurulumu tamamla.
8. https://alanadiniz.com/yonetim adresinden giriş yap. Ana sitede giriş bağlantısı yoktur. Kurulum ilk hesaptan sonra kilitlenir. setup_token değerini boşaltabilirsin; config.php dosyasını silme.

Paket uygulama kodu ve başlangıç içeriklerini içerir; bilgisayarındaki veritabanını, şifreleri, test dosyalarını veya yüklediğin kişisel görselleri içermez. Yerelde sonradan girilen içerikleri canlıya taşımak ayrı veri taşıma işlemidir. Mevcut canlı siteyi güncellerken önce dosya ve MySQL yedeği al; config.php ve uploads içeriğini koru.

## Yönetim modülleri

| Bölüm | İşlev |
| --- | --- |
| Genel bakış | Gerçek içerik/talep sayıları ve yayın hazırlığı |
| Sayfa tasarımları | Sekiz sayfanın bağımsız yerleşimi, açılış başlığı, giriş metni ve görseli |
| Ana sayfa & marka | Hero, görseller, tanıtım, CTA, sosyal hesaplar |
| Vitrin & video ayarları | Açılış/tanıtım videosu, bölüm metinleri, demo açıklaması |
| Renkli hizmet blokları | Başlık, görsel, renk, düzen, bağlantı, sıra ve görünürlük |
| Marka vitrini | Konsept markalar, logo, açıklama ve sıra |
| Yorumlar / senaryolar | İsim, açıklama, metin, görsel ve yayın durumu |
| Video kütüphanesi | MP4/WebM yükleme, kapak, açıklama ve silme |
| Hizmet kategorileri | Üst menü ve hizmet grupları |
| Hizmetler | Altı ayrı hizmet şablonu, içerik, kapsam, süreç, hedef kitle, görsel, SEO |
| Projeler | Üç proje anlatımı, ihtiyaç, çözüm, sonuç, görsel ve konsept etiketi |
| Blog & içgörüler | Yazar, kaynak, görsel, taslak, tarihli yayın |
| Sık sorulan sorular | Genel veya belirli hizmete bağlı soru/yanıt |
| Medya kütüphanesi | Görsel yükleme, WebP optimizasyonu, alt metin |
| Menü yönetimi | Site içi bağlantılar ve sıralama |
| Aktif lokasyonlar | Hedef bölgeler, yerel bağlam, CSV |
| Türkiye kataloğu | 81 il, 973 ilçe, 32.279 mahalle ve toplu üretim |
| PSEO sayfaları | Arama, düzenleme, önizleme, toplu yayın/taslak |
| PSEO üretici | Hizmet × lokasyon taslakları, içerik şablonları |
| SEO denetimi | Başlık, içerik, inceleme ve teknik kontroller |
| 301 yönlendirmeler | Eski URL → yeni URL ve döngü kontrolü |
| Teklif & müşteri takibi | Gelen formlar, durumlar, notlar, CSV |
| İçerik geçmişi | Düzenleme öncesi sürümleri görme ve geri alma |
| Kullanıcılar & roller | Yönetici ve editör hesapları |
| Yedek & işlem kaydı | İçerik JSON dışa aktarımı, işlem geçmişi |
| Genel ayarlar | İşletme bilgileri, SEO, gizlilik ve şifre |

Editör içerik, medya ve PSEO yönetir; kullanıcılar, müşteri mesajları, genel ayarlar ve yedeklere erişemez. Yetki sunucuda kontrol edilir. JSON dışa aktarımı tam sistem yedeği değildir; parola ve görsel dosyaları dahil değildir. Tam geri yükleme için hPanel dosya + veritabanı yedeğini kullan.

Başlangıçta 12 hizmet, 6 konsept proje/marka, 4 renkli hizmet bölümü, 3 editoryal rehber, 3 örnek ihtiyaç senaryosu, 3 demo teklif ve 6 noindex demo PSEO sayfası bulunur. İki WebM video da pakete dahildir: 8 saniyelik sessiz açılış ve 16 saniyelik konsept sunumu. Bunlar konsept görsellerinden hazırlanmış hareketli sunumlardır. Gerçek müşteri işi, çekilmiş ofis videosu veya müşteri yorumu iddiası içermez.

Örnek e-posta adresi merhaba@arkesoft.example çalışmaz; kendi adresinle değiştir. Telefon, sosyal hesaplar ve veri sorumlusu bilgilerini yayından önce tamamla. Form talepleri panelde kaydedilir; otomatik e-posta gönderilmez. Demo açıklamasını ancak örnek içerikleri doğrulanmış işletme bilgilerinle değiştirdiğinde kaldır.

## Sayfaları ayrı ayrı tasarla

Yönetim → Site içeriği → Sayfa tasarımları bölümünden Hizmetler, Projeler, Hakkımızda, İletişim, Blog, Yerel çözümler, Gizlilik ve 404 ekranlarını seç. Bir sayfanın seçimi diğerini değiştirmez. Hizmet dizini/kartlar, asimetrik portföy/ızgara, manifesto/panorama, yan yana iletişim/form öncelikli, dergi/editoryal liste ve lokasyon dizini/kompakt rehber alternatifleri vardır. Gizlilik ve 404 kendi amacına uygun tek düzen kullanır.

Hizmetler → Düzenle → Sayfa şablonu: Web, Yazılım, Mobil, Marka, E-ticaret veya Büyüme. Otomatik seçim hizmete göre uygun tasarımı kullanır. Görsel, içerik, kapsam ve süreç aynı editörden kaydedilir. Projelerde editoryal anlatım, ürün vitrini ve ürün arayüzü seçilebilir. Blog yazıları okuma, PSEO sayfaları yerel kapsam düzenindedir. Başlangıç içerikleri bu şablonları doldurur; aynı kapak kompozisyonu bütün sayfalara uygulanmaz.

Panel menüsü grupludur ve aranabilir. Mobilde menü düğmeyle açılır. Editörde kaydedilmemiş değişiklik uyarısı, kayıt çubuğu, görünür odak, parola göster/gizle ve ekranın amacını anlatan kısa açıklamalar vardır. Parola değişikliği diğer oturumları kapatır; bu sürüme geçişte mevcut oturumlar için yeniden giriş gerekir.

## Türkiye çapında PSEO

1. Türkiye kataloğunda il / ilçe / mahalle seviyesini seç. İl, ilçe veya isimle filtrele.
2. Seçilen kayıtları etkinleştir veya filtrenin tamamına toplu işlem uygula. Üst il ve ilçe ilişkileri otomatik kurulur. 81 il başlangıçta aktiftir; ilçeler ve mahalleler katalogdan seçilir.
3. “Hizmetlerle eşleştir ve PSEO taslakları üret” seçeneğini ve hizmetleri seç. Her istekte en fazla 40 kombinasyon işlenir; sekmeyi açık tut. Durdurma tamamlanmış kayıtları silmez. Yeniden başlatmak mevcut sayfaları korur.
4. Küçük seçimlerde PSEO üretici ekranını kullan: en fazla 300 kombinasyon, son 500 aktif lokasyon. Geniş kapsam Türkiye kataloğundaki üreticiden yönetilir.
5. Şablon değişkenleri: {{sehir}}, {{hizmet}}, {{marka}}, {{ozet}}, {{icerik}}, {{baglam}}, {{sektorler}}. Şablon değişikliği mevcut sayfaları yeniden yazmaz.
6. Üretilen sayfa taslaktır. Sayfaya özgü yerel ihtiyaç, doğrulanabilir kapsam veya örnek ekle; içeriği incele, inceleme kutusunu işaretle ve yayınla. Minimum karakter kontrolleri uygulama doğrulamasıdır; Google kalite puanı değildir.
7. Adres örnekleri: /cozumler/istanbul-web-tasarim ve /cozumler/istanbul-kadikoy-web-tasarim. Mahalle adresleri üst il ve ilçeyi de içerir.
8. Semtler tek tip resmî idari birim değildir. Katalogda olmayan semti Aktif lokasyonlar bölümünden ekle; semt/mahalle seviyesini ve bağlı ilçe ID'sini seç.

Coğrafi veri gerçek ofis veya hizmet kapasitesi iddiası değildir. Verideki tüm mahallelerin güncel/tam olduğu varsayılmamalıdır; yerel bilgiyi doğrula. Kaynak bildirimi app/data/SOURCE.txt içindedir. Toplu üretim kapasitesi hostingin disk, veritabanı ve kaynak sınırlarına bağlıdır. Tüm kombinasyonlar otomatik yayımlanmaz.

## Arama görünürlüğü

- İçerik ve bağlantılar sunucuda oluşturulur; JavaScript'e bağımlı değildir.
- Canonical, meta açıklama, sosyal metalar, Organization/Service/BlogPosting/BreadcrumbList bulunur.
- Taslaklar ziyaretçiye 404 verir. Yönetici önizlemesi noindex ve no-store kullanır.
- İndeks izni açık, yayındaki içerikler /sitemap.xml içinde yer alır. 1.000'den fazla PSEO sayfasında sitemap index ve numaralı parçalar oluşur.
- SEO sayfaları ve aktif lokasyonlarda sunucu tarafı arama/sayfalama vardır. Genel SEO denetimi son 100 sayfayı gösterir; her sayfanın düzenleme ekranında kendi kontrolleri vardır.
- Search Console doğrulama kodunu panelden ekleyebilirsin. Canlı mülküne sitemap adresini ayrıca gönder.
- Trafik, sıralama ve indekslenme panelde ölçülmez. Birinci sıra garantisi yoktur. Araştırma kaynakları SEO-ARASTIRMA.md dosyasındadır.

## Medya, güvenlik ve bakım

Görseller en fazla 5 MB / 25 megapiksel olabilir. Sistem dosya içeriğini kontrol eder, en büyük kenarı 2.400 px'e indirir ve WebP olarak yeniden kodlar. SVG ve çalıştırılabilir dosya yüklenmez. Kullanılan görsel önce içerikten çıkarılmadan silinmez.

Videolar en fazla 30 MB MP4/WebM olabilir; yeniden kodlama yapılmaz. H.264 MP4 veya VP8/VP9 WebM kullan. Açılış videosu sessiz oynar ve durdurulabilir; hareket azaltma tercihi veya veri tasarrufunda otomatik başlamaz. Tanıtım videosu düğmeyle açılır. Vitrinde kullanılan videonun silinmesi engellenir. Video tarayıcıda desteklenmezse açılışın kapak görseli görünür.

Parolalar password_hash ile saklanır. CSRF, giriş/form hız sınırı ve rol kontrolü vardır. İki saat hareketsizlik oturumu sonlandırır. HTTPS çerezi Secure, HttpOnly ve SameSite=Lax kullanır. Ayrı URL tek başına güvenlik değildir.

app, storage, tests, tools ve bağlantı dosyaları dış erişime kapalıdır. storage ve uploads yazılabilir olmalı; genellikle klasör 755 / dosya 644 yeterlidir. Düzenli MySQL + dosya yedeği al.

Alt sayfalar 404 ise .htaccess; 500 için PHP uzantıları, MySQL bağlantısı ve storage/error.log; yükleme sorunu için GD/fileinfo ve PHP yükleme sınırlarını kontrol et.

Tasarım, CSS/SVG arayüzleri ve uygulama kodu bu proje için hazırlanmıştır. Üç konsept fotoğraf yerleşik imagegen ile üretildi; yerel proje içindeki docs/image-prompts-v3.txt üretim yönergelerini içerir. Ücretli tema, eklenti, stok görsel, harici font, lisans anahtarı veya abonelik yoktur. Yalnızca coğrafi ad/ilişki verisi açık MIT kaynaklıdır; kaynak bildirimi korunmuştur. Çalışma zamanında harici API gerekmez.
