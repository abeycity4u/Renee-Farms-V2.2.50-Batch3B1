<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/production_cycle_service.php');
require_once(__DIR__ . '/../lib/poultry_cycle_onboarding.php');

requireLogin();
requireBusinessReportAccess();

$farmId = requireCurrentFarmId();

if (!(isPlatformOwner() || hasRole('farm_admin'))) {
    http_response_code(403);
    exit('Production-cycle management access required.');
}

$cycleId = (int)(
    $_GET['id']
    ?? $_POST['cycle_id']
    ?? 0
);

if ($cycleId <= 0) {
    http_response_code(404);
    exit('Production cycle not found.');
}

try {
    $cycle = production_cycle_get(
        $pdo,
        $farmId,
        $cycleId
    );
} catch (Throwable $error) {
    http_response_code(404);
    exit('Production cycle not found.');
}

$isPoultry =
    strtolower((string)$cycle['farm_type']) === 'poultry';

$acquisitionHistory = [];
$activeAcquisitions = [];
$currentAcquisition = null;

if ($isPoultry) {
    $acquisitionHistory = poultry_acquisition_history(
        $pdo,
        $farmId,
        $cycleId
    );

    $activeAcquisitions = array_values(
        array_filter(
            $acquisitionHistory,
            static function (array $row): bool {
                return empty($row['voided_at']);
            }
        )
    );

    if (count($activeAcquisitions) === 1) {
        $currentAcquisition =
            $activeAcquisitions[0];
    }
}

$currentTotalAcquisitionCost =
    $currentAcquisition !== null
    && $currentAcquisition['total_cost'] !== null
    && $currentAcquisition['total_cost'] !== ''
        ? (float)$currentAcquisition['total_cost']
        : null;

$flashSuccess = '';

if (!empty($_SESSION['production_cycle_edit_success'])) {
    $flashSuccess =
        (string)$_SESSION['production_cycle_edit_success'];

    unset($_SESSION['production_cycle_edit_success']);
}

$flashError = '';

$form = [
    'cycle_code' =>
        (string)$cycle['cycle_code'],
    'opening_headcount' =>
        (string)$cycle['opening_headcount'],
    'poultry_total_cost' =>
        $currentTotalAcquisitionCost === null
            ? ''
            : number_format(
                $currentTotalAcquisitionCost,
                2,
                '.',
                ''
            ),
    'expected_end_date' =>
        (string)($cycle['expected_end_date'] ?? ''),
    'notes' =>
        (string)($cycle['notes'] ?? ''),
    'correction_reason' =>
        '',
];

$populationRequestToken = trim(
    (string)(
        $_POST['population_request_token']
        ?? ''
    )
);

if (
    $populationRequestToken === ''
    || preg_match(
        '/^[a-f0-9]{32,64}$/',
        $populationRequestToken
    ) !== 1
) {
    $populationRequestToken =
        bin2hex(random_bytes(24));
}

$acquisitionRequestToken = trim(
    (string)(
        $_POST['acquisition_request_token']
        ?? ''
    )
);

