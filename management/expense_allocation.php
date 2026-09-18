<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php

require_once __DIR__
    . '/../config.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../lib/financial_allocation_workspace.php';

requireLogin();

$farmId =
    requireCurrentFarmId();

$expenseId =
    (int)(
        $_GET['expense_id']
        ?? 0
    );

$permissionScope =
    financial_allocation_workspace_scope(
        $_GET['permission_scope']
        ?? 'operational'
    );

$workspace = null;
$workspaceError = null;
$parent = null;

if ($expenseId < 1) {
    $workspaceError =
        'Select a valid expense.';
} else {
    try {
        $parent =
            financial_allocation_service_parent(
                $pdo,
                $farmId,
                $expenseId,
                false
            );

        if (
            !financial_allocation_workspace_can_access(
                $parent,
                $permissionScope
            )
        ) {
            header(
                'Location: '
                . BASE_URL
                . '/no_access.php'
            );

            exit();
        }

        $workspace =
            financial_allocation_workspace_snapshot(
                $pdo,
                $farmId,
                $expenseId
            );

    } catch (Throwable $e) {
        if ($e instanceof PDOException) {
            /*
             * Never disclose database/SQL diagnostics in tenant-facing HTML.
             */
            $workspaceError =
                'The allocation workspace could not be opened.';

            http_response_code(500);

        } else {
            $workspaceError =
                $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'The allocation workspace could not be opened.';

            http_response_code(
                $e->getMessage()
                    === 'Expense record not found.'
                        ? 404
                        : 422
            );
        }
    }
}

