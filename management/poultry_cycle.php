<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/production_cycle_service.php');
require_once(__DIR__ . '/../lib/poultry_cycle_lifecycle.php');
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

$canEndProduction =
    isPlatformOwner()
    || hasRole('farm_admin');

$flashSuccess = '';
$flashError = '';
$endDateForm = trim(
    (string)($_POST['end_date'] ?? '')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_post();

    $action = (string)($_POST['action'] ?? '');

    if (!$canEndProduction) {
        http_response_code(403);
        exit('Production-cycle management access required.');
    }

    try {
        if ($action !== 'end_production') {
            throw new InvalidArgumentException(
                'Unsupported production-cycle action.'
            );
        }

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

        $_SESSION['poultry_cycle_flash'] =
            $message;

        header(
            'Location: '
            . BASE_URL
            . '/management/poultry_cycle.php?id='
            . $cycleId
        );
        exit();
    } catch (Throwable $error) {
        $safe =
            $error instanceof InvalidArgumentException
            || $error instanceof ProductionCycleException
            || $error instanceof ProductionPopulationException
            || $error instanceof PoultryLifecycleException;

        if (!$safe) {
            error_log(
                'Poultry End Production failed: '
                . $error->getMessage()
            );
        }

        $flashError = $safe
            ? $error->getMessage()
            : 'Production could not be ended. No confirmed cycle, lifecycle, or population history was changed.';
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
                        Expected End
                    </div>

                    <div class="value">
                        <?php
                        echo htmlspecialchars(
                            (string)(
                                $cycle['expected_end_date']
                                ?? '-'
                            )
                        );
                        ?>
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

                <div class="col-md-3">
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

                <div class="col-md-3">
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

                <div class="col-md-3">
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

                <div class="col-md-3">
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
