// Logs in headlessly and saves the session to auth.json. Prints no secrets.
// Credentials come from either WP_USER / WP_PASS environment variables, or a
// "Body parameter" network secret (log, pwd) for samanzavarian.com that the
// session proxy injects into the login POST, so the password never reaches
// this machine.
// Usage: node login.js
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const { config, paths } = require('./lib/scenario');

(async () => {
  const { WP_USER, WP_PASS } = process.env;
  const site = config().site.replace(/\/$/, '');
  const browser = await chromium.launch();
  const context = await browser.newContext({ locale: 'fa-IR' });
  const page = await context.newPage();
  try {
    if (WP_USER && WP_PASS) {
      await page.goto(`${site}/wp-login.php`, { waitUntil: 'domcontentloaded' });
      if (await page.locator('.g-recaptcha, .h-captcha, .cf-turnstile, [name="cf-turnstile-response"]').count()) {
        throw new Error('The login form has a CAPTCHA; headless login is not possible.');
      }
      await page.fill('#user_login', WP_USER);
      await page.fill('#user_pass', WP_PASS);
      await page.check('#rememberme').catch(() => {});
      await Promise.all([page.waitForLoadState('domcontentloaded'), page.click('#wp-submit')]);
      await page.waitForTimeout(1500);
      if (await page.locator('#login_error').count()) throw new Error('WordPress rejected the login.');
    } else {
      // Proxy mode: the environment's "Body parameter" secret adds `log` and
      // `pwd` to POSTs under /wp-login.php. It cannot inject into a GET, so the
      // form is posted directly (with WordPress's test cookie set by hand).
      // Posted with curl under its own name (the host firewall refuses this
      // POST from Playwright's clients). Session cookies go from curl's private
      // cookie jar into the browser context, then the jar is deleted.
      const host = new URL(site).hostname;
      const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'wp-login-'));
      const jar = path.join(dir, 'jar');
      const body = path.join(dir, 'body');
      try {
        const out = execFileSync('curl', ['-s', '-m', '30', '-c', jar, '-b', 'wordpress_test_cookie=WP%20Cookie%20check',
          '--data', `wp-submit=Log+In&testcookie=1&rememberme=forever&redirect_to=${encodeURIComponent(site + '/wp-admin/')}`,
          '-o', body, '-w', '%{http_code} %{redirect_url}', `${site}/wp-login.php`], { encoding: 'utf8' });
        const [code, where = ''] = out.split(' ');
        const text = fs.existsSync(body) ? fs.readFileSync(body, 'utf8') : '';
        if (code === '502') throw new Error(`Proxy could not inject the secret: ${text.slice(0, 120)}`);
        if (code !== '302' || /wp-login\.php/.test(where)) {
          const m = text.match(/id="login_error"[^>]*>([\s\S]*?)<\/div>/);
          const why = m ? m[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim().slice(0, 200) : '';
          throw new Error(`WordPress did not accept the login (HTTP ${code}${why ? ': ' + why : ''}).`);
        }
        const cookies = fs.readFileSync(jar, 'utf8').split('\n')
          .map((l) => l.replace(/^#HttpOnly_/, ''))
          .filter((l) => l && !l.startsWith('#'))
          .map((l) => l.split('\t'))
          .filter((f) => f.length >= 7)
          .map(([domain, , cpath, secure, expires, name, value]) => ({
            name, value, domain: domain.replace(/^\./, '') || host, path: cpath,
            secure: secure === 'TRUE', httpOnly: true, sameSite: 'Lax', expires: Number(expires) || -1,
          }));
        await context.addCookies(cookies);
      } finally {
        fs.rmSync(dir, { recursive: true, force: true });
      }
      await page.goto(`${site}/wp-admin/`, { waitUntil: 'domcontentloaded' });
    }
    if (!/\/wp-admin\//.test(page.url())) {
      await page.goto(`${site}/wp-admin/`, { waitUntil: 'domcontentloaded' });
    }
    if (!(await page.locator('#wpadminbar').count())) throw new Error(`Not in the dashboard after login (at ${new URL(page.url()).pathname}).`);
    const file = paths('').auth;
    await context.storageState({ path: file });
    fs.chmodSync(file, 0o600);
    console.log('Logged in; session saved to auth.json.');
  } catch (e) {
    console.error('Login failed:', e.message);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
