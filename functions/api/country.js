// Cloudflare Pages adapter. The hosting edge supplies the country; no external request.
export function onRequestGet({ request }) {
  const candidate = request.cf?.country;
  const country = typeof candidate === 'string' && /^[A-Z]{2}$/.test(candidate) && !['XX', 'T1'].includes(candidate) ? candidate : null;
  return new Response(JSON.stringify({ country }), {
    headers: { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'private, no-store' }
  });
}
