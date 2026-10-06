# Renee AgriSuite v3.2 — Maximum Capacity Test Evidence

Status: TEMPLATE — COMPLETE ONE COPY PER EXECUTED TIER

## Test identity

- Date/time UTC:
- Source commit:
- Capacity environment:
- Environment class: ISOLATED
- Load-generator host:
- k6 version:
- Run ID:
- VU tier:
- Hold duration:

## Target safety

- Production target: NO
- Shared-host staging target: NO
- Payment mode: TEST
- Production mail: DISABLED / REDIRECTED
- Production cron: DISABLED
- Synthetic/disposable data only: YES

## Environment specification

- vCPU / CPU quota:
- Memory:
- PHP worker/process limit:
- Database connection limit:
- Disk/storage:
- Web server:
- PHP version:
- Database version:

## Pre-run baseline

- HTTP status:
- TTFB:
- Total response time:
- CPU:
- Memory:
- PHP workers:
- DB connections:

## Load result

- Classification: STABLE / DEGRADED / SATURATED / ABORTED_FOR_SAFETY / INVALID_TEST
- Requests:
- Requests/sec:
- HTTP failure rate:
- Successful checks:
- p50:
- p95:
- p99:
- HTTP 5xx count:
- Maximum CPU:
- Maximum memory:
- Maximum PHP workers/processes:
- Maximum DB connections:
- Slow-query evidence:
- OOM/resource-limit event: YES / NO

## Threshold evaluation

- HTTP failure rate < 1%:
- Checks > 99%:
- p95 < 2 seconds:
- p99 < 5 seconds:

Hard-stop observations:

- HTTP failure rate >= 2%:
- repeated HTTP 5xx:
- p95 >= 3 seconds:
- p99 >= 7.5 seconds:
- checks < 98%:
- DB connection exhaustion:
- PHP worker/process exhaustion:
- memory/OOM:
- sustained CPU saturation:
- application instability:

## Recovery

- Load returned to zero:
- Post-run HTTP baseline:
- Login verified:
- DB connectivity verified:
- PHP workers returned to normal:
- Persistent fault detected: YES / NO

## Tier decision

- Current tier:
- Result:
- Previous stable tier:
- Next tier authorized: YES / NO
- Reason:

Do not automatically authorize the next tier.

## Final capacity interpretation

Complete only after the capacity series is finished.

- Highest repeatably stable tier:
- Stable requests/sec:
- First degraded tier:
- First saturation tier:
- Recommended operating ceiling:
- Safety margin:
- Maximum-capacity breaking point established: YES / NO

## Evidence files

- k6 run log:
- k6 summary JSON:
- pre-run baseline:
- post-run baseline:
- infrastructure metrics:
- relevant application/server logs:

No passwords, session cookies, CSRF tokens or provider/database secrets belong
in this evidence document.
