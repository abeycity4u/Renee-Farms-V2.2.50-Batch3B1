# Renee AgriSuite v3.2 — Browser/API Reference

Status: GA-hardening source reference

Renee AgriSuite is primarily a server-rendered PHP application. The `/api` directory contains browser-facing JSON/read/action endpoints used by application pages. These are session-authenticated application APIs, not a public third-party REST API.

## 1. Shared API contract

`api/api_helpers.php` is the common API policy layer. It provides shared JSON response policy, HTTP-method enforcement, CSRF helpers, safe exception handling and rate limiting.

For an authenticated browser mutation, the expected authority chain is:

`session login`
→ `current farm`
→ `entitlement/module`
→ `permission/action`
→ `HTTP method`
→ `CSRF`
→ `validated input`
→ `tenant-bound object lookup`
→ `shared domain service`
→ `safe JSON response`

A record identifier never grants authority by itself.

## 2. Authentication

APIs that act on tenant data require an authenticated Renee AgriSuite PHP session unless explicitly designed as a provider/public endpoint outside `/api`.

The authenticated session contains the user/farm context. GA hardening also binds authenticated sessions to current credential state so a password change revokes older sessions on their next protected request.

## 3. CSRF and methods

State-changing browser endpoints are expected to:

- require the intended HTTP method, normally POST;
- verify the shared CSRF token;
- reject malformed/unsupported requests without exposing implementation details.

Read-only GET endpoints do not use CSRF as mutation authority, but still require authentication, entitlement/permission and tenant-scoped reads.

## 4. Error behavior

APIs must not return raw:

- SQLSTATE/driver errors;
- filesystem paths;
- stack traces;
- credentials/tokens;
- payment secrets;
- internal exception detail not intended for users.

Use the shared safe API exception/JSON response helpers for unexpected failures.

## 5. Observed API inventory

The source tree contains the following categories. The table records the security contract visible from GA source review; exact request fields remain defined by each route and its shared service.

| Endpoint | Purpose | Typical method | Security boundary |
|---|---|---:|---|
| `/api/check_pending_tasks.php` | Pending operational work/read model | GET | login + tenant/role visibility |
| `/api/check_record.php` | Poultry record existence/read check | GET | login + poultry View + current farm/cycle |
| `/api/check_ruminant_record.php` | Ruminant record existence/read check | GET | login + ruminant View + current farm |
| `/api/create_general_expense.php` | Create General Expense | POST | login + expense Add authority + CSRF + farm binding |
| `/api/delete_expense.php` | Delete permitted expense | POST | login + scoped Delete authority + CSRF + farm-bound locked row |
| `/api/delete_record.php` | Delete supported daily record | POST | login + granular Delete authority + CSRF + farm-bound record |
| `/api/delete_sale.php` | Delete sale and reverse linked effects | POST | login + Sales Delete + CSRF + farm-bound locked sale + canonical reversals |
| `/api/get_chart_data.php` | Dashboard/report chart data | GET | login + relevant View authority + tenant-filtered aggregation |
| `/api/get_item_details.php` | Inventory item detail | GET | login + Inventory View + item ID bound to current farm |
| `/api/get_previous_stock.php` | Prior/current stock context | GET | login + Inventory View + farm-scoped item/transaction context |
| `/api/get_record.php` | Layer/Broiler/Ruminant record fetch | GET | login + legacy View bridge + farm/module/cycle scope |
| `/api/get_stock_history.php` | Stock transaction history, ledger integrity and allocation-action context | GET | login + current farm + Inventory/admin or permitted Poultry/Ruminant item scope + tenant-bound item/transactions |
| `/api/get_stock_summary.php` | Dashboard stock summary for an allowed farm module | GET | login + current farm + Platform Owner/Farm Admin or permitted Poultry/Ruminant module scope + tenant-filtered aggregation |
| `/api/stock_history.php` | Authenticated Stock History browser page | GET | login + current farm + tenant-bound item + Inventory/admin or permitted Poultry/Ruminant item scope |
| `/api/update_expense.php` | Update an existing expense and its canonical attribution/revision state | POST | login + CSRF + rate limit + tenant-bound expense + scoped Edit authority + allocation/integrity services + transaction |
| `/api/update_financial_allocation.php` | Update shared financial allocation | POST | login + CSRF + rate limit + parent loaded/locked inside current farm + scoped edit authority |
| `/api/update_sale_revenue_allocation.php` | Update sale revenue allocation | POST | login + Sales/allocation authority + CSRF + tenant-bound sale/service |
| `/api/update_stock.php` | Receive/use/adjust inventory through stock service | POST | login + Inventory/Update Stock + CSRF + rate limit + `id`/`farm_id` row lock |
| `/api/update_stock_consumption_allocation.php` | Update stock-consumption attribution | POST | login + allocation authority + CSRF + tenant-bound stock transaction/service |

