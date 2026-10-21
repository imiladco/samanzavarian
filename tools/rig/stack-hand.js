// Photo stack: the hand and the scroll together. A still cursor the page scrolls the deck under is not a
// hover; a hover ending never closes what the scroll fanned; a tap on the front photo, Esc and a tap outside
// close it; the next scroll step fans it again; a width where it is off puts it at rest.
const { chromium } = require('playwright'); const fs = require('fs');
const U = 'http://127.0.0.1:8899/?page_id=' + fs.readFileSync(__dirname + '/stackpage2.txt', 'utf8').trim();
let fail = 0; const ok = (c, m) => { console.log(`  ${c ? '✓' : '✗'} ${m}`); if (!c) fail++; };
const st = (p) => p.evaluate(() => { const s = window.szwStack.get(document.querySelector('[data-sz-stack]')); const r = s.deck.getBoundingClientRect(); return { open: s.root.classList.contains('is-open'), state: s.state, t: s.geo ? s.target().t : null, seen: Math.max(0, Math.min(r.bottom, innerHeight) - Math.max(r.top, 0)) / r.height, front: s.order[0], order: s.order.join('') }; });
const spacers = (p) => p.evaluate(() => { const r = document.querySelector('[data-sz-stack]').closest('.elementor-widget'); const a = document.createElement('div'); a.style.height = '150vh'; const z = a.cloneNode(); r.before(a); r.after(z); });
// the scroll position where the path is at fraction q
const at = (p, q) => p.evaluate((q) => { const s = window.szwStack.get(document.querySelector('[data-sz-stack]')); return Math.round(s.geo.y0 + q * (s.geo.y1 - s.geo.y0)); }, q);
(async () => { const b = await chromium.launch();
 { console.log('desk');
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(U, { waitUntil: 'networkidle' }); await spacers(p); await p.waitForTimeout(800);
  const deck = await p.evaluate(() => { const r = document.querySelector('.sz-stack__deck').getBoundingClientRect(); return { x: r.left + r.width / 2, top: r.top + scrollY, h: r.height }; });
  // the cursor parked where the deck will pass, then only the wheel
  await p.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), deck.top - 900 - 200); await p.mouse.move(deck.x, 450); await p.waitForTimeout(300);
  await p.evaluate(() => { const s = window.szwStack.get(document.querySelector('[data-sz-stack]')); window.__early = null; const tick = () => { const r = s.deck.getBoundingClientRect(); const seen = Math.max(0, Math.min(r.bottom, innerHeight) - Math.max(r.top, 0)) / r.height; if (s.root.classList.contains('is-open') && window.__early === null) window.__early = seen; requestAnimationFrame(tick); }; tick(); });
  for (let i = 0; i < 9; i++) { await p.mouse.wheel(0, 100); await p.waitForTimeout(500); }
  await p.waitForTimeout(1500);
  const early = await p.evaluate(() => window.__early);
  ok(early === null || early >= 0.74, 'a still cursor the deck scrolls under does not fan it early (first fanned at ' + (early === null ? 'never' : early.toFixed(2)) + ' in view)');
  // in the middle of the path, the scroll has fanned it
  await p.mouse.move(40, 450);
  await p.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), await at(p, 0.5)); await p.mouse.wheel(0, 1); await p.waitForTimeout(1800);
  let s = await st(p); ok(s.open && s.state === 2, 'mid-path: fanned by the scroll, photo 2 in front (state ' + s.state + ')');
  // a hover over it and away: still fanned
  await p.mouse.move(deck.x - 30, 450, { steps: 5 }); await p.waitForTimeout(500); await p.mouse.move(40, 450, { steps: 5 }); await p.waitForTimeout(800);
  s = await st(p); ok(s.open, 'a hover ending leaves the scroll\'s fan alone');
  // a real hover still fans it when at rest
  await p.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), await at(p, -0.12)); await p.waitForTimeout(900);
  s = await st(p); ok(!s.open, 'before the path: at rest');
  const r2 = await p.evaluate(() => { const r = document.querySelector('.sz-stack__deck').getBoundingClientRect(); return { x: r.left + r.width / 2, y: Math.min(innerHeight - 20, r.top + r.height / 3) }; });
  await p.mouse.move(r2.x, r2.y, { steps: 6 }); await p.waitForTimeout(700); s = await st(p); ok(s.open, 'a hand moving over it fans it');
  await p.mouse.move(40, 40, { steps: 6 }); await p.waitForTimeout(800); s = await st(p); ok(!s.open, 'and leaving folds it');
  // Esc on a scroll-fanned deck
  await p.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), await at(p, 0.5)); await p.mouse.wheel(0, 1); await p.waitForTimeout(1500);
  await p.focus('.sz-stack__card[data-slot="0"]'); await p.keyboard.press('Escape'); await p.waitForTimeout(700);
  s = await st(p); ok(!s.open, 'Esc folds what the scroll fanned');
  await p.evaluate(y => scrollTo({ top: y, behavior: 'smooth' }), await at(p, 0.8)); await p.waitForTimeout(2200);
  s = await st(p); ok(s.open && s.state === 3, 'the next scroll step fans it again (state ' + s.state + ')');
  // narrower than desktop with only desktop on: at rest
  await p.evaluate(() => { const s = window.szwStack.get(document.querySelector('[data-sz-stack]')); s.scrollOn = ['d']; });
  await p.setViewportSize({ width: 900, height: 900 }); await p.waitForTimeout(1200);
  s = await st(p); ok(!s.open && s.state === 0 && s.order === '012', 'a width where it is off: at rest, as it was (' + s.order + ')');
  ok(!errs.length, 'JS errors: ' + errs.join(' | '));
  await ctx.close(); }
 { console.log('phone');
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(U, { waitUntil: 'networkidle' }); await spacers(p); await p.waitForTimeout(800);
  await p.evaluate(y => scrollTo({ top: y, behavior: 'instant' }), await at(p, 0.5)); await p.evaluate(() => scrollBy(0, 1)); await p.waitForTimeout(1800);
  let s = await st(p); ok(s.open && s.state === 2, 'mid-path: fanned by the scroll (state ' + s.state + ')');
  let f = await (await p.$('.sz-stack__card[data-slot="0"]')).boundingBox(); await p.touchscreen.tap(f.x + f.width / 2, f.y + f.height / 2); await p.waitForTimeout(800);
  s = await st(p); ok(!s.open, 'a tap on the front photo folds what the scroll fanned');
  f = await (await p.$('.sz-stack__card[data-slot="0"]')).boundingBox(); await p.touchscreen.tap(f.x + f.width / 2, f.y + f.height / 2); await p.waitForTimeout(800);
  s = await st(p); ok(s.open, 'a second tap fans it again');
  await p.touchscreen.tap(8, 420); await p.waitForTimeout(800);
  s = await st(p); ok(!s.open, 'a tap outside folds it');
  ok(!errs.length, 'JS errors: ' + errs.join(' | '));
  await ctx.close(); }
 await b.close(); console.log(fail ? `\n${fail} FAILED` : '\nALL PASSED'); process.exit(fail ? 1 : 0); })();
