// Reads scenarios/<name>/scenario.md. Format:
//   front matter (title, url) between --- lines, then one "## <id> · <title>"
//   section per step with "**گفتار:**" holding the narration.
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

function loadScenario(name) {
  const dir = path.join(ROOT, 'scenarios', name);
  const md = fs.readFileSync(path.join(dir, 'scenario.md'), 'utf8');
  const meta = {};
  const fm = md.match(/^---\n([\s\S]*?)\n---/);
  if (fm) for (const line of fm[1].split('\n')) {
    const m = line.match(/^(\w+):\s*(.*)$/);
    if (m) meta[m[1]] = m[2].trim();
  }
  const steps = [];
  for (const block of md.split(/^## /m).slice(1)) {
    const head = block.split('\n')[0];
    const [id, ...t] = head.split('·');
    const say = block.match(/\*\*گفتار:\*\*\s*([\s\S]*?)(?:\n\s*\n|\n\*\*|$)/);
    if (!say) throw new Error(`step ${id.trim()} has no **گفتار:** line`);
    steps.push({ id: id.trim(), title: t.join('·').trim(), say: say[1].replace(/\s+/g, ' ').trim() });
  }
  return { name, dir, meta, steps };
}

const loadJSON = (p) => JSON.parse(fs.readFileSync(p, 'utf8'));
const config = () => loadJSON(path.join(ROOT, 'branding', 'config.json'));
const paths = (name) => ({
  audio: path.join(ROOT, 'audio', name),
  out: path.join(ROOT, 'output', name),
  auth: path.join(ROOT, 'auth.json'),
});

module.exports = { ROOT, loadScenario, loadJSON, config, paths };
