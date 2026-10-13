<?php
declare(strict_types=1);

/**
 * B12 future read-only Daily Record API adapter. SOURCE ONLY / NOT WIRED.
 *
 * This is NOT an HTTP entry point. A separately approved thin API route must
 * first load init.php, config and canonical permission helpers and call
 * requireLogin(), then use send_json() (which applies Cache-Control: no-store).
 * DO NOT wire before schema/data audit, revision migration, browser token and
 * CAS writer integration. No farm identifier is ever accepted from the client.
 *
 * The adapter independently verifies session identity, module entitlement,
 * view+edit permission for a token-bearing edit read, and farm identity from
 * requireCurrentFarmId(). It cannot authorize a CREATE from a scope probe.
 *
 * No SQL mutations or application side effects are performed here.
 */
require_once __DIR__ . '/daily_record_revision_reader.php';
require_once __DIR__ . '/daily_record_revision_capabilities.php';

if (!class_exists('DailyRecordRevisionApiSchemaUnavailable', false)) {
    final class DailyRecordRevisionApiSchemaUnavailable extends RuntimeException {}
}

if (!function_exists('daily_record_revision_api_response')) {
    /** @return array{http_status:int,body:array<string,mixed>} */
    function daily_record_revision_api_response(int $status, array $body): array
    {
        return ['http_status' => $status, 'body' => $body];
    }
}

if (!function_exists('daily_record_revision_api_error')) {
    function daily_record_revision_api_error(int $status, string $code, string $message): array
    {
        return daily_record_revision_api_response($status, [
            'success' => false,
            'code' => $code,
            'error' => $message,
        ]);
    }
}

if (!function_exists('daily_record_revision_api_parse_request')) {
    /**
     * Explicit, strict GET contract; unknown keys, farm_id and array-valued
     * query parameters fail closed. PHP application request values are strings.
     * Scope check is NEVER an edit read, and does not reserve an INSERT.
     *
     * @return array{mode:string,type:string,cycle_id:?int,record_date:string,animal_type:?string,record_id:?int}
     */
    function daily_record_revision_api_parse_request(string $method, array $input): array
    {
        if ($method !== 'GET') {
            throw new InvalidArgumentException('Unsupported request method.');
        }
        $allowed = ['mode', 'type', 'cycle_id', 'record_date', 'animal_type', 'record_id'];
        foreach ($input as $key => $value) {
            if (!is_string($key) || !in_array($key, $allowed, true) || !is_string($value)) {
                throw new InvalidArgumentException('Invalid Daily Record query parameter.');
            }
        }
        foreach (['mode', 'type', 'cycle_id', 'record_date'] as $key) {
            if (!array_key_exists($key, $input)) {
                throw new InvalidArgumentException('Missing Daily Record query parameter.');
            }
        }
        $mode = $input['mode'];
        if ($mode !== 'exact' && $mode !== 'scope') {
            throw new InvalidArgumentException('Invalid Daily Record lookup mode.');
        }
        $type = $input['type'];
        if (!in_array($type, ['layer', 'broiler', 'ruminant'], true)) {
            throw new InvalidArgumentException('Invalid Daily Record type.');
        }
        if (($type === 'ruminant') !== array_key_exists('animal_type', $input)) {
            throw new InvalidArgumentException('Invalid Daily Record species parameter.');
        }
        if ($mode === 'exact' && !array_key_exists('record_id', $input)) {
            throw new InvalidArgumentException('Immutable record ID is required.');
        }
        if ($mode === 'scope' && array_key_exists('record_id', $input)) {
            throw new InvalidArgumentException('Scope lookup must not select an edit row.');
        }
        // A missing cycle query key cannot silently mean the legacy NULL scope.
        // Only the explicitly supplied empty string has that meaning.
        $cycle = daily_record_revision_reader_cycle($input['cycle_id']);
        $date = $input['record_date'];
        $species = daily_record_revision_reader_scope(
            $type, 1, $cycle, $date, $input['animal_type'] ?? null
        )['animal_type'];
        $id = $mode === 'exact'
            ? daily_record_revision_parse_record_id($input['record_id'])
            : null;
        return [
            'mode' => $mode, 'type' => $type, 'cycle_id' => $cycle,
            'record_date' => $date, 'animal_type' => $species, 'record_id' => $id,
        ];
    }
}

if (!function_exists('daily_record_revision_api_permissions')) {
    /**
     * Closed capability map; no user-supplied permission or module names.
     * The exact-row loader serves the editable browser modal, so it requires
     * BOTH the view and edit permission. The existence probe requires view.
     */
    function daily_record_revision_api_permissions(string $type): array
    {
        $capabilities = daily_record_revision_capabilities($type);
        return [
            $capabilities['module'],
            $capabilities['view'],
            $capabilities['edit'],
        ];
    }
}

if (!function_exists('daily_record_revision_api_authorized_farm')) {
    /**
     * Runtime functions must originate from canonical initialized session.
     * Refuses to probe the database for guests or insufficient capabilities.
     * No HTTP response, credentials, or tenant metadata are logged here.
     */
    function daily_record_revision_api_authorized_farm(array $request): ?int
    {
        $sessionId = $_SESSION['user_id'] ?? null;
        if (!(is_int($sessionId) && $sessionId > 0) &&
            !(is_string($sessionId) && preg_match('/^[1-9][0-9]*$/D', $sessionId) === 1)) {
            return null;
        }
        foreach (['isPlatformOwner', 'hasRole', 'checkAccess', 'getUserType',
                  'hasPermission', 'requireCurrentFarmId'] as $name) {
            if (!function_exists($name)) {
                return null;
            }
        }
        [$module, $view, $edit] = daily_record_revision_api_permissions($request['type']);
        $privileged = isPlatformOwner() || hasRole('farm_admin');
        if (!$privileged && (
            !checkAccess($module) ||
            !hasPermission(getUserType(), $view) ||
            ($request['mode'] === 'exact' && !hasPermission(getUserType(), $edit))
        )) {
            return null;
        }
        $farm = requireCurrentFarmId();
        return is_int($farm) && $farm > 0 ? $farm : null;
    }
}

