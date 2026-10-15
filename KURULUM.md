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
4. `php tools/build-server-tree.php` ile çalışma alanındaki `arkesoft-sunucu` klasörünü oluştur/güncelle. ZIP oluşturulmaz. İçindeki `public_html` ve `arkesoft-private` klasörlerini sunucuda yan yana yerleştir. Alan adının belge kökü yalnızca `public_html` olmalı. Özel klasörü public_html içine taşıma.
5. `arkesoft-private/config.php` içindeki site_url ve MySQL bağlantısını doldur. `arkesoft-private/.env` içindeki posta bilgilerini doldur. Gerçek config ilk üretimde hazırlanır; tekrar üretimde config ve env dosyaları korunur. Site kök alan adına kurulur; alt klasöre kurulmaz.
6. `config.php` içindeki `setup_token` ilk üretimde güvenli rastgele değerle doldurulur. Kurulum ekranında bunu kullan; paylaşma.
7. https://alanadiniz.com/kurulum adresini aç. Kurulum anahtarı, yönetici e-postası ve en az 12 karakterlik şifreyle kurulumu tamamla.
8. https://alanadiniz.com/yonetim adresinden giriş yap. Ana sitede giriş bağlantısı yoktur. Kurulum ilk hesaptan sonra kilitlenir. setup_token değerini boşaltabilirsin; config.php dosyasını silme.

Sunucu ağacı uygulama kodu ve başlangıç içeriklerini içerir; yerel SQLite veritabanı, test dosyaları veya yüklediğin kişisel görseller kopyalanmaz. İlk üretimde mevcut `.env` özel klasöre kopyalanır ve rastgele kurulum anahtarı oluşturulur: bu ağacın tamamı özel kabul edilmeli, Git'e veya herkese açık arşive eklenmemelidir. Yerelde sonradan girilen içerikleri canlıya taşımak ayrı veri taşıma işlemidir. Mevcut canlı siteyi güncellerken önce dosya ve MySQL yedeği al; sunucudaki config.php, .env ve uploads içeriğini koru.

```text
arkesoft-sunucu/
├── arkesoft-private/
│   ├── config.php, .env, deployment.php
│   ├── app/
│   ├── storage/
│   └── tools/
└── public_html/
    ├── index.php, .htaccess
    ├── assets/
    └── uploads/
```

Uygulama `ROOT` ile özel dosyaları, `PUBLIC_ROOT` ile açık medya ve stil dosyalarını bulur. Eski geliştirme klasörü ve yerel 8127 adresi çalışmaya devam eder. Sunucuda .htaccess desteği gereklidir; Nginx için eşdeğer kurallar gerekir. Yüklemeden sonra `/config.php`, `/.env`, `/arkesoft-private/config.php` ve `/storage/error.log` 403/404 vermeli. `uploads` içinde PHP/betik çalıştırılması engellenmelidir. Özel dosyaların dışarıda olması sunucu hesabının ve yönetici şifrelerinin güvenliğini ayrıca gerektirir.

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


## Görünürlük, güven ve iletişim güncellemesi (2026-10-15)

Mevcut kurulumda önce veritabanı ve uploads yedeğini alın. `php tools/growth-upgrade.php` yeni tabloları ve görsel boyutlarını oluşturur; iletişim ayarlarını veya içerikleri sıfırlamaz. Komut satırı yoksa panelde **Yayın hazırlığı** bölümündeki altyapı güncellemesini çalıştırın. Bu panel işlemi tabloları kurar; eski görsellerin boyutlarını topluca üretmek için CLI komutu gerekir. Yeni yüklenen görsellerin boyutları otomatik üretilir. Paket içindeki konsept görsellerin küçük sürümleri yeni kurulumda hazırdır.

