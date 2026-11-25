// Logs in headlessly and saves the session to auth.json. Prints no secrets.
// Credentials come from either WP_USER / WP_PASS environment variables, or a
// "Body parameter" network secret (log, pwd) for samanzavarian.com that the
// session proxy injects into the login POST, so the password never reaches
// this machine.
// Usage: node login.js
const fs = require('fs');
const { chromium } = require('playwright');
const { config, paths } = require('./lib/scenario');

(async () => {
  const { WP_USER, WP_PASS } = process.env;
  const site = config().site.replace(/\/$/, '');
  const browser = await chromium.launch();
  const context = await browser.newContext({ locale: 'fa-IR' });
  const page = await context.newPage();
  try {
    await page.goto(`${site}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    if (await page.locator('.g-recaptcha, .h-captcha, .cf-turnstile, [name="cf-turnstile-response"]').count()) {
      throw new Error('The login form has a CAPTCHA; headless login is not possible.');
    }
    if (WP_USER && WP_PASS) {
      await page.fill('#user_login', WP_USER);
      await page.fill('#user_pass', WP_PASS);
    } else {
      // Proxy mode: the environment's network secret injects `log` and `pwd`
      // into the POST. Drop the empty fields so nothing collides with them.
      await page.evaluate(() => {
        for (const id of ['user_login', 'user_pass']) {
          const el = document.getElementById(id);
          el.removeAttribute('name'); el.removeAttribute('required');
        }
      });
    }
    await page.check('#rememberme').catch(() => {});
    await Promise.all([page.waitForLoadState('domcontentloaded'), page.click('#wp-submit')]);
    await page.waitForTimeout(1500);
    if (await page.locator('#login_error').count()) throw new Error('WordPress rejected the login (see the login form).');
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
