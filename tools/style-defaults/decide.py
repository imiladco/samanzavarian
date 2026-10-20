import json, math, re, sys
C = json.load(open('controls.json')); M = json.load(open('measured.json'))
MOB = '(max-width: 767px)'
NUM = re.compile(r'^(-?[\d.]+)(px|em|rem|%|vw|vh|deg|s|ms)?$')

def hexc(v):
    m = re.match(r'rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)', v or '')
    if not m: return None
    r, g, b = (int(float(x)) for x in m.groups()[:3]); a = m.group(4)
    if a is not None and float(a) < 1:
        return 'rgba(%d,%d,%d,%s)' % (r, g, b, ('%.3f' % float(a)).rstrip('0').rstrip('.'))
    return '#%02X%02X%02X' % (r, g, b)

def slider(v, units, prop):
    if v is None or 'var(' in v or v in ('normal', 'auto', 'none'): return None
    m = NUM.match(v.strip())
    if m:
        size, unit = float(m.group(1)), m.group(2) or ''
        size = int(size) if size.is_integer() else size
        if unit == '' and prop == 'line-height':
            # unitless stays unitless (an em value is inherited as pixels by the children)
            return {'unit': 'custom', 'size': v.strip()} if 'custom' in units else None
        if unit in units: return {'unit': unit, 'size': size}
    if 'custom' in units: return {'unit': 'custom', 'size': v.strip()}
    return None

def mobile_ok(rec, ctrl):
    # every other place the property is set: only Elementor's phone breakpoint, on the same selector
    oth = rec.get('other') or []
    if not oth: return True, None
    if all(o['cond'] == MOB for o in oth) and len({o['sel'] for o in oth}) == 1 and ctrl.get('resp'):
        return True, oth[0]['v']
    return False, None

pend_tab = {}
out = {}; stats = {'set': 0, 'mobile': 0, 'skipped': 0}
for w, ctrls in C.items():
    for cn, c in ctrls.items():
        rec = M['desk'].get(w, {}).get(cn); prec = M['phone'].get(w, {}).get(cn)
        t = c['type']
        if not rec or t in ('font', 'box_shadow', 'text_shadow', 'media', 'switcher'): continue
        if rec.get('bad') or (prec and prec.get('bad')): stats['skipped'] += 1; continue
        d = None; mob = None
        if 'sides' in rec:
            if any(s['other'] for s in rec['sides']): stats['skipped'] += 1; continue
            parsed = [NUM.match(s['v'].strip()) if s['v'] and 'var(' not in s['v'] else None for s in rec['sides']]
            if not all(parsed): stats['skipped'] += 1; continue
            units = {p.group(2) or 'px' for p in parsed}
            if len(units) != 1 or list(units)[0] not in c['units']: stats['skipped'] += 1; continue
            vals = [p.group(1) for p in parsed]
            d = {'top': vals[0], 'right': vals[1], 'bottom': vals[2], 'left': vals[3], 'unit': list(units)[0], 'isLinked': len(set(vals)) == 1}
        else:
            ok, mv = mobile_ok(rec, c)
            if not ok: stats['skipped'] += 1; continue
            a, comp, prop = rec.get('authored'), rec.get('computed'), rec['prop']
            if t == 'color':
                d = hexc(comp) if a else None
                if d and mv is not None and prec: mob = hexc(prec.get('computed'))
            elif t == 'slider' and prop == 'line-height' and (any((x or {}).get('inhRisk') for x in (rec, prec, M['tab'].get(w, {}).get(cn)))):
                d = None
            elif t == 'slider':
                # sizes in plain pixels, per device: the design's value at each width
                src = a if a and 'var(' not in a else None
                if src is None: d = None
                else:
                    def px(r):
                        m = NUM.match((r or {}).get('computed', '').strip()) if r else None
                        if not m or (m.group(2) or 'px') != 'px' or 'px' not in c['units']: return None
                        v = float(m.group(1))
                        # a line height on the browser's 1/64px layout grid, as a unitless one lands (18.2 → 18.1875)
                        if prop == 'line-height':
                            # as the browser lands a unitless line height: the font size snapped to its 1/64px grid, times the number, snapped again
                            au = ((r or {}).get('authored') or '').strip(); fsv = (r or {}).get('fs') or ''
                            if re.match(r'^[\d.]+$', au) and fsv.endswith('px'):
                                v = math.floor(math.floor(float(fsv[:-2]) * 64 + 1e-6) / 64 * float(au) * 64 + 1e-6) / 64
                            else:
                                v = math.floor(v * 64 + 1e-6) / 64
                        else:
                            v = round(v, 3)
                        return {'unit': 'px', 'size': int(v) if v.is_integer() else v}
                    trec = M['tab'].get(w, {}).get(cn)
                    d = px(rec)
                    if d and c.get('resp'):
                        tv, pv = px(trec), px(prec)
                        if tv and tv != d: out.setdefault(w, {}); tab_v = tv
                        else: tab_v = None
                        mob = pv if pv and pv != (tv or d) else None
                        if tab_v: pend_tab[(w, cn)] = tab_v
            elif t == 'slider':
                # only a value the element sets itself: an inherited one would freeze what should follow its parent
                src = a if a and 'var(' not in a else None
                d = slider(src, c['units'], prop)
                if d and mv is not None: mob = slider(mv, c['units'], prop)
            elif t in ('select', 'choose'):
                if not a or 'var(' in a: stats['skipped'] += 1; continue
                v = (comp or '').split(' ')[0]
                if prop == 'font-weight' and v == '400': v = '400'
                d = v if v in c['opts'] and v != '' else None
        if d is None:
            stats['skipped'] += 1
            comp = rec.get('computed') if 'sides' not in rec else None
            if t == 'slider' and not rec.get('bad') and comp:
                m = NUM.match(comp.strip())
                if m and (m.group(2) or 'px') in c['units']:
                    sz = round(float(m.group(1)), 2); sz = int(sz) if float(sz).is_integer() else sz
                    out.setdefault(w, {})[cn] = {'s': c['section'], 'p': {'size': sz, 'unit': m.group(2) or 'px'}}
                    stats['placeholder'] = stats.get('placeholder', 0) + 1
            continue
        out.setdefault(w, {})[cn] = {'s': c['section'], 'd': d}
        if (w, cn) in pend_tab: out[w][cn]['t'] = pend_tab[(w, cn)]
        if mob is not None and mob != d: out[w][cn]['m'] = mob; stats['mobile'] += 1
        stats['set'] += 1
        # gates: the typography popover, the background type
        if c['group'] == 'typography' and c['prefix']:
            out[w][c['prefix'] + 'typography'] = {'s': c['section'], 'd': 'custom'}
        mb = re.match(r'(.*_bg(?:_h)?)_color$', cn)
        if mb and t == 'color':
            out[w][mb.group(1) + '_background'] = {'s': c['section'], 'd': 'classic'}