- **İşletme & iletişim:** e-posta, telefon, ikinci telefon, WhatsApp, gerçek adres, harita bağlantısı, koordinatlar, çalışma saatleri ve destek kapsamı. İlk kurulumda kullanıcının verdiği arkesoft.info@gmail.com / +90 538 641 59 37 bilgileri kullanılır; tümü panelden değişir. İkinci telefon boş bırakılmıştır. Fiziksel işletme şeması yalnızca bu model seçilip adres ve şehir girildiğinde üretilir. Harita gömüsü ziyaretçi düğmeye basmadan yüklenmez.
- **Sayfa SEO:** sekiz ana sayfa için ayrı başlık, açıklama ve paylaşım görseli. Detay sayfaları kendi içerik editöründeki SEO alanlarını kullanır. WebSite, işletme, Service, BlogPosting ve ortak gezinme yolu şemaları sunucuda oluşturulur.
- **Yazarlar & ekip:** gerçek kişi/kurum profili, uzmanlık, biyografi ve resmî bağlantı. Blog editöründen yazar, etiketler ve ilgili hizmetler seçilir. Yazarın URL değişikliği 301 oluşturur. Blog araması, kategori ve etiket filtreleri noindex/follow; normal sayfalamanın kendi canonical adresi vardır. Taslak ve gelecekteki yazılar listelerden/sitemapten çıkarılır.
- **İçerik ilişkileri:** proje galerisi, yayın izni, sonuç/kaynak bağlantısı; hizmete bağlı proje, rehber ve yorumlar. Gerçek müşteri veya sonuç verisi otomatik üretilmez. Kendi işletmesi hakkında yıldız şeması oluşturulmaz. Örnek içerik uyarısı gerçek içeriklerle değişim tamamlanana kadar kalmalıdır.
- **İletişim:** sabit mobil iletişim çubuğu, hizmet bağlamını taşıyan WhatsApp/iletişim bağlantıları ve `/ihtiyacini-belirle`. Kısa form ad + telefon veya e-posta + aydınlatma onayı ile çalışır. Form tokeniyle tekrar gönderim tek talep oluşturur; doğrulama hatasında alanlar ve kaynak özeti korunur. UTM alanları ve kaynak sayfası panelde görünür. Talepler SQL üzerinden aranır, durumla filtrelenir ve sayfalanır; müşteri oldu/olumsuz sonuçlandı durumları tıklamalardan ayrıdır.
- **Performans:** anonim içerik 60 saniyelik dosya önbelleği ve ETag kullanır. İçerik değişiklikleri önbellek sürümünü değiştirir; planlı yazının yayına girmesi de anahtarı değiştirir. Oturumlu, yönetim, önizleme ve form yanıtları paylaşılmaz. Ana sayfa yalnızca gerekli stil dosyalarını yükler. Küçük ekranda açılış videosu otomatik indirilmez; oynatma düğmesiyle açılabilir.

### Talep bildirimi

Talepler ve bildirim kuyruğu aynı veritabanı işlemiyle kaydedilir. Bildirim varsayılanı **bilgiler tamamlanınca otomatik gönder** seçeneğidir. `.env` içindeki posta alanlarını doldurmanız yeterlidir; ek kod değişikliği, gönderimi ayrıca açma veya zorunlu zamanlanmış görev kurulumu gerekmez. Bilgiler eksikken form talepleri ve bildirim kuyruğu korunur, gönderim denenmez. Panelden gönderim duraklatılabilir; daha önce açıkça kapatılmış ayarlar güncellemeyle kendiliğinden açılmaz. SMTP tanımlanmazsa eski PHP mail aktarımı ve `config.php` içindeki `mail_from` kullanılabilir. Alıcı ayrı tanımlanabilir; boşsa iletişim e-postası kullanılır.

Güvenli sunucu ağacında `.env` ve `config.php` **arkesoft-private** içindedir. Uygulama açıkça belirtilen env yolunu, yoksa özel uygulama kökündeki `.env` dosyasını kullanır; bu dosya yoksa eski geliştirme düzeni için üst klasöre bakar. Gerçek dosyayı Git'e veya herkese açık ZIP'e eklemeyin. Örnek dosyada parola yoktur. `config.php` MySQL ve kurulum bilgileri için gereklidir; `.env` bunun yerine geçmez.

