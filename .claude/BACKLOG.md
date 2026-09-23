# Arkesoft Sprint Backlog (devredilen işler)

## Sprint 3 adayları

### ARK-S3-01 — Servis sayfalarında hero/header çakışması — P1 — Sahip: arke-frontend (arke-artdir danışır)
otomasyon, sosyal-medya ve mobil-uygulama sayfalarında (her iki temada, Sprint 2 öncesinden beri var) uzun hero kopyası h1'i sticky header altına itiyor (ölçüm: h1 top ~88.8px < header bottom 108px); eyebrow logoyla çakışıyor. Hero üst boşluğu/offset düzeltilecek.
Kabul: 3 sayfada iki temada da header ile hero metinleri arasında sıfır piksel çakışma; diğer 12 hero sayfasında görsel regresyon yok; `npm test` geçer.

### ARK-S3-02 — Video'lar için açık tema davranışı — P2 — Sahip: arke-frontend
Hero/film video'larının light varyantı yok; açık temada .15 soluklukta kalıyorlar. Karar: ya video'ya açık poster/overlay çözümü ya bilinçli koyu vitrin ilanı.
