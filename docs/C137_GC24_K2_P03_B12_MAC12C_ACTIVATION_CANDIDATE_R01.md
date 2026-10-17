# C137 B12 — MAC-12C isolated activation candidate R01

## Scope and authority

This is an isolated, source-only activation candidate for one future
preliminary synthetic Layer edit/edit RC01 experiment.

It is NOT an execution authorization, certification, deployment approval,
migration approval or production cutover.

Protected baseline:
f9701c1c62d9d5981c3b10aeab803fd23d2b0d1c

Protected source branch:
c137-gc24-k2p03-b12-mac12b1-disabled-rc01

Isolated candidate branch:
c137-gc24-k2p03-b12-mac12c-activation-candidate-r01

The Python and PHP execution fuses are enabled in this candidate only.
The protected baseline retains both hard-disabled fuses.

## Candidate source identities

Python supervisor Git blob:
0f3f7c15f4e5a202f93ae613a697fc601afddb43

PHP RC01 harness Git blob:
cf24dbd54be7b60bee39b89c3d8e0904d5d08cb3

Trusted RSA public-PEM SHA-256:
d7a8c59c31898eedb4a894769f7c1ddc145d38a560632cfa0d1683a1935e8735

The final candidate commit SHA must be obtained from Git after committing.
No approval manifest may reuse the disabled baseline commit or old blobs.

## Verification evidence

- Mac isolated candidate source: five exact prepared code blobs PASS.
- Python syntax verification: PASS.
- Offline regressions: 50/50 PASS, no reported failures or skips.
- Separately authorized PHP 8.2 Docker syntax-only lint: PASS.
- Lint Docker networking: none.
- Temporary lint container cleanup: PASS.
- Final read-only local Git diff review: PASS.
- No RC01 controller or workers executed.
- No fixture data was inserted or updated.
- No operational approval manifest or signature was created.
- Local replay-protection claims directory remained empty.
- No GitHub candidate branch or commit existed before local commit preparation.

All evidence is limited to the verified source state and audit time.
Any future source changes require renewed verification.

## Explicit execution restrictions

DO NOT invoke the Python supervisor or PHP harness directly.

DO NOT create or sign a MAC-12C operational approval during this stage.

DO NOT create synthetic fixtures, execute workers, trigger migrations,
contact production/staging/AWS systems or access customer records.

DO NOT deploy this candidate to cPanel, live Renee AgriSuite,
production, staging or any commercial environment.

A locally enabled source fuse does not constitute authorization to run.

## Mandatory prerequisites for any future MAC-12C operation

A separate explicit one-use human authorization must identify:

- Exact final Git commit, supervisor blob and PHP harness blob.
- The one preliminary synthetic Layer edit/edit RC01 experiment only.
- The isolated disposable database renee_b12_disposable.
- Permitted fixture writes, two-worker race, independent host observer,
  cleanup and quarantine behavior.
- A fresh unique one-use run ID and short-lived signed manifest.
- Exact Docker image, container, volume and internal network identities.

The database must be rechecked immediately before any future execution:
69 tables, 886 columns, 193 referential constraints, three revision
columns, zero application rows, zero migration history, and no unexpected
schema objects.

The approval must be RSA-SHA256 signed with the separately protected
private key and verified against the pinned trusted public PEM.

The private key must never enter Git, chat, Docker or the worker runtime.

One-use claim markers must not be deleted or reused after an attempt.
Failures must not be automatically retried. Preserve evidence and
quarantine any uncertain cleanup state for separate review.

## Permanent scope exclusions

MAC-12C execution: NOT AUTHORIZED by this source commit.
Migration 092: NOT AUTHORIZED.
150/200 VU capacity tests: NOT AUTHORIZED.
Official repaired RC01–RC14 certification: 0/14.
B12 production cutover: HARD OFF.