| Alan | Kullanımı |
| --- | --- |
| `PUBLIC_ORIGIN` | Virgülle ayrılmış site kökleri. İlk adres canonical, sitemap ve mutlak bağlantılarda kullanılır; diğerleri ölçümün aynı site kontrolüne kabul edilir. Yerel `site_url` korunur. |
| `BRIEF_TO` | Yeni taleplerin bildirim alıcısı. Doluysa paneldeki bildirim alıcısından önceliklidir; boşsa panel ayarı kullanılır. Kuyruktaki mevcut kayıtların alıcısı değişmez. |
| `BRIEF_FROM` | SMTP hesabında gönderimine izin verilen e-posta adresi. Boşsa `config.php` içindeki `mail_from` kullanılır. |
| `BRIEF_FROM_NAME` | İsteğe bağlı gönderici görünen adı. |
| `SMTP_HOST` | Örneğin `smtp.hostinger.com`; tanımlanınca SMTP aktarımı seçilir. |
| `SMTP_PORT` | Boşsa 465. 587 için STARTTLS kullanılır. |
| `SMTP_USER`, `SMTP_PASS` | Posta hesabı ve parolası; panelde veya tanılama çıktısında gösterilmez. |
| `SMTP_ENCRYPTION` | İsteğe bağlı `ssl` (465) veya `tls` (587). Belirtilmezse porta göre seçilir. Sertifika doğrulaması zorunludur. |

Boş alanlar mevcut config değerlerini silmez. Sistem ortam değişkenleri dosyadan önceliklidir. Boşluk veya `#` içeren parolaları tırnaklayın; değişken genişletme ve komut çalıştırma yapılmaz. Özel bir dosya yolu gerekiyorsa `ARKESOFT_ENV` ortam değişkeni veya config içindeki `env_file` kullanılabilir. Eksik SMTP alanları panelde gösterilir; form kayıtları çalışmaya devam eder. PHP `openssl` ve `mbstring` eklentileri gerekir. SMTP yalnızca TLS üzerinden çalışır; sertifika doğrulaması kapatılmaz.

`php tools/check-environment.php` yapılandırmayı sırları göstermeden denetler; bağlantı kurmaz veya posta göndermez. Posta alanları eksikse çıkış kodu 1 olur. Yerelde `ARKESOFT_CONFIG=storage/local-config.php` ortam değişkenini kullanın. Dosya değişiklikleri sonraki PHP isteğinde okunur; yeniden derleme gerekmez.

Env güncellemesi için `php tests/environment.php`, `node tests/smtp.mjs` ve `node tests/env-site.mjs` çalıştırılır. SMTP testi geçici yerel sertifika ve sunucu kullanır; gerçek posta göndermez. TLS/STARTTLS, güvenilmeyen sertifika, kimlik doğrulama ve alıcı hataları, UTF-8 ileti, env önceliği, panelde sırların gizlenmesi ve yapılandırma eksikken talebin korunması denetlenir. SMTP testi OpenSSL komutu gerektirir; yolu `ARKESOFT_TEST_OPENSSL` ile verilebilir.

Yeni talep kaydedildikten sonra o talebin bildirimi otomatik denenir. Site/panel ziyaretleri zamanı gelen bekleyen bildirimlerden en fazla birini, arka plan kuyruğu için dakikada en fazla bir kez işler; yeni talepler bu süreyi beklemez. Dosya kilidi ve veritabanındaki kayıt sahiplenme işlemi eşzamanlı denemeleri sınırlar. FastCGI destekleniyorsa HTTP yanıtı SMTP işleminden önce tamamlanır; diğer ortamlarda yavaş posta sunucusu yanıt süresini uzatabilir. SMTP işlemi toplam 20 saniyelik zaman bütçesiyle ve okuma/yazma başına en fazla 5 saniyelik beklemeyle sınırlıdır.