The current `/api` directory is the source of truth; new endpoints must be added to this document and to GA security coverage when introduced.

## 6. Example: stock update contract

`/api/update_stock.php` is the reference mutation pattern observed in GA review:

1. authenticated user is required;
2. POST is required;
3. CSRF is verified;
4. request rate is bounded;
5. Inventory + Update Stock permission is enforced;
6. item/type/quantity/date/cost inputs are validated;
7. the item is selected with both `id` and current `farm_id` under `FOR UPDATE`;
8. delegated farm-type access is checked;
9. canonical stock movement service applies the transaction;
10. current stock is re-read inside tenant scope;
11. transaction commits and JSON is returned.

## 7. Example: sale delete contract

`/api/delete_sale.php` does not merely delete a row. It:

- locks the current-farm sale;
- checks delete/lifecycle authority;
- reverses canonical inventory effects;
- reverses/restores canonical population effects where applicable;
- updates related customer/ledger state inside farm scope;
- records audit behavior;
- performs the mutation transactionally.

This is why Sales deletion must not be replaced by page-local SQL.

## 8. Example: financial allocation contract

`/api/update_financial_allocation.php` resolves the allocation parent through the shared financial allocation workspace using the current farm, locks it, evaluates permission against that same parent and persists through the canonical service. This avoids an IDOR pattern where authorization and mutation are based on different object lookups.

## 9. Billing/public HTTP endpoints outside `/api`

Billing is a separate HTTP surface under `/billing`.

Key endpoints include:

- `/billing/checkout.php`
- `/billing/seat_topup_checkout.php`
- `/billing/return.php`
- `/billing/webhook.php`
- billing receipt/recovery/account surfaces
- `/billing/sandbox_checkout.php` (must remain environment-safe)

`billing/webhook.php` is deliberately session-independent. Its authority is the payment provider signature/authentication plus fresh provider verification and binding to a frozen local payment attempt. Browser CSRF is not a substitute for webhook authentication.

## 10. Public credential endpoints outside `/api`

- `/sign.php`
- `/account/activate.php`
- `/account/forgot_password.php`
- `/account/reset_password.php`
- `/logout.php`

Activation/reset tokens are capability URLs. Raw tokens are accepted only for the tokenized landing, moved into server-side session state, and followed by a clean-URL redirect before normal rendering.

## 11. Adding a new API

A new API route must answer all of these before merge:

1. Is it public or authenticated?
2. What farm/tenant owns the target object?
3. What entitlement/module gates access?
4. What View/Add/Edit/Delete/action permission applies?
5. What HTTP method is accepted?
6. Is CSRF required?
7. What input schema/range validation applies?
8. Is every object lookup tenant-bound?
9. Does a shared domain service already own the mutation?
10. Are writes transactional where multiple business effects must stay atomic?
11. Can an exception disclose SQL/path/secret information?
12. What focused verifier/E2E probe proves the new contract?

If a route cannot answer these consistently, it is not GA-ready.
