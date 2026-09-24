# ARKESOFT — Aynı sürüm, gelişmiş dijital deneyim

Önizleme: `npm run dev` → `http://127.0.0.1:5500/anasayfa`.
Statik HTML/CSS/JavaScript; çalışma zamanı bağımlılığı yoktur. Kaynak HTML dosyalarını değiştirdikten sonra `npm run build` çalıştırın; temiz adres kopyalarını ve hosting yönlendirmelerini günceller.

## Sayfalar ve deneyim

27 içerik sayfası ve bir 404 sayfası: ana sayfa, projeler, stüdyo, hizmetler, blog, iletişim, teklif, yasal metinler ve altı konsept proje detayı.

Türkçe adresler `routes.json`, İngilizce karşılıkları `routes-en.json` içinde tanımlıdır: `/anasayfa` ↔ `/en/home`, `/hakkimizda` ↔ `/en/about`, `/projeler` ↔ `/en/projects`. `npm run build` iki dil için toplam 54 içerik adresi, İngilizce statik HTML, `route-map.js` ve hosting yönlendirmelerini üretir. İngilizce sayfalar JavaScript kapalıyken de İngilizce içerik sunar. Eski `.html` adresleri kalıcı olarak yönlendirilir. Her dilin canonical, Open Graph ve yapılandırılmış veri adresleri ayrıdır; karşılıklı `hreflang` bağlantıları ve sitemap iki dili de kapsar. `tools/server.mjs` yerel önizlemede yönlendirme, video Range ve gerçek 404 yanıtlarını sağlar. Vercel, Cloudflare Pages ve Apache yapılandırmaları `npm run build` ile üretilir; canlı hosting davranışı yayında ayrıca doğrulanmalıdır.

Projeler: altı konsept, büyük PRISM vitrini, hizmet filtresi, proje/sektör/disiplin araması, URL'de saklanan filtre, sonuç sayısı ve boş sonuçtan sıfırlama. Tüm proje detaylarında arayüz kompozisyonu, yaklaşım/kapsam anlatımı ve sonraki proje bağlantısı bulunur.

Ana sayfa: açılıştan sonra gösterilen üç SVG sahnesi, hizmet özeti, kaydırmayla değişen üç aşamalı ürün sahnesi, kademeli aydınlanan tipografi, kaydırmayla tam ekrana açılıp daralan PRISM sahnesi, proje seçkisi, hizmetler, stüdyo yaklaşımı, dört kartlı yatay galeri, çalışma biçimi, sorular ve iletişim. Galeri normal yatay kaydırmayı destekler; sayfanın tekerlek/dokunma kaydırması ele geçirilmez. Başlangıç/sondaki galeri okları devre dışı kalır. Üstte okuma ilerleme çizgisi bulunur.

Açılış her sayfa yenilemesinde ve doğrudan ilk girişte çalışır. Aynı site içindeki normal menü geçişlerinde tekrarlanmaz. Oturumda bir defa gösterme kısıtı kaldırıldı. Geç düğmesi veya Escape ile atlanabilir. Hareket azaltma tercihi varsa açılış ve otomatik hareketler devre dışı kalır; üç aşamanın içeriği düz akışta gösterilir.

## Tema ve diller

- `preferences.js`: CSS yüklenmeden önce kayıtlı tema/dil tercihini uygular.
- `locales/tr.js` ve `locales/en.js`: 800'den fazla metin, başlık, erişilebilirlik etiketi, açıklama ve form mesajının iki dilde karşılığı.
- `i18n.js`: metinleri mevcut DOM yapısını, form durumunu ve olay dinleyicilerini koruyarak değiştirir.
- `experience.js`: tema, kaydırma koreografisi ve yatay seçki kontrolleri.
- `experience.css`: açık/koyu tema paletleri ve yeni deneyimin yerleşimleri.
- `polish.css`: sinematik görsel sahnesi, proje seçkisi, detay arayüzleri ve yumuşak sayfa girişi. Bağlantı davranışı değiştirilmez.

Dil/tema düğmeleri sabit üst menüdedir. Dil değişimi adresi ve tüm iç bağlantıları aynı sayfanın diğer diline geçirir; sayfa yenilenmediği için form alanları ve filtreler korunur. `/en/…` adresi doğrudan İngilizce açılır ve kayıtlı Türkçe tercihe göre önceliklidir. Manuel seçim localStorage ve sessionStorage'da da tutulur; ikisi engelliyken İngilizce adres dil bilgisini taşır, tema tercihi sonraki sayfaya taşınamaz. `?lang=tr`, `?lang=en`, `?theme=light`, `?theme=dark` eski bağlantılar ve önizleme için desteklenir; tercih uygulandıktan sonra bu parametreler adresten kaldırılır. Arama/filtre parametreleri ve bölüm bağlantıları korunur.