Trafik yokken de kuyruk düzenli işlensin isterseniz isteğe bağlı olarak dakikada bir `php /tam/yol/tools/mail-worker.php 10` zamanlanmış görevi eklenebilir. Hata halinde artan aralıklarla en fazla beş deneme yapılır; başarısız kayıt panelden yeniden sıraya alınabilir. Ziyaret olmadığında otomatik yeniden deneme bir sonraki ziyareti bekler. Posta sunucusunun kabul etmesi gelen kutusuna teslim kanıtı değildir. İşlem gönderimden hemen sonra kesilirse yeniden denemede tekrar posta ihtimali vardır; aynı Message-ID kullanılır. Canlı posta teslimi yerel testte yapılmadı.

Otomatik akış `node tests/auto-mail.mjs` ile izole veritabanında denetlenir: bilgiler eksikken kayıtların korunması, hazır olunca gönderim, duraklatma, tekrar gönderimin önlenmesi, süreli yeniden deneme ve kilit davranışı. Bu test gerçek posta göndermez.

### Ölçüm ve kalan kurulum adımı

Ölçüm varsayılan olarak kapalıdır. Panelden açıldığında telefon/WhatsApp/görüşme/harita tıklamaları ve başarılı form kayıtları ayrı toplu sayaçlara yazılır; telefon, e-posta ve mesaj metni ölçüm tablosuna aktarılmaz. DNT/GPC tercihleri gözetilir. Tıklama bir görüşme veya satış değildir. Form kaynak bilgisi müşterinin talebiyle saklanır. Gizlilik sayfasındaki teknik ölçüm açıklaması ölçüm açıldığında gösterilir; işletmeye özgü veri sorumlusu ve saklama politikası yine tamamlanmalıdır.

Core Web Vitals toplama kodu, API ve p75 ekranı hazırdır; **resmî web-vitals paketi bu ortamın ağ kısıtı nedeniyle indirilemedi**. İnternet erişimli ortamda `node tools/install-vitals.mjs` çalıştırın. Komut sabit 5.1.0 sürümünün npm arşivini SHA-512 bütünlük kontrolüyle indirip JS ve Apache-2.0 lisansını assets içine koyar; npm kurulum betiği çalıştırmaz. Ardından paketi yeniden oluşturun. Kütüphane yokken panel açıkça uyarır ve hız ölçümü isteği gönderilmez; iletişim sayaçları çalışabilir. Gerçek kullanıcı hedeflerinin sağlandığı iddia edilmez.

Performans örnekleri sayfa görünürlüğünün ilk sonlanışında, BFCache dönüşlerinde yeni ölçüm kimliğiyle raporlanır; süreler 50 ms, CLS 0,01 aralıklarında toplulaştırılır. Panel son 30 günün cihaz gruplarına göre yaklaşık p75 değerini ve örnek sayısını gösterir. 90 günden eski toplu ölçümleri silmek için günlük `php /tam/yol/tools/measurement-prune.php` görevi eklenebilir. Müşteri talepleri bu komutla silinmez.

### Yayın kontrolü ve doğrulama

`php tools/site-audit.php https://alanadiniz.com` sitemap indeksini, ilk 500 sayfanın HTTP durumunu, canonical/H1/meta/JSON-LD yapısını ve ilk 1.000 site içi bağlantıyı denetleyip JSON üretir; sorun varsa çıkış kodu 1 olur. Bu, tam HTML doğrulaması veya sınırsız tarama değildir. Canlı HTTPS, DNS, gerçek e-posta teslimi, Search Console ve Google İşletme Profili doğrulaması hosting/hesap ortamında ayrıca yapılır.

Yerel doğrulama: mevcut 117 entegrasyon kontrolü; yeni modüllerde 78 kontrol ve 33 ekran/boyut erişilebilirlik taraması; mevcut yüzeylerde 144 erişilebilirlik taramasında sıfır otomatik ihlal/taşma; 300 sabit tohumlu arayüz etkileşiminde JS hatası/HTTP 5xx yok. Güncellenen form doğrulama beklentisiyle işlevsel tekrar koşusu 57/57 geçti. Yerel sitemap 32 sayfa ve 82 iç bağlantı: hata ve tekrar eden açıklama yok. Testler SQLite ve Chromium ortamında çalıştırıldı; bu sonuçlar tam WCAG/ASVS uyumu veya bağımsız güvenlik testi iddiası değildir.
