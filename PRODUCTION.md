# Yayına hazırlık

Bu proje klasörü yayın köküdür. Frontend statiktir. Kaynak HTML, çeviri sözlüğü veya `routes*.json` değişikliklerinden sonra `npm run build` çalıştırın; Türkçe ve İngilizce sayfaları, sitemap, adres haritası ve hosting yönlendirmelerini günceller. `en/` içindeki üretilen HTML dosyalarını elle düzenlemeyin. Node/Playwright geliştirme ve test içindir.

## Yayından önce gereken gerçek bilgiler

- Gerçek alan adı: canonical, Open Graph URL/görseli, yapılandırılmış veri ve sitemap şu anda `https://arkesoft.com` kullanır. Yayın alan adıyla eşleştiğini kontrol edin.
- Gerçek iletişim kanalı: form `/api/brief` üzerinden Resend ile `arkesoft.info@gmail.com` adresine gönderir; servis yapılandırılmamışsa yerel brief dosyası indirir. API anahtarı ve gönderici adresi kaynak koduna yazılmaz.
- Formu canlıda etkinleştirmek için hosting ortamına `RESEND_API_KEY`, `BRIEF_TO=arkesoft.info@gmail.com`, `BRIEF_FROM="Arkesoft <form@arkesoft.com>"` ve `PUBLIC_ORIGIN=https://arkesoft.com` ekleyin. Resend panelinde `arkesoft.com` alan adını doğrulamadan `onboarding@resend.dev` veya Gmail adresini gönderici olarak kullanmayın.
- Spam riskini azaltmak için DNS'te Resend'in verdiği SPF ve DKIM kayıtlarını eksiksiz yayınlayın; alan adı için tek bir birleştirilmiş SPF kaydı kullanın ve DMARC'ı önce `p=none` ile izleyip raporlar temizlendikten sonra `p=quarantine`/`p=reject` seviyesine yükseltin. Gmail'e teslimat yine alıcı hesabının filtrelerine bağlıdır; “spam'e hiç düşmez” garantisi verilemez.
- Endpoint kaynak başına saatlik beş gönderim sınırı, origin kontrolü, e-posta doğrulaması, uzunluk temizleme ve `Reply-To` olarak formdaki e-posta adresini uygular. Resend'in alan adı doğrulaması ve DNS kayıtları yayında ayrıca test edilmelidir.
- İletişim/veri toplama eklenirse gerçek süreçle uyumlu gizlilik metni ve veri saklama kararları gerekir.
- Projeler konsept olarak etiketli. Gerçek referanslar ancak doğrulanmış bilgi ve izinle eklenmeli.
- TR/EN için ayrı statik adresler, canonical ve karşılıklı hreflang bağlantıları hazırdır. İngilizce sayfalar `en/` altında üretilir; yayın paketine `en/` ve `route-map.js` dosyasını dahil edin. Dil düğmeleri form durumunu koruyarak aynı sayfayı diğer dile geçirir.

## Dağıtım

