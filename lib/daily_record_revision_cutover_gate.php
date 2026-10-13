<?php
declare(strict_types=1);

/**
 * B12 source-only cutover safety fuse — ALWAYS OFF in this branch.
 *
 * No environment variable, HTTP parameter, session value, role, or runtime
 * constant may turn this on. Replacing this hard-disabled implementation
 * requires its own coordinated, reviewed commit covering ALL seven writer
 * paths, the authenticated browser/API, the verified three-table DDL and
 * authorized multi-process MariaDB testing.
 *
 * Until then, three staged route branches must not call dormant CAS helpers.
 */
function daily_record_revision_cutover_enabled(): bool
{
    return false;
}
