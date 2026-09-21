# Yayına hazırlık

Bu klasör mevcut sürümdür; kökteki eski site veya başka bir sürüm yayınlanmamalı. Frontend statiktir, derleme gerektirmez. Node/Playwright yalnızca geliştirme testleri içindir.

## Yayından önce gereken gerçek bilgiler

- Gerçek alan adı: canonical, Open Graph URL/görseli ve sitemap bu alan adıyla hazırlanmalı; örnek alan adı yerleştirilmedi.
- Gerçek iletişim kanalı: mevcut form yalnızca yerel brief dosyası indirir. E-posta gönderdiğini söylemez. Form servisi/CRM, alıcı adresi, spam koruması ve sunucu doğrulaması yapılandırılmadan canlı müşteri talebi toplama hazır değildir.
- İletişim/veri toplama eklenirse gerçek süreçle uyumlu gizlilik metni ve veri saklama kararları gerekir.
- Projeler konsept olarak etiketli. Gerçek referanslar ancak doğrulanmış bilgi ve izinle eklenmeli.
- İngilizce metinler istemci tarafında çevrilir. Ayrı TR/EN arama motoru sayfaları istenirse dil bazlı statik URL üretimi ayrıca yapılmalı.

## Dağıtım

- Proje kökü: `versiyonlar/maison-ajans`. Hosting seçimine göre `api/country.mjs` (Vercel) veya `functions/api/country.js` (Cloudflare Pages) kullanılır. Ülke servisi sitenin kendi alan adındadır; harici IP servisi yoktur.
- `_headers` Cloudflare Pages güvenlik/cache başlıkları içindir. Vercel karşılığı `vercel.json`. Başka sunucuda aynı başlıkları yapılandırın. HTTPS, video MIME türleri ve Range istekleri canlı ortamda doğrulanmalı.
- `_headers` dinamik fonksiyonlara uygulanmayabilir; ülke fonksiyonu ayrıca `Cache-Control: no-store` gönderir.
- HTTP önizlemesi ülke bilmez; tarayıcı dili geri dönüşü normaldir. Gerçek ülkeler canlı hosting üzerinde doğrulanmalı. VPN çıkış ülkesi belirleyici olabilir.
- Arşivdeki stok dosyaları **silinmedi**. Canlı dosya aktarımında yalnızca HTML/CSS/JS tarafından kullanılan varlıkları seçin. `tests/`, `assets/*source.html`, Markdown kaynak belgeleri, package dosyaları ve eski stoklar web köküne aktarılmak zorunda değildir. Yerel kaynaklar korunmalıdır.
- Analytics, çerez bannerı, üçüncü taraf font veya takip pikseli eklenmedi.

## Tekrarlanabilir kontrol

```sh
npm test
npm install
npx playwright install chromium
npm run test:browser
```

İlk komut bağımlılıksız çalışır. Tarayıcı testi yerel sunucusunu başlatır ve kapatır; bütün ülke yanıtları taklit edilir, dış ağ talepleri engellenir. Mevcut Chrome kullanmak için `BROWSER_EXECUTABLE`, kurulu Playwright modülü için `PLAYWRIGHT_MODULE` tanımlanabilir. `TEST_ARTIFACTS` ekran görüntüsü çıktısı için isteğe bağlıdır.

Otomatik kontrol manuel iOS/Safari/Firefox ve gerçek cihaz testinin yerine geçmez. Özellikle MP4 fallback, pil tasarrufunda autoplay, yavaş mobil bağlantı, büyük metin ve canlı form teslimatı son yayın kontrolüne dahildir.

## Hareket ve medya

18 saniyelik sessiz film `signature-film.webm`; MP4 alternatifi `signature-film.mp4`. Bu film özgün görsellerden kodla oluşturulan bir hareket kompozisyonudur, canlı çekim değildir. Hareket azaltma/veri tasarrufu tercihinde otomatik oynatma kapalıdır. Görünmeyen sekme ve ekran dışı hero videosu durur. Kullanıcı duraklatabilir; autoplay engellenirse poster kalır.

Kaydırma ele geçirilmez. Tam ekran görselin maskesi ve ölçeği tek requestAnimationFrame döngüsünde güncellenir. Sayfa girişinde kısa bir CSS solması kullanılır; bağlantılar, geri/ileri geçmişi ve yeni sekme davranışı değiştirilmez. [Chrome cross-document transitions](https://developer.chrome.com/docs/web-platform/view-transitions/cross-document) incelendi ve denendi; bu ortamda tekrar edilen geçişlerde takılma gözlendiği için son sürümde etkin değildir.


## Yayına çıkarken (Arkesoft)

1. **Alan adı:** Tüm mutlak adresler geçici olarak `https://arkesoft.com` yazıyor. Gerçek alan adı farklıysa tek komutla değiştirin:
   `LC_ALL=C sed -i '' 's|https://arkesoft.com|https://GERCEK-ALAN-ADI|g' *.html sitemap.xml robots.txt`
2. **Form gönderimi:** [resend.com](https://resend.com) hesabı açın, API anahtarını barındırma ortamına `RESEND_API_KEY` olarak ekleyin (isteğe bağlı: `BRIEF_TO`, `BRIEF_FROM`). Anahtar yoksa formlar otomatik olarak dosya-indirme akışına döner; site bozulmaz.
3. **Search Console:** Yayın sonrası `sitemap.xml` dosyasını Google Search Console'a gönderin.
4. **Gizlilik tutarlılığı:** Analitik eklerseniz `gizlilik.html` metnini güncellemeyi unutmayın (şu an "analitik yok" diyor).
