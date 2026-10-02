# Renee AgriSuite v3.2 API & Async Route Guide

## Scope

Renee AgriSuite is primarily a server-rendered PHP application with JSON/async helper routes under `api/`. This document describes the API conventions and the security/domain contracts that callers and future maintainers must preserve.

This is not a public third-party API specification. Routes are application-internal unless explicitly documented otherwise.

## Shared API authority

`api/api_helpers.php` provides common API behavior, including:

- authentication checks;
- permission checks;
- HTTP method enforcement;
- CSRF validation;
- JSON request decoding;
- JSON response helpers;
- rate limiting helpers;
- tenant-scoped record helpers;
- safe exception normalization;
- no-store/security response behavior.

New authenticated mutation endpoints should use these shared authorities rather than creating route-local equivalents.

## Core request rules

### Authentication

Authenticated API routes must establish the application session and call `requireLogin()` unless the endpoint has an explicitly reviewed public contract.

Public exceptions such as payment callbacks/webhooks must authenticate the external event using the provider-specific mechanism rather than relying on a browser session.

### Tenant context

Tenant-owned operations obtain the active Farm through `requireCurrentFarmId()` or a canonical service requiring an explicit `farmId`.

Object lookup must not rely on object `id` alone. Preferred patterns include:

```sql
WHERE id = ? AND farm_id = ?
```

or a shared service/helper whose contract guarantees the same tenant ownership rule.

### HTTP methods

State-changing APIs are POST-only unless a separately documented REST contract requires another method.

Use:

```php
require_http_method('POST');
```

for shared enforcement.

### CSRF

Browser-session mutations require CSRF validation through the shared API/CSRF helper.

Public provider webhooks are not browser-CSRF endpoints; they require provider signature authentication instead.

### Permissions

Authentication does not imply authorization. Routes must enforce the domain capability required for the action, for example Sales deletion, Inventory stock update, Expenses editing or financial-allocation management.

### Responses

JSON endpoints should return structured responses such as:

```json
{
  "success": true,
  "message": "..."
}
```

or:

```json
{
  "success": false,
  "error": "..."
}
```

Do not return SQL errors, stack traces, filesystem paths, secrets or raw internal exception detail.

## Reviewed endpoint families

### Sales

#### `api/delete_sale.php`

Purpose: delete/reverse a sale through the canonical Sales lifecycle.

Security/integrity contract:

- authenticated;
- POST only;
- CSRF protected;
- requires `delete_sales` permission;
- parent sale resolved inside current farm;
- canonical Sales lifecycle service owns physical/financial reversal behavior.

Direct SQL deletion of Sales lifecycle records should not replace this contract.

### Inventory

#### `api/update_stock.php`

Purpose: receive/use inventory through the canonical stock movement ledger.

Security/integrity contract:

- authenticated;
- POST only;
- CSRF protected;
- rate limited;
- Inventory + Update Stock authority (or Farm Admin/Platform Owner);
- item locked by `id + farm_id`;
- specialist-role farm-type access validated;
- movement persisted through `stock_apply_movement()`;
- transaction rollback on failure.

Accepted movement types are constrained to canonical movement semantics. Received stock requires an actual unit cost.

#### `api/get_stock_history.php`

Purpose: return inventory transaction history, running-balance/chart data and integrity summary.

Security/data contract:

- authenticated;
- current farm required;
- Inventory or relevant livestock access required;
- item lookup uses `id + farm_id`;
- transaction/user/cycle joins are farm-scoped;
- specialist users may only read items within their operational farm-type authority.

#### Other inventory/read helpers

The `api/` directory also contains item detail, previous-stock, stock summary and stock history helper routes. They must preserve the same tenant/read-authority rules.

### Expenses

#### `api/create_general_expense.php`

Purpose: create a General/Sales-workspace expense through the application expense contract.

Expected contract: authenticated, tenant-aware, permission-gated mutation with CSRF protection.

#### `api/update_expense.php`

Purpose: edit an existing expense.

Reviewed contract:

- authenticated;
- POST only;
- CSRF protected;
- current farm required;
- target expense resolved within current farm;
- module/permission policy enforced;
- referenced production cycle validated within current farm;
- update remains farm-scoped.

#### `api/delete_expense.php`

Purpose: delete/reverse an expense according to the expense lifecycle contract.

Deletion endpoints must retain tenant ownership, permission and CSRF/method gates.

### Financial allocation

#### `api/update_financial_allocation.php`

Purpose: update the shared financial-allocation workspace.

Contract:

