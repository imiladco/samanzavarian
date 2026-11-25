// Offline check of the whole pipeline on a local page (no site access).
module.exports = {
  steps: {
    s1: async (d, page) => { await d.click(page.locator('#t')); await d.type('سلام دنیا'); },
    s2: async (d, page) => {
      await d.click(page.locator('#c'));
      await d.point(page.locator('#pub'), { ms: 1500 }); await d.unhighlight();
    },
  },
};
