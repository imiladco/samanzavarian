// computed styles of every element inside every widget, desktop and phone → snap-<tag>.json
const { chromium } = require('playwright');
const ID = require('fs').readFileSync(__dirname + '/allpage.txt', 'utf8').trim();
const tag = process.argv[2] || 'before';
const PROPS = ['color','background-color','background-image','font-size','font-weight','line-height','letter-spacing','text-transform','padding-top','padding-right','padding-bottom','padding-left','margin-top','margin-bottom','border-top-left-radius','border-top-width','box-shadow','width','height','gap','justify-content','text-align','opacity','display'];
(async () => {
  const b = await chromium.launch(); const out = {};
  for (const [w, n] of [[1440, 'desk'], [1024, 'tab'], [390, 'phone']]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 } });
    await p.emulateMedia({ reducedMotion: 'reduce' });
    await p.goto('http://127.0.0.1:8899/?page_id=' + ID, { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
    out[n] = await p.evaluate((PROPS) => {
      const r = {};
      document.querySelectorAll('[data-widget_type]').forEach((wd) => {
        const name = wd.dataset.widget_type;
        [wd, ...wd.querySelectorAll('*')].forEach((el, i) => {
          if (el.closest('svg') && el.tagName !== 'svg') return;
          const cs = getComputedStyle(el); r[name + '#' + i + ':' + el.className.toString().slice(0, 40)] = PROPS.map(pp => cs.getPropertyValue(pp)).join('|');
        });
      });
      return r;
    }, PROPS);
    await p.close();
  }
  require('fs').writeFileSync(__dirname + '/snap-' + tag + '.json', JSON.stringify(out));
  console.log(tag, Object.values(out).map(o => Object.keys(o).length).join('/'), 'elements');
  await b.close();
})();
