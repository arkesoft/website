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

## Sprint 5 adayları (Sprint 4 kapanışından, PO önceliklendirmesiyle)
1. ARK-S4-13 → S5: AGOBEE referans çubuğu (P1 BAŞI — PO kararı: gerçek referans, ajans algısına en yüksek katkı): `● ● ● agobee.com.tr — canlı` + `teslimat: kurumsal site · e-posta altyapısı · SEO kurulumu`.
2. ARK-S4-11 → S5: Proof-strip (index hero metrik satırı).
3. ARK-S4-10 → S5: Kopya kalanı (studyo, iletisim hero + index seçki başlığı).
4. ARK-S4-12 → S5: Süreç çıktı satırları (mono `çıktı: ...`).
5. ARK-S5-01 (P1): Mobil pause (Ⅱ) butonu / WhatsApp balonu örtüşmesi (360/390/430px'te örtüşme 0).
6. ARK-S5-02 (P1): EN görünümde dekoratif SVG/mockup içi TR etiketler (i18n'e bağla veya EN varyant).
7. ARK-S5-03 (P2): 9px sans mikro kalıntılar ≥10.5px tabana (TR/EN dil anahtarı, MENÜ, expertise numaraları).
8. ARK-S4-14 (P2): Durum noktası (footer "sistemler çalışıyor", #6f8faf pulse).
