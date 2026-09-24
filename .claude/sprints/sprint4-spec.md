# Sprint 4 Keşif Spec'i — "Yazılım Ajansı Kimliği" (uiux + artdir + architect konsolide)

## Teşhis (3 rapor hemfikir)
Site couture/moda dilinde konuşuyor: (1) Didot italik `.serif` ikinci satırlar (28 sayfada 129 span) tema algısını tek başına belirliyor; (2) şiirsel-kanıtsız kopya; (3) 8-9px aşırı-tracked moda etiketleri (STUDY Nº 006, KONSEPT/2026); (4) hairline-uppercase davetiye butonları; (5) galeri asimetrisi; (6) AGOBEE altın kartı lüks sinyalini büyütüyor. DOĞRU OLAN: SVG mockup sanatı (panel/terminal/kod) birinci sınıf yazılım sinyali — kabuk ona hizalanacak. Palet + iki tema + i18n + 28 sayfa DOKUNULMAZ.

## Tipografi kararları
- T1. `.serif` TAMAMEN nötrleşir (ArtDir: yarım doz yok). CSS-only (Architect: polish.css SONUNA yeni blok; site.css'e dokunma; span'ler HTML'de kalır — 0 i18n maliyeti). Kural: `.serif{font-family:inherit;font-style:normal;font-weight:300;letter-spacing:-.03em}` + tema aksanı OTORITE BLOKLARINA: koyu `#9eb8d6`, açık `#3d5b80`. Üst satır 600 ağırlık (h1/h2/section-title'a font-weight:600). Serif yalnız AS logo SVG'sinde yaşar (o çizim). `.hero h1 .serif{display:block}` yapısı korunur.
- T2. Webfont YOK (ArtDir kararı; UIUX'un sıfır-webfont alternatifi): `--font-mono:ui-monospace,'SF Mono',Menlo,Consolas,'Liberation Mono',monospace` token'ı.
- T3. H1 ölçek: hero h1 clamp(70,7.5vw,116) → clamp(46px,5.4vw,80px), weight 600, ls -.035em, lh 1.04. page-title/contact-heading h1 benzer oranda düşer. DİKKAT (Architect R1): hero-snug klampları (polish 216-234) Didot ile ölçüldü — font değişince otomasyon/sosyal-medya/mobil-uygulama 1280x720+1366x768 TR+EN YENİDEN ÖLÇÜM ŞART; ezme aynı seçici+media koşullarıyla (`:lang(en) .page-hero.hero-snug h1` vb.).
- T4. H2/section-title: weight 600, ls -.025em, clamp üst sınırları ~%15 düşer (footer-cta h2 84→64).
- T5. Gövde: body 15px/1.65; 11-12px içerik paragrafları ≥14px.
- T6. Mono mikro-etiket katmanı: `.eyebrow,.section-label,.breadcrumbs,.hero-caption,.project-info>span,.case-meta span,.slide-dot,.footer-bottom,.deliverables span,.filter,.journey-counter,.immersive-index,.reference-meta,.portfolio-status,.code-signature` → var(--font-mono), font-size ≥10.5px (küçükler BÜYÜTÜLEREK çevrilir), letter-spacing .5-1px (2.2px iptal), tabular-nums. Mono OLMAYACAK yerler: nav, butonlar, başlıklar, gövde. Architect R: mono aynı px'te geniştir — dar konteynerler (slide-dot 46px, hero-caption mobil 180px) EN dahil test; site.css @≤400 `.hero-caption{display:none}` hatırla.
- T7. Etiket grameri: `Nº` yasak; STUDY Nº 006→`arkesoft / case #006`; KONSEPT / 2026→`R&D / 2026`; SEÇKİ→Seçili işler; ayraçlar `— / ·` (locale değişiklikleri!). "FİLMİ İZLE"→"SHOWREEL (45 SN)" / EN "SHOWREEL (45S)".
- T8. AS monogram drop-shadow ışıltısı kısılır (mühür değil logo).

## Buton/link dili
- B1. `.outline-link` (birincil kullanımlar): dolgu buton — koyu tema bg var(--bronze) metin #0c1017 hover #9eb8d6; açık tema bg #3d5b80 metin #f5f3ee; radius 8px; padding 14px 22px; 600 13px; text-transform none; ok `→` gap 10px; min-height 44px. Tema renkleri OTORITE BLOKLARINA.
- B2. `.text-link`: 12px, text-transform none, gap 10px, alt çizgi kalır.
- B3. `.arrow-round` CTA daireleri: footer-cta 88px daire → etiketli dolgu butona (CSS-only mümkün değilse 56px'e küçült + `→`); kart hover okları kalır.

## Yazılım sinyali bileşenleri (doz: viewport başına ≤2 motif; motif bilgi taşır)
- C1. `.proof-strip` (index hero'ya): 4 mono hücre `≤2.5s · LCP hedefi / 100 · Lighthouse erişilebilirlik / 28 · sayfa, 2 dil / %100 · özel kod, şablonsuz` — değer 20px/600 sans, etiket 11px mono muted; border-top var(--line). Yalnız doğrulanabilir metrik, uydurma yok. (HTML+locale: index only bu sprint.)
- C2. Süreç çıktı satırları (`.process-step`'e mono `çıktı: kapsam dokümanı` vb.) — uzmanlık/hizmet süreç bölümleri. (HTML+locale)
- C3. Referans kartı (projeler AGOBEE): üstüne mono çubuk `● ● ● agobee.com.tr — canlı`, teslimat satırı `teslimat: kurumsal site · e-posta altyapısı · SEO kurulumu`. (HTML+locale)
- C4. Works-grid asimetri kalkar (`nth-child(even) margin-top` → 0); kartlara 1px var(--line) çerçeve + radius 10px.
- C5. Footer'a build etiketi: `ARKESOFT · v4 · build 2026.09` mono 10.5px. (HTML+locale, footer paylaşımlı — 28 sayfa; JS ile inject daha ucuz: site.js data-build)
- C6. Durum noktası: footer "sistemler çalışıyor" + iletişim "yanıt ~48s" — #6f8faf pulse (yeşil yasak).
- C7. Intro: ≤2s'ye kısalt; tekrar ziyarette otomatik atla (localStorage); footer "AÇILIŞI TEKRAR İZLE" kalır.

## Kopya değişimleri (PO onayına tabi; her biri = HTML metin + tr.js + en.js anahtar; paylaşılan anahtarlar 25 sayfaya yansır — Architect R5)
1. index hero: "Dijitalde imzanız." → TR "Fikirden ürüne,<br>uçtan uca yazılım." / EN "From idea to product.<br>End-to-end software."
2. CTA bandı (25 sayfa paylaşımlı): "İyi bir fikir. Olağanüstü bir başlangıç." → "Projenizi anlatın.<br>48 saat içinde yol haritasıyla dönelim." / "Tell us about your project.<br>We reply with a roadmap within 48 hours."
3. projeler hero: "Fikirler geçer. İzler kalır." → "Seçili işler.<br>Gerçek mühendislik kararları." / "Selected work.<br>Real engineering decisions."
4. studyo hero: "İşimiz dijital. Meselemiz insan." → "Küçük ekip,<br>kıdemli işçilik." / "A small team<br>of senior builders."
5. web-sitesi hero: "Görünenden çok daha fazlası." → "Hızlı, ölçülebilir,<br>bakımı kolay web." / "Fast, measurable,<br>maintainable web."
6. iletisim hero: "Önce bir merhaba. Sonra olasılıklar." → "Kapsam, takvim, bütçe —<br>ilk görüşmede netleşir." / "Scope, timeline, budget —<br>clear from the first call."
7. index eyebrow: "ÖZEL YAZILIM. SEÇKİN DİJİTAL DENEYİMLER." → "CUSTOM SOFTWARE — WEB · MOBİL · OTOMASYON" / "...WEB · MOBILE · AUTOMATION"
8. index seçki: "Detayda saklı bir başka seviye." → "Seçili işler —<br>6 konsept, 6 disiplin." / "Selected work —<br>6 concepts, 6 disciplines."
(Not: <br>/span bölünmesi nedeniyle çoğu başlık 2 locale anahtarıdır; EN değeri mevcut TR anahtarıyla çakışmamalı.)

## Uygulama stratejisi (Architect)
polish.css EN SONUNA tek blok `/* ===== TEMA: YAZILIM AJANSI KATMANI (Sprint 4) ===== */` (tema-bağımsız her şey) + tema renkleri mevcut iki otorite bloğun İÇİNE. site.css/experience.css'e dokunma. Sıra: (1) mono token + serif nötrleme → test+görsel; (2) hero klamp yeniden ölçüm; (3) mono etiketler grup grup; (4) tema renk ekleri; (5) bileşenler (HTML+locale birlikte); (6) kopya en son, toplu; her adımda `npm test`.
