// a person browsing: 3 wheel notches, a pause, again. Every AI piece must be seen in focus,
// and the scene should be mid-transition only a small share of the time.
const { chromium } = require('playwright');
const LIVE = process.env.LIVE === '1';
const U = LIVE ? 'https://samanzavarian.com/?t=' + Date.now() : 'http://127.0.0.1:8899/?page_id=' + require('fs').readFileSync(__dirname + '/pinpage.txt','utf8').trim();
(async () => { const br = await chromium.launch(); const ctx = await br.newContext({ viewport:{width:1440,height:900} });
 const NEW = process.env.NEW === '1', NP = NEW ? JSON.parse(require('fs').readFileSync(__dirname + '/../newpath.json', 'utf8')) : null;
 if (LIVE) await ctx.route(/^https:/, async (route) => { const rq = route.request();
  const m = NEW && rq.url().match(/plugins\/samanzavarian-main\/(assets\/[^?]+)/);
  if (m) { return route.fulfill({ status: 200, contentType: m[1].endsWith('.css') ? 'text/css' : 'application/javascript', body: require('fs').readFileSync('/home/user/samanzavarian/' + m[1]) }); }
  try { const r = await fetch(rq.url(), { headers: { 'user-agent': 'Mozilla/5.0', accept: rq.headers()['accept'] || '*/*' }, redirect: 'manual' }); const h = {}; r.headers.forEach((v, k) => { if (!['content-encoding','content-length','transfer-encoding'].includes(k)) h[k] = v; }); let body = Buffer.from(await r.arrayBuffer()); if (NEW && /text\/html/.test(r.headers.get('content-type') || '')) { body = Buffer.from(body.toString().replace(/data-sz-pin-stops="[^"]*"/, 'data-sz-pin-stops="' + NP.stops + '"').replace(/data-sz-pin-total="[^"]*"/, 'data-sz-pin-total="' + NP.total + '"').replace(/(data-sz-pin="tabs"[^>]*--szp-len:)[0-9.]+svh/, '$1' + NP.total + 'svh')); } await route.fulfill({ status: r.status, headers: h, body }); } catch (e) { await route.abort(); } });
 const p = await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
 await p.goto(U, { waitUntil: 'load', timeout: 90000 }); await p.waitForTimeout(LIVE ? 6000 : 1500);
 await p.evaluate(() => { window.__t = []; const t0 = performance.now(); const loop = () => { const items = [...document.querySelectorAll('.szs--ai .szs-ai__item')]; const tab = [...document.querySelectorAll('.sz-tabs__tab')].findIndex(x => x.getAttribute('aria-selected') === 'true'); window.__t.push([performance.now() - t0, tab, items.findIndex(e => e.classList.contains('is-focus')), document.querySelector('.szs--ai').classList.contains('is-out')]); if (performance.now() - t0 < 30000) requestAnimationFrame(loop); }; requestAnimationFrame(loop); });
 await p.mouse.move(800, 450);
 const start = await p.evaluate(() => { const w = document.querySelector('.sz-pin[data-sz-pin="tabs"]'); return w.getBoundingClientRect().top + scrollY - 300; });
 await p.evaluate(v => scrollTo({ top: v, behavior: 'instant' }), start); await p.waitForTimeout(500);
 for (let i = 0; i < 14; i++) { for (let k = 0; k < 3; k++) { await p.mouse.wheel(0, 100); await p.waitForTimeout(60); } await p.waitForTimeout(1300); }
 const t = await p.evaluate(() => window.__t);
 const ai = t.filter(x => x[1] === 0); // while the AI tab is open
 const seen = new Set(); ai.forEach(x => { if (x[2] >= 0 && !x[3]) seen.add(x[2]); });
 // time in focus per piece (ms)
 const dwell = {}; for (let k = 1; k < ai.length; k++) { const [ms, , f, out] = ai[k]; if (f >= 0 && !out) dwell[f] = (dwell[f] || 0) + (ms - ai[k-1][0]); }
 const transit = ai.filter(x => x[2] < 0 || x[3]).length / Math.max(1, ai.length);
 const n = await p.evaluate(() => document.querySelectorAll('.szs--ai .szs-ai__item').length);
 const ok = seen.size === n && transit < 0.25 && !errs.length;
 console.log((ok ? 'PASS' : 'FAIL'), LIVE ? 'live' : 'rig', 'pieces seen in focus', [...seen].sort().join(',') + ' of ' + n, '| in transit', Math.round(transit * 100) + '%', '| ms in focus', JSON.stringify(Object.fromEntries(Object.entries(dwell).map(([k, v]) => [k, Math.round(v)]))), errs);
 await br.close(); })();
