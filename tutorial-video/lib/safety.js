// Read-only lockdown for the live site. Every request that could change data
// (anything but GET/HEAD/OPTIONS) is stopped inside the browser before it
// leaves. The WordPress heartbeat gets a harmless fake reply so the editor
// does not show "connection lost"; everything else is aborted.
const SAFE = new Set(['GET', 'HEAD', 'OPTIONS']);

async function lockdown(context) {
  const blocked = [];
  await context.route('**/*', async (route) => {
    const req = route.request();
    if (SAFE.has(req.method())) return route.continue();
    const url = new URL(req.url());
    const body = req.postData() || '';
    blocked.push(`${req.method()} ${url.pathname}`);
    if (url.pathname.endsWith('/admin-ajax.php') && body.includes('action=heartbeat')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ 'wp-auth-check': true, server_time: Math.floor(Date.now() / 1000) }),
      });
    }
    return route.abort('blockedbyclient');
  });
  return blocked;
}

// Turns off the block editor's autosave timers in this browser tab only.
async function disableEditorAutosave(page) {
  await page.evaluate(() => {
    const d = window.wp && wp.data && wp.data.dispatch('core/editor');
    if (d && d.updateEditorSettings) {
      d.updateEditorSettings({ autosaveInterval: 86400, localAutosaveInterval: 86400 });
    }
    if (window.wp && wp.autosave && wp.autosave.server) wp.autosave.server.suspend && wp.autosave.server.suspend();
  });
}

module.exports = { lockdown, disableEditorAutosave };
