<?php

declare(strict_types=1);

/*
 * V3.0.1 Canonical Farm Expense Category Authority
 *
 * One source of truth for:
 * - persisted farm_expenses.category keys;
 * - farmer-facing labels;
 * - normal manual expense entry categories;
 * - Slaughter Processing expense subset;
 * - historical Inventory-owned categories retained for old records/reporting.
 *
 * Pages/services must consume this authority instead of maintaining their own
 * duplicated arrays or <option> lists.
 */

if (!function_exists('expense_category_catalog')) {
    function expense_category_catalog(): array
    {
        return [
            /*
             * Historical categories.
             *
             * Existing farm_expenses rows remain valid and reportable.
             * New feed/medication purchases belong to canonical Inventory.
             */
            'feeds' => [
                'label' => 'Feed',
                'manual' => false,
                'slaughter_processing' => false,
                'historical_only' => true,
            ],

            'medication' => [
                'label' => 'Medication',
                'manual' => false,
                'slaughter_processing' => false,
                'historical_only' => true,
            ],

            /*
             * Canonical non-stock expense categories.
             */
            'salary' => [
                'label' => 'Salary / Wages',
                'manual' => true,
                'slaughter_processing' => false,
                'historical_only' => false,
            ],

            'labour' => [
                'label' => 'Labour Cost',
                'manual' => true,
                'slaughter_processing' => true,
                'historical_only' => false,
            ],

            'logistic' => [
                'label' => 'Logistics / Transport',
                'manual' => true,
                'slaughter_processing' => true,
                'historical_only' => false,
            ],

            'fuel' => [
                'label' => 'Fuel / Energy',
                'manual' => true,
                'slaughter_processing' => true,
                'historical_only' => false,
            ],

            'processing_materials' => [
                'label' => 'Processing Materials',
                'manual' => true,
                'slaughter_processing' => true,
                'historical_only' => false,
            ],

            'misc' => [
                'label' => 'Miscellaneous',
                'manual' => true,
                'slaughter_processing' => true,
                'historical_only' => false,
            ],
        ];
    }
}


if (!function_exists('expense_category_options')) {
    function expense_category_options(
        string $surface = 'manual'
    ): array {
        $surface =
            strtolower(
                trim($surface)
            );

        if (
            !in_array(
                $surface,
                [
                    'manual',
                    'slaughter_processing',
                    'report',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported expense-category surface.'
            );
        }

        $options = [];

        foreach (
            expense_category_catalog()
            as $key => $meta
        ) {
            $include =
                $surface === 'report'
                ||
                !empty(
                    $meta[$surface]
                );

            if (!$include) {
                continue;
            }

            $options[$key] =
                (string)$meta['label'];
        }

        return $options;
    }
}


if (!function_exists('expense_category_keys')) {
    function expense_category_keys(
        string $surface = 'manual'
    ): array {
        return
            array_keys(
                expense_category_options(
                    $surface
                )
            );
    }
}


if (!function_exists('expense_category_exists')) {
    function expense_category_exists(
        string $category
    ): bool {
        $category =
            strtolower(
                trim($category)
            );

        return
            array_key_exists(
                $category,
                expense_category_catalog()
            );
    }
}


if (!function_exists('expense_category_label')) {
    function expense_category_label(
        string $category
    ): string {
        $category =
            strtolower(
                trim($category)
            );

        $catalog =
            expense_category_catalog();

        if (
            isset(
                $catalog[$category]
            )
        ) {
            return
                (string)$catalog[
                    $category
                ]['label'];
        }

        return
            ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $category
                )
            );
    }
}


if (!function_exists('expense_category_is_historical')) {
    function expense_category_is_historical(
        string $category
    ): bool {
        $category =
            strtolower(
                trim($category)
            );

        $catalog =
            expense_category_catalog();

        return
            isset(
                $catalog[$category]
            )
            &&
            !empty(
                $catalog[
                    $category
                ]['historical_only']
            );
    }
}


if (!function_exists('expense_category_normalize')) {
    function expense_category_normalize(
        $value,
        string $surface = 'manual'
    ): string {
        $category =
            strtolower(
                trim(
                    (string)$value
                )
            );

        $allowed =
            expense_category_options(
                $surface
            );

        if (
            !array_key_exists(
                $category,
                $allowed
            )
        ) {
            if (
                $surface
                ===
                'slaughter_processing'
            ) {
                throw new InvalidArgumentException(
                    'Choose a valid processing expense category: Labour Cost, Logistics / Transport, Fuel / Energy, Processing Materials or Miscellaneous.'
                );
            }

            throw new InvalidArgumentException(
                'Stock purchases such as feed, medication and vaccines are recorded through Inventory. Choose a valid non-stock expense category.'
            );
        }

        return $category;
    }
}


if (!function_exists('expense_category_normalize_for_update')) {
    /**
     * Normalize an expense category for an existing farm_expenses row.
     *
     * Historical Inventory-owned categories may remain only when the edit
     * preserves that same persisted category. A historical row may still be
     * moved deliberately into any current manual non-stock category.
     *
     * All other requested categories use the same canonical manual-entry
     * authority as new expense creation.
     */
    function expense_category_normalize_for_update(
        $value,
        $existingValue
    ): string {
        $category =
            strtolower(
                trim(
                    (string)$value
                )
            );

        $existingCategory =
            strtolower(
                trim(
                    (string)$existingValue
                )
            );

        if (
            $category !== ''
            &&
            $category === $existingCategory
            &&
            expense_category_is_historical(
                $category
            )
        ) {
            return
                $category;
        }

        return
            expense_category_normalize(
                $category,
                'manual'
            );
    }
}
