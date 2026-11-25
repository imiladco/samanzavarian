// Records one scenario at 1920x1080 with the read-only lockdown on.
// Needs audio/<scenario>/durations.json (run tts.py first).
// Usage: node record.js <scenario>
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { loadScenario, loadJSON, config, paths } = require('./lib/scenario');
const { lockdown } = require('./lib/safety');
const overlayScript = require('./lib/overlay');
const { Director } = require('./lib/director');

(async () => {
  const name = process.argv[2];
  if (!name) throw new Error('usage: node record.js <scenario>');
  const cfg = config();
  const sc = loadScenario(name);
  const p = paths(name);
  const actions = require(path.join(sc.dir, 'steps.js'));
  const durations = loadJSON(path.join(p.audio, 'durations.json'));
  for (const s of sc.steps) {
    if (!actions.steps[s.id]) throw new Error(`steps.js has no action for ${s.id}`);
    if (durations[s.id] == null) throw new Error(`no audio for ${s.id}: run python3 tts.py ${name}`);
  }
  fs.mkdirSync(p.out, { recursive: true });

  const url = sc.meta.url === "file://SELF" ? "file://" + path.join(sc.dir, "page.html")
    : /^(https?|file):/.test(sc.meta.url) ? sc.meta.url : cfg.site.replace(/\/$/, '') + sc.meta.url;
  const browser = await chromium.launch();
  const context = await browser.newContext({
    storageState: sc.meta.auth === 'none' ? undefined : p.auth,
    viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1, locale: 'fa-IR',
  });
  const blocked = await lockdown(context);
  await context.addInitScript(overlayScript({ color: cfg.color }));
  const page = await context.newPage();
  const d = new Director(page, { typing: cfg.typingDelay });

  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    if (/wp-login\.php/.test(page.url())) throw new Error('Session expired: run node login.js again.');
    if (actions.prepare) await actions.prepare(page, d);   // not recorded
    await d.restoreCursor();
    await page.waitForTimeout(500);

    const raw = path.join(p.out, 'raw.webm');
    await page.screencast.start({ path: raw, size: { width: 1920, height: 1080 } });
    d.t0 = Date.now();
    await d.wait(cfg.leadIn * 1000);
    for (const s of sc.steps) {
      process.stdout.write(`${s.id} (${durations[s.id].toFixed(1)}s) … `);
      await d.step(s.id, durations[s.id] + cfg.stepGap, () => actions.steps[s.id](d, page));
      const t = d.timeline.at(-1);
      console.log(`${t.start.toFixed(2)} → ${t.end.toFixed(2)}`);
    }
    await d.wait(cfg.tail * 1000);
    const total = d.now();
    await page.screencast.stop();
    fs.writeFileSync(path.join(p.out, 'timeline.json'), JSON.stringify({ total, steps: d.timeline }, null, 2));
    console.log(`recorded ${total.toFixed(1)}s; writes blocked in browser: ${blocked.length}`);
    if (blocked.length) console.log('  ' + [...new Set(blocked)].join('\n  '));
  } finally {
    await context.close();   // closes without running "leave page" prompts
    await browser.close();
  }
})().catch((e) => { console.error(e.message); process.exit(1); });
