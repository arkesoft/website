# Kontrol kaydı — 19 Eylül 2026

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
