const { chromium } = require('playwright'); const { execSync } = require('child_process');
const ID = require('fs').readFileSync(__dirname + '/allpage.txt', 'utf8').trim();
const W = (c) => execSync(`cd ../site && php ../wp-cli.phar --allow-root eval '${c} delete_post_meta_by_key("_elementor_element_cache"); \\Elementor\\Plugin::$instance->files_manager->clear_cache();'`, { stdio: 'ignore' });
(async () => {
  const b = await chromium.launch(); const shots = {};
  for (const mode of ['off', 'on']) {
    W(mode === 'off' ? 'update_option("szw_rig_no_defaults",1);' : 'delete_option("szw_rig_no_defaults");');
    for (const [w, n] of [[1440, 'desk'], [390, 'phone']]) {
      const p = await b.newPage({ viewport: { width: w, height: 900 } }); await p.emulateMedia({ reducedMotion: 'reduce' });
      await p.goto('http://127.0.0.1:8899/?page_id=' + ID, { waitUntil: 'networkidle' }); await p.waitForTimeout(1500);
      shots[mode + n] = await p.screenshot({ fullPage: true, animations: 'disabled' });
      await p.close();
    }
  }
  // compare in a canvas: count differing pixels
  const p = await b.newPage();
  for (const n of ['desk', 'phone']) {
    const r = await p.evaluate(async ([a, c]) => {
      const load = (s) => new Promise(res => { const i = new Image(); i.onload = () => res(i); i.src = 'data:image/png;base64,' + s; });
      const [ia, ib] = await Promise.all([load(a), load(c)]);
      if (ia.width !== ib.width || ia.height !== ib.height) return 'size ' + ia.width + 'x' + ia.height + ' vs ' + ib.width + 'x' + ib.height;
      const cv = (i) => { const k = document.createElement('canvas'); k.width = i.width; k.height = i.height; const x = k.getContext('2d'); x.drawImage(i, 0, 0); return x.getImageData(0, 0, i.width, i.height).data; };
      const da = cv(ia), db = cv(ib); let d = 0, first = -1;
      for (let j = 0; j < da.length; j += 4) if (Math.abs(da[j] - db[j]) + Math.abs(da[j + 1] - db[j + 1]) + Math.abs(da[j + 2] - db[j + 2]) > 24) { d++; if (first < 0) first = j / 4; }
      return d + ' differing pixels' + (first >= 0 ? ' (first at y=' + Math.floor(first / ia.width) + ')' : '');
    }, [shots['off' + n].toString('base64'), shots['on' + n].toString('base64')]);
    console.log(n, r);
  }
  await b.close();
})();
