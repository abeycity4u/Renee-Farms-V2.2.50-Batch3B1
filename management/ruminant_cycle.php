<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../includes/notifications.php');
require_once(__DIR__ . '/../lib/production_cycle_service.php');
require_once(__DIR__ . '/../lib/production_population.php');
require_once(__DIR__ . '/../lib/ruminant_cycle_membership.php');
require_once(__DIR__ . '/../lib/ruminant_cycle_completion.php');

requireLogin();
requireBusinessReportAccess();

$farmId =
    requireCurrentFarmId();

if (
    !isPlatformOwner()
    && !hasRole('farm_admin')
    && !hasPermission(
        getUserType(),
        'production_cycles'
    )
) {
    header(
        'Location: '
        . BASE_URL
        . '/no_access.php'
    );

    exit();
}

$cycleId =
    (int)(
        $_GET['id']
        ?? $_POST['cycle_id']
        ?? 0
    );

if ($cycleId <= 0) {
    http_response_code(404);
    exit('Ruminant cycle not found.');
}

$canEndProduction =
    isPlatformOwner()
    || hasRole('farm_admin');

$today =
    function_exists('app_today')
        ? app_today()
        : date('Y-m-d');

$endDateForm =
    trim(
        (string)(
            $_POST['end_date']
            ?? $today
        )
    );

$flashError = '';
$flashSuccess = '';

$flashKey =
    'ruminant_cycle_flash_'
    . $cycleId;

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {
    require_valid_csrf_post();

    if (!$canEndProduction) {
        http_response_code(403);

        exit(
            'Production-cycle management access required.'
        );
    }

    $action =
        trim(
            (string)(
                $_POST['action']
                ?? ''
            )
        );

    try {
        if ($action !== 'end_production') {
            throw new InvalidArgumentException(
                'Unsupported production-cycle action.'
            );
        }

        $result =
            ruminant_cycle_end_production(
                $pdo,
                $farmId,
                $cycleId,
                $endDateForm,
                isset($_SESSION['user_id'])
                    ? (int)$_SESSION['user_id']
                    : null
            );

        $_SESSION[$flashKey] =
            'Production ended successfully on '
            . (string)$result['end_date']
            . '. Closing live population: '
            . number_format(
                (int)$result[
                    'closing_headcount'
                ]
            )
            . '. No animal lifecycle status or membership was changed automatically.';

        header(
            'Location: '
            . BASE_URL
            . '/management/ruminant_cycle.php?id='
            . $cycleId,
            true,
            303
        );

        exit();

    } catch (Throwable $error) {
        $safe =
            $error instanceof InvalidArgumentException
            || $error instanceof ProductionCycleException
            || $error instanceof ProductionPopulationException
            || $error instanceof RuminantCycleCompletionException;

        if (!$safe) {
            error_log(
                'Ruminant Manage Cycle action failed: '
                . $error->getMessage()
            );
        }

        $flashError =
            $safe
                ? $error->getMessage()
                : 'The requested cycle action could not be completed. '
                    . 'No confirmed cycle or animal history was changed.';
    }
}

if (
    isset($_SESSION[$flashKey])
    && $_SESSION[$flashKey] !== ''
) {
    $flashSuccess =
        (string)$_SESSION[$flashKey];

    unset(
        $_SESSION[$flashKey]
    );
}

try {
    $cycle =
        production_cycle_get(
            $pdo,
            $farmId,
            $cycleId
        );
} catch (Throwable $error) {
    http_response_code(404);
    exit('Ruminant cycle not found.');
}

if (
    strtolower(
        (string)$cycle['farm_type']
    ) !== 'ruminant'
) {
    http_response_code(404);
    exit('Ruminant cycle not found.');
}

$isClosed =
    strtolower(
        (string)$cycle['status']
    ) === 'closed';

$populationState = null;
$populationReadError = '';

