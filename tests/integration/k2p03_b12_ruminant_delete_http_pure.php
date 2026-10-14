<?php
declare(strict_types=1);
/**
 * B12 source-only Ruminant DELETE response policy tests.
 * No app bootstrap, request/HTTP emission, session, SQL, DB or migration.
 */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_delete_http.php';

$count = 0;
function b12_ruminant_http_check(bool $good, string $label): void
{
    global $count;
    if (!$good) throw new RuntimeException('FAIL: ' . $label);
    ++$count;
}

try {
    $xhr = [
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        'HTTP_ACCEPT' => 'application/json, */*',
    ];
    b12_ruminant_http_check(daily_record_revision_delete_is_ajax($xhr),
        'only explicit JSON AJAX POST can enter the future DELETE pipeline');
    foreach ([
        [], ['REQUEST_METHOD'=>'GET'],
        ['REQUEST_METHOD'=>'POST','HTTP_ACCEPT'=>'application/json'],
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'],
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest',
            'HTTP_ACCEPT'=>'text/html, */*'],
        ['REQUEST_METHOD'=>'DELETE','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest',
            'HTTP_ACCEPT'=>'application/json'],
    ] as $bad) {
        b12_ruminant_http_check(!daily_record_revision_delete_is_ajax($bad),
            'ordinary form/non-JSON/wrong-method requests are blocked');
    }
    $ok = daily_record_revision_delete_http_envelope(null, 19);
    b12_ruminant_http_check(
        $ok['status']===200 && $ok['body']['success']===true &&
        $ok['body']['code']==='deleted' && $ok['body']['record_id']===19,
        'confirmed delete echoes exact persisted ID'
    );
    foreach ([null, 0, -1] as $invalid) {
        try {
            daily_record_revision_delete_http_envelope(null, $invalid);
            throw new RuntimeException('Expected rejection for unconfirmed delete.');
        } catch (LogicException $expected) {
            b12_ruminant_http_check(true,
                'success cannot be generated without confirmed positive ID');
        }
    }
    $cases = [
        [
            new DailyRecordRevisionDeleteClientNotReady('unsafe caller'),
            409, 'revision_client_required',
        ],
        [
            new DailyRecordRevisionConflict('secret SQL WHERE ... tenant 7'),
            409, 'record_conflict',
        ],
        [
            new DailyRecordRevisionPermissionDenied('sensitive permission detail'),
            403, 'forbidden',
        ],
        [
            new InvalidArgumentException('raw user-provided record ID 5'),
            422, 'invalid_delete_request',
        ],
        [
            new RuntimeException('database credential / connection string'),
            500, 'delete_failed',
        ],
    ];
    foreach ($cases as [$error, $status, $code]) {
        $actual = daily_record_revision_delete_http_envelope($error);
        b12_ruminant_http_check(
            $actual['status']===$status &&
            $actual['body']['success']===false &&
            $actual['body']['code']===$code &&
            isset($actual['body']['error']) &&
            !str_contains(json_encode($actual['body']), $error->getMessage()),
            $code . ' sanitized and preserves exact status'
        );
    }
    $json = json_encode(
        daily_record_revision_delete_http_envelope(
            new DailyRecordRevisionConflict('private'),
        )['body'],
        JSON_THROW_ON_ERROR
    );
    b12_ruminant_http_check(
        str_contains($json, '"code":"record_conflict"') &&
        !str_contains($json, 'private'),
        'stale source returns stable conflict JSON without diagnostic leakage'
    );
    echo "B12_RUMINANT_DELETE_HTTP_PURE=PASS\nPASS_COUNT=$count\n";
} catch (Throwable $error) {
    fwrite(STDERR, "B12_RUMINANT_DELETE_HTTP_PURE=FAIL " .
        get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