- Proje kökü: bu klasör. Hosting seçimine göre `api/country.mjs` (Vercel) veya `functions/api/country.js` (Cloudflare Pages) kullanılır. Ülke servisi sitenin kendi alan adındadır; harici IP servisi yoktur.
- WhatsApp iletişimi: ana hat `+90 506 145 89 71`, destek hattı `+90 538 641 59 37` olarak iki ayrı bağlantı halinde gösterilir. Numaralar kişisel veri olarak yayına açık olduğundan hat sahiplerinin onayını alın.
- `npm run build`, Vercel için `vercel.json`, Cloudflare Pages için `_redirects`, Apache 2.4 için `.htaccess` üretir. Eski `.html` adreslerini ve sondaki `/` işaretini kalıcı olarak temiz adreslere yönlendirin. Canlıda yönlendirme döngüsü olmadığını, sorgu parametrelerinin korunduğunu ve bilinmeyen yolların HTTP 404 döndürdüğünü doğrulayın.
- `_headers` Cloudflare Pages güvenlik/cache başlıkları içindir. Vercel karşılığı `vercel.json`. Başka sunucuda aynı başlıkları yapılandırın. HTTPS, video MIME türleri ve Range istekleri canlı ortamda doğrulanmalı.
- `_headers` dinamik fonksiyonlara uygulanmayabilir; ülke fonksiyonu ayrıca `Cache-Control: no-store` gönderir.
- HTTP önizlemesi ülke bilmez; tarayıcı dili geri dönüşü normaldir. Gerçek ülkeler canlı hosting üzerinde doğrulanmalı. VPN çıkış ülkesi belirleyici olabilir.
- Arşivdeki stok dosyaları **silinmedi**. Canlı dosya aktarımında yalnızca HTML/CSS/JS tarafından kullanılan varlıkları seçin. `tests/`, `assets/*source.html`, Markdown kaynak belgeleri, package dosyaları ve eski stoklar web köküne aktarılmak zorunda değildir. Yerel kaynaklar korunmalıdır.
- Analytics, çerez bannerı, üçüncü taraf font veya takip pikseli eklenmedi.

## Tekrarlanabilir kontrol

```sh
npm run build
npm test
npm install
npx playwright install chromium
npm run test:browser
```

İlk komut bağımlılıksız çalışır. Tarayıcı testi yerel sunucusunu başlatır ve kapatır; bütün ülke yanıtları taklit edilir, dış ağ talepleri engellenir. Mevcut Chrome kullanmak için `BROWSER_EXECUTABLE`, kurulu Playwright modülü için `PLAYWRIGHT_MODULE` tanımlanabilir. `TEST_ARTIFACTS` ekran görüntüsü çıktısı için isteğe bağlıdır.

Otomatik kontrol manuel iOS/Safari/Firefox ve gerçek cihaz testinin yerine geçmez. Özellikle SVG animasyonları, yavaş mobil bağlantı, büyük metin ve canlı form teslimatı son yayın kontrolüne dahildir.

## Hareket ve medya

Güncel sayfalar SVG görselleri kullanır; ana sayfada üç sahne ve ayrı bir görsel diyaloğu bulunur. Hareket azaltma/veri tasarrufu tercihinde otomatik sahne geçişi kapalıdır. Kullanıcı sahne hareketini duraklatabilir. Önceki WebM/MP4 dosyaları arşivde durur.

Kaydırma ele geçirilmez. Tam ekran görselin maskesi ve ölçeği tek requestAnimationFrame döngüsünde güncellenir. Sayfa girişinde kısa bir CSS solması kullanılır; bağlantılar, geri/ileri geçmişi ve yeni sekme davranışı değiştirilmez. [Chrome cross-document transitions](https://developer.chrome.com/docs/web-platform/view-transitions/cross-document) incelendi ve denendi; bu ortamda tekrar edilen geçişlerde takılma gözlendiği için son sürümde etkin değildir.


## Yayına çıkarken (Arkesoft)

1. **Alan adı:** Tüm mutlak adresler `https://arkesoft.com` kullanıyor. Gerçek alan adı farklıysa kökteki HTML dosyalarında, `sitemap.xml` ve `robots.txt` içinde güncelleyin; ardından `npm run build` ve `npm test` çalıştırın.
2. **Form gönderimi:** [resend.com](https://resend.com) hesabı açın, alan adını doğrulayın ve `RESEND_API_KEY`, `BRIEF_TO`, `BRIEF_FROM`, `PUBLIC_ORIGIN` değişkenlerini barındırma ortamına ekleyin. Anahtar veya doğrulanmış gönderici yoksa formlar otomatik olarak dosya-indirme akışına döner; site bozulmaz.
3. **Search Console:** Yayın sonrası `sitemap.xml` dosyasını Google Search Console'a gönderin.
4. **Gizlilik tutarlılığı:** Analitik eklerseniz `gizlilik.html` metnini güncellemeyi unutmayın (şu an "analitik yok" diyor).
