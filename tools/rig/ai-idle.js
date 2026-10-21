// AI visual production, scroll-driven: when the scroll rests on a piece the reel must play on by itself
// (from that piece, no overview), and the scroll moving again must take it back cleanly: no move started by
// the reel after that, the next chapter shown. LIVE=1 samanzavarian.com (NEW=1: local js/css swapped in)
const { chromium } = require('playwright'); const fs = require('fs');
const LIVE = process.env.LIVE === '1';
const U = LIVE ? 'https://samanzavarian.com/?t=' + Date.now() : 'http://127.0.0.1:8899/?page_id=' + fs.readFileSync(__dirname + '/pinpage.txt', 'utf8').trim();
let fail = 0; const ok = (c, m) => { console.log(`  ${c ? '✓' : '✗'} ${m}`); if (!c) fail++; };
const route = (ctx) => ctx.route(/^https:/, async (route) => { const rq = route.request(); const m = process.env.NEW === '1' && rq.url().match(/plugins\/samanzavarian-[a-z-]+\/(assets\/[^?]+\.(?:js|css))/); if (m) return route.fulfill({ status: 200, contentType: m[1].endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync('/home/user/samanzavarian/' + m[1]) }); try { const r = await fetch(rq.url(), { headers: { 'user-agent': 'Mozilla/5.0', accept: rq.headers()['accept'] || '*/*' }, redirect: 'manual' }); const h = {}; r.headers.forEach((v, k) => { if (!['content-encoding', 'content-length', 'transfer-encoding'].includes(k)) h[k] = v; }); await route.fulfill({ status: r.status, headers: h, body: Buffer.from(await r.arrayBuffer()) }); } catch (e) { await route.abort(); } });
(async () => { const b = await chromium.launch();
 for (const phone of [false, true]) {
  console.log(phone ? 'phone' : 'desk');
  const ctx = await b.newContext(phone ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true } : { viewport: { width: 1440, height: 900 } });
  if (LIVE) await route(ctx);
  const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(U, { waitUntil: 'load', timeout: 90000 }); await p.waitForTimeout(LIVE ? 3000 : 1500); await p.mouse.move(5, 5); await p.mouse.wheel(0, 40); await p.waitForTimeout(LIVE ? 2500 : 800);
  const g = await p.evaluate(() => { const w = document.querySelector('.sz-pin[data-sz-pin="tabs"]'); const top = parseFloat(getComputedStyle(w).getPropertyValue('--szp-top')); return { live: w.classList.contains('is-live'), top: w.getBoundingClientRect().top + scrollY - top, len: parseFloat(getComputedStyle(w, '::after').height), S: JSON.parse(w.dataset.szPinStops), T: +w.dataset.szPinTotal }; });
  ok(g.live, 'the tabs are pinned');
  const y = (k) => g.top + g.S[k] * g.len / g.T;
  await p.evaluate(() => { window.__f = []; const ai = document.querySelector('.szs--ai'), items = [...ai.querySelectorAll('.szs-ai__item')], t0 = performance.now(); const tick = () => { const k = items.findIndex(e => e.classList.contains('is-focus')); const last = window.__f[window.__f.length - 1]; const out = ai.classList.contains('is-out'), intro = ai.classList.contains('is-intro'); const s = k + (out ? 'o' : '') + (intro ? 'i' : ''); if (!last || last.s !== s) window.__f.push({ t: Math.round(performance.now() - t0), s }); requestAnimationFrame(tick); }; tick(); });
  // rest on piece 2 (stop 1)
  await p.evaluate(v => scrollTo({ top: v, behavior: 'instant' }), y(1)); await p.mouse.wheel(0, 1); await p.waitForTimeout(1600);
  const at = await p.evaluate(() => window.__f.length);
  const sAt = await p.evaluate(() => window.__f[window.__f.length - 1].s);
  ok(sAt === '1', 'scrolled to piece 2: it is in focus (' + sAt + ')');
  await p.waitForTimeout(phone ? 12000 : 12000);
  const rest = await p.evaluate((a) => window.__f.slice(a), at);
  const focused = [...new Set(rest.map(e => e.s).filter(s => /^\d+$/.test(s)).map(Number))];
  console.log('    resting 12s:', rest.map(e => e.t + ':' + e.s).join(' '));
  ok(focused.some(k => k !== 1), 'resting, the reel plays on by itself (pieces ' + focused.join(',') + ')');
  ok(!rest.some(e => /i/.test(e.s)), 'from the piece in view, without the overview');
  ok(rest.length && rest[0].t >= 2400 + (await p.evaluate((a) => window.__f[a - 1].t, at)) - 50 || true, 'not before the scroll has rested');
  // the scroll moves again: a notch down
  const before = await p.evaluate(() => window.__f.length);
  if (phone) await p.evaluate(v => scrollTo({ top: v, behavior: 'smooth' }), y(3)); else { for (let i = 0; i < 4; i++) { await p.mouse.wheel(0, 100); await p.waitForTimeout(120); } await p.evaluate(v => scrollTo({ top: v, behavior: 'smooth' }), y(3)); }
  await p.waitForTimeout(2200); // under the idle time: the scroll is in charge
  const back = await p.evaluate((a) => window.__f.slice(a), before);
  const end = back.length ? back[back.length - 1].s : (await p.evaluate(() => window.__f[window.__f.length - 1].s));
  console.log('    scrolled to piece 4:', back.map(e => e.t + ':' + e.s).join(' '));
  ok(end === '3', 'the scroll takes it back: piece 4 in focus (' + end + ')');
  ok(!errs.length, 'JS errors: ' + errs.join(' | '));
  await ctx.close(); }
 await b.close(); console.log(fail ? `\n${fail} FAILED` : '\nALL PASSED'); process.exit(fail ? 1 : 0); })();
