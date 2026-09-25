<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/ruminant_slaughter_processing.php';
require_once __DIR__ . '/../includes/ruminant_slaughter_permissions.php';

requireLogin();

ruminant_slaughter_require(
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

$canCreateBatch =
    ruminant_slaughter_can(
        'create_batch'
    );

$canAddProcessingExpense =
    ruminant_slaughter_can(
        'add_processing_expense'
    );

$canAddOutput =
    ruminant_slaughter_can(
        'add_output'
    );

$processingExpenseCategories =
    expense_category_options(
        'slaughter_processing'
    );

if (
    $_SERVER[
        'REQUEST_METHOD'
    ]
    ===
    'POST'
) {
    require_valid_csrf_post();

    $action =
        strtolower(
            trim(
                (string)(
                    $_POST[
                        'action'
                    ]
                    ?? ''
                )
            )
        );

    try {
        if ($action === 'create_batch') {
            ruminant_slaughter_require(
                'create_batch'
            );

            ruminant_slaughter_processing_create_batch(
                $pdo,
                $farmId,
                (int)(
                    $_POST[
                        'exit_event_id'
                    ]
                    ?? 0
                ),
                trim(
                    (string)(
                        $_POST[
                            'notes'
                        ]
                        ?? ''
                    )
                ),
                $actorUserId > 0
                    ? $actorUserId
                    : null
            );

            $_SESSION['success'] =
                'Slaughter processing batch opened successfully.';

        } elseif (
            $action
            ===
            'add_processing_expense'
        ) {
            ruminant_slaughter_require(
                'add_processing_expense'
            );

            $result =
                ruminant_slaughter_processing_expense_add(
                    $pdo,
                    $farmId,
                    (int)(
                        $_POST[
                            'batch_id'
                        ]
                        ?? 0
                    ),
                    trim(
                        (string)(
                            $_POST[
                                'category'
                            ]
                            ?? ''
                        )
                    ),
                    $_POST[
                        'amount'
                    ]
                    ?? '',
                    $_POST[
                        'unit'
                    ]
                    ?? '',
                    trim(
                        (string)(
                            $_POST[
                                'description'
                            ]
                            ?? ''
                        )
                    ),
                    $actorUserId,
                    trim(
                        (string)(
                            $_POST[
                                'processing_expense_request_token'
                            ]
                            ?? ''
                        )
                    )
                );

            $_SESSION['success'] =
                !empty(
                    $result[
                        'idempotent'
                    ]
                )
                    ? 'This processing expense was already recorded. The existing record was retained safely.'
                    : 'Ruminant slaughter processing expense recorded successfully.';

        } elseif ($action === 'add_output') {
            ruminant_slaughter_require(
                'add_output'
            );

            ruminant_slaughter_processing_add_output(
                $pdo,
                $farmId,
                (int)(
                    $_POST[
                        'batch_id'
                    ]
                    ?? 0
                ),
                (int)(
                    $_POST[
                        'stock_item_id'
                    ]
                    ?? 0
                ),
                (float)(
                    $_POST[
                        'quantity'
                    ]
                    ?? 0
                ),
                (float)(
                    $_POST[
                        'cost_share_percent'
                    ]
                    ?? 0
                ),
                $actorUserId > 0
                    ? $actorUserId
                    : null
            );

            $_SESSION['success'] =
                'Slaughter output received into Inventory.';

        } else {
            throw new RuntimeException(
                'Choose a valid slaughter processing action.'
            );
        }

    } catch (Throwable $e) {
        $_SESSION['error'] =
            safeUserExceptionMessage(
                $e,
                'The slaughter processing action could not be completed.'
            );
    }

    header(
        'Location: slaughter_processing.php'
    );
    exit();
}

$eligibleExits =
    ruminant_slaughter_processing_eligible_exits(
        $pdo,
        $farmId
    );

$batches =
    ruminant_slaughter_processing_batches(
        $pdo,
        $farmId
    );

$itemStmt = $pdo->prepare(
    "SELECT
         si.id,
         si.item_name,
         si.unit,
         si.current_stock,
         ic.category_name
     FROM stock_items si
     INNER JOIN inventory_categories ic
         ON ic.id=si.category_id
        AND ic.farm_id=si.farm_id
     WHERE si.farm_id=?
       AND si.is_active=1
       AND si.farm_type='ruminant'
       AND si.feed_category='general'
       AND ic.inventory_role=?
     ORDER BY ic.category_name,si.item_name"
);
$itemStmt->execute([
    $farmId,
    inventory_category_slaughter_output_role(),
]);
$outputItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../navbar_head.php'; ?>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >
    <title>Slaughter Processing</title>
</head>
<body class="ruminant-page">
<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container-fluid mt-4 poultry-shell">
    <div class="card poultry-panel mb-4">
        <div class="card-header poultry-hero d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="mb-1">
                    <i class="bi bi-box-seam"></i>
                    Slaughter Processing
                </h4>
                <div class="small">
                    Convert a slaughtered tagged animal into traceable Inventory outputs.
                </div>
            </div>
            <a
                class="btn btn-light btn-sm"
                href="<?php echo BASE_URL; ?>/inventory.php"
            >
                Open Inventory
            </a>
        </div>

        <div class="card-body">
            <div class="alert alert-info mb-0">
                Slaughter has already removed the animal from live population.
                Recording meat, hide, head, offal or other outputs here only
                receives physical product into Inventory. It does not remove
                another animal from population. Output cost is derived from the
                animal's frozen production cost basis; selling price remains a
                separate Sales fact and may change over time.
            </div>
        </div>
    </div>

    <div class="card poultry-panel mb-4">
        <div class="card-header">
            <strong>Slaughtered animals awaiting processing</strong>
        </div>
        <div class="card-body">
            <?php if (!$eligibleExits): ?>
                <div class="text-muted">
                    No unprocessed Slaughtered animal is waiting for a batch.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Animal</th>
                            <th>Species</th>
                            <th>Cycle</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($eligibleExits as $exit): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                (string)$exit['exit_date']
                                            )
                                        )
                                    ); ?>
                                </td>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars(
                                            (string)$exit['tag_no']
                                        ); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(
                                        ucfirst(
                                            (string)$exit['species']
                                        )
                                    ); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(
                                        (string)$exit['cycle_code']
                                    ); ?>
                                </td>
                                <td>
                                    <?php if ($canCreateBatch): ?>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="create_batch"
                                            >
                                            <input
                                                type="hidden"
                                                name="exit_event_id"
                                                value="<?php echo (int)$exit['exit_event_id']; ?>"
                                            >
                                            <button
                                                class="btn btn-sm btn-primary"
                                                type="submit"
                                            >
                                                Start Processing
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            View only
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card poultry-panel">
        <div class="card-header">
            <strong>Slaughter batches</strong>
        </div>
        <div class="card-body">
            <?php if (!$batches): ?>
                <div class="text-muted">
                    No slaughter processing batch has been created yet.
                </div>
            <?php endif; ?>

            <?php foreach ($batches as $batch): ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">
                                <?php echo htmlspecialchars(
                                    (string)$batch['batch_code']
                                ); ?>
                                ·
                                <?php echo htmlspecialchars(
                                    (string)$batch['tag_no']
                                ); ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars(
                                    ucfirst(
                                        (string)$batch['species']
                                    )
                                ); ?>
                                ·
                                <?php echo htmlspecialchars(
                                    (string)$batch['cycle_code']
                                ); ?>
                                ·
                                Slaughtered
                                <?php echo htmlspecialchars(
                                    date(
                                        'd/m/Y',
                                        strtotime(
                                            (string)$batch['slaughter_date']
                                        )
                                    )
                                ); ?>
                            </div>
                        </div>

                        <span class="badge text-bg-warning align-self-start">
                            <?php echo htmlspecialchars(
                                ucfirst(
                                    (string)$batch['status']
                                )
                            ); ?>
                        </span>
                    </div>

                    <div class="alert alert-secondary py-2 mb-3">
                        <?php if ($batch['cost_basis_amount'] === null): ?>
                            <strong>Cost basis:</strong>
                            Will be frozen automatically when the first output is received,
                            using purchase cost + direct animal expenses + allocated shared
                            costs through the slaughter date.
                            Record any slaughter-day animal expense before receiving the first output.
                        <?php else: ?>
                            <strong>Frozen cost basis:</strong>
                            ₦<?php echo number_format(
                                (float)$batch['cost_basis_amount'],
                                2
                            ); ?>
                            <span class="text-muted">
                                · Purchase ₦<?php echo number_format(
                                    (float)($batch['cost_basis_purchase'] ?? 0),
                                    2
                                ); ?>
                                · Direct ₦<?php echo number_format(
                                    (float)($batch['cost_basis_direct_expense'] ?? 0),
                                    2
                                ); ?>
                                · Shared ₦<?php echo number_format(
                                    (float)($batch['cost_basis_shared'] ?? 0),
                                    2
                                ); ?>
                                · Allocated
                                <?php echo number_format(
                                    (float)($batch['allocated_cost_percent'] ?? 0),
                                    2
                                ); ?>%
                                · Unallocated
                                <?php echo number_format(
                                    (float)($batch['unallocated_cost_percent'] ?? 100),
                                    2
                                ); ?>%
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div>
                                    <strong>
                                        Processing Expenses
                                    </strong>
                                    <div class="small text-muted">
                                        Canonical slaughter-day expenses allocated directly
                                        to this tagged animal before cost-basis freeze.
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="small text-muted">
                                        Linked total
                                    </div>
                                    <div class="fw-semibold">
                                        ₦<?php echo number_format(
                                            (float)(
                                                $batch[
                                                    'processing_expense_snapshot_total'
                                                ]
                                                ?? 0
                                            ),
                                            2
                                        ); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($batch['processing_expenses'])): ?>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Reference</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                            <th class="text-end">
                                                Snapshot
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach (
                                            $batch['processing_expenses']
                                            as $processingExpense
                                        ): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars(
                                                        date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                (string)$processingExpense[
                                                                    'expense_date'
                                                                ]
                                                            )
                                                        )
                                                    ); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(
                                                        (string)(
                                                            $processingExpense[
                                                                'public_reference'
                                                            ]
                                                            ?? '—'
                                                        )
                                                    ); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(
                                                        (string)(
                                                            $processingExpense[
                                                                'category_label'
                                                            ]
                                                            ?? $processingExpense[
                                                                'category'
                                                            ]
                                                            ?? '—'
                                                        )
                                                    ); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(
                                                        (string)(
                                                            $processingExpense[
                                                                'description'
                                                            ]
                                                            ?? ''
                                                        )
                                                    ); ?>
                                                </td>
                                                <td class="text-end">
                                                    ₦<?php echo number_format(
                                                        (float)(
                                                            $processingExpense[
                                                                'amount_snapshot'
                                                            ]
                                                            ?? 0
                                                        ),
                                                        2
                                                    ); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="small text-muted mb-3">
                                    No processing expense has been linked to this batch yet.
                                </div>
                            <?php endif; ?>

                            <?php
                            $processingExpenseOpen =
                                $canAddProcessingExpense
                                &&
                                (string)$batch['status'] === 'open'
                                &&
                                $batch['cost_basis_amount'] === null
                                &&
                                empty($batch['outputs']);
                            ?>

                            <?php if ($processingExpenseOpen): ?>
                                <form method="post" class="row g-2 align-items-end">
                                    <?php echo csrf_field(); ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="add_processing_expense"
                                    >

                                    <input
                                        type="hidden"
                                        name="batch_id"
                                        value="<?php echo (int)$batch['id']; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="processing_expense_request_token"
                                        value="<?php echo htmlspecialchars(
                                            bin2hex(
                                                random_bytes(
                                                    16
                                                )
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                    >

                                    <div class="col-md-3">
                                        <label class="form-label">
                                            Category
                                        </label>

                                        <select
                                            class="form-select"
                                            name="category"
                                            required
                                        >
                                            <option value="">
                                                Select category...
                                            </option>

                                            <?php foreach (
                                                $processingExpenseCategories
                                                as $categoryKey => $categoryLabel
                                            ): ?>
                                                <option
                                                    value="<?php echo htmlspecialchars(
                                                        (string)$categoryKey,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>"
                                                >
                                                    <?php echo htmlspecialchars(
                                                        (string)$categoryLabel
                                                    ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">
                                            Amount per unit (₦)
                                        </label>

                                        <input
                                            class="form-control"
                                            type="number"
                                            name="amount"
                                            min="0.01"
                                            step="0.01"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">
                                            Units
                                        </label>

                                        <input
                                            class="form-control"
                                            type="number"
                                            name="unit"
                                            min="0.01"
                                            step="0.01"
                                            value="1"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">
                                            Description
                                        </label>

                                        <input
                                            class="form-control"
                                            type="text"
                                            name="description"
                                            maxlength="255"
                                            placeholder="Optional processing note"
                                        >
                                    </div>

                                    <div class="col-md-2">
                                        <button
                                            class="btn btn-outline-primary w-100"
                                            type="submit"
                                        >
                                            Add Expense
                                        </button>
                                    </div>
                                </form>

                                <div class="form-text mt-2">
                                    Add all slaughter processing expenses before
                                    receiving the first Inventory output. The first
                                    output freezes this animal's cost basis.
                                </div>

                            <?php elseif ($canAddProcessingExpense): ?>
                                <div class="small text-muted">
                                    Processing expenses are locked because this batch
                                    is no longer eligible for pre-output cost changes.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Inventory output</th>
                                <th>Initial</th>
                                <th>Remaining</th>
                                <th>Cost share</th>
                                <th>Unit cost</th>
                                <th>Stock receipt</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (($batch['outputs'] ?? []) as $output): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars(
                                            (string)$output['item_name']
                                        ); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format(
                                            (float)$output['initial_quantity'],
                                            2
                                        ); ?>
                                        <?php echo htmlspecialchars(
                                            (string)$output['unit']
                                        ); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format(
                                            (float)$output['remaining_quantity'],
                                            2
                                        ); ?>
                                        <?php echo htmlspecialchars(
                                            (string)$output['unit']
                                        ); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format(
                                            (float)($output['cost_share_percent'] ?? 0),
                                            2
                                        ); ?>%
                                        <div class="small text-muted">
                                            ₦<?php echo number_format(
                                                (float)($output['allocated_cost'] ?? 0),
                                                2
                                            ); ?>
                                        </div>
                                    </td>
                                    <td>
                                        ₦<?php echo number_format(
                                            (float)($output['unit_cost_snapshot'] ?? 0),
                                            4
                                        ); ?>
                                        / <?php echo htmlspecialchars(
                                            (string)$output['unit']
                                        ); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(
                                            (string)(
                                                $output['stock_reference']
                                                ?? '—'
                                            )
                                        ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($batch['outputs'])): ?>
                                <tr>
                                    <td colspan="6" class="text-muted text-center">
                                        No Inventory output recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($canAddOutput): ?>
                        <?php if ($outputItems): ?>
                            <form method="post" class="row g-2 align-items-end">
                                <?php echo csrf_field(); ?>
                                <input
                                    type="hidden"
                                    name="action"
                                    value="add_output"
                                >
                                <input
                                    type="hidden"
                                    name="batch_id"
                                    value="<?php echo (int)$batch['id']; ?>"
                                >

                                <div class="col-md-5">
                                    <label class="form-label">
                                        Inventory output item
                                    </label>
                                    <select
                                        class="form-select"
                                        name="stock_item_id"
                                        required
                                    >
                                        <option value="">
                                            Select item...
                                        </option>
                                        <?php foreach ($outputItems as $item): ?>
                                            <option
                                                value="<?php echo (int)$item['id']; ?>"
                                            >
                                                <?php echo htmlspecialchars(
                                                    (string)$item['category_name']
                                                    . ' · '
                                                    . (string)$item['item_name']
                                                    . ' · '
                                                    . (string)$item['unit']
                                                    . ' · Stock '
                                                    . number_format(
                                                        (float)$item['current_stock'],
                                                        2
                                                    )
                                                ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">
                                        Quantity produced
                                    </label>
                                    <input
                                        class="form-control"
                                        type="number"
                                        name="quantity"
                                        min="0.01"
                                        step="0.01"
                                        required
                                    >
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">
                                        Batch cost share (%)
                                    </label>
                                    <input
                                        class="form-control"
                                        type="number"
                                        name="cost_share_percent"
                                        min="0.0001"
                                        max="<?php echo htmlspecialchars(
                                            (string)max(
                                                0,
                                                (float)($batch['unallocated_cost_percent'] ?? 100)
                                            )
                                        ); ?>"
                                        step="0.0001"
                                        required
                                    >
                                    <div class="form-text">
                                        Allocate production cost, not selling price.
                                        Remaining:
                                        <?php echo number_format(
                                            (float)($batch['unallocated_cost_percent'] ?? 100),
                                            2
                                        ); ?>%.
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <button
                                        class="btn btn-primary w-100"
                                        type="submit"
                                    >
                                        Receive Output
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                Create an active Ruminant Inventory item under
                                an Inventory Category whose role is Slaughter Output,
                                then return here to receive the processed product.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="small text-muted mt-2">
                        Output remaining balances will be consumed by the
                        upcoming Sales lot-allocation step. Batch completion
                        is intentionally not manual.
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"
></script>
</body>
</html>
