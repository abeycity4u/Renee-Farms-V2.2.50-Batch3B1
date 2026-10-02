const { expect } = require('@playwright/test');

function requiredFarmCredentials() {
  const farmSlug = process.env.E2E_FARM_SLUG || '';
  const username = process.env.E2E_USERNAME || '';
  const password = process.env.E2E_PASSWORD || '';
  return { farmSlug, username, password };
}

function hasFarmCredentials() {
  const { farmSlug, username, password } = requiredFarmCredentials();
  return Boolean(farmSlug && username && password);
}

async function loginFarm(page) {
  const { farmSlug, username, password } = requiredFarmCredentials();
  if (!farmSlug || !username || !password) {
    throw new Error('E2E_FARM_SLUG, E2E_USERNAME and E2E_PASSWORD are required.');
  }

  await page.goto('/sign.php');
  await page.locator('input[name="account_type"][value="farm"]').check();
  await page.locator('input[name="farm_slug"]').fill(farmSlug);
  await page.locator('input[name="username"]').fill(username);
  await page.locator('input[name="password"]').fill(password);

  await Promise.all([
    page.waitForURL(/\/dashboard\.php(?:$|[?#])/, { timeout: 15_000 }),
    page.getByRole('button', { name: /enter workspace/i }).click(),
  ]);

  await expect(page).toHaveURL(/\/dashboard\.php(?:$|[?#])/);
}

module.exports = {
  hasFarmCredentials,
  loginFarm,
  requiredFarmCredentials,
};
