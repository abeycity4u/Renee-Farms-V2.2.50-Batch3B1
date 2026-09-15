<?php

$root = dirname(__DIR__);

$profilePath =
    $root . '/ruminant/animal_view.php';

$registryPath =
    $root . '/ruminant/animal_registry.php';

$lifecyclePath =
    $root . '/lib/ruminant_lifecycle_integrity.php';

$workspacePath =
    $root
    . '/lib/ruminant_cycle_transfer_workspace.php';

$servicePath =
    $root
    . '/lib/ruminant_cycle_transfer.php';

$profile =
    is_file($profilePath)
        ? file_get_contents($profilePath)
        : false;

$registry =
    is_file($registryPath)
        ? file_get_contents($registryPath)
        : false;

$lifecycle =
    is_file($lifecyclePath)
        ? file_get_contents($lifecyclePath)
        : false;

$workspace =
    is_file($workspacePath)
        ? file_get_contents($workspacePath)
        : false;

$service =
    is_file($servicePath)
        ? file_get_contents($servicePath)
        : false;

$checks = 0;
$failures = 0;

$check = static function (
    string $label,
    bool $passed
) use (
    &$checks,
    &$failures
): void {
    $checks++;

    echo (
        $passed
            ? 'PASS: '
            : 'FAIL: '
    ) . $label . PHP_EOL;

    if (!$passed) {
        $failures++;
    }
};

$check(
    'Animal Profile is readable',
    is_string($profile)
);

$check(
    'Animal Registry is readable',
    is_string($registry)
);

$check(
    'central ruminant lifecycle service is readable',
    is_string($lifecycle)
);

$check(
    'transfer workspace is readable',
    is_string($workspace)
);

$check(
    'canonical tagged transfer service is readable',
    is_string($service)
);

if (
    !is_string($profile)
    || !is_string($registry)
    || !is_string($lifecycle)
    || !is_string($workspace)
    || !is_string($service)
) {
    echo PHP_EOL;
    echo "Checks: {$checks}" . PHP_EOL;
    echo "Failures: {$failures}" . PHP_EOL;
    exit(1);
}

$check(
    'profile requires shared transfer workspace',
    str_contains(
        $profile,
        'ruminant_cycle_transfer_workspace.php'
    )
);

$check(
    'transfer permission uses granular animal edit permission',
    str_contains(
        $profile,
        "'ruminant_animals_edit'"
    )
);

$check(
    'profile has dedicated transfer permission variable',
    str_contains(
        $profile,
        '$canTransfer ='
    )
);

$actionPos = strpos(
    $profile,
    "if (\$action === 'transfer_cycle') {"
);

$permissionPos =
    $actionPos !== false
        ? strpos(
            $profile,
            'if (!$canTransfer)',
            $actionPos
        )
        : false;

$delegatePos =
    $actionPos !== false
        ? strpos(
            $profile,
            'ruminant_cycle_transfer_record(',
            $actionPos
        )
        : false;

$check(
    'transfer action enforces dedicated permission before mutation',
    $actionPos !== false
    && $permissionPos !== false
    && $delegatePos !== false
    && $actionPos < $permissionPos
    && $permissionPos < $delegatePos
);

$check(
    'profile delegates transfer exactly once',
    substr_count(
        $profile,
        'ruminant_cycle_transfer_record('
    ) === 1
);

$check(
    'profile delegates reversal exactly once',
    substr_count(
        $profile,
        'ruminant_cycle_transfer_reverse('
    ) === 1
);

$reverseActionPos = strpos(
    $profile,
    "if (\$action === 'reverse_cycle_transfer') {"
);

$reversePermissionPos =
    $reverseActionPos !== false
        ? strpos(
            $profile,
            'if (!$canTransfer)',
            $reverseActionPos
        )
        : false;

$reverseLoadPos =
    $reverseActionPos !== false
        ? strpos(
            $profile,
            'ruminant_cycle_transfer_load(',
            $reverseActionPos
        )
        : false;

$reverseAnimalBindingPos =
    $reverseLoadPos !== false
        ? strpos(
            $profile,
            "'animal_id'",
            $reverseLoadPos
        )
        : false;

$reverseDelegatePos =
    $reverseActionPos !== false
        ? strpos(
            $profile,
            'ruminant_cycle_transfer_reverse(',
            $reverseActionPos
        )
        : false;

