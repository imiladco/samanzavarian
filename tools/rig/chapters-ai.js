const { chromium } = require('playwright');
const U = 'http://127.0.0.1:8899/?page_id=' + require('fs').readFileSync('pinpage.txt','utf8').trim();
(async () => { const br = await chromium.launch(); const p = await br.newPage({ viewport:{width:1440,height:900} }); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
 await p.goto(U,{waitUntil:'networkidle'}); await p.waitForTimeout(1000);
 const info = await p.evaluate(()=>{ const w=document.querySelector('.sz-pin[data-sz-pin="tabs"]'); return { top: w.getBoundingClientRect().top+scrollY-80, len: parseFloat(getComputedStyle(w,'::after').height), S: JSON.parse(w.dataset.szPinStops), M: JSON.parse(w.dataset.szPinMap), T: +w.dataset.szPinTotal }; });
 console.log('stops', JSON.stringify(info.S), 'map', JSON.stringify(info.M), 'total', info.T);
 await p.evaluate(() => { window.__seq = []; const items = [...document.querySelectorAll('.szs--ai .szs-ai__item')]; new MutationObserver(() => { const k = items.findIndex(e => e.classList.contains('is-focus')); if (k >= 0 && window.__seq[window.__seq.length-1] !== k) window.__seq.push(k); }).observe(document.querySelector('.szs--ai'), { attributes: true, subtree: true, attributeFilter: ['class'] }); });
 const u = info.len / info.T, y = (k) => info.top + info.S[k] * u;
 const st = () => p.evaluate(() => ({ tab: [...document.querySelectorAll('.sz-tabs__tab')].findIndex(x=>x.getAttribute('aria-selected')==='true'), focus: [...document.querySelectorAll('.szs--ai .szs-ai__item')].findIndex(e=>e.classList.contains('is-focus')), hud: (document.querySelector('.szs--ai .szs-ai__hud b, .szs--ai [class*="hud"]')||{}).textContent }));
 const order = [...info.S.keys()].filter(k => info.M[k][0] === 0);
 const down = []; for (const k of [...order, order[order.length-1]+1]) { await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(k)); await p.waitForTimeout(2600); const s = await st(); down.push(k + '→tab' + s.tab + ':piece' + s.focus); }
 console.log('down', down.join('  '));
 const up = []; for (const k of [...order].reverse()) { await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(k)); await p.waitForTimeout(2600); const s = await st(); up.push(k + '→piece' + s.focus); }
 console.log('up  ', up.join('  '));
 await p.evaluate(() => { window.__seq = []; });
 await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(order[order.length-1])); await p.waitForTimeout(4000);
 console.log('fast 0→last: focus sequence', JSON.stringify(await p.evaluate(()=>window.__seq)), 'final', JSON.stringify(await st()));
 await p.locator('.szs--ai').screenshot({ path: '../chap-ai.png' });
 console.log('errors', errs); await br.close(); })();
