# Renee AgriSuite GA Playwright E2E

These tests are prepared for **isolated staging**. They are not intended to mutate production.

## Prerequisites

- staging app deployed from the intended `v320-ga-hardening` commit;
- staging database separated from production;
- synthetic/disposable test tenants/users;
- HTTPS with staging session configuration;
- Node.js/npm;
- Playwright browser runtime.

Install:

```bash
cd tests/e2e
npm install
npx playwright install chromium
```

## Safe public/auth tests

Set:

```bash
export E2E_BASE_URL="https://staging.example.test"
export E2E_FARM_SLUG="synthetic-farm"
export E2E_USERNAME="ga-test-user"
export E2E_PASSWORD="<staging-only-secret>"
```

Do not echo or commit staging credentials.

Run:

```bash
npm test
```

Tests with missing fixture variables skip rather than invent IDs.

## Tenant-boundary probes

To test a Farm B session against an object owned by Farm A, supply an inventory item ID created in the other synthetic tenant:

```bash
export E2E_FOREIGN_STOCK_ITEM_ID="<foreign-staging-id>"
```

Expected result: 403/404, never a successful object response.

For a role-specific direct-route denial, supply a route that the current staging user is intentionally not permitted to view:

```bash
export E2E_DENIED_ROUTE="/admin/permissions.php"
```

Expected result: HTTP 403 or redirect to `no_access.php`.

## Destructive credential/session test

Use a **disposable staging account only**. This test changes its password.

Create/request a valid staging reset URL first, then set:

```bash
export E2E_DESTRUCTIVE_CREDENTIAL_TEST=1
export E2E_RESET_URL="https://staging.example.test/account/reset_password.php?token=<staging-token>"
export E2E_NEW_PASSWORD="<new-disposable-staging-password>"
```

The test proves that a browser session authenticated before the reset is redirected to sign-in on its next protected request.

After the test, reset/recreate the disposable account as needed. Never use a production account or historical certified user.

## Evidence to retain

For GA evidence retain:

- staging release SHA;
- test command/environment names, but no secrets;
- PASS/FAIL summary;
- Playwright HTML report;
- trace/screenshot/video only for failed cases;
- fixture tenant/user IDs that are synthetic;
- issue/remediation link for every failure.

Do not retain passwords, reset tokens, session cookies or CSRF tokens in the evidence package.
