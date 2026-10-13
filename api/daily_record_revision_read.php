<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/init.php';

/**
 * B12 staged authenticated, read-only scope/exact Daily Record endpoint.
 *
 * Deliberately unavailable while the shared cutover fuse remains hard-off.
 * The legacy GET endpoints remain unchanged. No schema inspection or
 * tenant-record SELECT is possible in the disabled branch. A separately
 * reviewed coordinated cutover must activate all writers and browser clients
 * and certify the record_revision migration before enabling this endpoint.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/../lib/daily_record_revision_cutover_gate.php';

requireLogin();
require_http_method('GET');

if (!daily_record_revision_cutover_enabled()) {
    send_json([
        'success' => false,
        'code' => 'revision_cutover_disabled',
        'error' => 'Revision-aware Daily Record editing is not yet available.',
    ], 503);
}

require_once __DIR__ . '/../lib/daily_record_revision_api_adapter.php';
$result = daily_record_revision_api_handle(
    $pdo,
    'GET',
    $_GET
);
send_json($result['body'], $result['http_status']);
