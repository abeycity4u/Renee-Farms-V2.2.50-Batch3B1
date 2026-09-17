<?php

$root =
    dirname(__DIR__);

$read = static function (string $path): string {
    $text =
        file_get_contents(
            $path
        );

    if (!is_string($text)) {
        throw new RuntimeException(
            'Unable to read '
            . $path
        );
    }

    return $text;
};

try {
    $migration =
        $read(
            $root
            . '/migrations/064_expense_revision_reason.sql'
        );

    $service =
        $read(
            $root
            . '/lib/expense_revision_service.php'
        );

    $provenance =
        $read(
            $root
            . '/lib/expense_revision_provenance.php'
        );

    $updateApi =
        $read(
            $root
            . '/api/update_expense.php'
        );

    $deleteApi =
        $read(
            $root
            . '/api/delete_expense.php'
        );

    $confirmations =
        $read(
            $root
            . '/assets/js/confirmations.js'
        );

    $clients = [
        'layer' =>
            $read(
                $root
                . '/assets/js/layer-expenses.js'
            ),

        'broiler' =>
            $read(
                $root
                . '/assets/js/broiler-expenses.js'
            ),

        'ruminant' =>
            $read(
                $root
                . '/assets/js/ruminant-expenses.js'
            ),

        'management' =>
            $read(
                $root
                . '/assets/js/management-expenses.js'
            ),
    ];

    $main =
        $read(
            $root
            . '/assets/js/main.js'
        );

    $checks = [];

    $checks['MIGRATION_064_REASON_COLUMN'] =
        strpos(
            $migration,
            'ADD COLUMN revision_reason VARCHAR(500) NULL'
        ) !== false
        &&
        strpos(
            $migration,
            "064_expense_revision_reason.sql"
        ) !== false;

    $checks['MIGRATION_064_NO_BACKFILL'] =
        preg_match(
            '/\bUPDATE\s+farm_expense_revisions\b/i',
            $migration
        ) !== 1
        &&
        preg_match(
            '/\bINSERT\s+INTO\s+farm_expense_revisions\b/i',
            $migration
        ) !== 1;

    $checks['SERVICE_PERSISTS_REASON'] =
        strpos(
            $service,
            'revision_reason'
        ) !== false
        &&
        strpos(
            $service,
            'expense_revision_service_reason_for_action'
        ) !== false;

    $checks['LEGACY_BASELINE_SYSTEM_REASON'] =
        strpos(
            $service,
            'Legacy baseline captured before first controlled revision'
        ) !== false;

    $checks['UPDATE_DELETE_REASON_REQUIRED'] =
        strpos(
            $service,
            'Enter a reason for changing this expense.'
        ) !== false
        &&
        strpos(
            $service,
            'Enter a reason for deleting this expense.'
        ) !== false
        &&
        strpos(
            $service,
            'cannot exceed 500 characters'
        ) !== false;

    $checks['UPDATE_DELETE_ACTOR_REQUIRED'] =
        strpos(
            $service,
            'Expense revision actor is required.'
        ) !== false;

    $checks['REASON_NOT_CAUSAL_OR_STATE_HASHED'] =
        strpos(
            $provenance,
            'revision_reason'
        ) === false;

    $checks['UPDATE_API_PASSES_REASON'] =
        strpos(
            $updateApi,
            "\$_POST['revision_reason']"
        ) !== false
        &&
        strpos(
            $updateApi,
            '$revisionReason'
        ) !== false
        &&
        strpos(
            $updateApi,
            'InvalidArgumentException'
        ) !== false
        &&
        strpos(
            $updateApi,
            '], 422)'
        ) !== false;

    $checks['DELETE_API_PASSES_REASON'] =
        strpos(
            $deleteApi,
            "\$_POST['revision_reason']"
        ) !== false
        &&
        strpos(
            $deleteApi,
            '$revisionReason'
        ) !== false
        &&
        strpos(
            $deleteApi,
            'InvalidArgumentException'
        ) !== false
        &&
        strpos(
            $deleteApi,
            '],422)'
        ) !== false;

    $checks['SHARED_REASON_DIALOG'] =
        strpos(
            $confirmations,
            'function askReason('
        ) !== false
        &&
        strpos(
            $confirmations,
            'window.AppConfirm={ask,submit,askReason};'
        ) !== false
        &&
        strpos(
            $confirmations,
            'maxlength="${maxLength}"'
        ) !== false;

    $checks['CONFIRM_DIALOG_MODAL_FOCUS_COMPATIBILITY'] =
        strpos(
            $confirmations,
            "document.querySelector('.modal.show')"
        ) !== false
        &&
        substr_count(
            $confirmations,
            'mountConfirm(el);'
        ) === 2
        &&
        strpos(
            $confirmations,
            'document.body.appendChild(el);'
        ) === false;

    $allOperationalClients = true;

    foreach ($clients as $text) {
        if (
            strpos(
                $text,
                'AppConfirm.askReason'
            ) === false
            ||
            strpos(
                $text,
                'revision_reason'
            ) === false
        ) {
            $allOperationalClients = false;
            break;
        }
    }

    $checks['ALL_OPERATIONAL_CLIENTS_USE_REASON'] =
        $allOperationalClients;

    $checks['GENERIC_DELETE_USES_REASON'] =
        strpos(
            $main,
            'AppConfirm.askReason'
        ) !== false
        &&
        strpos(
            $main,
            'revision_reason'
        ) !== false;


    require_once
        $root
        . '/lib/expense_revision_service.php';

    $semanticPass = true;

    try {
        $create =
            expense_revision_service_reason_for_action(
                'create',
                'ignored'
            );

        $baseline =
            expense_revision_service_reason_for_action(
                'legacy_baseline',
                null
            );

        $update =
            expense_revision_service_reason_for_action(
                'update',
                '  corrected amount  '
            );

        if (
            $create !== null
            ||
            $baseline !==
                'Legacy baseline captured before first controlled revision'
            ||
            $update !==
                'corrected amount'
        ) {
            $semanticPass = false;
        }

        $blankUpdateRejected = false;

        try {
            expense_revision_service_reason_for_action(
                'update',
                '   '
            );
        } catch (InvalidArgumentException $e) {
            $blankUpdateRejected = true;
        }

        $blankDeleteRejected = false;

        try {
            expense_revision_service_reason_for_action(
                'delete',
                ''
            );
        } catch (InvalidArgumentException $e) {
            $blankDeleteRejected = true;
        }

        $longReasonRejected = false;

        try {
            expense_revision_service_reason_for_action(
                'update',
                str_repeat(
                    'x',
                    501
                )
            );
        } catch (InvalidArgumentException $e) {
            $longReasonRejected = true;
        }

        $semanticPass =
            $semanticPass
            &&
            $blankUpdateRejected
            &&
            $blankDeleteRejected
            &&
            $longReasonRejected;

    } catch (Throwable $e) {
        $semanticPass = false;
    }

    $checks['REASON_SEMANTICS'] =
        $semanticPass;


    $failed = [];

    foreach ($checks as $name => $pass) {
        echo $name
            . '='
            . (
                $pass
                    ? 'PASS'
                    : 'FAIL'
            )
            . PHP_EOL;

        if (!$pass) {
            $failed[] =
                $name;
        }
    }

    echo 'RESULT='
        . (
            $failed
                ? 'FAIL'
                : 'PASS'
        )
        . PHP_EOL;

    if ($failed) {
        echo 'FAILED='
            . implode(
                ',',
                $failed
            )
            . PHP_EOL;

        exit(1);
    }

    exit(0);

} catch (Throwable $e) {
    echo "RESULT=FAIL\n";
    echo "ERROR_CLASS="
        . get_class($e)
        . "\n";
    exit(1);
}
