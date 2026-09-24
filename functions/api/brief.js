// Brief / teklif formu gönderimi (Cloudflare Pages). Secrets stay in env vars.
const attempts = new Map();
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
const clean = value => String(value ?? '').replace(/[\u0000-\u001f\u007f]/g, ' ').trim().slice(0, 4000);
function limited(request) {
  const key = (request.headers.get('CF-Connecting-IP') || request.headers.get('x-forwarded-for') || 'unknown').split(',')[0].trim();
  const now = Date.now(), fresh = (attempts.get(key) || []).filter(time => now - time < 3600000);
  if (fresh.length >= 5) { attempts.set(key, fresh); return true; }
  fresh.push(now); attempts.set(key, fresh); return false;
}
export async function onRequestPost({ request, env }) {
  const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
  const origin = request.headers.get('Origin');
  if (origin && env.PUBLIC_ORIGIN && origin !== env.PUBLIC_ORIGIN) return json({ error: 'origin-not-allowed' }, 403);
  if (limited(request)) return json({ error: 'rate-limit' }, 429);
  const key = env.RESEND_API_KEY;
  const to = env.BRIEF_TO || 'arkesoft.info@gmail.com';
  const from = env.BRIEF_FROM;
  if (!key || !from || !emailPattern.test(to)) return json({ error: 'not-configured' }, 503);
  let payload;
  try { payload = await request.json(); } catch { return json({ error: 'bad-request' }, 400); }
  const { title, items, page } = payload || {};
  if (!Array.isArray(items) || !items.length || items.length > 20) return json({ error: 'bad-request' }, 400);
  const emailItem = items.find(pair => String(pair?.[0] || '').toLowerCase().includes('e-posta') || String(pair?.[0] || '').toLowerCase().includes('email'));
  const replyTo = clean(emailItem?.[1]);
  if (!emailPattern.test(replyTo)) return json({ error: 'invalid-email' }, 400);
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
  if (!response.ok) return json({ error: 'delivery-failed' }, 502);
  return json({ ok: true });
}
