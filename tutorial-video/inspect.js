// Read-only look at one admin screen: which editor it uses, which panels and
// fields it has, and a screenshot. All writes are blocked by the lockdown.
// Usage: node inspect.js [admin-path]   (default /wp-admin/post-new.php)
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { config, paths, ROOT } = require('./lib/scenario');
const { lockdown, disableEditorAutosave } = require('./lib/safety');

(async () => {
  const target = process.argv[2] || '/wp-admin/post-new.php';
  const site = config().site.replace(/\/$/, '');
  const out = path.join(ROOT, 'output', 'inspect');
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch();
  const context = await browser.newContext({
    storageState: paths('').auth, viewport: { width: 1920, height: 1080 }, locale: 'fa-IR',
  });
  const blocked = await lockdown(context);
  const page = await context.newPage();
  await page.goto(site + target, { waitUntil: 'networkidle', timeout: 60000 });
  if (/wp-login\.php/.test(page.url())) throw new Error('Session expired: run node login.js again.');
  await disableEditorAutosave(page).catch(() => {});
  await page.waitForTimeout(2500);
  await page.screenshot({ path: path.join(out, 'before.png') });

  const report = await page.evaluate(() => {
    const txt = (el) => (el ? el.textContent.replace(/\s+/g, ' ').trim() : null);
    const all = (sel) => [...document.querySelectorAll(sel)];
    const vis = (el) => !!(el && el.getClientRects().length);
    return {
      wp: (document.querySelector('meta[name=generator]') || {}).content || null,
      title: document.title,
      dir: document.documentElement.dir,
      blockEditor: !!document.querySelector('.block-editor, .edit-post-layout, .editor-editor-interface'),
      canvasIframe: !!document.querySelector('iframe[name="editor-canvas"]'),
      classicEditor: !!document.querySelector('#postdivrich, #wp-content-wrap'),
      elementorButton: txt(document.querySelector('#elementor-switch-mode, #elementor-editor-button, .elementor-switch-mode-button, [id*="elementor"] button')),
      welcomeGuide: vis(document.querySelector('.edit-post-welcome-guide, .editor-welcome-guide')),
      headerButtons: all('.edit-post-header button, .editor-header button, .interface-interface-skeleton__header button')
        .filter(vis).map((b) => ({ label: b.getAttribute('aria-label') || txt(b), cls: b.className.split(' ').slice(0, 3).join(' ') })),
      sidebarPanels: all('.components-panel__body-title, .editor-sidebar .components-panel__body > h2')
        .filter(vis).map(txt),
      metaBoxes: all('.postbox, .edit-post-meta-boxes-area .postbox').map((b) => ({ id: b.id, title: txt(b.querySelector('h2, .hndle')) })),
      inputs: all('input:not([type=hidden]), textarea, select, [contenteditable=true]').filter(vis).slice(0, 60)
        .map((e) => ({ tag: e.tagName.toLowerCase(), type: e.type || null, id: e.id || null, name: e.name || null,
          aria: e.getAttribute('aria-label'), placeholder: e.getAttribute('placeholder') })),
    };
  });
  // The title/body live inside the canvas iframe on recent WordPress.
  const canvas = page.frame({ name: 'editor-canvas' });
  if (canvas) {
    report.canvas = await canvas.evaluate(() => ({
      title: !!document.querySelector('.editor-post-title, h1.wp-block-post-title'),
      titlePlaceholder: (document.querySelector('.editor-post-title') || {}).getAttribute?.('aria-label') || null,
      appender: [...document.querySelectorAll('.block-editor-default-block-appender__content, [data-empty="true"]')]
        .map((e) => e.getAttribute('aria-label') || e.textContent.trim()).slice(0, 3),
    }));
  }
  report.blockedWrites = blocked;
  fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((e) => { console.error(e.message); process.exit(1); });
