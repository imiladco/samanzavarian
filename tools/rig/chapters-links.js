const { chromium } = require('playwright');
const U = 'http://127.0.0.1:8899/?page_id=' + require('fs').readFileSync('pinpage.txt','utf8').trim();
(async () => { const br = await chromium.launch(); const p = await br.newPage({ viewport:{width:1440,height:900} }); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
 await p.addInitScript(() => { window.dataLayer = []; });
 await p.goto(U + '#ai-visual-production-3', {waitUntil:'networkidle'}); await p.waitForTimeout(3500);
 const st = () => p.evaluate(() => ({ tab: [...document.querySelectorAll('.sz-tabs__tab')].findIndex(x=>x.getAttribute('aria-selected')==='true'), piece: [...document.querySelectorAll('.szs--ai .szs-ai__item')].findIndex(e=>e.classList.contains('is-focus')), say: (document.querySelector('.sz-tabs__say')||{}).textContent, chapters: getComputedStyle(document.querySelector('.sz-tabs')).getPropertyValue('--szt-chapters'), fill: getComputedStyle(document.querySelector('.sz-tabs')).getPropertyValue('--szt-fill') }));
 console.log('deep link #ai-visual-production-3:', JSON.stringify(await st()));
 await p.locator('.sz-tabs__nav').screenshot({ path: '../chap-seg.png' });
 // walk down the AI chapters and into direction: analytics events
 const info = await p.evaluate(()=>{ const w=document.querySelector('.sz-pin[data-sz-pin="tabs"]'); return { top: w.getBoundingClientRect().top+scrollY-80, len: parseFloat(getComputedStyle(w,'::after').height), S: JSON.parse(w.dataset.szPinStops), T: +w.dataset.szPinTotal }; });
 for (let k = 0; k < 9; k++) { await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), info.top + info.S[k] * info.len / info.T); await p.waitForTimeout(400); }
 console.log('analytics:', JSON.stringify(await p.evaluate(() => window.dataLayer.filter(e => e.event === 'sz_service_chapter').map(e => e.service_name.split(' ')[0] + ' ' + e.chapter + '/' + e.chapter_count))));
 console.log('errors', errs); await br.close(); })();
