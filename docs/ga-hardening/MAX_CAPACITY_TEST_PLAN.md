# Renee AgriSuite v3.2 — Safe Maximum-Capacity Test Plan

Status: DESIGNED — EXECUTION PENDING ISOLATED CAPACITY ENVIRONMENT

## Purpose

The goal is to establish two separate operational figures:

1. SAFE OPERATING CAPACITY — the highest sustained workload that remains
   comfortably inside the agreed latency, error-rate and resource limits.

2. SATURATION POINT — the first tested workload where one or more defined
   stop conditions are reached.

The objective is not to crash the application.

## Prior evidence

A bounded authenticated read-load certification has already completed
successfully in isolated staging using a 10 → 25 → 50 → 100 VU ramp.

That evidence remains valid for the tested envelope.

It does not establish the maximum-capacity breaking point.

## Environment decision

The current staging installation resides on shared CloudLinux hosting.

The account is identified as LVE 1481, but account-level CPU, memory, PID and
throttling counters are not readable from the user shell.

Because whole-host CPU/load/memory values include unrelated tenants, they
cannot reliably establish Renee AgriSuite's saturation point.

Therefore a deliberate breaking-point test must not use the shared-host
environment as its saturation target.

## Required capacity-test environment

Use a temporary isolated environment with:

- Renee AgriSuite GA source at the intended test commit;
- isolated application filesystem;
- isolated database;
- synthetic/disposable farm data;
- no production credentials;
- payment mode TEST;
- outbound mail disabled or redirected;
- no production cron jobs;
- HTTPS;
- resource limits known and measurable;
- CPU metrics;
- memory metrics;
- process/PHP-worker metrics;
- database connection metrics;
- disk/I/O visibility where available.

The environment should approximate the intended production resource profile
closely enough for the result to be operationally useful.

## Load-generator isolation

The load generator must run on a machine separate from the application target.

Do not generate maximum-capacity load from the application server itself.

Approved generator families include:

- k6;
- equivalent controlled HTTP load tooling with percentile/error metrics.

Secrets and staging credentials must not be committed to Git.

## Workload model

Capacity testing should begin with authenticated read-heavy operations because
they are repeatable and non-destructive.

Representative paths:

- dashboard;
- inventory;
- reporting;
- other authenticated read surfaces already covered by the GA load harness.

A later controlled write workload may be executed only against disposable
synthetic records.

## Session model

Use multiple independent authenticated staging sessions.

Do not use one PHP session for every virtual user because PHP session locking
can serialize requests and produce a misleading capacity result.

## Progressive ramp

Start below the previously certified envelope and increase in controlled tiers.

Initial proposed tiers:

- 25 VUs
- 50 VUs
- 75 VUs
- 100 VUs
- 125 VUs
- 150 VUs
- 200 VUs

Each tier must be sustained long enough for latency and resource behavior to
stabilize before proceeding.

Do not automatically proceed to the next tier after a stop condition.

Additional tiers above 200 VUs may only be selected after reviewing the
preceding evidence.

## HTTP acceptance targets

Healthy operating target:

- HTTP failure rate < 1%;
- checks > 99%;
- p95 response time < 2 seconds;
- p99 response time < 5 seconds.

These retain the thresholds used by the existing GA bounded-load harness.

## Hard stop conditions

Stop the ramp when any of the following is sustained rather than transient:

- HTTP failure rate >= 2%;
- repeated HTTP 5xx responses;
- p95 response time >= 3 seconds;
- p99 response time >= 7.5 seconds;
- successful-check rate < 98%;
- database connection exhaustion;
- PHP-worker/process exhaustion;
- memory pressure or OOM event;
- sustained CPU saturation at the environment limit;
- application instability;
- unexpected production connectivity;
- real customer data is discovered;
- payment or email production integration is discovered.

A hard-stop result is evidence, not a reason to continue until total failure.

## Safe operating capacity calculation

The safe operating capacity is not the first failing tier.

After the first saturation/degradation tier is identified:

1. stop load;
2. verify target recovery;
3. examine the previous stable tier;
4. if necessary test one intermediate tier;
5. identify the highest repeatably stable sustained tier;
6. retain safety margin below the measured saturation point.

The documented production planning figure should distinguish:

- measured stable VUs;
- measured request throughput;
- measured saturation tier;
- recommended operating ceiling.

## Recovery verification

After every saturation stop:

- return VUs to zero;
- verify HTTP 200 baseline;
- verify normal login;
- verify database connectivity;
- verify no stuck PHP workers/processes;
- verify error rate returns to baseline;
- verify no persistent application fault was introduced.

## Evidence to retain

Record:

- source commit;
- environment specification;
- test-generator version;
- workload paths;
- session count;
- VU tier;
- test duration;
- request count;
- requests/second;
- failure rate;
- p50;
- p95;
- p99;
- CPU;
- memory;
- PHP/process utilization;
- DB connections;
- relevant slow-query evidence;
- stop-condition reason;
- recovery result.

Do not retain passwords, session cookies, CSRF tokens or other secrets.

## Result classifications

Use:

- STABLE
- DEGRADED
- SATURATED
- ABORTED_FOR_SAFETY
- INVALID_TEST

## Current status

BOUNDED_LOAD_TEST=COMPLETE

MAX_CAPACITY_TEST_DESIGN=COMPLETE

MAX_CAPACITY_EXECUTION=PENDING_ISOLATED_ENVIRONMENT

MAX_CAPACITY_BREAKING_POINT=NOT_ESTABLISHED

PRODUCTION_MAX_LOAD_TEST=PROHIBITED

SHARED_HOST_BREAKING_POINT_TEST=PROHIBITED
