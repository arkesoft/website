# SPRINT 4 BACKLOG — "Yazılım Ajansı Kimliği" (PO+PM onaylı plan)
Spec referansı: .claude/sprints/sprint4-spec.md

## P0 (sıralı)
- ARK-S4-01 Serif nötrleme + --font-mono token + monogram ışıltı kısma (T1,T2,T8). Yalnız polish.css (+otorite bloklara aksan renkleri: koyu #9eb8d6 / açık #3d5b80). Kabul: serif yalnız AS logosunda; computed style sans/normal; npm test.
- ARK-S4-02 Başlık ölçeği (hero h1 clamp(46px,5.4vw,80px) w600 ls-.035em lh1.04; H2 üst sınırlar ~-%15, footer-cta 84→64; body 15px/1.65; 11-12px paragraflar ≥14px) + hero-snug klamp YENİDEN ÖLÇÜMÜ (aynı seçici+media: :lang(en) .page-hero.hero-snug h1 vb.). Kabul: 3 snug sayfa × 1280x720+1366x768 × TR+EN taşmasız; npm test.
- ARK-S4-03 Mono mikro-etiket katmanı (T6 listesi; ≥10.5px; ls .5-1px; tabular-nums; nav/buton/başlık/gövde SANS kalır). Kabul: EN dar-konteyner testi (slide-dot, hero-caption); @≤400 hero-caption gizleme bozulmaz; npm test.
- ARK-S4-04 Buton dili (B1 outline-link → dolgu: radius 8, padding 14/22, 600 13px, transform none, → gap 10, min-h 44; koyu bg var(--bronze)/#0c1017 hover #9eb8d6; açık bg #3d5b80/#f5f3ee — otorite bloklara; B2 text-link 12px transform none; B3 footer-cta 88px daire → 56px + →). Kabul: kontrast ≥4.5:1 iki temada; npm test.
- ARK-S4-05 Works-grid asimetri 0 + kartlara 1px var(--line) + radius 10 (CSS-only). Kabul: grid hizalı; sınır 3:1; npm test.
- ARK-S4-06 Build etiketi JS inject (ARKESOFT · v4 · build 2026.09, mono 10.5px, footer'a site.js ile) + intro ≤2s + tekrar ziyarette localStorage ile atla + "AÇILIŞI TEKRAR İZLE" localStorage sıfırlar. Kabul: HTML diff 0; node --check; senaryo testi; npm test.
- ARK-S4-07 Etiket grameri: Nº yasak; STUDY Nº 006→arkesoft / case #006; KONSEPT / 2026→R&D / 2026; SEÇKİ→Seçili işler; FİLMİ İZLE→SHOWREEL (45 SN)/(45S). HTML+tr.js+en.js birlikte. Kabul: grep Nº=0; anahtar paritesi; npm test.
- ARK-S4-08 Kopya çekirdeği (spec Kopya #1 index hero, #7 index eyebrow, #2 CTA bandı — paylaşımlı anahtar 25 sayfa). Kabul: index 2 tema taşmasız; CTA 3 iç sayfada TR+EN; EN-TR anahtar çakışması yok; npm test.
- ARK-S4-09 Kopya: projeler + web-sitesi hero (#3, #5). Kabul: 2 sayfa × TR+EN × 2 tema × 1280x720 taşmasız; npm test.

## P1 (kapasite kalırsa): S4-10 kopya kalanı (studyo/iletisim/seçki), S4-11 proof-strip (index-only), S4-12 süreç çıktı satırları, S4-13 AGOBEE referans çubuğu. P2: S4-14 durum noktası.

## PM kırmızı çizgiler
site.css/experience.css'e DOKUNULMAZ; tema kuralları yalnız otorite bloklara; tema-bağımsız her şey polish.css sonundaki "TEMA: YAZILIM AJANSI KATMANI (Sprint 4)" bloğuna; her adımda npm test; koyu+açık tema regresyon taraması; kapsam genişlemesi PM mini-onayı ister; kapanış PO+PM çift ONAY.
