<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php

require_once __DIR__
    . '/../config.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../lib/stock_consumption_allocation_workspace.php';

requireLogin();

if (
    !stock_consumption_allocation_workspace_can_manage()
) {
    header(
        'Location: '
        . BASE_URL
        . '/no_access.php'
    );
    exit();
}

$farmId =
    requireCurrentFarmId();

$stockTransactionId =
    (int)(
        $_GET['stock_transaction_id']
        ?? 0
    );

$workspace = null;
$workspaceError = null;
$movement = null;

$backUrl =
    rtrim(BASE_URL, '/')
    . '/inventory.php';

if ($stockTransactionId < 1) {
    $workspaceError =
        'Select a valid consumed-stock transaction.';

} else {
    try {
        /*
         * Read the canonical stock movement first only to preserve a useful
         * return route even if the full allocation snapshot fails closed.
         */
        $movement =
            stock_consumption_allocation_persistence_movement(
                $pdo,
                $farmId,
                $stockTransactionId,
                false
            );

        $backUrl =
            stock_consumption_allocation_workspace_return_url(
                (int)(
                    $movement['stock_item_id']
                    ?? 0
                )
            );

        $workspace =
            stock_consumption_allocation_workspace_snapshot(
                $pdo,
                $farmId,
                $stockTransactionId
            );

    } catch (PDOException $e) {
        $workspaceError =
            'The consumed-stock allocation workspace could not be opened.';

        http_response_code(500);

    } catch (
        InvalidArgumentException
        |
        RuntimeException
        $e
    ) {
        $workspaceError =
            trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : 'The consumed-stock allocation workspace could not be opened.';

        http_response_code(
            $e->getMessage()
                === 'Stock consumption transaction not found.'
                    ? 404
                    : 422
        );
    }
}

$escape =
    static function ($value): string {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    };

$money =
    static function ($value): string {
        return
            '₦'
            . number_format(
                (float)$value,
                2
            );
    };