$check(
    'reversal action enforces transfer permission before central delegate',
    $reverseActionPos !== false
    && $reversePermissionPos !== false
    && $reverseDelegatePos !== false
    && $reverseActionPos < $reversePermissionPos
    && $reversePermissionPos < $reverseDelegatePos
);

$reverseAnimalBindingContract =
    preg_match(
        '/\(int\)\s*\$reverseTarget\s*'
        . '\[\s*\'animal_id\'\s*\]\s*'
        . '!==\s*\$animalId/s',
        $profile
    ) === 1;

$check(
    'reversal route binds submitted transfer to current animal through shared loader',
    $reverseLoadPos !== false
    && $reverseAnimalBindingPos !== false
    && $reverseDelegatePos !== false
    && $reverseLoadPos < $reverseAnimalBindingPos
    && $reverseAnimalBindingPos < $reverseDelegatePos
    && $reverseAnimalBindingContract
);

$check(
    'failed reversal form and reopen state are preserved',
    str_contains(
        $profile,
        'ruminant_cycle_transfer_reverse_form'
    )
    && str_contains(
        $profile,
        'ruminant_cycle_transfer_reverse_reopen'
    )
);

$check(
    'reversal modal requires bounded reason',
    str_contains(
        $profile,
        'name="transfer_reversal_reason"'
    )
    && str_contains(
        $profile,
        'minlength="4"'
    )
    && str_contains(
        $profile,
        'maxlength="255"'
    )
);

$check(
    'active unreversed history exposes Reverse action',
    str_contains(
        $profile,
        "empty(\$transferRow['reversed_at'])"
    )
    && str_contains(
        $profile,
        "data-bs-target=\"#cycleTransferReverseModal\""
    )
    && str_contains(
        $profile,
        '>Reverse</button>'
    )
);

$check(
    'reversal form uses styled destructive confirmation contract',
    str_contains(
        $profile,
        'data-confirm="Reverse this production-cycle transfer?'
    )
    && str_contains(
        $profile,
        'data-confirm-title="Reverse cycle transfer?"'
    )
    && str_contains(
        $profile,
        'data-confirm-button="Reverse Transfer"'
    )
);

$check(
    'route does not duplicate central reversal audit ownership',
    !str_contains(
        $profile,
        "'ruminant_cycle_transfer_reversed'"
    )
);

$check(
    'central tagged transfer service owns reversal audit event',
    str_contains(
        $service,
        "'ruminant_cycle_transfer_reversed'"
    )
);

$check(
    'reversal success handles central idempotent replay',
    str_contains(
        $profile,
        "'already_reversed'"
    )
    && str_contains(
        $profile,
        'No additional changes'
    )
    && str_contains(
        $profile,
        'were made.'
    )
);

$check(
    'reversal modal remains outside manage-only modal group',
    strpos(
        $profile,
        '/* canManage modal group */'
    ) !== false
    && strpos(
        $profile,
        'id="cycleTransferReverseModal"'
    ) !== false
    && strpos(
        $profile,
        '/* canManage modal group */'
    ) < strpos(
        $profile,
        'id="cycleTransferReverseModal"'
    )
);

$check(
    'profile does not write canonical population SQL',
    preg_match(
        '/\b(INSERT|UPDATE|DELETE)\b.{0,160}'
        . 'production_population_'
        . '(movements|baselines)/is',
        $profile
    ) !== 1
);

$check(
    'route does not duplicate tagged transfer audit ownership',
    !str_contains(
        $profile,
        "'ruminant_cycle_transfer'"
    )
);

$check(
    'central tagged transfer service owns audit event',
    str_contains(
        $service,
        "'ruminant_cycle_transfer_recorded'"
    )
    && str_contains(
        $service,
        "'ruminant_animal_cycle_transfer'"
    )
);

$check(
    'failed transfer form is preserved in session',
    str_contains(
        $profile,
        'ruminant_cycle_transfer_form'
    )
);

$check(
    'failed transfer modal is reopened',
    str_contains(
        $profile,
        'ruminant_cycle_transfer_reopen'
    )
    && str_contains(
        $profile,
        'cycleTransferModal'
    )
);

$check(
    'request token is preserved across validation errors',
    str_contains(
        $profile,
        'transfer_request_token'
    )
    && str_contains(
        $profile,
        "'request_token'"
    )
);

$check(
    'profile uses shared workspace read model',
    str_contains(
        $profile,
        'ruminant_cycle_transfer_workspace('
    )
);

