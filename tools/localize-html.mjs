// Translate the site's controlled HTML sources without a browser or dependencies.
import fs from 'node:fs';
import vm from 'node:vm';
const context = { window: {} };
vm.runInNewContext(fs.readFileSync(new URL('../locales/en.js', import.meta.url), 'utf8'), context);
const dictionary = context.window.ARKESOFT_LOCALES.en;
const decode = value => value.replace(/&(?:amp|lt|gt|quot|apos|nbsp|#\d+|#x[\da-f]+);/gi, entity => {
  const named = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&apos;': "'", '&nbsp;': '\u00a0' };
  return named[entity] ?? String.fromCodePoint(entity[2].toLowerCase() === 'x' ? parseInt(entity.slice(3), 16) : parseInt(entity.slice(2), 10));
});
const escape = value => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const translate = value => dictionary[decode(value).replace(/\s+/g, ' ').trim()] || decode(value);
export function localizeHTML(html, routes, englishRoutes) {
  const localURL = value => {
    if (value.startsWith('#')) return value;
    const origin = new URL(html.match(/rel="canonical" href="([^"]+)"/)[1]).origin;
    const url = new URL(decode(value), origin);
    if (url.origin !== origin) return value;
    const file = Object.keys(routes).find(file => routes[file] === url.pathname);
    if (!file) return value;
    url.pathname = englishRoutes[file];
    return value.startsWith('/') ? url.pathname + url.search + url.hash : url.href;
  };
  const localSchema = value => {
    if (Array.isArray(value)) return value.map(localSchema);
    if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, key === 'inLanguage' ? 'en' : localSchema(item)]));
    if (typeof value !== 'string') return value;
    return value.startsWith('https://') ? localURL(value) : translate(value);
  };
  const stack = [];
  const voidTags = new Set(['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr']);
  return html.replace(/<!--[\s\S]*?-->|<script\b[\s\S]*?<\/script>|<style\b[\s\S]*?<\/style>|<(?:"[^"]*"|'[^']*'|[^'">])*>|[^<]+/gi, token => {
    if (/^<script/i.test(token)) {
      if (!token.includes('type="application/ld+json"')) return token;
      return token.replace(/>([\s\S]*?)<\/script>/, (_, json) => '>' + JSON.stringify(localSchema(JSON.parse(json))) + '</script>');
    }
    if (/^<(?:style|!)/i.test(token)) return token;
    if (token.startsWith('</')) { stack.pop(); return token; }
    if (token.startsWith('<')) {
      const tag = token.match(/^<([\w-]+)/)?.[1]?.toLowerCase();
      const skip = stack.at(-1) || /\bdata-no-translate\b/.test(token) || ['code','pre','textarea','svg'].includes(tag);
      if (!voidTags.has(tag) && !token.endsWith('/>')) stack.push(skip);
      if (tag === 'html') token = token.replace('lang="tr"', 'lang="en"');
      if (tag === 'button' && token.includes('data-language=')) token = token.replace(/aria-pressed="[^"]+"/, `aria-pressed="${token.includes('data-language="en"')}"`);
      // Alternate-language URLs deliberately retain both language variants.
      if (!/\bhreflang=/.test(token)) token = token.replace(/\bhref="([^"]+)"/g, (_, value) => `href="${escape(decode(localURL(value)))}"`);
      if (tag === 'meta' && /(?:og:url|og:image|twitter:image)/.test(token)) token = token.replace(/content="([^"]+)"/, (_, value) => `content="${escape(decode(localURL(value)))}"`);
      if (tag === 'meta' && token.includes('og:locale')) token = token.replace('tr_TR', 'en_US');
      if (tag === 'meta' && /(?:description|og:title|twitter:title)/.test(token)) token = token.replace(/content="([^"]+)"/, (_, value) => `content="${escape(translate(value))}"`);
      if (!skip) token = token.replace(/\b(alt|aria-label|placeholder|title)="([^"]+)"/g, (_, attr, value) => `${attr}="${escape(translate(value))}"`);
      return token;
    }
    if (stack.at(-1) || !token.trim()) return token;
    return token.match(/^\s*/)[0] + escape(translate(token).trim()) + token.match(/\s*$/)[0];
  });
}
