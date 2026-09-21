# Arkesoft görsel seti üretici — tek sanat yönetimi: lacivert + çelik mavisi "yazılım" sahneleri
import math, random, os

W, H = 1536, 1024
NAVY_DEEP = '#0b111c'
NAVY = '#101827'
PANEL = '#131c2b'
PANEL_EDGE = '#26334a'
STEEL = '#6f8faf'
GLOW = '#9cc0e6'
CYAN = '#7fc7d9'
CREAM = '#f5f3ee'
MUTED = '#4d5d75'
DIM = '#33415a'
MONO = "font-family=\"'SF Mono',Menlo,Consolas,'Liberation Mono',monospace\""

def head(anim=False):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">
<defs>
<radialGradient id="bg" cx="30%" cy="18%" r="120%">
  <stop offset="0" stop-color="#182337"/>
  <stop offset="45%" stop-color="{NAVY}"/>
  <stop offset="100%" stop-color="{NAVY_DEEP}"/>
</radialGradient>
<linearGradient id="steelline" x1="0" y1="0" x2="1" y2="0">
  <stop offset="0" stop-color="{STEEL}" stop-opacity="0"/>
  <stop offset="0.5" stop-color="{GLOW}"/>
  <stop offset="1" stop-color="{STEEL}" stop-opacity="0"/>
</linearGradient>
<linearGradient id="chartfill" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0" stop-color="{STEEL}" stop-opacity="0.35"/>
  <stop offset="1" stop-color="{STEEL}" stop-opacity="0"/>
</linearGradient>
<filter id="soft" x="-60%" y="-60%" width="220%" height="220%">
  <feGaussianBlur stdDeviation="14"/>
</filter>
<filter id="soft28" x="-80%" y="-80%" width="260%" height="260%">
  <feGaussianBlur stdDeviation="28"/>
</filter>
<filter id="grain">
  <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" stitchTiles="stitch"/>
  <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.02 0"/>
  <feComposite operator="over" in2="SourceGraphic"/>
</filter>
<pattern id="grid" width="64" height="64" patternUnits="userSpaceOnUse">
  <path d="M64 0H0V64" fill="none" stroke="#2a3852" stroke-opacity="0.35" stroke-width="1"/>
</pattern>
</defs>
<rect width="{W}" height="{H}" fill="url(#bg)"/>
<rect width="{W}" height="{H}" fill="url(#grid)" opacity="0.5"/>
'''

def vignette():
    return f'''<rect width="{W}" height="{H}" fill="none"/>
<radialGradient id="vig" cx="50%" cy="50%" r="75%">
  <stop offset="60%" stop-color="#000" stop-opacity="0"/>
  <stop offset="100%" stop-color="#05080f" stop-opacity="0.55"/>
