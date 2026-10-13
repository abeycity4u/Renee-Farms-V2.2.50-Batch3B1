# B12 confirmed defect vs hypothetical schema risks — four-version source comparison

**2026-10-13 — GitHub source comparison, not deployed-database inspection.**

## Four pinned GitHub versions
| Branch | HEAD |
|---|---|
| `v320-renee-agrisuite-branding` | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| `v320-ga-hardening` | `246cbdfab3a8fb485bc33945b8149bc6ff8fa619` |
| `c137-gc24-k2p03-ci-mariadb-b12` | `39ab4dd71a896fa73e4f4513efa0bd1ab72263db` |
| `c137-gc24-k2p03-b12-schema-preflight-source` | `298322bd0629f8d6d5022380e38dcb4f3ae32db5` |

Branding -> GA: 58 commits ahead, 0 behind; neither Layer nor Broiler nor Ruminant writer file changed. GA -> failed B12: 187 commits ahead, 0 behind; Layer/Broiler/Ruminant writer files and schema still have identical blobs.

## Exact blobs
| Path | Branding | GA | Failed B12 | Latest B12 |
|---|---|---|---|---|
| `poultry/layers_daily_record.php` | `87b7c6ace9bd577ad2303ea4aa50312a780412d2` | same | same | same |
| `poultry/broiler_daily_record.php` | `2a797c7fec45d3f78e7b2f01cb72531b1c416699` | same | same | same |
| `ruminant/ruminant_daily_record.php` | `27c6755f0ef9491a7019f94b6a62b602e8e8dcaa` | same | same | `b77cb8c5222c597fadc2e03c03f5dd4c0583cf83` |
| `database_schema.sql` | `e77f287d62c5812a3d55dc23b5b20f5d167db389` | same | same | same |

**Interpretation:** Layer's demonstrated stale correction vulnerability is not introduced by branding -> GA; code remained identical. Working normal-mode user experience is consistent with an undetected concurrency race.

## Precisely established by original B12 MariaDB run

Run `38233373699` (2026-10-12), one disposable synthetic MariaDB 10.11 execution, failed `TWO_STALE_COMMITS` at `NO_SILENT_OVERWRITE`. Two actual independently connected PHP editors read the same earlier target Layer state and separately updated same farm/cycle/date. The second waited on target lock, then both committed, despite downstream stock/mortality/population/feed/Layer egg-sale reconciliation. See `docs/C137_GC24_K2_P03_CI_B12_FAILURE_REVIEW.md` and `tests/integration/k2p03_b12_real_mariadb.php`.

**Do not overgeneralize:** No authenticated browser flow or production customer data was tested, and no Broiler/Ruminant concurrent worker result exists. B12 remains uncured in the live paths.

## Ordered remediation — favor verified root cause

1. Preserve exact scope and dependency owner. The immutable browser-loaded `record_id` and `expected_revision` must be passed to the same shared authoritative EDIT service; never obtain `expected_revision` from POST-time SELECT.
2. Caller begins transaction before authoritative edit, locks the tenant-scoped exact row and rejects changed revision *before* any feed, stock, mortality, population or sales effects. Use the dormant `lib/daily_record_revision_authority.php` after verifying it against actual MariaDB schema and migrations; no page-local bypass.
3. Ensure revisions increment for downstream rewrites in `lib/daily_population_continuity.php`, and unify DELETE CAS across permitted endpoints before coordinated activation. Preserve current permission/CSRF and existing side effects.
4. On CREATE, existing nullable-cycle schema and cross-tenant cycle FK are potential risks identified in source; no claim of actual data collisions. Do not force a migration or auto-merge existing records until read-only actual schema/data evidence. Existing dormant farm-lock CREATE service only protects writers that participate in the same lock ordering.
5. Run a new authorized disposable MariaDB concurrency test with two editors, exactly one commit and one explicit conflict, no silent overwrite, and verified downstream ledger rollback. The original one-use B12 run cannot be rerun.
6. Complete browser/API and D12 gates, then reopen authorization for 150 VU. 200 VU remains separately gated.

**No protected systems changed by this report; source-only.** The next productive implementation must update the relevant shared write path and all participating callers together. A new contract/document alone is not concurrency remediation.
