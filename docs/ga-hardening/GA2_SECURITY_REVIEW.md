# Renee AgriSuite v3.2 — GA-2 Internal Source Security Review

Status: REPOSITORY/SOURCE REVIEW COMPLETE; runtime adversarial testing remains required on isolated staging.

This is an internal engineering security review, not an independent third-party penetration test. It reviews source trust boundaries and adds regression contracts. Runtime exploit resistance must still be tested against staging before GA.

## Scope

Reviewed security architecture and representative high-risk paths covering:

- authentication/session establishment;
- activation/password recovery credential lifecycle;
- tenant identity and role resolution;
- route/action permission runtime;
- Team User administration boundary;
- Platform Owner selected-tenant read view;
- browser APIs, including direct item/record reads and stock/sale/expense/allocation mutations;
- billing checkout/webhook/sandbox boundaries;
- PDF/report generation;
- source secrets/config/session/header policy;
- dependency manifests/lock;
- destructive/live-state testing exclusions.

GA-1 inventory remains the complete attack-surface map.

## Findings summary

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| GA2-AUTH-01 | High | Existing authenticated PHP sessions were not visibly bound to credential changes, so password reset/admin password change did not have a central stale-session revocation contract. | FIXED on GA branch with shared password-hash session fingerprint + tenant-bound revalidation. Runtime staging E2E still required. |
| GA2-AUTH-02 | Medium | Login POST established authenticated session state without using the shared CSRF form contract. | FIXED on GA branch with shared `csrf_field()` + `require_valid_csrf_post()`. |
| GA2-INFO-01 | Medium | PDF generation failure returned raw exception message to browser. | FIXED with generic browser message and server-side failure-class logging. |
| GA2-TEST-01 | Test defect | Initial API security verifier did not recognize the app's canonical `require_http_method()` / `require_csrf_token()` helper names, causing CI false failures. | FIXED in verifier; product routes were not weakened/rewritten to satisfy the test. |

No proven cross-tenant IDOR, SQL injection or billing-authentication bypass was established from the source paths inspected. That statement is intentionally limited to source evidence; runtime IDOR/injection/DAST remains a staging task.

## Authentication/session review

Observed controls before GA changes:

- prepared username/workspace lookup;
- generic login failure text;
- credential-state gate;
- guest IP-aware rate limiting;
- `session.use_strict_mode=1`;
- HttpOnly session cookie;
- Secure cookie on HTTPS;
- SameSite=Lax;
- session ID regeneration after successful login;
- 15-minute inactivity timeout;
- canonical password hashing/verification.

GA changes add:

- login CSRF;
- authenticated-session credential fingerprint;
- password-change revocation on next protected request;
- legacy sessions without fingerprint fail closed after deployment;
- public credential pages may render their PRG confirmation before stale-session destruction, preventing regression of the already-certified recovery UX.

Residual runtime tests:

- cookie attributes on real staging HTTPS;
- fixation before/after login;
- stale-session revocation after public reset and Team User admin password change;
- multiple-browser/session behavior;
- guest limiter behavior behind actual proxy/CDN topology.

## Credential token lifecycle

Source review observed:

- random token generation;
- hash-only token persistence;
- purpose binding;
- expiration;
- one-time use;
- supersession of outstanding tokens;
- `FOR UPDATE`/transaction use on credential mutation;
- privacy-safe recovery messaging.

Residual runtime tests:

- replay consumed reset/activation link;
- expired token;
- superseded token;
- clean-URL token landing;
- logs contain no raw token.

## Tenancy / IDOR review

Source architecture uses `farm_id` as the core tenant key. Representative direct-route checks inspected:

- stock update locks `stock_items` by both object ID and current farm;
- item detail lookup binds item ID to current farm;
- daily-record read routes bind reads to current farm;
- expense delete locks target row by ID + farm and applies scoped permission before deletion;
- sale delete uses tenant-scoped locked sale and canonical reversal services;
- financial-allocation update resolves/locks the parent in current-farm context before permission/mutation;
- Platform Owner tenant support view requires Platform Owner and issues tenant-specific read queries without switching owner identity.

The GA API verifier now audits minimum entry-point contracts across every `api/*.php` route and emits review findings when farm scope is not visible directly because it may be delegated to a service.

