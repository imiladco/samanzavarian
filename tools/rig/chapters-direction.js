const { chromium } = require('playwright');
const U = 'http://127.0.0.1:8899/?page_id=' + require('fs').readFileSync('pinpage.txt','utf8').trim();
(async () => { const br = await chromium.launch(); const p = await br.newPage({ viewport:{width:1440,height:900} }); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
 await p.goto(U,{waitUntil:'networkidle'}); await p.waitForTimeout(1000);
 const info = await p.evaluate(()=>{ const w=document.querySelector('.sz-pin[data-sz-pin="tabs"]'); return { top: w.getBoundingClientRect().top+scrollY-80, len: parseFloat(getComputedStyle(w,'::after').height), S: JSON.parse(w.dataset.szPinStops), M: JSON.parse(w.dataset.szPinMap), T: +w.dataset.szPinTotal }; });
 console.log('stops', JSON.stringify(info.S), 'total', info.T);
 const u = info.len / info.T, y = (k) => info.top + info.S[k] * u;
 const st = () => p.evaluate(() => { const r = document.querySelector('.szs--direction'); return { tab: [...document.querySelectorAll('.sz-tabs__tab')].findIndex(x=>x.getAttribute('aria-selected')==='true'), head: r.querySelector('.szs-dir__nh').textContent.trim(), status: r.querySelector('.szs-dir__status').textContent.trim() }; });
 const order = [...info.S.keys()].filter(k => info.M[k][0] === 1);
 const down = []; for (const k of order) { await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(k)); await p.waitForTimeout(3500); const s = await st(); down.push('ch' + info.M[k][1] + ' tab' + s.tab + ': ' + s.status); }
 console.log('down\n  ' + down.join('\n  '));
 const up = []; for (const k of [...order].reverse()) { await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(k)); await p.waitForTimeout(3500); const s = await st(); up.push('ch' + info.M[k][1] + ': ' + s.status); }
 console.log('up\n  ' + up.join('\n  '));
 await p.evaluate(() => { window.__said = []; new MutationObserver(() => { const t = document.querySelector('.szs--direction .szs-dir__status').textContent.trim(); if (window.__said[window.__said.length-1] !== t) window.__said.push(t); }).observe(document.querySelector('.szs--direction .szs-dir__status'), { childList: true, subtree: true, characterData: true }); });
 await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(order[order.length-1])); await p.waitForTimeout(5000);
 console.log('fast first→last, statuses seen:', JSON.stringify(await p.evaluate(()=>window.__said)));
 await p.locator('.szs--direction').screenshot({ path: '../chap-dir.png' });
 console.log('errors', errs); await br.close(); })();
