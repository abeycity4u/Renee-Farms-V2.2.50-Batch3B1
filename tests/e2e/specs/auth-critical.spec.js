const { test, expect } = require('@playwright/test');
const { hasFarmCredentials, loginFarm } = require('../helpers/auth');

test.describe('Authentication critical path', () => {
  test('sign in page exposes password recovery without leaking account context', async ({ page }) => {
    await page.goto('/sign.php');

    await expect(page.getByRole('heading', { name: /sign in to continue/i })).toBeVisible();
    const recovery = page.getByRole('link', { name: /forgot password/i });
    await expect(recovery).toBeVisible();
    await expect(recovery).toHaveAttribute('href', /account\/forgot_password\.php/);
  });

  test('invalid credentials return the generic login failure', async ({ page }) => {
    await page.goto('/sign.php');
    await page.locator('input[name="account_type"][value="farm"]').check();
    await page.locator('input[name="farm_slug"]').fill('ga-nonexistent-workspace');
    await page.locator('input[name="username"]').fill('ga-invalid-user');
    await page.locator('input[name="password"]').fill('DefinitelyWrongPassword!');

    await Promise.all([
      page.waitForURL(/\/sign\.php(?:$|[?#])/, { timeout: 15_000 }),
      page.getByRole('button', { name: /enter workspace/i }).click(),
    ]);

    await expect(page.getByText('Invalid workspace, username, or password.')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('ga-nonexistent-workspace');
  });

  test('valid farm credentials establish an authenticated dashboard session', async ({ page }) => {
    test.skip(!hasFarmCredentials(), 'Staging farm credentials were not supplied.');
    await loginFarm(page);
    await expect(page.locator('body')).not.toContainText(/invalid workspace, username, or password/i);
  });
});
