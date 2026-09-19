<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php

require_once __DIR__
    . '/../config.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../lib/sale_revenue_allocation_workspace.php';

requireLogin();

$farmId =
    requireCurrentFarmId();

$saleId =
    (int)(
        $_GET['sale_id']
        ?? 0
    );

$workspace = null;
$workspaceError = null;
$parent = null;

if ($saleId < 1) {
    $workspaceError =
        'Select a valid sale.';
} else {
    try {
        $parent =
            sale_revenue_allocation_persistence_parent(
                $pdo,
                $farmId,
                $saleId,
                false
            );

        if (
            !sale_revenue_allocation_workspace_can_access(
                $parent
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
            sale_revenue_allocation_workspace_snapshot(
                $pdo,
                $farmId,
                $saleId
            );

    } catch (PDOException $e) {
        /*
         * Never disclose SQL/database diagnostics in tenant-facing HTML.
         */
        $workspaceError =
            'The shared revenue allocation workspace could not be opened.';

        http_response_code(500);

    } catch (Throwable $e) {
        $message =
            trim(
                (string)$e->getMessage()
            );

        $workspaceError =
            $message !== ''
                ? $message
                : 'The shared revenue allocation workspace could not be opened.';

        http_response_code(
            $message === 'Sale record not found.'
                ? 404
                : 422
        );
    }
}

$backUrl =
    $parent
        ? sale_revenue_allocation_workspace_return_url(
            $parent
        )
        : (
            rtrim(BASE_URL, '/')
            . '/management/profitability.php'
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
    <title>Shared Revenue Allocation</title>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container-xl mt-4 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-diagram-3"></i>
                Shared Revenue Allocation
            </h3>

            <div class="text-muted">
                Explicitly attribute shared sale revenue to compatible production cycles.
            </div>
        </div>

        <a
            class="btn btn-outline-secondary"
            href="<?php echo $escape($backUrl); ?>"
        >
            <i class="bi bi-arrow-left"></i>
            Back to Profitability
        </a>
    </div>

    <?php if ($workspaceError !== null): ?>

        <div class="alert alert-danger">
            <?php echo $escape($workspaceError); ?>
        </div>

    <?php elseif ($workspace !== null): ?>

        <?php
        $sale =
            $workspace['parent'];

        $animalAllocationCount =
            (int)$workspace[
                'animal_allocation_count'
            ];

        $mutationBlocked =
            !empty(
                $workspace[
                    'mutation_blocked'
                ]
            );

        $reasonRequired =
            !empty(
                $workspace[
                    'reason_required'
                ]
            );

        $retainedShared =
            !empty(
                $workspace[
                    'retained_shared'
                ]
            );
        ?>

        <div class="alert alert-info">
            <strong>Explicit attribution only.</strong>
            Nothing is allocated automatically.
            Use equal split only when every compatible production cycle shown
            should share the full sale revenue equally.
            Otherwise enter the actual business allocation manually.
            Any balance left over remains visibly unallocated.
        </div>

        <?php if ($retainedShared): ?>

            <div class="alert alert-success">
                <strong>Retained as shared revenue.</strong>
                This sale was deliberately reviewed and left without
                production-cycle attribution.

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
                    The revenue remains included at shared poultry/farm level
                    and is not assigned to an individual production cycle.
                </div>
            </div>

        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-3">
                    <div class="small text-muted">Sale Reference</div>
                    <div class="fw-semibold">
                        <code><?php echo $escape(
                            $sale['public_reference']
                            ?? '—'
                        ); ?></code>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="small text-muted">Product</div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                $sale['product_type']
                                ?? 'Revenue'
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">Sale Date</div>
                        <div class="fw-semibold">
                            <?php echo $escape(
                                $sale['sale_date']
                                ?? '—'
                            ); ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">Scope</div>
                        <div class="fw-semibold">
                            <?php
                            echo $escape(
                                ucfirst(
                                    (string)(
                                        $sale['farm_type']
                                        ?? ''
                                    )
                                )
                                . ' · '
                                . ucfirst(
                                    (string)(
                                        $sale['production_type']
                                        ?? ''
                                    )
                                )
                                . ' · '
                                . ucfirst(
                                    (string)(
                                        $sale['attribution_scope']
                                        ?? ''
                                    )
                                )
                            );
                            ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="small text-muted">Latest Revision</div>
                        <div class="fw-semibold">
                            <?php echo (int)$workspace['revision_no'] > 0
                                ? '#'
                                    . (int)$workspace['revision_no']
                                : 'None'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-muted">
                            Parent Sale Total
                        </div>

                        <div class="fs-4 fw-semibold">
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
                                $workspace[
                                    'allocated_amount'
                                ]
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
                                $workspace[
                                    'remaining_amount'
                                ]
                            ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($animalAllocationCount > 0): ?>

            <div class="alert alert-warning">
                This Ruminant sale already has
                <?php echo $animalAllocationCount; ?>
                individual-animal revenue allocation row(s).
                It cannot also be allocated to production cycles.
                Resolve the individual-animal allocation authority first.
            </div>

        <?php endif; ?>

        <?php if (!$workspace['cycles']): ?>

            <div class="alert alert-warning">
                No compatible production cycles are available for this sale.
                The revenue remains unallocated.
            </div>

        <?php endif; ?>

            <form
                id="financialAllocationWorkspaceForm"
                data-gross="<?php echo $escape(
                    $workspace['gross_amount']
                ); ?>"
                data-parent-id="<?php echo (int)$sale['id']; ?>"
                data-parent-id-field="sale_id"
                data-entity-label="sale"
                data-allocation-label="shared revenue allocation"
                data-reason-required="<?php echo $reasonRequired
                    ? '1'
                    : '0'; ?>"
                data-csrf-token="<?php echo $escape(
                    csrf_token()
                ); ?>"
                data-endpoint="<?php echo $escape(
                    rtrim(BASE_URL, '/')
                    . '/api/update_sale_revenue_allocation.php'
                ); ?>"
                data-mutation-blocked="<?php echo $mutationBlocked
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
                        <strong>
                            Compatible Production Cycles
                        </strong>

                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <div class="form-check mb-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="financialAllocationEqualSplit"
                                    <?php echo (
                                        $mutationBlocked
                                        ||
                                        !$workspace['cycles']
                                    )
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
                                <?php echo (
                                    $mutationBlocked
                                    ||
                                    !$workspace['cycles']
                                )
                                    ? 'disabled'
                                    : ''; ?>
                            >
                                Clear amounts
                            </button>
                        </div>
                    </div>

                    <div class="px-3 pt-2 small text-muted">
                        Equal split is only a convenience tool.
                        Use it only when every compatible cycle shown genuinely
                        shares this sale revenue equally.
                        Otherwise enter the actual amounts manually.
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
                            <?php foreach (
                                $workspace['cycles']
                                as $cycle
                            ): ?>

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

                                        <?php if (!empty(
                                            $cycle['start_date']
                                        )): ?>
                                            <div class="small text-muted">
                                                Started:
                                                <?php echo $escape(
                                                    $cycle[
                                                        'start_date'
                                                    ]
                                                ); ?>
                                            </div>
                                        <?php endif; ?>
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
                                            <?php echo $mutationBlocked
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
                                            <?php echo $mutationBlocked
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
                                Reason for allocation / shared-retention decision
                                <?php if (!$reasonRequired): ?>
                                    <span class="text-muted">
                                        (optional on first allocation)
                                    </span>
                                <?php endif; ?>
                            </label>

                            <textarea
                                id="financialAllocationRevisionReason"
                                class="form-control"
                                rows="2"
                                maxlength="500"
                                <?php echo $reasonRequired
                                    ? 'required'
                                    : ''; ?>
                                <?php echo $mutationBlocked
                                    ? 'disabled'
                                    : ''; ?>
                                placeholder="<?php echo $reasonRequired
                                    ? 'Explain why this revenue allocation is being changed.'
                                    : 'Optional note for the initial allocation.'; ?>"
                            ></textarea>

                            <div class="form-text">
                                A reason is required when deliberately retaining
                                revenue as shared. After the first revision, a
                                change reason is also required by the canonical
                                revenue audit contract. Clearing all amounts
                                preserves immutable revision history.
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="small text-muted">
                                Parent sale total =
                                explicit cycle allocations +
                                visible unallocated remainder.
                            </div>

                            <?php if (
                                (float)$workspace[
                                    'allocated_amount'
                                ] <= 0.00001
                            ): ?>

                                <button
                                    type="submit"
                                    class="btn btn-outline-info"
                                    id="financialAllocationRetainSharedButton"
                                    data-allocation-decision="retain_shared"
                                    <?php echo (
                                        $mutationBlocked
                                        ||
                                        $retainedShared
                                    )
                                        ? 'disabled'
                                        : ''; ?>
                                >
                                    <i class="bi bi-check-circle"></i>
                                    <?php echo $retainedShared
                                        ? 'Retained as Shared'
                                        : 'Keep as Shared Revenue'; ?>
                                </button>

                            <?php endif; ?>

                            <button
                                type="submit"
                                class="btn btn-primary"
                                id="financialAllocationSaveButton"
                                data-allocation-decision="allocate"
                                <?php echo (
                                    $mutationBlocked
                                    ||
                                    !$workspace['cycles']
                                )
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
                        The canonical shared-revenue allocation contract rejected
                        them, so this sale cannot be assigned to them.
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
                                                $cycle[
                                                    'start_date'
                                                ]
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
                                        ?? 'Not compatible with this shared revenue.'
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