</radialGradient>
<rect width="{W}" height="{H}" fill="url(#vig)"/>
<rect width="{W}" height="{H}" fill="#fff" opacity="0.012"/>
</svg>'''

def window(x, y, w, h, title, rx=14):
    bar = 44
    return (f'<g><rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{PANEL}" stroke="{PANEL_EDGE}" stroke-width="1.5"/>'
            f'<rect x="{x}" y="{y+8}" width="{w}" height="{bar}" rx="{rx}" fill="none"/>'
            f'<line x1="{x}" y1="{y+bar}" x2="{x+w}" y2="{y+bar}" stroke="{PANEL_EDGE}" stroke-width="1"/>'
            f'<circle cx="{x+26}" cy="{y+bar/2}" r="5.5" fill="#3a4a66"/>'
            f'<circle cx="{x+46}" cy="{y+bar/2}" r="5.5" fill="#3a4a66"/>'
            f'<circle cx="{x+66}" cy="{y+bar/2}" r="5.5" fill="{STEEL}"/>'
            f'<text x="{x+92}" y="{y+bar/2+4.5}" {MONO} font-size="13.5" letter-spacing="2" fill="{MUTED}">{title}</text></g>')

_orbn = [0]
def glow_orb(cx, cy, r, color=STEEL, op=0.5):
    _orbn[0] += 1
    op = min(op * 0.4, 0.14)
    gid = f'orb{_orbn[0]}'
    return (f'<radialGradient id="{gid}"><stop offset="0" stop-color="{color}" stop-opacity="{op}"/>'
            f'<stop offset="1" stop-color="{color}" stop-opacity="0"/></radialGradient>'
            f'<circle cx="{cx}" cy="{cy}" r="{r*1.6:.0f}" fill="url(#{gid})"/>')

def code_lines(x, y, lines, size=15.5, lh=27):
    out = []
    for i, segs in enumerate(lines):
        tx = x
        parts = []
        for text, color in segs:
            parts.append(f'<tspan fill="{color}">{text}</tspan>')
        out.append(f'<text x="{x}" y="{y + i*lh}" {MONO} font-size="{size}" xml:space="preserve">{"".join(parts)}</text>')
    return ''.join(out)

K = '#8fb4d9'   # keyword
S = '#a8c9a3'   # string
F = '#d9c79a'   # function
P = '#f5f3ee'   # plain
C = '#4d5d75'   # comment
N = '#c9a3a3'   # number/accent

random.seed(7)

# ---------------------------------------------------------------- 1. signal.svg
def signal():
    s = head()
    s += glow_orb(1050, 430, 260, STEEL, 0.28)
    s += glow_orb(430, 700, 200, '#3d5a7a', 0.25)
    # main signature wave: layered beziers
    def wave(amp, yb, op, sw, blur='', ph=0.0):
        pts = []
        for i in range(0, 130):
            x = -20 + i * 12.2
            u = i / 129
            yy = yb + (math.sin(u * 6.8 + ph) * 0.72 + math.sin(u * 13.1 + ph * 1.7) * 0.28) * amp * math.sin(u * math.pi)
            pts.append(f'{x:.0f} {yy:.0f}')
        d = 'M' + ' L'.join(pts)
        return f'<path d="{d}" fill="none" stroke="url(#steelline)" stroke-width="{sw}" opacity="{op}" stroke-linejoin="round" {blur}/>'
    s += wave(170, 520, 0.5, 7, 'filter="url(#soft)"')
    s += wave(170, 520, 1.0, 2.6)
    s += wave(170, 520, 0.9, 1.0)
    s += wave(120, 560, 0.5, 1.4, '', 1.4)
    s += wave(210, 480, 0.35, 1.1, '', 2.6)
    # data particles along field
    for i in range(90):
        x = random.uniform(20, W-20); y = random.uniform(60, H-60)
        r = random.uniform(1, 2.6); op = random.uniform(0.12, 0.5)
        s += f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{r:.1f}" fill="{GLOW}" opacity="{op:.2f}"/>'
    # pulse dots on the wave (animated subtly)
    for cx, cy, delay in [(300, 455, 0), (760, 610, 1.2), (1180, 470, 2.3)]:
        s += (f'<circle cx="{cx}" cy="{cy}" r="5" fill="{CREAM}">'
              f'<animate attributeName="opacity" values="0.2;1;0.2" dur="4s" begin="{delay}s" repeatCount="indefinite"/></circle>'
              f'<circle cx="{cx}" cy="{cy}" r="14" fill="none" stroke="{GLOW}" stroke-opacity="0.5">'
              f'<animate attributeName="r" values="6;22" dur="4s" begin="{delay}s" repeatCount="indefinite"/>'
              f'<animate attributeName="stroke-opacity" values="0.6;0" dur="4s" begin="{delay}s" repeatCount="indefinite"/></circle>')
    s += vignette()
    return s

# ---------------------------------------------------------------- 2. dashboard.svg (orbit)
def dashboard():
    s = head()
    s += glow_orb(1150, 260, 240, STEEL, 0.22)
    x, y, w, h = 176, 132, 1184, 760
    s += window(x, y, w, h, 'ORBIT — KONTROL PANELİ')
    # sidebar
    sx = x
    s += f'<line x1="{x+232}" y1="{y+44}" x2="{x+232}" y2="{y+h}" stroke="{PANEL_EDGE}"/>'
    items = ['Genel bakış', 'Otomasyonlar', 'Veri akışı', 'Raporlar', 'Ekip', 'Ayarlar']
    for i, it in enumerate(items):
        yy = y + 108 + i * 54
        act = i == 1
        if act:
            s += f'<rect x="{x+18}" y="{yy-27}" width="196" height="42" rx="9" fill="{STEEL}" fill-opacity="0.14" stroke="{STEEL}" stroke-opacity="0.4"/>'
        s += f'<circle cx="{x+44}" cy="{yy-6}" r="4" fill="{GLOW if act else DIM}"/>'
        s += f'<text x="{x+62}" y="{yy}" {MONO} font-size="14.5" fill="{CREAM if act else MUTED}">{it}</text>'
    # stat cards
    cards = [('Aktif akış', '128', '+%12'), ('İşlenen görev', '4.2M', '+%31'), ('Hata oranı', '%0.02', '−%40')]
    cw = 280
    for i, (t, v, d) in enumerate(cards):
        cx = x + 264 + i * (cw + 24)
        s += f'<rect x="{cx}" y="{y+76}" width="{cw}" height="128" rx="12" fill="#0f1725" stroke="{PANEL_EDGE}"/>'
        s += f'<text x="{cx+22}" y="{y+112}" {MONO} font-size="13" letter-spacing="2" fill="{MUTED}">{t.upper()}</text>'
        s += f'<text x="{cx+22}" y="{y+164}" {MONO} font-size="38" fill="{CREAM}">{v}</text>'
        s += f'<text x="{cx+cw-70}" y="{y+164}" {MONO} font-size="14" fill="{CYAN}">{d}</text>'
    # area chart
    chx, chy, chw, chh = x + 264, y + 244, 888 - 24, 330
    s += f'<rect x="{chx}" y="{chy}" width="{chw}" height="{chh}" rx="12" fill="#0f1725" stroke="{PANEL_EDGE}"/>'
    s += f'<text x="{chx+22}" y="{chy+38}" {MONO} font-size="13" letter-spacing="2" fill="{MUTED}">HAFTALIK OTOMASYON HACMİ</text>'
    vals = [0.32, 0.44, 0.38, 0.55, 0.48, 0.66, 0.58, 0.75, 0.69, 0.86, 0.8, 0.92]
    pts = [(chx + 40 + i * (chw - 80) / 11, chy + chh - 44 - v * (chh - 120)) for i, v in enumerate(vals)]
    dline = 'M' + ' L'.join(f'{px:.0f} {py:.0f}' for px, py in pts)
    darea = dline + f' L{pts[-1][0]:.0f} {chy+chh-30} L{pts[0][0]:.0f} {chy+chh-30} Z'
    s += f'<path d="{darea}" fill="url(#chartfill)"/>'
    s += f'<path d="{dline}" fill="none" stroke="{GLOW}" stroke-width="2.5" filter="url(#soft)" opacity="0.5"/>'
    s += f'<path d="{dline}" fill="none" stroke="{GLOW}" stroke-width="2"/>'
    for px, py in pts[-1:]:
        s += f'<circle cx="{px:.0f}" cy="{py:.0f}" r="5" fill="{CREAM}"/>'
    # bottom row: bars + list
    bx, by = chx, chy + chh + 24
    s += f'<rect x="{bx}" y="{by}" width="420" height="120" rx="12" fill="#0f1725" stroke="{PANEL_EDGE}"/>'
    for i, v in enumerate([0.5, 0.8, 0.35, 0.65, 0.9, 0.45, 0.7]):
        s += f'<rect x="{bx+30+i*54}" y="{by+100-70*v}" width="26" height="{70*v:.0f}" rx="4" fill="{STEEL}" fill-opacity="{0.35+0.5*v:.2f}"/>'
    s += f'<rect x="{bx+444}" y="{by}" width="420" height="120" rx="12" fill="#0f1725" stroke="{PANEL_EDGE}"/>'
    for i, (dot, txt) in enumerate([(CYAN, 'sync.stok → tamam'), (GLOW, 'rapor.haftalık → gönderildi'), (MUTED, 'yedek.gece → planlandı')]):
        s += f'<circle cx="{bx+470}" cy="{by+34+i*32}" r="4" fill="{dot}"/>'
        s += f'<text x="{bx+488}" y="{by+39+i*32}" {MONO} font-size="13.5" fill="{MUTED}">{txt}</text>'
    s += vignette()
    return s

# ---------------------------------------------------------------- 3. mobile.svg
def mobile():
    s = head()
    s += glow_orb(500, 380, 240, STEEL, 0.25)
    def phone(px, py, scale, main=True):
        pw, ph = 320 * scale, 660 * scale
        r = 48 * scale
        g = f'<g transform="translate({px} {py})">'
        g += f'<rect width="{pw}" height="{ph}" rx="{r}" fill="#0d1522" stroke="{PANEL_EDGE}" stroke-width="2"/>'
        g += f'<rect x="{10*scale}" y="{10*scale}" width="{pw-20*scale}" height="{ph-20*scale}" rx="{r-8*scale}" fill="{PANEL}"/>'
        g += f'<rect x="{pw/2-40*scale}" y="{22*scale}" width="{80*scale}" height="{10*scale}" rx="{5*scale}" fill="#0d1522"/>'
        if main:
            ix = 34 * scale
            g += f'<text x="{ix}" y="{92*scale}" {MONO} font-size="{15*scale}" fill="{MUTED}">Merhaba,</text>'
            g += f'<text x="{ix}" y="{124*scale}" {MONO} font-size="{22*scale}" fill="{CREAM}">Bugünün özeti</text>'
            g += f'<rect x="{ix}" y="{150*scale}" width="{pw-68*scale}" height="{120*scale}" rx="{14*scale}" fill="{STEEL}" fill-opacity="0.16" stroke="{STEEL}" stroke-opacity="0.45"/>'
            g += f'<text x="{ix+18*scale}" y="{186*scale}" {MONO} font-size="{12*scale}" letter-spacing="2" fill="{MUTED}">AKTİF SİPARİŞ</text>'
            g += f'<text x="{ix+18*scale}" y="{234*scale}" {MONO} font-size="{34*scale}" fill="{CREAM}">247</text>'
            for i, v in enumerate([0.4, 0.7, 0.5, 0.85, 0.65, 0.95]):
                bh = 40 * scale * v
                g += f'<rect x="{ix+150*scale+i*18*scale}" y="{248*scale-bh}" width="{10*scale}" height="{bh:.0f}" rx="{3*scale}" fill="{GLOW}" fill-opacity="0.8"/>'
            for i in range(3):
                yy = (296 + i * 74) * scale
                g += f'<rect x="{ix}" y="{yy}" width="{pw-68*scale}" height="{58*scale}" rx="{12*scale}" fill="#0f1725" stroke="{PANEL_EDGE}"/>'
                g += f'<circle cx="{ix+29*scale}" cy="{yy+29*scale}" r="{14*scale}" fill="{DIM}"/>'
                g += f'<rect x="{ix+56*scale}" y="{yy+18*scale}" width="{120*scale}" height="{8*scale}" rx="{4*scale}" fill="{DIM}"/>'
                g += f'<rect x="{ix+56*scale}" y="{yy+34*scale}" width="{80*scale}" height="{7*scale}" rx="{3.5*scale}" fill="#22304a"/>'
                g += f'<circle cx="{ix+pw-108*scale}" cy="{yy+29*scale}" r="{4*scale}" fill="{CYAN}"/>'
            ty = ph - 58 * scale
            g += f'<line x1="{20*scale}" y1="{ty-16*scale}" x2="{pw-20*scale}" y2="{ty-16*scale}" stroke="{PANEL_EDGE}"/>'
            for i in range(4):
                g += f'<rect x="{(58+i*66)*scale}" y="{ty}" width="{22*scale}" height="{22*scale}" rx="{6*scale}" fill="{GLOW if i==0 else DIM}"/>'
        else:
            for i in range(5):
                yy = (90 + i * 100) * scale
                g += f'<rect x="{30*scale}" y="{yy}" width="{pw-60*scale}" height="{80*scale}" rx="{12*scale}" fill="#0f1725" stroke="{PANEL_EDGE}" stroke-opacity="0.6"/>'
        return g + '</g>'
    s += phone(430, 200, 0.92, False)
    s += phone(640, 150, 1.06, True)
    s += vignette()
    return s

# ---------------------------------------------------------------- 4. social.svg (forma)
def social():
    s = head()
    s += glow_orb(1150, 700, 230, '#3d5a7a', 0.3)
    x, y = 300, 120
    s += window(x, y, 936, 784, 'FORMA — İÇERİK TAKVİMİ')
    gx, gy = x + 48, y + 96
    tile = 256; gap = 28
    tags = ['LANSMAN', 'HİKÂYE', 'REEL', 'ÜRÜN', 'KAMPANYA', 'MARKA', 'SERİ', 'PAYLAŞIM', 'ANALİZ']
    for i in range(9):
        r, c = divmod(i, 3)
        tx, ty = gx + c * (tile + gap), gy + r * (tile - 60 + gap)
        hot = i == 4
        fill = f'{STEEL}" fill-opacity="0.2' if hot else '#0f1725'
        s += f'<rect x="{tx}" y="{ty}" width="{tile}" height="{tile-60}" rx="14" fill="{fill}" stroke="{STEEL if hot else PANEL_EDGE}" stroke-opacity="{0.7 if hot else 1}"/>'
        s += f'<text x="{tx+18}" y="{ty+34}" {MONO} font-size="11.5" letter-spacing="2.5" fill="{GLOW if hot else MUTED}">{tags[i]}</text>'
        if hot:
            s += f'<text x="{tx+18}" y="{ty+96}" {MONO} font-size="30" fill="{CREAM}">♥ 48,2B</text>'
            s += f'<text x="{tx+18}" y="{ty+128}" {MONO} font-size="13" fill="{GLOW}">etkileşim +%64</text>'
            s += f'<circle cx="{tx+tile-28}" cy="{ty+30}" r="5" fill="{CYAN}"><animate attributeName="opacity" values="1;0.2;1" dur="2.4s" repeatCount="indefinite"/></circle>'
        else:
            s += f'<rect x="{tx+18}" y="{ty+66}" width="{tile-120}" height="9" rx="4.5" fill="{DIM}"/>'
            s += f'<rect x="{tx+18}" y="{ty+88}" width="{tile-170}" height="8" rx="4" fill="#22304a"/>'
            s += f'<rect x="{tx+18}" y="{ty+142}" width="66" height="24" rx="12" fill="none" stroke="{PANEL_EDGE}"/>'
            s += f'<text x="{tx+30}" y="{ty+158}" {MONO} font-size="10.5" fill="{MUTED}">plan</text>'
    s += vignette()
    return s

# ---------------------------------------------------------------- 5. store.svg (atelier / setup)
def store():
    s = head()
    s += glow_orb(400, 300, 220, STEEL, 0.22)
    x, y = 200, 150
    s += window(x, y, 1136, 724, 'KURULUM — MAĞAZA & ALTYAPI')
    # left: storefront product card
    px, py = x + 56, y + 96
    s += f'<rect x="{px}" y="{py}" width="420" height="520" rx="14" fill="#0f1725" stroke="{PANEL_EDGE}"/>'
    s += f'<rect x="{px+24}" y="{py+24}" width="372" height="270" rx="10" fill="{NAVY}"/>'
    s += f'<circle cx="{px+210}" cy="{py+159}" r="88" fill="{STEEL}" fill-opacity="0.2" stroke="{GLOW}" stroke-opacity="0.6" stroke-width="1.5"/>'
    s += f'<circle cx="{px+210}" cy="{py+159}" r="52" fill="{STEEL}" fill-opacity="0.28"/>'
    s += f'<text x="{px+24}" y="{py+336}" {MONO} font-size="12.5" letter-spacing="2" fill="{MUTED}">YENİ SEZON</text>'
    s += f'<text x="{px+24}" y="{py+372}" {MONO} font-size="21" fill="{CREAM}">Stüdyo Kulaklık</text>'
    s += f'<text x="{px+24}" y="{py+424}" {MONO} font-size="26" fill="{GLOW}">₺4.850</text>'
    s += f'<rect x="{px+24}" y="{py+452}" width="372" height="46" rx="23" fill="{CREAM}"/>'
    s += f'<text x="{px+140}" y="{py+482}" {MONO} font-size="15" fill="{NAVY_DEEP}">Sepete ekle ↗</text>'
    # right: setup checklist rail
    cx = px + 480
    steps = [('Alan adı & DNS', 1), ('Kurumsal e-posta', 1), ('Mağaza kurulumu', 1), ('Ödeme sağlayıcı', 1), ('Kargo entegrasyonu', 0.5), ('Analitik & ölçüm', 0)]
    s += f'<line x1="{cx+14}" y1="{py+30}" x2="{cx+14}" y2="{py+30+5*84}" stroke="{PANEL_EDGE}" stroke-width="2"/>'
    for i, (t, st) in enumerate(steps):
        yy = py + 30 + i * 84
        if st == 1:
            s += f'<circle cx="{cx+14}" cy="{yy}" r="13" fill="{STEEL}"/><path d="M{cx+8} {yy} l4.5 5 8-10" stroke="{NAVY_DEEP}" stroke-width="2.5" fill="none"/>'
        elif st == 0.5:
            s += f'<circle cx="{cx+14}" cy="{yy}" r="13" fill="none" stroke="{GLOW}" stroke-width="2"/><circle cx="{cx+14}" cy="{yy}" r="5" fill="{GLOW}"><animate attributeName="opacity" values="1;0.3;1" dur="2s" repeatCount="indefinite"/></circle>'
        else:
            s += f'<circle cx="{cx+14}" cy="{yy}" r="13" fill="none" stroke="{PANEL_EDGE}" stroke-width="2"/>'
        s += f'<text x="{cx+48}" y="{yy+5}" {MONO} font-size="16.5" fill="{CREAM if st==1 else (GLOW if st==0.5 else MUTED)}">{t}</text>'
        s += f'<text x="{cx+48}" y="{yy+27}" {MONO} font-size="12" fill="{MUTED}">{"tamamlandı" if st==1 else ("kuruluyor…" if st==0.5 else "sırada")}</text>'
    s += vignette()
    return s

# ---------------------------------------------------------------- 6. architecture.svg
def architecture():
    s = head()
    s += glow_orb(768, 470, 300, STEEL, 0.2)
    nodes = {
        'web':    (420, 300, 'WEB'),
        'mobile': (420, 660, 'MOBİL'),
        'api':    (768, 480, 'API'),
        'auth':   (1010, 260, 'KİMLİK'),
        'db':     (1110, 520, 'VERİ'),
        'queue':  (1010, 760, 'KUYRUK'),
        'cdn':    (180, 480, 'CDN'),
    }
    edges = [('cdn','web'),('cdn','mobile'),('web','api'),('mobile','api'),('api','auth'),('api','db'),('api','queue'),('queue','db')]
    for a, b in edges:
        ax, ay, _ = nodes[a]; bx, by, _ = nodes[b]
        s += f'<line x1="{ax}" y1="{ay}" x2="{bx}" y2="{by}" stroke="{DIM}" stroke-width="1.5"/>'
        # flowing pulse
        s += (f'<circle r="4" fill="{GLOW}"><animateMotion dur="{random.uniform(2.5,4.5):.1f}s" repeatCount="indefinite" '
              f'path="M{ax} {ay} L{bx} {by}"/><animate attributeName="opacity" values="0;1;0" dur="2s" repeatCount="indefinite"/></circle>')
    for key, (nx, ny, label) in nodes.items():
        main = key == 'api'
        r = 74 if main else 52
        s += f'<circle cx="{nx}" cy="{ny}" r="{r}" fill="{PANEL}" stroke="{STEEL if main else PANEL_EDGE}" stroke-width="{2.5 if main else 1.5}"/>'
        if main:
            s += f'<circle cx="{nx}" cy="{ny}" r="{r+16}" fill="none" stroke="{STEEL}" stroke-opacity="0.35"><animate attributeName="r" values="{r+10};{r+26}" dur="3.5s" repeatCount="indefinite"/><animate attributeName="stroke-opacity" values="0.4;0" dur="3.5s" repeatCount="indefinite"/></circle>'
        s += f'<text x="{nx}" y="{ny+5}" {MONO} font-size="{16 if main else 13.5}" letter-spacing="2" fill="{CREAM if main else MUTED}" text-anchor="middle">{label}</text>'
    s += vignette()
    return s

# ---------------------------------------------------------------- 7. studio.svg (editor + terminal)
def studio():
    s = head()
    s += glow_orb(1050, 300, 260, STEEL, 0.22)
    # editor window
    x, y = 170, 150
    s += window(x, y, 820, 560, 'arkesoft / deneyim.ts')
    lines = [
        [('const ', K), ('stüdyo ', P), ('= {', P)],
        [('  disiplin: [', P), ("'tasarım'", S), (', ', P), ("'yazılım'", S), ('],', P)],
        [('  ilke: ', P), ("'detayda ustalık'", S), (',', P)],
        [('  hız: ', P), ('Infinity', N), (',', P)],
        [('};', P)],
        [('', P)],
        [('// fikirden ürüne, tek çatı altında', C)],
        [('export function ', K), ('deneyim', F), ('(fikir: ', P), ('Fikir', K), (') {', P)],
        [('  return ', K), ('inceLik', F), ('(fikir)', P)],
        [('    .tasarla({ zarafet: ', P), ('1.0', N), (' })', P)],
        [('    .geliştir({ tip: ', P), ("'sağlam'", S), (' })', P)],
        [('    .yayınla();', P)],
        [('}', P)],
    ]
    s += code_lines(x + 36, y + 96, lines, 16, 30)
    # line numbers
    for i in range(len(lines)):
        s += f'<text x="{x+16}" y="{y+96+i*30}" {MONO} font-size="12.5" fill="#2c3a54">{i+1:02d}</text>'
    # terminal window overlapping
    tx, ty = 760, 520
    s += window(tx, ty, 610, 360, 'terminal — dağıtım')
    term = [
        [('$ ', MUTED), ('arkesoft deploy --prod', P)],
        [('▸ derleniyor… ', MUTED), ('4.180 modül', P)],
        [('▸ testler     ', MUTED), ('142 geçti', S)],
        [('▸ paket       ', MUTED), ('96 kB · optimize', P)],
        [('', P)],
        [('✓ yayında — 8.4s', S)],
        [('  https://arkesoft.dev', GLOW)],
    ]
    s += code_lines(tx + 30, ty + 90, term, 15.5, 30)
    s += f'<rect x="{tx+30}" y="{ty+288}" width="11" height="20" fill="{CREAM}"><animate attributeName="opacity" values="1;0;1" dur="1.2s" repeatCount="indefinite"/></rect>'
    s += vignette()
    return s

# ---------------------------------------------------------------- 8. signature.svg — animated "film"
def signature():
    s = head()
    s += glow_orb(768, 512, 340, STEEL, 0.25)
    # typing code block center-left
    x, y = 220, 300
    s += window(x, y, 640, 420, 'imza.ts')
    tl = [
        [('const ', K), ('imza ', P), ('= ', P), ('await ', K), ('arkesoft', F), ('.craft({', P)],
        [('  fikir: ', P), ("'sizin'", S), (',', P)],
        [('  işçilik: ', P), ("'bizim'", S), (',', P)],
        [('  sonuç: ', P), ("'kalıcı'", S), (',', P)],
        [('});', P)],
    ]
    # animated reveal per line via clip widths
    for i, segs in enumerate(tl):
        line = ''.join(f'<tspan fill="{c}">{t}</tspan>' for t, c in segs)
        s += (f'<g><text x="{x+36}" y="{y+110+i*40}" {MONO} font-size="19" xml:space="preserve">{line}</text>'
              f'<rect x="{x+30}" y="{y+86+i*40}" width="580" height="34" fill="{PANEL}">'
              f'<animate attributeName="width" values="580;0" dur="0.9s" begin="{0.6+i*0.8}s" fill="freeze"/></rect></g>')
    s += f'<rect x="{x+36}" y="{y+312}" width="12" height="22" fill="{CREAM}"><animate attributeName="opacity" values="1;0;1" dur="1s" repeatCount="indefinite"/></rect>'
    # right: forming monogram wave
    cx, cy = 1120, 500
    for i, r in enumerate([70, 110, 150, 190]):
        s += (f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="{STEEL}" stroke-opacity="{0.5-i*0.1}" stroke-width="1.5" stroke-dasharray="{r*6.28:.0f}" stroke-dashoffset="{r*6.28:.0f}">'
              f'<animate attributeName="stroke-dashoffset" values="{r*6.28:.0f};0" dur="{3+i}s" begin="{i*0.5}s" fill="freeze"/></circle>')
    s += f'<text x="{cx}" y="{cy+14}" {MONO} font-size="40" letter-spacing="6" fill="{CREAM}" text-anchor="middle">AS<animate attributeName="opacity" values="0;0;1" dur="2.5s" fill="freeze"/></text>'
    s += (f'<circle cx="{cx}" cy="{cy}" r="210" fill="none" stroke="{GLOW}" stroke-opacity="0.4">'
          f'<animate attributeName="r" values="200;240" dur="4s" repeatCount="indefinite"/>'
          f'<animate attributeName="stroke-opacity" values="0.4;0" dur="4s" repeatCount="indefinite"/></circle>')
    s += vignette()
    return s


# ---------------------------------------------------------------- 9. seo-rank.svg
def seo_rank():
    s = head()
    s += glow_orb(1100, 320, 260, STEEL, 0.28)
    x, y = 250, 140
    s += window(x, y, 1036, 744, 'ARAMA — GÖRÜNÜRLÜK')
    # search bar
    s += f'<rect x="{x+40}" y="{y+80}" width="700" height="58" rx="29" fill="#0f1725" stroke="{STEEL}" stroke-opacity="0.55"/>'
    s += f'<circle cx="{x+72}" cy="{y+109}" r="9" fill="none" stroke="{GLOW}" stroke-width="2"/>'
    s += f'<line x1="{x+79}" y1="{y+116}" x2="{x+88}" y2="{y+125}" stroke="{GLOW}" stroke-width="2"/>'
    s += f'<text x="{x+108}" y="{y+116}" {MONO} font-size="17" fill="{CREAM}">yazılım stüdyosu istanbul</text>'
    s += f'<rect x="{x+40+700+24}" y="{y+80}" width="272" height="58" rx="29" fill="{STEEL}" fill-opacity="0.16" stroke="{STEEL}" stroke-opacity="0.4"/>'
    s += f'<text x="{x+40+700+58}" y="{y+116}" {MONO} font-size="14" fill="{GLOW}">2.4M sonuç · 0.31s</text>'
    # результ list; #1 highlighted as arkesoft
    rows = [(1, 'arkesoft.dev', 'Arkesoft — Yazılım & Dijital Stüdyo', True),
            (2, 'rakip-a.com', 'Dijital ajans hizmetleri', False),
            (3, 'rakip-b.io', 'Web tasarım ve yazılım', False),
            (4, 'rakip-c.net', 'Kurumsal yazılım çözümleri', False)]
    for i, (rank, dom, title, hot) in enumerate(rows):
        ry = y + 176 + i * 108
        s += f'<rect x="{x+40}" y="{ry}" width="620" height="92" rx="12" fill="{"#16233a" if hot else "#0f1725"}" stroke="{STEEL if hot else PANEL_EDGE}" stroke-opacity="{0.8 if hot else 1}"/>'
        s += f'<text x="{x+66}" y="{ry+40}" {MONO} font-size="15" fill="{GLOW if hot else MUTED}">#{rank}</text>'
        s += f'<text x="{x+112}" y="{ry+36}" {MONO} font-size="13" fill="{CYAN if hot else MUTED}">{dom}</text>'
        s += f'<text x="{x+112}" y="{ry+64}" {MONO} font-size="16.5" fill="{CREAM if hot else DIM}">{title}</text>'
        if hot:
            s += f'<circle cx="{x+630}" cy="{ry+46}" r="5" fill="{CYAN}"><animate attributeName="opacity" values="1;0.25;1" dur="2.2s" repeatCount="indefinite"/></circle>'
    # growth chart right
    gx, gy, gw, gh = x + 700, y + 176, 296, 300
    s += f'<rect x="{gx}" y="{gy}" width="{gw}" height="{gh}" rx="12" fill="#0f1725" stroke="{PANEL_EDGE}"/>'
    s += f'<text x="{gx+20}" y="{gy+34}" {MONO} font-size="12" letter-spacing="2" fill="{MUTED}">ORGANİK TRAFİK</text>'
    vals = [0.2, 0.26, 0.24, 0.35, 0.45, 0.42, 0.58, 0.7, 0.66, 0.85]
    pts = [(gx + 24 + i * (gw - 48) / 9, gy + gh - 36 - v * (gh - 110)) for i, v in enumerate(vals)]
    d = 'M' + ' L'.join(f'{a:.0f} {b:.0f}' for a, b in pts)
    s += f'<path d="{d} L{pts[-1][0]:.0f} {gy+gh-24} L{pts[0][0]:.0f} {gy+gh-24} Z" fill="url(#chartfill)"/>'
    s += f'<path d="{d}" fill="none" stroke="{GLOW}" stroke-width="2.4"/>'
    s += f'<circle cx="{pts[-1][0]:.0f}" cy="{pts[-1][1]:.0f}" r="5" fill="{CREAM}"/>'
    s += f'<text x="{gx+20}" y="{gy+gh-52}" {MONO} font-size="22" fill="{CREAM}">+%212</text>'
    # keyword chips
    ky = gy + gh + 28
    chips = [('yazılım ajansı', 1), ('e-ticaret kurulum', 1), ('mobil uygulama', 0)]
    cxp = gx
    for txt, up in chips:
        wch = 24 + len(txt) * 8.6
        s += f'<rect x="{cxp:.0f}" y="{ky}" width="{wch:.0f}" height="36" rx="18" fill="none" stroke="{STEEL if up else PANEL_EDGE}" stroke-opacity="0.6"/>'
        s += f'<text x="{cxp+12:.0f}" y="{ky+23}" {MONO} font-size="12.5" fill="{GLOW if up else MUTED}">{txt}</text>'
        ky += 48
    s += vignette()
    return s

OUT = '/Users/kullanici/Desktop/arkesoft/my_site/versiyonlar/arkesoft/assets'
scenes = {
    'signal.svg': signal, 'dashboard.svg': dashboard, 'mobile-app.svg': mobile,
    'social.svg': social, 'store.svg': store, 'architecture-sys.svg': architecture,
    'studio.svg': studio, 'signature.svg': signature, 'seo-rank.svg': seo_rank,
}
import re as _re
def _sanitize(svg):
    return _re.sub(r'&(?!amp;|#\d|[a-zA-Z]+;)', '&amp;', svg)

for name, fn in scenes.items():
    svg = _sanitize(fn())
    open(os.path.join(OUT, name), 'w').write(svg)
    print(name, len(svg)//1024, 'KB')
