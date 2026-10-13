# C137 GC24 K2-P03 B12 — config.php authentication integration semantic audit

**2026-10-13 | Source-only audit; NO runtime wiring.** Protected production, staging, AWS nodes, cPanel, customer databases and original concurrency workflows remain untouched.

## Exact input pins

- Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Source branch: `c137-gc24-k2p03-b12-canonical-auth-decisions-source`, source HEAD `67a73a90dc9a48580046c659577bb0d692ad1a9c`.
- Audit branch: `c137-gc24-k2p03-b12-config-auth-semantic-audit`, forked at that exact HEAD.
- Existing live `config.php` blob: `2833c047b08c7f6f0e5a95c14151df287d51db09`.
- Dormant pure helper `includes/b12_auth_core_decisions.php` blob: `77d2a038462329950dada89111677f9cb9d58c6f`.
- Prior extracted source-only unit verifier: `tests/integration/k2p03_b12_auth_core_decisions_pure.php` blob `67b18530fa56ca5f3968b040fdfe20a5ff547bba`; prior 39/39 local PASS relates only to that pure helper, **not** equivalence with live PHP.
- No new tests claimed in this review. Remote compare required after this new documentation commit.

## Canonical config.php decision and side-effect inventory

| Source location / condition | Canonical behavior now | Dormant helper candidate | Safe integration requirement |
|---|---|---|---|
| Session init around lines 65–73 | Strict session mode, secure/HTTP-only/SameSite cookie policy, `session_start()` | None | Must precede timeout evaluation; no route bypass |
| `config.php` lines 75–82 | If `user_id` and `LAST_ACTIVITY` exist and `time()-last > SESSION_TIMEOUT`, clear the entire session and redirect to login timeout URL, exit | `b12_auth_core_session_timeout_event()` | Decide before teardown; preserve teardown and exact legacy HTML redirect; approved B12 route alone may use JSON response; never allow bootstrap to proceed after denial |
| Lines 85–88 | Refresh `LAST_ACTIVITY` for logged-in session | None | Preserve update only for nonexpired sessions; avoid passing client time |
| Lines 90–100 | PDO connect and session SQL timezone set; PDO failure logs and ends in plain text | None | Separate fail-closed JSON-error transport evaluation; do not bypass DB/account check |
| `requireCurrentFarmId()` lines 148–161 | Read farm_id from server session, cast to int; if <1 clear session, redirect `sign.php`, exit | `b12_auth_core_farm_event()` | Preserve server-only identity, teardown, and HTML redirect |
| `requireLogin()` lines 347–351 | No session user means login redirect, exit | Prior `b12_auth_response_policy.php` `unauthenticated` event | No operational SELECT on guests; retain canonical semantics |
| `requireLogin()` lines 353–366 | Tenant-scoped JOIN over users/farms/roles; `WHERE u.id=? AND u.farm_id=?`; `GROUP BY`; `LIMIT 1` | `b12_auth_core_account_outcome()` consumes result | Keep EXACT SQL owner and query parameters; no second lookup for the B12 API |
| Lines 367–374 | Detect Platform Owner from projected flag. For any row, refresh session user_type and full_name BEFORE account restriction branch | Returns refreshed fields | Preserve ordering, even when suspended/cancelled leads to subsequent teardown |
| Lines 375–380 | Missing row or suspended/cancelled non-owner clears entire session, login redirect, exit | Returns `invalid_account` | Preserve complete teardown and Platform Owner bypass; no fail-open on invalid projected row |

## Semantic mismatches that MUST be resolved before touching live config.php

