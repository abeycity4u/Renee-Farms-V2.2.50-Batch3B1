<?php
declare(strict_types=1);

/**
 * B12 future-only Ruminant Daily Record DELETE response policy.
 * SOURCE ONLY / NOT ACTIVATED: both privileged and delegated handlers must
 * use identical JSON/HTTP conflict semantics while the seven-writer cutover
 * gate remains hard-OFF. This contains no SQL, transaction, access bypass,
 * or retry/fallback to legacy deletion.
 *
 * The existing Ruminant page uses a native HTML DELETE form. Once the B12
 * gate is enabled, it must FIRST be replaced by an authenticated, CSRF
 * protected AJAX client. Until then, reject ordinary navigation POST before
 * the canonical deletion transaction. Never redirect a revision conflict.
 */
require_once __DIR__ . '/daily_record_revision_save_gate.php';
require_once __DIR__ . '/daily_record_revision_http_transport.php';

if (!class_exists('DailyRecordRevisionDeleteClientNotReady', false)) {
    final class DailyRecordRevisionDeleteClientNotReady extends RuntimeException {}
}

if (!function_exists('daily_record_revision_delete_is_ajax')) {
    /** This is transport validation, NOT user authorization. */
    function daily_record_revision_delete_is_ajax(array $server): bool
    {
        return daily_record_revision_http_is_ajax($server);
    }
}

if (!function_exists('daily_record_revision_delete_http_envelope')) {
    /**
     * @return array{status:int,body:array<string,mixed>}
     * No exception messages, SQL, farm IDs, or raw browser fields are emitted.
     */
    function daily_record_revision_delete_http_envelope(
        ?Throwable $error, ?int $deletedRecordId = null
    ): array {
        if ($error === null) {
            if ($deletedRecordId === null || $deletedRecordId <= 0) {
                throw new LogicException('A confirmed DELETE requires a persisted record ID.');
            }
            return [
                'status' => 200,
                'body' => [
                    'success' => true, 'code' => 'deleted',
                    'record_id' => $deletedRecordId,
                    'message' => 'Daily Record deleted successfully.',
                ],
            ];
        }
        if ($error instanceof DailyRecordRevisionDeleteClientNotReady) {
            return ['status' => 409, 'body' => [
                'success' => false, 'code' => 'revision_client_required',
                'error' => 'Reload the Daily Record page before deleting.',
            ]];
        }
        if ($error instanceof DailyRecordRevisionConflict) {
            return ['status' => 409, 'body' => [
                'success' => false, 'code' => 'record_conflict',
                'error' => 'The Daily Record changed. Reload before deleting.',
            ]];
        }
        if ($error instanceof DailyRecordRevisionPermissionDenied) {
            return ['status' => 403, 'body' => [
                'success' => false, 'code' => 'forbidden',
                'error' => 'You do not have permission to delete this Daily Record.',
            ]];
        }
        if ($error instanceof InvalidArgumentException) {
            return ['status' => 422, 'body' => [
                'success' => false, 'code' => 'invalid_delete_request',
                'error' => 'Invalid Daily Record selection. Reload before deleting.',
            ]];
        }
        return ['status' => 500, 'body' => [
            'success' => false, 'code' => 'delete_failed',
            'error' => 'The Daily Record could not be deleted. Try again later.',
        ]];
    }
}

if (!function_exists('daily_record_revision_delete_http_send')) {
    /** Terminates both cutover-only Ruminant caller paths without redirect. */
    function daily_record_revision_delete_http_send(
        ?Throwable $error, ?int $deletedRecordId = null
    ): never {
        $result = daily_record_revision_delete_http_envelope($error, $deletedRecordId);
        http_response_code($result['status']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($result['body'], JSON_THROW_ON_ERROR);
        exit;
    }
}

if (!function_exists('daily_record_revision_delete_require_ajax')) {
    /**
     * Called after the existing CSRF and role checks, before any DB mutation.
     * An ordinary form POST must never reach the revision-aware DELETE when
     * an AJAX client is not yet installed by the coordinated B12 cutover.
     */
    function daily_record_revision_delete_require_ajax(array $server): void
    {
        if (!daily_record_revision_delete_is_ajax($server)) {
            daily_record_revision_delete_http_send(
                new DailyRecordRevisionDeleteClientNotReady('B12 AJAX DELETE client is required.')
            );
        }
    }
}
