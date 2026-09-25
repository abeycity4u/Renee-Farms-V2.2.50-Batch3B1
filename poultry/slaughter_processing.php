<?php

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/poultry_slaughter_permissions.php';
require_once dirname(__DIR__) . '/lib/poultry_slaughter_service.php';
require_once dirname(__DIR__) . '/lib/poultry_slaughter_workspace.php';

requireLogin();
poultry_slaughter_require(
    'view'
);

$farmId =
    requireCurrentFarmId();

$actorUserId =
    (int)(
        $_SESSION[
            'user_id'
        ]
        ?? 0
    );

$workspaceUrl =
    BASE_URL
    .
    '/poultry/slaughter_processing.php';

$h =
    static function (
        $value
    ): string {
        return
            htmlspecialchars(
                (string)$value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
    };

$money =
    static function (
        $value
    ): string {
        return
            number_format(
                (float)$value,
                2
            );
    };

$posted =
    $_SERVER[
        'REQUEST_METHOD'
    ]
    ===
    'POST'
        ? $_POST
        : [];

$formError =
    null;

$selectedBatchId =
    filter_var(
        $_GET[
            'batch_id'
        ]
        ?? (
            $posted[
                'batch_id'
            ]
            ?? 0
        ),
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    )
    ?: 0;

$batchRequestToken =
    trim(
        (string)(
            $posted[
                'batch_request_token'
            ]
            ?? ''
        )
    );

if ($batchRequestToken === '') {
    $batchRequestToken =
        bin2hex(
            random_bytes(
                16
            )
        );
}

$expenseRequestToken =
    trim(
        (string)(
            $posted[
                'expense_request_token'
            ]
            ?? ''
        )
    );

if ($expenseRequestToken === '') {
    $expenseRequestToken =
        bin2hex(
            random_bytes(
                16
            )
        );
}


$sharedExpenseRequestToken =
    trim(
        (string)(
            $posted[
                'shared_expense_request_token'
            ]
            ?? ''
        )
    );

if ($sharedExpenseRequestToken === '') {
    $sharedExpenseRequestToken =
        bin2hex(
            random_bytes(
                16
            )
        );
}


if (
    $_SERVER[
        'REQUEST_METHOD'
    ]
    ===
    'POST'
) {
    if (
        !verify_csrf_token(
            $_POST[
                'csrf_token'
            ]
            ?? ''
        )
    ) {
        $formError =
            'Your request token expired or is invalid. Refresh the page and try again.';

    } else {
        $action =
            strtolower(
                trim(
                    (string)(
                        $_POST[
                            'slaughter_action'
                        ]
                        ?? ''
                    )
                )
            );

        try {
            switch ($action) {
                case 'create_batch':
                    poultry_slaughter_require(
                        'create_batch'
                    );

                    $cycleId =
                        filter_var(
                            $_POST[
                                'cycle_id'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    $birdCount =
                        filter_var(
                            $_POST[
                                'bird_count'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    $slaughterDate =
                        trim(
                            (string)(
                                $_POST[
                                    'slaughter_date'
                                ]
                                ?? ''
                            )
                        );

                    $liveWeightRaw =
                        trim(
                            (string)(
                                $_POST[
                                    'live_weight_total_kg'
                                ]
                                ?? ''
                            )
                        );

                    $liveWeightTotalKg =
                        $liveWeightRaw === ''
                            ? null
                            : (float)$liveWeightRaw;

                    $notes =
                        trim(
                            (string)(
                                $_POST[
                                    'notes'
                                ]
                                ?? ''
                            )
                        );

                    $result =
                        poultry_slaughter_batch_create(
                            $pdo,
                            $farmId,
                            $cycleId,
                            $slaughterDate,
                            $birdCount,
                            $liveWeightTotalKg,
                            $notes === ''
                                ? null
                                : $notes,
                            $actorUserId,
                            $batchRequestToken
                        );

                    $selectedBatchId =
                        (int)(
                            $result[
                                'batch_id'
                            ]
                            ?? 0
                        );

                    $_SESSION[
                        'success'
                    ] =
                        !empty(
                            $result[
                                'idempotent'
                            ]
                        )
                            ? 'This slaughter batch was already recorded. The existing batch has been reopened safely.'
                            : 'Slaughter batch recorded. Live population and frozen pre-processing cost basis were updated together.';

                    header(
                        'Location: '
                        .
                        $workspaceUrl
                        .
                        '?batch_id='
                        .
                        $selectedBatchId
                    );
                    exit();


                case 'add_processing_expense':
                    poultry_slaughter_require(
                        'add_processing_expense'
                    );

                    $batchId =
                        filter_var(
                            $_POST[
                                'batch_id'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    $result =
                        poultry_slaughter_processing_expense_add(
                            $pdo,
                            $farmId,
                            $batchId,
                            trim(
                                (string)(
                                    $_POST[
                                        'expense_category'
                                    ]
                                    ?? ''
                                )
                            ),
                            $_POST[
                                'expense_amount'
                            ]
                            ?? '',
                            $_POST[
                                'expense_unit'
                            ]
                            ?? '',
                            trim(
                                (string)(
                                    $_POST[
                                        'expense_description'
                                    ]
                                    ?? ''
                                )
                            )
                            ?: null,
                            $actorUserId,
                            $expenseRequestToken
                        );

                    $_SESSION[
                        'success'
                    ] =
                        !empty(
                            $result[
                                'idempotent'
                            ]
                        )
                            ? 'This processing expense was already recorded. The existing canonical expense was reused safely.'
                            : 'Processing expense recorded in canonical farm expenses and linked to this slaughter batch.';

                    header(
                        'Location: '
                        .
                        $workspaceUrl
                        .
                        '?batch_id='
                        .
                        $batchId
                    );
                    exit();


                case 'link_shared_processing_expense':
                    poultry_slaughter_require(
                        'add_processing_expense'
                    );

                    $batchId =
                        filter_var(
                            $_POST[
                                'batch_id'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    $sharedExpenseId =
                        filter_var(
                            $_POST[
                                'shared_expense_id'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    $result =
                        poultry_slaughter_processing_shared_expense_link(
                            $pdo,
                            $farmId,
                            $batchId,
                            $sharedExpenseId,
                            $_POST[
                                'shared_expense_amount'
                            ]
                            ?? '',
                            $actorUserId,
                            $sharedExpenseRequestToken
                        );

                    $wasIdempotent =
                        (bool)(
                            $result[
                                'idempotent'
                            ]
                            ?? false
                        );

                    $_SESSION[
                        'success'
                    ] =
                        $wasIdempotent
                            ? 'This Shared processing allocation was already linked. The existing immutable slaughter snapshot was reused safely.'
                            : 'Shared processing allocation linked to this slaughter batch without changing the canonical Shared Cost Allocation.';

                    header(
                        'Location: '
                        .
                        $workspaceUrl
                        .
                        '?batch_id='
                        .
                        $batchId
                    );
                    exit();


                case 'finalize_cost_basis':
                    poultry_slaughter_require(
                        'finalize_cost_basis'
                    );

                    $batchId =
                        filter_var(
                            $_POST[
                                'batch_id'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    $result =
                        poultry_slaughter_cost_basis_finalize(
                            $pdo,
                            $farmId,
                            $batchId,
                            $actorUserId
                        );

                    $_SESSION[
                        'success'
                    ] =
                        !empty(
                            $result[
                                'idempotent'
                            ]
                        )
                            ? 'The slaughter cost basis was already finalized. No duplicate financial effect was created.'
                            : 'Slaughter cost basis finalized. Processed output can now be received into Inventory.';

                    header(
                        'Location: '
                        .
                        $workspaceUrl
                        .
                        '?batch_id='
                        .
                        $batchId
                    );
                    exit();


                case 'add_output':
                    poultry_slaughter_require(
                        'add_output'
                    );

                    $batchId =
                        filter_var(
                            $_POST[
                                'batch_id'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    $stockItemId =
                        filter_var(
                            $_POST[
                                'stock_item_id'
                            ]
                            ?? 0,
                            FILTER_VALIDATE_INT,
                            [
                                'options' => [
                                    'min_range' => 1,
                                ],
                            ]
                        )
                        ?: 0;

                    poultry_slaughter_output_add(
                        $pdo,
                        $farmId,
                        $batchId,
                        $stockItemId,
                        (float)(
                            $_POST[
                                'output_quantity'
                            ]
                            ?? 0
                        ),
                        (float)(
                            $_POST[
                                'cost_share_percent'
                            ]
                            ?? 0
                        ),
                        $actorUserId
                    );

                    $_SESSION[
                        'success'
                    ] =
                        'Processed output received into canonical Slaughter Output Inventory with its frozen cost allocation.';

                    header(
                        'Location: '
                        .
                        $workspaceUrl
                        .
                        '?batch_id='
                        .
                        $batchId
                    );
                    exit();


                default:
                    throw new InvalidArgumentException(
                        'Choose a valid Poultry slaughter action.'
                    );
            }

        } catch (
            PoultrySlaughterException
            |
            InvalidArgumentException $e
        ) {
            $formError =
                $e->getMessage();

        } catch (Throwable $e) {
            error_log(
                'Poultry slaughter workspace failure: '
                .
                $e->getMessage()
            );

            $formError =
                'The Poultry slaughter action could not be completed. No database details were exposed. Please review the entries and try again.';
        }
    }
}


$cycles =
    poultry_slaughter_workspace_cycles(
        $pdo,
        $farmId
    );

$outputItems =
    poultry_slaughter_workspace_output_items(
        $pdo,
        $farmId
    );

$batches =
    poultry_slaughter_workspace_batches(
        $pdo,
        $farmId
    );

if (
    $selectedBatchId < 1
    &&
    $batches
) {
    $selectedBatchId =
        (int)$batches[0][
            'id'
        ];
}

$selectedBatch =
    poultry_slaughter_workspace_batch_from_rows(
        $batches,
        $selectedBatchId
    );

if (
    $selectedBatchId > 0
    &&
    !$selectedBatch
) {
    $formError =
        $formError
        ??
        'The selected slaughter batch does not belong to this farm.';

    $selectedBatchId = 0;
}

$outputs =
    $selectedBatch
        ? poultry_slaughter_workspace_outputs(
            $pdo,
            $farmId,
            $selectedBatchId
        )
        : [];

$canCreateBatch =
    poultry_slaughter_can(
        'create_batch'
    );

$canAddExpense =
    poultry_slaughter_can(
        'add_processing_expense'
    );

$canFinalize =
    poultry_slaughter_can(
        'finalize_cost_basis'
    );

$canAddOutput =
    poultry_slaughter_can(
        'add_output'
    );

$selectedOpen =
    $selectedBatch
    &&
    (
        (string)$selectedBatch[
            'status'
        ]
        ===
        'open'
    );

$selectedFinalized =
    $selectedBatch
    &&
    !empty(
        $selectedBatch[
            'cost_basis_finalized_at'
        ]
    );

$selectedHasOutputs =
    $selectedBatch
    &&
    (
        (int)$selectedBatch[
            'output_count'
        ]
        > 0
    );

$sharedProcessingExpenses =
    (
        $selectedBatch
        &&
        $selectedOpen
        &&
        !$selectedFinalized
        &&
        !$selectedHasOutputs
        &&
        $canAddExpense
    )
        ? poultry_slaughter_workspace_shared_processing_expenses(
            $pdo,
            $farmId,
            $selectedBatchId
        )
        : [];

/*
 * navbar.php owns the platform-wide centered notification container.
 *
 * Redirect success messages already live in $_SESSION['success'].
 * Same-page validation errors are promoted to $_SESSION['error']
 * before navbar.php renders, while $posted remains available so the
 * user's submitted values stay on the form.
 */
if (
    $formError !== null
    &&
    trim(
        (string)$formError
    ) !== ''
) {
    $_SESSION[
        'error'
    ] =
        (string)$formError;
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include dirname(__DIR__) . '/navbar_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Poultry Slaughter Processing</title>
    <link
        rel="stylesheet"
        href="<?= $h(BASE_URL . versioned_asset('/assets/css/poultry-slaughter-processing.css')) ?>"
    >
</head>
<body>
<?php include dirname(__DIR__) . '/navbar.php'; ?>

<main class="container py-4 slaughter-workspace">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Poultry Slaughter Processing</h2>
            <p class="text-muted mb-0">
                Transform live Layer or Broiler population into costed processed Inventory.
                Slaughter is not a sale.
            </p>
        </div>

        <a
            class="btn btn-outline-primary"
            href="<?= $h(BASE_URL . '/management/sales_records.php') ?>"
        >
            <i class="bi bi-receipt me-1"></i>
            Processed Chicken Sales
        </a>
    </div>

    <div class="alert alert-info slaughter-boundary-note">
        <strong>Workflow:</strong>
        create slaughter batch →
        add processing expenses →
        finalize cost basis →
        receive processed outputs into Inventory →
        sell processed products from Sales Records.
        A processed-product sale does not reduce live flock again.
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <section class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-1">1. Create slaughter batch</h5>
                    <small class="text-muted">
                        This is the physical live-population exit.
                    </small>
                </div>

                <div class="card-body">
                    <?php if (!$canCreateBatch): ?>
                        <div class="alert alert-secondary mb-0">
                            You can view slaughter history, but batch creation is not delegated to your account.
                        </div>

                    <?php elseif (!$cycles): ?>
                        <div class="alert alert-warning mb-0">
                            No active Layer or Broiler production cycle is available.
                        </div>

                    <?php else: ?>
                        <form method="post">
                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="slaughter_action"
                                value="create_batch"
                            >

                            <input
                                type="hidden"
                                name="batch_request_token"
                                value="<?= $h($batchRequestToken) ?>"
                            >

                            <div class="mb-3">
                                <label class="form-label" for="slaughterCycle">
                                    Production cycle
                                </label>

                                <select
                                    class="form-select"
                                    id="slaughterCycle"
                                    name="cycle_id"
                                    required
                                >
                                    <option value="">Choose cycle</option>

                                    <?php foreach ($cycles as $cycle): ?>
                                        <?php
                                        $cycleId =
                                            (int)$cycle[
                                                'id'
                                            ];

                                        $postedCycleId =
                                            (int)(
                                                $posted[
                                                    'cycle_id'
                                                ]
                                                ?? 0
                                            );
                                        ?>
                                        <option
                                            value="<?= $cycleId ?>"
                                            <?= $postedCycleId === $cycleId ? 'selected' : '' ?>
                                        >
                                            <?= $h(ucfirst((string)$cycle['production_type'])) ?>
                                            ·
                                            <?= $h($cycle['cycle_code']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="slaughterDate">
                                        Slaughter date
                                    </label>

                                    <input
                                        class="form-control"
                                        id="slaughterDate"
                                        type="date"
                                        name="slaughter_date"
                                        required
                                        value="<?= $h($posted['slaughter_date'] ?? date('Y-m-d')) ?>"
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="birdCount">
                                        Birds slaughtered
                                    </label>

                                    <input
                                        class="form-control"
                                        id="birdCount"
                                        type="number"
                                        name="bird_count"
                                        min="1"
                                        step="1"
                                        required
                                        value="<?= $h($posted['bird_count'] ?? '') ?>"
                                    >
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label" for="liveWeight">
                                    Total live weight (kg)
                                    <span class="text-muted">optional</span>
                                </label>

                                <input
                                    class="form-control"
                                    id="liveWeight"
                                    type="number"
                                    name="live_weight_total_kg"
                                    min="0.0001"
                                    step="0.0001"
                                    value="<?= $h($posted['live_weight_total_kg'] ?? '') ?>"
                                >
                            </div>

                            <div class="mt-3">
                                <label class="form-label" for="slaughterNotes">
                                    Notes
                                </label>

                                <textarea
                                    class="form-control"
                                    id="slaughterNotes"
                                    name="notes"
                                    maxlength="255"
                                    rows="3"
                                ><?= $h($posted['notes'] ?? '') ?></textarea>
                            </div>

                            <button class="btn btn-success mt-3" type="submit">
                                <i class="bi bi-plus-circle me-1"></i>
                                Create Slaughter Batch
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-xl-7">
            <section class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <h5 class="mb-1">Batch workspace</h5>
                        <small class="text-muted">
                            Select a batch to continue its processing workflow.
                        </small>
                    </div>

                    <?php if ($batches): ?>
                        <form method="get" class="slaughter-batch-picker">
                            <div class="input-group">
                                <select
                                    class="form-select"
                                    name="batch_id"
                                    data-slaughter-batch-picker
                                    aria-label="Choose slaughter batch"
                                >
                                    <?php foreach ($batches as $batch): ?>
                                        <option
                                            value="<?= (int)$batch['id'] ?>"
                                            <?= (int)$batch['id'] === $selectedBatchId ? 'selected' : '' ?>
                                        >
                                            <?= $h($batch['batch_code']) ?>
                                            ·
                                            <?= $h(ucfirst((string)$batch['production_type'])) ?>
                                            ·
                                            <?= $h($batch['slaughter_date']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button
                                    class="btn btn-outline-secondary"
                                    type="submit"
                                >
                                    Open
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <?php if (!$selectedBatch): ?>
                        <div class="text-center text-muted py-5">
                            No Poultry slaughter batch has been recorded yet.
                        </div>

                    <?php else: ?>
                        <div class="row g-3 slaughter-stat-grid">
                            <div class="col-sm-6 col-lg-4">
                                <div class="slaughter-stat">
                                    <span>Batch</span>
                                    <strong><?= $h($selectedBatch['batch_code']) ?></strong>
                                </div>
                            </div>

                            <div class="col-sm-6 col-lg-4">
                                <div class="slaughter-stat">
                                    <span>Cycle</span>
                                    <strong>
                                        <?= $h(ucfirst((string)$selectedBatch['production_type'])) ?>
                                        ·
                                        <?= $h($selectedBatch['cycle_code']) ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="col-sm-6 col-lg-4">
                                <div class="slaughter-stat">
                                    <span>Birds</span>
                                    <strong><?= number_format((int)$selectedBatch['bird_count']) ?></strong>
                                </div>
                            </div>

                            <div class="col-sm-6 col-lg-4">
                                <div class="slaughter-stat">
                                    <span>Population before</span>
                                    <strong><?= number_format((int)$selectedBatch['population_before']) ?></strong>
                                </div>
                            </div>

                            <div class="col-sm-6 col-lg-4">
                                <div class="slaughter-stat">
                                    <span>Status</span>
                                    <strong><?= $h(ucfirst((string)$selectedBatch['status'])) ?></strong>
                                </div>
                            </div>

                            <div class="col-sm-6 col-lg-4">
                                <div class="slaughter-stat">
                                    <span>Cost basis</span>
                                    <strong>
                                        <?= $selectedFinalized ? 'Finalized' : 'Open' ?>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="slaughter-cost-card">
                                    <small>Capital transferred</small>
                                    <strong>₦<?= $money($selectedBatch['capital_basis_transferred']) ?></strong>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="slaughter-cost-card">
                                    <small>Embedded operating</small>
                                    <strong>₦<?= $money($selectedBatch['embedded_operating_basis_transferred']) ?></strong>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="slaughter-cost-card">
                                    <small>Processing operating</small>
                                    <strong>₦<?= $money($selectedBatch['processing_operating_cost']) ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="slaughter-full-cost mt-3">
                            <span>Frozen full-cost basis</span>
                            <strong>₦<?= $money($selectedBatch['full_cost_basis_amount']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <?php if ($selectedBatch): ?>
        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <section class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-1">2. Processing expenses</h5>
                        <small class="text-muted">
                            Canonical farm expenses linked immutably to this batch.
                        </small>
                    </div>

                    <div class="card-body">
                        <div class="d-flex gap-3 flex-wrap mb-3">
                            <span class="badge text-bg-light border">
                                <?= (int)$selectedBatch['processing_expense_count'] ?>
                                linked expense(s)
                            </span>

                            <span class="badge text-bg-light border">
                                Snapshot total:
                                ₦<?= $money($selectedBatch['processing_expense_snapshot_total']) ?>
                            </span>
                        </div>

                        <?php if (!$selectedOpen): ?>
                            <div class="alert alert-secondary mb-0">
                                This batch is no longer open for processing expenses.
                            </div>

                        <?php elseif ($selectedFinalized): ?>
                            <div class="alert alert-secondary mb-0">
                                Cost basis has already been finalized. Processing expenses are frozen.
                            </div>

                        <?php elseif ($selectedHasOutputs): ?>
                            <div class="alert alert-secondary mb-0">
                                Output Inventory already exists, so processing expenses cannot be changed.
                            </div>

                        <?php elseif (!$canAddExpense): ?>
                            <div class="alert alert-secondary mb-0">
                                Adding processing expenses is not delegated to your account.
                            </div>

                        <?php else: ?>
                            <form method="post">
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="slaughter_action"
                                    value="add_processing_expense"
                                >

                                <input
                                    type="hidden"
                                    name="batch_id"
                                    value="<?= (int)$selectedBatch['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="expense_request_token"
                                    value="<?= $h($expenseRequestToken) ?>"
                                >

                                <div class="mb-3">
                                    <label class="form-label" for="expenseCategory">
                                        Expense category
                                    </label>

                                    <select
                                        class="form-select"
                                        id="expenseCategory"
                                        name="expense_category"
                                        required
                                    >
                                        <option value="">
                                            Select processing expense category
                                        </option>

                                        <?php foreach (expense_category_options('slaughter_processing') as $expenseCategoryKey => $expenseCategoryLabel): ?>
                                            <option
                                                value="<?= $h($expenseCategoryKey) ?>"
                                                <?= (($posted['expense_category'] ?? '') === $expenseCategoryKey) ? 'selected' : '' ?>
                                            >
                                                <?= $h($expenseCategoryLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label" for="expenseAmount">
                                            Amount (₦)
                                        </label>

                                        <input
                                            class="form-control"
                                            id="expenseAmount"
                                            type="number"
                                            name="expense_amount"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            value="<?= $h($posted['expense_amount'] ?? '') ?>"
                                        >
                                    </div>

                                    <div class="col-sm-6">
                                        <label class="form-label" for="expenseUnit">
                                            Unit / quantity
                                        </label>

                                        <input
                                            class="form-control"
                                            id="expenseUnit"
                                            type="number"
                                            name="expense_unit"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            value="<?= $h($posted['expense_unit'] ?? '1') ?>"
                                        >
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label" for="expenseDescription">
                                        Description
                                    </label>

                                    <textarea
                                        class="form-control"
                                        id="expenseDescription"
                                        name="expense_description"
                                        rows="2"
                                        maxlength="255"
                                    ><?= $h($posted['expense_description'] ?? '') ?></textarea>
                                </div>

                                <button class="btn btn-outline-success mt-3" type="submit">
                                    Add Processing Expense
                                </button>
                            </form>

                                <div class="mt-4 pt-4 border-top">
                                    <h6 class="mb-1">
                                        Use existing Shared Cost Allocation
                                    </h6>

                                    <p class="text-muted small mb-3">
                                        Only Shared allocations proven to occur after this batch's frozen cost-basis snapshot are shown.
                                        Linking here does not rewrite the original Shared Cost Allocation.
                                    </p>

                                    <?php if (!$sharedProcessingExpenses): ?>
                                        <div class="alert alert-light border mb-0">
                                            No eligible Shared processing allocation is available for this batch.
                                        </div>
                                    <?php else: ?>
                                        <form method="post">
                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="slaughter_action"
                                                value="link_shared_processing_expense"
                                            >

                                            <input
                                                type="hidden"
                                                name="batch_id"
                                                value="<?= (int)$selectedBatch['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="shared_expense_request_token"
                                                value="<?= $h($sharedExpenseRequestToken) ?>"
                                            >

                                            <div class="mb-3">
                                                <label
                                                    class="form-label"
                                                    for="sharedExpenseId"
                                                >
                                                    Shared processing allocation
                                                </label>

                                                <select
                                                    class="form-select"
                                                    id="sharedExpenseId"
                                                    name="shared_expense_id"
                                                    required
                                                >
                                                    <option value="">
                                                        Select eligible Shared allocation
                                                    </option>

                                                    <?php foreach ($sharedProcessingExpenses as $sharedExpense): ?>
                                                        <option
                                                            value="<?= (int)$sharedExpense['expense_id'] ?>"
                                                            <?= ((int)($posted['shared_expense_id'] ?? 0) === (int)$sharedExpense['expense_id']) ? 'selected' : '' ?>
                                                        >
                                                            <?= $h($sharedExpense['public_reference'] ?: ('Expense #' . $sharedExpense['expense_id'])) ?>
                                                            — <?= $h($sharedExpense['category_label']) ?>
                                                            — <?= $h($sharedExpense['expense_date']) ?>
                                                            — Remaining ₦<?= $money($sharedExpense['remaining_amount']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label
                                                    class="form-label"
                                                    for="sharedExpenseAmount"
                                                >
                                                    Amount to link (₦)
                                                </label>

                                                <input
                                                    class="form-control"
                                                    id="sharedExpenseAmount"
                                                    type="number"
                                                    name="shared_expense_amount"
                                                    min="0.01"
                                                    step="0.01"
                                                    required
                                                    value="<?= $h($posted['shared_expense_amount'] ?? '') ?>"
                                                >

                                                <div class="form-text">
                                                    Enter an amount no greater than the remaining allocation shown above.
                                                    The server revalidates the allocation before linking.
                                                </div>
                                            </div>

                                            <button
                                                class="btn btn-outline-primary"
                                                type="submit"
                                            >
                                                Link Shared Processing Allocation
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-1">3. Finalize cost basis</h5>
                        <small class="text-muted">
                            Freeze capital, embedded operating and processing cost before output receipt.
                        </small>
                    </div>

                    <div class="card-body">
                        <?php if ($selectedFinalized): ?>
                            <div class="alert alert-success">
                                Finalized at
                                <strong><?= $h($selectedBatch['cost_basis_finalized_at']) ?></strong>.
                            </div>

                            <div class="slaughter-full-cost">
                                <span>Finalized full cost</span>
                                <strong>₦<?= $money($selectedBatch['full_cost_basis_amount']) ?></strong>
                            </div>

                        <?php elseif (!$selectedOpen): ?>
                            <div class="alert alert-secondary mb-0">
                                This batch is not open for cost finalization.
                            </div>

                        <?php elseif (!$canFinalize): ?>
                            <div class="alert alert-secondary mb-0">
                                Cost finalization is not delegated to your account.
                            </div>

                        <?php else: ?>
                            <div class="alert alert-warning">
                                Finalizing freezes the processing cost basis used by all later processed-output Inventory receipts.
                            </div>

                            <form
                                method="post"
                                data-confirm="Finalize this slaughter cost basis? Processing costs will be frozen before processed output Inventory is received."
                                data-confirm-title="Finalize slaughter cost basis?"
                                data-confirm-button="Finalize Cost Basis"
                                data-confirm-tone="warning"
                            >
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="slaughter_action"
                                    value="finalize_cost_basis"
                                >

                                <input
                                    type="hidden"
                                    name="batch_id"
                                    value="<?= (int)$selectedBatch['id'] ?>"
                                >

                                <button
                                    class="btn btn-warning"
                                    type="submit"
                                >
                                    Finalize Cost Basis
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <section class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-1">4. Processed output Inventory</h5>
                <small class="text-muted">
                    Receive carcass/processed output only after cost basis finalization.
                </small>
            </div>

            <div class="card-body">
                <?php if (!$selectedFinalized): ?>
                    <div class="alert alert-info">
                        Finalize the slaughter cost basis before receiving processed output.
                    </div>

                <?php elseif (!$selectedOpen): ?>
                    <div class="alert alert-secondary">
                        This batch is not open for additional output receipts.
                    </div>

                <?php elseif (!$canAddOutput): ?>
                    <div class="alert alert-secondary">
                        Output receipt is not delegated to your account.
                    </div>

                <?php elseif (!$outputItems): ?>
                    <div class="alert alert-warning">
                        No active Poultry Slaughter Output Inventory item is available.
                        Create or classify the required item in Inventory first.
                    </div>

                    <a
                        class="btn btn-outline-secondary"
                        href="<?= $h(BASE_URL . '/inventory.php') ?>"
                    >
                        Open Inventory
                    </a>

                <?php else: ?>
                    <form method="post" class="row g-3 align-items-end slaughter-output-form">
                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="slaughter_action"
                            value="add_output"
                        >

                        <input
                            type="hidden"
                            name="batch_id"
                            value="<?= (int)$selectedBatch['id'] ?>"
                        >

                        <div class="col-lg-5">
                            <label class="form-label" for="outputItem">
                                Slaughter Output item
                            </label>

                            <select
                                class="form-select"
                                id="outputItem"
                                name="stock_item_id"
                                required
                            >
                                <option value="">Choose Inventory item</option>

                                <?php foreach ($outputItems as $item): ?>
                                    <?php
                                    $itemId =
                                        (int)$item[
                                            'id'
                                        ];

                                    $postedItemId =
                                        (int)(
                                            $posted[
                                                'stock_item_id'
                                            ]
                                            ?? 0
                                        );
                                    ?>
                                    <option
                                        value="<?= $itemId ?>"
                                        <?= $postedItemId === $itemId ? 'selected' : '' ?>
                                    >
                                        <?= $h($item['item_name']) ?>
                                        ·
                                        <?= $h($item['unit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="outputQuantity">
                                Quantity
                            </label>

                            <input
                                class="form-control"
                                id="outputQuantity"
                                type="number"
                                name="output_quantity"
                                min="0.01"
                                step="0.01"
                                required
                                value="<?= $h($posted['output_quantity'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-sm-6 col-lg-2">
                            <label class="form-label" for="costShare">
                                Cost share %
                            </label>

                            <input
                                class="form-control"
                                id="costShare"
                                type="number"
                                name="cost_share_percent"
                                min="0.0001"
                                max="100"
                                step="0.0001"
                                required
                                value="<?= $h($posted['cost_share_percent'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-lg-2">
                            <button class="btn btn-success w-100" type="submit">
                                Receive Output
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="table-responsive mt-4">
                    <table class="table table-sm align-middle slaughter-output-table">
                        <thead>
                            <tr>
                                <th>Inventory item</th>
                                <th>Received</th>
                                <th>Remaining</th>
                                <th>Cost share</th>
                                <th>Allocated cost</th>
                                <th>Unit cost</th>
                                <th>Stock Tx</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$outputs): ?>
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">
                                    No processed output has been received for this batch.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($outputs as $output): ?>
                                <tr>
                                    <td><?= $h($output['item_name']) ?></td>
                                    <td>
                                        <?= $h($output['initial_quantity']) ?>
                                        <?= $h($output['unit']) ?>
                                    </td>
                                    <td>
                                        <?= $h($output['remaining_quantity']) ?>
                                        <?= $h($output['unit']) ?>
                                    </td>
                                    <td>
                                        <?= number_format((float)$output['cost_share_percent'], 4) ?>%
                                    </td>
                                    <td>
                                        ₦<?= $money($output['allocated_cost']) ?>
                                    </td>
                                    <td>
                                        ₦<?= number_format((float)$output['unit_cost_snapshot'], 4) ?>
                                    </td>
                                    <td>
                                        <?= $output['stock_transaction_id'] !== null
                                            ? '#' . (int)$output['stock_transaction_id']
                                            : 'Pending'
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($selectedBatch): ?>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge text-bg-light border">
                            Allocated:
                            <?= number_format((float)$selectedBatch['output_cost_share_percent_total'], 4) ?>%
                        </span>

                        <span class="badge text-bg-light border">
                            Cost allocated:
                            ₦<?= $money($selectedBatch['output_allocated_cost_total']) ?>
                        </span>

                        <span class="badge text-bg-light border">
                            Active processed-sale allocation(s):
                            <?= (int)$selectedBatch['active_sale_allocation_count'] ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card shadow-sm border-0 mt-4">
            <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h6 class="mb-1">Ready to sell processed output?</h6>
                    <p class="text-muted mb-0">
                        Revenue and processed-output lot consumption belong to the canonical Sales Records workflow.
                    </p>
                </div>

                <a
                    class="btn btn-primary"
                    href="<?= $h(BASE_URL . '/management/sales_records.php') ?>"
                >
                    Open Sales Records
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($batches): ?>
        <section class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Poultry slaughter batches</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Batch</th>
                            <th>Cycle</th>
                            <th>Birds</th>
                            <th>Processing cost</th>
                            <th>Full cost</th>
                            <th>Outputs</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?= $h($batch['slaughter_date']) ?></td>
                            <td><?= $h($batch['batch_code']) ?></td>
                            <td>
                                <?= $h(ucfirst((string)$batch['production_type'])) ?>
                                ·
                                <?= $h($batch['cycle_code']) ?>
                            </td>
                            <td><?= number_format((int)$batch['bird_count']) ?></td>
                            <td>₦<?= $money($batch['processing_operating_cost']) ?></td>
                            <td>₦<?= $money($batch['full_cost_basis_amount']) ?></td>
                            <td><?= (int)$batch['output_count'] ?></td>
                            <td><?= $h(ucfirst((string)$batch['status'])) ?></td>
                            <td>
                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    href="<?= $h($workspaceUrl . '?batch_id=' . (int)$batch['id']) ?>"
                                >
                                    Open
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<script
    src="<?= $h(BASE_URL . versioned_asset('/assets/js/poultry-slaughter-processing.js')) ?>"
    defer
></script>
</body>
</html>