1. **Timestamp coercion is not strictly equivalent.** Legacy expression `time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT` uses PHP native arithmetic/coercion. Dormant helper validates a nonnegative canonical integer/numeric string and rejects future timestamps and malformed inputs. For some malformed strings native PHP could throw; for future valid timestamps legacy simply does not expire. The new helper's strict rejection is a deliberate fail-closed design but **cannot yet be described as behavior-preserving**. Specify the accepted authenticated-session timestamp domain, how malformed/future values should invalidate identity, and test PHP-version-specific behavior without real sessions before adopting it.
2. **Existing `isset` and weak casts differ from strict helper requirements.** `requireCurrentFarmId()` casts server session farm ID with `(int)`; helper receives typed integer. Caller must pass canonical `getCurrentFarmId()` result—not a request value—and must preserve invalid-farm teardown.
3. **Account-result validation is stronger in the dormant helper.** Legacy trusts the projected user_type, full_name, subscription_status, is_platform_owner fields returned by the established query. Helper rejects missing or malformed projection keys. Decide how to fail closed and clean session on corrupt/incompatible result, avoiding an uncaught 500 or split HTML/JSON response. Do not weaken entitlement decisions.
4. **Session field refresh order is security-relevant.** Legacy updates `$_SESSION['user_type']` and `full_name` on a found account before potentially clearing session on suspended/cancelled statuses. New integration must not accidentally refresh fields after a denial or preserve stale role claims.
5. **Platform Owner bypass is exact to current query.** Do not replace `MAX(CASE WHEN ...)` with a client role flag; account tenant row and selected farm must still match; bypass applies only to suspended/cancelled login gate, not blanket record access.
6. **The subscription lifecycle owns `past_due`.** Existing `init.php` can invoke `subscription_lifecycle_request_guard()` before route-level `requireLogin()`; it may transactionally change farm status, append history, rotate session/restrict billing recovery for Farm Admin, or clear normal identity for others. Do not classify past_due as invalid_account or preempt the lifecycle service.
7. **Early redirects preempt a route-local emitter.** Session timeout in config.php and lifecycle guard in bootstrap may `header();exit()` before the proposed API executes. The previous dormant `b12_auth_early_bootstrap_seam.php` only simulates canonical completion order; actual wiring needs an early route format allowlist and shared transport handling without cloning account or lifecycle SQL.
8. **Failure boundary remains open.** PDO connection failure currently `die()`s with plain text. A future strict JSON API must prevent confusing HTML/plaintext successful fetch parses while preserving no access and no sensitive database detail disclosure.

## Proposed controlled integration order (NONE performed here)

1. Define exact account/session input normalization and a **single canonical** fail-closed malformed-session policy. Add a test fixture comparing old vs proposed decision events for boundary time 900/901 seconds, absent LAST_ACTIVITY, malformed/future timestamps, zero/negative farm ID, account projection and owner bypass.
2. Extract response *transport* at the existing canonical denial branches, without changing their decision, role-refresh, session teardown, or billing side effects. Prove HTML redirects remain identical for all other routes; only the explicitly registered future B12 endpoint should become JSON.
3. Coordinate the early session timeout branch (before PDO) with the lifecycle guard (after PDO) using the existing shared response policy; guard exactly one response/termination and fail closed if recovery/cleanup fails.
4. Verify existing `requireLogin()` account JOIN still operates once with tenant-bound placeholders. Do not wire the future B12 API or change active `config.php` until isolated HTTP/session tests validate deleted user, suspended farm, past_due Farm Admin recovery, past_due non-admin denial, missing farm, and operational View/Edit permissions.
5. Continue separate B12 schema gates: actual authorized read-only legacy audit; NULL-cycle identity/SET NULL collision policy; guarded revision migration; all Layer/Broiler/Ruminant CAS save/delete and continuity revision bumps; separately authorized disposable MariaDB/browser concurrency retest.

## Evidence/release hold

No active source files or DB schema changed by this audit. Existing R2 **synthetic schema-only** run `38301126240` remains SUCCESS; original B12 concurrent stale-write run `38233373699` remains FAILED (`TWO_STALE_COMMITS`) and must never be silently rerun. D12 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED.

**One fix, fit it all:** central auth and response services; shared ownership; no duplicated account/billing SQL; thin routes; exact branch/HEAD guards and remote semantic review.
