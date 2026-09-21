// Brief / teklif formu gönderimi (Cloudflare Pages). RESEND_API_KEY yoksa 503 döner;
// site.js bu durumda dosya indirme akışına geri düşer.
export async function onRequestPost({ request, env }) {
  const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
  const key = env.RESEND_API_KEY;
  const to = env.BRIEF_TO || 'arkesoft.info@gmail.com';
  if (!key) return json({ error: 'not-configured' }, 503);
  let payload;
  try { payload = await request.json(); } catch { return json({ error: 'bad-request' }, 400); }
  const { title, items, page } = payload || {};
  if (!Array.isArray(items) || !items.length || items.length > 20) return json({ error: 'bad-request' }, 400);
  const clean = value => String(value ?? '').slice(0, 4000);
  const text = items.map(pair => `${clean(pair && pair[0])}\n${clean(pair && pair[1])}`).join('\n\n') + `\n\nSayfa: ${clean(page)}`;
  const response = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      from: env.BRIEF_FROM || 'Arkesoft Site <onboarding@resend.dev>',
      to: [to],
      subject: clean(title) || 'ARKESOFT — FORM',
      text
    })
  });
  if (!response.ok) return json({ error: 'delivery-failed' }, 502);
  return json({ ok: true });
}
