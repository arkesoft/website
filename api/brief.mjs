// Brief / teklif formu gönderimi (Vercel). Secrets are environment variables;
// never put RESEND_API_KEY in the repository or in browser code.
const attempts = globalThis.__arkesoftBriefAttempts || (globalThis.__arkesoftBriefAttempts = new Map());
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
const clean = value => String(value ?? '').replace(/[\u0000-\u001f\u007f]/g, ' ').trim().slice(0, 4000);
function allowedOrigin(req) {
  const origin = req.headers?.origin;
  if (!origin) return true;
  const configured = process.env.PUBLIC_ORIGIN;
  if (configured) return origin === configured;
  const proto = req.headers?.['x-forwarded-proto'] || 'https';
  const host = req.headers?.host;
  return Boolean(host && origin === `${proto}://${host}`);
}
function limited(req) {
  const key = String(req.headers?.['x-forwarded-for'] || req.headers?.['x-real-ip'] || 'unknown').split(',')[0].trim();
  const now = Date.now();
  const previous = attempts.get(key) || [];
  const fresh = previous.filter(time => now - time < 60 * 60 * 1000);
  if (fresh.length >= 5) { attempts.set(key, fresh); return true; }
  fresh.push(now); attempts.set(key, fresh); return false;
}
export default async function handler(req, res) {
  if (req.method !== 'POST') { res.status(405).json({ error: 'method-not-allowed' }); return; }
  if (!allowedOrigin(req)) { res.status(403).json({ error: 'origin-not-allowed' }); return; }
  if (limited(req)) { res.status(429).json({ error: 'rate-limit' }); return; }
  const key = process.env.RESEND_API_KEY;
  const to = process.env.BRIEF_TO || 'arkesoft.info@gmail.com';
  const from = process.env.BRIEF_FROM;
  if (!key || !from || !emailPattern.test(to)) { res.status(503).json({ error: 'not-configured' }); return; }
  const { title, items, page } = req.body || {};
  if (!Array.isArray(items) || !items.length || items.length > 20) { res.status(400).json({ error: 'bad-request' }); return; }
  const emailItem = items.find(pair => String(pair?.[0] || '').toLowerCase().includes('e-posta') || String(pair?.[0] || '').toLowerCase().includes('email'));
  const replyTo = clean(emailItem?.[1]);
  if (!emailPattern.test(replyTo)) { res.status(400).json({ error: 'invalid-email' }); return; }
  const text = items.map(pair => `${clean(pair && pair[0])}\n${clean(pair && pair[1])}`).join('\n\n') + `\n\nSayfa: ${clean(page)}`;
  const response = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      from,
      to: [to],
      reply_to: replyTo,
      subject: clean(title) || 'ARKESOFT — FORM',
      text
    })
  });
  if (!response.ok) { res.status(502).json({ error: 'delivery-failed' }); return; }
  res.status(200).json({ ok: true });
}