$check(
    'Move to another cycle is first-class Animal Profile action',
    str_contains(
        $profile,
        'Move to another cycle'
    )
);

$check(
    'transfer modal identifies source cycle',
    str_contains(
        $profile,
        'Current Production Cycle'
    )
);

$check(
    'transfer form uses destination cycle selector',
    str_contains(
        $profile,
        'name="to_cycle_id"'
    )
);

$check(
    'transfer form uses effective transfer date',
    str_contains(
        $profile,
        'name="transfer_date"'
    )
);

$check(
    'UI explains destination owns transfer date',
    str_contains(
        $profile,
        'source membership ends the previous day'
    )
);

$check(
    'cycle transfer history is visible',
    str_contains(
        $profile,
        'Cycle Transfer History'
    )
);

$check(
    'workspace reuses central membership history helper',
    str_contains(
        $workspace,
        'ruminant_cycle_memberships_for_animal('
    )
);

$check(
    'workspace rejects ambiguous multiple open memberships',
    str_contains(
        $workspace,
        'count($openMemberships) > 1'
    )
);

$check(
    'workspace explains missing current membership',
    str_contains(
        $workspace,
        'Assign the animal to its current'
    )
);

$check(
    'source cycle must be active',
    str_contains(
        $workspace,
        'must be Active before this'
    )
);

$check(
    'source cycle requires V3 population baseline',
    str_contains(
        $workspace,
        'production_population_baselines'
    )
    && str_contains(
        $workspace,
        'Confirm the current population'
    )
);

$check(
    'destination cycles require population baseline',
    preg_match(
        '/INNER JOIN\s+production_population_baselines/s',
        $workspace
    ) === 1
);

$check(
    'destination cycles must be active',
    str_contains(
        $workspace,
        'pc.status = "active"'
    )
);

$check(
    'destination excludes current source cycle',
    str_contains(
        $workspace,
        'pc.id <> ?'
    )
);

$check(
    'destination preserves farm type identity',
    str_contains(
        $workspace,
        'LOWER(pc.farm_type)'
    )
);

$check(
    'destination preserves production type identity',
    str_contains(
        $workspace,
        'LOWER(pc.production_type)'
    )
);

$check(
    'destination preserves custom livestock identity',
    str_contains(
        $workspace,
        'pc.livestock_type_id'
    )
    && str_contains(
        $workspace,
        '<=> ?'
    )
);

$check(
    'workspace reads durable tagged transfer history',
    str_contains(
        $workspace,
        'ruminant_animal_cycle_transfers'
    )
    && str_contains(
        $workspace,
        'production_population_transfers'
    )
);

$check(
    'workspace contains no population mutation SQL',
    preg_match(
        '/\b(INSERT|UPDATE|DELETE)\b.{0,160}'
        . 'production_population_/is',
        $workspace
    ) !== 1
);

$check(
    'new Record Exit no longer offers Transferred',
    !str_contains(
        $registry,
        '<option value="transferred">Transferred</option>'
    )
);

$check(
    'Registry does not duplicate transferred-exit policy',
    !str_contains(
        $registry,
        "if(\$outcome==='transferred')"
    )
);

$check(
    'central manual-exit service rejects new Transferred outcome',
    str_contains(
        $lifecycle,
        "if(\$outcome==='transferred')"
    )
    && str_contains(
        $lifecycle,
        'Use Move to another cycle'
    )
);

$check(
    'central manual-exit outcome map no longer accepts transferred',
    !str_contains(
        $lifecycle,
        "'transferred'=>'transferred'"
    )
);

$check(
    'historical manual transferred display remains readable',
    str_contains(
        $lifecycle,
        "'manual_transferred'=>'Transferred'"
    )
);

$check(
    'historical transferred animal status remains readable in Registry',
    substr_count(
        $registry,
        "'transferred'"
    ) >= 2
);

$check(
    'Record Exit copy directs internal moves to Animal Profile',
    str_contains(
        $registry,
        'open its Animal Profile and use Move to another cycle'
    )
);

$check(
    'canonical record function remains present exactly once',
    substr_count(
        $service,
        'function ruminant_cycle_transfer_record('
    ) === 1
);

$check(
    'canonical reversal function remains present exactly once',
    substr_count(
        $service,
        'function ruminant_cycle_transfer_reverse('
    ) === 1
);