# By hand, where the tool rightly stops: a state that has its own control can be filled
# together with that control (each state then keeps its own, more specific rule), and the
# bar's background, whose scrolled and open states win with an id-weighted rule.
MANUAL = {
    'sz-nav': {
        'st_link_color': '#525A65', 'st_link_color_h': '#111318', 'st_here_c': '#111318',
        'st_bar_bg_background': 'classic', 'st_bar_bg_color': 'rgba(255,255,255,0.72)',
        'st_sheet_radius': {'d': None, 'm': {'top': '24', 'right': '24', 'bottom': '24', 'left': '24', 'unit': 'px', 'isLinked': True}},
        'st_sheet_pad': {'d': None, 'm': {'top': '8', 'right': '8', 'bottom': '8', 'left': '8', 'unit': 'px', 'isLinked': True}},
        'st_cta_bg_background': 'classic', 'st_cta_bg_color': '#0D1B2A', 'st_cta_c': '#FFFFFF',
        'st_cta_radius': {'top': '99', 'right': '99', 'bottom': '99', 'left': '99', 'unit': 'px', 'isLinked': True},
    },
}
for w, items in MANUAL.items():
    for cn, v in items.items():
        sec = C.get(w, {}).get(cn, {}).get('section')
        if not sec:
            # group gates (…_background) are not in the dump: take the section of their colour
            sec = next((c['section'] for k, c in C.get(w, {}).items() if k.startswith(cn.rsplit('_', 1)[0])), None)
        if not sec: print('no section for', w, cn, file=sys.stderr); continue
        if isinstance(v, dict) and 'm' in v:
            out.setdefault(w, {})[cn] = {'s': sec, 'd': v['d'] if v['d'] is not None else {'unit': 'px', 'isLinked': True}, 'm': v['m']}
        else:
            out.setdefault(w, {})[cn] = {'s': sec, 'd': v}
        stats['manual'] = stats.get('manual', 0) + 1
print(stats, file=sys.stderr)

def php(v, ind='\t\t'):
    if isinstance(v, bool): return 'true' if v else 'false'
    if isinstance(v, (int, float)): return repr(v)
    if isinstance(v, str): return "'" + v.replace('\\', '\\\\').replace("'", "\\'") + "'"
    if isinstance(v, dict): return '[ ' + ', '.join(php(k) + ' => ' + php(x) for k, x in v.items()) + ' ]'
lines = ['<?php', '/**', ' * The design\'s own values, as the defaults of the Style tab (generated by', ' * tools/build-style-defaults; do not edit by hand). Only properties that', ' * hold one value are here: anything that changes with a state (hover,', ' * picked) or at a breakpoint other than Elementor\'s phone stays empty, so', ' * a default can never override the design. See Style_Defaults.', ' */', '', "defined( 'ABSPATH' ) || exit;", '', 'return [']
for w in sorted(out):
    lines.append("\t'%s' => [" % w)
    for cn, x in out[w].items():
        lines.append('\t\t' + php(cn) + ' => ' + php(x) + ',')
    lines.append('\t],')
lines.append('];')
open('/home/user/samanzavarian/includes/style-defaults.php', 'w').write('\n'.join(lines) + '\n')
