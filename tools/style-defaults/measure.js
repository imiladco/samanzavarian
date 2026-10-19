// Reads the design's own value for every style control, from the plugin's stylesheets.
// Safe only: a property that changes with a state (hover, picked…) or at a breakpoint other than
// Elementor's mobile is left empty, since a default there would override the design.
const { chromium } = require('playwright');
const ID = require('fs').readFileSync(__dirname + '/allpage.txt', 'utf8').trim();
const CTRLS = JSON.parse(require('fs').readFileSync(__dirname + '/controls.json', 'utf8'));

function inPage(CTRLS) {
  const sheets = [...document.styleSheets].filter(s => s.href && /samanzavarian/.test(s.href));
  const rules = [];
  const walk = (list, cond) => [...list].forEach(r => {
    if (r.cssRules && (r.media || r.conditionText !== undefined) && !r.selectorText) walk(r.cssRules, (cond ? cond + ' && ' : '') + (r.media ? r.media.mediaText : r.conditionText) + (r.constructor.name === 'CSSContainerRule' ? ' [container]' : ''));
    else if (r.selectorText) rules.push({ r, cond: cond || '' });
  });
  sheets.forEach(s => { try { walk(s.cssRules, ''); } catch (e) {} });
  const STATE = /:(hover|focus|focus-visible|focus-within|active|checked|visited|disabled|placeholder-shown|not\([^()]*\))|\.(is|has)-[\w-]+|\[aria-[^\]]+\]|\[hidden\]|\[open\]|\[style[^\]]*\]/g;
  const spec = (sel) => { const s = sel.replace(/:(is|where|not|has)\(/g, '('); return (s.match(/#[\w-]+/g) || []).length * 100 + (s.match(/\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g) || []).length * 10 + (s.match(/(^|[\s>+~(])[a-z][\w-]*/gi) || []).length; };
  const parts = (sel) => { const out = []; let d = 0, cur = ''; for (const ch of sel) { if (ch === '(') d++; if (ch === ')') d--; if (ch === ',' && !d) { out.push(cur.trim()); cur = ''; } else cur += ch; } out.push(cur.trim()); return out; };
  const matches = (el, sel) => { try { return el.matches(sel); } catch (e) { return false; } };
  // every rule that sets prop on el: now, or in another state / at another width
  const SHORT = (p) => p.startsWith('background') ? 'background' : p.startsWith('padding') ? 'padding' : p.startsWith('margin') ? 'margin' : /radius/.test(p) ? 'border-radius' : p.startsWith('font') || p === 'line-height' ? 'font' : p.startsWith('border') ? 'border' : p === 'gap' ? 'gap' : /^(row|column)-gap$/.test(p) ? 'gap' : '';
  const find = (el, prop) => {
    const now = [], other = [];
    rules.forEach(({ r, cond }) => {
      // a shorthand written with var() leaves its longhands blank: count it as setting the property, value unknown
      const sh = SHORT(prop);
      const v = r.style.getPropertyValue(prop) || (sh && sh !== prop && new RegExp('(^|;)\\s*' + sh + '\\s*:').test(r.style.cssText) ? 'var(?)' : '');
      if (!v) return;
      parts(r.selectorText).forEach(sel => {
        const cm = cond === '' || (!/\[container\]/.test(cond) && matchMedia(cond).matches);
        if (cm && matches(el, sel)) now.push({ sel, v, imp: r.style.getPropertyPriority(prop), sp: spec(sel), cond });
        else if (matches(el, sel.replace(STATE, '')) || (cond && matches(el, sel))) other.push({ sel, v, cond });
      });
    });
    now.sort((a, b) => (a.imp ? 1 : 0) - (b.imp ? 1 : 0) || a.sp - b.sp);
    return { win: now[now.length - 1], now, other };
  };
  const out = {};
  for (const [wname, ctrls] of Object.entries(CTRLS)) {
    const wd = document.querySelector(`.elementor-widget-${wname}`);
    if (!wd) continue;
    const id = '.elementor-element-' + wd.dataset.id;
    for (const [cn, c] of Object.entries(ctrls)) {
      for (const [selT, valT] of Object.entries(c.sel)) {
        if (/:hover|is-hot|:checked|:focus/.test(selT)) continue;
        const sels = parts(selT.replace(/\{\{WRAPPER\}\}/g, id));
        let els = [];
        // every part of the selector list: a control on "input, textarea" must suit both
        for (const s of sels) { try { els.push(...document.querySelectorAll(s)); } catch (e) {} }
        if (!els.length) continue;
        const decls = valT.split(';').map(x => x.trim()).filter(Boolean).map(d => /^([\w-]+)\s*:\s*(.+)$/.exec(d)).filter(Boolean);
        if (!decls.length || decls[0][1].startsWith('--')) continue;
        const prop = decls[0][1], tpl = decls[0][2];
        const rec = { prop, tpl, type: c.type };
        const isDims = /\{\{TOP\}\}/.test(tpl);
        const sides = !isDims ? null : prop === 'border-radius' ? ['border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius'] : prop === 'margin' || prop === 'padding' ? ['top', 'right', 'bottom', 'left'].map(s => prop + '-' + s) : null;
        if (isDims && !sides) continue;
        // every element the selector reaches must agree, and each must have its own rule (not inherit)
        const seen = new Set(); let bad = '';
        const per = els.map(el => {
          const cs = getComputedStyle(el);
          if (isDims) {
            const f = sides.map(sp => find(el, sp));
            const r = f.map((x, i) => ({ v: x.win ? x.win.v : null, comp: cs.getPropertyValue(sides[i]), other: x.other.map(o => o.cond || o.sel) }));
            seen.add(JSON.stringify(r.map(x => x.v)));
            return { sides: r };
          }
          const f = find(el, prop);
          // the other properties a control writes with the same value must hold that value too (font-size on an svg does not matter)
          const extra = decls.slice(1).filter(d => !(d[1] === 'font-size' && el.tagName.toLowerCase() === 'svg')).map(d => cs.getPropertyValue(d[1]));
          if (extra.some(v => v !== cs.getPropertyValue(prop))) bad = 'multi';
          const r = { authored: f.win ? f.win.v : null, computed: cs.getPropertyValue(prop), other: f.other.map(o => ({ cond: o.cond, sel: o.sel, v: o.v })) };
          seen.add(c.type === 'color' ? r.computed : (r.authored || '') + '|' + r.computed);
          return r;
        });
        if (seen.size > 1) bad = bad || 'differ';
        Object.assign(rec, per[0]);
        if (!isDims) rec.other = [].concat(...per.map(x => x.other));
        else rec.sides = rec.sides.map((s, i) => ({ ...s, other: [].concat(...per.map(x => x.sides[i].other)) }));
        if (bad) rec.bad = bad;
        (out[wname] = out[wname] || {})[cn] = rec;
        break;
      }
    }
  }
  return out;
}

(async () => {
  const b = await chromium.launch(); const res = {};
  for (const [w, n] of [[1440, 'desk'], [1024, 'tab'], [390, 'phone']]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 } });
    await p.emulateMedia({ reducedMotion: 'reduce' });
    await p.goto('http://127.0.0.1:8899/?page_id=' + ID, { waitUntil: 'networkidle' }); await p.waitForTimeout(1200);
    res[n] = await p.evaluate(inPage, CTRLS);
    await p.close();
  }
  require('fs').writeFileSync(__dirname + '/measured.json', JSON.stringify(res, null, 1));
  console.log('measured', Object.values(res.desk).reduce((a, w) => a + Object.keys(w).length, 0), 'controls');
  await b.close();
})();
