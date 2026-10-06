# Renee AgriSuite v3.2 — Maximum-Capacity Provisioning Plan

Status: DESIGNED — INFRASTRUCTURE NOT YET PROVISIONED

Target GA commit:

`cf8b76c81437f34aa72553e4b664b323018d9554`

## Purpose

This plan defines the isolated infrastructure topology required to execute
Renee AgriSuite maximum-capacity testing safely and repeatably.

The environment consists of three separate nodes:

1. application target;
2. isolated database;
3. external k6 load generator.

No node may use production credentials, production data, production payment
configuration, or production scheduled jobs.

## Node A — application target

Role:

- Renee AgriSuite application;
- HTTPS termination;
- PHP application runtime;
- application telemetry.

Required resource envelope:

- 2 vCPU;
- 2 GB application memory ceiling;
- approximately 30 simultaneous PHP/application workers;
- approximately 200 total processes where enforceable;
- approximately 50 MB/s disk throughput where enforceable;
- approximately 1024 IOPS where enforceable;
- Linux x86_64;
- PHP 8.2.x;
- Composer dependencies from composer.lock.

Required PHP capabilities:

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

Required telemetry:

- CPU utilization;
- memory utilization;
- process count;
- PHP worker active/idle counts;
- request queue where available;
- disk throughput;
- IOPS;
- HTTP status distribution;
- application/PHP errors.

Application node must not host k6.

## Node B — isolated database

Role:

- synthetic Renee AgriSuite capacity-test database only.

Initial reference profile:

- 2 vCPU minimum;
- 4 GB RAM recommended starting allocation;
- MariaDB 11.4.x compatible;
- private network access only;
- no public database port exposure;
- enough storage for representative synthetic data;
- separately measurable CPU/memory/connection/query telemetry.

The database profile is intentionally not forced into the application node's
2 GB CloudLinux PMEM envelope because the current shared-host database service
is not proven to operate inside the user's LVE.

The database must not be scaled during an individual capacity tier.

If the database becomes the first measured bottleneck, record that result and
stop the tier. Do not hide it by changing resources mid-run.

Required telemetry:

- CPU utilization;
- memory utilization;
- active connections;
- maximum connections;
- connection errors;
- query latency;
- slow queries;
- lock waits/deadlocks where available.

## Node C — external k6 load generator

Role:

- generate controlled authenticated capacity workload only.

Required profile:

- at least 2 vCPU;
- at least 2 GB RAM;
- k6 installed;
- no application services;
- no database services;
- no production credentials;
- stable network path to Node A.

Monitor:

- generator CPU;
- generator memory;
- k6 throughput;
- HTTP failure rate;
- checks;
- p50;
- p95;
- p99.

If Node C saturates, classify the tier as INVALID_TEST.

## Network topology

Permitted flows:

- Node C -> Node A: HTTPS 443;
- Node A -> Node B: MariaDB 3306 over private network;
- administrator -> nodes: SSH only from explicitly authorized administration
  addresses or provider console.

Prohibited flows:

- public internet -> Node B: prohibited;
- capacity environment -> production database: prohibited;
- capacity environment -> production SMTP: prohibited;
- capacity environment -> production backup storage: prohibited;
- capacity environment -> production monitoring callbacks: prohibited.

Node B should accept database traffic only from Node A's private network
identity.

## Existing Renee AgriSuite configuration contract

Use the application's existing shared deployment variables.

Database:

- DB_HOST
- DB_NAME
- DB_USER
- DB_PASS

Timezone:

- APP_TIMEZONE=Africa/Lagos

Public URL:

- PLATFORM_PUBLIC_BASE_URL=https://<isolated-capacity-host>
- BILLING_PUBLIC_BASE_URL=https://<isolated-capacity-host>

`PLATFORM_PUBLIC_BASE_URL` is authoritative.
`BILLING_PUBLIC_BASE_URL` is retained only as the application's existing
compatibility fallback.

Billing:

- BILLING_PAYMENT_MODE=test

Provider credentials, if provider-specific TEST billing behavior is included:

- PAYSTACK_TEST_SECRET_KEY
- FLUTTERWAVE_TEST_SECRET_KEY
- FLUTTERWAVE_TEST_WEBHOOK_HASH

Only dedicated TEST credentials may be supplied.

Do not configure any live payment credential slot.

Mail:

- PLATFORM_MAIL_ENABLED=disabled

No production SMTP username or password is required while mail remains
disabled.

If a later test explicitly requires outbound email, use a controlled test mail
sink and a separate environment profile.

## Application source deployment

Deploy exact commit:

`cf8b76c81437f34aa72553e4b664b323018d9554`

Deployment must:

- exclude `.git`;
- exclude production secrets;
- exclude production uploads;
- exclude production runtime logs;
- install Composer dependencies from composer.lock;
- preserve isolated uploads;
- create no production cron entries.

## Database preparation

Use an empty isolated database and prepare only the schema required by the
current GA source.

The database must then be populated with representative synthetic data.

Do not copy the live production database.

Do not copy real customer records.

Required synthetic coverage:

- Tenant A;
- Tenant B;
- Farm Admin accounts;
- specialist role accounts;
- Viewer;
- Sales Representative;
- poultry data;
- ruminant data;
- inventory data;
- sales data;
- expenses;
- production cycles;
- financial allocations;
- profitability data;
- TEST billing fixtures where required.

## Session pool

Create multiple independent authenticated sessions on the isolated target.

Do not reuse production sessions.

Do not use one PHP session for all virtual users.

Session cookies must remain outside Git and published evidence.

## Scheduled jobs

Do not install or enable production scheduled workers, including:

- credential-delivery worker;
- subscription lifecycle worker;
- production backup jobs;
- production monitoring jobs;
- production RPO jobs.

Capacity execution should begin with application-request workload only.

## Environment-certification sequence

Before the first 25-VU test:

1. confirm all three nodes are independent of production;
2. verify exact GA commit;
3. verify Node A CPU/memory/process/worker limits;
4. verify Node B is private and synthetic;
5. verify Node C is separate from Node A;
6. verify HTTPS;
7. verify billing mode TEST;
8. verify mail disabled;
9. verify production cron absent;
10. verify no production credentials;
11. verify telemetry on all three nodes;
12. verify multiple independent sessions;
13. verify representative authenticated baseline requests;
14. verify isolated DB connectivity;
15. verify no production connectivity.

Only after all certification checks pass may the 25-VU tier be authorized.

## Capacity execution progression

The sequence remains:

- 25 VUs;
- 50 VUs;
- 75 VUs;
- 100 VUs;
- 125 VUs;
- 150 VUs;
- 200 VUs.

Each tier is separately authorized.

No automatic progression is permitted.

## Current status

CAPACITY_FRAMEWORK=READY

CAPACITY_ENVIRONMENT_SPEC=COMPLETE

CAPACITY_PROVISIONING_PLAN=COMPLETE

CAPACITY_INFRASTRUCTURE=NOT_PROVISIONED

CAPACITY_ENVIRONMENT_CERTIFICATION=PENDING

MAX_CAPACITY_EXECUTION=NOT_STARTED

MAX_CAPACITY_BREAKING_POINT=NOT_ESTABLISHED

PRODUCTION_MAX_LOAD_TEST=PROHIBITED

SHARED_HOST_BREAKING_POINT_TEST=PROHIBITED
