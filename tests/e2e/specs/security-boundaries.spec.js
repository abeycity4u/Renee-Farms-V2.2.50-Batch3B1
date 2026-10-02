const { test, expect } = require('@playwright/test');
const { hasFarmCredentials, loginFarm } = require('../helpers/auth');

test.describe('Tenant and authorization boundaries', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!hasFarmCredentials(), 'Staging farm credentials were not supplied.');
    await loginFarm(page);
  });

  test('foreign inventory item id is not disclosed', async ({ page }) => {
    const foreignId = process.env.E2E_FOREIGN_STOCK_ITEM_ID || '';
    test.skip(!foreignId, 'E2E_FOREIGN_STOCK_ITEM_ID was not supplied from a second staging tenant.');

    const response = await page.request.get(`/api/get_item_details.php?id=${encodeURIComponent(foreignId)}`);
    const body = await response.text();

    expect([403, 404]).toContain(response.status());
    expect(body).not.toMatch(/"success"\s*:\s*true/i);
  });

  test('explicitly denied route remains denied when requested directly', async ({ page }) => {
    const deniedRoute = process.env.E2E_DENIED_ROUTE || '';
    test.skip(!deniedRoute, 'E2E_DENIED_ROUTE was not supplied for the staging role.');

    const response = await page.goto(deniedRoute);
    expect(response).not.toBeNull();

    const url = page.url();
    const status = response.status();
    const denied = status === 403 || /\/no_access\.php(?:$|[?#])/.test(url);
    expect(denied).toBeTruthy();
  });

  test('authenticated pages do not leak raw SQL/PHP exception signatures', async ({ page }) => {
    const routes = (process.env.E2E_READ_ROUTES || '/dashboard.php,/inventory.php')
      .split(',')
      .map((value) => value.trim())
      .filter(Boolean);

    for (const route of routes) {
      const response = await page.goto(route);
      expect(response).not.toBeNull();
      expect(response.status()).toBeLessThan(500);
      const body = await page.locator('body').innerText();
      expect(body).not.toMatch(/SQLSTATE\[|PDOException|Fatal error:|Stack trace:/i);
    }
  });
});
