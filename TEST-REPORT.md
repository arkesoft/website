# Kontrol kaydı — 23 Eylül 2026

## İngilizce adresler ve metin yerleşimi

- 27 İngilizce sayfa `/en/home`, `/en/about`, `/en/projects` gibi ayrı adreslerde üretildi; toplam 54 içerik adresi ve 404 ile 55 HTML belgesi kontrol edildi. İngilizce içerik JavaScript kapalıyken de sunuluyor.
- Dil seçimi adresi ve iç bağlantıları güncelliyor; form değerleri, proje filtreleri ve bölüm bağlantıları korunuyor. Doğrudan İngilizce adres açma, yenileme ve depolama tamamen kapalıyken İngilizce sayfalar arasında gezinme doğrulandı.
- Her dil için canonical, Open Graph, yapılandırılmış veri, karşılıklı hreflang bağlantıları ve sitemap üretildi. 3488 yerel referans, 869 dil anahtarı ve 86 kalıcı yönlendirme kontrolü geçti.
- İngilizce hizmet/proje başlıklarının sabit bölüm yüksekliğinden taşması giderildi. Bölümler metne göre büyüyor; başlık boyutları ve satır düzeni ekran genişliğine uyarlanıyor.
- Tarayıcı kontrolleri 1440, 1024, 768, 390 ve 320 pikselde hem yatay taşmayı hem bölüm içinde kesilen metni ve başlıkların menü/alt kontrollerle çakışmasını denetliyor. Form, filtre, ülke ve dil/tema kontrolleri de geçti.
- Yayına dağıtım yapılmadı; canlı hosting ve gerçek cihaz kontrolleri ayrıca yapılmalıdır.

## Önceki temiz URL kontrolü

Yerel Google Chrome + Playwright 1.59.1. Temiz URL ve SEO çalışması tamamlandı; canlıya dağıtım yapılmadı.

- `npm run build`: 27 temiz rota, statik dizin kopyaları ve hosting yönlendirmeleri üretildi.
- `npm test`: 28 HTML sayfa, 1746 yerel dosya/bölüm referansı, 869 eşleşen TR/EN dil anahtarı, JS sözdizimi ve ülke adaptörü geçti.
- SEO/HTTP: 27 canonical adres, sitemap kapsamı, Open Graph adresleri, yapılandırılmış veri, 58 kalıcı yönlendirme, sorgu parametrelerinin korunması ve gerçek 404 yanıtları geçti.
- Tarayıcı: 28 sayfanın 1440, 390 ve 320 piksel genişlikleri, görsel yüklemeleri ve EN→TR geçişleri geçti. Alt bölümdeki uzun metinler ve iletişim bağlantılarının 320 pikselde oluşturduğu taşma giderildi; Hakkımızda görselinin eksik İngilizce açıklaması eklendi.
- Açılış/yenileme, sahne seçimi ve duraklatma, görsel diyaloğu, menü odağı, kaydırma sahnesi, proje arama/filtreleri ve form dosya indirme kontrolleri geçti.
- Eski `.html?lang=en&theme=light` bağlantıları temiz adreslere dönüşüyor; filtre ve bölüm bağlantıları korunuyor. Dil/tema aynı sekmede localStorage engellendiğinde sessionStorage ile korunuyor.
- Ülke seçimi, geciken yanıtta manuel seçim önceliği, ülke servisi hatasında tarayıcı dili ve JavaScript kapalı içerik kontrolleri geçti. Ülke/form cevapları taklit edildi; dış ağ isteği ve beklenmeyen JS/konsol hatası görülmedi.
- `git diff --check` geçti. Güncel tarayıcı testi SVG sahnelerini doğrular; önceki sürümün WebM/MP4 testleri bu sürüm için geçerli değildir.

Canlı Vercel/Cloudflare/Apache davranışı, gerçek form teslimatı ve Safari/Firefox/gerçek cihaz kontrolleri yapılmadı.

## Önceki kayıt — 19 Eylül 2026

Yerel Google Chrome + Playwright 1.59.1. Yayına dağıtım yapılmadı.

## Geçen kontroller

- 14 HTML sayfa, 689 yerel dosya/bölüm referansı, 521 eşleşen TR/EN dil anahtarı; JS sözdizimi ve ülke adaptörü.
- Açılışın ilk ziyarette ve yenilemede çalışması; doğal bitiş; Escape; site içi geçişte gereksiz tekrar etmemesi.
- Açılış arkasında video oynatmama; hero videosunun açılıştan sonra başlaması; duraklatma ve film diyaloğu. MP4: 1440×810, yaklaşık 18 saniye, oynatma doğrulandı.
- Tema/dil kalıcılığı, TR/EN metin ve erişilebilirlik etiketleri, ülke TR/DE, geciken ülke yanıtında manuel seçim önceliği, servis hatasında tarayıcı dili.
- 1440, 390 ve 320 piksel genişlikte yatay taşma olmaması; görsellerin yüklenmesi; EN → TR geri dönüşü.
- Proje filtresi, arama, boş sonuç, sıfırlama, URL'de durum saklama ve yenilemede geri yükleme.
- Tam ekrana açılan/daralan scroll sahnesi; menü klavye odağı ve arka planın etkileşime kapanması.
- Brief doğrulaması ve İngilizce dosya indirme; JavaScript kapalıyken içerik; azaltılmış hareket modu.
- Güvenlik başlıkları altında çalışma. Beklenmeyen JS/konsol hatası, eksik HTTP kaynağı veya harici ağ talebi görülmedi. Bilerek kesilen ülke isteğinin beklenen ağ hata mesajı ayrı tutuldu.
- Arşiv stok görsel/video ve harici font talebi yok. Eski dosyalar diskte korundu.

## Düzeltilen sorunlar

- Hero'nun video yerine yalnızca ilk görselle başlaması ve açılış arkasında medya oynatma ihtimali.
- Eski `#kampanya` bağlantısının içerik güncellemesinde kaybolması.
- Proje vitrininin eksik kapanış etiketinden kaynaklanan metin çakışması.
- Yeni bölüm etiketlerinin çeviri anahtarları ve açık temadaki açılış yazısı kontrastı.
- Native cross-document transition denemesinde tekrar eden gezintide takılma; güvenilir CSS giriş solmasıyla değiştirildi.
- Film üretiminde ilk animation-frame zaman damgasının başlangıçtan önce gelebilmesi.

## Henüz doğrulanmayanlar

Gerçek iOS/Safari/Firefox, gerçek ülke edge bilgisi, canlı alan adı/SEO, gerçek form teslimatı ve saha performansı. Otomatik yerel kontrol bunların tamamlandığı anlamına gelmez. Son yayın listesi `PRODUCTION.md` içindedir.
