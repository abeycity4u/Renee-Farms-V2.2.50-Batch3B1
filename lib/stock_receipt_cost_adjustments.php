<?php

/**
 * Append-only receipt-cost adjustment policy.
 *
 * Physical stock transactions remain immutable. When a posted receipt's
 * monetary total needs a proven correction, the original stock transaction
 * stays unchanged and a separate signed adjustment is appended here.
 *
 * Current valuation and historical costing must use the effective receipt
 * total: original posted total + all append-only receipt-cost adjustments.
 */

if (!function_exists(
    'stock_receipt_cost_adjustment_total_sql'
)) {
    function stock_receipt_cost_adjustment_total_sql(
        string $transactionAlias = 't'
    ): string {
        $alias =
            preg_replace(
                '/[^A-Za-z0-9_]/',
                '',
                $transactionAlias
            );

        if ($alias === '') {
            $alias = 't';
        }

        return
            "(SELECT COALESCE(SUM(rca.amount_delta),0)
              FROM stock_receipt_cost_adjustments rca
              WHERE rca.farm_id={$alias}.farm_id
                AND rca.stock_transaction_id={$alias}.id)";
    }
}

if (!function_exists(
    'stock_receipt_effective_total_cost_sql'
)) {
    function stock_receipt_effective_total_cost_sql(
        string $transactionAlias = 't'
    ): string {
        $alias =
            preg_replace(
                '/[^A-Za-z0-9_]/',
                '',
                $transactionAlias
            );

        if ($alias === '') {
            $alias = 't';
        }

        $adjustment =
            stock_receipt_cost_adjustment_total_sql(
                $alias
            );

        return
            "(CASE
                WHEN {$alias}.total_cost IS NULL
                    THEN NULL
                ELSE ROUND(
                    {$alias}.total_cost
                    + {$adjustment},
                    2
                )
              END)";
    }
}