try {
    $populationAsOf =
        $isClosed
        && !empty($cycle['close_date'])
            ? (string)$cycle['close_date']
            : $today;

    $populationState =
        production_population_state(
            $pdo,
            $farmId,
            $cycleId,
            $populationAsOf
        );
} catch (Throwable $error) {
    $populationReadError =
        $error instanceof ProductionPopulationException
        || $error instanceof InvalidArgumentException
            ? $error->getMessage()
            : 'Canonical population could not be read safely.';
}

$membershipBlockers = [];
$membershipPreviewError = '';

if (
    !$isClosed
    && production_cycle_valid_date(
        $endDateForm
    )
) {
    try {
        $membershipBlockers =
            ruminant_cycle_completion_membership_blockers(
                $pdo,
                $farmId,
                $cycleId,
                $endDateForm,
                false
            );
    } catch (Throwable $error) {
        $membershipPreviewError =
            'Membership readiness could not be read safely.';
    }
}

$confirmationText =
    'End this ruminant production cycle? '
    . 'This records the canonical live population as closing headcount. '
    . 'It does not mark animals as sold, dead, culled, slaughtered or transferred, '
    . 'and it does not rewrite animal cycle memberships.';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Ruminant Production Cycle</title>

    <?php include(dirname(__DIR__) . '/navbar_head.php'); ?>

    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/management-workspaces.css'); ?>"
    >
</head>

<body>
<?php include(dirname(__DIR__) . '/navbar.php'); ?>

