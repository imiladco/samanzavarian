// Photo stack, scroll-driven: the scroll is the playhead. Judged for exactly that:
// - one scroll position, one picture: the same coming down or going up, and the same again later
// - the scroll stops, the photos stop (once the easing has landed)
// - a little scroll, a little movement: no jump between two nearby positions
// - in order: at rest → fanned on photo 1 → 2 → 3 → at rest as it was; videos play while their photo rests in front
// - a hand takes it over; gone and the page moved on, the scroll has it again
// LIVE=1 samanzavarian.com (NEW=1 local js/css swapped in)
const { chromium } = require('playwright'); const fs = require('fs');
const LIVE = process.env.LIVE === '1';
const U = LIVE ? 'https://samanzavarian.com/?t=' + Date.now() : 'http://127.0.0.1:8899/?page_id=' + fs.readFileSync(__dirname + '/stackpage2.txt', 'utf8').trim();
let fail = 0; const ok = (c, m) => { console.log(`  ${c ? '✓' : '✗'} ${m}`); if (!c) fail++; };
const route = (ctx) => ctx.route(/^https:/, async (route) => { const rq = route.request(); const m = process.env.NEW === '1' && rq.url().match(/plugins\/samanzavarian-[a-z-]+\/(assets\/[^?]+\.(?:js|css))/); if (m) return route.fulfill({ status: 200, contentType: m[1].endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync('/home/user/samanzavarian/' + m[1]) }); try { const r = await fetch(rq.url(), { headers: { 'user-agent': 'Mozilla/5.0', accept: rq.headers()['accept'] || '*/*' }, redirect: 'manual' }); const h = {}; r.headers.forEach((v, k) => { if (!['content-encoding', 'content-length', 'transfer-encoding'].includes(k)) h[k] = v; }); await route.fulfill({ status: r.status, headers: h, body: Buffer.from(await r.arrayBuffer()) }); } catch (e) { await route.abort(); } });
// the photos as drawn: each card's matrix (translate x, y, angle, scale) and the front one
const look = (p) => p.evaluate(() => { const root = document.querySelector('[data-sz-stack]'); const cards = [...root.querySelectorAll('.sz-stack__card')]; return { open: root.classList.contains('is-open'), front: cards.findIndex(c => c.dataset.slot === '0'), order: cards.map(c => c.dataset.slot).join(''), play: cards.findIndex(c => { const v = c.querySelector('video'); return v && !v.paused; }), m: cards.map(c => { const t = new DOMMatrix(getComputedStyle(c).transform); return [t.e, t.f, Math.atan2(t.b, t.a) * 180 / Math.PI, Math.hypot(t.a, t.b)]; }) }; });
const dist = (a, b) => Math.max(...a.m.map((m, i) => Math.max(Math.abs(m[0] - b.m[i][0]), Math.abs(m[1] - b.m[i][1]), Math.abs(m[2] - b.m[i][2]) * 4, Math.abs(m[3] - b.m[i][3]) * 400)));
(async () => { const b = await chromium.launch();
 for (const phone of [false, true]) {
  console.log(phone ? 'phone' : 'desk');
  const ctx = await b.newContext(phone ? { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true } : { viewport: { width: 1440, height: 900 } });
  if (LIVE) await route(ctx);
  const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  if (LIVE && process.env.NEW === '1') await p.addInitScript(() => document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-sz-stack]').forEach(r => { r.dataset.scroll = r.dataset.scroll || 'd t m'; }), true));
  await p.goto(U, { waitUntil: 'networkidle', timeout: 90000 }); await p.waitForTimeout(LIVE ? 3500 : 1200);
  if (LIVE) { await p.mouse.move(5, 5); await p.mouse.wheel(0, 30); await p.waitForTimeout(2500); }
  if (!LIVE) await p.evaluate(() => { const r = document.querySelector('[data-sz-stack]').closest('.elementor-widget'); const a = document.createElement('div'); a.style.height = '150vh'; const z = a.cloneNode(); r.before(a); r.after(z); });
  await p.waitForTimeout(600);
  const geo = await p.evaluate(() => { const s = window.szwStack.get(document.querySelector('[data-sz-stack]')); return s.geo; });
  ok(!!geo, 'scroll-driven is on (path ' + (geo ? Math.round(geo.y1 - geo.y0) + 'px' : '-') + ')');
  const go = async (q, wait = 900) => { await p.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), Math.round(geo.y0 + q * (geo.y1 - geo.y0))); await p.waitForTimeout(wait); return look(p); };
  // the sweep down, at rest positions, and back up
  const Q = [-0.3, 0, 0.05, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 0.95, 1, 1.3];
  const down = []; for (const q of Q) down.push(await go(q));
  const up = []; for (const q of [...Q].reverse()) up.push(await go(q)); up.reverse();
  console.log('    down', down.map((s, i) => Q[i] + ':' + (s.open ? 'F' : 'r') + s.front + (s.play >= 0 ? 'v' + s.play : '')).join(' '));
  const worst = Math.max(...down.map((s, i) => dist(s, up[i])));
  ok(worst < 2, 'one position, one picture: down and up agree (worst ' + worst.toFixed(2) + 'px)');
  // the order
  const fronts = []; down.forEach(s => { if (s.open && fronts[fronts.length - 1] !== s.front) fronts.push(s.front); });
  ok(fronts.join('') === '012', 'in order: fanned on photo 1, 2, 3 (' + fronts.join('') + ')');
  ok(!down[0].open && !down[1].open && down[0].order === '012', 'before the path: at rest');
  const last = down[down.length - 1]; ok(!last.open && last.order === '012' && last.play < 0, 'after it: at rest, as it was, nothing playing (' + last.order + ')');
  // stops when the scroll stops
  await go(0.33, 50); await p.waitForTimeout(700); const s1 = await look(p); await p.waitForTimeout(1500); const s2 = await look(p);
  ok(dist(s1, s2) < 0.5, 'the scroll stopped: the photos stopped (moved ' + dist(s1, s2).toFixed(2) + 'px in 1.5s)');
  // continuity: 8px steps through a move
  let jump = 0, prev = await go(0.12, 700);
  for (let y = 0; y < 26; y++) { const s = await go(0.12 + y * 8 / (geo.y1 - geo.y0), 500); jump = Math.max(jump, dist(prev, s)); prev = s; }
  ok(jump < 40, 'a little scroll, a little movement: at most ' + jump.toFixed(1) + 'px for 8px of scroll');
  // reversible again later: the same spot as the sweep
  const again = await go(0.5); ok(dist(again, down[Q.indexOf(0.5)]) < 2, 'the same spot later: the same picture');
  // the hand (desktop): a moving mouse takes it over; gone, and the page moved on, the scroll again
  if (!phone) {
    await go(0.22);
    const r = await p.evaluate(() => { const d = document.querySelector('.sz-stack__deck').getBoundingClientRect(); return { x: d.left + d.width / 2, y: d.top + d.height / 2 }; });
    await p.mouse.move(r.x - 40, r.y, { steps: 6 }); await p.waitForTimeout(600);
    const side = await p.$('.sz-stack__card[data-slot="2"]'); const sb = await side.boundingBox(); await p.mouse.click(sb.x + sb.width * 0.85, sb.y + sb.height * 0.4); await p.waitForTimeout(900);
    const h1 = await look(p); ok(h1.open && h1.front !== 0, 'a hand brings a photo forward (front ' + h1.front + ')');
    await p.mouse.move(5, 300, { steps: 6 }); await p.waitForTimeout(500);
    const back = await go(0.75, 1300); const want = down[Q.indexOf(0.7)];
    ok(back.front === want.front && back.open, 'the hand gone and the page moved on: the scroll has it again (front ' + back.front + ', expected ' + want.front + ')');
  } else {
    await go(0.22);
    const f = await (await p.$('.sz-stack__card[data-slot="0"]')).boundingBox(); await p.touchscreen.tap(f.x + f.width / 2, f.y + f.height / 2); await p.waitForTimeout(800);
    ok(!(await look(p)).open, 'a tap on the front photo folds it');
    const back = await go(0.75, 1300); ok(back.open && back.front === down[Q.indexOf(0.7)].front, 'the page moved on: the scroll has it again');
  }
  ok(!errs.length, 'JS errors: ' + errs.join(' | '));
  await ctx.close(); }
 await b.close(); console.log(fail ? `\n${fail} FAILED` : '\nALL PASSED'); process.exit(fail ? 1 : 0); })();
