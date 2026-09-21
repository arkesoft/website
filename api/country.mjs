// Vercel adapter. Vercel supplies this request header at its own edge.
export default function handler(request, response) {
  const candidate = request.headers['x-vercel-ip-country'];
  const country = typeof candidate === 'string' && /^[A-Z]{2}$/.test(candidate) && !['XX', 'T1'].includes(candidate) ? candidate : null;
  response.setHeader('Cache-Control', 'private, no-store');
  response.status(200).json({ country });
}
