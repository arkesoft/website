// Brief / teklif formu gönderimi (Vercel). RESEND_API_KEY tanımlı değilse 503 döner;
// site.js bu durumda dosya indirme akışına geri düşer.
export default async function handler(req, res) {
  if (req.method !== 'POST') { res.status(405).json({ error: 'method-not-allowed' }); return; }
  const key = process.env.RESEND_API_KEY;
  const to = process.env.BRIEF_TO || 'arkesoft.info@gmail.com';
  if (!key) { res.status(503).json({ error: 'not-configured' }); return; }
  const { title, items, page } = req.body || {};
  if (!Array.isArray(items) || !items.length || items.length > 20) { res.status(400).json({ error: 'bad-request' }); return; }
  const clean = value => String(value ?? '').slice(0, 4000);
  const text = items.map(pair => `${clean(pair && pair[0])}\n${clean(pair && pair[1])}`).join('\n\n') + `\n\nSayfa: ${clean(page)}`;
  const response = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      from: process.env.BRIEF_FROM || 'Arkesoft Site <onboarding@resend.dev>',
      to: [to],
      subject: clean(title) || 'ARKESOFT — FORM',
      text
    })
  });
  if (!response.ok) { res.status(502).json({ error: 'delivery-failed' }); return; }
  res.status(200).json({ ok: true });
}