Residual runtime requirement: seed at least two staging tenants and deliberately submit Farm A IDs from Farm B credentials for read/edit/delete across users, cycles, animals, inventory, stock transactions, sales, expenses, allocations, reports and billing views.

## Authorization / privilege review

Source evidence supports:

- canonical permission catalog/runtime;
- farm-product/module applicability;
- action permissions separated from View permissions;
- Farm Admin/Platform Owner privilege handling;
- non-delegable user/permission administration to specialist roles;
- canonical navbar/route permission parity from earlier certified work;
- Platform Owner selected-tenant support view is explicitly read-only.

Residual staging tests:

- Sales Rep → Farm Admin privilege escalation;
- Farm Admin → Platform Owner escalation;
- crafted role/permission POST;
- direct denied-route access with hidden navbar bypassed;
- API action access where page navigation is denied.

## CSRF / HTTP method review

Shared browser CSRF is generated with `random_bytes(32)` and compared using `hash_equals`.

Observed mutation APIs use shared method/CSRF helpers. The GA API contract audit checks create/update/delete entry points for authentication + POST + CSRF. Login now participates in the same CSRF contract.

Residual runtime test: submit missing/invalid tokens to representative state-changing routes/APIs and confirm 405/419/403 behavior without state changes.

## Billing/payment review

Source review of webhook/sandbox architecture observed:

- webhook POST-only behavior;
- provider authentication/signature before trusting/persisting accepted event data;
- fresh provider-side payment verification;
- frozen local payment-attempt binding;
- transactions/row locking;
- idempotent/terminal event handling and exactly-once downstream dispatch architecture;
- sandbox launcher requires Farm Admin, payment mode `test`, explicit enable flag, designated QA farm and supported test provider.

Historical billing certification remains frozen. Do not mutate Farm22/K2–K6/seat-top-up history merely to retest.

Residual staging tests:

- invalid signature;
- replay;
- amount/currency/plan/farm mismatch;
- callback/webhook race;
- test-mode checkout only;
- sandbox flags disabled in production.

## SQL/data-access review

Core auth and reviewed APIs use prepared PDO statements and tenant predicates. Sensitive stock/expense/billing/allocation mutations use transactions and, where appropriate, row locks.

The source-security scanner treats dangerous execution primitives/secrets as blockers or review items. Regex/static checks do not prove absence of SQL injection; dynamic identifier/order fragments and delegated services remain part of staging DAST/manual review.

No SQL injection exploit is claimed proven or disproven by this document.

## XSS/output review

Observed:

- centralized CSP/output security bootstrap;
- widespread `htmlspecialchars` on reviewed user-visible values;
- JSON response encoding through shared helpers;
- PDF scripts removed from rendered HTML;
- PDF remote resource loading disabled;
- PDF engine chrooted to app root.

GA fixed raw PDF exception disclosure.

Residual runtime tests must cover stored/reflected payloads in customer/remarks, farm/user names, category/item names, animal/tag values and report/PDF output.

## Files/PDF review

Shared PDF service uses:

- `isRemoteEnabled=false`;
- application-root chroot;
- normalized download filename;
- generic failure text after GA hardening.

No broad upload subsystem was proven by the baseline inventory as a high-volume attack surface. Any upload route discovered during runtime/staging setup must still be reviewed for MIME, path traversal, executable placement, tenant ownership and download authorization.

## Configuration/secrets review

Source `config.php` expects DB credentials from environment. `.gitignore` excludes `.env*` except example config, logs and runtime upload contents. GA source scanner checks obvious live payment/mail key literals, private keys and TLS verification disablement.

No conclusion is made about secrets that may exist in server environment/config outside the Git repository. ChatGPT Work must inspect staging configuration without printing secret values.

## Dependency review

Direct Composer dependency is `dompdf/dompdf ^3.0`; exact locked packages are in `composer.lock`.

GitHub CI runs `composer audit --locked --no-dev`. Advisory status must be taken from the latest successful dependency-assurance job; do not infer vulnerabilities from version strings alone.

## Source security exit status

Repository-side GA-2 work is **PASS WITH RUNTIME EVIDENCE PENDING** provided the final GA CI source-regression and dependency-assurance jobs pass on the final HEAD.

This is not a declaration that v3.2 is production GA-ready. Remaining high-value assurance work requires isolated staging/Work and an independent external pentest.
