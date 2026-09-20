<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

require_once __DIR__
    . '/../config.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../lib/attribution.php';

require_once __DIR__
    . '/../lib/transaction_actor_display.php';

require_once __DIR__
    . '/../lib/poultry_expense_entry.php';

require_once __DIR__
    . '/../lib/poultry_expense_workspace.php';

require_once __DIR__
    . '/../lib/financial_allocation_workspace.php';

requireLogin();


$poultryExpenseEntitled =
    isPlatformOwner()
    ||
    user_can_access_entitled_module(
        'poultry'
    )
    ||
    (
        hasRole(
            'sales_rep'
        )
        &&
        current_farm_has_entitlement(
            'poultry'
        )
    );


$workspaceTabs =
    poultry_expense_workspace_tabs();


if (
    !$poultryExpenseEntitled
    ||
    $workspaceTabs === []
) {
    header(
        'Location: '
        . BASE_URL
        . '/no_access.php'
    );

    exit();
}


$tenantFarmId =
    requireCurrentFarmId();


$month =
    trim(
        (string)(
            $_GET[
                'month'
            ]
            ?? date(
                'Y-m'
            )
        )
    );


if (
    preg_match(
        '/^\d{4}-\d{2}$/',
        $month
    ) !== 1
) {
    $month =
        date(
            'Y-m'
        );
}


$monthObject =
    DateTime::createFromFormat(
        '!Y-m',
        $month
    );


if (
    !$monthObject
    ||
    $monthObject->format(
        'Y-m'
    ) !== $month
) {
    $month =
        date(
            'Y-m'
        );

    $monthObject =
        DateTime::createFromFormat(
            '!Y-m',
            $month
        );
}


$yearMonth =
    $month;


$startDate =
    $monthObject->format(
        'Y-m-01'
    );


$endDate =
    $monthObject->format(
        'Y-m-t'
    );


$monthSelectorDate =
    $monthObject->format(
        'Y-m-01'
    );


$activeTab =
    poultry_expense_workspace_resolve_tab(
        $_GET[
            'tab'
        ]
        ?? null,
        $workspaceTabs
    );


$canViewLayer =
    array_key_exists(
        'layer',
        $workspaceTabs
    );


$canViewBroiler =
    array_key_exists(
        'broiler',
        $workspaceTabs
    );


$canViewShared =
    array_key_exists(
        'shared',
        $workspaceTabs
    );


$canAddLayer =
    $canViewLayer
    &&
    poultry_expense_entry_can(
        'layer',
        'add'
    );


$canAddBroiler =
    $canViewBroiler
    &&
    poultry_expense_entry_can(
        'broiler',
        'add'
    );


$canAddShared =
    $canViewShared
    &&
    poultry_expense_entry_can(
        'shared',
        'add'
    );


$addProductionTypes = [];

if ($canAddLayer) {
    $addProductionTypes[
        'layer'
    ] =
        'Layer';
}

if ($canAddBroiler) {
    $addProductionTypes[
        'broiler'
    ] =
        'Broiler';
}

if ($canAddShared) {
    $addProductionTypes[
        'shared'
    ] =
        'Shared';
}


$canAddAny =
    $addProductionTypes !== [];


/*
 * The combined editor exposes only production targets the current user can
 * operationally edit. The API independently re-authorizes both the existing
 * row and requested target.
 */
$editableProductionTypes = [];

foreach (
    [
        'layer' =>
            'Layer',

        'broiler' =>
            'Broiler',

        'shared' =>
            'Shared',
    ]
    as $type => $label
) {
    if (
        permission_catalog_expense_operational_can(
            poultry_expense_workspace_production_row(
                $type
            ),
            'edit'
        )
    ) {
        $editableProductionTypes[
            $type
        ] =
            $label;
    }
}


$canEditAny =
    $editableProductionTypes !== [];


