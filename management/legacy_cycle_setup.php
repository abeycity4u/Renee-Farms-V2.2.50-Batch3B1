<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/production_cycle_service.php');
require_once(__DIR__ . '/../lib/production_population_intelligence.php');

requireLogin();
requireBusinessReportAccess();

$farmId =
    requireCurrentFarmId();

if (
    !isPlatformOwner()
    && !hasRole('farm_admin')
) {
    header(
        'Location: '
        . BASE_URL
        . '/no_access.php'
    );

    exit();
}

$populationBaselineTableExists = false;
$legacyCycles = [];
$trackedActiveCount = 0;
$errorMessage = null;
$flash = null;

$cutoverForm = [
    'cycle_id' => '',
    'baseline_date' => '',
    'baseline_quantity' => '',
    'notes' => '',
    'confirmed' => false,
];

$prgKey =
    'legacy_cycle_setup_prg';

$redirectPrg = static function (
    array $flashState,
    array $formState
) use (
    $prgKey
): void {
    $_SESSION[$prgKey] = [
        'flash' =>
            $flashState,
        'form' =>
            $formState,
    ];

    header(
        'Location: '
        . BASE_URL
        . '/management/legacy_cycle_setup.php#population-cutover',
        true,
        303
    );

    exit();
};

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_SESSION[$prgKey])
    && is_array($_SESSION[$prgKey])
) {
    $state =
        $_SESSION[$prgKey];

    unset(
        $_SESSION[$prgKey]
    );

    if (
        isset($state['flash'])
        && is_array($state['flash'])
    ) {
        $flash =
            $state['flash'];
    }

    if (
        isset($state['form'])
        && is_array($state['form'])
    ) {
        foreach (
            array_keys($cutoverForm)
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $state['form']
                )
            ) {
                $cutoverForm[$field] =
                    $state['form'][$field];
            }
        }
    }
}

