// Runs inside the browser. Detect text clipped inside a section even when the
// document itself has no horizontal scrollbar (for example, absolute heroes).
module.exports = function checkLayout() {
  const issues = [];
  if (document.documentElement.scrollWidth > innerWidth) issues.push('Page has horizontal overflow');
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode, element = node.parentElement;
    if (!node.textContent.trim() || element.closest('script,style,svg,[hidden],.gallery-track,.intro,dialog:not([open])') || !element.getClientRects().length) continue;
    const range = document.createRange();
    range.selectNodeContents(node);
    const rects = [...range.getClientRects()];
    for (let parent = element; parent && parent !== document.body; parent = parent.parentElement) {
      const style = getComputedStyle(parent), box = parent.getBoundingClientRect();
      const clippedX = ['hidden', 'clip'].includes(style.overflowX) && rects.some(rect => rect.right > box.right + 3 || rect.left < box.left - 3);
      const clippedY = ['hidden', 'clip'].includes(style.overflowY) && rects.some(rect => rect.bottom > box.bottom + 3 || rect.top < box.top - 3);
      if (clippedX || clippedY) { issues.push(`Clipped by ${parent.className}: ${node.textContent.trim().slice(0,80)}`); break; }
    }
  }
  const hero = document.querySelector('.hero-content');
  if (hero) {
    const content = hero.getBoundingClientRect();
    const bar = document.querySelector('.hero-bottom').getBoundingClientRect();
    const header = document.querySelector('.header').getBoundingClientRect();
    const crumbs = document.querySelector('.breadcrumbs')?.getBoundingClientRect();
    if (content.top < Math.max(header.bottom, crumbs?.bottom || 0) + 12) issues.push('Hero overlaps navigation');
    if (content.bottom > bar.top - 10) issues.push('Hero overlaps bottom controls');
  }
  return [...new Set(issues)];
};
