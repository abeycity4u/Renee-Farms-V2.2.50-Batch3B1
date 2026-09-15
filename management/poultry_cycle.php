<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/production_cycle_service.php');
require_once(__DIR__ . '/../lib/poultry_cycle_lifecycle.php');
require_once(__DIR__ . '/../lib/poultry_cycle_acquisition.php');
require_once(__DIR__ . '/../lib/poultry_rearing_economics.php');
require_once(__DIR__ . '/../lib/poultry_production_entry_snapshots.php');
require_once(__DIR__ . '/../lib/poultry_cycle_completion.php');

requireLogin();
requireBusinessReportAccess();

$farmId = requireCurrentFarmId();

if (
    !isPlatformOwner()
    && !hasRole('farm_admin')
    && !hasPermission(getUserType(), 'production_cycles')
) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

$cycleId = (int)($_GET['id'] ?? ($_POST['cycle_id'] ?? 0));

if ($cycleId <= 0) {
    http_response_code(404);
    exit('Poultry cycle not found.');
}

$canManageCycleOperations =
    isPlatformOwner()
    || hasRole('farm_admin');

$canEndProduction = $canManageCycleOperations;

$flashSuccess = '';
$flashError = '';
$endDateForm = trim(
    (string)($_POST['end_date'] ?? '')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_post();

    $action = (string)($_POST['action'] ?? '');

    $adminMutationActions = [
        'end_production',
        'void_poultry_acquisition',
        'transition_poultry_phase',
        'approve_production_entry_basis',
    ];

    if (
        in_array(
            $action,
            $adminMutationActions,
            true
        )
        && !$canManageCycleOperations
    ) {
        http_response_code(403);
        exit('Production-cycle management access required.');
    }

    try {
        if ($action === 'end_production') {
            $result = poultry_cycle_end_production(
                $pdo,
                $farmId,
                $cycleId,
                $endDateForm,
                isset($_SESSION['user_id'])
                    ? (int)$_SESSION['user_id']
                    : null
            );

            $message =
                'Production ended successfully on '
                . (string)$result['end_date']
                . '. Closing live population: '
                . number_format(
                    (int)$result['closing_headcount']
                )
                . '.';

            if (!empty($result['ended_phase'])) {
                $message .=
                    ' The open biological stage was ended on the same date.';
            }

            $_SESSION['poultry_cycle_flash'] = $message;

            header(
                'Location: '
                . BASE_URL
                . '/management/poultry_cycle.php?id='
                . $cycleId
            );
            exit();
        }

        if ($action === 'void_poultry_acquisition') {
            $acquisitionId =
                (int)($_POST['acquisition_id'] ?? 0);

            $voidReason = trim(
                (string)($_POST['void_reason'] ?? '')
            );

            $cycleAcquisitions =
                poultry_acquisition_history(
                    $pdo,
                    $farmId,
                    $cycleId
                );

            $cycleAcquisitionIds = array_map(
                static function (array $row): int {
                    return (int)($row['id'] ?? 0);
                },
                $cycleAcquisitions
            );

            if (
                $acquisitionId <= 0
                || !in_array(
                    $acquisitionId,
                    $cycleAcquisitionIds,
                    true
                )
            ) {
                throw new PoultryAcquisitionException(
                    'The selected acquisition entry does not belong to this cycle.'
                );
            }

            poultry_acquisition_void(
                $pdo,
                $farmId,
                $acquisitionId,
                $voidReason,
                isset($_SESSION['user_id'])
                    ? (int)$_SESSION['user_id']
                    : null
            );

            $_SESSION['poultry_cycle_flash'] =
                'Acquisition entry voided. '
                . 'The original row remains in audit history.';

            header(
                'Location: '
                . BASE_URL
                . '/management/poultry_cycle.php?id='
                . $cycleId
                . '#entry'
            );
            exit();
        }

        if ($action === 'transition_poultry_phase') {
            $nextPhase = strtolower(
                trim((string)($_POST['phase'] ?? ''))
            );

            $transitionDate = trim(
                (string)($_POST['phase_start_date'] ?? '')
            );

            $notes = trim(
                (string)($_POST['phase_notes'] ?? '')
            );

            poultry_lifecycle_transition_phase(
                $pdo,
                $farmId,
                $cycleId,
                $nextPhase,
                $transitionDate,
                $notes !== '' ? $notes : null,
                isset($_SESSION['user_id'])
                    ? (int)$_SESSION['user_id']
                    : null
            );

            $_SESSION['poultry_cycle_flash'] =
                'Lifecycle transition recorded. '
                . 'The previous stage was closed on the day before '
                . 'the new stage began.';

            header(
                'Location: '
                . BASE_URL
                . '/management/poultry_cycle.php?id='
                . $cycleId
                . '#lifecycle'
            );
            exit();
        }

        if ($action === 'approve_production_entry_basis') {
            $category = trim(
                (string)($_POST['revision_category'] ?? '')
            );

            $reason = trim(
                (string)($_POST['revision_reason'] ?? '')
            );

            poultry_production_entry_approve(
                $pdo,
                $farmId,
                $cycleId,
                (int)($_SESSION['user_id'] ?? 0),
                $category,
                $reason
            );

            $_SESSION['poultry_cycle_flash'] =
                'Production-entry economic basis approved '
                . 'as a new immutable version.';

            header(
                'Location: '
                . BASE_URL
                . '/management/poultry_cycle.php?id='
                . $cycleId
                . '#economic-basis'
            );
            exit();
        }

        throw new InvalidArgumentException(
            'Unsupported production-cycle action.'
        );

    } catch (Throwable $error) {
        $safe =
            $error instanceof InvalidArgumentException
            || $error instanceof ProductionCycleException
            || $error instanceof ProductionPopulationException
            || $error instanceof PoultryLifecycleException
            || $error instanceof PoultryAcquisitionException
            || (
                $action === 'approve_production_entry_basis'
                && $error instanceof RuntimeException
            );

        if (!$safe) {
            error_log(
                'Poultry Manage Cycle action failed: '
                . $error->getMessage()
            );
        }

        $flashError = $safe
            ? $error->getMessage()
            : 'The requested cycle action could not be completed. '
                . 'No confirmed cycle history was changed.';
    }
}

