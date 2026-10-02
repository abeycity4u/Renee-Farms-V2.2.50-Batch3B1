# Renee AgriSuite v3.2 — Isolated Staging Execution Runbook

Purpose: create and certify an isolated staging target for the remaining GA runtime assurance work. This runbook is intentionally parameterized so it can be used on cPanel or another Linux host without embedding secrets in Git.

## 1. Release authority

Repository:
`abeycity4u/Renee-Farms-V2.2.50-Batch3B1`

Active GA branch:
`v320-ga-hardening`

Expected GA HEAD at the time this runbook was authored:
`ff3956396008cd423cbe1f164736c0e761a18f66`

Protected known-good baseline:
`10041da3a3c4e4a7b8101f8183c243d056c7b128`

Before using this runbook, replace `EXPECTED_GA_HEAD` with the current remote GA HEAD if documentation-only commits have advanced it. Never silently deploy an unexpected commit.

## 2. Safety boundary

This procedure must not target production.

Required isolation before any destructive or adversarial test:

- separate document root;
- separate database and database user;
- staging-only application URL;
- no production uploads directory mount/symlink;
- payment providers in test mode;
- staging/test SMTP policy;
- synthetic or sanitized data;
- disposable test users;
- separate backup/restore location;
- visible staging marker;
- explicit rollback snapshot.

Stop immediately if the staging runtime resolves to the production database, production uploads path, production payment mode, or production hostname.

## 3. Operator variables

Set these interactively in the staging shell. Do not commit real credentials.

```bash
export REPO_DIR="$HOME/renee-ga-staging-src"
export STAGING_DOCROOT="$HOME/public_html_staging"
export STAGING_BACKUP_ROOT="$HOME/renee-ga-staging-backups"
export EXPECTED_BRANCH="v320-ga-hardening"
export EXPECTED_GA_HEAD="ff3956396008cd423cbe1f164736c0e761a18f66"
export STAGING_URL="https://staging.example.invalid"
export STAGING_DB_NAME="CHANGE_ME_STAGING_DB"
export STAGING_DB_USER="CHANGE_ME_STAGING_DB_USER"
export STAGING_DB_HOST="localhost"
```

Set `DB_PASS` only in the runtime secret/environment mechanism used by the staging host. Do not echo it.

## 4. Source checkout and branch guard

```bash
set -euo pipefail
umask 027

if [ ! -d "$REPO_DIR/.git" ]; then
  git clone https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1.git "$REPO_DIR"
fi

cd "$REPO_DIR"
git fetch --prune origin

git checkout "$EXPECTED_BRANCH"
git reset --hard "origin/$EXPECTED_BRANCH"

echo "BRANCH=$(git branch --show-current)"
echo "LOCAL_HEAD=$(git rev-parse HEAD)"
echo "REMOTE_HEAD=$(git rev-parse origin/$EXPECTED_BRANCH)"
git status --short

[ "$(git branch --show-current)" = "$EXPECTED_BRANCH" ]
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/$EXPECTED_BRANCH)" ]
[ "$(git rev-parse HEAD)" = "$EXPECTED_GA_HEAD" ]
[ -z "$(git status --porcelain)" ]
```

If the last HEAD guard fails because the GA branch advanced, stop and review the semantic diff before changing `EXPECTED_GA_HEAD`.

## 5. Source-side certification before staging deployment

```bash
cd "$REPO_DIR"
bash scripts/run_v320_ga_regression.sh
composer validate --no-check-publish --no-interaction
composer audit --locked --no-dev --no-interaction
```

Expected: all source regression/security contracts pass and Composer reports no blocking advisory result.

## 6. Pre-deployment backup of the staging target

This is a staging rollback backup, not a production backup.

```bash
mkdir -p "$STAGING_BACKUP_ROOT"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$STAGING_BACKUP_ROOT/pre-ga-$STAMP"
mkdir -p "$BACKUP_DIR"

if [ -d "$STAGING_DOCROOT" ]; then
  tar -C "$STAGING_DOCROOT" -czf "$BACKUP_DIR/files.tgz" .
fi

echo "STAGING_BACKUP_DIR=$BACKUP_DIR"
```

Create a database dump using the host's approved secret mechanism. Do not place the password on the command line or copy it into evidence.

Example when a protected MySQL option file is available:

```bash
mysqldump --defaults-extra-file="$HOME/.my-staging.cnf" \
  --single-transaction --routines --triggers \
  "$STAGING_DB_NAME" | gzip > "$BACKUP_DIR/database.sql.gz"
```

## 7. Deploy GA source to the staging document root

Do not copy `.git`, runtime logs, secrets, or production uploads.

```bash
mkdir -p "$STAGING_DOCROOT"

rsync -a --delete \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='logs/' \
  --exclude='uploads/' \
  "$REPO_DIR/" "$STAGING_DOCROOT/"

cd "$STAGING_DOCROOT"
composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
```

Preserve a separate staging `uploads/` directory if the application requires it. Never point it at production.

## 8. Staging environment requirements

Configure through the staging host's environment/secret mechanism:

```text
DB_HOST=<staging db host>
DB_NAME=<staging db name>
DB_USER=<staging db user>
DB_PASS=<secret>
APP_TIMEZONE=Africa/Lagos
PLATFORM_PUBLIC_BASE_URL=<staging https url>
BILLING_PUBLIC_BASE_URL=<staging https url, compatibility only if still required>
```

Payment-specific variables must select TEST/sandbox mode only. SMTP must use a staging/test recipient policy.

