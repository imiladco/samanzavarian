// Drives the page like a calm human presenter: smooth cursor moves, a
// highlight before every click, natural typing, and steps that last at least
// as long as their narration.
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

class Director {
  constructor(page, { typing = [60, 120] } = {}) {
    this.page = page;
    this.typing = typing;
    this.x = 960;
    this.y = 540;
    this.t0 = null;
    this.timeline = [];
  }

  now() { return (Date.now() - this.t0) / 1000; }
  wait(ms) { return sleep(ms); }

  async cursorTo(x, y, ms) {
    const dist = Math.hypot(x - this.x, y - this.y);
    const dur = ms ?? Math.min(1100, 350 + dist * 0.6);
    const sx = this.x, sy = this.y, frames = Math.max(8, Math.round(dur / 16));
    for (let i = 1; i <= frames; i++) {
      const k = ease(i / frames);
      const nx = sx + (x - sx) * k, ny = sy + (y - sy) * k;
      await this.page.mouse.move(nx, ny);
      await this.page.evaluate(([a, b]) => window.__tv && window.__tv.move(a, b), [nx, ny]);
      await sleep(dur / frames);
    }
    this.x = x; this.y = y;
  }

  // Re-places the cursor after a navigation (the overlay is re-created).
  async restoreCursor() {
    await this.page.evaluate(([a, b]) => window.__tv && window.__tv.move(a, b), [this.x, this.y]);
  }

  async box(target) {
    await target.waitFor({ state: 'visible', timeout: 15000 });
    await target.scrollIntoViewIfNeeded();
    await sleep(250);
    return target.boundingBox();
  }

  async highlight(target, ms = 1100) {
    const b = await this.box(target);
    await this.page.evaluate((bb) => window.__tv.highlight(bb), b);
    await sleep(ms);
    return b;
  }

  async unhighlight() {
    await this.page.evaluate(() => window.__tv && window.__tv.highlight(null));
    await sleep(250);
  }

  async point(target, { highlight = true, ms = 1100 } = {}) {
    const b = await this.box(target);
    await this.cursorTo(b.x + b.width / 2, b.y + b.height / 2);
    if (highlight) await this.highlight(target, ms);
    return b;
  }

  async click(target, opts = {}) {
    await this.point(target, opts);
    await this.page.evaluate(() => window.__tv.press(true));
    await this.page.mouse.down();
    await sleep(90);
    await this.page.mouse.up();
    await this.page.evaluate(() => window.__tv.press(false));
    await this.unhighlight();
  }

  async type(text) {
    const [lo, hi] = this.typing;
    for (const ch of text) {
      await this.page.keyboard.type(ch);
      const pause = /[\s،.؟!]/.test(ch) ? hi * 1.6 : lo + Math.random() * (hi - lo);
      await sleep(pause);
    }
  }

  // Runs one scenario step and pads it to at least `minSeconds`.
  async step(id, minSeconds, fn) {
    const start = this.now();
    await fn(this);
    const left = start + minSeconds - this.now();
    if (left > 0) await sleep(left * 1000);
    this.timeline.push({ id, start, end: this.now() });
  }
}

module.exports = { Director, sleep };
