# Arkesoft Ekip Ortak Bağlamı (agent tanımları bu dosyaya referans verir)

- Ürün: Arkesoft — premium/elit yazılım & dijital ajans sitesi. Statik HTML + CSS + vanilla JS, çift dilli (TR/EN, locales/ + i18n.js). Repo kökü: bu depo. Canlı: https://arkesoft.github.io/website/ (GitHub Pages, main branch).
- Marka: logo beyazı #F5F3EE + bebek mavisi #6F8FAF (--bronze değişkeni). Aksan skalası: #3d5b80 (açık temada metin aksanı) / #4a6c94 / #6f8faf / #9eb8d6 (koyu temada eyebrow) / #cfe0f0. Sıcak/bej ve yeşilimsi gri tonlar YASAK (nötr rengin B kanalı >= G kanalı olmalı).
- CSS katman sırası: site.css -> experience.css -> polish.css (en son kazanır). Tema: html[data-theme]=dark (varsayılan) | light; URL ?theme=light ile de seçilir. polish.css sonunda iki otorite blok: "[data-theme=dark] ..." koyu dönüşümleri ve "AÇIK TEMA KATMANI" (yüzey tokenları: --surface-0 #f5f3ee, --surface-1 #efede6, --surface-2 #e8ebf0, --surface-tint #e3e8ef, --ink #1b2433, --ink-2 #46505f). Tema kuralları YALNIZCA bu bloklara yazılır.
- Ritim kuralları: koyu temada açık (krem) bölüm sıfır; açık temada sayfa başına en fazla BİR bilinçli koyu tam-genişlik vitrin (index: immersive-scroll) + çerçeveli koyu mockup kartları serbest.
- Test: `npm test` (tests/check.mjs — 28 sayfa, referanslar, çift dillilik, JS sözdizimi) her değişiklikten sonra GEÇMELİ. JS değişince `node --check <dosya>`.
- Görsel doğrulama: yerel sunucu `python3 -m http.server 8077` + puppeteer-core (scratchpad'e `npm i puppeteer-core`, executablePath: /Applications/Google Chrome.app/Contents/MacOS/Google Chrome). Sayfalar `http://127.0.0.1:8077/<sayfa>.html?theme=light|dark`.
- Erişilebilirlik tabanı: WCAG 2.2 AA; görünür metin >= 10.5px; kontrast: normal metin 4.5:1, UI sınırları 3:1.
