# ARKESOFT — Aynı sürüm, gelişmiş dijital deneyim

Önizleme: `http://127.0.0.1:4173/versiyonlar/maison-ajans/`.
Statik HTML/CSS/JavaScript; kurulum ve derleme gerektirmez. Bu çalışma mevcut `maison-ajans` klasöründe yapıldı. Diğer sürümler değişmedi.

## Sayfalar ve deneyim

14 bağımsız sayfa: ana sayfa, projeler, stüdyo, dört hizmet sayfası, iletişim ve altı konsept proje detayı. ORBIT, FORMA ve PRISM yeni detay sayfalarıdır; aynı sürümün içindedir.

Projeler: altı konsept, büyük PRISM vitrini, hizmet filtresi, proje/sektör/disiplin araması, URL'de saklanan filtre, sonuç sayısı ve boş sonuçtan sıfırlama. Tüm proje detaylarında arayüz kompozisyonu, yaklaşım/kapsam anlatımı ve sonraki proje bağlantısı bulunur.

Ana sayfa: açılıştan hemen sonra başlayan sinematik hero filmi, hizmet özeti, kaydırmayla değişen üç aşamalı ürün sahnesi, kademeli aydınlanan tipografi, kaydırmayla tam ekrana açılıp daralan PRISM sahnesi, proje seçkisi, hizmetler, stüdyo yaklaşımı, dört kartlı yatay galeri, çalışma biçimi, sorular ve iletişim. Galeri normal yatay kaydırmayı destekler; sayfanın tekerlek/dokunma kaydırması ele geçirilmez. Başlangıç/sondaki galeri okları devre dışı kalır. Üstte okuma ilerleme çizgisi bulunur.

Açılış her sayfa yenilemesinde ve doğrudan ilk girişte çalışır. Aynı site içindeki normal menü geçişlerinde tekrarlanmaz. Oturumda bir defa gösterme kısıtı kaldırıldı. Geç düğmesi veya Escape ile atlanabilir. Hareket azaltma tercihi varsa açılış ve otomatik hareketler devre dışı kalır; üç aşamanın içeriği düz akışta gösterilir.

## Tema ve diller

- `preferences.js`: CSS yüklenmeden önce kayıtlı tema/dil tercihini uygular.
- `locales/tr.js` ve `locales/en.js`: 400'den fazla metin, başlık, erişilebilirlik etiketi, açıklama ve form mesajının iki dilde karşılığı.
- `i18n.js`: metinleri mevcut DOM yapısını, form durumunu ve olay dinleyicilerini koruyarak değiştirir.
- `experience.js`: tema, kaydırma koreografisi ve yatay seçki kontrolleri.
- `experience.css`: açık/koyu tema paletleri ve yeni deneyimin yerleşimleri.
- `polish.css`: sinematik görsel sahnesi, proje seçkisi, detay arayüzleri ve yumuşak sayfa girişi. Bağlantı davranışı değiştirilmez.

Dil/tema düğmeleri sabit üst menüdedir. Manuel seçim localStorage'da tutulur ve iç bağlantılara da eklenir; depolama engellense bile sayfa geçişlerinde tercih korunur. `?lang=tr`, `?lang=en`, `?theme=light`, `?theme=dark` önizleme için kullanılabilir.

İlk dil seçiminin sırası:
1. URL veya ziyaretçinin kayıtlı manuel dil seçimi.
2. Sunucu tarafından eklenmiş `<meta name="visitor-country" content="TR">` varsa ülke.
3. Oturumda en çok 30 dakika saklanan ülke kodu.
4. Aynı alan adındaki `api/country` uç noktasından sunucunun ülke kodu: TR → Türkçe; bilinen diğer ülkeler → İngilizce.
5. Ülke bulunamazsa veya 2,5 saniyelik süre aşılırsa tarayıcı dili: Türkçe için TR, diğerleri için EN.

Ülke adaptörleri bu klasörde hazırdır: Cloudflare Pages için `functions/api/country.js`, Vercel için `api/country.mjs`. Proje kökü olarak bu klasör kullanıldığında ilgili sunucu kendi ülke bilgisini döndürür. Yalın statik sunucu / Python önizlemesinde ülke bilgisi bulunmaz; tarayıcı dili kullanılır. Başka bir hosting için aynı `{ "country": "TR" }` yanıtını döndüren uç nokta veya ülke meta etiketi gerekir.

IP konumu kesin fiziksel konum değildir; VPN/proxy çıkışının ülkesini gösterebilir. Otomatik sonuç beklenirken yapılan manuel seçim hiçbir zaman ezilmez. Harici IP servisine istek yapılmaz. Ülke bilgisini sitenin kendi hosting katmanı sağlar; form verisi veya e-posta ülke uç noktasına gönderilmez.

Hosting belgeleri: https://developers.cloudflare.com/workers/runtime-apis/request/#incomingrequestcfproperties ve https://vercel.com/docs/headers/request-headers#x-vercel-ip-country

## İçerik ve medya

Stok fotoğraf ve video dosyaları **silinmedi veya değiştirilmedi**. Eski Unsplash/Mixkit varlıkları `assets/` klasöründe arşiv olarak duruyor; bu sürümün HTML/JS dosyalarında bunlara başvuru yok.

Sayfalarda projeye özel üretilen `digital-studio.webp`, `architecture.webp`, `atelier.webp`, `mobile.webp`, `prism.webp`, `orbit.webp`, `forma.webp` görselleri kullanılır. Yeni 18 saniyelik `signature-film.webm` ve MP4 alternatifi açılıştan sonra hero'da oynar. Bunlar özgün görsellerden kodla hazırlanmış hareketli kompozisyonlardır; canlı çekim veya stok video değildir. Kaynak `assets/signature-source.html`; üretim istemleri `assets/ART-DIRECTION.md`. Önceki `digital-film.webm` ve kaynak dosyası da korunmuştur. Müzik kullanılmaz.

Harici font dosyaları kullanılmaz; sistem Arial/Helvetica ve Georgia yazı tipleriyle çalışır. Önceki font dosyaları da korunmuştur. Bu tercih ayrıca font indirmesini kaldırır. Üretilen içerikler için evrensel bir hukuki hak garantisi verilmez; kaynak geçmişi `assets/SOURCES.md` dosyasında kayıtlıdır.

Proje kartları açıkça konsepttir. Gerçek olmayan müşteri, başarı, servet veya kıtlık iddiası eklenmedi; premium konumlandırma tasarım, özen ve kişiye özel çalışma diliyle sağlandı.

## İletişim ve test

İletişim alanı üç adımlı yerel brief oluşturucudur; TR veya EN metin dosyası indirir. E-posta/CRM servisine bağlı değildir. Form alanları dil/tema değişiminde korunur.

`npm test`: bağımlılıksız dosya/bağlantı, başlık yapısı, dil anahtarı, JS sözdizimi ve ülke adaptörü kontrolü. `npm run test:browser`: Playwright ile 14 sayfa, ilk ziyaret/yenileme açılışı, hero ve MP4, TR/EN, ülke senaryoları, tema kalıcılığı, arama/filtre, scroll sahnesi, form, mobil taşmalar ve JavaScript kapalı durum. Ülke cevapları taklit edilir; harici ağ talepleri engellenir.

Yayın yapılandırması, henüz gerçek servise bağlı olmayan iletişim formu, domain/SEO ve gerçek cihaz kontrol listesi için `PRODUCTION.md` dosyasını okuyun. Bu çalışma siteyi internete yayımlamaz.
