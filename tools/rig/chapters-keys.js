const { chromium } = require('playwright');
const U = 'http://127.0.0.1:8899/?page_id=' + require('fs').readFileSync('pinpage.txt','utf8').trim();
(async () => { const br = await chromium.launch(); const p = await br.newPage({ viewport:{width:1440,height:900} }); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
 await p.goto(U,{waitUntil:'networkidle'}); await p.waitForTimeout(1000);
 // preload: give pieces 1..5 a video (metadata only), as the site's videos are
 await p.evaluate(() => document.querySelectorAll('.szs--ai .szs-ai__item').forEach((it, k) => { if (k) { const v = document.createElement('video'); v.preload = 'metadata'; v.muted = true; it.appendChild(v); } }));
 const info = await p.evaluate(()=>{ const w=document.querySelector('.sz-pin[data-sz-pin="tabs"]'); return { top: w.getBoundingClientRect().top+scrollY-80, len: parseFloat(getComputedStyle(w,'::after').height), S: JSON.parse(w.dataset.szPinStops), T: +w.dataset.szPinTotal }; });
 await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), info.top + info.S[0] * info.len / info.T); await p.waitForTimeout(2500);
 const pre = () => p.evaluate(() => [...document.querySelectorAll('.szs--ai .szs-ai__item')].map(it => { const v = it.querySelector('video'); return v ? v.preload[0] : '-'; }).join(''));
 console.log('at piece 1, preload per piece (m=metadata a=auto):', await pre());
 await p.locator('.sz-tabs__tab').first().focus();
 const st = () => p.evaluate(() => [...document.querySelectorAll('.sz-tabs__tab')].findIndex(x=>x.getAttribute('aria-selected')==='true') + '/' + [...document.querySelectorAll('.szs--ai .szs-ai__item')].findIndex(e=>e.classList.contains('is-focus')));
 const seq = []; for (let i = 0; i < 7; i++) { await p.keyboard.press('PageDown'); await p.waitForTimeout(2600); seq.push(await st() + '@' + await p.evaluate(()=>Math.round(scrollY))); if (i === 2) seq.push('preload ' + await pre()); }
 console.log('PageDown x7 (tab/piece):', seq.join('  '));
 const up = []; for (let i = 0; i < 3; i++) { await p.keyboard.press('PageUp'); await p.waitForTimeout(2600); up.push(await st()); }
 console.log('PageUp x3:', up.join('  '), 'errors', errs); await br.close(); })();