if (
    $acquisitionRequestToken === ''
    || preg_match(
        '/^[a-f0-9]{32,64}$/',
        $acquisitionRequestToken
    ) !== 1
) {
    $acquisitionRequestToken =
        bin2hex(random_bytes(24));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_post();

    $form = [
        'cycle_code' =>
            trim((string)($_POST['cycle_code'] ?? '')),
        'opening_headcount' =>
            trim((string)($_POST['opening_headcount'] ?? '')),
        'poultry_total_cost' =>
            trim((string)($_POST['poultry_total_cost'] ?? '')),
        'expected_end_date' =>
            trim((string)($_POST['expected_end_date'] ?? '')),
        'notes' =>
            trim((string)($_POST['notes'] ?? '')),
        'correction_reason' =>
            trim((string)($_POST['correction_reason'] ?? '')),
    ];

    try {
        $openingHeadcount =
            production_cycle_nonnegative_int(
                $form['opening_headcount'],
                'Opening headcount'
            );

        if ($isPoultry && $openingHeadcount < 1) {
            throw new InvalidArgumentException(
                'Opening headcount must be at least 1 bird for a poultry cycle.'
            );
        }

        $totalAcquisitionCost = null;

        if ($isPoultry && $currentAcquisition !== null) {
            $totalAcquisitionCost =
                production_cycle_optional_money(
                    $form['poultry_total_cost'],
                    'total acquisition cost'
                );
        }

        $openingChanged =
            $openingHeadcount
            !== (int)$cycle['opening_headcount'];

        $totalChanged = false;

        if ($isPoultry && $currentAcquisition !== null) {
            $totalChanged =
                !(
                    (
                        $currentTotalAcquisitionCost === null
                        && $totalAcquisitionCost === null
                    )
                    || (
                        $currentTotalAcquisitionCost !== null
                        && $totalAcquisitionCost !== null
                        && abs(
                            $currentTotalAcquisitionCost
                            - $totalAcquisitionCost
                        ) < 0.005
                    )
                );
        }

        $initialCorrectionNeeded =
            $openingChanged
            || $totalChanged;

        if (
            $isPoultry
            && $initialCorrectionNeeded
            && $currentAcquisition === null
        ) {
            throw new PoultryAcquisitionException(
                'Opening Headcount or Total Acquisition Cost can be corrected here only when the cycle has exactly one active initial acquisition entry.'
            );
        }

        if (
            $initialCorrectionNeeded
            && strlen($form['correction_reason']) < 4
        ) {
            throw new InvalidArgumentException(
                'Enter a short correction reason when changing Opening Headcount or Total Acquisition Cost.'
            );
        }

        $pdo->beginTransaction();

        try {
            production_cycle_update_metadata(
                $pdo,
                $farmId,
                $cycleId,
                [
                    'cycle_code' =>
                        $form['cycle_code'],
                    'expected_end_date' =>
                        $form['expected_end_date'],
                    'notes' =>
                        $form['notes'],
                ],
                isset($_SESSION['user_id'])
                    ? (int)$_SESSION['user_id']
                    : null
            );

            if ($openingChanged) {
                production_cycle_correct_opening_headcount(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $openingHeadcount,
                    $form['correction_reason'],
                    $populationRequestToken,
                    isset($_SESSION['user_id'])
                        ? (int)$_SESSION['user_id']
                        : null
                );
            }

            if ($isPoultry && $initialCorrectionNeeded) {
                poultry_cycle_onboarding_correct_initial_acquisition(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $openingHeadcount,
                    $totalAcquisitionCost,
                    $form['correction_reason'],
                    $acquisitionRequestToken,
                    isset($_SESSION['user_id'])
                        ? (int)$_SESSION['user_id']
                        : null
                );

                $derivedBirdCostBasis =
                    poultry_acquisition_cost_per_bird(
                        $totalAcquisitionCost,
                        $openingHeadcount
                    );

                production_cycle_update_bird_cost_basis(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $derivedBirdCostBasis,
                    isset($_SESSION['user_id'])
                        ? (int)$_SESSION['user_id']
                        : null
                );
            }

            $pdo->commit();

        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }

        $_SESSION['production_cycle_edit_success'] =
            $initialCorrectionNeeded
                ? 'Cycle details and initial flock facts updated. The previous acquisition entry remains in audit history.'
                : 'Cycle details updated successfully.';

        header(
            'Location: '
            . BASE_URL
            . '/management/production_cycle_edit.php?id='
            . $cycleId
        );
        exit();

    } catch (Throwable $error) {
        $safe =
            $error instanceof InvalidArgumentException
            || $error instanceof ProductionCycleException
            || $error instanceof PoultryAcquisitionException
            || $error instanceof ProductionPopulationException;

        if (!$safe) {
            error_log(
                'Production cycle edit failed: '
                . $error->getMessage()
            );
        }

        $flashError = $safe
            ? $error->getMessage()
            : 'The cycle details could not be updated. No changes were saved.';
    }
}

$displayType = production_cycle_display_type(
    $pdo,
    $farmId,
    $cycle
);

$activeAcquisitionLabel = '';

if ($currentAcquisition !== null) {
    $activeAcquisitionLabel =
        poultry_acquisition_type_label(
            (string)$cycle['production_type'],
            (string)$currentAcquisition['acquisition_type']
        );
}

$currentCostPerBird =
    $currentAcquisition !== null
        ? poultry_acquisition_cost_per_bird(
            $currentTotalAcquisitionCost,
            (int)$currentAcquisition['quantity']
        )
        : null;
?>
<!doctype html>
<html lang="en">
<head>
    <title>Edit Production Cycle</title>
    <?php include(dirname(__DIR__) . '/navbar_head.php'); ?>
</head>

<body>
<?php include(dirname(__DIR__) . '/navbar.php'); ?>