if (!empty($_SESSION['poultry_cycle_flash'])) {
    $flashSuccess =
        (string)$_SESSION['poultry_cycle_flash'];

    unset($_SESSION['poultry_cycle_flash']);
}

try {
    $cycle = production_cycle_get(
        $pdo,
        $farmId,
        $cycleId
    );
} catch (Throwable $error) {
    http_response_code(404);
    exit('Poultry cycle not found.');
}

$type = strtolower(
    (string)$cycle['production_type']
);

if (
    strtolower((string)$cycle['farm_type'])
        !== 'poultry'
    || !in_array(
        $type,
        ['layer', 'broiler'],
        true
    )
) {
    http_response_code(404);
    exit('Poultry cycle not found.');
}

$currentPhase = poultry_lifecycle_current_phase(
    $pdo,
    $farmId,
    $cycleId
);

$phaseHistory = poultry_lifecycle_history(
    $pdo,
    $farmId,
    $cycleId
);

$latestPhase = !empty($phaseHistory)
    ? $phaseHistory[count($phaseHistory) - 1]
    : null;

if ($currentPhase !== null) {
    $phaseLabel = poultry_lifecycle_phase_label(
        $type,
        (string)$currentPhase['phase']
    );

    $phaseDisplay = $phaseLabel;
} elseif ($latestPhase !== null) {
    $phaseLabel = poultry_lifecycle_phase_label(
        $type,
        (string)$latestPhase['phase']
    );

    $phaseDisplay =
        $phaseLabel . ' · ended';
} else {
    $phaseLabel = 'Not defined';
    $phaseDisplay = 'Not defined';
}

$acquisitionHistory =
    poultry_acquisition_history(
        $pdo,
        $farmId,
        $cycleId
    );

$acquisitionSummary =
    poultry_acquisition_summary(
        $acquisitionHistory
    );

$nextPhases =
    $currentPhase !== null
        ? poultry_lifecycle_next_phases(
            $type,
            (string)$currentPhase['phase']
        )
        : [];

$rearingEconomics =
    $type === 'layer'
        ? poultry_rearing_economics(
            $pdo,
            $farmId,
            $cycleId
        )
        : null;

$productionEntryCandidate =
    $type === 'layer'
        ? poultry_production_entry_candidate(
            $pdo,
            $farmId,
            $cycleId
        )
        : null;

$productionEntrySnapshots =
    $type === 'layer'
        ? poultry_production_entry_snapshots(
            $pdo,
            $farmId,
            $cycleId
        )
        : [];

$latestProductionEntrySnapshot =
    $productionEntrySnapshots[0] ?? null;

$moneyOrDash = static function ($value): string {
    return $value === null
        ? '-'
        : '₦' . number_format((float)$value, 2);
};

$dailyUrl =
    $type === 'layer'
        ? '/poultry/layers_daily_record.php'
        : '/poultry/broiler_daily_record.php';

$feedsUrl =
    $type === 'layer'
        ? '/poultry/layer_feeds.php'
        : '/poultry/broiler_feeds.php';

$expensesUrl =
    $type === 'layer'
        ? '/poultry/layer_expenses.php'
        : '/poultry/broiler_expenses.php';

$populationState = null;
$populationReadError = '';

try {
    $populationState = production_population_state(
        $pdo,
        $farmId,
        $cycleId
    );
} catch (Throwable $error) {
    $safe =
        $error instanceof InvalidArgumentException
        || $error instanceof ProductionPopulationException;

    if (!$safe) {
        error_log(
            'Poultry cycle population read failed: '
            . $error->getMessage()
        );
    }

    $populationReadError = $safe
        ? $error->getMessage()
        : 'Canonical population state could not be read.';
}

$isClosed =
    strtolower((string)$cycle['status'])
        === 'closed';

if (
    $isClosed
    && $cycle['closing_headcount'] !== null
) {
    $displayPopulation =
        (int)$cycle['closing_headcount'];

    $populationLabel = 'Closing Population';
    $populationSource =
        'Recorded when production ended';
} elseif ($populationState !== null) {
    $displayPopulation =
        (int)$populationState['quantity'];

    $populationLabel = 'Tracked Population';
    $populationSource =
        'V3 population ledger';
} else {
    $displayPopulation = null;
    $populationLabel = 'Tracked Population';
    $populationSource =
        'Population cutover required';
}