<div class="container-fluid px-3 px-lg-4 py-3">

    <?php if ($flashSuccess !== ''): ?>
        <?php
        renderNotification(
            'success',
            $flashSuccess
        );
        ?>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
        <?php
        renderNotification(
            'error',
            $flashError
        );
        ?>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small text-uppercase">
                RUMINANT · PRODUCTION CYCLE
            </div>

            <h3 class="mb-1">
                <?php
                echo htmlspecialchars(
                    (string)$cycle['cycle_code']
                );
                ?>
            </h3>

            <div class="text-muted">
                <?php
                echo htmlspecialchars(
                    ucfirst(
                        (string)$cycle[
                            'production_type'
                        ]
                    )
                );
                ?>
                ·
                <?php
                echo htmlspecialchars(
                    ucfirst(
                        (string)$cycle['status']
                    )
                );
                ?>
            </div>
        </div>

        <a
            class="btn btn-outline-secondary btn-sm"
            href="<?php echo BASE_URL; ?>/management/production_cycles.php"
        >
            ← Production Cycles
        </a>
    </div>


    <div class="row g-3 mb-3">

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Start Date
                    </div>

                    <div class="fw-bold">
                        <?php
                        echo htmlspecialchars(
                            (string)$cycle['start_date']
                        );
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Opening Headcount
                    </div>

                    <div class="fw-bold">
                        <?php
                        echo number_format(
                            max(
                                0,
                                (int)(
                                    $cycle[
                                        'opening_headcount'
                                    ]
                                    ?? 0
                                )
                            )
                        );
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Canonical Live Population
                    </div>

                    <div class="fw-bold">
                        <?php if ($populationState !== null): ?>
                            <?php
                            echo number_format(
                                (int)$populationState[
                                    'quantity'
                                ]
                            );
                            ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Closing Headcount
                    </div>

                    <div class="fw-bold">
                        <?php
                        echo $cycle['closing_headcount']
                            !== null
                                ? number_format(
                                    (int)$cycle[
                                        'closing_headcount'
                                    ]
                                )
                                : '—';
                        ?>
                    </div>
                </div>
            </div>
        </div>

    </div>


    <div class="card mb-3">
        <div class="card-header">
            <strong>Animal Membership Readiness</strong>
        </div>

        <div class="card-body">

            <?php if ($isClosed): ?>

                <div class="alert alert-success mb-0">
                    This production cycle is closed.
                    Existing membership history remains preserved.
                </div>

            <?php elseif ($membershipPreviewError !== ''): ?>

                <div class="alert alert-danger mb-0">
                    <?php
                    echo htmlspecialchars(
                        $membershipPreviewError
                    );
                    ?>
                </div>

            <?php elseif (!$membershipBlockers): ?>

                <div class="alert alert-success mb-0">
                    No animal cycle membership extends beyond
                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $endDateForm
                        );
                        ?>
                    </strong>.
                    Membership history does not block End Production for this date.
                </div>

            <?php else: ?>

                <div class="alert alert-warning">
                    <strong>
                        <?php
                        echo number_format(
                            count(
                                $membershipBlockers
                            )
                        );
                        ?>
                        animal membership<?php echo count($membershipBlockers) === 1 ? '' : 's'; ?>
                        must be resolved first.
                    </strong>

                    <div class="mt-2">
                        End Production will not silently close, transfer,
                        sell, cull or otherwise exit these animals.
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Animal</th>
                            <th>Species</th>
                            <th>Status</th>
                            <th>Membership Start</th>
                            <th>Membership End</th>
                            <th>Action</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($membershipBlockers as $membership): ?>
                            <tr>
                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$membership[
                                            'tag_no'
                                        ]
                                    );
                                    ?>
                                </td>

                                <td class="text-capitalize">
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$membership[
                                            'species'
                                        ]
                                    );
                                    ?>
                                </td>

                                <td class="text-capitalize">
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$membership[
                                            'status'
                                        ]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$membership[
                                            'start_date'
                                        ]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $membership['end_date']
                                        !== null
                                            ? htmlspecialchars(
                                                (string)$membership[
                                                    'end_date'
                                                ]
                                            )
                                            : 'Open';
                                    ?>
                                </td>

                                <td>
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="<?php echo BASE_URL; ?>/ruminant/animal_view.php?id=<?php echo (int)$membership['animal_id']; ?>"
                                    >
                                        Animal Profile
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

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
                        This ruminant production cycle has ended.
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
                                (int)$cycle[
                                    'closing_headcount'
                                ]
                            );
                            ?>
                        </strong>.
                    <?php endif; ?>
                </div>

            <?php elseif (!$canEndProduction): ?>

                <div class="alert alert-secondary mb-0">
                    This cycle is active.
                    Only a Farm Admin can end production.
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
                        canonical V3 population tracking.
                        Confirm its physically verified live population first.
                        The system will not infer or fabricate a baseline.
                    </div>

                    <div class="mt-3">
                        <a
                            class="btn btn-outline-warning btn-sm"
                            href="<?php echo BASE_URL; ?>/management/legacy_cycle_setup.php#population-cutover"
                        >
                            Complete Legacy Cycle Setup
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <p class="mb-2">
                    Ending production closes this cycle and records
                    its canonical live population on the selected
                    date as the closing headcount.
                </p>

                <div class="alert alert-info">
                    <strong>
                        Ending production does not remove animals
                        from physical population.
                    </strong>

                    Sale, mortality/death, cull, slaughter and
                    movement to another production cycle remain
                    separate animal/population events.
                </div>

                <?php if ((int)$populationState['quantity'] > 0): ?>
                    <div class="alert alert-secondary">
                        Current canonical live population:
                        <strong>
                            <?php
                            echo number_format(
                                (int)$populationState[
                                    'quantity'
                                ]
                            );
                            ?>
                        </strong>.

                        A positive live population does not block
                        cycle completion. The canonical service will
                        calculate the closing headcount for the
                        selected end date.
                    </div>
                <?php endif; ?>

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

                <?php if ($membershipBlockers): ?>
                    <div class="form-text mt-2">
                        The selected date currently has unresolved
                        membership boundaries above. Submission will
                        remain blocked until those boundaries are
                        resolved or the selected end date is valid
                        for all recorded memberships.
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>