<div class="container-fluid px-3 px-lg-4 py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small text-uppercase">
                Production Cycle
            </div>

            <h3 class="mb-1">
                Edit Cycle
            </h3>
        </div>

        <a
            class="btn btn-outline-secondary btn-sm"
            href="<?php echo BASE_URL; ?>/management/production_cycles.php#recent-cycles"
        >
            ← Production Cycles
        </a>
    </div>

    <?php if ($flashSuccess !== ''): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($flashSuccess); ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">
            <strong>Cycle Context</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">

                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Type</div>
                    <strong>
                        <?php echo htmlspecialchars($displayType); ?>
                    </strong>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Status</div>
                    <strong class="text-uppercase">
                        <?php
                        echo htmlspecialchars(
                            (string)$cycle['status']
                        );
                        ?>
                    </strong>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Cycle Start</div>
                    <strong>
                        <?php
                        echo htmlspecialchars(
                            (string)$cycle['start_date']
                        );
                        ?>
                    </strong>
                </div>

                <?php if ($isPoultry): ?>
                    <div class="col-6 col-lg-3">
                        <div class="text-muted small">
                            Current Acquisition Cost / Bird
                        </div>

                        <strong>
                            <?php
                            echo $currentCostPerBird === null
                                ? '-'
                                : '₦'
                                    . number_format(
                                        $currentCostPerBird,
                                        2
                                    );
                            ?>
                        </strong>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <form method="post">
        <?php echo csrf_field(); ?>

        <input
            type="hidden"
            name="cycle_id"
            value="<?php echo (int)$cycleId; ?>"
        >

        <input
            type="hidden"
            name="population_request_token"
            value="<?php echo htmlspecialchars($populationRequestToken, ENT_QUOTES); ?>"
        >

        <input
            type="hidden"
            name="acquisition_request_token"
            value="<?php echo htmlspecialchars($acquisitionRequestToken, ENT_QUOTES); ?>"
        >

        <div class="card mb-3">
            <div class="card-header">
                <strong>Cycle Details</strong>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">
                            Cycle Code
                        </label>

                        <input
                            class="form-control"
                            name="cycle_code"
                            maxlength="100"
                            value="<?php echo htmlspecialchars($form['cycle_code'], ENT_QUOTES); ?>"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Expected End
                        </label>

                        <input
                            class="form-control"
                            type="date"
                            name="expected_end_date"
                            value="<?php echo htmlspecialchars($form['expected_end_date'], ENT_QUOTES); ?>"
                        >

                        <div class="form-text">
                            Planning date only. It does not end production.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            Notes
                        </label>

                        <textarea
                            class="form-control"
                            name="notes"
                            rows="3"
                        ><?php echo htmlspecialchars($form['notes']); ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <strong>Opening &amp; Acquisition Correction</strong>
            </div>

            <div class="card-body">

                <div class="alert alert-info">
                    Use these fields to correct facts entered wrongly when
                    the cycle was created. A correction does not silently
                    erase the old acquisition record: the previous row remains
                    in audit history and the corrected row becomes active.
                </div>

                <?php if ($isPoultry && $currentAcquisition === null): ?>
                    <div class="alert alert-warning">
                        This poultry cycle does not currently have exactly one
                        active initial acquisition entry. Opening Headcount and
                        Total Acquisition Cost cannot be corrected from this
                        form until that acquisition history is reconciled.
                    </div>
                <?php endif; ?>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">
                            Opening Headcount
                        </label>

                        <?php if ($isPoultry && $currentAcquisition === null): ?>
                            <input
                                type="hidden"
                                name="opening_headcount"
                                value="<?php echo htmlspecialchars($form['opening_headcount'], ENT_QUOTES); ?>"
                            >
                        <?php endif; ?>

                        <input
                            class="form-control"
                            type="number"
                            min="<?php echo $isPoultry ? '1' : '0'; ?>"
                            step="1"
                            name="opening_headcount"
                            value="<?php echo htmlspecialchars($form['opening_headcount'], ENT_QUOTES); ?>"
                            <?php echo ($isPoultry && $currentAcquisition === null) ? 'disabled' : ''; ?>
                            required
                        >

                        <div class="form-text">
                            This corrects the original opening fact.
                            The V3 population baseline is not silently rewritten.
                            Existing Daily Records are separate historical source
                            records and are not silently rewritten by this correction.
                        </div>
                    </div>

                    <?php if ($isPoultry): ?>
                        <div class="col-md-6">
                            <label class="form-label">
                                Total Acquisition Cost (₦)
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                step="0.01"
                                name="poultry_total_cost"
                                value="<?php echo htmlspecialchars($form['poultry_total_cost'], ENT_QUOTES); ?>"
                                <?php
                                echo $currentAcquisition === null
                                    ? 'disabled'
                                    : (
                                        (string)$currentAcquisition['acquisition_type']
                                            !== 'internal_transfer'
                                            ? 'required'
                                            : ''
                                    );
                                ?>
                            >

                            <?php if ($currentAcquisition !== null): ?>
                                <div class="form-text">
                                    <?php
                                    echo htmlspecialchars(
                                        $activeAcquisitionLabel
                                    );
                                    ?>.
                                    Bird Cost Basis is recalculated automatically
                                    from Total Acquisition Cost ÷ Opening Headcount.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <label class="form-label">
                            Correction Reason
                        </label>

                        <input
                            class="form-control"
                            name="correction_reason"
                            maxlength="255"
                            value="<?php echo htmlspecialchars($form['correction_reason'], ENT_QUOTES); ?>"
                            placeholder="Required only when correcting Opening Headcount or Total Acquisition Cost"
                        >

                        <div class="form-text">
                            Required when either opening/acquisition value changes.
                            Example: “Total acquisition cost was entered as 1700 instead of 1700000.”
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button
                class="btn btn-primary"
                type="submit"
            >
                Save Changes
            </button>

            <a
                class="btn btn-outline-secondary"
                href="<?php echo BASE_URL; ?>/management/production_cycles.php#recent-cycles"
            >
                Cancel
            </a>
        </div>

    </form>
</div>

</body>
</html>