try {
    $populationBaselineTableExists =
        (
            $pdo->query(
                "SHOW TABLES LIKE 'production_population_baselines'"
            )->rowCount()
            > 0
        );

    if (
        $_SERVER['REQUEST_METHOD']
        === 'POST'
    ) {
        if (
            !verify_csrf_token(
                $_POST['csrf_token']
                ?? ''
            )
        ) {
            http_response_code(419);
            exit(
                'Invalid request token.'
            );
        }

        $action =
            trim(
                (string)(
                    $_POST['action']
                    ?? ''
                )
            );

        $cycleId =
            (int)(
                $_POST['cycle_id']
                ?? 0
            );

        $baselineDate =
            trim(
                (string)(
                    $_POST['baseline_date']
                    ?? ''
                )
            );

        $baselineQuantityRaw =
            trim(
                (string)(
                    $_POST['baseline_quantity']
                    ?? ''
                )
            );

        $notes =
            trim(
                (string)(
                    $_POST['notes']
                    ?? ''
                )
            );

        $confirmed =
            (
                (string)(
                    $_POST['confirm_cutover']
                    ?? ''
                )
            )
            === '1';

        $cutoverForm = [
            'cycle_id' =>
                $cycleId > 0
                    ? (string)$cycleId
                    : '',

            'baseline_date' =>
                $baselineDate,

            'baseline_quantity' =>
                $baselineQuantityRaw,

            'notes' =>
                $notes,

            // Physical confirmation is never sticky after an error.
            'confirmed' =>
                false,
        ];

        if (
            $action
            !==
            'confirm_population_cutover'
        ) {
            $flash = [
                'type' => 'danger',
                'title' =>
                    'Unsupported legacy setup action.',
                'message' =>
                    'Refresh the page and try again.',
            ];

        } elseif (
            !$populationBaselineTableExists
        ) {
            $flash = [
                'type' => 'danger',
                'title' =>
                    'Population foundation is not available.',
                'message' =>
                    'Run the required database migrations before completing legacy cycle setup.',
            ];

        } elseif (!$confirmed) {
            $flash = [
                'type' => 'danger',
                'title' =>
                    'Physical population confirmation is required.',
                'message' =>
                    'Confirm that the entered headcount is the physically verified live population at the start of the selected date.',
            ];

        } else {
            try {
                production_cycle_cutover_population_v3(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $baselineDate,
                    $baselineQuantityRaw,
                    $notes !== ''
                        ? $notes
                        : null,
                    isset($_SESSION['user_id'])
                        ? (int)$_SESSION['user_id']
                        : null
                );

                $flash = [
                    'type' => 'success',
                    'title' =>
                        'Legacy cycle setup completed.',
                    'message' =>
                        'The selected cycle now uses its confirmed population starting point. Earlier historical records were not reconstructed or rewritten.',
                    'tip' =>
                        'Future population changes remain under the normal population ledger.',
                ];

                $cutoverForm = [
                    'cycle_id' => '',
                    'baseline_date' => '',
                    'baseline_quantity' => '',
                    'notes' => '',
                    'confirmed' => false,
                ];

            } catch (Throwable $error) {
                $safe =
                    $error
                        instanceof
                        InvalidArgumentException
                    ||
                    $error
                        instanceof
                        ProductionCycleException
                    ||
                    $error
                        instanceof
                        ProductionPopulationException;

                if (!$safe) {
                    error_log(
                        'Legacy cycle population setup failed: '
                        . $error->getMessage()
                    );
                }

                $flash = [
                    'type' => 'danger',
                    'title' =>
                        'Legacy cycle setup was not saved.',
                    'message' =>
                        $safe
                            ? $error->getMessage()
                            : 'The population starting point could not be completed. No baseline was changed.',
                ];
            }
        }

        $redirectPrg(
            $flash,
            $cutoverForm
        );
    }

    $snapshots =
        production_population_intelligence_active_cycle_snapshots(
            $pdo,
            $farmId,
            'both',
            $populationBaselineTableExists
        );

    foreach (
        $snapshots
        as $snapshot
    ) {
        if (
            (
                $snapshot['tracking_status']
                ?? ''
            )
            === 'canonical'
        ) {
            $trackedActiveCount++;
            continue;
        }

        $legacyCycles[] =
            $snapshot;
    }

} catch (Throwable $error) {
    error_log(
        'Legacy Cycle Setup page failed: '
        . $error->getMessage()
    );

    $errorMessage =
        'Legacy cycle setup could not be loaded right now. Please try again.';

    if (
        $_SERVER['REQUEST_METHOD']
        === 'POST'
    ) {
        $redirectPrg(
            [
                'type' => 'danger',
                'title' =>
                    'Legacy cycle setup could not be completed.',
                'message' =>
                    $errorMessage,
            ],
            $cutoverForm
        );
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        Legacy Cycle Setup - Farm Management System
    </title>
</head>

<body data-arrow-scroll-safe-scope>
<?php include(__DIR__ . '/../navbar.php'); ?>

<div class="container-fluid mt-4">

    <div class="card mb-3">
        <div
            class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3"
        >
            <div>
                <h4 class="mb-2">
                    <i class="bi bi-tools"></i>
                    Legacy Cycle Setup
                </h4>

                <p class="mb-0 text-muted">
                    Temporary setup for active production cycles
                    created before the current population-tracking contract.
                    New cycles do not use this page.
                </p>
            </div>

            <a
                class="btn btn-outline-secondary"
                href="<?php echo BASE_URL; ?>/management/production_cycles.php"
            >
                <i class="bi bi-arrow-left"></i>
                Production Cycles
            </a>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <?php
        renderNotification(
            $flash['type'] === 'danger'
                ? 'error'
                : $flash['type'],
            $flash['message'],
            $flash['title'] ?? null,
            $flash['tip'] ?? null
        );
        ?>
    <?php endif; ?>

    <?php if ($errorMessage !== null): ?>

        <?php
        renderNotification(
            'error',
            $errorMessage,
            'Could not load legacy cycle setup.',
            'Return to Production Cycles and try again.'
        );
        ?>

    <?php elseif (
        !$populationBaselineTableExists
    ): ?>

        <div class="alert alert-danger">
            <strong>
                Population foundation is not available.
            </strong>

            Run the required database migrations before
            using Legacy Cycle Setup.
        </div>

    <?php elseif (empty($legacyCycles)): ?>

        <div class="alert alert-success">
            <strong>
                No legacy population setup is required.
            </strong>

            Every active production cycle in this farm already
            uses the current population-tracking contract.
        </div>

    <?php else: ?>

        <div
            class="card mb-3"
            id="population-cutover"
        >
            <div
                class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"
            >
                <strong>
                    Confirm Legacy Population Starting Point
                </strong>

                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-warning text-dark">
                        <?php
                        echo number_format(
                            count($legacyCycles)
                        );
                        ?>
                        legacy active cycle(s)
                    </span>

                    <span class="badge bg-secondary">
                        <?php
                        echo number_format(
                            $trackedActiveCount
                        );
                        ?>
                        already current
                    </span>
                </div>
            </div>

            <div class="card-body">

                <div class="alert alert-warning">
                    <strong>
                        Use a physically verified live population.
                    </strong>

                    Do not copy a number from Daily Records,
                    Animal Registry, Sales, opening stock,
                    or another historical record merely to complete
                    this setup.
                </div>

                <form
                    method="post"
                    class="row g-3"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="confirm_population_cutover"
                    >

                    <div class="col-lg-6">
                        <label class="form-label">
                            Legacy Active Cycle
                        </label>

                        <select
                            class="form-select"
                            name="cycle_id"
                            required
                        >
                            <option value="">
                                Select cycle requiring setup
                            </option>

                            <?php foreach ($legacyCycles as $cycle): ?>
                                <option
                                    value="<?php echo (int)$cycle['cycle_id']; ?>"
                                    <?php
                                    echo (
                                        (string)$cutoverForm['cycle_id']
                                        ===
                                        (string)$cycle['cycle_id']
                                    )
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$cycle['cycle_code']
                                        . ' — '
                                        . ucfirst(
                                            (string)$cycle['farm_type']
                                        )
                                        . ' / '
                                        . ucfirst(
                                            (string)$cycle['production_type']
                                        )
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Starting Date
                        </label>

                        <input
                            class="form-control"
                            type="date"
                            name="baseline_date"
                            value="<?php echo htmlspecialchars((string)$cutoverForm['baseline_date'], ENT_QUOTES); ?>"
                            required
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            Physically Confirmed Population
                        </label>

                        <input
                            class="form-control"
                            type="number"
                            min="0"
                            step="1"
                            name="baseline_quantity"
                            value="<?php echo htmlspecialchars((string)$cutoverForm['baseline_quantity'], ENT_QUOTES); ?>"
                            required
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            Confirmation Notes
                        </label>

                        <textarea
                            class="form-control"
                            name="notes"
                            rows="2"
                            placeholder="Optional: how the live population was physically confirmed"
                        ><?php echo htmlspecialchars((string)$cutoverForm['notes']); ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                value="1"
                                name="confirm_cutover"
                                id="confirmLegacyPopulation"
                                required
                            >

                            <label
                                class="form-check-label"
                                for="confirmLegacyPopulation"
                            >
                                I confirm this is the physically
                                verified live population at the start
                                of the selected date, before that
                                date's population-changing activity
                                is recorded.
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button
                            class="btn btn-warning"
                            type="submit"
                        >
                            Confirm Legacy Population Setup
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php endif; ?>

</div>

</body>
</html>
