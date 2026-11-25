// Logs in headlessly and saves the session to auth.json. Prints no secrets.
// Credentials come from either WP_USER / WP_PASS environment variables, or a
// "Body parameter" network secret (log, pwd) for samanzavarian.com that the
// session proxy injects into the login POST, so the password never reaches
// this machine.
// Usage: node login.js
const fs = require('fs');
const { chromium, request } = require('playwright');
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
      // Posted with Playwright's own HTTP client (honest "Playwright" user
      // agent); the resulting session cookies are handed to the browser.
      const host = new URL(site).hostname;
      const api = await request.newContext({
        storageState: { cookies: [{ name: 'wordpress_test_cookie', value: 'WP Cookie check', domain: host, path: '/',
          expires: -1, httpOnly: false, secure: true, sameSite: 'Lax' }], origins: [] },
      });
      const res = await api.post(`${site}/wp-login.php`, {
        form: { 'wp-submit': 'Log In', redirect_to: `${site}/wp-admin/`, testcookie: '1', rememberme: 'forever' },
        maxRedirects: 0,
      });
      const where = res.headers()['location'] || '';
      if (res.status() === 502) throw new Error(`Proxy could not inject the secret: ${(await res.text()).slice(0, 120)}`);
      if (res.status() !== 302 || /wp-login\.php/.test(where)) {
        const m = (await res.text()).match(/id="login_error"[^>]*>([\s\S]*?)<\/div>/);
        const why = m ? m[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim().slice(0, 200) : '';
        throw new Error(`WordPress did not accept the login (HTTP ${res.status()}${why ? ': ' + why : ''}).`);
      }
      await context.addCookies((await api.storageState()).cookies);
      await api.dispose();
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