Do not hard-code any secret in `.htaccess`, Git, screenshots, reports, or shell history when a secret store/environment facility is available.

## 9. Prove environment isolation

Before security/load/DR execution, collect non-secret evidence:

```bash
cd "$STAGING_DOCROOT"

echo "DEPLOYED_HEAD=$EXPECTED_GA_HEAD"
echo "STAGING_URL=$STAGING_URL"
echo "STAGING_DB_HOST=$STAGING_DB_HOST"
echo "STAGING_DB_NAME=$STAGING_DB_NAME"
echo "STAGING_DOCROOT=$STAGING_DOCROOT"
```

Also prove through the hosting/environment configuration that:

- production DB name is different;
- production document root is different;
- payment mode is test;
- staging SMTP cannot send arbitrary customer mail;
- uploads are isolated;
- staging cookie/domain scope is separate.

Do not print passwords, provider secrets, SMTP credentials, session IDs or tokens.

## 10. Database preparation

Use a clean/sanitized staging database. Apply only the migrations already required by the GA source if the isolated target is behind the certified schema.

Do not re-run historical destructive billing or tenant certification merely to create evidence.

After schema preparation, verify the existing migration marker state and run only the focused staging checks required by the release checklist.

## 11. Synthetic tenant fixtures

Create at least:

- Tenant A — Farm Admin + specialist users;
- Tenant B — Farm Admin + specialist users;
- one disposable user specifically for password reset/session revocation;
- one disposable user for admin-password-change revocation;
- disposable inventory, sales, expense, cycle, poultry and ruminant records;
- TEST-mode billing fixture only when provider QA is required.

Do not reuse real customer passwords or tokens.

## 12. Playwright execution

```bash
cd "$STAGING_DOCROOT/tests/e2e"
npm ci
npx playwright install --with-deps chromium
```

Provide staging-only environment variables described in `tests/e2e/README.md`, then execute:

```bash
npx playwright test --project=chromium
```

Run the destructive/disposable password/session test only after confirming the fixture is not a production user.

Expected evidence:

- HTML report;
- concise PASS/FAIL list;
- screenshots/traces only for failures or material evidence;
- no secret-bearing storage-state files in Git.

## 13. Runtime security / DAST

Against staging only, execute the acceptance matrix from `CHATGPT_WORK_HANDOFF.md`:

- two-tenant IDOR;
- direct-route authorization;
- role escalation attempts;
- CSRF negative tests;
- XSS/SQLi input probing;
- account-enumeration checks;
- credential token replay/expiry/supersession;
- stale-session revocation;
- headers/cookies/CSP;
- PDF path/resource/error probes;
- TEST webhook signature/replay/mismatch.

For each finding: prove first, patch only the GA branch, add regression coverage, run CI, redeploy staging, and retest the affected boundary.

## 14. Load test

Use multiple pre-authenticated synthetic sessions.

```bash
cd "$STAGING_DOCROOT"
k6 run tests/load/k6_authenticated_read.js
```

Execute the planned 10 -> 25 -> 50 -> 100 VU ramp while simultaneously recording:

- p50/p95/p99;
- throughput;
- error rate;
- CPU;
- memory;
- PHP workers;
- MySQL connections;
- slow queries;
- lock waits/deadlocks.

Do not run load tests against production.

## 15. Disaster-recovery drill

Use a separate clean restore target, not the active staging database.

After restoring files + database + environment:

```bash
cd <clean-restore-docroot>
GA_DR_ISOLATED_RESTORE=YES php scripts/ga_dr_post_restore_verify.php
```

Record restore start/end, source-backup age, RPO, RTO, verifier output and representative application checks.

## 16. Monitoring validation

Follow `MONITORING_OPERATIONS.md` and prove:

- controlled application error reaches collector;
- 5xx and latency telemetry exists;
- failed-login/security signal exists without sensitive values;
- test alert reaches the intended channel/person;
- PHP/MySQL/host resource metrics are visible;
- backup freshness and TLS expiry are monitored;
- captured events redact credentials/tokens/sessions.

## 17. Runtime evidence bundle

Return an evidence package containing:

```text
GA_HEAD=<exact commit>
STAGING_URL=<non-secret staging identity>
ENVIRONMENT_ISOLATION=PASS|FAIL
PLAYWRIGHT=PASS|FAIL|BLOCKED
TENANT_IDOR=PASS|FAIL|BLOCKED
PRIVILEGE_ESCALATION=PASS|FAIL|BLOCKED
DAST=PASS|FAIL|BLOCKED
LOAD=PASS|FAIL|BLOCKED
DR=PASS|FAIL|BLOCKED
MONITORING=PASS|FAIL|BLOCKED
EXTERNAL_PENTEST=PASS|FAIL|BLOCKED|NOT_RUN
RPO=<measured value>
RTO=<measured value>
UNRESOLVED_FINDINGS=<count + references>
FINAL_REMOTE_HEAD=<sha>
WORKTREE=CLEAN|DIRTY
```

Attach artifact locations, not secrets.

## 18. Release gate

Do not merge or deploy GA to production merely because CI and repository review passed.

Production release remains blocked until the mandatory runtime evidence is reviewed and any Critical/High findings are either remediated and retested or explicitly dispositioned through the formal release process.

After runtime evidence returns, update `GA_STATUS_MATRIX.md` and `PRODUCTION_READINESS_V3.2.md`, perform one final remote semantic diff from the protected baseline, and only then present the production deployment decision to the user.