if (
    $_SERVER[
        'REQUEST_METHOD'
    ] === 'POST'
    &&
    isset(
        $_POST[
            'add_expense'
        ]
    )
) {
    if (
        !verify_csrf_token(
            $_POST[
                'csrf_token'
            ]
            ?? ''
        )
    ) {
        http_response_code(
            419
        );

        exit(
            'Invalid request token.'
        );
    }

    $requestedProductionType =
        strtolower(
            trim(
                (string)(
                    $_POST[
                        'production_type'
                    ]
                    ?? ''
                )
            )
        );

    $redirectTab =
        array_key_exists(
            $requestedProductionType,
            $workspaceTabs
        )
            ? $requestedProductionType
            : 'all';

    $expenseDate =
        trim(
            (string)(
                $_POST[
                    'expense_date'
                ]
                ?? ''
            )
        );

    $redirectMonth =
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $expenseDate
        ) === 1
            ? substr(
                $expenseDate,
                0,
                7
            )
            : $yearMonth;

    try {
        if (
            !array_key_exists(
                $requestedProductionType,
                $addProductionTypes
            )
        ) {
            throw new InvalidArgumentException(
                'You do not have permission to add an expense in that Poultry area.'
            );
        }

        $pdo->beginTransaction();

        poultry_expense_entry_create(
            $pdo,
            $tenantFarmId,
            (int)(
                $_SESSION[
                    'user_id'
                ]
                ?? 0
            ),
            [
                'production_type' =>
                    $requestedProductionType,

                'expense_date' =>
                    $expenseDate,

                'cycle_id' =>
                    $requestedProductionType === 'shared'
                        ? 0
                        : (
                            $_POST[
                                'cycle_id'
                            ]
                            ?? 0
                        ),

                'category' =>
                    $_POST[
                        'category'
                    ]
                    ?? '',

                'amount' =>
                    $_POST[
                        'amount'
                    ]
                    ?? null,

                'unit' =>
                    $_POST[
                        'unit'
                    ]
                    ?? 1,

                'description' =>
                    $_POST[
                        'description'
                    ]
                    ?? '',
            ]
        );

        $pdo->commit();

        $_SESSION[
            'success'
        ] =
            'Poultry expense recorded successfully.';

    } catch (
        InvalidArgumentException $e
    ) {
        if (
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        $_SESSION[
            'error'
        ] =
            $e->getMessage();

    } catch (
        Throwable $e
    ) {
        if (
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        log_app_error(
            'poultry_expense_hub_create_failed',
            [
                'error' =>
                    safe_api_exception_message(
                        $e,
                        'The Poultry expense could not be recorded.'
                    ),
            ]
        );

        $_SESSION[
            'error'
        ] =
            'The Poultry expense could not be recorded.';
    }

    header(
        'Location: expenses.php?'
        . http_build_query([
            'month' =>
                $redirectMonth,

            'tab' =>
                $redirectTab,
        ])
    );

    exit();
}


$workspaceRows =
    poultry_expense_workspace_rows(
        $pdo,
        $tenantFarmId,
        $startDate,
        $endDate
    );


$workspaceTotals =
    poultry_expense_workspace_totals(
        $workspaceRows
    );


$productionSpendingView =
    null;


if (
    in_array(
        $activeTab,
        [
            'layer',
            'broiler',
        ],
        true
    )
) {
    $productionSpendingView =
        poultry_expense_workspace_production_spending_view(
            $pdo,
            $tenantFarmId,
            $startDate,
            $endDate,
            $activeTab,
            $workspaceRows
        );

    $expenses =
        $productionSpendingView[
            'manual_expenses'
        ];

} else {
    $expenses =
        poultry_expense_workspace_filter_rows(
            $workspaceRows,
            $activeTab
        );
}


$expenseCycles =
    poultry_expense_workspace_cycles(
        $pdo,
        $tenantFarmId
    );


$visibleTotal =
    round(
        array_sum(
            array_map(
                static function (
                    array $row
                ): float {
                    return
                        (
                            (float)(
                                $row[
                                    'amount'
                                ]
                                ?? 0
                            )
                        )
                        *
                        (
                            (float)(
                                $row[
                                    'unit'
                                ]
                                ?? 1
                            )
                        );
                },
                $expenses
            )
        ),
        2
    );


$productionLabels = [
    'layer' =>
        'Layer',

    'broiler' =>
        'Broiler',

    'shared' =>
        'Poultry Shared',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../navbar_head.php'; ?>
    <title>Poultry Expenses - Renee Farms</title>
</head>

<body class="poultry-page">

<?php include __DIR__ . '/../navbar.php'; ?>

<div class="container-fluid mt-4 poultry-shell">

    <div class="card poultry-panel">

        <div
            class="card-header poultry-hero d-flex justify-content-between align-items-center flex-wrap gap-2"
        >
            <div>
                <h4 class="mb-1">
                    <i class="bi bi-cash-stack"></i>
                    Poultry Expenses
                </h4>

                <div class="small opacity-75">
                    One expense workspace for Layer, Broiler and Poultry-wide Shared costs.
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">

                <input
                    type="date"
                    class="form-control js-calendar-input app-month-selector"
                    id="poultryExpenseMonth"
                    value="<?php echo app_attr($monthSelectorDate); ?>"
                    aria-label="Expense month"
                >

                <?php if ($canAddAny): ?>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#addPoultryExpenseModal"
                >
                    <i class="bi bi-plus-circle"></i>
                    Add Expense
                </button>
                <?php endif; ?>

            </div>
        </div>


        <div class="card-body">

            <div class="alert alert-info d-flex gap-2 align-items-start">
                <i class="bi bi-info-circle mt-1"></i>

                <div>
                    <strong>Non-stock operating costs only.</strong>
                    Feed, medication, vaccines and other physical stock purchases
                    continue through Inventory. This workspace records services
                    and other non-stock Poultry operating expenses.
                </div>
            </div>


            <ul class="nav nav-tabs mb-4">

                <?php foreach ($workspaceTabs as $tabKey => $tabLabel): ?>

                <li class="nav-item">

                    <a
                        class="nav-link <?php echo $activeTab === $tabKey ? 'active' : ''; ?>"
                        href="<?php
                            echo app_attr(
                                'expenses.php?'
                                . http_build_query([
                                    'month' =>
                                        $yearMonth,

                                    'tab' =>
                                        $tabKey,
                                ])
                            );
                        ?>"
                    >
                        <?php echo htmlspecialchars($tabLabel); ?>
                    </a>

                </li>

                <?php endforeach; ?>

            </ul>


            <?php if ($productionSpendingView !== null): ?>

            <?php
                $productionLabel =
                    $productionLabels[
                        $activeTab
                    ]
                    ?? ucfirst(
                        $activeTab
                    );

                $manualExpenseTotal =
                    (float)$productionSpendingView[
                        'manual_expense_total'
                    ];

                $inventoryPurchaseTotal =
                    (float)$productionSpendingView[
                        'inventory_purchase_total'
                    ];

                $totalSpending =
                    (float)$productionSpendingView[
                        'total_spending'
                    ];

                $spendingCategoryTotals =
                    (array)$productionSpendingView[
                        'spending_category_totals'
                    ];

                $inventoryPurchases =
                    (array)$productionSpendingView[
                        'inventory_purchases'
                    ];
            ?>


            <div class="smart-poultry-note p-3 mb-4 d-flex gap-3 align-items-start">
                <i class="bi bi-stars fs-4"></i>

                <div>
                    <div class="fw-bold">
                        <?php echo htmlspecialchars($productionLabel); ?>
                        cost intelligence
                    </div>

                    <div class="small">
                        One view of non-stock expenses and Inventory purchase spending for this production area.
                    </div>
                </div>
            </div>


            <div class="row g-3 mb-4">

                <div class="col-12 col-md-4">
                    <div class="card h-100 bg-danger text-white">
                        <div class="card-body text-center">
                            <div class="small text-uppercase">
                                Total Spending
                            </div>

                            <div class="fs-3 fw-bold">
                                ₦<?php echo number_format($totalSpending, 2); ?>
                            </div>

                            <div class="small">
                                Inventory Purchases
                                ₦<?php echo number_format($inventoryPurchaseTotal, 2); ?>
                                +
                                Detailed Expenses
                                ₦<?php echo number_format($manualExpenseTotal, 2); ?>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="col-12 col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="small text-muted">
                                Inventory Purchases
                            </div>

                            <div class="fs-4 fw-bold">
                                ₦<?php echo number_format($inventoryPurchaseTotal, 2); ?>
                            </div>

                            <div class="small text-muted">
                                Purchase/cash activity from Inventory.
                            </div>
                        </div>
                    </div>
                </div>


                <div class="col-12 col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="small text-muted">
                                Detailed Expenses
                            </div>

                            <div class="fs-4 fw-bold">
                                ₦<?php echo number_format($manualExpenseTotal, 2); ?>
                            </div>

                            <div class="small text-muted">
                                Non-stock operating expenses.
                            </div>
                        </div>
                    </div>
                </div>

            </div>


            <?php if ($spendingCategoryTotals !== []): ?>

            <h5>
                Spending Breakdown
            </h5>

            <div class="row g-3 mb-4">

                <?php foreach ($spendingCategoryTotals as $category => $total): ?>

                <?php
                    $categoryTotal =
                        (float)$total;

                    if ($categoryTotal <= 0) {
                        continue;
                    }

                    $percentage =
                        $totalSpending > 0
                            ? (
                                $categoryTotal
                                /
                                $totalSpending
                                *
                                100
                            )
                            : 0;
                ?>

                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">

                    <div class="card h-100">

                        <div class="card-body text-center">

                            <div class="small text-muted text-uppercase">
                                <?php
                                    echo htmlspecialchars(
                                        inventory_financial_spending_label(
                                            (string)$category
                                        )
                                    );
                                ?>
                            </div>

                            <div class="fs-5 fw-bold text-danger">
                                ₦<?php echo number_format($categoryTotal, 2); ?>
                            </div>

                            <div class="progress app-progress-h-5 mt-2">
                                <div
                                    class="progress-bar bg-danger <?php echo app_percent_class($percentage); ?>"
                                ></div>
                            </div>

                            <div class="small text-muted mt-1">
                                <?php echo number_format($percentage, 1); ?>%
                                of total
                            </div>

                        </div>

                    </div>

                </div>

                <?php endforeach; ?>

            </div>

            <?php endif; ?>


            <div class="card border-primary-subtle mb-4">

                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div>
                        <strong>
                            <i class="bi bi-box-arrow-in-down"></i>
                            Inventory Purchases
                        </strong>

                        <div class="small text-muted">
                            Received stock shown from the Inventory ledger.
                            These are purchase/cash records, not duplicate operating-expense entries.
                        </div>
                    </div>

                    <span class="badge text-bg-primary">
                        Total ₦<?php echo number_format($inventoryPurchaseTotal, 2); ?>
                    </span>

                </div>


                <div class="table-responsive">

                    <table class="table table-sm table-hover mb-0 poultry-table align-middle">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Unit Cost</th>
                                <th>Total</th>
                                <th>Attribution</th>
                                <th>Source</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if ($inventoryPurchases === []): ?>

                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">
                                    No inventory purchases recorded for this month.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($inventoryPurchases as $purchase): ?>

                            <?php
                                $purchaseProductionType =
                                    strtolower(
                                        trim(
                                            (string)(
                                                $purchase[
                                                    'production_type'
                                                ]
                                                ?? $activeTab
                                            )
                                        )
                                    );

                                if (
                                    !in_array(
                                        $purchaseProductionType,
                                        [
                                            'layer',
                                            'broiler',
                                        ],
                                        true
                                    )
                                ) {
                                    $purchaseProductionType =
                                        $activeTab;
                                }
                            ?>

                            <tr>

                                <td>
                                    <?php
                                        echo htmlspecialchars(
                                            date(
                                                'd/m/Y',
                                                strtotime(
                                                    (string)$purchase[
                                                        'transaction_date'
                                                    ]
                                                )
                                            )
                                        );
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php
                                            echo htmlspecialchars(
                                                (string)$purchase[
                                                    'item_name'
                                                ]
                                            );
                                        ?>
                                    </strong>

                                    <?php if (!empty($purchase['category_name'])): ?>

                                    <div class="small text-muted">
                                        <?php
                                            echo htmlspecialchars(
                                                (string)$purchase[
                                                    'category_name'
                                                ]
                                            );
                                        ?>
                                    </div>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php
                                        echo number_format(
                                            (float)$purchase[
                                                'quantity'
                                            ],
                                            2
                                        );
                                    ?>

                                    <?php
                                        echo htmlspecialchars(
                                            (string)$purchase[
                                                'unit'
                                            ]
                                        );
                                    ?>
                                </td>

                                <td>
                                    ₦<?php
                                        echo number_format(
                                            (float)$purchase[
                                                'unit_cost'
                                            ],
                                            2
                                        );
                                    ?>
                                </td>

                                <td class="fw-semibold">
                                    ₦<?php
                                        echo number_format(
                                            (float)$purchase[
                                                'total_cost'
                                            ],
                                            2
                                        );
                                    ?>
                                </td>

                                <td>
                                    <div class="fw-semibold">
                                        <?php
                                            echo htmlspecialchars(
                                                attribution_production_label(
                                                    'poultry',
                                                    $purchaseProductionType
                                                )
                                            );
                                        ?>
                                    </div>

                                    <div class="small text-muted">
                                        <?php
                                            echo htmlspecialchars(
                                                attribution_cycle_label(
                                                    'poultry',
                                                    $purchaseProductionType,
                                                    $purchase[
                                                        'cycle_code'
                                                    ]
                                                    ?? null
                                                )
                                            );
                                        ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge text-bg-light border">
                                        Inventory
                                    </span>
                                </td>

                            </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <div class="card-footer small text-muted">
                    Inventory purchases are included in Total Spending, while profitability recognises stocked-item cost through the appropriate consumption logic.
                </div>

            </div>


            <?php else: ?>


            <div class="row g-3 mb-4">

                <div class="col-12 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">
                                Visible Poultry Expenses
                            </div>

                            <div class="fs-4 fw-bold">
                                ₦<?php echo number_format($workspaceTotals['all'], 2); ?>
                            </div>

                            <div class="small text-muted">
                                Each expense parent counted once.
                            </div>
                        </div>
                    </div>
                </div>


                <?php if ($canViewLayer): ?>

                <div class="col-12 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">
                                Layer
                            </div>

                            <div class="fs-4 fw-bold">
                                ₦<?php echo number_format($workspaceTotals['layer'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>


                <?php if ($canViewBroiler): ?>

                <div class="col-12 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted">
                                Broiler
                            </div>

                            <div class="fs-4 fw-bold">
                                ₦<?php echo number_format($workspaceTotals['broiler'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>


                <?php if ($canViewShared): ?>

                <div class="col-12 col-md-3">
                    <div class="card h-100 border-warning-subtle">
                        <div class="card-body">
                            <div class="small text-muted">
                                Poultry Shared
                            </div>

                            <div class="fs-4 fw-bold">
                                ₦<?php echo number_format($workspaceTotals['shared'], 2); ?>
                            </div>

                            <div class="small text-muted">
                                Not duplicated into Layer or Broiler totals.
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

            </div>


            <?php endif; ?>


            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">

                <h5 class="mb-0">
                    <?php if ($productionSpendingView !== null): ?>
                    Detailed Expenses —
                    <?php echo htmlspecialchars($productionLabel); ?>
                    —
                    <?php else: ?>
                    <?php echo htmlspecialchars($workspaceTabs[$activeTab] ?? 'Poultry'); ?>
                    Expenses —
                    <?php endif; ?>

                    <?php echo htmlspecialchars($monthObject->format('F Y')); ?>
                </h5>

                <span class="badge text-bg-secondary">
                    <?php if ($productionSpendingView !== null): ?>
                    Non-stock
                    <?php else: ?>
                    Total
                    <?php endif; ?>

                    ₦<?php echo number_format($visibleTotal, 2); ?>
                </span>

            </div>


            <div class="table-responsive">

                <table class="table table-striped table-hover poultry-table align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Production</th>
                            <th>Cycle / Scope</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Amount</th>
                            <th>Total</th>
                            <th>Description</th>
                            <th>Recorded By</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($expenses === []): ?>

                        <tr>
                            <td
                                colspan="11"
                                class="text-center text-muted py-4"
                            >
                                <i class="bi bi-receipt display-6 d-block mb-2"></i>
                                No Poultry expenses found in this view for this month.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($expenses as $expense): ?>

                        <?php
                            $productionType =
                                (string)(
                                    $expense[
                                        'workspace_production_type'
                                    ]
                                    ?? ''
                                );

                            $lineTotal =
                                round(
                                    (
                                        (float)(
                                            $expense[
                                                'amount'
                                            ]
                                            ?? 0
                                        )
                                    )
                                    *
                                    (
                                        (float)(
                                            $expense[
                                                'unit'
                                            ]
                                            ?? 1
                                        )
                                    ),
                                    2
                                );

                            $canAllocate =
                                financial_allocation_workspace_parent_is_eligible(
                                    $expense
                                )
                                &&
                                financial_allocation_workspace_can_access(
                                    $expense,
                                    'operational'
                                );

                            $canEditExpense =
                                permission_catalog_expense_operational_can(
                                    $expense,
                                    'edit'
                                );

                            $canDeleteExpense =
                                permission_catalog_expense_operational_can(
                                    $expense,
                                    'delete'
                                );

                            $hasRowAction =
                                $canAllocate
                                ||
                                $canEditExpense
                                ||
                                $canDeleteExpense;
                        ?>

                        <tr>

                            <td>
                                <?php
                                    echo htmlspecialchars(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                (string)$expense[
                                                    'expense_date'
                                                ]
                                            )
                                        )
                                    );
                                ?>
                            </td>

                            <td class="text-nowrap">
                                <code>
                                    <?php
                                        echo htmlspecialchars(
                                            (string)(
                                                $expense[
                                                    'public_reference'
                                                ]
                                                ?? '—'
                                            )
                                        );
                                    ?>
                                </code>
                            </td>

                            <td>
                                <?php if ($productionType === 'shared'): ?>
                                <span class="badge text-bg-warning">
                                    Poultry Shared
                                </span>
                                <?php else: ?>
                                <span class="badge text-bg-success">
                                    <?php
                                        echo htmlspecialchars(
                                            $productionLabels[
                                                $productionType
                                            ]
                                            ?? ucfirst(
                                                $productionType
                                            )
                                        );
                                    ?>
                                </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($productionType === 'shared'): ?>

                                <div class="fw-semibold">
                                    Poultry-wide shared
                                </div>
                                <div class="small text-muted">
                                    No specific production cycle
                                </div>

                                <?php else: ?>

                                <div class="fw-semibold">
                                    <?php
                                        echo htmlspecialchars(
                                            attribution_production_label(
                                                'poultry',
                                                $productionType
                                            )
                                        );
                                    ?>
                                </div>

                                <div class="small text-muted">
                                    <?php
                                        echo htmlspecialchars(
                                            attribution_cycle_label(
                                                'poultry',
                                                $productionType,
                                                $expense[
                                                    'expense_cycle_code'
                                                ]
                                                ?? null
                                            )
                                        );
                                    ?>
                                </div>

                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge text-bg-secondary">
                                    <?php
                                        echo htmlspecialchars(
                                            ucfirst(
                                                (string)(
                                                    $expense[
                                                        'category'
                                                    ]
                                                    ?? 'misc'
                                                )
                                            )
                                        );
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                    echo number_format(
                                        (float)(
                                            $expense[
                                                'unit'
                                            ]
                                            ?? 1
                                        ),
                                        2
                                    );
                                ?>
                            </td>

                            <td class="text-danger fw-semibold">
                                ₦<?php
                                    echo number_format(
                                        (float)(
                                            $expense[
                                                'amount'
                                            ]
                                            ?? 0
                                        ),
                                        2
                                    );
                                ?>
                            </td>

                            <td class="text-danger fw-bold">
                                ₦<?php echo number_format($lineTotal, 2); ?>
                            </td>

                            <td>
                                <?php
                                    if (
                                        trim(
                                            (string)(
                                                $expense[
                                                    'description'
                                                ]
                                                ?? ''
                                            )
                                        )
                                        !== ''
                                    ) {
                                        echo app_html(
                                            (string)$expense[
                                                'description'
                                            ]
                                        );

                                    } else {
                                        echo '<span class="text-muted">—</span>';
                                    }
                                ?>
                            </td>

                            <td>
                                <small>
                                    <?php
                                        echo app_html(
                                            transaction_recorded_by_label_from_row(
                                                $pdo,
                                                $tenantFarmId,
                                                $expense
                                            )
                                        );
                                    ?>
                                </small>
                            </td>

                            <td class="no-print text-nowrap">

                                <?php if ($canAllocate): ?>

                                <a
                                    class="btn btn-sm btn-outline-secondary"
                                    href="<?php
                                        echo app_attr(
                                            financial_allocation_workspace_url(
                                                (int)$expense[
                                                    'id'
                                                ],
                                                'operational'
                                            )
                                        );
                                    ?>"
                                    title="Allocate shared cost"
                                    aria-label="Allocate shared cost"
                                >
                                    <i class="bi bi-diagram-3"></i>
                                </a>

                                <?php endif; ?>


                                <?php if ($canEditExpense): ?>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary poultry-expense-edit-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editPoultryExpenseModal"
                                    data-expense-id="<?php echo (int)$expense['id']; ?>"
                                    data-expense-date="<?php echo app_attr((string)$expense['expense_date']); ?>"
                                    data-production-type="<?php echo app_attr($productionType); ?>"
                                    data-cycle-id="<?php echo (int)($expense['cycle_id'] ?? 0); ?>"
                                    data-category="<?php echo app_attr((string)($expense['category'] ?? 'misc')); ?>"
                                    data-unit="<?php echo app_attr((string)($expense['unit'] ?? 1)); ?>"
                                    data-amount="<?php echo app_attr((string)($expense['amount'] ?? 0)); ?>"
                                    data-description="<?php echo app_attr((string)($expense['description'] ?? '')); ?>"
                                    title="Edit expense"
                                    aria-label="Edit expense"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <?php endif; ?>


                                <?php if ($canDeleteExpense): ?>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger poultry-expense-delete-btn"
                                    data-expense-id="<?php echo (int)$expense['id']; ?>"
                                    title="Delete expense"
                                    aria-label="Delete expense"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>

                                <?php endif; ?>


                                <?php if (!$hasRowAction): ?>

                                <span class="text-muted">—</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                    <tfoot class="table-secondary">
                        <tr>
                            <td colspan="7">
                                <strong>VISIBLE TOTAL</strong>
                            </td>
                            <td class="text-danger fw-bold">
                                ₦<?php echo number_format($visibleTotal, 2); ?>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>


<?php if ($canEditAny): ?>

<div
    class="modal fade"
    id="editPoultryExpenseModal"
    tabindex="-1"
    aria-labelledby="editPoultryExpenseTitle"
    aria-hidden="true"
>
    <div class="modal-dialog">

        <div class="modal-content">

            <form id="editPoultryExpenseForm">

                <input
                    type="hidden"
                    name="expense_id"
                    id="editPoultryExpenseId"
                >

                <input
                    type="hidden"
                    name="farm_type"
                    value="poultry"
                >

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="editPoultryExpenseTitle"
                    >
                        Edit Poultry Expense
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label
                            for="editPoultryExpenseDate"
                            class="form-label"
                        >
                            Date
                        </label>

                        <input
                            type="date"
                            name="expense_date"
                            id="editPoultryExpenseDate"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label
                            for="editPoultryProductionType"
                            class="form-label"
                        >
                            Production Type
                        </label>

                        <select
                            name="production_type"
                            id="editPoultryProductionType"
                            class="form-select"
                            required
                        >

                            <?php foreach ($editableProductionTypes as $type => $label): ?>

                            <option value="<?php echo app_attr($type); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            Moving an expense to another Poultry area requires permission for both the existing row and the requested target.
                        </div>

                    </div>


                    <div class="mb-3">

                        <label
                            for="editPoultryExpenseCycle"
                            class="form-label"
                        >
                            Production Cycle
                        </label>

                        <select
                            name="cycle_id"
                            id="editPoultryExpenseCycle"
                            class="form-select"
                        >

                            <?php if (isset($editableProductionTypes['layer'])): ?>

                            <option
                                value="0"
                                data-production-type="layer"
                            >
                                Shared between Layer cycles
                            </option>

                            <?php foreach ($expenseCycles['layer'] as $cycle): ?>

                            <option
                                value="<?php echo (int)$cycle['id']; ?>"
                                data-production-type="layer"
                            >
                                <?php
                                    echo htmlspecialchars(
                                        (string)$cycle['cycle_code']
                                        . ' — '
                                        . (string)$cycle['status']
                                    );
                                ?>
                            </option>

                            <?php endforeach; ?>

                            <?php endif; ?>


                            <?php if (isset($editableProductionTypes['broiler'])): ?>

                            <option
                                value="0"
                                data-production-type="broiler"
                            >
                                Shared between Broiler cycles
                            </option>

                            <?php foreach ($expenseCycles['broiler'] as $cycle): ?>

                            <option
                                value="<?php echo (int)$cycle['id']; ?>"
                                data-production-type="broiler"
                            >
                                <?php
                                    echo htmlspecialchars(
                                        (string)$cycle['cycle_code']
                                        . ' — '
                                        . (string)$cycle['status']
                                    );
                                ?>
                            </option>

                            <?php endforeach; ?>

                            <?php endif; ?>


                            <?php if (isset($editableProductionTypes['shared'])): ?>

                            <option
                                value="0"
                                data-production-type="shared"
                            >
                                Poultry-wide shared — no specific production cycle
                            </option>

                            <?php endif; ?>

                        </select>

                        <div
                            class="form-text"
                            id="editPoultryExpenseCycleHelp"
                        >
                            Choose a cycle only when the expense belongs directly to it.
                        </div>

                    </div>


                    <div class="mb-3">

                        <label
                            for="editPoultryExpenseCategory"
                            class="form-label"
                        >
                            Category
                        </label>

                        <select
                            name="category"
                            id="editPoultryExpenseCategory"
                            class="form-select"
                            required
                        >
                            <option value="feeds">Feeds (legacy historical record)</option>
                            <option value="medication">Medication (legacy historical record)</option>
                            <option value="salary">Salary</option>
                            <option value="logistic">Logistic</option>
                            <option value="fuel">Fuel</option>
                            <option value="misc">Miscellaneous</option>
                        </select>

                        <div class="form-text">
                            New stock purchases remain Inventory records. Legacy Feed/Medication categories are shown only so historical records can remain unchanged while editing other fields.
                        </div>

                    </div>


                    <div class="mb-3">

                        <label
                            for="editPoultryExpenseUnit"
                            class="form-label"
                        >
                            Unit
                        </label>

                        <input
                            type="number"
                            name="unit"
                            id="editPoultryExpenseUnit"
                            class="form-control"
                            step="0.01"
                            min="0.01"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label
                            for="editPoultryExpenseAmount"
                            class="form-label"
                        >
                            Amount (₦)
                        </label>

                        <input
                            type="number"
                            name="amount"
                            id="editPoultryExpenseAmount"
                            class="form-control"
                            step="0.01"
                            min="0.01"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label
                            for="editPoultryExpenseDescription"
                            class="form-label"
                        >
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="editPoultryExpenseDescription"
                            class="form-control"
                            rows="3"
                        ></textarea>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </div>
</div>

<?php endif; ?>


<?php if ($canAddAny): ?>

<div
    class="modal fade"
    id="addPoultryExpenseModal"
    tabindex="-1"
    aria-labelledby="addPoultryExpenseTitle"
    aria-hidden="true"
>
    <div class="modal-dialog">

        <div class="modal-content">

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo app_attr(csrf_token()); ?>"
                >

                <div class="modal-header">
                    <h5
                        class="modal-title"
                        id="addPoultryExpenseTitle"
                    >
                        Add Poultry Expense
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>


                <div class="modal-body">

                    <div class="alert alert-info py-2 small">
                        <strong>Non-stock costs only.</strong>
                        Physical stock purchases belong in Inventory.
                    </div>


                    <div class="mb-3">
                        <label
                            for="addPoultryExpenseDate"
                            class="form-label"
                        >
                            Date
                        </label>

                        <input
                            type="date"
                            name="expense_date"
                            id="addPoultryExpenseDate"
                            class="form-control"
                            value="<?php echo app_attr(date('Y-m-d')); ?>"
                            required
                        >
                    </div>


                    <div class="mb-3">
                        <label
                            for="addPoultryProductionType"
                            class="form-label"
                        >
                            Production Type
                        </label>

                        <select
                            name="production_type"
                            id="addPoultryProductionType"
                            class="form-select"
                            required
                        >

                            <?php foreach ($addProductionTypes as $type => $label): ?>

                            <option value="<?php echo app_attr($type); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>

                            <?php endforeach; ?>

                        </select>
                    </div>


                    <div class="mb-3">
                        <label
                            for="addPoultryExpenseCycle"
                            class="form-label"
                        >
                            Production Cycle
                        </label>

                        <select
                            name="cycle_id"
                            id="addPoultryExpenseCycle"
                            class="form-select"
                        >

                            <?php if ($canAddLayer): ?>
                            <option
                                value="0"
                                data-production-type="layer"
                            >
                                Shared between Layer cycles
                            </option>

                            <?php foreach ($expenseCycles['layer'] as $cycle): ?>
                            <option
                                value="<?php echo (int)$cycle['id']; ?>"
                                data-production-type="layer"
                            >
                                <?php
                                    echo htmlspecialchars(
                                        (string)$cycle[
                                            'cycle_code'
                                        ]
                                        . ' — '
                                        . (string)$cycle[
                                            'status'
                                        ]
                                    );
                                ?>
                            </option>
                            <?php endforeach; ?>
                            <?php endif; ?>


                            <?php if ($canAddBroiler): ?>
                            <option
                                value="0"
                                data-production-type="broiler"
                            >
                                Shared between Broiler cycles
                            </option>

                            <?php foreach ($expenseCycles['broiler'] as $cycle): ?>
                            <option
                                value="<?php echo (int)$cycle['id']; ?>"
                                data-production-type="broiler"
                            >
                                <?php
                                    echo htmlspecialchars(
                                        (string)$cycle[
                                            'cycle_code'
                                        ]
                                        . ' — '
                                        . (string)$cycle[
                                            'status'
                                        ]
                                    );
                                ?>
                            </option>
                            <?php endforeach; ?>
                            <?php endif; ?>


                            <?php if ($canAddShared): ?>
                            <option
                                value="0"
                                data-production-type="shared"
                            >
                                Poultry-wide shared — no specific production cycle
                            </option>
                            <?php endif; ?>

                        </select>

                        <div
                            class="form-text"
                            id="addPoultryExpenseCycleHelp"
                        >
                            Choose a cycle only when the expense belongs directly to it.
                        </div>
                    </div>


                    <div class="mb-3">
                        <label
                            for="addPoultryExpenseCategory"
                            class="form-label"
                        >
                            Category
                        </label>

                        <select
                            name="category"
                            id="addPoultryExpenseCategory"
                            class="form-select"
                            required
                        >
                            <option value="salary">Salary</option>
                            <option value="logistic">Logistic</option>
                            <option value="fuel">Fuel</option>
                            <option value="misc">Miscellaneous</option>
                        </select>
                    </div>


                    <div class="mb-3">
                        <label
                            for="addPoultryExpenseUnit"
                            class="form-label"
                        >
                            Unit
                        </label>

                        <input
                            type="number"
                            name="unit"
                            id="addPoultryExpenseUnit"
                            class="form-control"
                            step="0.01"
                            min="0.01"
                            value="1"
                            required
                        >
                    </div>


                    <div class="mb-3">
                        <label
                            for="addPoultryExpenseAmount"
                            class="form-label"
                        >
                            Amount (₦)
                        </label>

                        <input
                            type="number"
                            name="amount"
                            id="addPoultryExpenseAmount"
                            class="form-control"
                            step="0.01"
                            min="0.01"
                            required
                        >
                    </div>


                    <div class="mb-3">
                        <label
                            for="addPoultryExpenseDescription"
                            class="form-label"
                        >
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="addPoultryExpenseDescription"
                            class="form-control"
                            rows="3"
                            placeholder="Describe the Poultry expense"
                        ></textarea>
                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        name="add_expense"
                        class="btn btn-primary"
                    >
                        Save Expense
                    </button>

                </div>

            </form>

        </div>

    </div>
</div>

<?php endif; ?>


<div
    class="d-none"
    id="poultryExpensesHubConfig"
    data-active-tab="<?php echo app_attr($activeTab); ?>"
    data-csrf-token="<?php echo app_attr(csrf_token()); ?>"
></div>


<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"
></script>

<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"
></script>

<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/poultry-expenses.js'); ?>"
></script>

</body>
</html>