- authenticated;
- POST only;
- CSRF protected;
- current farm required;
- canonical financial-allocation workspace/service owns source lookup, validation and persistence.

#### `api/update_sale_revenue_allocation.php`

Purpose: allocate or retain shared sale revenue.

Reviewed contract:

- authenticated;
- POST only;
- CSRF protected;
- rate limited;
- current farm required;
- parent sale locked/resolved using `farmId + saleId`;
- workspace authorization checked before mutation;
- canonical persistence service owns validation, revisions and writes.

Manual shared-revenue allocation must remain distinct from automatic allocation contracts such as Layer egg allocation.

#### `api/update_stock_consumption_allocation.php`

Purpose: attribute physical stock consumption between eligible production cycles without redefining the underlying stock movement.

The route must remain tenant scoped and use the canonical stock-consumption allocation service/workspace.

### Daily record helpers

Examples include:

- `api/check_record.php`;
- `api/check_ruminant_record.php`;
- record retrieval/deletion helpers.

These routes support Poultry/Ruminant daily-record UI workflows. They must enforce the current farm and the corresponding operational permission/module boundary.

### Charts / reporting helpers

Examples include:

- `api/get_chart_data.php`;
- stock-summary/history endpoints;
- pending-task helpers.

Read-only does not mean public: tenant reporting endpoints still require authentication, authorization and farm-scoped queries.

## Billing provider callbacks/webhooks

Billing provider callbacks/webhooks live under `billing/` rather than the main `api/` directory.

The reviewed Paystack webhook contract includes:

- POST-only receipt;
- raw-body HMAC-SHA512 verification;
- timing-safe `hash_equals()` comparison;
- environment-backed secret resolution;
- tracked provider reference validation;
- canonical provider/payment verification;
- DB transaction and row locking;
- canonical billing finalization.

Do not add browser CSRF tokens to provider webhooks; provider signature verification is the correct authentication boundary for those requests.

## Status code guidance

Application APIs currently use conventional status classes:

- `200` — success;
- `400` — malformed/invalid request;
- `403` — authenticated but not authorized;
- `404` — tenant-scoped parent/object not found;
- `409` — valid request conflicts with current domain state;
- `419` — CSRF/session request-token failure where used by browser flows;
- `422` — domain validation failure;
- `429` — rate limit;
- `500` — unexpected server error;
- `503` — temporarily unavailable dependency/service.

Exact route behavior should be preserved when clients already depend on it.

## Error handling rules

Safe domain validation messages may be returned when they reveal no internal implementation detail.

Unexpected failures should:

1. roll back an active transaction where applicable;
2. record an operator-safe log event;
3. return a generic user-facing error;
4. never return SQLSTATE, raw SQL, stack traces, environment values, tokens, secrets or filesystem paths.

GA hardening tracks exception-normalization coverage as a dedicated review item.

## Transaction rules

Use a DB transaction when an operation changes multiple pieces of authoritative state or combines parent locking with dependent writes.

Typical pattern:

```text
begin transaction
→ lock tenant-scoped parent
→ validate authority/current state
→ call canonical domain service
→ write revision/audit/ledger state
→ commit
```

On any exception, roll back before responding.

## Concurrency rules

Balance/lifecycle operations that can race should lock the authoritative row(s) using `FOR UPDATE` inside a transaction.

Examples include:

- Inventory current balance;
- billing/payment attempt finalization;
- allocation parent/revision state;
- credential/lifecycle token transitions where required.

## Adding a new API route

Before merging a new authenticated mutation endpoint, verify all of the following:

- route uses common bootstrap;
- authentication required;
- current tenant established;
- permission/entitlement enforced;
- method constrained;
- CSRF enforced for browser-session mutation;
- object ownership includes farm ID;
- input type/range/enum/date validation exists;
- canonical domain service is used rather than duplicated SQL;
- transaction/locking applied when state can race;
- safe error normalization applied;
- no secrets/internal errors returned;
- focused verifier added when introducing a new architectural contract;
- staging cross-tenant negative test added for ID-addressable resources.

## Staging API security matrix

The GA runtime test suite must use at least Farm A and Farm B and attempt Farm B object IDs while authenticated as Farm A across:

- Sales;
- Inventory;
- Expenses;
- Financial allocation;
- stock-consumption allocation;
- Production Cycles;
- Poultry daily records;
- Ruminant animals/records;
- Team Users;
- Permissions;
- Billing/account resources;
- reports/PDF exports.

A correct result is denial/not-found without Farm B data disclosure or mutation.
