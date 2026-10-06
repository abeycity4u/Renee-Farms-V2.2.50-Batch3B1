# Renee AgriSuite v3.2 — Maximum-Capacity Environment Specification

Status: DESIGNED — PROVISIONING PENDING

Source capacity-framework commit:

`d6102141f5a9a11efc64dee6d6c425fa33cfb9ce`

## Purpose

This document defines the isolated infrastructure profile required before
executing Renee AgriSuite maximum-capacity testing.

The target must reproduce the current hosting account's measurable application
resource envelope closely enough for the result to be operationally useful,
while avoiding deliberate saturation testing on the production or shared
CloudLinux environments.

The objective is to establish:

- SAFE OPERATING CAPACITY; and
- SATURATION POINT.

The objective is not to crash the application.

## Authoritative current-host limits

The following limits were read directly from the hosting account's cPanel
Resource Usage interface.

### CPU / SPEED

- Limit: 200%
- Capacity-test interpretation: approximately 2 CPU cores of scheduling
  capacity.
- Isolated application target: 2 vCPU.

### Physical memory / PMEM

- Limit: 2 GB.
- Isolated application target memory ceiling: 2 GB.

This is the CloudLinux account/application-process memory envelope.

It must not automatically be interpreted as the MariaDB server's own memory
limit because the shared database service may run outside the account LVE.

### Entry Processes / EP

- Limit: 30.

CloudLinux Entry Processes are not identical to PHP-FPM worker count.

For the isolated target, the practical concurrency analogue is:

- maximum application/PHP request workers: approximately 30;
- exact configured worker ceiling must be recorded in test evidence.

The capacity report must describe this as an EP-equivalent application
concurrency control, not as a claim that PHP-FPM workers and CloudLinux EP are
identical metrics.

### NPROC

- Limit: 200.
- Isolated application process ceiling: 200 processes where the environment
  supports an enforceable process limit.

### Disk throughput

- I/O limit: 50 MB/s.

The isolated application target should enforce or select storage whose
application-visible throughput is approximately 50 MB/s where practical.

The actual enforcement mechanism and observed throughput must be recorded.

### IOPS

- Limit: 1024 IOPS.

The isolated application target should enforce or select storage approximating
1024 IOPS where practical.

The actual enforcement mechanism and observed IOPS must be recorded.

## Current application runtime compatibility baseline

Observed source/runtime baseline:

- architecture: Linux x86_64;
- current hosting kernel family: CloudLinux/LVE Linux 4.18;
- PHP CLI: 8.2.34;
- PHP SAPI observed from CLI: cli;
- PHP memory_limit: 1024M;
- PHP post_max_size: 1024M;
- PHP upload_max_filesize: 1024M;
- PHP max_file_uploads: 20;
- PHP max_input_vars: 2000;
- Composer metadata present;
- package.json absent;
- MariaDB client: 11.4.13;
- migration files observed: 95.

Required PHP capabilities observed on the existing environment include:

- apcu;
- bcmath;
- curl;
- dom;
- fileinfo;
- gd;
- imagick;
- intl;
- json;
- mbstring;
- mysqli;
- mysqlnd;
- openssl;
- PDO;
- pdo_mysql;
- session;
- SimpleXML;
- sodium;
- xml;
- xmlreader;
- xmlwriter;
- zip;
- zlib;
- Zend OPcache.

The isolated environment does not need to reproduce unrelated cPanel-specific
extensions if Renee AgriSuite does not depend on them.

## Application target specification

The isolated application target shall use:

- 2 vCPU;
- 2 GB application-host memory ceiling;
- Linux x86_64;
- PHP 8.2.x compatible with the GA source;
- required PHP extensions;
- Composer dependencies installed from composer.lock;
- HTTPS;
- approximately 30 simultaneous application/PHP workers;
- process ceiling approximately 200 where enforceable;
- approximately 50 MB/s application-visible disk throughput where enforceable;
- approximately 1024 IOPS where enforceable;
- measurable CPU utilization;
- measurable memory utilization;
- measurable process count;
- measurable PHP worker utilization;
- measurable disk throughput and IOPS.

The target must use the exact intended GA source commit for execution.

## Database topology

The capacity database must be:

- isolated from production;
- isolated from shared staging;
- populated only with synthetic/disposable Renee AgriSuite test data;
- separately measurable;
- free of production credentials;
- protected from external public access except where specifically required by
  the isolated application target.

The database should not be arbitrarily forced into the application's 2 GB PMEM
limit because the current CloudLinux PMEM measurement does not establish the
resource budget of the shared MariaDB server.

For the initial application-capacity series, provision the database with enough
resources that it is not intentionally made the first bottleneck.

Record:

- database vCPU;
- database memory;
- maximum connections;
- active connections;
- connection saturation;
- query latency;
- slow-query evidence;
- CPU and memory utilization.

