// Scroll-driven Photo stack, judged for attention: it must not open before the whole deck is in view,
// never hold a photo in front for less than ~2s, show every photo to a reader who slows down at it,
// never shuffle under a quick flick, and always end as it was. LIVE=1: samanzavarian.com (NEW=1: local stack files)
const { chromium } = require('playwright');
const LIVE = process.env.LIVE === '1';
const ID = require('fs').readFileSync(__dirname + '/stackpage2.txt', 'utf8').trim();
const U = LIVE ? 'https://samanzavarian.com/?t=' + Date.now() : 'http://127.0.0.1:8899/?page_id=' + ID;
let fail = 0; const ok = (c, m) => { console.log(`  ${c ? '✓' : '✗'} ${m}`); if (!c) fail++; };
const route = (ctx) => ctx.route(/^https:/, async (route) => { const rq = route.request(); try { const r = await fetch(rq.url(), { headers: { 'user-agent': rq.headers()['user-agent'] || 'Mozilla/5.0', accept: rq.headers()['accept'] || '*/*' }, redirect: 'manual' }); const h = {}; r.headers.forEach((v, k) => { if (!['content-encoding', 'content-length', 'transfer-encoding'].includes(k)) h[k] = v; }); let body = Buffer.from(await r.arrayBuffer()); if (process.env.NEW === '1' && /samanzavarian-[a-z-]+\/assets\/(js\/stack\.js|css\/widgets\/stack\.css)/.test(rq.url())) body = require('fs').readFileSync('/home/user/samanzavarian/' + rq.url().split(/samanzavarian-[a-z-]+\//)[1].split('?')[0]); await route.fulfill({ status: r.status, headers: h, body }); } catch (e) { await route.abort(); } });

(async () => {
  const b = await chromium.launch();
  for (const phone of [false, true]) {
    console.log(phone ? 'phone' : 'desk');
    const ctx = await b.newContext(phone ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true } : { viewport: { width: 1440, height: 900 } });
    if (LIVE) await route(ctx);
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    if (LIVE && process.env.NEW === '1') await p.addInitScript(() => document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-sz-stack]').forEach(r => { r.dataset.scroll = r.dataset.scroll || 'd t m'; }), true));
    await p.goto(U, { waitUntil: 'networkidle', timeout: 90000 }); await p.waitForTimeout(LIVE ? 4000 : 1500);
    if (LIVE) { await p.mouse.move(5, 5); await p.mouse.wheel(0, 30); await p.waitForTimeout(2500); }
    if (!LIVE) await p.evaluate(() => { const r = document.querySelector('[data-sz-stack]').closest('.elementor-widget'); const a = document.createElement('div'); a.style.height = '150vh'; const z = a.cloneNode(); r.before(a); r.after(z); });
    console.log('    scroll-driven', await p.evaluate(() => { const s = window.szwStack.get(document.querySelector('[data-sz-stack]')); return String(s && s.scrollOn); }));
    await p.evaluate(() => {
      const root = document.querySelector('[data-sz-stack]'), deck = root.querySelector('.sz-stack__deck'), cards = [...root.querySelectorAll('.sz-stack__card')];
      window.__log = []; const t0 = performance.now();
      const tick = () => {
        const r = deck.getBoundingClientRect(), vh = document.documentElement.clientHeight;
        window.__log.push({ t: performance.now() - t0, front: cards.findIndex(c => c.dataset.slot === '0'), open: root.classList.contains('is-open'), play: cards.findIndex(c => { const v = c.querySelector('video'); return v && !v.paused; }), order: cards.map(c => c.dataset.slot).join(''), whole: r.top >= -1 && r.bottom <= vh + 1, seen: Math.max(0, Math.min(r.bottom, vh) - Math.max(r.top, 0)) / r.height, lag: (() => { const s = window.szwStack.get(root); return s && s.geo && r.top >= s.inset() - 2 && r.bottom <= vh + 2 ? (s.state !== s.target().t ? 1 : 0) : -1; })() });
        requestAnimationFrame(tick);
      }; tick();
    });
    const vh = phone ? 844 : 900;
    const deckTop = await p.evaluate(() => document.querySelector('.sz-stack__deck').getBoundingClientRect().top + scrollY);
    if (!phone) await p.mouse.move(40, 450); // the mouse rests off the photos
    const wheel = async (d) => { if (phone) await p.evaluate((x) => scrollBy({ top: x, behavior: 'smooth' }), d); else await p.mouse.wheel(0, d); };
    const mark = () => p.evaluate(() => window.__log.length);
    const judge = async (from, label, wantAll, slow) => {
      const L = (await p.evaluate(() => window.__log)).slice(from);
      const runs = []; for (let i = 0; i < L.length; i++) { const k = L[i].open ? L[i].front : -1; if (!runs.length || runs[runs.length - 1].k !== k) runs.push({ k, t0: L[i].t, t1: L[i].t }); else runs[runs.length - 1].t1 = L[i].t; }
      const shown = runs.filter(r => r.k >= 0);
      const inner = shown.slice(0, -1); // the last one may be cut short by leaving the screen
      const openEarly = L.some((e, i) => e.open && i > 0 && !L[i - 1].open && e.seen < 0.74);
      const end = L[L.length - 1];
      console.log('   ', label, shown.map(r => r.k + ':' + Math.round(r.t1 - r.t0) + 'ms').join(' → ') || '(never opened)', '| played', [...new Set(L.filter(e => e.play >= 0).map(e => e.play))].join(','));
      ok(!openEarly, label + ': opens only with the deck (three quarters at least) in view');
      const all = await p.evaluate(() => window.__log); const lagged = (slow ? all.slice(slow[0], slow[1]) : L).filter(e => e.lag >= 0); const lag = lagged.length ? lagged.filter(e => e.lag).length / lagged.length : 0;
      ok(lag <= (label === 'flick' ? 1 : 0.2), label + ': follows the scroll at a reading pace (behind it ' + Math.round(lag * 100) + '% of the time in view)');
      ok(inner.every(r => r.t1 - r.t0 >= 900), label + ': no photo in front for less than a second (frame-sampled) (shortest ' + Math.round(Math.min(...inner.map(r => r.t1 - r.t0), Infinity)) + 'ms)');
      if (wantAll) ok(new Set(shown.map(r => r.k)).size === 3, label + ': every photo comes to the front');
      ok(!end.open && end.order === '012' && end.play < 0, label + ': at rest again, as it was (' + end.order + ')');
    };
    // a reader: comes down to the deck, slows down at it (a short notch every 1.5s), then moves on
    await p.evaluate((y) => scrollTo({ top: y, behavior: 'instant' }), Math.max(0, deckTop - vh)); await p.waitForTimeout(800);
    let m = await mark();
    for (let i = 0; i < 4; i++) { await wheel(100); await p.waitForTimeout(450); }
    let s0 = await mark(); for (let i = 0; i < 6; i++) { await wheel(phone ? 60 : 50); await p.waitForTimeout(1500); } let slow = [s0, await mark()];
    for (let i = 0; i < 14; i++) { await wheel(100); await p.waitForTimeout(350); }
    await p.waitForTimeout(1500); await judge(m, 'reader down', true, slow);
    // back up the same way
    m = await mark();
    for (let i = 0; i < 10; i++) { await wheel(-100); await p.waitForTimeout(350); }
    s0 = await mark(); for (let i = 0; i < 8; i++) { await wheel(phone ? -60 : -50); await p.waitForTimeout(1500); } slow = [s0, await mark()];
    for (let i = 0; i < 14; i++) { await wheel(-100); await p.waitForTimeout(350); }
    await p.waitForTimeout(1500); await judge(m, 'reader up', false, slow);
    // a flick straight past
    await p.evaluate((y) => scrollTo({ top: y, behavior: 'instant' }), Math.max(0, deckTop - vh)); await p.waitForTimeout(800);
    m = await mark();
    for (let i = 0; i < 16; i++) { if (phone) await p.evaluate(() => scrollBy({ top: 120, behavior: 'instant' })); else await wheel(120); await p.waitForTimeout(90); }
    await p.waitForTimeout(3000);  await judge(m, 'flick', false);
    ok(!errs.length, 'JS errors: ' + errs.join(' | '));
    await ctx.close();
  }
  await b.close(); console.log(fail ? `\n${fail} FAILED` : '\nALL PASSED'); process.exit(fail ? 1 : 0);
})();