$postStartPos = strpos(
    $profile,
    "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {"
);

$postActionPos =
    $postStartPos !== false
        ? strpos(
            $profile,
            "\$action = \$_POST['action'] ?? '';",
            $postStartPos
        )
        : false;

$postTransferAuthPos =
    $postActionPos !== false
        ? strpos(
            $profile,
            "\$action === 'transfer_cycle'",
            $postActionPos
        )
        : false;

$postReverseAuthPos =
    $postTransferAuthPos !== false
        ? strpos(
            $profile,
            "|| \$action === 'reverse_cycle_transfer'",
            $postTransferAuthPos
        )
        : false;

$postTransferPermissionPos =
    $postTransferAuthPos !== false
        ? strpos(
            $profile,
            'if (!$canTransfer)',
            $postTransferAuthPos
        )
        : false;

$postFallbackManagePos =
    $postTransferAuthPos !== false
        ? strpos(
            $profile,
            '} elseif (!$canManage) {',
            $postTransferAuthPos
        )
        : false;

$postCsrfPos =
    $postFallbackManagePos !== false
        ? strpos(
            $profile,
            'verify_csrf_token',
            $postFallbackManagePos
        )
        : false;

$check(
    'POST action is resolved before authorization policy',
    $postStartPos !== false
    && $postActionPos !== false
    && $postStartPos < $postActionPos
);

$check(
    'transfer uses granular permission while other mutations retain canManage',
    $postTransferAuthPos !== false
    && $postReverseAuthPos !== false
    && $postTransferPermissionPos !== false
    && $postFallbackManagePos !== false
    && $postCsrfPos !== false
    && $postActionPos < $postTransferAuthPos
    && $postTransferAuthPos
        < $postReverseAuthPos
    && $postReverseAuthPos
        < $postTransferPermissionPos
    && $postTransferPermissionPos
        < $postFallbackManagePos
    && $postFallbackManagePos
        < $postCsrfPos
);

$manageModalEndPos = strpos(
    $profile,
    '/* canManage modal group */'
);

$transferModalPos = strpos(
    $profile,
    'id="cycleTransferModal"'
);

$bootstrapScriptPos = strpos(
    $profile,
    'bootstrap.bundle.min.js'
);

$check(
    'transfer modal is rendered outside manage-only modal group',
    $manageModalEndPos !== false
    && $transferModalPos !== false
    && $bootstrapScriptPos !== false
    && $manageModalEndPos < $transferModalPos
    && $transferModalPos < $bootstrapScriptPos
);

/*
 * Token-based self-audit avoids matching strings that merely
 * describe forbidden bootstrap patterns inside this verifier.
 */
$verifierSource =
    file_get_contents(__FILE__);

$tokens =
    token_get_all(
        (string)$verifierSource
    );

$hasBootstrapToken = false;

for (
    $i = 0,
    $tokenCount = count($tokens);
    $i < $tokenCount;
    $i++
) {
    $token = $tokens[$i];

    if (!is_array($token)) {
        continue;
    }

    if (
        in_array(
            $token[0],
            [
                T_REQUIRE,
                T_REQUIRE_ONCE,
                T_INCLUDE,
                T_INCLUDE_ONCE,
            ],
            true
        )
    ) {
        $hasBootstrapToken = true;
        break;
    }

    if ($token[0] !== T_NEW) {
        continue;
    }

    for (
        $j = $i + 1;
        $j < $tokenCount;
        $j++
    ) {
        $next = $tokens[$j];

        if (
            is_array($next)
            && $next[0] === T_WHITESPACE
        ) {
            continue;
        }

        if (
            is_array($next)
            && $next[0] === T_STRING
            && strcasecmp(
                $next[1],
                'PDO'
            ) === 0
        ) {
            $hasBootstrapToken = true;
        }

        break;
    }

    if ($hasBootstrapToken) {
        break;
    }
}

$check(
    'verifier itself does not bootstrap database',
    !$hasBootstrapToken
);

echo PHP_EOL;
echo "Checks: {$checks}" . PHP_EOL;
echo "Failures: {$failures}" . PHP_EOL;

if ($failures > 0) {
    echo "V3 tagged-ruminant transfer UI verifier FAILED."
        . PHP_EOL;
    exit(1);
}

echo "V3 tagged-ruminant transfer UI verifier PASSED."
    . PHP_EOL;

echo "Database connection/write: NONE."
    . PHP_EOL;