$confirmationText =
    'End this production cycle?';

if ($currentPhase !== null) {
    $confirmationText .=
        ' The current '
        . $phaseLabel
        . ' biological stage will end on the same date.';
}

$confirmationText .=
    ' This does not record birds as sold, dead, culled, slaughtered, or transferred.';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Production Cycle</title>
    <?php include(dirname(__DIR__) . '/navbar_head.php'); ?>

    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/management-workspaces.css'); ?>"
    >
</head>

<body>
<?php include(dirname(__DIR__) . '/navbar.php'); ?>

<div class="container-fluid px-3 px-lg-4 py-3">

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($flashSuccess); ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">

        <div>
            <div class="text-muted small text-uppercase">
                <?php echo htmlspecialchars(strtoupper($type)); ?>
                · Production Cycle
            </div>

            <h3 class="mb-1">
                <?php
                echo htmlspecialchars(
                    (string)$cycle['cycle_code']
                );
                ?>
            </h3>
        </div>

        <a
            class="btn btn-outline-secondary btn-sm"
            href="<?php echo BASE_URL; ?>/management/production_cycles.php"
        >
            ← Production Cycles
        </a>

    </div>

    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <div class="card workspace-stat h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Status
                    </div>

                    <div class="value text-uppercase">
                        <?php
                        echo htmlspecialchars(
                            (string)$cycle['status']
                        );
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card workspace-stat h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Biological Stage
                    </div>

                    <div class="value">
                        <?php
                        echo htmlspecialchars(
                            $phaseDisplay
                        );
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card workspace-stat h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        <?php
                        echo htmlspecialchars(
                            $populationLabel
                        );
                        ?>
                    </div>

                    <div class="value">
                        <?php
                        echo $displayPopulation === null
                            ? '-'
                            : number_format(
                                $displayPopulation
                            );
                        ?>
                    </div>

                    <div class="small text-muted">
                        <?php
                        echo htmlspecialchars(
                            $populationSource
                        );
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card workspace-stat h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Bird Cost Basis
                    </div>

                    <div class="value">
                        <?php
                        echo htmlspecialchars(
                            $moneyOrDash(
                                $cycle['bird_unit_cost']
                                ?? null
                            )
                        );
                        ?>
                    </div>

                    <div class="small text-muted">
                        Mortality valuation basis
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="card mb-3">
        <div class="card-header">
            <strong>Cycle Overview</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">

                <div class="col-6 col-lg">
                    <div class="text-muted small">
                        Cycle Start
                    </div>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            (string)$cycle['start_date']
                        );
                        ?>
                    </strong>
                </div>

                <div class="col-6 col-lg">
                    <div class="text-muted small">
                        Expected End
                    </div>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            (string)(
                                $cycle['expected_end_date']
                                ?? '-'
                            )
                        );
                        ?>
                    </strong>
                </div>

                <div class="col-6 col-lg">
                    <div class="text-muted small">
                        Opening Headcount
                    </div>

                    <strong>
                        <?php
                        echo number_format(
                            (int)$cycle['opening_headcount']
                        );
                        ?>
                    </strong>
                </div>

                <div class="col-6 col-lg">
                    <div class="text-muted small">
                        Production End
                    </div>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            (string)(
                                $cycle['close_date']
                                ?? '-'
                            )
                        );
                        ?>
                    </strong>
                </div>

                <div class="col-6 col-lg">
                    <div class="text-muted small">
                        Closing Headcount
                    </div>

                    <strong>
                        <?php
                        echo $cycle['closing_headcount'] === null
                            ? '-'
                            : number_format(
                                (int)$cycle['closing_headcount']
                            );
                        ?>
                    </strong>
                </div>

            </div>
        </div>
    </div>


    <div class="card mb-3">
        <div class="card-header">
            <strong>Cycle Workspace</strong>
        </div>

        <div class="card-body">
            <div class="small text-muted mb-2">
                One place to understand and manage this flock.
                Source transactions remain in their existing modules.
            </div>

            <div class="workspace-actions d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-outline-primary btn-sm" href="#entry">
                    Entry &amp; Acquisition
                </a>
                <a class="btn btn-outline-primary btn-sm" href="#lifecycle">
                    Lifecycle
                </a>

                <?php if ($type === 'layer'): ?>
                    <a class="btn btn-outline-primary btn-sm" href="#economics">
                        Rearing Economics
                    </a>
                    <a class="btn btn-outline-primary btn-sm" href="#economic-basis">
                        Economic Basis
                    </a>
                <?php endif; ?>

                <a class="btn btn-outline-warning btn-sm" href="#end-production">
                    End Production
                </a>
            </div>

            <div class="small text-muted fw-semibold mb-2">
                Source modules
            </div>

            <div class="workspace-actions d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary btn-sm"
                   href="<?php echo BASE_URL . $dailyUrl; ?>">
                    Daily Records ↗
                </a>

                <a class="btn btn-outline-secondary btn-sm"
                   href="<?php echo BASE_URL . $feedsUrl; ?>">
                    Feed Records ↗
                </a>

                <a class="btn btn-outline-secondary btn-sm"
                   href="<?php echo BASE_URL; ?>/poultry/health.php">
                    Health &amp; Treatment ↗
                </a>

                <a class="btn btn-outline-secondary btn-sm"
                   href="<?php echo BASE_URL . $expensesUrl; ?>">
                    Expenses ↗
                </a>
            </div>
        </div>
    </div>

    <div id="entry" class="card mb-3 section-anchor">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Entry &amp; Acquisition</strong>

            <?php if ($canManageCycleOperations): ?>
                <span class="badge bg-primary">Correction available</span>
            <?php else: ?>
                <span class="badge bg-info">Read only</span>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="text-muted small">
                        Recorded Quantity
                    </div>
                    <h5>
                        <?php
                        echo number_format(
                            (int)$acquisitionSummary['quantity']
                        );
                        ?>
                    </h5>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">
                        Active Acquisition Cost
                    </div>
                    <h5>
                        <?php
                        echo $moneyOrDash(
                            $acquisitionSummary['total_cost']
                        );
                        ?>
                    </h5>
                </div>

                <div class="col-md-4">
                    <div class="text-muted small">
                        Effective Cost / Bird
                    </div>
                    <h5>
                        <?php
                        echo $moneyOrDash(
                            $acquisitionSummary[
                                'effective_cost_per_bird'
                            ]
                        );
                        ?>
                    </h5>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-sm compact-table mb-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry</th>
                        <th>Age</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Cost</th>
                        <th>Status</th>
                        <th>Source / Ref</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (!$acquisitionHistory): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No flock-entry history recorded.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($acquisitionHistory as $row): ?>
                            <tr>
                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$row['acquisition_date']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        poultry_acquisition_type_label(
                                            $type,
                                            (string)$row['acquisition_type']
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php echo (int)$row['age_days']; ?> days
                                </td>

                                <td class="text-end">
                                    <?php
                                    echo number_format(
                                        (int)$row['quantity']
                                    );
                                    ?>
                                </td>

                                <td class="text-end">
                                    <?php
                                    echo $moneyOrDash(
                                        $row['total_cost']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php if (empty($row['voided_at'])): ?>
                                        <span class="badge bg-success">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            Voided
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php
                                    $sourceReference = trim(
                                        (string)($row['source_name'] ?? '')
                                        . ' '
                                        . (string)($row['reference_no'] ?? '')
                                    );

                                    echo htmlspecialchars(
                                        $sourceReference !== ''
                                            ? $sourceReference
                                            : '-'
                                    );
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $activeAcquisitionRows = array_values(
                array_filter(
                    $acquisitionHistory,
                    static function (array $row): bool {
                        return empty($row['voided_at']);
                    }
                )
            );
            ?>

            <?php if ($canManageCycleOperations && $activeAcquisitionRows): ?>
                <div class="border rounded p-3">
                    <h6>Correct an Erroneous Entry</h6>

                    <p class="text-muted small">
                        Use this only for a mistaken or duplicated acquisition.
                        The original row remains in audit history.
                    </p>

                    <form
                        method="post"
                        data-confirm="Void this acquisition entry? It will remain visible in audit history."
                        data-confirm-title="Confirm acquisition correction"
                        data-confirm-button="Void entry"
                    >
                        <?php echo csrf_field(); ?>

                        <input
                            type="hidden"
                            name="action"
                            value="void_poultry_acquisition"
                        >

                        <input
                            type="hidden"
                            name="cycle_id"
                            value="<?php echo (int)$cycleId; ?>"
                        >

                        <div class="mb-2">
                            <label class="form-label">
                                Acquisition Entry
                            </label>

                            <select
                                class="form-select"
                                name="acquisition_id"
                                required
                            >
                                <option value="">
                                    Select entry
                                </option>

                                <?php foreach ($activeAcquisitionRows as $row): ?>
                                    <option value="<?php echo (int)$row['id']; ?>">
                                        #<?php echo (int)$row['id']; ?>
                                        ·
                                        <?php
                                        echo htmlspecialchars(
                                            (string)$row['acquisition_date']
                                        );
                                        ?>
                                        ·
                                        <?php
                                        echo number_format(
                                            (int)$row['quantity']
                                        );
                                        ?>
                                        birds
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">
                                Correction Reason
                            </label>

                            <input
                                class="form-control"
                                name="void_reason"
                                maxlength="255"
                                required
                                placeholder="Explain the mistaken or duplicated entry"
                            >
                        </div>

                        <button
                            class="btn btn-outline-warning"
                            type="submit"
                        >
                            Void Erroneous Entry
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="small text-muted mt-3">
                Initial flock entry is recorded during Create Cycle.
                Manage Cycle does not create a second onboarding entry.
            </div>
        </div>
    </div>

    <div id="lifecycle" class="card mb-3 section-anchor">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Biological Lifecycle</strong>

            <?php if ($canManageCycleOperations): ?>
                <span class="badge bg-primary">Manage here</span>
            <?php else: ?>
                <span class="badge bg-info">Read only</span>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <p class="text-muted small">
                Lifecycle is explicit and dated. It is never inferred
                from bird age, feed, mortality, egg output, or cycle status.
            </p>

            <div class="table-responsive mb-3">
                <table class="table table-sm compact-table mb-0">
                    <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Notes</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (!$phaseHistory): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                Lifecycle history is not yet defined.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($phaseHistory as $row): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            poultry_lifecycle_phase_label(
                                                $type,
                                                (string)$row['phase']
                                            )
                                        );
                                        ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$row['start_date']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        (string)(
                                            $row['end_date']
                                            ?? 'Open'
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        (string)(
                                            $row['notes']
                                            ?? '-'
                                        )
                                    );
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$phaseHistory): ?>
                <div class="alert alert-secondary mb-0">
                    New V3 poultry cycles record their starting biological
                    stage during Create Cycle. No replacement starting-stage
                    form is exposed here.
                </div>

            <?php elseif (
                !$isClosed
                && $canManageCycleOperations
                && $currentPhase !== null
                && !empty($nextPhases)
            ): ?>
                <div class="border rounded p-3">
                    <h6>
                        Move from
                        <?php
                        echo htmlspecialchars(
                            poultry_lifecycle_phase_label(
                                $type,
                                (string)$currentPhase['phase']
                            )
                        );
                        ?>
                    </h6>

                    <p class="text-muted small">
                        The current stage closes on the day before
                        the transition date. History is appended,
                        never rewritten.
                    </p>

                    <form
                        method="post"
                        data-confirm="Record this lifecycle transition?"
                        data-confirm-title="Confirm lifecycle transition"
                        data-confirm-button="Record transition"
                    >
                        <?php echo csrf_field(); ?>

                        <input
                            type="hidden"
                            name="action"
                            value="transition_poultry_phase"
                        >

                        <input
                            type="hidden"
                            name="cycle_id"
                            value="<?php echo (int)$cycleId; ?>"
                        >

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">
                                    Next Stage
                                </label>

                                <select
                                    class="form-select"
                                    name="phase"
                                    required
                                >
                                    <?php foreach ($nextPhases as $phaseValue => $phaseName): ?>
                                        <option
                                            value="<?php echo htmlspecialchars((string)$phaseValue, ENT_QUOTES); ?>"
                                        >
                                            <?php echo htmlspecialchars((string)$phaseName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">
                                    Transition Date
                                </label>

                                <input
                                    class="form-control"
                                    type="date"
                                    name="phase_start_date"
                                    required
                                >
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">
                                    Notes
                                </label>

                                <input
                                    class="form-control"
                                    name="phase_notes"
                                >
                            </div>
                        </div>

                        <button
                            class="btn btn-success mt-3"
                            type="submit"
                        >
                            Record Transition
                        </button>
                    </form>
                </div>

            <?php elseif (
                !$isClosed
                && $currentPhase !== null
                && empty($nextPhases)
            ): ?>
                <div class="alert alert-info mb-0">
                    <strong>
                        <?php echo htmlspecialchars($phaseLabel); ?>
                    </strong>
                    is the terminal biological stage for this cycle.
                    When production is actually complete, use
                    <a href="#end-production" class="alert-link">
                        End Production
                    </a>
                    below. That canonical operation closes the cycle
                    and the open biological stage together.
                </div>

            <?php elseif (
                !$isClosed
                && !$canManageCycleOperations
                && $currentPhase !== null
            ): ?>
                <div class="alert alert-secondary mb-0">
                    You can review lifecycle history here.
                    Stage changes are available to the Platform Owner
                    or Farm Admin.
                </div>

            <?php elseif (!$isClosed && $currentPhase === null): ?>
                <div class="alert alert-secondary mb-0">
                    Lifecycle history exists but there is no open
                    biological stage. No stage is inferred automatically.
                </div>
            <?php endif; ?>
        </div>
    </div>

  <?php if($type==='layer' && $rearingEconomics!==null): ?>
  <div id="economics" class="card mb-3 section-anchor">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Layer Rearing & Production-Entry Economics</strong></div>
    <div class="card-body">
      <p class="text-muted mb-3"><?php echo htmlspecialchars($rearingEconomics['message']); ?></p>
      <?php if(!$rearingEconomics['available']): ?>
        <div class="alert alert-secondary mb-0">No economics are invented. Record a defensible lifecycle/acquisition history before rearing economics can be interpreted.</div>
      <?php elseif($rearingEconomics['mode']==='pol'): ?>
        <div class="row g-3">
          <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Entry Model</div><h5>Purchased Point-of-Lay</h5></div></div></div>
          <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Production-entry Acquisition Basis</div><h5><?php echo $moneyOrDash($rearingEconomics['rearing_investment']); ?></h5></div></div></div>
          <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Effective Acquisition Basis / Bird</div><h5><?php echo $moneyOrDash($rearingEconomics['investment_per_surviving_bird']); ?></h5></div></div></div>
        </div>
        <div class="mt-3"><strong>On-farm rearing investment:</strong> Not applicable. No on-farm rearing history is created for purchased POL birds.</div>
      <?php else: ?>
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Rearing Window</div><h6><?php echo htmlspecialchars($rearingEconomics['rearing_phase']['start_date'].' → '.$rearingEconomics['rearing_phase']['end_date']); ?></h6></div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Known Attributable Rearing Cost</div><h5><?php echo $moneyOrDash($rearingEconomics['known_attributable_rearing_cost']); ?></h5></div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Attributed Rearing Investment</div><h5><?php echo $moneyOrDash($rearingEconomics['rearing_investment']); ?></h5></div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Investment / Surviving Production Bird</div><h5><?php echo $moneyOrDash($rearingEconomics['investment_per_surviving_bird']); ?></h5></div></div></div>
        </div>
        <div class="explain-row"><span class="label">Bird acquisition basis</span><strong><?php echo $moneyOrDash($rearingEconomics['acquisition_cost']); ?></strong></div>
        <div class="explain-row"><span class="label">Feed actually consumed during Rearing</span><strong><?php echo $moneyOrDash($rearingEconomics['feed_consumed_cost']); ?></strong></div>
        <div class="explain-row"><span class="label">Medication/Vaccine, supplements & consumables USED</span><strong><?php echo $moneyOrDash($rearingEconomics['inventory_operating_cost']); ?></strong></div>
        <div class="explain-row"><span class="label">Direct non-feed expenses</span><strong><?php echo $moneyOrDash($rearingEconomics['direct_expenses']); ?></strong></div>
        <div class="explain-row"><span class="label">Explicit shared-expense allocations to this cycle</span><strong><?php echo $moneyOrDash($rearingEconomics['allocated_shared_expenses']); ?></strong></div>
        <div class="explain-row"><span class="label">Surviving flock at Production entry</span><strong><?php echo $rearingEconomics['production_entry_headcount']===null?'-':number_format((int)$rearingEconomics['production_entry_headcount']); ?></strong></div>
        <?php if($rearingEconomics['production_entry_headcount_source']): ?><div class="small text-muted mt-2">Headcount source: <?php echo htmlspecialchars($rearingEconomics['production_entry_headcount_source']); ?></div><?php endif; ?>
        <?php if((float)$rearingEconomics['unallocated_shared_expense_pool']>0): ?><div class="alert alert-warning mt-3 mb-0"><strong>Unallocated shared Layer expense pool in this rearing window:</strong> <?php echo $moneyOrDash($rearingEconomics['unallocated_shared_expense_pool']); ?>. It is disclosed but not silently assigned to this cycle.</div><?php endif; ?>
      <?php endif; ?>
      <?php if(!empty($rearingEconomics['warnings'])): ?>
        <?php $visibleWarnings=array_values(array_filter($rearingEconomics['warnings'],static function($warning) use ($rearingEconomics): bool {
          return !((float)$rearingEconomics['unallocated_shared_expense_pool']>0 && str_starts_with((string)$warning,'Unallocated shared Layer expenses exist in the Rearing window.'));
        })); ?>
        <?php if($visibleWarnings): ?><div class="mt-3"><?php foreach($visibleWarnings as $w): ?><div class="alert alert-warning py-2 mb-2"><?php echo htmlspecialchars($w); ?></div><?php endforeach; ?></div><?php endif; ?>
      <?php endif; ?>
      <div class="small text-muted mt-3">This is a read layer over recorded source transactions. It does not alter monthly profitability, Bird Cost Basis, inventory, expenses, mortality records, or lifecycle history.</div>
    </div>
  </div>

  <div id="economic-basis" class="card mb-3 section-anchor">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Production-Entry Economic Basis</strong></div>
    <div class="card-body">
      <div class="small text-muted mb-3">Production-Entry Economic Basis is accumulated attributable rearing investment per surviving bird at production entry. It is a separate management-costing measure and does not replace Bird Cost Basis used for mortality valuation.</div>
      <?php if($latestProductionEntrySnapshot): ?>
        <?php
          $changed=$productionEntryCandidate && !empty($productionEntryCandidate['ready']) && !hash_equals((string)$latestProductionEntrySnapshot['source_fingerprint'],(string)$productionEntryCandidate['source_fingerprint']);
          $investmentChanged=$productionEntryCandidate && !empty($productionEntryCandidate['ready']) && abs((float)$productionEntryCandidate['attributed_investment']-(float)$latestProductionEntrySnapshot['attributed_investment'])>0.0049;
          $flockChanged=$productionEntryCandidate && !empty($productionEntryCandidate['ready']) && (int)$productionEntryCandidate['production_entry_headcount']!==(int)$latestProductionEntrySnapshot['production_entry_headcount'];
        ?>
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="text-muted small">Current Approved Version</div><h5>V<?php echo (int)$latestProductionEntrySnapshot['version_no']; ?> · <?php echo htmlspecialchars(ucfirst($latestProductionEntrySnapshot['snapshot_status'])); ?></h5></div>
          <div class="col-md-3"><div class="text-muted small">Approved Attributed Investment</div><h5><?php echo $moneyOrDash($latestProductionEntrySnapshot['attributed_investment']); ?></h5></div>
          <div class="col-md-3"><div class="text-muted small">Approved Production-entry Flock</div><h5><?php echo number_format((int)$latestProductionEntrySnapshot['production_entry_headcount']); ?></h5></div>
          <div class="col-md-3"><div class="text-muted small">Approved Economic Basis / Entry Bird</div><h5><?php echo $moneyOrDash($latestProductionEntrySnapshot['investment_per_entry_bird']); ?></h5></div>
        </div>
        <?php if($changed): ?>
          <div class="alert alert-warning"><strong>Historical source economics changed after the latest approval.</strong>
            <?php if($investmentChanged): ?><div class="mt-2"><strong>Attributed investment changed:</strong> current <?php echo $moneyOrDash($productionEntryCandidate['attributed_investment']); ?> vs approved V<?php echo (int)$latestProductionEntrySnapshot['version_no']; ?> <?php echo $moneyOrDash($latestProductionEntrySnapshot['attributed_investment']); ?>.</div><?php endif; ?>
            <?php if($flockChanged): ?><div class="mt-1"><strong>Production-entry flock changed:</strong> current <?php echo number_format((int)$productionEntryCandidate['production_entry_headcount']); ?> vs approved V<?php echo (int)$latestProductionEntrySnapshot['version_no']; ?> <?php echo number_format((int)$latestProductionEntrySnapshot['production_entry_headcount']); ?>.</div><?php endif; ?>
            <?php if(!$investmentChanged && !$flockChanged): ?><div class="mt-2">Source records changed, but the approved attributed investment and production-entry flock remain numerically unchanged.</div><?php endif; ?>
            <div class="mt-1">Current economic basis / bird is <?php echo $moneyOrDash($productionEntryCandidate['investment_per_entry_bird']); ?>; approved V<?php echo (int)$latestProductionEntrySnapshot['version_no']; ?> is <?php echo $moneyOrDash($latestProductionEntrySnapshot['investment_per_entry_bird']); ?>. Review the corrected source records before approving a revision.</div>
          </div>
        <?php elseif($productionEntryCandidate && !empty($productionEntryCandidate['ready'])): ?>
          <div class="alert alert-success">Current source-derived economics match the latest approved version.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-secondary">No approved Production-Entry Economic Basis exists yet. Lifecycle and source accounting remain independent from this approval.</div>
      <?php endif; ?>

      <?php if($productionEntryCandidate && !empty($productionEntryCandidate['ready'])): ?>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><div class="text-muted small">Current Source-Derived Attributed Investment</div><strong><?php echo $moneyOrDash($productionEntryCandidate['attributed_investment']); ?></strong></div>
          <div class="col-md-4"><div class="text-muted small">Current Production-entry Flock</div><strong><?php echo number_format((int)$productionEntryCandidate['production_entry_headcount']); ?></strong></div>
          <div class="col-md-4"><div class="text-muted small">Current Source-Derived Economic Basis / Bird</div><strong><?php echo $moneyOrDash($productionEntryCandidate['investment_per_entry_bird']); ?></strong></div>
        </div>
        <?php $needsApproval=!$latestProductionEntrySnapshot || !hash_equals((string)$latestProductionEntrySnapshot['source_fingerprint'],(string)$productionEntryCandidate['source_fingerprint']); ?>
        <?php if($needsApproval && $canManageCycleOperations): ?>
        <form method="post" class="border rounded p-3 mb-3" data-confirm="Approve this source-derived Production-Entry Economic Basis as an immutable version?" data-confirm-title="Confirm economic basis" data-confirm-button="Approve version">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="approve_production_entry_basis">
          <input type="hidden" name="cycle_id" value="<?php echo (int)$cycleId; ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Approval / Revision Category</label>
              <select class="form-select" name="revision_category" required>
                <?php if(!$latestProductionEntrySnapshot): ?><option value="production_entry_confirmation">Production-entry confirmation</option><?php else: ?>
                <option value="">Select correction category</option>
                <option value="source_transaction_correction">Source transaction correction</option>
                <option value="missing_historical_transaction">Missing historical transaction</option>
                <option value="allocation_correction">Allocation correction</option>
                <option value="production_entry_headcount_correction">Production-entry headcount correction</option>
                <option value="administrative_correction">Administrative correction</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Revision Reason <?php echo $latestProductionEntrySnapshot?'(required)':'(optional)'; ?></label>
              <input class="form-control" name="revision_reason" maxlength="500" <?php echo $latestProductionEntrySnapshot?'required':''; ?> placeholder="Explain the correction; do not replace source accounting here">
            </div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success w-100" type="submit"><?php echo $latestProductionEntrySnapshot?'Approve Revised Basis':'Confirm Entry Basis'; ?></button></div>
          </div>
          <div class="small text-muted mt-2">Approval freezes this version only. Future source corrections are detected and require a new approved version; Bird Cost Basis is not changed.</div>
        </form>
        <?php elseif($needsApproval): ?>
        <div class="alert alert-secondary">
          You can review the current and approved economic basis here.
          Approval or revision is available to the Platform Owner or Farm Admin.
        </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-warning"><strong>Basis pending:</strong> <?php echo htmlspecialchars($productionEntryCandidate['reason']??'Source-derived production-entry economics are not ready.'); ?></div>
      <?php endif; ?>

      <?php if($productionEntrySnapshots): ?>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-1 mb-2">
        <strong>Approved Basis History</strong>
        <span class="small text-muted">Newest approved version first · previous versions remain immutable</span>
      </div>
      <div class="table-responsive"><table class="table table-sm compact-table basis-history-table mb-0">
        <thead><tr><th>Version</th><th>Status</th><th>Entry Date</th><th class="text-end">Investment</th><th class="text-end">Entry Flock</th><th class="text-end">Basis / Bird</th><th>Revision Details</th><th>Approved</th></tr></thead>
        <tbody><?php foreach($productionEntrySnapshots as $snap): ?><tr>
          <td class="history-version">V<?php echo (int)$snap['version_no']; ?><?php if((int)$snap['version_no']===(int)$latestProductionEntrySnapshot['version_no']): ?><span class="badge bg-success ms-1">Current</span><?php endif; ?></td><td><?php echo htmlspecialchars(ucfirst($snap['snapshot_status'])); ?></td>
          <td class="history-number"><?php echo htmlspecialchars($snap['production_entry_date']); ?></td><td class="text-end history-number"><?php echo $moneyOrDash($snap['attributed_investment']); ?></td>
          <td class="text-end history-number"><?php echo number_format((int)$snap['production_entry_headcount']); ?></td><td class="text-end history-number"><?php echo $moneyOrDash($snap['investment_per_entry_bird']); ?></td>
          <td><span class="history-category"><?php echo htmlspecialchars(str_replace('_',' ',(string)$snap['revision_category'])); ?></span><?php if(!empty($snap['revision_reason'])): ?><span class="history-reason"><?php echo htmlspecialchars($snap['revision_reason']); ?></span><?php endif; ?></td>
          <td class="history-approved"><?php echo htmlspecialchars($snap['approved_at']); ?><?php echo !empty($snap['approved_by_name'])?'<span class="history-reason">'.htmlspecialchars($snap['approved_by_name']).'</span>':''; ?></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

    <div class="card mb-3" id="end-production">
        <div class="card-header">
            <strong>End Production</strong>
        </div>

        <div class="card-body">

            <?php if ($isClosed): ?>

                <div class="alert alert-success mb-0">
                    <strong>
                        This production cycle has ended.
                    </strong>

                    <?php if (!empty($cycle['close_date'])): ?>
                        Production ended on
                        <?php
                        echo htmlspecialchars(
                            (string)$cycle['close_date']
                        );
                        ?>.
                    <?php endif; ?>

                    <?php if ($cycle['closing_headcount'] !== null): ?>
                        Closing live population:
                        <strong>
                            <?php
                            echo number_format(
                                (int)$cycle['closing_headcount']
                            );
                            ?>
                        </strong>.
                    <?php endif; ?>
                </div>

            <?php elseif (!$canEndProduction): ?>

                <div class="alert alert-secondary mb-0">
                    This cycle is active.
                    Only the Platform Owner or Farm Admin
                    can end production.
                </div>

            <?php elseif ($populationReadError !== ''): ?>

                <div class="alert alert-danger mb-0">
                    <strong>
                        End Production is unavailable.
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $populationReadError
                    );
                    ?>
                </div>

            <?php elseif ($populationState === null): ?>

                <div class="alert alert-warning mb-0">
                    <strong>
                        Population cutover is required
                        before End Production.
                    </strong>

                    <div class="mt-2">
                        This existing cycle has not yet entered
                        V3 population tracking. Confirm its
                        physically verified live population first.
                        The system will not infer or fabricate
                        a population baseline.
                    </div>

                    <div class="mt-3">
                        <a
                            class="btn btn-outline-warning btn-sm"
                            href="<?php echo BASE_URL; ?>/management/production_cycles.php#population-cutover"
                        >
                            Complete V3 Population Cutover
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <p class="mb-2">
                    Ending production closes this cycle and
                    records its canonical live population as
                    the closing headcount.
                </p>

                <?php if ($currentPhase !== null): ?>
                    <p class="mb-2">
                        The current
                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $phaseLabel
                            );
                            ?>
                        </strong>
                        biological stage will end on the
                        same date.
                    </p>
                <?php else: ?>
                    <p class="mb-2 text-muted">
                        No biological stage is currently open.
                        The system will not invent one.
                    </p>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>
                        Ending production does not remove birds
                        from physical population.
                    </strong>

                    Sales, mortality, cull, slaughter and
                    transfer-out remain separate population events.
                </div>

                <form
                    method="post"
                    class="row g-3 align-items-end"
                    data-confirm="<?php echo htmlspecialchars($confirmationText, ENT_QUOTES); ?>"
                    data-confirm-title="Confirm End Production"
                    data-confirm-button="End Production"
                >
                    <?php echo csrf_field(); ?>

                    <input
                        type="hidden"
                        name="action"
                        value="end_production"
                    >

                    <input
                        type="hidden"
                        name="cycle_id"
                        value="<?php echo (int)$cycleId; ?>"
                    >

                    <div class="col-md-6">
                        <label class="form-label">
                            Production End Date
                        </label>

                        <input
                            class="form-control"
                            type="date"
                            name="end_date"
                            value="<?php echo htmlspecialchars($endDateForm, ENT_QUOTES); ?>"
                            required
                        >
                    </div>

                    <div class="col-md-3">
                        <button
                            class="btn btn-warning w-100"
                            type="submit"
                        >
                            End Production
                        </button>
                    </div>

                </form>

            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>