$backUrl =
    $parent
        ? financial_allocation_workspace_return_url(
            $parent,
            $permissionScope
        )
        : (
            rtrim(BASE_URL, '/')
            . '/management/expenses.php'
        );

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
    <title>Shared Cost Allocation</title>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container-xl mt-4 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-diagram-3"></i>
                Shared Cost Allocation
            </h3>
            <div class="text-muted">
                Explicitly assign a shared expense to compatible production cycles.
            </div>
        </div>

        <a
            class="btn btn-outline-secondary"
            href="<?php echo $escape($backUrl); ?>"
        >
            <i class="bi bi-arrow-left"></i>
            Back to Expenses
        </a>
    </div>

    <?php if ($workspaceError !== null): ?>
        <div class="alert alert-danger">
            <?php echo $escape($workspaceError); ?>
        </div>
    <?php elseif ($workspace !== null): ?>

        <?php
        $expense =
            $workspace['parent'];

        $contract =
            $workspace['parent_contract'];

        $animalAllocationCount =
            (int)$workspace[
                'animal_allocation_count'
            ];
        ?>

        <div class="alert alert-info">
            <strong>Explicit attribution only.</strong>
            Nothing is allocated automatically.
            Tick <strong>Split total equally across all compatible cycles</strong>
            only when every compatible cycle shown should share the full parent
            expense equally. Otherwise leave it unchecked and enter the amounts
            manually. Any balance left over remains visibly unallocated.
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Expense</div>
                        <div class="fw-semibold">
                            #<?php echo (int)$expense['id']; ?>
                            ·
                            <?php echo $escape(
                                ucfirst(
                                    (string)$expense['category']
                                )
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">Date</div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                $expense['expense_date']
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">Source scope</div>
                        <div class="fw-semibold">
                            <?php
                            echo $escape(
                                ucfirst(
                                    (string)$contract[
                                        'farm_type'
                                    ]
                                )
                                . ' / '
                                . ucfirst(
                                    (string)$contract[
                                        'production_type'
                                    ]
                                )
                            );
                            ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">Description</div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                trim(
                                    (string)(
                                        $expense['description']
                                        ?? ''
                                    )
                                ) !== ''
                                    ? $expense['description']
                                    : '—'
                            ); ?>
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
                            Parent Expense Total
                        </div>
                        <div
                            class="fs-4 fw-semibold"
                            id="allocationGrossDisplay"
                        >
                            <?php echo $money(
                                $workspace['gross_amount']
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
                            id="allocationAllocatedDisplay"
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
                            id="allocationRemainderDisplay"
                        >
                            <?php echo $money(
                                $workspace['remaining_amount']
                            ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($animalAllocationCount > 0): ?>
            <div class="alert alert-warning">
                This Ruminant expense already has
                <?php echo $animalAllocationCount; ?>
                individual-animal allocation row(s).
                It cannot also be allocated to production cycles.
                Clear the animal allocation from the expense record first.
            </div>
        <?php endif; ?>

        <?php if (!$workspace['cycles']): ?>
            <div class="alert alert-warning">
                No compatible production cycles are available for this expense.
                The expense remains unallocated.
            </div>
        <?php else: ?>

            <form
                id="financialAllocationWorkspaceForm"
                data-gross="<?php echo $escape(
                    $workspace['gross_amount']
                ); ?>"
                data-expense-id="<?php echo (int)$expense['id']; ?>"
                data-permission-scope="<?php echo $escape(
                    $permissionScope
                ); ?>"
                data-csrf-token="<?php echo $escape(
                    csrf_token()
                ); ?>"
                data-endpoint="<?php echo $escape(
                    rtrim(BASE_URL, '/')
                    . '/api/update_financial_allocation.php'
                ); ?>"
                data-mutation-blocked="<?php echo $animalAllocationCount > 0
                    ? '1'
                    : '0'; ?>"
            >
                <div
                    class="alert d-none"
                    id="financialAllocationWorkspaceMessage"
                    role="alert"
                ></div>

                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <strong>Compatible Production Cycles</strong>

                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <div class="form-check mb-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="financialAllocationEqualSplit"
                                    <?php echo $animalAllocationCount > 0
                                        ? 'disabled'
                                        : ''; ?>
                                >
                                <label
                                    class="form-check-label fw-semibold"
                                    for="financialAllocationEqualSplit"
                                >
                                    Split total equally across all compatible cycles
                                </label>
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="financialAllocationClearAmounts"
                                <?php echo $animalAllocationCount > 0
                                    ? 'disabled'
                                    : ''; ?>
                            >
                                Clear amounts
                            </button>
                        </div>
                    </div>

                    <div class="px-3 pt-2 small text-muted">
                        When checked, the full parent expense is divided as
                        equally as currency allows across every compatible cycle
                        shown below. Any unavoidable extra kobo is distributed
                        across the first rows so the total remains exact.
                        Uncheck the box to use manual amounts.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Production Cycle</th>
                                    <th>Status</th>
                                    <th class="app-width-220">
                                        Allocated Amount (₦)
                                    </th>
                                    <th>Allocation Note</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($workspace['cycles'] as $cycle): ?>
                                <?php
                                $amount =
                                    (float)$cycle[
                                        'allocated_amount'
                                    ];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo $escape(
                                                $cycle['cycle_code']
                                            ); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php
                                            echo $escape(
                                                ucfirst(
                                                    $cycle[
                                                        'farm_type'
                                                    ]
                                                )
                                                . ' · '
                                                . ucfirst(
                                                    $cycle[
                                                        'production_type'
                                                    ]
                                                )
                                            );
                                            ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-light border">
                                            <?php echo $escape(
                                                $cycle['status'] !== ''
                                                    ? ucfirst(
                                                        $cycle['status']
                                                    )
                                                    : 'Unknown'
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="form-control financial-allocation-amount"
                                            data-cycle-id="<?php echo (int)$cycle['id']; ?>"
                                            min="0"
                                            step="0.01"
                                            value="<?php echo $amount > 0
                                                ? $escape(
                                                    number_format(
                                                        $amount,
                                                        2,
                                                        '.',
                                                        ''
                                                    )
                                                )
                                                : ''; ?>"
                                            placeholder="0.00"
                                            <?php echo $animalAllocationCount > 0
                                                ? 'disabled'
                                                : ''; ?>
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control financial-allocation-note"
                                            data-cycle-id="<?php echo (int)$cycle['id']; ?>"
                                            maxlength="255"
                                            value="<?php echo $escape(
                                                $cycle['notes']
                                                ?? ''
                                            ); ?>"
                                            placeholder="Optional note"
                                            <?php echo $animalAllocationCount > 0
                                                ? 'disabled'
                                                : ''; ?>
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
                                for="financialAllocationRevisionReason"
                                class="form-label"
                            >
                                Reason for allocation change
                            </label>

                            <textarea
                                id="financialAllocationRevisionReason"
                                class="form-control"
                                rows="2"
                                maxlength="500"
                                required
                                <?php echo $animalAllocationCount > 0
                                    ? 'disabled'
                                    : ''; ?>
                                placeholder="Example: Allocated generator servicing cost to the Layer cycles that used it."
                            ></textarea>

                            <div class="form-text">
                                Required for the expense revision audit trail.
                                Clearing every amount explicitly clears the
                                cycle allocation while preserving revision history.
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="small text-muted">
                                Parent total =
                                explicit cycle allocations +
                                visible unallocated remainder.
                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary"
                                id="financialAllocationSaveButton"
                                <?php echo $animalAllocationCount > 0
                                    ? 'disabled'
                                    : ''; ?>
                            >
                                <i class="bi bi-check2-circle"></i>
                                Save Allocation
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>

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
                        The canonical shared-expense allocation contract
                        rejected them, so this expense cannot be assigned
                        to them.
                    </p>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Production Cycle</th>
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
                                        ucfirst(
                                            (string)(
                                                $cycle['farm_type']
                                                ?? ''
                                            )
                                        )
                                        . ' · '
                                        . ucfirst(
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
                                        ?? 'Not compatible with this shared expense.'
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

<script src="<?php
    echo BASE_URL;
    echo versioned_asset(
        '/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'
    );
?>"></script>

<script src="<?php
    echo BASE_URL;
    echo versioned_asset(
        '/assets/js/main.js'
    );
?>"></script>

<script src="<?php
    echo BASE_URL;
    echo versioned_asset(
        '/assets/js/financial-allocation-workspace.js'
    );
?>"></script>

</body>
</html>
