const { chromium } = require('playwright');
const U = 'http://127.0.0.1:8899/?page_id=' + require('fs').readFileSync('heropage.txt','utf8').trim();
const cases = [];
for (const w of [430, 390, 360]) for (const [name, o] of [['normal', {}], ['font1.2s', { font: 1200 }], ['font4s', { font: 4000 }], ['cpu4x', { cpu: 4 }], ['noProbe', { strip: true, font: 800 }]]) cases.push([w, name, o]);
(async () => { const br = await chromium.launch(); let fails = 0;
 for (const [w, name, o] of cases) {
  const ctx = await br.newContext({ viewport:{width:w,height:844}, isMobile:true, hasTouch:true });
  if (o.font) await ctx.route(/\.(woff2?|ttf)(\?|$)/, async r => { await new Promise(x => setTimeout(x, o.font)); r.continue(); });
  if (o.strip) await ctx.route(/page_id=/, async r => { const res = await r.fetch(); let b = await res.text(); b = b.replace(/<script data-wpmeteor-nooptimize[^>]*>\(function\(me\)[\s\S]*?<\/script>/, ''); await r.fulfill({ response: res, body: b }); });
  const p = await ctx.newPage();
  if (o.cpu) { const c = await ctx.newCDPSession(p); await c.send('Emulation.setCPUThrottlingRate', { rate: o.cpu }); }
  await p.addInitScript(() => { window.__f = []; const t0 = performance.now(); const loop = () => { const t = document.querySelector('.sz-htitle'); if (t) { const cs = getComputedStyle(t); window.__f.push([Math.round(performance.now() - t0), parseFloat(cs.fontSize), cs.visibility]); } if (performance.now() - t0 < 6000) requestAnimationFrame(loop); }; requestAnimationFrame(loop); });
  await p.goto(U, { waitUntil: 'load' }); await p.waitForTimeout(6500);
  const r = await p.evaluate(() => { const t = document.querySelector('.sz-htitle'); const w = [...t.querySelectorAll('.sz-htitle__w')]; const span = Math.max(...w.map(e => e.offsetLeft + e.offsetWidth)) - Math.min(...w.map(e => e.offsetLeft)); const wd = t.closest('.elementor-widget'), par = wd.parentElement, cs = getComputedStyle(par); return { f: window.__f, span: Math.round(span), room: Math.round(par.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight)), doc: document.documentElement.scrollWidth, vw: innerWidth }; });
  const fin = r.f[r.f.length - 1][1];
  const bad = r.f.filter(([, fs, v]) => v === 'visible' && Math.abs(fs - fin) > 0.5);
  const firstVisible = (r.f.find(([, , v]) => v === 'visible') || ['never'])[0];
  const fill = Math.abs(r.span - r.room) <= 6, ok = !bad.length && fill && r.doc <= r.vw && firstVisible !== 'never';
  if (!ok) fails++;
  console.log((ok ? 'PASS' : 'FAIL'), w, name.padEnd(8), 'final', fin.toFixed(1) + 'px', 'shown at', firstVisible + 'ms', 'wrong-size visible frames', bad.length, 'span/room', r.span + '/' + r.room, 'doc', r.doc);
  await ctx.close(); }
 console.log(fails ? fails + ' FAILED' : 'ALL PASS'); await br.close(); })();