İlk dil seçiminin sırası:
1. URL'deki `lang` parametresi, ardından `/en/…` adresi veya ziyaretçinin kayıtlı manuel dil seçimi.
2. Sunucu tarafından eklenmiş `<meta name="visitor-country" content="TR">` varsa ülke.
3. Oturumda en çok 30 dakika saklanan ülke kodu.
4. Aynı alan adındaki `api/country` uç noktasından sunucunun ülke kodu: TR → Türkçe; bilinen diğer ülkeler → İngilizce.
5. Ülke bulunamazsa veya 2,5 saniyelik süre aşılırsa tarayıcı dili: Türkçe için TR, diğerleri için EN.

Ülke adaptörleri bu klasörde hazırdır: Cloudflare Pages için `functions/api/country.js`, Vercel için `api/country.mjs`. Proje kökü olarak bu klasör kullanıldığında ilgili sunucu kendi ülke bilgisini döndürür. Yalın statik sunucu / Python önizlemesinde ülke bilgisi bulunmaz; tarayıcı dili kullanılır. Başka bir hosting için aynı `{ "country": "TR" }` yanıtını döndüren uç nokta veya ülke meta etiketi gerekir.

IP konumu kesin fiziksel konum değildir; VPN/proxy çıkışının ülkesini gösterebilir. Otomatik sonuç beklenirken yapılan manuel seçim hiçbir zaman ezilmez. Harici IP servisine istek yapılmaz. Ülke bilgisini sitenin kendi hosting katmanı sağlar; form verisi veya e-posta ülke uç noktasına gönderilmez.

Hosting belgeleri: https://developers.cloudflare.com/workers/runtime-apis/request/#incomingrequestcfproperties ve https://vercel.com/docs/headers/request-headers#x-vercel-ip-country

## İçerik ve medya

Stok fotoğraf ve video dosyaları **silinmedi veya değiştirilmedi**. Eski Unsplash/Mixkit varlıkları `assets/` klasöründe arşiv olarak duruyor; bu sürümün HTML/JS dosyalarında bunlara başvuru yok.

Sayfalarda projeye özel SVG kompozisyonları kullanılır; ana sahnede `signal.svg`, `social.svg` ve `mobile-app.svg`, film diyaloğunda `signature.svg` gösterilir. Önceki WebP ve WebM/MP4 dosyaları arşivde korunur. Müzik kullanılmaz.

Harici font dosyaları kullanılmaz; sistem Arial/Helvetica ve Georgia yazı tipleriyle çalışır. Önceki font dosyaları da korunmuştur. Bu tercih ayrıca font indirmesini kaldırır. Üretilen içerikler için evrensel bir hukuki hak garantisi verilmez; kaynak geçmişi `assets/SOURCES.md` dosyasında kayıtlıdır.

Proje kartları açıkça konsepttir. Gerçek olmayan müşteri, başarı, servet veya kıtlık iddiası eklenmedi; premium konumlandırma tasarım, özen ve kişiye özel çalışma diliyle sağlandı.

## İletişim ve test

İletişim alanı üç adımlı brief oluşturucudur. `/api/brief` yapılandırıldığında Resend üzerinden `arkesoft.info@gmail.com` adresine e-posta gönderimini dener; servis kullanılamazsa TR veya EN metin dosyası indirir. Gönderici alan adı doğrulanmadan canlı e-posta etkinleşmez. Form alanları dil/tema değişiminde korunur. WhatsApp'ta ana hat `+90 506 145 89 71`, destek hattı `+90 538 641 59 37` olarak görünür.

`npm test`: 55 HTML belgesinin dosya/bağlantı, başlık yapısı, dil anahtarı, JS sözdizimi, ülke adaptörü, canonical/hreflang/sitemap tutarlılığı, yapılandırılmış veri ve yerel HTTP yönlendirme/404 kontrolü. `npm run test:browser`: Playwright ile 28 sayfanın 1440/1024/768/390/320 pikselde kontrolü; kutu içinde kesilen metinler, başlık/menü çakışması, TR/EN adres geçişleri, form ve filtre korunması, tema kalıcılığı, depolama geri dönüşü, ülke senaryoları ve 27 İngilizce sayfanın JavaScript kapalı içeriği. Ülke ve form cevapları taklit edilir; harici ağ talepleri engellenir.

Yayın yapılandırması, iletişim formunun servis ayarları, domain/SEO ve gerçek cihaz kontrol listesi için `PRODUCTION.md` dosyasını okuyun. Bu çalışma siteyi internete yayımlamaz.
