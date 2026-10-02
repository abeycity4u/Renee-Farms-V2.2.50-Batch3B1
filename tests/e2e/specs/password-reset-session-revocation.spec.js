const { test, expect } = require('@playwright/test');
const { loginFarm } = require('../helpers/auth');

const destructiveEnabled = process.env.E2E_DESTRUCTIVE_CREDENTIAL_TEST === '1';

test.describe('Password reset session revocation — isolated staging only', () => {
  test.skip(!destructiveEnabled, 'Set E2E_DESTRUCTIVE_CREDENTIAL_TEST=1 only for a disposable staging account.');

  test('successful reset revokes an already authenticated session', async ({ browser }) => {
    const resetUrl = process.env.E2E_RESET_URL || '';
    const newPassword = process.env.E2E_NEW_PASSWORD || '';
    test.skip(!resetUrl || !newPassword, 'E2E_RESET_URL and E2E_NEW_PASSWORD are required.');

    const oldContext = await browser.newContext();
    const oldPage = await oldContext.newPage();
    await loginFarm(oldPage);

    const resetContext = await browser.newContext();
    const resetPage = await resetContext.newPage();
    await resetPage.goto(resetUrl);
    await resetPage.locator('input[name="new_password"]').fill(newPassword);
    await resetPage.locator('input[name="confirm_password"]').fill(newPassword);
    await resetPage.locator('button[type="submit"]').click();

    await expect(resetPage.getByText(/password has been reset successfully/i)).toBeVisible();

    await oldPage.goto('/dashboard.php');
    await expect(oldPage).toHaveURL(/\/sign\.php(?:$|[?#])/);

    await oldContext.close();
    await resetContext.close();
  });
});
