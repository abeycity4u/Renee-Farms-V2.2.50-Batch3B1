# GA Monitoring & Operational Readiness Contract

Status: repository-side design complete; runtime deployment/alert delivery requires staging/infrastructure access.

## Objectives

Monitoring must detect application failures, security abuse, commercial failures and infrastructure saturation without recording passwords, credential/reset tokens, PHP session IDs, CSRF tokens, DB/SMTP/payment secrets or full payment-provider payloads.

## Application signals

Capture and aggregate:

- HTTP 500/fatal/unhandled exceptions;
- repeated 4xx authorization/CSRF failures by route class;
- slow requests and slow report/PDF generation;
- DB connection failures;
- queue/outbox delivery failures;
- application version/commit and environment tag.

Minimum alert: sustained HTTP 500 rate above normal baseline or any sharp increase immediately after deployment.

## Authentication/security signals

Monitor:

- repeated failed login attempts after the shared rate limiter;
- repeated password-reset/activation rate-limit events;
- invalid CSRF attempts on authenticated mutations;
- denied cross-tenant/object lookups where safely distinguishable internally;
- role/permission changes;
- account activation/reset failures by reason class, never raw token;
- stale-session revocations after password changes;
- destructive Farm Admin/Platform Owner actions.

Do not log submitted passwords, raw reset/activation tokens, session cookies or CSRF values.

## Commercial/billing signals

Monitor:

- rejected webhook authentication/signature checks;
- provider verification failures;
- unmatched/frozen-attempt lookup failures;
- duplicate/replay disposition events;
- paid-attempt dispatch/provisioning failures;
- seat top-up/reduction failures;
- refund/reconciliation failures;
- billing email delivery failure.

Log provider reference only when it is already an intended non-secret business identifier. Never log provider secret keys or raw signed webhook secrets.

## Infrastructure signals

Collect from staging/production platform:

- CPU utilization;
- memory utilization;
- disk usage/inodes;
- PHP worker saturation/queueing;
- MySQL active/max connections;
- DB size/growth;
- slow-query volume;
- backup success/failure and age of latest usable backup;
- TLS certificate expiry;
- filesystem/runtime log growth.

## Suggested event envelope

A centralized collector should normalize events to fields such as:

- `timestamp`
- `environment`
- `release_sha`
- `severity`
- `event_type`
- `route`
- `http_method`
- `farm_id` when appropriate and non-public
- `user_id` when appropriate and non-public
- `role_family`
- `request_correlation_id`
- `provider` for billing events
- `safe_error_class`

Never include secret/token/cookie values in the envelope.

## Alert classes

### Critical

- production unavailable;
- sustained 5xx spike;
- database unavailable;
- billing webhook authentication bypass indication;
- backup/restore capability lost;
- cross-tenant security event with evidence;
- disk exhaustion imminent.

### High

- payment/provisioning failure rate above baseline;
- repeated privilege/authorization anomalies;
- PHP worker or DB connection saturation;
- error surge after deployment;
- backup overdue.

### Medium

- elevated password-reset/login abuse;
- slow report/PDF generation;
- noncritical email delivery failures;
- rising slow-query volume.

## Runtime acceptance criteria for ChatGPT Work

Monitoring is not GA-certified until Work/staging proves:

1. a controlled synthetic application error reaches the central collector;
2. alert routing reaches the intended recipient/channel;
3. a safe failed-login burst creates the expected security signal without exposing credentials;
4. a safe invalid webhook test creates a rejection signal without logging secrets;
5. release SHA/environment are attached to events;
6. sensitive-value redaction is verified from captured examples;
7. alert flood/rate behavior is acceptable;
8. dashboard covers 5xx, latency, PHP workers, DB connections, CPU, memory, disk, backup age and commercial failures;
9. retention/access policy is documented;
10. monitoring failure itself has an external health signal.

## Production deployment rule

Do not make production the first validation target. Configure and prove collection/alerts in isolated staging, then deploy production credentials/destinations through environment configuration. No monitoring secret belongs in the repository.
