const { chromium } = require('playwright');
const ID = require('fs').readFileSync(__dirname + '/heropage.txt', 'utf8').trim();
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1500, height: 950 } });
  await p.goto('http://127.0.0.1:8899/wp-login.php'); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'admin'); await p.click('#wp-submit'); await p.waitForLoadState('networkidle');
  await p.goto(`http://127.0.0.1:8899/wp-admin/post.php?post=${ID}&action=elementor`); await p.waitForSelector('#elementor-preview-iframe', { timeout: 240000, state: 'attached' }); await p.waitForTimeout(5000);
  const d = await p.evaluate(() => {
    const out = {};
    Object.entries(elementor.widgetsCache).forEach(([name, w]) => {
      if (!/^sz-/.test(name) || !w.controls) return;
      Object.entries(w.controls).forEach(([cn, c]) => {
        if (!c.selectors || c.tab !== 'style' || /_(tablet|mobile|widescreen|laptop|tablet_extra|mobile_extra)$/.test(cn)) return;
        (out[name] = out[name] || {})[cn] = { type: c.type, sel: c.selectors, units: c.size_units || [], opts: c.options ? Object.keys(c.options) : [], group: c.groupType || '', prefix: c.groupPrefix || '', resp: !!w.controls[cn + '_mobile'], def: c.default, section: c.section };
      });
    });
    return out;
  });
  require('fs').writeFileSync(__dirname + '/controls.json', JSON.stringify(d, null, 1));
  console.log(Object.keys(d).length, 'widgets', Object.values(d).reduce((a, w) => a + Object.keys(w).length, 0), 'controls');
  await b.close();
})();
