// Injected into every page (top frame only). Draws a visible cursor, a click
// ripple and a highlight box. Purely visual: it never touches the site's data.
// Positions are pushed from Node (Director), so it also works over iframes
// such as the block editor canvas, whose mouse events never reach this frame.
module.exports = function overlayScript({ color }) {
  return `(() => {
  if (window.top !== window || window.__tv) return;
  const C = ${JSON.stringify(color)};
  const css = \`
    #tv-layer{position:fixed;inset:0;pointer-events:none;z-index:2147483647}
    #tv-cursor{position:absolute;left:0;top:0;width:34px;height:34px;transform:translate(-4px,-3px);
      filter:drop-shadow(0 3px 6px rgba(0,0,0,.35));transition:transform .08s}
    #tv-cursor.down{transform:translate(-4px,-3px) scale(.86)}
    .tv-ripple{position:absolute;width:16px;height:16px;margin:-8px 0 0 -8px;border-radius:50%;
      border:3px solid \${C};animation:tvr .55s ease-out forwards}
    @keyframes tvr{to{transform:scale(4.2);opacity:0}}
    #tv-hl{position:absolute;border:3px solid \${C};border-radius:10px;opacity:0;
      box-shadow:0 0 0 9999px rgba(15,18,30,.28),0 0 0 6px \${C}33;
      transition:opacity .25s, left .35s, top .35s, width .35s, height .35s}
    #tv-hl.on{opacity:1;animation:tvp 1.1s ease-in-out infinite}
    @keyframes tvp{50%{box-shadow:0 0 0 9999px rgba(15,18,30,.28),0 0 0 12px \${C}22}}
  \`;
  function mount() {
    if (document.getElementById('tv-layer')) return;
    const s = document.createElement('style'); s.textContent = css;
    const l = document.createElement('div'); l.id = 'tv-layer';
    l.innerHTML = '<div id="tv-hl"></div><svg id="tv-cursor" viewBox="0 0 24 24">' +
      '<path d="M3 2l7.5 19 2.6-7.9L21 10.5z" fill="#fff" stroke="#111" stroke-width="1.4" stroke-linejoin="round"/></svg>';
    document.documentElement.append(s, l);
    const p = window.__tvPos || { x: -100, y: -100 };
    window.__tv.move(p.x, p.y);
  }
  window.__tv = {
    move(x, y) {
      window.__tvPos = { x, y };
      const c = document.getElementById('tv-cursor');
      if (c) { c.style.left = x + 'px'; c.style.top = y + 'px'; }
    },
    press(down) {
      const c = document.getElementById('tv-cursor'); if (!c) return;
      c.classList.toggle('down', down);
      if (down) {
        const r = document.createElement('div'); r.className = 'tv-ripple';
        r.style.left = window.__tvPos.x + 'px'; r.style.top = window.__tvPos.y + 'px';
        document.getElementById('tv-layer').append(r); setTimeout(() => r.remove(), 700);
      }
    },
    highlight(b) {
      const h = document.getElementById('tv-hl'); if (!h) return;
      if (!b) { h.classList.remove('on'); return; }
      const pad = 6;
      Object.assign(h.style, { left: (b.x - pad) + 'px', top: (b.y - pad) + 'px',
        width: (b.width + pad * 2) + 'px', height: (b.height + pad * 2) + 'px' });
      h.classList.add('on');
    },
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
})();`;
};
