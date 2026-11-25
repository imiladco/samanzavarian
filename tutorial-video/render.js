// Builds the final video: branded intro + recording + outro, narration placed
// at each step's recorded start time, loudness-normalized, plus subtitles.
// Usage: node render.js <scenario>
// Output: output/<scenario>/<scenario>.mp4, .srt and -subtitled.mp4
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { ROOT, loadScenario, loadJSON, config, paths } = require('./lib/scenario');

const FONT_DIR = path.join(ROOT, 'node_modules', 'vazirmatn', 'fonts', 'ttf');
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

async function card(file, body, seconds, cfg) {
  const tpl = fs.readFileSync(path.join(ROOT, 'branding', 'card.html'), 'utf8');
  const html = tpl.replace('{{FONT}}', 'file://' + path.join(FONT_DIR, '..', 'variable', 'Vazirmatn[wght].ttf'))
    .replace('{{COLOR}}', cfg.color).replace('{{BODY}}', body);
  const htmlFile = file.replace(/\.webm$/, '.html');
  fs.writeFileSync(htmlFile, html);
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
  await page.goto('file://' + htmlFile);
  await page.evaluate(() => document.fonts.ready);
  await page.screencast.start({ path: file, size: { width: 1920, height: 1080 } });
  await page.evaluate(() => document.body.classList.add('go'));
  await page.waitForTimeout(seconds * 1000 + 300);
  await page.screencast.stop();
  await browser.close();
}

function logoTag(cfg) {
  const f = cfg.logo && path.join(ROOT, cfg.logo);
  if (!f || !fs.existsSync(f)) return `<div class="brand">${esc(cfg.brandName)}</div>`;
  const mime = f.endsWith('.svg') ? 'image/svg+xml' : f.endsWith('.png') ? 'image/png' : 'image/jpeg';
  return `<img class="logo" src="data:${mime};base64,${fs.readFileSync(f).toString('base64')}">`;
}

const srtTime = (t) => {
  const ms = Math.max(0, Math.round(t * 1000));
  const h = String(Math.floor(ms / 3600000)).padStart(2, '0');
  const m = String(Math.floor(ms / 60000) % 60).padStart(2, '0');
  const s = String(Math.floor(ms / 1000) % 60).padStart(2, '0');
  return `${h}:${m}:${s},${String(ms % 1000).padStart(3, '0')}`;
};

(async () => {
  const name = process.argv[2];
  if (!name) throw new Error('usage: node render.js <scenario>');
  const cfg = config();
  const sc = loadScenario(name);
  const p = paths(name);
  const tl = loadJSON(path.join(p.out, 'timeline.json'));
  const durations = loadJSON(path.join(p.audio, 'durations.json'));
  const intro = path.join(p.out, 'intro.webm');
  const outro = path.join(p.out, 'outro.webm');

  await card(intro, `${logoTag(cfg)}<div class="bar"></div><h1>${esc(sc.meta.title)}</h1>` +
    `<p>${esc(sc.meta.subtitle || cfg.tagline)}</p>`, cfg.introSeconds, cfg);
  await card(outro, `${logoTag(cfg)}<div class="bar"></div><p>${esc(cfg.outroText)}</p>` +
    cfg.contact.map((c) => `<div class="contact">${esc(c)}</div>`).join(''), cfg.outroSeconds, cfg);

  const I = cfg.introSeconds, M = tl.total, O = cfg.outroSeconds, total = I + M + O;
  const steps = tl.steps.map((t) => ({ ...t, audio: path.join(p.audio, `${t.id}.mp3`) }));
  for (const s of steps) {
    if (s.end - s.start + 0.01 < durations[s.id]) throw new Error(`${s.id} is shorter than its narration`);
  }

  // Video: three segments at constant 30 fps; audio: each clip delayed to its step start.
  const inputs = ['-i', intro, '-i', path.join(p.out, 'raw.webm'), '-i', outro];
  steps.forEach((s) => inputs.push('-i', s.audio));
  const norm = (i, dur) => `[${i}:v]trim=duration=${dur.toFixed(3)},setpts=PTS-STARTPTS,fps=30,` +
    `scale=1920:1080:flags=lanczos,setsar=1,format=yuv420p,tpad=stop_mode=clone:stop_duration=1,trim=duration=${dur.toFixed(3)}[v${i}]`;
  const graph = [norm(0, I), norm(1, M), norm(2, O), '[v0][v1][v2]concat=n=3:v=1:a=0[v]'];
  const delayed = steps.map((s, k) => {
    const ms = Math.round((I + s.start) * 1000);
    graph.push(`[${k + 3}:a]aresample=48000,adelay=${ms}:all=1[a${k}]`);
    return `[a${k}]`;
  });
  graph.push(`${delayed.join('')}amix=inputs=${steps.length}:normalize=0:dropout_transition=0,` +
    `loudnorm=I=-16:TP=-1.5:LRA=11,aresample=48000,apad,atrim=duration=${total.toFixed(3)}[a]`);

  const mp4 = path.join(p.out, `${name}.mp4`);
  const venc = ['-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high', '-pix_fmt', 'yuv420p',
    '-r', '30', '-movflags', '+faststart'];
  const aenc = ['-c:a', 'aac', '-b:a', '192k', '-ar', '48000'];
  execFileSync('ffmpeg', ['-y', '-v', 'error', ...inputs, '-filter_complex', graph.join(';'),
    '-map', '[v]', '-map', '[a]', ...venc, ...aenc, mp4], { stdio: 'inherit' });

  // Subtitles from edge-tts sentence timings.
  let n = 0;
  const srt = [];
  const cues = steps.flatMap((s) => loadJSON(path.join(p.audio, `${s.id}.json`)).sentences
    .map((x) => ({ start: I + s.start + x.start, end: I + s.start + x.end + 0.15, text: x.text })));
  cues.forEach((c, k) => {
    const end = k + 1 < cues.length ? Math.min(c.end, cues[k + 1].start - 0.02) : c.end;
    // RLM on both ends keeps Persian punctuation on the correct side.
    srt.push(`${++n}\n${srtTime(c.start)} --> ${srtTime(end)}\n\u200F${c.text}\u200F\n`);
  });
  const srtFile = path.join(p.out, `${name}.srt`);
  fs.writeFileSync(srtFile, srt.join('\n'));

  if (cfg.burnSubtitles) {
    const style = `FontName=Vazirmatn,FontSize=21,Bold=1,PrimaryColour=&H00FFFFFF,OutlineColour=&H30101320,` +
      `BorderStyle=3,BackColour=&H00000000,Outline=10,Shadow=0,MarginV=36`;
    const sub = `subtitles=${srtFile.replace(/[:\\']/g, '\\$&')}:fontsdir=${FONT_DIR}:force_style='${style}'`;
    execFileSync('ffmpeg', ['-y', '-v', 'error', '-i', mp4, '-vf', sub, '-c:a', 'copy', ...venc,
      path.join(p.out, `${name}-subtitled.mp4`)], { stdio: 'inherit' });
  }
  console.log(`done: ${path.relative(ROOT, mp4)} (${total.toFixed(1)}s)`);
})().catch((e) => { console.error(e.message); process.exit(1); });
