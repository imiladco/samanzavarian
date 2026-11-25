// Logs in headlessly with WP_USER / WP_PASS (set in the environment, never in
// files or chat) and saves the session to auth.json. Prints no secrets.
// Usage: node login.js
const fs = require('fs');
const { chromium } = require('playwright');
const { config, paths } = require('./lib/scenario');

(async () => {
  const { WP_USER, WP_PASS } = process.env;
  if (!WP_USER || !WP_PASS) {
    console.error('WP_USER and WP_PASS must be set in the environment.');
    process.exit(2);
  }
  const site = config().site.replace(/\/$/, '');
  const browser = await chromium.launch();
  const context = await browser.newContext({ locale: 'fa-IR' });
  const page = await context.newPage();
  try {
    await page.goto(`${site}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    if (await page.locator('.g-recaptcha, .h-captcha, .cf-turnstile, [name="cf-turnstile-response"]').count()) {
      throw new Error('The login form has a CAPTCHA; headless login is not possible.');
    }
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
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