If the database becomes the first measured bottleneck, that is valid evidence
and must be reported rather than hidden by increasing resources mid-tier.

## PHP/application concurrency equivalence

The target should begin with an application worker ceiling of approximately 30
to represent the current CloudLinux Entry Processes limit.

This equivalence must be treated as an engineering approximation.

Record at minimum:

- PHP-FPM or equivalent worker ceiling;
- active workers;
- idle workers;
- queued requests if available;
- worker exhaustion events;
- process count.

Do not silently increase the worker ceiling during a capacity tier.

Any worker-limit change creates a new environment profile and requires a new
test-series identifier.

## Application configuration safety

The isolated target must use:

- payment/billing mode TEST;
- no live Paystack transaction processing;
- no live Flutterwave transaction processing;
- outbound email disabled or redirected to a controlled sink;
- no production cron jobs;
- no production backup jobs;
- no production monitoring callbacks;
- no production database credentials;
- no production API secrets;
- no production object-storage credentials;
- synthetic/disposable farm data only.

Unexpected production connectivity is an immediate hard-stop condition.

## Data profile

The database must contain representative synthetic data sufficient to exercise:

- authentication;
- dashboard;
- inventory;
- reporting;
- poultry;
- ruminant;
- sales;
- expenses;
- financial allocation;
- profitability;
- other authenticated read surfaces included in the GA capacity workload.

No real customer records should be required.

## Load generator

The load generator must be a separate machine from the application target.

Initial generator profile:

- at least 2 vCPU;
- at least 2 GB RAM;
- k6 installed;
- no application or database workload;
- stable network path to the isolated target;
- no production credentials;
- no committed session secrets.

Generator CPU and memory must be monitored during each tier.

If the generator itself saturates, the tier is INVALID_TEST rather than an
application saturation result.

## Session requirements

Use multiple independent authenticated sessions created only for the isolated
environment.

Do not use a single PHP session for all virtual users.

Session credentials and cookies must never be committed to Git or retained in
published evidence.

## Initial tier sequence

Approved initial tiers:

- 25 VUs;
- 50 VUs;
- 75 VUs;
- 100 VUs;
- 125 VUs;
- 150 VUs;
- 200 VUs.

Each tier is a separate controlled execution.

The next tier requires manual review and authorization.

Do not run all tiers automatically.

## Application-health target

A healthy tier should remain within:

- HTTP failure rate < 1%;
- checks > 99%;
- p95 response time < 2 seconds;
- p99 response time < 5 seconds.

## Hard-stop conditions

Stop a tier when a sustained condition reaches any of:

- HTTP failure rate >= 2%;
- repeated HTTP 5xx responses;
- p95 response time >= 3 seconds;
- p99 response time >= 7.5 seconds;
- successful-check rate < 98%;
- database connection exhaustion;
- application/PHP worker exhaustion;
- process-limit exhaustion;
- memory pressure or OOM;
- sustained CPU saturation;
- application instability;
- load-generator saturation;
- unexpected production connectivity;
- discovery of real customer data;
- discovery of production payment or email integration.

The first hard-stop tier is evidence.

Do not continue until the server crashes.

## Required telemetry

Application target:

- CPU utilization;
- memory utilization;
- process count;
- PHP worker utilization;
- PHP queue if available;
- disk throughput;
- IOPS;
- HTTP status distribution;
- application errors.

Database:

- CPU utilization;
- memory utilization;
- active connections;
- maximum connections;
- slow-query evidence;
- connection errors.

Load generator:

- CPU utilization;
- memory utilization;
- k6 request throughput;
- request failure rate;
- checks;
- p50;
- p95;
- p99.

## Environment certification before first tier

The first 25-VU tier must not start until all of the following are verified:

- exact GA commit deployed;
- application target is isolated;
- database is isolated;
- synthetic data only;
- HTTPS operational;
- billing mode TEST;
- outbound production mail disabled/redirected;
- production cron absent;
- production credentials absent;
- application CPU limit approximately 2 vCPU;
- application memory ceiling approximately 2 GB;
- application worker ceiling approximately 30;
- application process ceiling approximately 200 where enforceable;
- disk throughput/IOPS profile documented;
- telemetry operational;
- external k6 generator operational;
- multiple independent test sessions available;
- baseline authenticated requests succeed;
- baseline database connectivity succeeds;
- no production connectivity detected.

## Current status

CAPACITY_FRAMEWORK=READY

CAPACITY_ENVIRONMENT_SPEC=COMPLETE

CAPACITY_ENVIRONMENT_PROVISIONING=PENDING

CAPACITY_ENVIRONMENT_CERTIFICATION=PENDING

MAX_CAPACITY_EXECUTION=NOT_STARTED

MAX_CAPACITY_BREAKING_POINT=NOT_ESTABLISHED

PRODUCTION_MAX_LOAD_TEST=PROHIBITED

SHARED_HOST_BREAKING_POINT_TEST=PROHIBITED
