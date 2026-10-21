const { chromium } = require('playwright');
const URL0 = 'https://samanzavarian.com/?t=' + Date.now();
const route = async (ctx) => ctx.route(/^https:/, async (route) => { const rq = route.request(); try { const r = await fetch(rq.url(), { headers: { 'user-agent': rq.headers()['user-agent'] || 'Mozilla/5.0', accept: rq.headers()['accept'] || '*/*' }, redirect: 'manual' }); const h = {}; r.headers.forEach((v, k) => { if (!['content-encoding','content-length','transfer-encoding'].includes(k)) h[k] = v; }); await route.fulfill({ status: r.status, headers: h, body: Buffer.from(await r.arrayBuffer()) }); } catch (e) { await route.abort(); } });
(async () => { const br = await chromium.launch();
 const ctx = await br.newContext({ viewport:{width:1440,height:900} }); await route(ctx);
 const p = await ctx.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message + ' @ ' + (e.stack||'').split('\n')[1]));
 await p.addInitScript(() => { window.dataLayer = window.dataLayer || []; });
 await p.goto(URL0, { waitUntil: 'load', timeout: 90000 }); await p.waitForTimeout(4000);
 await p.mouse.move(700, 400); await p.mouse.wheel(0, 50); await p.waitForTimeout(1500); // WP Meteor: a first interaction
 const info = await p.evaluate(()=>{ const w=document.querySelector('.sz-pin[data-sz-pin="tabs"]'); const top = parseFloat(getComputedStyle(w).getPropertyValue('--szp-top')); return { live: w.classList.contains('is-live'), top: w.getBoundingClientRect().top+scrollY-top, len: parseFloat(getComputedStyle(w,'::after').height), S: JSON.parse(w.dataset.szPinStops), M: JSON.parse(w.dataset.szPinMap), T: +w.dataset.szPinTotal, stageH: w.querySelector('.sz-pin__stage').offsetHeight, vh: innerHeight, pinTop: top }; });
 console.log('pinned', info.live, 'stage', info.stageH, 'screen', info.vh, 'top', info.pinTop, 'len', info.len);
 const y = (k) => info.top + info.S[k] * info.len / info.T;
 const st = () => p.evaluate(() => { const ai = document.querySelector('.szs--ai'), d = document.querySelector('.szs--direction'); return { tab: [...document.querySelectorAll('.sz-tabs__tab')].findIndex(x=>x.getAttribute('aria-selected')==='true'), piece: ai ? [...ai.querySelectorAll('.szs-ai__item')].findIndex(e=>e.classList.contains('is-focus')) : null, dir: d ? d.querySelector('.szs-dir__status').textContent.trim().slice(0, 40) : null, stageTop: Math.round(document.querySelector('.sz-pin[data-sz-pin="tabs"] .sz-pin__stage').getBoundingClientRect().top) }; });
 const rows = [];
 for (let k = 0; k < info.S.length; k++) { await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(k)); await p.waitForTimeout(3200); const s = await st(); rows.push(`stop ${k} [tab ${info.M[k][0]} ch ${info.M[k][1]}] → tab ${s.tab} piece ${s.piece} | ${s.dir} | stage@${s.stageTop}`); if (k === 3) await p.screenshot({ path: '../live-ai-ch.png' }); if (k === 8) await p.screenshot({ path: '../live-dir-ch.png' }); }
 console.log(rows.join('\n'));
 const back = []; for (let k = 10; k >= 0; k -= 2) { await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(k)); await p.waitForTimeout(3000); const s = await st(); back.push(`${k}:t${s.tab}/p${s.piece}`); } console.log('back up', back.join(' '));
 await p.evaluate(() => { window.__seq = []; const items = [...document.querySelectorAll('.szs--ai .szs-ai__item')]; new MutationObserver(() => { const k = items.findIndex(e => e.classList.contains('is-focus')); if (k >= 0 && window.__seq[window.__seq.length-1] !== k) window.__seq.push(k); }).observe(document.querySelector('.szs--ai'), { attributes: true, subtree: true, attributeFilter: ['class'] }); });
 await p.evaluate(v=>scrollTo({top:v,behavior:'instant'}), y(5)); await p.waitForTimeout(4000); console.log('fast 0→5 pieces focused on the way', JSON.stringify(await p.evaluate(()=>window.__seq)));
 console.log('analytics', JSON.stringify(await p.evaluate(() => (window.dataLayer||[]).filter(e => e && e.event === 'sz_service_chapter').map(e => e.chapter + '/' + e.chapter_count))));
 console.log('desktop errors', errs);
 // deep link
 const p2 = await ctx.newPage(); await p2.goto('https://samanzavarian.com/?t=' + Date.now() + '#ai-visual-production-4', { waitUntil: 'load', timeout: 90000 }); await p2.waitForTimeout(4000); await p2.mouse.move(600, 300); await p2.waitForTimeout(3500);
 console.log('deep link #ai-visual-production-4:', JSON.stringify(await p2.evaluate(() => ({ tab: [...document.querySelectorAll('.sz-tabs__tab')].findIndex(x=>x.getAttribute('aria-selected')==='true'), piece: [...document.querySelectorAll('.szs--ai .szs-ai__item')].findIndex(e=>e.classList.contains('is-focus')), say: (document.querySelector('.sz-tabs__say')||{}).textContent }))));
 // mobile: not pinned, scenes play by themselves
 const m = await br.newContext({ viewport:{width:390,height:844}, isMobile:true, hasTouch:true }); await route(m); const pm = await m.newPage(); const merr=[]; pm.on('pageerror',e=>merr.push(e.message));
 await pm.goto(URL0, { waitUntil: 'load', timeout: 90000 }); await pm.waitForTimeout(3000); await pm.touchscreen.tap(200, 500); await pm.locator('.szs--ai').scrollIntoViewIfNeeded(); 
 const seen = new Set(); for (let i = 0; i < 24; i++) { await pm.waitForTimeout(500); seen.add(await pm.evaluate(()=>[...document.querySelectorAll('.szs--ai .szs-ai__item')].findIndex(e=>e.classList.contains('is-focus')))); }
 console.log('mobile: pinned', await pm.evaluate(()=>document.querySelector('.sz-pin[data-sz-pin="tabs"]').classList.contains('is-live')), 'autoplay pieces seen', [...seen].join(','), 'doc width', await pm.evaluate(()=>document.documentElement.scrollWidth), 'errors', merr);
 await br.close(); })();
