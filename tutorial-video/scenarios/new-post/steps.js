// Page actions for scenario.md, one per step id. Nothing here saves,
// publishes or uploads: Publish and "Set featured image" are only pointed at,
// and the lockdown in lib/safety.js blocks every write request anyway.
// Labels match both a Persian and an English admin.
const { disableEditorAutosave } = require('../../lib/safety');

const any = (...xs) => new RegExp(`^\\s*(${xs.join('|')})\\s*$`, 'i');
const L = {
  posts: any('نوشته‌ها', 'نوشته ها', 'Posts'),
  addPost: any('افزودن نوشته', 'افزودن نوشته جدید', 'نوشته تازه', 'Add New Post', 'Add Post', 'Add New'),
  settings: any('تنظیمات', 'Settings'),
  postTab: any('نوشته', 'Post'),
  categories: any('دسته‌ها', 'دسته ها', 'دسته‌بندی‌ها', 'Categories'),
  featured: any('تصویر شاخص', 'Featured image'),
  setFeatured: any('تعیین تصویر شاخص', 'Set featured image'),
  preview: any('پیش‌نمایش', 'پیش نمایش', 'View', 'Preview'),
  publish: any('انتشار', 'Publish'),
  inserter: /افزودن بلوک|Block Inserter|Toggle block inserter|Add block/i,
};

const SAMPLE_TITLE = 'پشت صحنه‌ی پروژه‌ی جدید';
const SAMPLE_BODY = 'در این مطلب از روند شکل‌گیری پروژه‌ی تازه‌مان می‌گوییم.';

const canvas = (page) => page.frameLocator('iframe[name="editor-canvas"]');
async function inEditor(page, sel) {
  const framed = canvas(page).locator(sel).first();
  if (await page.locator('iframe[name="editor-canvas"]').count()) return framed;
  return page.locator(sel).first();
}

async function closeEditorPopups(page) {
  await disableEditorAutosave(page).catch(() => {});
  const guide = page.locator('.edit-post-welcome-guide, .editor-welcome-guide');
  if (await guide.isVisible().catch(() => false)) await page.keyboard.press('Escape');
  // Also stop the guide from opening, in this browser only.
  await page.evaluate(() => {
    const p = window.wp && wp.data && wp.data.dispatch('core/preferences');
    if (p) { p.set('core/edit-post', 'welcomeGuide', false); p.set('core', 'welcomeGuide', false); }
  }).catch(() => {});
}

async function ensurePanelOpen(page, label) {
  const btn = page.getByRole('button', { name: label }).first();
  if ((await btn.getAttribute('aria-expanded')) === 'false') await btn.click();
  return btn;
}

module.exports = {
  // Runs before recording starts.
  async prepare(page) {
    await page.locator('#adminmenu').waitFor();
  },

  steps: {
    'step-01': async (d, page) => {
      await d.wait(600);
      await d.point(page.locator('#menu-posts > a'), { ms: 1600 });
      await d.unhighlight();
    },

    'step-02': async (d, page) => {
      await d.point(page.locator('#menu-posts > a'), { highlight: false });
      await d.wait(500);
      const add = page.locator('#menu-posts .wp-submenu a').filter({ hasText: L.addPost }).first();
      await d.click(add);
      await page.waitForURL(/post-new\.php/, { timeout: 30000 });
      await page.waitForLoadState('networkidle').catch(() => {});
      await closeEditorPopups(page);
      await d.restoreCursor();
    },

    'step-03': async (d, page) => {
      const title = await inEditor(page, '.editor-post-title, h1.wp-block-post-title, #title');
      await d.click(title);
      await d.wait(300);
      await d.type(SAMPLE_TITLE);
    },

    'step-04': async (d, page) => {
      await page.keyboard.press('Enter');       // title → first paragraph block
      await d.wait(400);
      const para = await inEditor(page, '.wp-block-paragraph[contenteditable="true"], p.block-editor-rich-text__editable');
      await d.point(para, { ms: 700 });
      await d.unhighlight();
      await d.type(SAMPLE_BODY);
      await d.wait(500);
      await d.point(page.getByRole('button', { name: L.inserter }).first(), { ms: 1400 });
      await d.unhighlight();
    },

    'step-05': async (d, page) => {
      const sidebar = page.locator('.interface-complementary-area, .editor-sidebar');
      if (!(await sidebar.first().isVisible().catch(() => false))) {
        await d.click(page.getByRole('button', { name: L.settings }).first());
      }
      const tab = page.getByRole('tab', { name: L.postTab }).first();
      if (await tab.isVisible().catch(() => false) && (await tab.getAttribute('aria-selected')) !== 'true') await d.click(tab);
      const panel = page.getByRole('button', { name: L.categories }).first();
      if ((await panel.getAttribute('aria-expanded')) === 'false') await d.click(panel);
      else await d.point(panel, { ms: 800 });
      await d.unhighlight();
      const box = page.locator('.editor-post-taxonomies__hierarchical-terms-list input[type=checkbox]').first();
      if (!(await box.isChecked())) await d.click(box);   // local only: nothing is saved
      else await d.point(box, { ms: 900 });
      await d.unhighlight();
    },

    'step-06': async (d, page) => {
      const panel = await ensurePanelOpen(page, L.featured);
      await d.point(panel, { highlight: false });
      const btn = page.getByRole('button', { name: L.setFeatured }).first();
      await d.point(btn, { ms: 2600 });                 // highlight only, no click
      await d.unhighlight();
    },

    'step-07': async (d, page) => {
      const header = page.locator('.editor-header, .edit-post-header');
      const preview = header.getByRole('button', { name: L.preview }).first();
      if (await preview.isVisible().catch(() => false)) {
        await d.point(preview, { ms: 1800 });
        await d.unhighlight();
      }
      await d.point(header.getByRole('button', { name: L.publish }).first(), { ms: 2600 });  // never clicked
      await d.unhighlight();
    },

    'step-08': async (d) => {
      await d.cursorTo(960, 560, 900);
    },
  },
};