$scopeLabel =
    static function (
        string $farmType,
        string $productionType
    ): string {
        $farmType =
            strtolower(
                trim($farmType)
            );

        $productionType =
            strtolower(
                trim($productionType)
            );

        if ($farmType === 'poultry') {
            if ($productionType === 'layer') {
                return 'Poultry / Layer';
            }

            if ($productionType === 'broiler') {
                return 'Poultry / Broiler';
            }

            return 'Poultry / Shared';
        }

        if ($farmType === 'ruminant') {
            return
                'Ruminant / '
                . ucfirst(
                    $productionType !== ''
                        ? $productionType
                        : 'Shared'
                );
        }

        if ($farmType === 'both') {
            return 'Farm-wide / Poultry + Ruminant';
        }

        return
            ucfirst(
                $farmType !== ''
                    ? $farmType
                    : 'Shared'
            )
            . ' / '
            . ucfirst(
                $productionType !== ''
                    ? $productionType
                    : 'Shared'
            );
    };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../navbar_head.php'; ?>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Consumed Stock Cost Allocation</title>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container-xl mt-4 mb-5">

    <div
        class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"
    >
        <div>
            <h3 class="mb-1">
                <i class="bi bi-diagram-3"></i>
                Consumed Stock Cost Allocation
            </h3>

            <div class="text-muted">
                Explicitly assign pooled operating stock consumption
                to compatible production cycles.
            </div>
        </div>

        <a
            class="btn btn-outline-secondary"
            href="<?php echo $escape($backUrl); ?>"
        >
            <i class="bi bi-arrow-left"></i>
            Back to Stock History
        </a>
    </div>

    <?php if ($workspaceError !== null): ?>

        <div class="alert alert-danger">
            <?php echo $escape($workspaceError); ?>
        </div>

    <?php elseif ($workspace !== null): ?>

        <?php
        $movement =
            $workspace['movement'];

        $item =
            $workspace['item'];

        $contract =
            $workspace['parent_contract'];

        $latestRevision =
            $workspace['latest_revision'];

        $hasRevision =
            $latestRevision !== null;

        $retainedShared =
            !empty(
                $workspace[
                    'retained_shared'
                ]
            );

        $sourceState =
            $workspace['source_state']
            ?? [];
        ?>

        <div class="alert alert-info">
            <strong>Explicit attribution only.</strong>
            This does not change the stock movement or physical inventory.
            Enter the amounts that belong to each compatible production
            cycle. Any balance you leave unassigned remains visibly
            unallocated. Use equal split only when every compatible cycle
            shown should share the full consumed cost equally.
        </div>

        <?php if ($retainedShared): ?>

            <div class="alert alert-success">
                <strong>Retained as shared cost.</strong>
                This consumed-stock cost was deliberately reviewed and left
                without production-cycle attribution.

                <?php if (!empty(
                    $workspace['retained_shared_reason']
                )): ?>
                    <div class="mt-1">
                        Decision reason:
                        <?php echo $escape(
                            $workspace[
                                'retained_shared_reason'
                            ]
                        ); ?>
                    </div>
                <?php endif; ?>

                <div class="small mt-1">
                    The consumed cost remains included at its shared operation
                    level and has not been removed from production economics.
                </div>
            </div>

        <?php endif; ?>

        <div class="alert alert-light border">
            <strong>
                Source attribution:
                <?php echo $escape(
                    $sourceState['label']
                    ?? 'Source attribution verified'
                ); ?>
            </strong>

            <div class="mt-1">
                <?php echo $escape(
                    $sourceState['message']
                    ?? 'The canonical source-attribution contract has accepted this consumed-stock source.'
                ); ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">

                <div class="mb-3">
                    <div class="small text-muted">
                        Stock Reference
                    </div>
                    <div class="fw-semibold">
                        <code><?php echo $escape(
                            $movement['public_reference']
                            ?? '—'
                        ); ?></code>
                    </div>
                </div>

                <div class="row g-3">

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Inventory Item
                        </div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                $item['item_name']
                                ?? 'Inventory Item'
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Date
                        </div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                $movement['transaction_date']
                                ?? '—'
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Financial Type
                        </div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        (string)(
                                            $movement[
                                                'financial_classification'
                                            ]
                                            ?? ''
                                        )
                                    )
                                )
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Source Scope
                        </div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                $scopeLabel(
                                    (string)(
                                        $contract['farm_type']
                                        ?? ''
                                    ),
                                    (string)(
                                        $contract['production_type']
                                        ?? ''
                                    )
                                )
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Quantity Used
                        </div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                number_format(
                                    (float)(
                                        $movement['quantity']
                                        ?? 0
                                    ),
                                    2
                                )
                                . ' '
                                . (
                                    $item['unit']
                                    ?? ''
                                )
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Movement Source
                        </div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                str_replace(
                                    '_',
                                    ' ',
                                    ucfirst(
                                        (string)(
                                            $movement['source_type']
                                            ?? ''
                                        )
                                    )
                                )
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Allocation Revision
                        </div>
                        <div class="fw-semibold">
                            <?php
                            echo $hasRevision
                                ? '#'
                                    . (int)$latestRevision[
                                        'revision_no'
                                    ]
                                : 'Not allocated yet';
                            ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">
                            Current State
                        </div>
                        <div class="fw-semibold">
                            <?php echo !empty(
                                $workspace['fully_allocated']
                            )
                                ? 'Fully allocated'
                                : 'Has unallocated balance'; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-muted">
                            Consumed Cost
                        </div>

                        <div
                            class="fs-4 fw-semibold"
                            id="stockAllocationParentDisplay"
                        >
                            <?php echo $money(
                                $workspace['parent_amount']
                            ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-muted">
                            Explicitly Allocated
                        </div>

                        <div
                            class="fs-4 fw-semibold"
                            id="stockAllocationAllocatedDisplay"
                        >
                            <?php echo $money(
                                $workspace['allocated_amount']
                            ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-muted">
                            Unallocated Remainder
                        </div>

                        <div
                            class="fs-4 fw-semibold"
                            id="stockAllocationRemainderDisplay"
                        >
                            <?php echo $money(
                                $workspace['remaining_amount']
                            ); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <?php if (!$workspace['cycles']): ?>

            <div class="alert alert-warning">
                No compatible production cycles are available.
                This consumed cost remains unallocated unless you explicitly
                confirm that it should be retained as shared cost.
            </div>

        <?php endif; ?>

            <form
                id="stockConsumptionAllocationWorkspaceForm"
                data-parent-amount="<?php echo $escape(
                    $workspace['parent_amount']
                ); ?>"
                data-stock-transaction-id="<?php echo (int)$movement['id']; ?>"
                data-csrf-token="<?php echo $escape(
                    csrf_token()
                ); ?>"
                data-endpoint="<?php echo $escape(
                    rtrim(BASE_URL, '/')
                    . '/api/update_stock_consumption_allocation.php'
                ); ?>"
                data-has-revision="<?php echo $hasRevision
                    ? '1'
                    : '0'; ?>"
                data-retained-shared="<?php echo $retainedShared
                    ? '1'
                    : '0'; ?>"
            >

                <div
                    class="alert d-none"
                    id="stockConsumptionAllocationWorkspaceMessage"
                    role="alert"
                ></div>

                <div class="card">

                    <div
                        class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3"
                    >
                        <strong>
                            Compatible Production Cycles
                        </strong>

                        <div class="d-flex flex-wrap gap-3">

                            <div class="form-check mb-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="stockConsumptionAllocationEqualSplit"
                                    <?php echo !$workspace['cycles']
                                        ? 'disabled'
                                        : ''; ?>
                                >

                                <label
                                    class="form-check-label"
                                    for="stockConsumptionAllocationEqualSplit"
                                >
                                    Split total equally across all compatible cycles
                                </label>
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="stockConsumptionAllocationClearAmounts"
                                <?php echo !$workspace['cycles']
                                    ? 'disabled'
                                    : ''; ?>
                            >
                                Clear amounts
                            </button>

                        </div>
                    </div>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">
                                <tr>
                                    <th>Cycle</th>
                                    <th>Module</th>
                                    <th>Status</th>
                                    <th class="text-end">
                                        Amount (₦)
                                    </th>
                                    <th class="text-end">
                                        %
                                    </th>
                                    <th>Notes</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach (
                                $workspace['cycles']
                                as $cycle
                            ): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?php echo $escape(
                                                $cycle[
                                                    'cycle_code'
                                                ]
                                            ); ?>
                                        </strong>

                                        <?php if (
                                            !empty(
                                                $cycle[
                                                    'start_date'
                                                ]
                                            )
                                        ): ?>
                                            <div class="small text-muted">
                                                <?php echo $escape(
                                                    $cycle[
                                                        'start_date'
                                                    ]
                                                ); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty(
                                            $cycle['is_pre_cycle']
                                        )): ?>
                                            <div class="mt-1">
                                                <span
                                                    class="badge text-bg-warning"
                                                >
                                                    Pre-cycle preparation
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php echo $escape(
                                            $scopeLabel(
                                                (string)$cycle[
                                                    'farm_type'
                                                ],
                                                (string)$cycle[
                                                    'production_type'
                                                ]
                                            )
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php
                                        $cycleStatus =
                                            strtolower(
                                                (string)$cycle[
                                                    'status'
                                                ]
                                            );

                                        $badgeClass =
                                            $cycleStatus === 'active'
                                                ? 'text-bg-success'
                                                : 'text-bg-secondary';
                                        ?>

                                        <span
                                            class="badge <?php echo $badgeClass; ?>"
                                        >
                                            <?php echo $escape(
                                                ucfirst(
                                                    $cycleStatus !== ''
                                                        ? $cycleStatus
                                                        : 'Unknown'
                                                )
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="form-control form-control-sm text-end stock-consumption-allocation-amount"
                                            min="0"
                                            step="0.01"
                                            data-cycle-id="<?php echo (int)$cycle['id']; ?>"
                                            data-pre-cycle="<?php echo !empty(
                                                $cycle['is_pre_cycle']
                                            )
                                                ? '1'
                                                : '0'; ?>"
                                            value="<?php echo $escape(
                                                $cycle[
                                                    'allocated_amount'
                                                ]
                                            ); ?>"
                                        >
                                    </td>

                                    <td class="text-end">
                                        <span
                                            class="stock-consumption-allocation-percent"
                                            data-cycle-id="<?php echo (int)$cycle['id']; ?>"
                                        >
                                            <?php echo $escape(
                                                $cycle[
                                                    'allocation_percent'
                                                ]
                                            ); ?>%
                                        </span>
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            maxlength="255"
                                            class="form-control form-control-sm stock-consumption-allocation-note"
                                            data-cycle-id="<?php echo (int)$cycle['id']; ?>"
                                            value="<?php echo $escape(
                                                $cycle['notes']
                                                ?? ''
                                            ); ?>"
                                            placeholder="Optional note"
                                        >
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                    <div class="card-body border-top">

                        <div class="mb-3">
                            <label
                                for="stockConsumptionAllocationRevisionReason"
                                class="form-label fw-semibold"
                            >
                                Reason for allocation / shared-retention decision
                                <?php if (!$hasRevision): ?>
                                    <span class="text-muted">
                                        (optional on first allocation unless a pre-cycle target is used)
                                    </span>
                                <?php endif; ?>
                            </label>

                            <textarea
                                class="form-control"
                                id="stockConsumptionAllocationRevisionReason"
                                maxlength="500"
                                rows="2"
                                <?php echo $hasRevision
                                    ? 'required'
                                    : ''; ?>
                                placeholder="<?php echo $hasRevision
                                    ? 'Explain why this consumed-stock allocation is being changed.'
                                    : 'Optional for first allocation; required to retain this cost as shared.'; ?>"
                            ></textarea>

                            <div class="form-text">
                                A reason is always required when deliberately
                                retaining consumed cost as shared. A reason is
                                also required when assigning any amount to a
                                cycle that starts after this stock-use date,
                                because that is a pre-cycle preparation cost.
                                After the first revision, a change reason is
                                also required by the canonical stock-allocation
                                audit contract.
                            </div>
                        </div>

                        <div
                            class="d-flex flex-wrap justify-content-between align-items-center gap-2"
                        >
                            <a
                                class="btn btn-outline-secondary"
                                href="<?php echo $escape($backUrl); ?>"
                            >
                                Cancel
                            </a>

                            <div class="d-flex flex-wrap gap-2">
                                <?php if (
                                    (float)$workspace[
                                        'allocated_amount'
                                    ] <= 0.00001
                                ): ?>

                                    <button
                                        type="submit"
                                        class="btn btn-outline-info"
                                        id="stockConsumptionAllocationRetainSharedButton"
                                        data-allocation-decision="retain_shared"
                                        <?php echo $retainedShared
                                            ? 'disabled'
                                            : ''; ?>
                                    >
                                        <i class="bi bi-check-circle me-1"></i>
                                        <?php echo $retainedShared
                                            ? 'Retained as Shared'
                                            : 'Keep as Shared Cost'; ?>
                                    </button>

                                <?php endif; ?>

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    id="stockConsumptionAllocationSaveButton"
                                    data-allocation-decision="allocate"
                                    <?php echo !$workspace['cycles']
                                        ? 'disabled'
                                        : ''; ?>
                                >
                                    <i class="bi bi-save me-1"></i>
                                    Save Allocation
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </form>

        <?php if (!empty(
            $workspace['incompatible_cycles']
        )): ?>

            <div class="card mt-3">
                <div class="card-header">
                    <strong>
                        Incompatible Production Cycles
                    </strong>
                </div>

                <div class="card-body pb-2">
                    <p class="text-muted mb-0">
                        These cycles are shown for clarity only.
                        The canonical allocation contract rejected them,
                        so no amount can be assigned to them from this
                        consumed-stock source.
                    </p>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cycle</th>
                                <th>Module</th>
                                <th>Status</th>
                                <th>Why it is incompatible</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach (
                            $workspace['incompatible_cycles']
                            as $cycle
                        ): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php echo $escape(
                                            $cycle['cycle_code']
                                            ?? (
                                                'Cycle '
                                                . (int)(
                                                    $cycle['id']
                                                    ?? 0
                                                )
                                            )
                                        ); ?>
                                    </strong>

                                    <?php if (!empty(
                                        $cycle['start_date']
                                    )): ?>
                                        <div class="small text-muted">
                                            <?php echo $escape(
                                                $cycle['start_date']
                                            ); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php echo $escape(
                                        $scopeLabel(
                                            (string)(
                                                $cycle['farm_type']
                                                ?? ''
                                            ),
                                            (string)(
                                                $cycle['production_type']
                                                ?? ''
                                            )
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo $escape(
                                        ucfirst(
                                            (string)(
                                                $cycle['status']
                                                ?? 'Unknown'
                                            )
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo $escape(
                                        $cycle['reason']
                                        ?? 'Not compatible with this source.'
                                    ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"
></script>

<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"
></script>

<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/stock-consumption-allocation-workspace.js'); ?>"
></script>

</body>
</html>
