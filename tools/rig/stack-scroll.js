// Scroll-driven Photo stack: a natural wheel / swipe past the deck must fan it, put every photo in front
// (its video playing), bring the first back, fold it; and the same backwards. LIVE=1: samanzavarian.com
const { chromium } = require('playwright');
const LIVE = process.env.LIVE === '1';
const ID = require('fs').readFileSync(__dirname + '/stackpage2.txt', 'utf8').trim();
const U = LIVE ? 'https://samanzavarian.com/' : 'http://127.0.0.1:8899/?page_id=' + ID;
let fail = 0; const ok = (c, m) => { console.log(`  ${c ? '✓' : '✗'} ${m}`); if (!c) fail++; };

(async () => {
  const b = await chromium.launch();
  for (const phone of [false, true]) {
    console.log(phone ? 'phone' : 'desk');
    const ctx = await b.newContext(phone ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true } : { viewport: { width: 1440, height: 900 } });
    if (LIVE) await ctx.route(/^https:/, async (route) => { const rq = route.request(); try { const r = await fetch(rq.url(), { headers: { 'user-agent': rq.headers()['user-agent'] || 'Mozilla/5.0', accept: rq.headers()['accept'] || '*/*' }, redirect: 'manual' }); const h = {}; r.headers.forEach((v, k) => { if (!['content-encoding', 'content-length', 'transfer-encoding'].includes(k)) h[k] = v; }); let body = Buffer.from(await r.arrayBuffer()); if (process.env.NEW === '1' && /samanzavarian-[a-z-]+\/assets\/(js\/stack\.js|css\/widgets\/stack\.css)/.test(rq.url())) body = require('fs').readFileSync('/home/user/samanzavarian/' + rq.url().split(/samanzavarian-[a-z-]+\//)[1].split('?')[0]); await route.fulfill({ status: r.status, headers: h, body }); } catch (e) { await route.abort(); } });
    const p = await ctx.newPage(); if (LIVE && process.env.NEW === '1') await p.addInitScript(() => document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-sz-stack]').forEach(r => { r.dataset.scroll = 'd t m'; }), true)); const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(U, { waitUntil: 'networkidle', timeout: 90000 }); await p.waitForTimeout(LIVE ? 4000 : 1500); if (LIVE) { await p.mouse.move(5, 5); await p.mouse.wheel(0, 30); await p.waitForTimeout(2500); if (process.env.NEW === '1') await p.evaluate(() => { const r = document.querySelector('[data-sz-stack]'); if (!r.dataset.scroll) { r.dataset.scroll = 'd t m'; } }); }
    if (!LIVE) await p.evaluate(() => { const r = document.querySelector('[data-sz-stack]').closest('.elementor-widget') || document.body.firstElementChild; const a = document.createElement('div'); a.style.height = '150vh'; const z = a.cloneNode(); r.before(a); r.after(z); });
    await p.evaluate(() => {
      const root = document.querySelector('[data-sz-stack]'), cards = [...root.querySelectorAll('.sz-stack__card')];
      window.__log = []; const t0 = performance.now();
      const tick = () => {
        const front = cards.findIndex(c => c.dataset.slot === '0');
        const play = cards.findIndex(c => { const v = c.querySelector('video'); return v && !v.paused; });
        window.__log.push({ t: performance.now() - t0, y: scrollY, front, open: root.classList.contains('is-open'), play, order: cards.map(c => c.dataset.slot).join('') });
        requestAnimationFrame(tick);
      }; tick();
    });
    const deckTop = await p.evaluate(() => document.querySelector('.sz-stack__deck').getBoundingClientRect().top + scrollY);
    const vh = phone ? 844 : 900;
    await p.evaluate((y) => scrollTo(0, y), Math.max(0, deckTop - vh * 1.1)); await p.waitForTimeout(600);
    console.log('    new code', await p.evaluate(() => { const s = window.szwStack && window.szwStack.get(document.querySelector('[data-sz-stack]')); return !!(s && s.scrollDrive) + ' on ' + (s && s.scrollOn); }));
    const base = await p.evaluate(() => [...document.querySelectorAll('.sz-stack__card')].map(c => c.dataset.slot).join(''));
    const n = base.length, hasVid = await p.evaluate(() => [...document.querySelectorAll('.sz-stack__card')].map(c => !!c.querySelector('video')));
    // a reader's pace: one wheel notch (100px) or a short swipe every ~450ms
    const run = async (dir, steps) => {
      for (let i = 0; i < steps; i++) {
        if (phone) await p.evaluate((d) => scrollBy({ top: d * 110, behavior: 'smooth' }), dir); else await p.mouse.wheel(0, dir * 100);
        await p.waitForTimeout(450);
      }
      await p.waitForTimeout(900);
    };
    if (!phone) await p.mouse.move(40, 450); // the mouse rests off the photos
    const mark = async () => p.evaluate(() => window.__log.length);
    const judge = async (from, label) => {
      const L = (await p.evaluate(() => window.__log)).slice(from);
      const seq = []; L.forEach(e => { if (e.open && seq[seq.length - 1] !== e.front) seq.push(e.front); });
      const opened = L.some(e => e.open), end = L[L.length - 1];
      const focus = {}; for (let i = 1; i < L.length; i++) if (L[i].open) focus[L[i].front] = (focus[L[i].front] || 0) + (L[i].t - L[i - 1].t);
      const played = new Set(L.filter(e => e.play >= 0).map(e => e.play));
      console.log('   ', label, 'fronts while open', seq.join('→'), '| ms in front', JSON.stringify(Object.fromEntries(Object.entries(focus).map(([k, v]) => [k, Math.round(v)]))), '| played', [...played].join(','));
      ok(opened, label + ': fans out');
      ok(new Set(seq).size === n, label + ': every photo comes to the front (' + seq.length + ' changes)');
      ok(!end.open && end.order === base && end.play < 0, label + ': folded back as it was, nothing playing (' + end.order + ')');
      hasVid.forEach((v, i) => v && ok(played.has(i), label + ': video of photo ' + (i + 1) + ' played'));
    };
    let m = await mark(); await run(1, Math.ceil((vh * 1.1 + vh * 0.45 + 700) / (phone ? 110 : 100)) + 4); await judge(m, 'down');
    m = await mark(); await run(-1, Math.ceil((vh * 1.1 + vh * 0.45 + 700) / (phone ? 110 : 100)) + 4); await judge(m, 'up');
    ok(!errs.length, 'JS errors: ' + errs.join(' | '));
    await ctx.close();
  }
  await b.close(); console.log(fail ? `\n${fail} FAILED` : '\nALL PASSED'); process.exit(fail ? 1 : 0);
})();
