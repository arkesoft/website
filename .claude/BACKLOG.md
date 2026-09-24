# Arkesoft Sprint Backlog (devredilen işler)

## Sprint 3 adayları

### ~~ARK-S3-01~~ — TAMAMLANDI (Sprint 3, PO+PM onaylı) — Servis sayfalarında hero/header çakışması
otomasyon, sosyal-medya ve mobil-uygulama sayfalarında (her iki temada, Sprint 2 öncesinden beri var) uzun hero kopyası h1'i sticky header altına itiyor (ölçüm: h1 top ~88.8px < header bottom 108px); eyebrow logoyla çakışıyor. Hero üst boşluğu/offset düzeltilecek.
Kabul: 3 sayfada iki temada da header ile hero metinleri arasında sıfır piksel çakışma; diğer 12 hero sayfasında görsel regresyon yok; `npm test` geçer.

### ARK-S3-02 — Video'lar için açık tema davranışı — P2 — Sahip: arke-frontend
Hero/film video'larının light varyantı yok; açık temada .15 soluklukta kalıyorlar. Karar: ya video'ya açık poster/overlay çözümü ya bilinçli koyu vitrin ilanı.

### ARK-S4-01 — EN kısa-viewport klamp kapsamı — P2 — Sahip: arke-frontend
PM notu (Sprint 3): kısa-viewport (≤885px) h1 klampında :lang(en) istisnası yalnız .hero-snug sayfalarını kapsıyor; hero-snug olmayan servis sayfaları EN'de genel klampa düşüyor. Uzun EN başlık eklenirse gözden geçir; yeni uzun EN başlıklı sayfaya hero-snug uygula.

### ARK-S4-02 — Mobilde eyebrow / dekoratif mockup kesişimi — P2 — Sahip: arke-frontend
PO notu (Sprint 3): 390x844'te servis hero'larında eyebrow, arka plandaki soluk mockup metniyle görsel kesişiyor (Sprint 3 öncesinden, kozmetik). İstenirse mobilde hero-media opaklığı/konumu ayarlanır.

### PO kararı (Sprint 3): EN başlıklarını kısaltma story'si AÇILMAYACAK — dil başına ayrı klamp doğru tipografik çözüm.
### PM süreç notu: kök-neden kaynaklı kapsam genişlemeleri sprint ortasında PM mini-onayına sunulacak.