if (!function_exists('daily_record_revision_api_require_schema')) {
    /**
     * Pre-activation guard. All three revision columns must exist before any
     * reader SQL is attempted. This is NOT a substitute for a verified schema
     * migration and legacy duplicate/cycle integrity audit.
     */
    function daily_record_revision_api_require_schema(PDO $pdo): void
    {
        $sql = "SELECT COUNT(DISTINCT table_name) FROM information_schema.columns"
            . " WHERE table_schema = DATABASE()"
            . " AND table_name IN ('layer_daily_records', 'broiler_daily_records', 'ruminant_daily_records')"
            . " AND column_name = 'record_revision'";
        $stmt = $pdo->query($sql);
        if ($stmt === false || (string)$stmt->fetchColumn() !== '3') {
            throw new DailyRecordRevisionApiSchemaUnavailable(
                'Daily Record revision schema is not available.'
            );
        }
    }
}

if (!function_exists('daily_record_revision_api_editor_fields')) {
    /** Expose only closed application data fields, never internal farm/user or joined metadata. */
    function daily_record_revision_api_editor_fields(string $type, array $row): array
    {
        $definition = daily_record_revision_definition($type);
        $fields = [
            'id' => (string)$row['id'],
            'cycle_id' => $row['cycle_id'] === null ? null : (string)$row['cycle_id'],
            'record_date' => (string)$row['record_date'],
        ];
        if ($definition['animal_type']) {
            $fields['animal_type'] = strtolower(trim((string)$row['animal_type']));
        }
        foreach ($definition['fields'] as $key) {
            if (array_key_exists($key, $row)) {
                $fields[$key] = $row[$key];
            }
        }
        return $fields;
    }
}

if (!function_exists('daily_record_revision_api_handle')) {
    /**
     * Pure response envelope except SELECT-only PDO reader. No send_json()
     * or HTTP headers here. Future route must call requireLogin() first and
     * emit exactly this body via send_json($result['body'], $result['http_status']).
     */
    function daily_record_revision_api_handle(PDO $pdo, string $method, array $input): array
    {
        // Confirm session before input parsing, authorization, or any SELECT.
        $sid = $_SESSION['user_id'] ?? null;
        if (!((is_int($sid) && $sid > 0) ||
              (is_string($sid) && preg_match('/^[1-9][0-9]*$/D', $sid) === 1))) {
            return daily_record_revision_api_error(401, 'authentication_required', 'Please sign in.');
        }
        if ($method !== 'GET') {
            return daily_record_revision_api_error(405, 'method_not_allowed', 'Method not allowed.');
        }
        try {
            try {
                $request = daily_record_revision_api_parse_request($method, $input);
            } catch (InvalidArgumentException|DailyRecordRevisionConflict $e) {
                return daily_record_revision_api_error(400, 'invalid_request', 'Invalid Daily Record selection.');
            }
            $farmId = daily_record_revision_api_authorized_farm($request);
            if ($farmId === null) {
                return daily_record_revision_api_error(403, 'forbidden', 'Access denied.');
            }
            daily_record_revision_api_require_schema($pdo);
            if ($request['mode'] === 'scope') {
                $status = daily_record_revision_reader_check_scope(
                    $pdo, $request['type'], $farmId, $request['cycle_id'],
                    $request['record_date'], $request['animal_type']
                );
                return daily_record_revision_api_response(200, [
                    'success' => true,
                    'mode' => 'scope',
                    'data' => $status, // no edit fields/tokens on a scope probe
                ]);
            }
            $row = daily_record_revision_reader_read_exact(
                $pdo, $request['type'], $farmId, $request['record_id'],
                $request['cycle_id'], $request['record_date'], $request['animal_type']
            );
            return daily_record_revision_api_response(200, [
                'success' => true,
                'mode' => 'exact',
                'data' => [
                    'record' => daily_record_revision_api_editor_fields($request['type'], $row['record']),
                    'form_tokens' => $row['form_tokens'],
                ],
            ]);
        } catch (DailyRecordRevisionNotFound $e) {
            return daily_record_revision_api_error(404, 'record_not_found', 'Daily Record not found.');
        } catch (DailyRecordRevisionApiSchemaUnavailable $e) {
            return daily_record_revision_api_error(503, 'schema_unavailable', 'Daily Record editing is temporarily unavailable.');
        } catch (DailyRecordRevisionConflict $e) {
            return daily_record_revision_api_error(409, 'record_conflict', 'Daily Record selection changed or is ambiguous. Reload and review.');
        } catch (PDOException $e) {
            return daily_record_revision_api_error(503, 'reader_unavailable', 'Daily Record editing is temporarily unavailable.');
        } catch (Throwable $e) {
            return daily_record_revision_api_error(500, 'internal_error', 'The Daily Record request could not be completed.');
        }
    }
}
